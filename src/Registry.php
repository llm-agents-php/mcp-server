<?php

declare(strict_types=1);

namespace Mcp\Server;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Mcp\Server\Contracts\HandlerInterface;
use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\ResourceTemplate;
use PhpMcp\Schema\Tool;
use Mcp\Server\Elements\RegisteredPrompt;
use Mcp\Server\Elements\RegisteredResource;
use Mcp\Server\Elements\RegisteredResourceTemplate;
use Mcp\Server\Elements\RegisteredTool;
use Psr\Log\LoggerInterface;

class Registry implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var array<string, RegisteredTool> */
    private array $tools = [];

    /** @var array<string, RegisteredResource> */
    private array $resources = [];

    /** @var array<string, RegisteredPrompt> */
    private array $prompts = [];

    /** @var array<string, RegisteredResourceTemplate> */
    private array $resourceTemplates = [];

    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function registerTool(
        Tool $tool,
        HandlerInterface $handler,
        bool $isManual = false,
    ): void {
        $toolName = $tool->name;
        $existing = $this->tools[$toolName] ?? null;

        if ($existing && !$isManual && $existing->isManual) {
            $this->logger->debug(
                "Ignoring discovered tool '{$toolName}' as it conflicts with a manually registered one.",
            );

            return;
        }

        $this->tools[$toolName] = new RegisteredTool($tool, $handler, $isManual);
    }

    public function registerResource(
        Resource $resource,
        HandlerInterface $handler,
        bool $isManual = false,
    ): void {
        $uri = $resource->uri;
        $existing = $this->resources[$uri] ?? null;

        if ($existing && !$isManual && $existing->isManual) {
            $this->logger->debug(
                "Ignoring discovered resource '{$uri}' as it conflicts with a manually registered one.",
            );

            return;
        }

        $this->resources[$uri] = new RegisteredResource($resource, $handler, $isManual);
    }

    public function registerResourceTemplate(
        ResourceTemplate $template,
        HandlerInterface $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $uriTemplate = $template->uriTemplate;
        $existing = $this->resourceTemplates[$uriTemplate] ?? null;

        if ($existing && !$isManual && $existing->isManual) {
            $this->logger->debug(
                "Ignoring discovered template '{$uriTemplate}' as it conflicts with a manually registered one.",
            );

            return;
        }

        $this->resourceTemplates[$uriTemplate] = new RegisteredResourceTemplate(
            $template,
            $handler,
            $isManual,
            $completionProviders,
        );
    }

    public function registerPrompt(
        Prompt $prompt,
        HandlerInterface $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void {
        $promptName = $prompt->name;
        $existing = $this->prompts[$promptName] ?? null;

        if ($existing && !$isManual && $existing->isManual) {
            $this->logger->debug(
                "Ignoring discovered prompt '{$promptName}' as it conflicts with a manually registered one.",
            );

            return;
        }

        $this->prompts[$promptName] = new RegisteredPrompt($prompt, $handler, $isManual, $completionProviders);
    }

    /**
     * Checks if any elements (manual or discovered) are currently registered.
     */
    public function hasElements(): bool
    {
        return !empty($this->tools)
            || !empty($this->resources)
            || !empty($this->prompts)
            || !empty($this->resourceTemplates);
    }

    public function getTool(string $name): ?RegisteredTool
    {
        return $this->tools[$name] ?? null;
    }

    public function getResource(
        string $uri,
        bool $includeTemplates = true,
    ): RegisteredResource|RegisteredResourceTemplate|null {
        $registration = $this->resources[$uri] ?? null;
        if ($registration) {
            return $registration;
        }

        if (!$includeTemplates) {
            return null;
        }

        foreach ($this->resourceTemplates as $template) {
            if ($template->matches($uri)) {
                return $template;
            }
        }

        $this->logger->debug('No resource matched URI.', ['uri' => $uri]);

        return null;
    }

    public function getResourceTemplate(string $uriTemplate): ?RegisteredResourceTemplate
    {
        return $this->resourceTemplates[$uriTemplate] ?? null;
    }

    public function getPrompt(string $name): ?RegisteredPrompt
    {
        return $this->prompts[$name] ?? null;
    }

    /**
     * @return array<string, Tool>
     */
    public function getTools(): array
    {
        return \array_map(static fn($tool) => $tool->schema, $this->tools);
    }

    /**
     * @return array<string, Resource>
     */
    public function getResources(): array
    {
        return \array_map(static fn($resource) => $resource->schema, $this->resources);
    }

    /**
     * @return array<string, Prompt>
     */
    public function getPrompts(): array
    {
        return \array_map(static fn($prompt) => $prompt->schema, $this->prompts);
    }

    /**
     * @return array<string, ResourceTemplate>
     */
    public function getResourceTemplates(): array
    {
        return \array_map(static fn($template) => $template->schema, $this->resourceTemplates);
    }
}
