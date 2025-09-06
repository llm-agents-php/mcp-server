<?php

declare(strict_types=1);

namespace Mcp\Server\Elements;

use PhpMcp\Schema\Content\ResourceContents;
use PhpMcp\Schema\ResourceTemplate;
use PhpMcp\Schema\Result\CompletionCompleteResult;
use Mcp\Server\Context;
use Mcp\Server\Contracts\CompletionProviderInterface;
use Mcp\Server\Contracts\HandlerInterface;
use Mcp\Server\Contracts\SessionInterface;

final class RegisteredResourceTemplate extends RegisteredElement
{
    private array $variableNames;
    private array $uriVariables;

    /** @var non-empty-string */
    private string $uriTemplateRegex;

    /**
     * @param array<string, CompletionProviderInterface> $completionProviders
     */
    public function __construct(
        public readonly ResourceTemplate $schema,
        HandlerInterface $handler,
        bool $isManual = false,
        public readonly array $completionProviders = [],
    ) {
        parent::__construct($handler, $isManual);

        $this->compileTemplate();
    }

    public static function fromArray(array $data): self|false
    {
        try {
            if (!isset($data['schema']) || !isset($data['handler'])) {
                return false;
            }

            $completionProviders = [];
            foreach ($data['completionProviders'] ?? [] as $argument => $provider) {
                $completionProviders[$argument] = \unserialize($provider);
            }

            return new self(
                ResourceTemplate::fromArray($data['schema']),
                $data['handler'],
                $data['isManual'] ?? false,
                $completionProviders,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Gets the resource template.
     *
     * @return array<ResourceContents> Array of ResourceContents objects.
     */
    public function read(string $uri, Context $context): array
    {
        $arguments = \array_merge($this->uriVariables, ['uri' => $uri]);

        $result = $this->handler->handle($arguments, $context);

        return ResourceResultFormatter::format($result, $uri, $this->schema->mimeType);
    }

    public function complete(
        string $argument,
        string $value,
        SessionInterface $session,
    ): CompletionCompleteResult {
        $provider = $this->completionProviders[$argument] ?? null;
        if ($provider === null) {
            return new CompletionCompleteResult([]);
        }

        $completions = $provider->getCompletions($value, $session);

        $total = \count($completions);
        $hasMore = $total > 100;

        $pagedCompletions = \array_slice($completions, 0, 100);

        return new CompletionCompleteResult($pagedCompletions, $total, $hasMore);
    }

    public function getVariableNames(): array
    {
        return $this->variableNames;
    }

    public function matches(string $uri): bool
    {
        if (\preg_match($this->uriTemplateRegex, $uri, $matches)) {
            $variables = [];
            foreach ($this->variableNames as $varName) {
                if (isset($matches[$varName])) {
                    $variables[$varName] = $matches[$varName];
                }
            }

            $this->uriVariables = $variables;

            return true;
        }

        return false;
    }

    public function toArray(): array
    {
        $completionProviders = [];
        foreach ($this->completionProviders as $argument => $provider) {
            $completionProviders[$argument] = \serialize($provider);
        }

        return [
            'schema' => $this->schema->toArray(),
            'completionProviders' => $completionProviders,
            ...parent::toArray(),
        ];
    }

    private function compileTemplate(): void
    {
        $this->variableNames = [];
        $regexParts = [];

        $segments = \preg_split(
            '/(\{\w+})/',
            $this->schema->uriTemplate,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($segments === false) {
            throw new \RuntimeException("Failed to split URI template '{$this->schema->uriTemplate}'");
        }

        foreach ($segments as $segment) {
            if (\preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                $varName = $matches[1];
                $this->variableNames[] = $varName;
                $regexParts[] = '(?P<' . $varName . '>[^/]+)';
            } else {
                $regexParts[] = \preg_quote($segment, '#');
            }
        }

        $this->uriTemplateRegex = '#^' . \implode('', $regexParts) . '$#';
    }
}
