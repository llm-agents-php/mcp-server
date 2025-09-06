<?php

declare(strict_types=1);

namespace Mcp\Server\Transports;

use Evenement\EventEmitterInterface;
use Mcp\Server\Contracts\EventStoreInterface;
use Mcp\Server\Contracts\HttpServerInterface;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Contracts\SessionIdGeneratorInterface;
use Mcp\Server\Exception\McpServerException;
use Mcp\Server\Exception\TransportException;
use Mcp\Server\Session\SessionIdGenerator;
use PhpMcp\Schema\JsonRpc\BatchRequest;
use PhpMcp\Schema\JsonRpc\BatchResponse;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Message;
use PhpMcp\Schema\JsonRpc\Parser;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Http\Message\Response as HttpResponse;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\ThroughStream;

use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * @psalm-suppress
 */
final class StreamableHttpServerTransport implements ServerTransportInterface
{
    /**
     * Stores Deferred objects for POST requests awaiting a direct JSON response.
     * Keyed by a unique pendingRequestId.
     * @var array<string, Deferred>
     */
    private array $pendingRequests = [];

    /**
     * Stores active SSE streams.
     * Key: streamId
     * Value: ['stream' => ThroughStream, 'sessionId' => string, 'context' => array]
     * @var array<string, array{stream: ThroughStream, sessionId: string, context: array}>
     */
    private array $activeSseStreams = [];

    private ?ThroughStream $getStream = null;

    /**
     * @param bool $enableJsonResponse If true, the server will return JSON responses instead of starting an SSE stream.
     * @param bool $stateless If true, the server will not emit client_connected events.
     * @param EventStoreInterface $eventStore If provided, the server will replay events to the client.
     */
    public function __construct(
        private readonly HttpServerInterface $httpServer,
        private readonly SessionIdGeneratorInterface $sessionId = new SessionIdGenerator(),
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $enableJsonResponse = true,
        private readonly bool $stateless = false,
        private readonly ?EventStoreInterface $eventStore = null,
    ) {}

    public function listen(): void
    {
        $this->httpServer->listen(
            onRequest: $this->createRequestHandler(...),
            onClose: $this->close(...),
        );
    }

    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        if ($this->httpServer->isClosing()) {
            return reject(new TransportException('Transport is closing.'));
        }

        $isInitializeResponse = ($context['is_initialize_request'] ?? false) && ($message instanceof Response);

