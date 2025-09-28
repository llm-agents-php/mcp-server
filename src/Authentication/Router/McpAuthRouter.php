<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\Contract\OAuthTokenVerifierInterface;
use Mcp\Server\Authentication\Error\InsufficientScopeError;
use Mcp\Server\Authentication\Error\InvalidTokenError;
use Mcp\Server\Authentication\Handler\AuthorizeHandler;
use Mcp\Server\Authentication\Handler\MetadataHandler;
use Mcp\Server\Authentication\Handler\RegisterHandler;
use Mcp\Server\Authentication\Handler\RevokeHandler;
use Mcp\Server\Authentication\Handler\TokenHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that handles OAuth endpoints with bearer token protection.
 */
final readonly class McpAuthRouter implements MiddlewareInterface
{
    public function __construct(
        private AuthorizeHandler $authorizeHandler,
        private RegisterHandler $registerHandler,
        private TokenHandler $tokenHandler,
        private RevokeHandler $revokeHandler,
        private MetadataHandler $metadataHandler,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ?OAuthTokenVerifierInterface $tokenVerifier = null,
        private array $requiredScopes = [],
        private ?string $resourceMetadataUrl = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Handle OAuth endpoints (no authentication required for these)
        $oauthResponse = match (true) {
            [$path, $method] === [
                '/.well-known/mcp-oauth-metadata',
                'GET',
            ] => $this->metadataHandler->handleOAuthMetadata($request),
            \str_starts_with(
                $path,
                '/.well-known/oauth-protected-resource',
            ) && $method === 'GET' => $this->metadataHandler->handleProtectedResourceMetadata(
                $request,
            ),
            \str_starts_with(
                $path,
                '/.well-known/oauth-authorization-server',
            ) && $method === 'GET' => $this->metadataHandler->handleOAuthMetadata(
                $request,
            ),
            [$path, $method] == ['/oauth2/authorize', 'GET'] => $this->authorizeHandler->handle($request),
            [$path, $method] == ['/oauth2/token', 'POST'] => $this->tokenHandler->handle($request),
            [$path, $method] == ['/oauth2/revoke', 'POST'] => $this->revokeHandler->handle($request),
            [$path, $method] == ['/oauth2/register', 'POST'] => $this->registerHandler->handle($request),
            default => null,
        };

        if ($oauthResponse !== null) {
            return $oauthResponse;
        }

        try {
            // For all other requests, require authentication if token verifier is configured
            if ($this->tokenVerifier !== null) {
                $authInfo = $this->extractAndVerifyToken($request);

                // Check required scopes
                if ($authInfo !== null) {
                    if (!empty($this->requiredScopes)) {
                        $this->validateScopes($authInfo->getScopes(), $this->requiredScopes);
                    }

                    // Add authenticated user to request attributes
                    $request = $request->withAttribute('auth', $authInfo);
                }
            }

            return $handler->handle($request);
        } catch (InvalidTokenError $e) {
            return $this->createAuthErrorResponse(401, 'invalid_token', 'Authentication required');
        } catch (InsufficientScopeError $e) {
            return $this->createAuthErrorResponse(403, 'insufficient_scope', $e->getMessage());
        }
    }

    private function extractAndVerifyToken(ServerRequestInterface $request): ?AuthInfo
    {
        // Extract Bearer token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !\str_starts_with(\strtolower($authHeader), 'bearer ')) {
            return null;
        }

        $token = \substr($authHeader, 7); // Remove "Bearer " prefix

        if (empty($token)) {
            return null;
        }

        // Verify token
        $authInfo = $this->tokenVerifier->verifyAccessToken($token);

        // Check if token is expired
        if ($authInfo->getExpiresAt() !== null && $authInfo->getExpiresAt() < \time()) {
            throw new InvalidTokenError('Token has expired');
        }

        return $authInfo;
    }

    private function validateScopes(array $tokenScopes, array $requiredScopes): void
    {
        foreach ($requiredScopes as $requiredScope) {
            if (!\in_array($requiredScope, $tokenScopes, true)) {
                throw new InsufficientScopeError("Required scope: {$requiredScope}");
            }
        }
    }

    private function createAuthErrorResponse(int $statusCode, string $error, string $description): ResponseInterface
    {
        // Build WWW-Authenticate header value
        $wwwAuthParts = [
            "error=\"{$error}\"",
            "error_description=\"{$description}\"",
        ];

        if ($this->resourceMetadataUrl !== null) {
            $wwwAuthParts[] = "resource_metadata=\"{$this->resourceMetadataUrl}\"";
        }

        $wwwAuthenticate = 'Bearer ' . \implode(', ', $wwwAuthParts);

        // Create error response body
        $errorBody = [
            'error' => $error,
            'error_description' => $description,
        ];

        $json = \json_encode($errorBody, JSON_THROW_ON_ERROR);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', $wwwAuthenticate)
            ->withBody($body);
    }
}
