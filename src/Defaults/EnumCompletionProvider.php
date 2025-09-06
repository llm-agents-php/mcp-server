<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

use Mcp\Server\Contracts\CompletionProviderInterface;
use Mcp\Server\Contracts\SessionInterface;

final readonly class EnumCompletionProvider implements CompletionProviderInterface
{
    /** @var string[] */
    private array $values;

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    public function __construct(string $enumClass)
    {
        if (!\enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not an enum");
        }

        $this->values = \array_map(
            static fn($case) => isset($case->value) && \is_string($case->value) ? $case->value : $case->name,
            $enumClass::cases(),
        );
    }

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
