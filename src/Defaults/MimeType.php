<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

final class MimeType
{
    public static function guess(string $content): string
    {
        $trimmed = \ltrim($content);

        if (\str_starts_with($trimmed, '<') && \str_ends_with(\rtrim($content), '>')) {
            if (\str_contains($trimmed, '<html')) {
                return 'text/html';
            }
            if (\str_contains($trimmed, '<?xml')) {
                return 'application/xml';
            }

            return 'text/plain';
        }

        if (\str_starts_with($trimmed, '{') && \str_ends_with(\rtrim($content), '}')) {
            return 'application/json';
        }

        if (\str_starts_with($trimmed, '[') && \str_ends_with(\rtrim($content), ']')) {
            return 'application/json';
        }

        return 'text/plain';
    }
}
