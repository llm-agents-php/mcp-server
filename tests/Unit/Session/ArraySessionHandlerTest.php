<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Session\ArraySessionHandler;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ArraySessionHandlerTest extends TestCase
{
    private ClockInterface $clock;
    private ArraySessionHandler $handler;

    public function test_read_returns_false_when_session_not_exists(): void
    {
        $result = $this->handler->read('non-existent-id');

        $this->assertFalse($result);
    }

    public function test_write_and_read_session_data(): void
    {
        $dateTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $this->clock->shouldReceive('now')->andReturn($dateTime);

        $sessionId = 'test-session-id';
        $sessionData = '{"user_id": 123, "username": "test"}';

        $writeResult = $this->handler->write($sessionId, $sessionData);
        $this->assertTrue($writeResult);

        $readResult = $this->handler->read($sessionId);
        $this->assertEquals($sessionData, $readResult);
    }

    public function test_read_returns_false_when_session_expired(): void
    {
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $readTime = new \DateTimeImmutable('2025-01-01 14:01:00'); // 2 hours 1 minute later

        $sessionId = 'expired-session';
        $sessionData = '{"data": "test"}';

        // Write session
        $this->clock->shouldReceive('now')->andReturn($writeTime)->once();
        $this->handler->write($sessionId, $sessionData);

        // Try to read after expiration (ttl = 3600 seconds = 1 hour)
        $this->clock->shouldReceive('now')->andReturn($readTime)->once();
        $result = $this->handler->read($sessionId);

        $this->assertFalse($result);
    }

    public function test_read_returns_data_when_session_not_expired(): void
    {
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $readTime = new \DateTimeImmutable('2025-01-01 12:30:00'); // 30 minutes later

        $sessionId = 'valid-session';
        $sessionData = '{"data": "test"}';

        // Write session
        $this->clock->shouldReceive('now')->andReturn($writeTime)->once();
        $this->handler->write($sessionId, $sessionData);

        // Read before expiration
        $this->clock->shouldReceive('now')->andReturn($readTime)->once();
        $result = $this->handler->read($sessionId);

        $this->assertEquals($sessionData, $result);
    }

    public function test_destroy_removes_session(): void
    {
        $dateTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $this->clock->shouldReceive('now')->andReturn($dateTime)->times(2);

        $sessionId = 'test-session';
        $sessionData = '{"data": "test"}';

        // Write session
        $this->handler->write($sessionId, $sessionData);
        $this->assertEquals($sessionData, $this->handler->read($sessionId));

        // Destroy session
        $result = $this->handler->destroy($sessionId);
        $this->assertTrue($result);

        // Verify session is removed
        $this->assertFalse($this->handler->read($sessionId));
    }

    public function test_destroy_returns_true_for_non_existent_session(): void
    {
        $result = $this->handler->destroy('non-existent-session');

        $this->assertTrue($result);
    }

    public function test_gc_removes_expired_sessions(): void
    {
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $gcTime = new \DateTimeImmutable('2025-01-01 14:31:00'); // 2 hours 31 minutes later

        // Write multiple sessions
        $this->clock->shouldReceive('now')->andReturn($writeTime)->times(3);
        $this->handler->write('session1', '{"data": "test1"}');
        $this->handler->write('session2', '{"data": "test2"}');
        $this->handler->write('session3', '{"data": "test3"}');

        // Run garbage collection with maxLifetime = 1800 (30 minutes)
        $this->clock->shouldReceive('now')->andReturn($gcTime)->once();
        $deletedSessions = $this->handler->gc(1800);

        $this->assertCount(3, $deletedSessions);
        $this->assertContains('session1', $deletedSessions);
        $this->assertContains('session2', $deletedSessions);
        $this->assertContains('session3', $deletedSessions);

        // Verify sessions are actually removed
        $this->assertFalse($this->handler->read('session1'));
        $this->assertFalse($this->handler->read('session2'));
        $this->assertFalse($this->handler->read('session3'));
    }

    public function test_gc_keeps_non_expired_sessions(): void
    {
        $writeTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $gcTime = new \DateTimeImmutable('2025-01-01 12:30:00'); // 30 minutes later

        // Write session
        $this->clock->shouldReceive('now')->andReturn($writeTime)->once();
        $this->handler->write('valid-session', '{"data": "test"}');

        // Run garbage collection with maxLifetime = 3600 (1 hour)
        $this->clock->shouldReceive('now')->andReturn($gcTime)->times(2); // once for gc, once for read
        $deletedSessions = $this->handler->gc(3600);

        $this->assertEmpty($deletedSessions);
        $this->assertEquals('{"data": "test"}', $this->handler->read('valid-session'));
    }

    public function test_gc_returns_empty_array_when_no_sessions(): void
    {
        $this->clock->shouldReceive('now')->andReturn(new \DateTimeImmutable())->once();

        $deletedSessions = $this->handler->gc(3600);

        $this->assertEmpty($deletedSessions);
    }

    public function test_constructor_with_default_parameters(): void
    {
        $handler = new ArraySessionHandler();

        $this->assertInstanceOf(ArraySessionHandler::class, $handler);
        $this->assertEquals(3600, $handler->ttl);
    }

    public function test_constructor_with_custom_ttl(): void
    {
        $handler = new ArraySessionHandler(ttl: 7200);

        $this->assertEquals(7200, $handler->ttl);
    }

    protected function setUp(): void
    {
        $this->clock = \Mockery::mock(ClockInterface::class);
        $this->handler = new ArraySessionHandler(ttl: 3600, clock: $this->clock);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}
