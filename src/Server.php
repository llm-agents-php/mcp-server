<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\LoggerAwareInterface;
use Mcp\Server\Contracts\LoopAwareInterface;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Session\SessionManager;

/**
 * Core MCP Server instance.
 *
 * Holds the configured MCP logic (Configuration, Registry, Protocol)
 * but is transport-agnostic. It relies on a ServerTransportInterface implementation,
 * provided via the listen() method, to handle network communication.
 *
 * Instances should be created via the ServerBuilder.
 */
class Server
{
    protected bool $discoveryRan = false;
    protected bool $isListening = false;

    /**
     * @param Configuration $configuration Core configuration and dependencies.
     * @param Registry $registry Holds registered MCP element definitions.
     * @param Protocol $protocol Handles MCP requests and responses.
     * @internal Use ServerBuilder::make()->...->build().
     *
     */
    public function __construct(
        protected readonly Configuration $configuration,
        protected readonly Registry $registry,
        protected readonly Protocol $protocol,
        protected readonly SessionManager $sessionManager,
    ) {}

    public static function make(): ServerBuilder
    {
        return new ServerBuilder();
    }

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
    public function listen(ServerTransportInterface $transport, bool $runLoop = true): void
    {
        if ($this->isListening) {
            throw new \LogicException('Server is already listening via a transport.');
        }

        $this->warnIfNoElements();

        if ($transport instanceof LoggerAwareInterface) {
            $transport->setLogger($this->configuration->logger);
        }
        if ($transport instanceof LoopAwareInterface) {
            $transport->setLoop($this->configuration->loop);
        }

        $protocol = $this->getProtocol();

        $closeHandlerCallback = function (?string $reason = null) use ($protocol): void {
            $this->isListening = false;
            $this->configuration->logger->info('Transport closed.', ['reason' => $reason ?? 'N/A']);
            $protocol->unbindTransport();
            $this->configuration->loop->stop();
        };

        $transport->once('close', $closeHandlerCallback);

        $protocol->bindTransport($transport);

        try {
            $transport->listen();

            $this->isListening = true;

            if ($runLoop) {
                $this->sessionManager->startGcTimer();

                $this->configuration->loop->run();

                $this->endListen($transport);
            }
        } catch (\Throwable $e) {
            $this->configuration->logger->critical(
                'Failed to start listening or event loop crashed.',
                ['exception' => $e->getMessage()],
            );
            $this->endListen($transport);
            throw $e;
        }
    }

    public function endListen(ServerTransportInterface $transport): void
    {
        $protocol = $this->getProtocol();

        $protocol->unbindTransport();

        $this->sessionManager->stopGcTimer();

        $transport->removeAllListeners('close');
        $transport->close();

        $this->isListening = false;
        $this->configuration->logger->info("Server '{$this->configuration->serverInfo->name}' listener shut down.");
    }

    /**
     * Gets the Configuration instance associated with this server.
     */
    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Gets the Registry instance associated with this server.
     */
    public function getRegistry(): Registry
    {
        return $this->registry;
    }

    /**
     * Gets the Protocol instance associated with this server.
     */
    public function getProtocol(): Protocol
    {
        return $this->protocol;
    }

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    /**
     * Warns if no MCP elements are registered and discovery has not been run.
     */
    protected function warnIfNoElements(): void
    {
        if (!$this->registry->hasElements() && !$this->discoveryRan) {
            $this->configuration->logger->warning(
                'Starting listener, but no MCP elements are registered and discovery has not been run. ' .
                'Call $server->discover(...) at least once to find and cache elements before listen().',
            );
        } elseif (!$this->registry->hasElements() && $this->discoveryRan) {
            $this->configuration->logger->warning(
                'Starting listener, but no MCP elements were found after discovery/cache load.',
            );
        }
    }
}
