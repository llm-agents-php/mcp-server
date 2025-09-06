<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Session\SubscriptionManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SubscriptionManagerTest extends TestCase
{
    private LoggerInterface $logger;
    private SubscriptionManager $subscriptionManager;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->subscriptionManager = new SubscriptionManager($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_subscribe_adds_session_to_resource(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger
            ->shouldReceive('debug')
            ->with('Session subscribed to resource', [
                'sessionId' => $sessionId,
                'uri' => $uri,
            ])
            ->once();

        $this->subscriptionManager->subscribe($sessionId, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertContains($sessionId, $subscribers);
    }

    public function test_subscribe_multiple_sessions_to_same_resource(): void
    {
        $uri = 'file:///test.txt';
        $session1 = 'session1';
        $session2 = 'session2';

        $this->logger->shouldReceive('debug')->twice();

        $this->subscriptionManager->subscribe($session1, $uri);
        $this->subscriptionManager->subscribe($session2, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertCount(2, $subscribers);
        $this->assertContains($session1, $subscribers);
        $this->assertContains($session2, $subscribers);
    }

    public function test_subscribe_same_session_to_multiple_resources(): void
    {
        $sessionId = 'session1';
        $uri1 = 'file:///test1.txt';
        $uri2 = 'file:///test2.txt';

        $this->logger->shouldReceive('debug')->twice();

        $this->subscriptionManager->subscribe($sessionId, $uri1);
        $this->subscriptionManager->subscribe($sessionId, $uri2);

        $this->assertTrue($this->subscriptionManager->isSubscribed($sessionId, $uri1));
        $this->assertTrue($this->subscriptionManager->isSubscribed($sessionId, $uri2));
    }

    public function test_unsubscribe_removes_session_from_resource(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger->shouldReceive('debug')->twice(); // subscribe + unsubscribe

        $this->subscriptionManager->subscribe($sessionId, $uri);
        $this->assertTrue($this->subscriptionManager->isSubscribed($sessionId, $uri));

        $this->subscriptionManager->unsubscribe($sessionId, $uri);
        $this->assertFalse($this->subscriptionManager->isSubscribed($sessionId, $uri));
    }

    public function test_unsubscribe_cleans_up_empty_resource_entry(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger->shouldReceive('debug')->twice();

        $this->subscriptionManager->subscribe($sessionId, $uri);
        $this->subscriptionManager->unsubscribe($sessionId, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertEmpty($subscribers);
    }

    public function test_unsubscribe_keeps_other_subscribers(): void
    {
        $uri = 'file:///test.txt';
        $session1 = 'session1';
        $session2 = 'session2';

        $this->logger->shouldReceive('debug')->times(3); // 2 subscribes + 1 unsubscribe

        $this->subscriptionManager->subscribe($session1, $uri);
        $this->subscriptionManager->subscribe($session2, $uri);

        $this->subscriptionManager->unsubscribe($session1, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertCount(1, $subscribers);
        $this->assertContains($session2, $subscribers);
        $this->assertNotContains($session1, $subscribers);
    }

    public function test_get_subscribers_returns_empty_for_unknown_resource(): void
    {
        $subscribers = $this->subscriptionManager->getSubscribers('file:///unknown.txt');

        $this->assertEmpty($subscribers);
    }

    public function test_get_subscribers_returns_all_subscribers(): void
    {
        $uri = 'file:///test.txt';
        $session1 = 'session1';
        $session2 = 'session2';
        $session3 = 'session3';

        $this->logger->shouldReceive('debug')->times(3);

        $this->subscriptionManager->subscribe($session1, $uri);
        $this->subscriptionManager->subscribe($session2, $uri);
        $this->subscriptionManager->subscribe($session3, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);

        $this->assertCount(3, $subscribers);
        $this->assertContains($session1, $subscribers);
        $this->assertContains($session2, $subscribers);
        $this->assertContains($session3, $subscribers);
    }

    public function test_is_subscribed_returns_true_for_existing_subscription(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger->shouldReceive('debug')->once();

        $this->subscriptionManager->subscribe($sessionId, $uri);

        $this->assertTrue($this->subscriptionManager->isSubscribed($sessionId, $uri));
    }

    public function test_is_subscribed_returns_false_for_non_existing_subscription(): void
    {
        $this->assertFalse($this->subscriptionManager->isSubscribed('session1', 'file:///test.txt'));
    }

    public function test_cleanup_session_removes_all_subscriptions(): void
    {
        $sessionId = 'session1';
        $uri1 = 'file:///test1.txt';
        $uri2 = 'file:///test2.txt';
        $uri3 = 'file:///test3.txt';

        $this->logger->shouldReceive('debug')->times(3); // 3 subscribes
        $this->logger
            ->shouldReceive('debug')
            ->with('Cleaned up all subscriptions for session', [
                'sessionId' => $sessionId,
                'count' => 3,
            ])
            ->once();

        $this->subscriptionManager->subscribe($sessionId, $uri1);
        $this->subscriptionManager->subscribe($sessionId, $uri2);
        $this->subscriptionManager->subscribe($sessionId, $uri3);

        $this->subscriptionManager->cleanupSession($sessionId);

        $this->assertFalse($this->subscriptionManager->isSubscribed($sessionId, $uri1));
        $this->assertFalse($this->subscriptionManager->isSubscribed($sessionId, $uri2));
        $this->assertFalse($this->subscriptionManager->isSubscribed($sessionId, $uri3));
    }

    public function test_cleanup_session_cleans_up_empty_resource_entries(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger->shouldReceive('debug')->twice(); // subscribe + cleanup

        $this->subscriptionManager->subscribe($sessionId, $uri);
        $this->subscriptionManager->cleanupSession($sessionId);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertEmpty($subscribers);
    }

    public function test_cleanup_session_preserves_other_sessions_subscriptions(): void
    {
        $uri = 'file:///test.txt';
        $session1 = 'session1';
        $session2 = 'session2';

        $this->logger->shouldReceive('debug')->times(3); // 2 subscribes + 1 cleanup

        $this->subscriptionManager->subscribe($session1, $uri);
        $this->subscriptionManager->subscribe($session2, $uri);

        $this->subscriptionManager->cleanupSession($session1);

        $this->assertFalse($this->subscriptionManager->isSubscribed($session1, $uri));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session2, $uri));

        $subscribers = $this->subscriptionManager->getSubscribers($uri);
        $this->assertCount(1, $subscribers);
        $this->assertContains($session2, $subscribers);
    }

    public function test_cleanup_session_for_non_existent_session_does_nothing(): void
    {
        // This should not throw an exception or cause issues
        $this->subscriptionManager->cleanupSession('non-existent');

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function test_multiple_subscribe_unsubscribe_operations(): void
    {
        $session1 = 'session1';
        $session2 = 'session2';
        $uri1 = 'file:///test1.txt';
        $uri2 = 'file:///test2.txt';

        $this->logger->shouldReceive('debug')->times(6); // 4 subscribes + 2 unsubscribes

        // Create complex subscription pattern
        $this->subscriptionManager->subscribe($session1, $uri1);
        $this->subscriptionManager->subscribe($session1, $uri2);
        $this->subscriptionManager->subscribe($session2, $uri1);
        $this->subscriptionManager->subscribe($session2, $uri2);

        // Verify initial state
        $this->assertTrue($this->subscriptionManager->isSubscribed($session1, $uri1));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session1, $uri2));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session2, $uri1));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session2, $uri2));

        // Remove some subscriptions
        $this->subscriptionManager->unsubscribe($session1, $uri1);
        $this->subscriptionManager->unsubscribe($session2, $uri2);

        // Verify partial cleanup
        $this->assertFalse($this->subscriptionManager->isSubscribed($session1, $uri1));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session1, $uri2));
        $this->assertTrue($this->subscriptionManager->isSubscribed($session2, $uri1));
        $this->assertFalse($this->subscriptionManager->isSubscribed($session2, $uri2));

        // Verify resource subscribers are correct
        $uri1Subscribers = $this->subscriptionManager->getSubscribers($uri1);
        $uri2Subscribers = $this->subscriptionManager->getSubscribers($uri2);

        $this->assertCount(1, $uri1Subscribers);
        $this->assertContains($session2, $uri1Subscribers);

        $this->assertCount(1, $uri2Subscribers);
        $this->assertContains($session1, $uri2Subscribers);
    }

    public function test_subscribe_same_session_to_same_resource_multiple_times(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger->shouldReceive('debug')->twice();

        // Subscribe twice to the same resource
        $this->subscriptionManager->subscribe($sessionId, $uri);
        $this->subscriptionManager->subscribe($sessionId, $uri);

        $subscribers = $this->subscriptionManager->getSubscribers($uri);

        // Should still only appear once
        $this->assertCount(1, $subscribers);
        $this->assertContains($sessionId, $subscribers);
    }

    public function test_unsubscribe_non_existent_subscription(): void
    {
        $sessionId = 'session1';
        $uri = 'file:///test.txt';

        $this->logger
            ->shouldReceive('debug')
            ->with('Session unsubscribed from resource', [
                'sessionId' => $sessionId,
                'uri' => $uri,
            ])
            ->once();

        // This should not cause any issues
        $this->subscriptionManager->unsubscribe($sessionId, $uri);

        $this->assertFalse($this->subscriptionManager->isSubscribed($sessionId, $uri));
    }

    public function test_subscription_state_isolation(): void
    {
        $session1 = 'session1';
        $session2 = 'session2';
        $uri1 = 'file:///test1.txt';
        $uri2 = 'file:///test2.txt';

        $this->logger->shouldReceive('debug')->times(2);

        $this->subscriptionManager->subscribe($session1, $uri1);
        $this->subscriptionManager->subscribe($session2, $uri2);

        // Verify isolation - session1 should not be subscribed to uri2
        $this->assertTrue($this->subscriptionManager->isSubscribed($session1, $uri1));
        $this->assertFalse($this->subscriptionManager->isSubscribed($session1, $uri2));

        // Verify isolation - session2 should not be subscribed to uri1
        $this->assertTrue($this->subscriptionManager->isSubscribed($session2, $uri2));
        $this->assertFalse($this->subscriptionManager->isSubscribed($session2, $uri1));

        // Verify resource isolation
        $uri1Subscribers = $this->subscriptionManager->getSubscribers($uri1);
        $uri2Subscribers = $this->subscriptionManager->getSubscribers($uri2);

        $this->assertCount(1, $uri1Subscribers);
        $this->assertCount(1, $uri2Subscribers);
        $this->assertContains($session1, $uri1Subscribers);
        $this->assertContains($session2, $uri2Subscribers);
    }
}
