<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery;

use Mcp\Server\Attributes\McpPrompt;

#[McpPrompt(name: "InvokableGreeterPrompt")]
class InvocablePromptFixture
{
    public function __invoke(string $personName): array
    {
        return [['role' => 'user', 'content' => "Generate a short greeting for {$personName}."]];
    }
}
