<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Helpers\PaginationHelper;
use Mcp\Server\Routes\CompletionRoute;
use Mcp\Server\Routes\InitializeRoute;
use Mcp\Server\Routes\LoggingRoute;
use Mcp\Server\Routes\PromptRoute;
use Mcp\Server\Routes\ResourceRoute;
use Mcp\Server\Routes\ToolRoute;
use Mcp\Server\Session\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class DispatcherRoutesFactory implements DispatcherRoutesFactoryInterface
{
    public function __construct(
        private Configuration $configuration,
        private Registry $registry,
        private SubscriptionManager $subscriptionManager,
        private ToolExecutorInterface $toolExecutor,
        private int $paginationLimit = 50,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function create(): array
    {
        $pagination = new PaginationHelper();

        return [
            new InitializeRoute(
                configuration: $this->configuration,
            ),
            new ToolRoute(
                registry: $this->registry,
                toolExecutor: $this->toolExecutor,
                logger: $this->logger,
                paginationHelper: $pagination,
                paginationLimit: $this->paginationLimit,
            ),
            new ResourceRoute(
                registry: $this->registry,
                subscriptionManager: $this->subscriptionManager,
                logger: $this->logger,
                paginationHelper: $pagination,
                paginationLimit: $this->paginationLimit,
            ),
            new PromptRoute(
                registry: $this->registry,
                logger: $this->logger,
                paginationHelper: $pagination,
                paginationLimit: $this->paginationLimit,
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
