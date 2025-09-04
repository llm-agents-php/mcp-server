<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery;

use Mcp\Server\Attributes\McpResourceTemplate;

#[McpResourceTemplate(uriTemplate: "invokable://user-profile/{userId}")]
class InvocableResourceTemplateFixture
{
    public function __invoke(string $userId): array
    {
        return ["id" => $userId, "email" => "user{$userId}@example-invokable.com"];
    }
}
