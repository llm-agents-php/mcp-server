<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Promise\PromiseInterface;

/**
 * PSR-15 RequestHandler implementation that wraps a React middleware callable
 * @internal
 */
final readonly class RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private \Closure $handler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = ($this->handler)($request);

        if ($result instanceof PromiseInterface) {
            throw new \RuntimeException('Promise-based handlers cannot be used in PSR-15 context');
        }

        return $result;
    }
}
