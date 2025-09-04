<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Elements\RegisteredTool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function call(
        RegisteredTool $registeredTool,
        array $arguments,
        Context $context,
    ): array {
        $this->logger->debug('Calling tool', [
            'name' => $registeredTool->schema->name,
        ]);

        return $registeredTool->call($arguments, $context);
    }
}
