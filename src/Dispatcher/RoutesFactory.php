<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher;

use Mcp\Server\Configuration;
use Mcp\Server\Contracts\DispatcherRoutesFactoryInterface;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Dispatcher\Routes\CompletionRoute;
use Mcp\Server\Dispatcher\Routes\InitializeRoute;
use Mcp\Server\Dispatcher\Routes\LoggingRoute;
use Mcp\Server\Dispatcher\Routes\PromptRoute;
use Mcp\Server\Dispatcher\Routes\ResourceRoute;
use Mcp\Server\Dispatcher\Routes\ToolRoute;
use Mcp\Server\Registry;
use Mcp\Server\Session\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class RoutesFactory implements DispatcherRoutesFactoryInterface
{
    public function __construct(
        private Configuration $configuration,
        private ReferenceProviderInterface $registry,
        private SubscriptionManager $subscriptionManager,
        private ToolExecutorInterface $toolExecutor,
        private Paginator $pagination = new Paginator(),
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function create(): array
    {
        return [
            new InitializeRoute(
                configuration: $this->configuration,
            ),
            new ToolRoute(
                registry: $this->registry,
                toolExecutor: $this->toolExecutor,
                logger: $this->logger,
                paginationHelper: $this->pagination,
            ),
            new ResourceRoute(
                registry: $this->registry,
                subscriptionManager: $this->subscriptionManager,
                logger: $this->logger,
                paginationHelper: $this->pagination,
            ),
            new PromptRoute(
                registry: $this->registry,
                logger: $this->logger,
                paginationHelper: $this->pagination,
            ),
            new LoggingRoute(
                logger: $this->logger,
            ),
            new CompletionRoute(
                registry: $this->registry,
            ),
        ];
    }
}
