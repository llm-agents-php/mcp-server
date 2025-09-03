<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery\SubDir;

use Mcp\Server\Attributes\McpTool;

class HiddenTool
{
    #[McpTool(name: 'hidden_subdir_tool')]
    public function run(): void {}
}
