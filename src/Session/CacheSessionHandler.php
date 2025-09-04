<?php

declare(strict_types=1);

namespace Mcp\Server\Session;

use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Defaults\SystemClock;
use Psr\SimpleCache\CacheInterface;
use Psr\Clock\ClockInterface;

final class CacheSessionHandler implements SessionHandlerInterface
{
    private const string SESSION_INDEX_KEY = 'mcp_session_index';

    private array $sessionIndex;

    public function __construct(
        public readonly CacheInterface $cache,
        public readonly int $ttl = 3600,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
        $this->sessionIndex = $this->cache->get(self::SESSION_INDEX_KEY, []);
    }

    public function read(string $id): string|false
    {
        $session = $this->cache->get($id, false);
        if ($session === false) {
            if (isset($this->sessionIndex[$id])) {
                unset($this->sessionIndex[$id]);
                $this->cache->set(self::SESSION_INDEX_KEY, $this->sessionIndex);
            }
            return false;
        }

        if (!isset($this->sessionIndex[$id])) {
            $this->sessionIndex[$id] = $this->clock->now()->getTimestamp();
            $this->cache->set(self::SESSION_INDEX_KEY, $this->sessionIndex);
            return $session;
        }

        if ($this->clock->now()->getTimestamp() - $this->sessionIndex[$id] > $this->ttl) {
            $this->cache->delete($id);
            return false;
        }

        return $session;
    }

    public function write(string $id, string $data): bool
    {
        $this->sessionIndex[$id] = $this->clock->now()->getTimestamp();
        $this->cache->set(self::SESSION_INDEX_KEY, $this->sessionIndex);
        return $this->cache->set($id, $data);
    }

    public function destroy(string $id): bool
    {
        unset($this->sessionIndex[$id]);
        $this->cache->set(self::SESSION_INDEX_KEY, $this->sessionIndex);
        return $this->cache->delete($id);
    }

    public function gc(int $maxLifetime): array
    {
        $currentTime = $this->clock->now()->getTimestamp();
        $deletedSessions = [];

        foreach ($this->sessionIndex as $sessionId => $timestamp) {
            if ($currentTime - $timestamp > $maxLifetime) {
                $this->cache->delete($sessionId);
                unset($this->sessionIndex[$sessionId]);
                $deletedSessions[] = $sessionId;
            }
        }

        $this->cache->set(self::SESSION_INDEX_KEY, $this->sessionIndex);

        return $deletedSessions;
    }
}
