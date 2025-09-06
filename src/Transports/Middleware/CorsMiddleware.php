<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;

/**
 * CORS middleware for handling Cross-Origin Resource Sharing
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $allowedOrigins = ['*'],
        private array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        private array $allowedHeaders = ['Content-Type', 'Authorization'],
        private int $maxAge = 86400,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->createPreflightResponse();
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $request);
    }

    private function createPreflightResponse(): ResponseInterface
    {
        return new Response(
            status: 204,
            headers: [
                'Access-Control-Allow-Origin' => \implode(', ', $this->allowedOrigins),
                'Access-Control-Allow-Methods' => \implode(', ', $this->allowedMethods),
                'Access-Control-Allow-Headers' => \implode(', ', $this->allowedHeaders),
                'Access-Control-Max-Age' => (string) $this->maxAge,
                'Content-Length' => '0',
            ],
        );
    }

    private function addCorsHeaders(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->isOriginAllowed($origin) ? $origin : $this->allowedOrigins[0];

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', \implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', \implode(', ', $this->allowedHeaders));
    }

    private function isOriginAllowed(string $origin): bool
    {
        return \in_array('*', $this->allowedOrigins, true)
            || \in_array($origin, $this->allowedOrigins, true);
    }
}
