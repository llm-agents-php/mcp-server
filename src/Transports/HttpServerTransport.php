<?php

declare(strict_types=1);

namespace Mcp\Server\Transports;

use Evenement\EventEmitterInterface;
use Mcp\Server\Contracts\HttpServerInterface;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Contracts\SessionIdGeneratorInterface;
use Mcp\Server\Exception\TransportException;
use Mcp\Server\Session\SessionIdGenerator;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Message;
use PhpMcp\Schema\JsonRpc\Parser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Http\Message\Response;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

final class HttpServerTransport implements ServerTransportInterface
{
    private readonly string $ssePath;
    private readonly string $messagePath;

    /** @var array<string, ThroughStream> sessionId => SSE Stream */
    private array $activeSseStreams = [];

    public function __construct(
        private readonly HttpServerInterface $httpServer,
        private readonly SessionIdGeneratorInterface $sessionId = new SessionIdGenerator(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->ssePath = '/' . \trim($httpServer->mcpPath(), '/') . '/sse';
        $this->messagePath = '/' . \trim($httpServer->mcpPath(), '/') . '/message';
    }

    public function listen(): void
    {
        $this->httpServer->listen(
            onRequest: $this->createRequestHandler(...),
            onClose: $this->close(...),
        );
    }

    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        if (!isset($this->activeSseStreams[$sessionId])) {
            return reject(new TransportException("Cannot send message: Client '{$sessionId}' not connected via SSE."));
        }

        $stream = $this->activeSseStreams[$sessionId];
        if (!$stream->isWritable()) {
            return reject(
                new TransportException("Cannot send message: SSE stream for client '{$sessionId}' is not writable."),
            );
        }

        $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === '') {
            return resolve(null);
        }

        $deferred = new Deferred();
        $written = $this->sendSseEvent($stream, 'message', $json);

        if ($written) {
            $deferred->resolve(null);
        } else {
            $this->logger->debug('SSE stream buffer full, waiting for drain.', ['sessionId' => $sessionId]);
            $stream->once('drain', function () use ($deferred, $sessionId): void {
                $this->logger->debug('SSE stream drained.', ['sessionId' => $sessionId]);
                $deferred->resolve(null);
            });
        }