        switch ($context['type'] ?? null) {
            case 'post_202_sent':
                return resolve(null);

            case 'post_sse':
                $streamId = $context['streamId'];
                if (!isset($this->activeSseStreams[$streamId])) {
                    $this->logger->error(
                        "SSE stream for POST not found.",
                        ['streamId' => $streamId, 'sessionId' => $sessionId],
                    );
                    return reject(new TransportException("SSE stream {$streamId} not found for POST response."));
                }

                $stream = $this->activeSseStreams[$streamId]['stream'];
                if (!$stream->isWritable()) {
                    $this->logger->warning(
                        "SSE stream for POST is not writable.",
                        ['streamId' => $streamId, 'sessionId' => $sessionId],
                    );
                    return reject(new TransportException("SSE stream {$streamId} for POST is not writable."));
                }

                $sentCountThisCall = 0;

                if ($message instanceof Response || $message instanceof Error) {
                    $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $eventId = $this->eventStore?->storeEvent($streamId, $json);
                    $this->sendSseEventToStream($stream, $json, $eventId);
                    $sentCountThisCall = 1;
                } elseif ($message instanceof BatchResponse) {
                    foreach ($message->getAll() as $singleResponse) {
                        $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $eventId = $this->eventStore?->storeEvent($streamId, $json);
                        $this->sendSseEventToStream($stream, $json, $eventId);
                        $sentCountThisCall++;
                    }
                }

                if (isset($this->activeSseStreams[$streamId]['context'])) {
                    $this->activeSseStreams[$streamId]['context']['nResponses'] += $sentCountThisCall;
                    if ($this->activeSseStreams[$streamId]['context']['nResponses'] >= $this->activeSseStreams[$streamId]['context']['nRequests']) {
                        $this->logger->info(
                            "All expected responses sent for POST SSE stream. Closing.",
                            ['streamId' => $streamId, 'sessionId' => $sessionId],
                        );
                        $stream->end(); // Will trigger 'close' event.

                        if ($context['stateless'] ?? false) {
                            $this->httpServer->onTick(
                                static function (EventEmitterInterface $events) use ($sessionId): void {
                                    $events->emit('client_disconnected', [$sessionId, 'Stateless request completed']);
                                },
                            );
                        }
                    }
                }

                return resolve(null);

            case 'post_json':
                $pendingRequestId = $context['pending_request_id'];
                if (!isset($this->pendingRequests[$pendingRequestId])) {
                    $this->logger->error(
                        "Pending direct JSON request not found.",
                        ['pending_request_id' => $pendingRequestId, 'session_id' => $sessionId],
                    );
                    return reject(new TransportException("Pending request {$pendingRequestId} not found."));
                }

                $deferred = $this->pendingRequests[$pendingRequestId];
                unset($this->pendingRequests[$pendingRequestId]);

                $responseBody = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $headers = ['Content-Type' => 'application/json'];
                if ($isInitializeResponse && !$this->stateless) {
                    $headers['Mcp-Session-Id'] = $sessionId;
                }

                $statusCode = $context['status_code'] ?? 200;
                $deferred->resolve(new HttpResponse($statusCode, $headers, $responseBody . "\n"));

                if ($context['stateless'] ?? false) {
                    $this->httpServer->onTick(static function (EventEmitterInterface $events) use ($sessionId): void {
                        $events->emit('client_disconnected', [$sessionId, 'Stateless request completed']);
                    });
                }

                return resolve(null);

            default:
                if ($this->getStream === null) {
                    $this->logger->error("GET SSE stream not found.", ['sessionId' => $sessionId]);
                    return reject(new TransportException("GET SSE stream not found."));
                }

                if (!$this->getStream->isWritable()) {
                    $this->logger->warning("GET SSE stream is not writable.", ['sessionId' => $sessionId]);
                    return reject(new TransportException("GET SSE stream not writable."));
                }
                if ($message instanceof Response || $message instanceof Error) {
                    $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $eventId = $this->eventStore?->storeEvent('GET_STREAM', $json);
                    $this->sendSseEventToStream($this->getStream, $json, $eventId);
                } elseif ($message instanceof BatchResponse) {
                    foreach ($message->getAll() as $singleResponse) {
                        $json = \json_encode($singleResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $eventId = $this->eventStore?->storeEvent('GET_STREAM', $json);
                        $this->sendSseEventToStream($this->getStream, $json, $eventId);
                    }
                }
                return resolve(null);
        }
    }

