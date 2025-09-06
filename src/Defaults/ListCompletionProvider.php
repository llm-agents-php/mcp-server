<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

use Mcp\Server\Contracts\CompletionProviderInterface;
use Mcp\Server\Contracts\SessionInterface;

final readonly class ListCompletionProvider implements CompletionProviderInterface
{
    /**
     * @param string[] $values
     */
    public function __construct(
        private array $values,
    ) {}

    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        if (empty($currentValue)) {
            return $this->values;
        }

        return \array_values(
            \array_filter(
                $this->values,
                static fn(string $value): bool => \str_starts_with($value, $currentValue),
            ),
        );
    }
}
