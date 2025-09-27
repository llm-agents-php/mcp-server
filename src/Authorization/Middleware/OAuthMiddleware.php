<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Mcp\Server\Authorization\Contracts\ResourceServerInterface;
use Mcp\Server\Authorization\Discovery\DiscoveryEndpointHandler;
use Mcp\Server\Authorization\Exception\AuthorizationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Unified OAuth middleware handling both authorization and discovery endpoints
 * Supports RFC 6750, RFC 8414, RFC 9728, and OpenID Connect Discovery 1.0
 */
final readonly class OAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResourceServerInterface $resourceServer,
        private string $expectedAudience,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private DiscoveryEndpointHandler $discoveryHandler,
        private bool $enabled = true,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Handle discovery endpoints first (GET requests only)
        if ($method === 'GET' && $this->discoveryHandler->isDiscoveryEndpoint($path)) {
            return $this->handleDiscoveryRequest($path);
        }

        // Handle authorization for protected endpoints
        if ($this->resourceServer->requiresAuthorization($request)) {
            try {
                $accessToken = $this->resourceServer->validateRequest($request, $this->expectedAudience);

                $authenticatedRequest = $request
                    ->withAttribute('oauth.access_token', $accessToken)
                    ->withAttribute('oauth.client_id', $accessToken->clientId)
                    ->withAttribute('oauth.subject', $accessToken->subject)
                    ->withAttribute('oauth.audience', $accessToken->audience)
                    ->withAttribute('oauth.claims', $accessToken->claims);

                return $handler->handle($authenticatedRequest);
            } catch (AuthorizationException $e) {
                return $this->createErrorResponse($e);
            }
        }

        // Pass through unprotected requests
        return $handler->handle($request);
    }

    private function handleDiscoveryRequest(string $path): ResponseInterface
    {
        $metadata = $this->discoveryHandler->getMetadataForPath($path);
        
        if ($metadata === null) {
            return $this->responseFactory->createResponse(404)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream('{"error": "not_found"}'));
        }

        return $this->createDiscoveryResponse($metadata);
    }

    private function createDiscoveryResponse(array $metadata): ResponseInterface
    {
        $body = \json_encode($metadata, \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            ->withBody($this->streamFactory->createStream($body ?: '{}'));
    }

    private function createErrorResponse(AuthorizationException $exception): ResponseInterface
    {
        $body = \json_encode($exception->getErrorResponse());

        return $this->responseFactory
            ->createResponse(StatusCodeInterface::STATUS_UNAUTHORIZED)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader(
                'WWW-Authenticate',
                $this->resourceServer->createWwwAuthenticateHeader(
                    $exception->errorCode,
                    $exception->errorDescription,
                ),
            )
            ->withBody($this->streamFactory->createStream($body ?: ''));
    }
}