        return $deferred->promise();
    }

    public function close(): void
    {
        $activeStreams = $this->activeSseStreams;
        $this->activeSseStreams = [];
        foreach ($activeStreams as $sessionId => $stream) {
            $this->logger->debug('Closing active SSE stream', ['sessionId' => $sessionId]);
            unset($this->activeSseStreams[$sessionId]);
            $stream->close();
        }
    }

    public function on($event, callable $listener)
    {
        return $this->httpServer->on($event, $listener);
    }

    public function once($event, callable $listener)
    {
        return $this->httpServer->once($event, $listener);
    }

    public function removeListener($event, callable $listener)
    {
        return $this->httpServer->removeListener($event, $listener);
    }

    public function removeAllListeners($event = null)
    {
        return $this->httpServer->removeAllListeners($event);
    }

    public function listeners($event = null)
    {
        return $this->httpServer->listeners($event);
    }

    public function emit($event, array $arguments = [])
    {
        return $this->httpServer->emit($event, $arguments);
    }

    private function createRequestHandler(): callable
    {
        return function (ServerRequestInterface $request) {
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();

            if ($method === 'GET' && $path === $this->ssePath) {
                return $this->handleSseRequest($request);
            }

            if ($method === 'POST' && $path === $this->messagePath) {
                return $this->handleMessagePostRequest($request);
            }

            $this->logger->debug('404 Not Found', ['method' => $method, 'path' => $path]);

            return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
        };
    }

    /**
     * Handles a new SSE connection request
     */
    private function handleSseRequest(ServerRequestInterface $request): Response
    {
        $sessionId = $this->sessionId->generate();
        $this->logger->info('New SSE connection', ['sessionId' => $sessionId]);

        $sseStream = new ThroughStream();

        $sseStream->on('close', function () use ($sessionId): void {
            $this->logger->info('SSE stream closed', ['sessionId' => $sessionId]);
            unset($this->activeSseStreams[$sessionId]);
            $this->httpServer->emit('client_disconnected', [$sessionId, 'SSE stream closed']);
        });

        $sseStream->on('error', function (\Throwable $error) use ($sessionId): void {
            $this->logger->warning('SSE stream error', ['sessionId' => $sessionId, 'error' => $error->getMessage()]);
            unset($this->activeSseStreams[$sessionId]);
            $this->emit(
                'error',
                [new TransportException("SSE Stream Error: {$error->getMessage()}", 0, $error), $sessionId],
            );
            $this->emit('client_disconnected', [$sessionId, 'SSE stream error']);
        });

        $this->activeSseStreams[$sessionId] = $sseStream;

        $this->httpServer->onTick(
            function (EventEmitterInterface $events) use ($sessionId, $request, $sseStream): void {
                if (!isset($this->activeSseStreams[$sessionId]) || !$sseStream->isWritable()) {
                    $this->logger->warning(
                        'Cannot send initial endpoint event, stream closed/invalid early.',
                        ['sessionId' => $sessionId],
                    );

                    return;
                }

                try {
                    $baseUri = $request->getUri()->withPath($this->messagePath)->withQuery('')->withFragment('');
                    $postEndpointWithId = (string) $baseUri->withQuery("clientId={$sessionId}");
                    $this->sendSseEvent($sseStream, 'endpoint', $postEndpointWithId, "init-{$sessionId}");

                    $events->emit('client_connected', [$sessionId]);
                } catch (\Throwable $e) {
                    $this->logger->error(
                        'Error sending initial endpoint event',
                        ['sessionId' => $sessionId, 'exception' => $e],
                    );

                    $sseStream->close();
                }
            },
        );

        return new Response(
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
                'Access-Control-Allow-Origin' => '*',
            ],
            $sseStream,
        );
    }

    /**
     * Handles incoming POST requests with messages
     */
    private function handleMessagePostRequest(ServerRequestInterface $request): Response
    {
        $queryParams = $request->getQueryParams();
        $sessionId = $queryParams['clientId'] ?? null;
        $jsonEncodeFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (!$sessionId || !\is_string($sessionId)) {
            $this->logger->warning('Received POST without valid clientId query parameter.');
            $error = Error::forInvalidRequest('Missing or invalid clientId query parameter');

            return new Response(400, ['Content-Type' => 'application/json'], \json_encode($error, $jsonEncodeFlags));
        }

        if (!isset($this->activeSseStreams[$sessionId])) {
            $this->logger->warning('Received POST for unknown or disconnected sessionId.', ['sessionId' => $sessionId]);

            $error = Error::forInvalidRequest('Session ID not found or disconnected');

            return new Response(404, ['Content-Type' => 'application/json'], \json_encode($error, $jsonEncodeFlags));
        }

        if (!\str_contains(\strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            $error = Error::forInvalidRequest('Content-Type must be application/json');

            return new Response(415, ['Content-Type' => 'application/json'], \json_encode($error, $jsonEncodeFlags));
        }

        $body = $request->getBody()->getContents();

        if (empty($body)) {
            $this->logger->warning('Received empty POST body', ['sessionId' => $sessionId]);

            $error = Error::forInvalidRequest('Empty request body');

            return new Response(400, ['Content-Type' => 'application/json'], \json_encode($error, $jsonEncodeFlags));
        }

        try {
            $message = Parser::parse($body);
        } catch (\Throwable $e) {
            $this->logger->error('Error parsing message', ['sessionId' => $sessionId, 'exception' => $e]);

            $error = Error::forParseError('Invalid JSON-RPC message: ' . $e->getMessage());

            return new Response(400, ['Content-Type' => 'application/json'], \json_encode($error, $jsonEncodeFlags));
        }

        $context = [
            'request' => $request,
        ];

        $this->emit('message', [$message, $sessionId, $context]);

        return new Response(202, ['Content-Type' => 'text/plain'], 'Accepted');
    }

    /**
     * Helper to format and write an SSE event
     */
    private function sendSseEvent(
        WritableStreamInterface $stream,
        string $event,
        string $data,
        ?string $id = null,
    ): bool {
        if (!$stream->isWritable()) {
            return false;
        }

        $frame = "event: {$event}\n";
        if ($id !== null) {
            $frame .= "id: {$id}\n";
        }

        $lines = \explode("\n", $data);
        foreach ($lines as $line) {
            $frame .= "data: {$line}\n";
        }
        $frame .= "\n"; // End of event

        $this->logger->debug('Sending SSE event', ['event' => $event, 'frame' => $frame]);

        return $stream->write($frame);
    }
}
