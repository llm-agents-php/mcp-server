<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Evenement\EventEmitterInterface;
use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Session\SessionManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class SessionManagerTest extends TestCase
{
    private SessionHandlerInterface $handler;
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private SessionManager $sessionManager;

    public function test_implements_event_emitter_interface(): void
    {
        $this->assertInstanceOf(EventEmitterInterface::class, $this->sessionManager);
    }

    public function test_start_gc_timer_adds_periodic_timer(): void
    {
        $timer = \Mockery::mock(TimerInterface::class);

        $this->loop
            ->shouldReceive('addPeriodicTimer')
            ->with(300, \Mockery::type('callable'))
            ->andReturn($timer)
            ->once();

        $this->sessionManager->startGcTimer();

        $this->addToAssertionCount(1); // Explicit assertion count
    }

    public function test_start_gc_timer_does_not_add_duplicate_timer(): void
    {
        $timer = \Mockery::mock(TimerInterface::class);

        $this->loop
            ->shouldReceive('addPeriodicTimer')
            ->with(300, \Mockery::type('callable'))
            ->andReturn($timer)
            ->once(); // Should only be called once

        $this->sessionManager->startGcTimer();
        $this->sessionManager->startGcTimer(); // Second call should be ignored

        $this->addToAssertionCount(1); // Explicit assertion count
    }

    public function test_stop_gc_timer_cancels_timer(): void
    {
        $timer = \Mockery::mock(TimerInterface::class);

        $this->loop->shouldReceive('addPeriodicTimer')->andReturn($timer);
        $this->loop->shouldReceive('cancelTimer')->with($timer)->once();

        $this->sessionManager->startGcTimer();
        $this->sessionManager->stopGcTimer();

        $this->addToAssertionCount(1); // Explicit assertion count
    }

    public function test_stop_gc_timer_when_no_timer_exists(): void
    {
        // Should not throw an exception
        $this->sessionManager->stopGcTimer();

        // No assertions needed, just verifying no exception is thrown
        $this->assertTrue(true);
    }

    public function test_gc_runs_handler_gc_and_emits_events(): void
    {
        $deletedSessions = ['session1', 'session2', 'session3'];

        $this->handler
            ->shouldReceive('gc')
            ->with(3600)
            ->andReturn($deletedSessions)
            ->once();

        $this->logger
            ->shouldReceive('debug')
            ->with('Session garbage collection complete', ['purged_sessions' => 3])
            ->once();

        // Capture emitted events
        $emittedEvents = [];
        $this->sessionManager->on('session_deleted', static function ($sessionId) use (&$emittedEvents): void {
            $emittedEvents[] = $sessionId;
        });

        $result = $this->sessionManager->gc();

        $this->assertEquals($deletedSessions, $result);
        $this->assertEquals($deletedSessions, $emittedEvents);
    }

    public function test_gc_with_no_deleted_sessions(): void
    {
        $this->handler
            ->shouldReceive('gc')
            ->with(3600)
            ->andReturn([])
            ->once();

        $this->logger->shouldNotReceive('debug');

        $result = $this->sessionManager->gc();

        $this->assertEmpty($result);
    }

    public function test_create_session_creates_and_saves_session(): void
    {
        $sessionId = 'new-session-id';

        // Session constructor will try to read existing data first
        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn(false)
            ->once();

        $this->handler
            ->shouldReceive('write')
            ->with(
                $sessionId,
                \Mockery::on(static function ($json) {
                    $data = \json_decode($json, true);
                    return $data['initialized'] === false &&
                        $data['client_info'] === null &&
                        $data['subscriptions'] === [] &&
                        $data['message_queue'] === [];
                }),
            )
            ->andReturn(true)
            ->once();

        $this->logger
            ->shouldReceive('info')
            ->with('Session created', ['sessionId' => $sessionId])
            ->once();

        // Capture emitted event
        $createdSessionId = null;
        $createdSession = null;
        $this->sessionManager->on(
            'session_created',
            static function ($id, $session) use (&$createdSessionId, &$createdSession): void {
                $createdSessionId = $id;
                $createdSession = $session;
            },
        );

        $session = $this->sessionManager->createSession($sessionId);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertEquals($sessionId, $session->getId());
        $this->assertEquals($sessionId, $createdSessionId);
        $this->assertSame($session, $createdSession);
    }

    public function test_get_session_returns_existing_session(): void
    {
        $sessionId = 'existing-session';
        $sessionData = \json_encode(['user_id' => 123]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $session = $this->sessionManager->getSession($sessionId);

        $this->assertInstanceOf(SessionInterface::class, $session);
        $this->assertEquals($sessionId, $session->getId());
    }

    public function test_get_session_returns_null_for_non_existent(): void
    {
        $this->handler
            ->shouldReceive('read')
            ->with('non-existent')
            ->andReturn(false)
            ->once();

        $session = $this->sessionManager->getSession('non-existent');

        $this->assertNull($session);
    }

    public function test_has_session_returns_true_for_existing(): void
    {
        $sessionId = 'existing-session';
        $sessionData = \json_encode(['user_id' => 123]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $result = $this->sessionManager->hasSession($sessionId);

        $this->assertTrue($result);
    }

    public function test_has_session_returns_false_for_non_existent(): void
    {
        $this->handler
            ->shouldReceive('read')
            ->with('non-existent')
            ->andReturn(false)
            ->once();

        $result = $this->sessionManager->hasSession('non-existent');

        $this->assertFalse($result);
    }

    public function test_delete_session_destroys_and_emits_event(): void
    {
        $sessionId = 'session-to-delete';

        $this->handler
            ->shouldReceive('destroy')
            ->with($sessionId)
            ->andReturn(true)
            ->once();

        $this->logger
            ->shouldReceive('info')
            ->with('Session deleted', ['sessionId' => $sessionId])
            ->once();

        // Capture emitted event
        $deletedSessionId = null;
        $this->sessionManager->on('session_deleted', static function ($id) use (&$deletedSessionId): void {
            $deletedSessionId = $id;
        });

        $result = $this->sessionManager->deleteSession($sessionId);

        $this->assertTrue($result);
        $this->assertEquals($sessionId, $deletedSessionId);
    }

    public function test_delete_session_logs_warning_on_failure(): void
    {
        $sessionId = 'session-to-delete';

        $this->handler
            ->shouldReceive('destroy')
            ->with($sessionId)
            ->andReturn(false)
            ->once();

        $this->logger
            ->shouldReceive('warning')
            ->with('Failed to delete session', ['sessionId' => $sessionId])
            ->once();

        $result = $this->sessionManager->deleteSession($sessionId);

        $this->assertFalse($result);
    }

    public function test_queue_message_adds_to_existing_session(): void
    {
        $sessionId = 'existing-session';
        $message = 'test message';
        $sessionData = \json_encode(['message_queue' => []]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $this->handler
            ->shouldReceive('write')
            ->with(
                $sessionId,
                \Mockery::on(static function ($json) use ($message) {
                    $data = \json_decode($json, true);
                    return \in_array($message, $data['message_queue'] ?? []);
                }),
            )
            ->andReturn(true)
            ->once();

        $this->sessionManager->queueMessage($sessionId, $message);

        $this->addToAssertionCount(1); // Explicit assertion count
    }

    public function test_queue_message_ignores_non_existent_session(): void
    {
        $this->handler
            ->shouldReceive('read')
            ->with('non-existent')
            ->andReturn(false)
            ->once();

        $this->handler->shouldNotReceive('write');

        $this->sessionManager->queueMessage('non-existent', 'test message');

        $this->addToAssertionCount(1); // Explicit assertion count
    }

    public function test_dequeue_messages_returns_and_clears_queue(): void
    {
        $sessionId = 'existing-session';
        $messages = ['message1', 'message2'];
        $sessionData = \json_encode(['message_queue' => $messages]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $this->handler
            ->shouldReceive('write')
            ->with(
                $sessionId,
                \Mockery::on(static function ($json) {
                    $data = \json_decode($json, true);
                    return empty($data['message_queue']);
                }),
            )
            ->andReturn(true)
            ->once();

        $result = $this->sessionManager->dequeueMessages($sessionId);

        $this->assertEquals($messages, $result);
    }

    public function test_dequeue_messages_returns_empty_for_non_existent_session(): void
    {
        $this->handler
            ->shouldReceive('read')
            ->with('non-existent')
            ->andReturn(false)
            ->once();

        $result = $this->sessionManager->dequeueMessages('non-existent');

        $this->assertEmpty($result);
    }

    public function test_has_queued_messages_returns_true_when_messages_exist(): void
    {
        $sessionId = 'existing-session';
        $sessionData = \json_encode(['message_queue' => ['message1']]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $result = $this->sessionManager->hasQueuedMessages($sessionId);

        $this->assertTrue($result);
    }

    public function test_has_queued_messages_returns_false_when_no_messages(): void
    {
        $sessionId = 'existing-session';
        $sessionData = \json_encode(['message_queue' => []]);

        $this->handler
            ->shouldReceive('read')
            ->with($sessionId)
            ->andReturn($sessionData)
            ->once();

        $result = $this->sessionManager->hasQueuedMessages($sessionId);

        $this->assertFalse($result);
    }

    public function test_has_queued_messages_returns_false_for_non_existent_session(): void
    {
        $this->handler
            ->shouldReceive('read')
            ->with('non-existent')
            ->andReturn(false)
            ->once();

        $result = $this->sessionManager->hasQueuedMessages('non-existent');

        $this->assertFalse($result);
    }

    public function test_constructor_with_custom_parameters(): void
    {
        $manager = new SessionManager(
            handler: $this->handler,
            logger: $this->logger,
            loop: $this->loop,
            ttl: 7200,
            gcInterval: 600,
        );

        $this->assertInstanceOf(SessionManager::class, $manager);
    }

    public function test_gc_timer_callback_triggers_gc(): void
    {
        $timer = \Mockery::mock(TimerInterface::class);
        $gcCallback = null;

        $this->loop
            ->shouldReceive('addPeriodicTimer')
            ->with(300, \Mockery::capture($gcCallback))
            ->andReturn($timer);

        $this->handler
            ->shouldReceive('gc')
            ->with(3600)
            ->andReturn([])
            ->once();

        $this->sessionManager->startGcTimer();

        // Execute the callback that was passed to addPeriodicTimer
        $this->assertNotNull($gcCallback);
        \call_user_func($gcCallback);

        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        $this->handler = \Mockery::mock(SessionHandlerInterface::class);
        $this->logger = \Mockery::mock(LoggerInterface::class);
        $this->loop = \Mockery::mock(LoopInterface::class);

        $this->sessionManager = new SessionManager(
            handler: $this->handler,
            logger: $this->logger,
            loop: $this->loop,
            ttl: 3600,
            gcInterval: 300,
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
    }
}
