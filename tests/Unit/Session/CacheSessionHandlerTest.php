<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Session\CacheSessionHandler;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

final class CacheSessionHandlerTest extends TestCase
{
    private CacheInterface $cache;
    private ClockInterface $clock;
    private CacheSessionHandler $handler;

    protected function setUp(): void
    {
        $this->cache = Mockery::mock(CacheInterface::class);
        $this->clock = Mockery::mock(ClockInterface::class);
        
        // Mock the initial session index retrieval in constructor
        $this->cache->shouldReceive('get')
            ->with('mcp_session_index', [])
            ->andReturn([])
            ->once();
            
        $this->handler = new CacheSessionHandler($this->cache, ttl: 3600, clock: $this->clock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_read_returns_false_when_session_not_exists(): void
    {
        $this->cache->shouldReceive('get')->with('non-existent-id', false)->andReturn(false);

        $result = $this->handler->read('non-existent-id');

        $this->assertFalse($result);
    }

    public function test_read_removes_expired_session_from_index(): void
    {
        $sessionId = 'expired-session';
        
        // Session exists in cache but not in index
        $this->cache->shouldReceive('get')->with($sessionId, false)->andReturn(false);
        $this->cache->shouldReceive('set')->with('mcp_session_index', [])->andReturn(true);

        $result = $this->handler->read($sessionId);

        $this->assertFalse($result);
    }

    public function test_read_adds_session_to_index_if_missing(): void
    {
        $sessionId = 'test-session';
        $sessionData = '{"user": "test"}';
        $currentTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->cache->shouldReceive('get')->with($sessionId, false)->andReturn($sessionData);
        $this->clock->shouldReceive('now')->andReturn($currentTime);
        $this->cache->shouldReceive('set')->with('mcp_session_index', [$sessionId => $currentTime->getTimestamp()])->andReturn(true);

        $result = $this->handler->read($sessionId);

        $this->assertEquals($sessionData, $result);
    }

    public function test_read_returns_false_when_session_expired_by_ttl(): void
    {
        $sessionId = 'expired-session';
        $sessionData = '{"user": "test"}';
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $readTime = new \DateTimeImmutable('2025-01-01 14:00:00'); // 2 hours later

        // Setup handler with existing session in index
        $this->cache->shouldReceive('get')
            ->with('mcp_session_index', [])
            ->andReturn([$sessionId => $writeTime->getTimestamp()])
            ->once();

        $handler = new CacheSessionHandler($this->cache, ttl: 3600, clock: $this->clock);

        $this->cache->shouldReceive('get')->with($sessionId, false)->andReturn($sessionData);
        $this->clock->shouldReceive('now')->andReturn($readTime);
        $this->cache->shouldReceive('delete')->with($sessionId)->andReturn(true);

        $result = $handler->read($sessionId);

        $this->assertFalse($result);
    }

    public function test_read_returns_data_when_session_not_expired(): void
    {
        $sessionId = 'valid-session';
        $sessionData = '{"user": "test"}';
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $readTime = new \DateTimeImmutable('2025-01-01 12:30:00'); // 30 minutes later

        // Setup handler with existing session in index
        $this->cache->shouldReceive('get')
            ->with('mcp_session_index', [])
            ->andReturn([$sessionId => $writeTime->getTimestamp()])
            ->once();

        $handler = new CacheSessionHandler($this->cache, ttl: 3600, clock: $this->clock);

        $this->cache->shouldReceive('get')->with($sessionId, false)->andReturn($sessionData);
        $this->clock->shouldReceive('now')->andReturn($readTime);

        $result = $handler->read($sessionId);

        $this->assertEquals($sessionData, $result);
    }

    public function test_write_updates_session_and_index(): void
    {
        $sessionId = 'test-session';
        $sessionData = '{"user": "test"}';
        $currentTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->clock->shouldReceive('now')->andReturn($currentTime);
        $this->cache->shouldReceive('set')
            ->with('mcp_session_index', [$sessionId => $currentTime->getTimestamp()])
            ->andReturn(true);
        $this->cache->shouldReceive('set')->with($sessionId, $sessionData)->andReturn(true);

        $result = $this->handler->write($sessionId, $sessionData);

        $this->assertTrue($result);
    }

    public function test_destroy_removes_session_from_cache_and_index(): void
    {
        $sessionId = 'test-session';

        $this->cache->shouldReceive('set')->with('mcp_session_index', [])->andReturn(true);
        $this->cache->shouldReceive('delete')->with($sessionId)->andReturn(true);

        $result = $this->handler->destroy($sessionId);

        $this->assertTrue($result);
    }

    public function test_gc_removes_expired_sessions(): void
    {
        $currentTime = new \DateTimeImmutable('2025-01-01 14:00:00');
        $expiredTime1 = new \DateTimeImmutable('2025-01-01 12:00:00'); // 2 hours ago
        $expiredTime2 = new \DateTimeImmutable('2025-01-01 12:30:00'); // 1.5 hours ago
        $validTime = new \DateTimeImmutable('2025-01-01 13:30:00'); // 30 minutes ago

        $initialIndex = [
            'expired1' => $expiredTime1->getTimestamp(),
            'expired2' => $expiredTime2->getTimestamp(),
            'valid' => $validTime->getTimestamp(),
        ];

        // Setup handler with existing sessions in index
        $this->cache->shouldReceive('get')
            ->with('mcp_session_index', [])
            ->andReturn($initialIndex)
            ->once();

        $handler = new CacheSessionHandler($this->cache, ttl: 3600, clock: $this->clock);

        $this->clock->shouldReceive('now')->andReturn($currentTime);
        $this->cache->shouldReceive('delete')->with('expired1')->andReturn(true);
        $this->cache->shouldReceive('delete')->with('expired2')->andReturn(true);
        $this->cache->shouldReceive('set')
            ->with('mcp_session_index', ['valid' => $validTime->getTimestamp()])
            ->andReturn(true);

        $deletedSessions = $handler->gc(3600); // 1 hour maxLifetime

        $this->assertCount(2, $deletedSessions);
        $this->assertContains('expired1', $deletedSessions);
        $this->assertContains('expired2', $deletedSessions);
        $this->assertNotContains('valid', $deletedSessions);
    }

    public function test_gc_returns_empty_array_when_no_expired_sessions(): void
    {
        $currentTime = new \DateTimeImmutable('2025-01-01 12:30:00');
        $validTime = new \DateTimeImmutable('2025-01-01 12:00:00'); // 30 minutes ago

        $initialIndex = ['valid' => $validTime->getTimestamp()];

        // Setup handler with valid session
        $this->cache->shouldReceive('get')
            ->with('mcp_session_index', [])
            ->andReturn($initialIndex)
            ->once();

        $handler = new CacheSessionHandler($this->cache, ttl: 3600, clock: $this->clock);

        $this->clock->shouldReceive('now')->andReturn($currentTime);
        $this->cache->shouldReceive('set')->with('mcp_session_index', $initialIndex)->andReturn(true);

        $deletedSessions = $handler->gc(3600);

        $this->assertEmpty($deletedSessions);
    }

    public function test_constructor_with_default_ttl(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('mcp_session_index', [])->andReturn([]);

        $handler = new CacheSessionHandler($cache);

        $this->assertEquals(3600, $handler->ttl);
    }

    public function test_constructor_with_custom_ttl(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('mcp_session_index', [])->andReturn([]);

        $handler = new CacheSessionHandler($cache, ttl: 7200);

        $this->assertEquals(7200, $handler->ttl);
    }

    public function test_multiple_operations_maintain_index_consistency(): void
    {
        $sessionId1 = 'session1';
        $sessionId2 = 'session2';
        $sessionData1 = '{"user": "alice"}';
        $sessionData2 = '{"user": "bob"}';
        $currentTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $this->clock->shouldReceive('now')->andReturn($currentTime);

        // Write first session
        $this->cache->shouldReceive('set')
            ->with('mcp_session_index', [$sessionId1 => $currentTime->getTimestamp()])
            ->andReturn(true);
        $this->cache->shouldReceive('set')->with($sessionId1, $sessionData1)->andReturn(true);
        $this->handler->write($sessionId1, $sessionData1);

        // Write second session
        $this->cache->shouldReceive('set')
            ->with('mcp_session_index', [
                $sessionId1 => $currentTime->getTimestamp(),
                $sessionId2 => $currentTime->getTimestamp()
            ])
            ->andReturn(true);
        $this->cache->shouldReceive('set')->with($sessionId2, $sessionData2)->andReturn(true);
        $this->handler->write($sessionId2, $sessionData2);

        // Destroy first session
        $this->cache->shouldReceive('set')
            ->with('mcp_session_index', [$sessionId2 => $currentTime->getTimestamp()])
            ->andReturn(true);
        $this->cache->shouldReceive('delete')->with($sessionId1)->andReturn(true);
        $result = $this->handler->destroy($sessionId1);

        $this->assertTrue($result);
    }
}
