<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Mcp\Server\Context;
use Mcp\Server\Elements\RegisteredTool;
use Mcp\Server\Exception\ValidationException;

interface ToolExecutorInterface
{
    /**
     * Call a registered tool with the given arguments.
     *
     * @throws ValidationException If arguments do not match the tool's input schema.
     */
    public function call(RegisteredTool $registeredTool, array $arguments, Context $context): array;
}
