<?php

declare(strict_types=1);

namespace Mcp\Server\Transports;

use Evenement\EventEmitter;
use Mcp\Server\Contracts\HttpServerInterface;
use Mcp\Server\Exception\TransportException;
use Mcp\Server\Transports\Middleware\MiddlewareUtils;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface;
use React\Socket\SocketServer;

final class HttpServer extends EventEmitter implements HttpServerInterface
{
    public readonly ServerInterface $socket;
    public readonly string $mcpPath;
    public readonly string $protocol;
    public readonly string $listenAddress;
    private bool $listening = false;
    private bool $closing = false;

    /**
     * @param string $host Host to bind to (e.g., '127.0.0.1', '0.0.0.0').
     * @param int $port Port to listen on (e.g., 8080).
     * @param string $mcpPath URL prefix for MCP endpoints (e.g., 'mcp').
     * @param array|null $sslContext Optional SSL context options for React SocketServer (for HTTPS).
     * @param array<callable|MiddlewareInterface> $middleware Middleware (React callables or PSR-15) to be applied to the HTTP server.
     * @param bool $runLoop Whether to run the event loop after starting the listener. (If external loop is used, set to false.)
     */
    public function __construct(
        private readonly LoopInterface $loop,
        string $host = '127.0.0.1',
        int $port = 8080,
        string $mcpPath = '/mcp',
        private readonly ?array $sslContext = null,
        private readonly array $middleware = [],
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $runLoop = true,
    ) {
        $this->listenAddress = "{$host}:{$port}";
        $this->protocol = !empty($this->sslContext) ? 'https' : 'http';

        $this->mcpPath = '/' . \trim($mcpPath, '/');

        $this->validateMiddleware();

        $this->socket = new SocketServer(
            $this->listenAddress,
            $this->sslContext ?? [],
            $this->loop,
        );
    }

    public function mcpPath(): string
    {
        return $this->mcpPath;
    }

    /**
     * @throws TransportException
     */
    public function listen(callable $onRequest, callable $onClose): void
    {
        if ($this->listening) {
            throw new TransportException('Transport is already listening.');
        }

        if ($this->closing) {
            throw new TransportException('Cannot listen, transport is closing/closed.');
        }

        try {
            // Convert PSR-15 middleware to React format automatically
            $reactMiddleware = MiddlewareUtils::normalizeMiddleware($this->middleware);
            $handlers = \array_merge($reactMiddleware, [$onRequest]);

            $http = new \React\Http\HttpServer($this->loop, ...$handlers);
            $http->listen($this->socket);

            $this->socket->on('error', function (\Throwable $error) use ($onClose): void {
                $this->logger->error('Socket server error.', ['error' => $error->getMessage()]);
                $this->emit(
                    'error',
                    [new TransportException("Socket server error: {$error->getMessage()}", 0, $error)],
                );
                $this->close($onClose);
            });

            $this->listening = true;
            $this->closing = false;
            $this->emit('ready');

            if ($this->runLoop) {
                $this->loop->run();
            }
        } catch (\Throwable $e) {
            $this->logger->error("Failed to start listener on {$this->listenAddress}", ['exception' => $e]);
            throw new TransportException(
                "Failed to start listener on {$this->listenAddress}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    public function onTick(\Closure $onTick): void
    {
        $this->loop->futureTick(function () use ($onTick): void {
            $onTick($this);
        });
    }

    public function isClosing(): bool
    {
        return $this->closing;
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    private function validateMiddleware(): void
    {
        foreach ($this->middleware as $mw) {
            if (!\is_callable($mw) && !$mw instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException(
                    'All provided middlewares must be callable or implement MiddlewareInterface.',
                );
            }
        }
    }

    private function close(callable $onClose): void
    {
        if ($this->closing) {
            return;
        }

        if ($this->runLoop) {
            $this->loop->stop();
        }

        $this->closing = true;
        $this->listening = false;
        $this->logger->info('Closing transport...');

        $this->socket->close();

        $onClose();

        $this->emit('close', ['HttpTransport closed.']);
        $this->removeAllListeners();
    }
}
