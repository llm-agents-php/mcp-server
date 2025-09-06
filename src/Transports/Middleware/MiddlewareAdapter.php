<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Converts PSR-15 middleware to React HTTP middleware format
 * @internal
 */
final readonly class MiddlewareAdapter
{
    public function __construct(
        private MiddlewareInterface $middleware,
    ) {}

    /**
     * Convert PSR-15 middleware to React middleware callable
     */
    public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $handler = new RequestHandler($next(...));

        return $this->middleware->process($request, $handler);
    }
}
