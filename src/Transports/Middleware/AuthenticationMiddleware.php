<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;

/**
 * Authentication middleware for bearer token validation
 */
final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \Closure $authenticator,
        private array $protectedPaths = [],
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (!$this->isProtectedPath($path)) {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->createUnauthorizedResponse('Missing Authorization header');
        }

        if (!$this->authenticateRequest($request, $authHeader)) {
            return $this->createUnauthorizedResponse('Invalid credentials');
        }

        return $handler->handle($request);
    }

    private function isProtectedPath(string $path): bool
    {
        if (empty($this->protectedPaths)) {
            return true; // Protect all paths if none specified
        }

        foreach ($this->protectedPaths as $protectedPath) {
            if (\str_starts_with($path, (string) $protectedPath)) {
                return true;
            }
        }

        return false;
    }

    private function authenticateRequest(ServerRequestInterface $request, string $authHeader): bool
    {
        return ($this->authenticator)($request, $authHeader);
    }

    private function createUnauthorizedResponse(string $message): ResponseInterface
    {
        return new Response(
            status: 401,
            headers: [
                'Content-Type' => 'application/json',
                'WWW-Authenticate' => 'Bearer',
            ],
            body: \json_encode(['error' => $message]),
        );
    }
}
