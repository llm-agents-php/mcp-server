<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Mcp\Server\Elements\RegisteredPrompt;
use Mcp\Server\Elements\RegisteredResource;
use Mcp\Server\Elements\RegisteredResourceTemplate;
use Mcp\Server\Elements\RegisteredTool;
use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\ResourceTemplate;
use PhpMcp\Schema\Tool;

interface ReferenceProviderInterface
{
    public function getTool(string $name): ?RegisteredTool;

    public function getResource(
        string $uri,
        bool $includeTemplates = true,
    ): RegisteredResource|RegisteredResourceTemplate|null;

    public function getResourceTemplate(string $uriTemplate): ?RegisteredResourceTemplate;

    public function getPrompt(string $name): ?RegisteredPrompt;

    /**
     * @return array<string, Tool>
     */
    public function getTools(): array;

    /**
     * @return array<string, Resource>
     */
    public function getResources(): array;

    /**
     * @return array<string, Prompt>
     */
    public function getPrompts(): array;

    /**
     * @return array<string, ResourceTemplate>
     */
    public function getResourceTemplates(): array;

    /**
     * Checks if any references are currently registered.
     */
    public function hasReferences(): bool;
}