    public function close(): void
    {
        foreach ($this->activeSseStreams as $streamInfo) {
            if ($streamInfo['stream']->isWritable()) {
                $streamInfo['stream']->end();
            }
        }

        if ($this->getStream !== null) {
            $this->getStream->end();
            $this->getStream = null;
        }

        foreach ($this->pendingRequests as $deferred) {
            $deferred->reject(new TransportException('Transport is closing.'));
        }

        $this->activeSseStreams = [];
        $this->pendingRequests = [];
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

            if ($path !== $this->httpServer->mcpPath()) {
                $error = Error::forInvalidRequest("Not found: {$path}");
                return new HttpResponse(404, ['Content-Type' => 'application/json'], \json_encode($error));
            }

            try {
                return match ($method) {
                    'GET' => $this->handleGetRequest($request),
                    'POST' => $this->handlePostRequest($request),
                    'DELETE' => $this->handleDeleteRequest($request),
                    default => $this->handleUnsupportedRequest($request),
                };
            } catch (\Throwable $e) {
                return $this->handleRequestError($e, $request);
            }
        };
    }

    private function handleGetRequest(ServerRequestInterface $request): PromiseInterface
    {
        if ($this->stateless) {
            $error = Error::forInvalidRequest("GET requests (SSE streaming) are not supported in stateless mode.");
            return resolve(new HttpResponse(405, ['Content-Type' => 'application/json'], \json_encode($error)));
        }

        $acceptHeader = $request->getHeaderLine('Accept');
        if (!\str_contains($acceptHeader, 'text/event-stream')) {
            $error = Error::forInvalidRequest("Not Acceptable: Client must accept text/event-stream for GET requests.");
            return resolve(new HttpResponse(406, ['Content-Type' => 'application/json'], \json_encode($error)));
        }

        $sessionId = $request->getHeaderLine('Mcp-Session-Id');
        if (empty($sessionId)) {
            $this->logger->warning("GET request without Mcp-Session-Id.");
            $error = Error::forInvalidRequest("Mcp-Session-Id header required for GET requests.");
            return resolve(new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)));
        }

        $this->getStream = new ThroughStream();

        $this->getStream->on('close', function () use ($sessionId): void {
            $this->logger->debug("GET SSE stream closed.", ['sessionId' => $sessionId]);
            $this->getStream = null;
        });

        $this->getStream->on('error', function (\Throwable $e) use ($sessionId): void {
            $this->logger->error("GET SSE stream error.", ['sessionId' => $sessionId, 'error' => $e->getMessage()]);
            $this->getStream = null;
        });

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        $response = new HttpResponse(200, $headers, $this->getStream);

        if ($this->eventStore) {
            $lastEventId = $request->getHeaderLine('Last-Event-ID');
            $this->replayEvents($lastEventId, $this->getStream, $sessionId);
        }

        return resolve($response);
    }

    private function handlePostRequest(ServerRequestInterface $request): PromiseInterface
    {
        $deferred = new Deferred();

        $acceptHeader = $request->getHeaderLine('Accept');
        if (!\str_contains($acceptHeader, 'application/json') && !\str_contains($acceptHeader, 'text/event-stream')) {
            $error = Error::forInvalidRequest(
                "Not Acceptable: Client must accept both application/json or text/event-stream",
            );
            $deferred->resolve(new HttpResponse(406, ['Content-Type' => 'application/json'], \json_encode($error)));
            return $deferred->promise();
        }

        if (!\str_contains($request->getHeaderLine('Content-Type'), 'application/json')) {
            $error = Error::forInvalidRequest("Unsupported Media Type: Content-Type must be application/json");
            $deferred->resolve(new HttpResponse(415, ['Content-Type' => 'application/json'], \json_encode($error)));
            return $deferred->promise();
        }

        $body = $request->getBody()->getContents();

        if (empty($body)) {
            $this->logger->warning("Received empty POST body");
            $error = Error::forInvalidRequest("Empty request body.");
            $deferred->resolve(new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)));
            return $deferred->promise();
        }

        try {
            $message = Parser::parse($body);
        } catch (\Throwable $e) {
            $this->logger->error("Failed to parse MCP message from POST body", ['error' => $e->getMessage()]);
            $error = Error::forParseError("Invalid JSON: " . $e->getMessage());
            $deferred->resolve(new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)));
            return $deferred->promise();
        }

        $isInitializeRequest = ($message instanceof Request && $message->method === 'initialize');

        if ($this->stateless) {
            $sessionId = $this->sessionId->generate();
            $this->emit('client_connected', [$sessionId]);
        } else {
            if ($isInitializeRequest) {
                if ($request->hasHeader('Mcp-Session-Id')) {
                    $this->logger->warning(
                        "Client sent Mcp-Session-Id with InitializeRequest. Ignoring.",
                        ['clientSentId' => $request->getHeaderLine('Mcp-Session-Id')],
                    );
                    $error = Error::forInvalidRequest(
                        "Invalid request: Session already initialized. Mcp-Session-Id header not allowed with InitializeRequest.",
                        $message->getId(),
                    );
                    $deferred->resolve(
                        new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)),
                    );
                    return $deferred->promise();
                }

                $sessionId = $this->sessionId->generate();
                $this->emit('client_connected', [$sessionId]);
            } else {
                $sessionId = $request->getHeaderLine('Mcp-Session-Id');

                if (empty($sessionId)) {
                    $this->logger->warning("POST request without Mcp-Session-Id.");
                    $error = Error::forInvalidRequest(
                        "Mcp-Session-Id header required for POST requests.",
                        $message->getId(),
                    );
                    $deferred->resolve(
                        new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)),
                    );
                    return $deferred->promise();
                }
            }
        }

        $context = [
            'is_initialize_request' => $isInitializeRequest,
        ];

        $nRequests = match (true) {
            $message instanceof Request => 1,
            $message instanceof BatchRequest => $message->nRequests(),
            default => 0,
        };

        if ($nRequests === 0) {
            $deferred->resolve(new HttpResponse(202));
            $context['type'] = 'post_202_sent';
        } else {
            if ($this->enableJsonResponse) {
                $pendingRequestId = $this->sessionId->generate();
                $this->pendingRequests[$pendingRequestId] = $deferred;

                $timeoutTimer = $this->httpServer->getLoop()->addTimer(
                    30,
                    function () use ($pendingRequestId, $sessionId): void {
                        if (isset($this->pendingRequests[$pendingRequestId])) {
                            $deferred = $this->pendingRequests[$pendingRequestId];
                            unset($this->pendingRequests[$pendingRequestId]);
                            $this->logger->warning(
                                "Timeout waiting for direct JSON response processing.",
                                ['pending_request_id' => $pendingRequestId, 'session_id' => $sessionId],
                            );
                            $errorResponse = McpServerException::internalError(
                                "Request processing timed out.",
                            )->toJsonRpcError($pendingRequestId);
                            $deferred->resolve(
                                new HttpResponse(
                                    500,
                                    ['Content-Type' => 'application/json'],
                                    \json_encode($errorResponse->toArray()),
                                ),
                            );
                        }
                    },
                );

                $this->pendingRequests[$pendingRequestId]->promise()->finally(function () use ($timeoutTimer): void {
                    $this->httpServer->getLoop()->cancelTimer($timeoutTimer);
                });

                $context['type'] = 'post_json';
                $context['pending_request_id'] = $pendingRequestId;
            } else {
                $streamId = $this->sessionId->generate();
                $sseStream = new ThroughStream();
                $this->activeSseStreams[$streamId] = [
                    'stream' => $sseStream,
                    'sessionId' => $sessionId,
                    'context' => ['nRequests' => $nRequests, 'nResponses' => 0],
                ];

                $sseStream->on('close', function () use ($streamId): void {
                    $this->logger->info(
                        "POST SSE stream closed by client/server.",
                        ['streamId' => $streamId, 'sessionId' => $this->activeSseStreams[$streamId]['sessionId']],
                    );
                    unset($this->activeSseStreams[$streamId]);
                });
                $sseStream->on('error', function (\Throwable $e) use ($streamId): void {
                    $this->logger->error(
                        "POST SSE stream error.",
                        [
                            'streamId' => $streamId,
                            'sessionId' => $this->activeSseStreams[$streamId]['sessionId'],
                            'error' => $e->getMessage(),
                        ],
                    );
                    unset($this->activeSseStreams[$streamId]);
                });

                $headers = [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ];

                if (!empty($sessionId) && !$this->stateless) {
                    $headers['Mcp-Session-Id'] = $sessionId;
                }

                $deferred->resolve(new HttpResponse(200, $headers, $sseStream));
                $context['type'] = 'post_sse';
                $context['streamId'] = $streamId;
                $context['nRequests'] = $nRequests;
            }
        }

        $context['stateless'] = $this->stateless;
        $context['request'] = $request;

        $this->httpServer->onTick(
            static function (EventEmitterInterface $events) use ($message, $sessionId, $context): void {
                $events->emit('message', [$message, $sessionId, $context]);
            },
        );

        return $deferred->promise();
    }

    private function handleDeleteRequest(ServerRequestInterface $request): PromiseInterface
    {
        if ($this->stateless) {
            return resolve(new HttpResponse(204));
        }

        $sessionId = $request->getHeaderLine('Mcp-Session-Id');
        if (empty($sessionId)) {
            $this->logger->warning("DELETE request without Mcp-Session-Id.");
            $error = Error::forInvalidRequest("Mcp-Session-Id header required for DELETE.");
            return resolve(new HttpResponse(400, ['Content-Type' => 'application/json'], \json_encode($error)));
        }

        $streamsToClose = [];
        foreach ($this->activeSseStreams as $streamId => $streamInfo) {
            if ($streamInfo['sessionId'] === $sessionId) {
                $streamsToClose[] = $streamId;
            }
        }

        foreach ($streamsToClose as $streamId) {
            $this->activeSseStreams[$streamId]['stream']->end();
            unset($this->activeSseStreams[$streamId]);
        }

        if ($this->getStream !== null) {
            $this->getStream->end();
            $this->getStream = null;
        }

        $this->emit('client_disconnected', [$sessionId, 'Session terminated by DELETE request']);

        return resolve(new HttpResponse(204));
    }

    private function handleUnsupportedRequest(ServerRequestInterface $request): HttpResponse
    {
        $error = Error::forInvalidRequest("Method not allowed: {$request->getMethod()}");
        $headers = [
            'Content-Type' => 'application/json',
            'Allow' => 'GET, POST, DELETE, OPTIONS',
        ];
        return new HttpResponse(405, $headers, \json_encode($error));
    }

    private function handleRequestError(\Throwable $e, ServerRequestInterface $request): HttpResponse
    {
        $this->logger->error("Error processing HTTP request", [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'exception' => $e->getMessage(),
        ]);

        if ($e instanceof TransportException) {
            $error = Error::forInternalError("Transport Error: " . $e->getMessage());
            return new HttpResponse(500, ['Content-Type' => 'application/json'], \json_encode($error));
        }

        $error = Error::forInternalError("Internal Server Error during HTTP request processing.");
        return new HttpResponse(500, ['Content-Type' => 'application/json'], \json_encode($error));
    }

    private function replayEvents(string $lastEventId, ThroughStream $sseStream, string $sessionId): void
    {
        if (empty($lastEventId)) {
            return;
        }

        try {
            $this->eventStore->replayEventsAfter(
                $lastEventId,
                function (string $replayedEventId, string $json) use ($sseStream): void {
                    $this->logger->debug("Replaying event", ['replayedEventId' => $replayedEventId]);
                    $this->sendSseEventToStream($sseStream, $json, $replayedEventId);
                },
            );
        } catch (\Throwable $e) {
            $this->logger->error("Error during event replay.", ['sessionId' => $sessionId, 'exception' => $e]);
        }
    }

    private function sendSseEventToStream(ThroughStream $stream, string $data, ?string $eventId = null): bool
    {
        if (!$stream->isWritable()) {
            return false;
        }

        $frame = "event: message\n";
        if ($eventId !== null) {
            $frame .= "id: {$eventId}\n";
        }

        $lines = \explode("\n", $data);
        foreach ($lines as $line) {
            $frame .= "data: {$line}\n";
        }
        $frame .= "\n";

        return $stream->write($frame);
    }
}
