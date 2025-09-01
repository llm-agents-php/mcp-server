<?php

declare(strict_types=1);

namespace PhpMcp\Server\Tests\Fixtures\Discovery;

use PhpMcp\Server\Attributes\McpResource;

#[McpResource(uri: "invokable://config/status", name: "invokable_app_status")]
class InvocableResourceFixture
{
    public function __invoke(): array
    {
        return ["status" => "OK", "load" => random_int(1, 100) / 100.0];
    }
}
