<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\General;

use Mcp\Server\Contracts\CompletionProviderInterface;
use Mcp\Server\Contracts\SessionInterface;

class CompletionProviderFixture implements CompletionProviderInterface
{
    public static array $completions = ['alpha', 'beta', 'gamma'];
    public static string $lastCurrentValue = '';
    public static ?SessionInterface $lastSession = null;

    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        self::$lastCurrentValue = $currentValue;
        self::$lastSession = $session;

        return \array_filter(self::$completions, static fn($item) => \str_starts_with((string) $item, $currentValue));
    }
}
