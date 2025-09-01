<?php

declare(strict_types=1);

namespace PhpMcp\Server;

use PhpMcp\Server\Contracts\RouteInterface;
use PhpMcp\Server\Contracts\ToolExecutorInterface;
use PhpMcp\Server\Routes\CompletionRoute;
use PhpMcp\Server\Routes\InitializeRoute;
use PhpMcp\Server\Routes\LoggingRoute;
use PhpMcp\Server\Routes\PromptRoute;
use PhpMcp\Server\Routes\ResourceRoute;
use PhpMcp\Server\Routes\ToolRoute;
use PhpMcp\Server\Session\SubscriptionManager;

final class DispatcherRouter
{
    /**
     * @param RouteInterface[] $routes
     */
    public function __construct(
        private array $routes = [],
    ) {
    }

    public static function create(
        Configuration $configuration,
        Registry $registry,
        SubscriptionManager $subscriptionManager,
        ToolExecutorInterface $toolExecutor,
    ): self {
        $routes = [
            new InitializeRoute($configuration),
            new ToolRoute($registry, $toolExecutor, $configuration),
            new ResourceRoute($registry, $subscriptionManager, $configuration),
            new PromptRoute($registry, $configuration),
            new LoggingRoute($configuration),
            new CompletionRoute($registry, $configuration),
        ];

        return new self($routes);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function addRoute(RouteInterface $route): void
    {
        foreach ($route->getMethods() as $method) {
            $this->routes[$method] = $route;
        }
    }

}
