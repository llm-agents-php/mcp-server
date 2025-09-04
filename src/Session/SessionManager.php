<?php

declare(strict_types=1);

namespace Mcp\Server\Session;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Contracts\SessionInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class SessionManager implements EventEmitterInterface
{
    use EventEmitterTrait;

    private ?TimerInterface $gcTimer = null;
    private readonly LoopInterface $loop;

    public function __construct(
        private readonly SessionHandlerInterface $handler,
        private readonly LoggerInterface $logger,
        ?LoopInterface $loop = null,
        private readonly int $ttl = 3600,
        private readonly int|float $gcInterval = 300,
    ) {
        $this->loop = $loop ?: Loop::get();
    }

    /**
     * Start the garbage collection timer
     */
    public function startGcTimer(): void
    {
        if ($this->gcTimer !== null) {
            return;
        }

        $this->gcTimer = $this->loop->addPeriodicTimer($this->gcInterval, $this->gc(...));
    }

    public function gc(): array
    {
        $deletedSessions = $this->handler->gc($this->ttl);

        foreach ($deletedSessions as $sessionId) {
            $this->emit('session_deleted', [$sessionId]);
        }

        if (\count($deletedSessions) > 0) {
            $this->logger->debug('Session garbage collection complete', [
                'purged_sessions' => \count($deletedSessions),
            ]);
        }

        return $deletedSessions;
    }

    /**
     * Stop the garbage collection timer
     */
    public function stopGcTimer(): void
    {
        if ($this->gcTimer !== null) {
            $this->loop->cancelTimer($this->gcTimer);
            $this->gcTimer = null;
        }
    }

    /**
     * Create a new session
     */
    public function createSession(string $sessionId): SessionInterface
    {
        $session = new Session($this->handler, $sessionId);

        $session->hydrate([
            'initialized' => false,
            'client_info' => null,
            'protocol_version' => null,
            'subscriptions' => [],      // [uri => true]
            'message_queue' => [],      // string[] (raw JSON-RPC frames)
            'log_level' => null,
        ]);

        $session->save();

        $this->logger->info('Session created', ['sessionId' => $sessionId]);
        $this->emit('session_created', [$sessionId, $session]);

        return $session;
    }

    /**
     * Get an existing session
     */
    public function getSession(string $sessionId): ?SessionInterface
    {
        return Session::retrieve($sessionId, $this->handler);
    }

    public function hasSession(string $sessionId): bool
    {
        return $this->getSession($sessionId) !== null;
    }

    /**
     * Delete a session completely
     */
    public function deleteSession(string $sessionId): bool
    {
        $success = $this->handler->destroy($sessionId);

        if ($success) {
            $this->emit('session_deleted', [$sessionId]);
            $this->logger->info('Session deleted', ['sessionId' => $sessionId]);
        } else {
            $this->logger->warning('Failed to delete session', ['sessionId' => $sessionId]);
        }

        return $success;
    }

    public function queueMessage(string $sessionId, string $message): void
    {
        $session = $this->getSession($sessionId);
        if ($session === null) {
            return;
        }

        $session->queueMessage($message);
        $session->save();
    }

    public function dequeueMessages(string $sessionId): array
    {
        $session = $this->getSession($sessionId);
        if ($session === null) {
            return [];
        }

        $messages = $session->dequeueMessages();
        $session->save();

        return $messages;
    }

    public function hasQueuedMessages(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);
        if ($session === null) {
            return false;
        }

        return $session->hasQueuedMessages();
    }
}
