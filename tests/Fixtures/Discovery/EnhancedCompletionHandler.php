<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery;

use Mcp\Server\Attributes\McpPrompt;
use Mcp\Server\Attributes\McpResourceTemplate;
use Mcp\Server\Attributes\CompletionProvider;
use Mcp\Server\Tests\Fixtures\Enums\StatusEnum;
use Mcp\Server\Tests\Fixtures\Enums\PriorityEnum;

class EnhancedCompletionHandler
{
    /**
     * Create content with list and enum completion providers.
     */
    #[McpPrompt(name: 'content_creator')]
    public function createContent(
        #[CompletionProvider(values: ['blog', 'article', 'tutorial', 'guide'])]
        string $type,
        #[CompletionProvider(enum: StatusEnum::class)]
        string $status,
        #[CompletionProvider(enum: PriorityEnum::class)]
        string $priority,
    ): array {
        return [
            ['role' => 'user', 'content' => "Create a {$type} with status {$status} and priority {$priority}"],
        ];
    }

    /**
     * Resource template with list completion for categories.
     */
    #[McpResourceTemplate(
        uriTemplate: 'content://{category}/{slug}',
        name: 'content_template',
    )]
    public function getContent(
        #[CompletionProvider(values: ['news', 'blog', 'docs', 'api'])]
        string $category,
        string $slug,
    ): array {
        return [
            'category' => $category,
            'slug' => $slug,
            'url' => "https://example.com/{$category}/{$slug}",
        ];
    }
}
