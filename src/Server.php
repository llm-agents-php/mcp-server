<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Session\SessionManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Core MCP Server instance.
 *
 * Holds the configured MCP logic (Configuration, Registry, Protocol)
 * but is transport-agnostic. It relies on a ServerTransportInterface implementation,
 * provided via the listen() method, to handle network communication.
 */
final class Server
{
    protected bool $isListening = false;

    public function __construct(
        protected readonly Protocol $protocol,
        protected readonly SessionManager $sessionManager,
        protected readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Binds the server's MCP logic to the provided transport and starts the transport's listener,
     * then runs the event loop, making this a BLOCKING call suitable for standalone servers.
     *
     * For framework integration where the loop is managed externally, use `getProtocol()`
     * and bind it to your framework's transport mechanism manually.
     *
     * @param ServerTransportInterface $transport The transport to listen with.
     *
     * @throws \LogicException If called after already listening.
     * @throws \Throwable If transport->listen() fails immediately.
     */
    public function listen(ServerTransportInterface $transport): void
    {
        if ($this->isListening) {
            throw new \LogicException('Server is already listening via a transport.');
        }

        $transport->once('close', function (?string $reason = null): void {
            $this->isListening = false;
            $this->logger->info('Transport closed.', ['reason' => $reason ?? 'N/A']);
            $this->protocol->unbindTransport();
        });

        $this->protocol->bindTransport($transport);

        try {
            $this->isListening = true;
            $this->sessionManager->startGcTimer();
            $transport->listen();
        } catch (\Throwable $e) {
            $this->logger->critical(
                'Failed to start listening or event loop crashed.',
                ['exception' => $e->getMessage()],
            );

            $this->endListen($transport);
            throw $e;
        } finally {
            $this->sessionManager->stopGcTimer();
        }
    }

    public function endListen(ServerTransportInterface $transport): void
    {
        $transport->removeAllListeners('close');
        $transport->close();

        $this->isListening = false;
        $this->logger->info("Server listener shut down.");
    }
}
