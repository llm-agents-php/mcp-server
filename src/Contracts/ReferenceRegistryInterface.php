<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Evenement\EventEmitterInterface;
use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\ResourceTemplate;
use PhpMcp\Schema\Tool;

interface ReferenceRegistryInterface extends EventEmitterInterface
{
    public function registerTool(
        Tool $tool,
        HandlerInterface $handler,
        bool $isManual = false,
    ): void;

    public function registerResource(
        Resource $resource,
        HandlerInterface $handler,
        bool $isManual = false,
    ): void;

    public function registerResourceTemplate(
        ResourceTemplate $template,
        HandlerInterface $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void;

    public function registerPrompt(
        Prompt $prompt,
        HandlerInterface $handler,
        array $completionProviders = [],
        bool $isManual = false,
    ): void;
}
