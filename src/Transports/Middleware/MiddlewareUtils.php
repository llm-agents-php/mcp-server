<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Utility class for working with mixed middleware types
 * @internal
 */
final class MiddlewareUtils
{
    /**
     * Convert mixed middleware array to React-compatible callables
     *
     * @param array<callable|MiddlewareInterface> $middleware
     * @return callable[]
     */
    public static function normalizeMiddleware(array $middleware): array
    {
        return \array_map(static function ($mw) {
            if ($mw instanceof MiddlewareInterface) {
                return new MiddlewareAdapter($mw);
            }

            if (\is_callable($mw)) {
                return $mw;
            }

            throw new \InvalidArgumentException(
                'Middleware must be callable or implement MiddlewareInterface',
            );
        }, $middleware);
    }
}
