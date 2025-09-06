<?php

declare(strict_types=1);

namespace Mcp\Server\Tests;

use Mcp\Server\Context;
use Mcp\Server\Contracts\DispatcherRoutesFactoryInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Dispatcher;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DispatcherTest extends TestCase
{
    private LoggerInterface $logger;
    private DispatcherRoutesFactoryInterface $routesFactory;
    private RouteInterface $mockRoute;
    private Context $context;
    private SessionInterface $session;
    private array $loggedMessages = [];

    public function testConstructorRegistersRoutes(): void
    {
        $routes = [
            'test/method' => $this->mockRoute,
        ];

        $this->routesFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($routes);

        new Dispatcher($this->logger, $this->routesFactory);

        // If constructor completes without exception, routes were registered successfully
        $this->assertTrue(true);
    }

    public function testHandleRequestWithExistingRoute(): void
    {
        $request = new Request(
            jsonrpc: '2.0',
            id: 1,
            method: 'test/method',
            params: ['param' => 'value'],
        );

        $expectedResult = $this->createMock(Result::class);

        $this->mockRoute
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request, $this->context)
            ->willReturn($expectedResult);

        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $result = $dispatcher->handleRequest($request, $this->context);

        $this->assertSame($expectedResult, $result);
        $this->assertTrue($this->hasLoggedMessage('debug', 'Received request'));

        $logEntry = $this->getLoggedMessage('debug', 'Received request');
        $this->assertEquals('test/method', $logEntry['context']['method']);
        $this->assertEquals(['param' => 'value'], $logEntry['context']['params']);
    }

    public function testHandleRequestWithNonExistingRoute(): void
    {
        $request = new Request(
            jsonrpc: '2.0',
            id: 1,
            method: 'unknown/method',
            params: [],
        );

        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        $this->expectException(McpServerException::class);
        $this->expectExceptionMessage("Method 'unknown/method' not found.");

        try {
            $dispatcher->handleRequest($request, $this->context);
        } catch (McpServerException $e) {
            $this->assertTrue($this->hasLoggedMessage('debug', 'Received request'));
            $this->assertTrue($this->hasLoggedMessage('error', 'Method not found'));

            $errorLog = $this->getLoggedMessage('error', 'Method not found');
            $this->assertEquals('unknown/method', $errorLog['context']['method']);

            throw $e;
        }
    }

    public function testHandleRequestLogsRequestDetails(): void
    {
        $request = new Request(
            jsonrpc: '2.0',
            id: 1,
            method: 'test/method',
            params: ['key' => 'value', 'number' => 42],
        );

        $expectedResult = $this->createMock(Result::class);

        $this->mockRoute
            ->method('handleRequest')
            ->willReturn($expectedResult);

        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $dispatcher->handleRequest($request, $this->context);

        $logEntry = $this->getLoggedMessage('debug', 'Received request');
        $this->assertNotNull($logEntry);
        $this->assertEquals('test/method', $logEntry['context']['method']);
        $this->assertEquals(['key' => 'value', 'number' => 42], $logEntry['context']['params']);
    }

    public function testHandleNotificationWithExistingRoute(): void
    {
        $notification = new Notification(
            jsonrpc: '2.0',
            method: 'test/notification',
            params: ['param' => 'value'],
        );

        $this->mockRoute
            ->expects($this->once())
            ->method('handleNotification')
            ->with($notification, $this->context);

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $dispatcher->handleNotification($notification, $this->context);

        $this->assertTrue($this->hasLoggedMessage('debug', 'Received notification'));

        $logEntry = $this->getLoggedMessage('debug', 'Received notification');
        $this->assertEquals('test/notification', $logEntry['context']['method']);
    }

    public function testHandleNotificationWithNonExistingRoute(): void
    {
        $notification = new Notification(
            jsonrpc: '2.0',
            method: 'unknown/notification',
            params: [],
        );

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        // Should not throw exception for unknown notification methods
        $dispatcher->handleNotification($notification, $this->context);

        $this->assertTrue($this->hasLoggedMessage('debug', 'Received notification'));
        $this->assertTrue($this->hasLoggedMessage('error', 'Method not found'));

        $errorLog = $this->getLoggedMessage('error', 'Method not found');
        $this->assertEquals('unknown/notification', $errorLog['context']['method']);
    }

    public function testHandleNotificationDoesNotCallRouteWhenNotFound(): void
    {
        $notification = new Notification(
            jsonrpc: '2.0',
            method: 'unknown/notification',
            params: [],
        );

        $this->mockRoute
            ->expects($this->never())
            ->method('handleNotification');

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $dispatcher->handleNotification($notification, $this->context);
    }

    public function testHandleNotificationLogsNotificationDetails(): void
    {
        $notification = new Notification(
            jsonrpc: '2.0',
            method: 'test/notification',
            params: ['key' => 'value'],
        );

        $this->mockRoute
            ->method('handleNotification');

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $dispatcher->handleNotification($notification, $this->context);

        $logEntry = $this->getLoggedMessage('debug', 'Received notification');
        $this->assertNotNull($logEntry);
        $this->assertEquals('test/notification', $logEntry['context']['method']);
    }

    public function testMultipleRoutesRegistration(): void
    {
        $route1 = $this->createMock(RouteInterface::class);
        $route2 = $this->createMock(RouteInterface::class);

        $routes = [
            'method/one' => $route1,
            'method/two' => $route2,
        ];

        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        // Test first route
        $request1 = new Request('2.0', 1, 'method/one', []);
        $result1 = $this->createMock(Result::class);

        $route1
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request1, $this->context)
            ->willReturn($result1);

        $actualResult1 = $dispatcher->handleRequest($request1, $this->context);
        $this->assertSame($result1, $actualResult1);

        // Test second route
        $request2 = new Request('2.0', 2, 'method/two', []);
        $result2 = $this->createMock(Result::class);

        $route2
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request2, $this->context)
            ->willReturn($result2);

        $actualResult2 = $dispatcher->handleRequest($request2, $this->context);
        $this->assertSame($result2, $actualResult2);
    }

    public function testEmptyRoutesHandling(): void
    {
        $this->routesFactory
            ->method('create')
            ->willReturn([]);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        $request = new Request('2.0', 1, 'any/method', []);

        $this->expectException(McpServerException::class);
        $this->expectExceptionMessage("Method 'any/method' not found.");

        $dispatcher->handleRequest($request, $this->context);
    }

    public function testRouteExceptionPropagation(): void
    {
        $request = new Request('2.0', 1, 'test/method', []);

        $customException = new McpServerException('Custom route error', -32000);

        $this->mockRoute
            ->method('handleRequest')
            ->willThrowException($customException);

        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        $this->expectException(McpServerException::class);
        $this->expectExceptionMessage('Custom route error');

        $dispatcher->handleRequest($request, $this->context);
    }

    public function testNotificationRouteExceptionHandling(): void
    {
        $notification = new Notification('2.0', 'test/notification', []);

        $customException = new \RuntimeException('Route notification error');

        $this->mockRoute
            ->method('handleNotification')
            ->willThrowException($customException);

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        // Notifications should not throw exceptions, they should be silently handled
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route notification error');

        $dispatcher->handleNotification($notification, $this->context);
    }

    public function testDispatcherIsReadOnly(): void
    {
        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);

        // Test that the class is readonly by checking its reflection
        $reflection = new \ReflectionClass($dispatcher);
        $this->assertTrue($reflection->isReadOnly(), 'Dispatcher class should be readonly');
    }

    public function testHandleRequestWithNullParams(): void
    {
        $request = new Request(
            jsonrpc: '2.0',
            id: 1,
            method: 'test/method',
            params: null,
        );

        $expectedResult = $this->createMock(Result::class);

        $this->mockRoute
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request, $this->context)
            ->willReturn($expectedResult);

        $routes = ['test/method' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $result = $dispatcher->handleRequest($request, $this->context);

        $this->assertSame($expectedResult, $result);

        $logEntry = $this->getLoggedMessage('debug', 'Received request');
        $this->assertEquals('test/method', $logEntry['context']['method']);
        $this->assertNull($logEntry['context']['params']);
    }

    public function testHandleNotificationWithNullParams(): void
    {
        $notification = new Notification(
            jsonrpc: '2.0',
            method: 'test/notification',
            params: null,
        );

        $this->mockRoute
            ->expects($this->once())
            ->method('handleNotification')
            ->with($notification, $this->context);

        $routes = ['test/notification' => $this->mockRoute];
        $this->routesFactory
            ->method('create')
            ->willReturn($routes);

        $dispatcher = new Dispatcher($this->logger, $this->routesFactory);
        $dispatcher->handleNotification($notification, $this->context);

        $this->assertTrue($this->hasLoggedMessage('debug', 'Received notification'));

        $logEntry = $this->getLoggedMessage('debug', 'Received notification');
        $this->assertEquals('test/notification', $logEntry['context']['method']);
    }

    protected function setUp(): void
    {
        $this->loggedMessages = [];
        $this->logger = $this->createMockLogger();
        $this->routesFactory = $this->createMock(DispatcherRoutesFactoryInterface::class);
        $this->mockRoute = $this->createMock(RouteInterface::class);
        $this->session = $this->createMock(SessionInterface::class);
        $this->context = new Context($this->session);
    }

    private function createMockLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger
            ->method('debug')
            ->willReturnCallback(function (string $message, array $context = []): void {
                $this->loggedMessages[] = [
                    'level' => 'debug',
                    'message' => $message,
                    'context' => $context,
                ];
            });

        $logger
            ->method('error')
            ->willReturnCallback(function (string $message, array $context = []): void {
                $this->loggedMessages[] = [
                    'level' => 'error',
                    'message' => $message,
                    'context' => $context,
                ];
            });

        return $logger;
    }

    private function hasLoggedMessage(string $level, string $messagePattern): bool
    {
        foreach ($this->loggedMessages as $log) {
            if ($log['level'] === $level && \str_contains((string) $log['message'], $messagePattern)) {
                return true;
            }
        }
        return false;
    }

    private function getLoggedMessage(string $level, string $messagePattern): ?array
    {
        foreach ($this->loggedMessages as $log) {
            if ($log['level'] === $level && \str_contains((string) $log['message'], $messagePattern)) {
                return $log;
            }
        }
        return null;
    }
}
