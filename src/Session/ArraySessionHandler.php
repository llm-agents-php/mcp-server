<?php

declare(strict_types=1);

namespace Mcp\Server\Session;

use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Defaults\SystemClock;
use Psr\Clock\ClockInterface;

final class ArraySessionHandler implements SessionHandlerInterface
{
    /**
     * @var array<string, array{
     *     data: string,
     *     timestamp: int
     * }>
     */
    protected array $store = [];

    public function __construct(
        public readonly int $ttl = 3600,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {}

    public function read(string $id): string|false
    {
        $session = $this->store[$id] ?? null;
        if ($session === null) {
            return false;
        }

        $currentTimestamp = $this->clock->now()->getTimestamp();

        if ($currentTimestamp - $session['timestamp'] > $this->ttl) {
            unset($this->store[$id]);
            return false;
        }

        return $session['data'];
    }

    public function write(string $id, string $data): bool
    {
        $this->store[$id] = [
            'data' => $data,
            'timestamp' => $this->clock->now()->getTimestamp(),
        ];

        return true;
    }

    public function destroy(string $id): bool
    {
        if (isset($this->store[$id])) {
            unset($this->store[$id]);
        }

        return true;
    }

    public function gc(int $maxLifetime): array
    {
        $currentTimestamp = $this->clock->now()->getTimestamp();
        $deletedSessions = [];

        foreach ($this->store as $sessionId => $session) {
            if ($currentTimestamp - $session['timestamp'] > $maxLifetime) {
                unset($this->store[$sessionId]);
                $deletedSessions[] = $sessionId;
            }
        }

        return $deletedSessions;
    }
}
