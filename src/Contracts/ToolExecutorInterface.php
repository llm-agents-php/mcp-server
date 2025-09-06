<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Mcp\Server\Context;
use Mcp\Server\Exception\ToolNotFoundException;
use Mcp\Server\Exception\ValidationException;
use PhpMcp\Schema\Content\Content;

interface ToolExecutorInterface
{
    /**
     * Call a registered tool with the given arguments.
     *
     * @return Content[] The content items for CallToolResult.
     *
     * @throws ValidationException If arguments do not match the tool's input schema.
     * @throws ToolNotFoundException If the tool is not registered.
     */
    public function call(string $toolName, array $arguments, Context $context): array;
}
