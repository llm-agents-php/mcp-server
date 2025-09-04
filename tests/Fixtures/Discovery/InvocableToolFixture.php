<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery;

use Mcp\Server\Attributes\McpTool;

#[McpTool(name: "InvokableCalculator", description: "An invokable calculator tool.")]
class InvocableToolFixture
{
    /**
     * Adds two numbers.
     */
    public function __invoke(int $a, int $b): int
    {
        return $a + $b;
    }
}
