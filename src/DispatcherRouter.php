<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Routes\CompletionRoute;
use Mcp\Server\Routes\InitializeRoute;
use Mcp\Server\Routes\LoggingRoute;
use Mcp\Server\Routes\PromptRoute;
use Mcp\Server\Routes\ResourceRoute;
use Mcp\Server\Routes\ToolRoute;
use Mcp\Server\Session\SubscriptionManager;

final readonly class DispatcherRouter
{
    /**
     * @param RouteInterface[] $routes
     */
    public function __construct(
        private array $routes = [],
    ) {}

    public static function create(
        Configuration $configuration,
        Registry $registry,
        SubscriptionManager $subscriptionManager,
        ToolExecutorInterface $toolExecutor,
    ): self {
        $routes = [
            new InitializeRoute($configuration),
            new ToolRoute($registry, $configuration),
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
}
