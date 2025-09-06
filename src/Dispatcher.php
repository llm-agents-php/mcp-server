<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\DispatcherInterface;
use Mcp\Server\Contracts\DispatcherRoutesFactoryInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use Psr\Log\LoggerInterface;

final readonly class Dispatcher implements DispatcherInterface
{
    /** @var array<string, RouteInterface> */
    private array $routes;

    public function __construct(
        private LoggerInterface $logger,
        DispatcherRoutesFactoryInterface $routesFactory,
    ) {
        $routes = [];

        foreach ($routesFactory->create() as $route) {
            foreach ($route->getMethods() as $method) {
                $routes[$method] = $route;
            }
        }

        $this->routes = $routes;
    }

    /**
     * @throws McpServerException
     */
    public function handleRequest(Request $request, Context $context): Result
    {
        $this->logger->debug('Received request', ['method' => $request->method, 'params' => $request->params]);

        $route = $this->routes[$request->method] ?? null;

        if (!$route) {
            $this->logger->error('Method not found', ['method' => $request->method]);
            throw McpServerException::methodNotFound("Method '{$request->method}' not found.");
        }

        return $route->handleRequest($request, $context);
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        $this->logger->debug('Received notification', ['method' => $notification->method]);

        $route = $this->routes[$notification->method] ?? null;

        if (!$route) {
            $this->logger->error('Method not found', ['method' => $notification->method]);
        }

        $route?->handleNotification($notification, $context);
    }
}
