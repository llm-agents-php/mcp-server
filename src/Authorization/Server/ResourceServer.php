<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Server;

use Mcp\Server\Authorization\Contracts\ResourceServerInterface;
use Mcp\Server\Authorization\Contracts\TokenValidatorInterface;
use Mcp\Server\Authorization\Entities\AccessToken;
use Mcp\Server\Authorization\Exception\AuthorizationException;
use Mcp\Server\Authorization\OAuth;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resource server implementation for protecting MCP endpoints
 */
final readonly class ResourceServer implements ResourceServerInterface
{
    public function __construct(
        private TokenValidatorInterface $tokenValidator,
        private OAuth $auth,
    ) {}

    public function validateRequest(ServerRequestInterface $request, string $expectedAudience): AccessToken
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            throw AuthorizationException::missingToken();
        }

        // Extract bearer token
        if (!\str_starts_with(\strtolower($authHeader), 'bearer ')) {
            throw AuthorizationException::invalidToken('Authorization header must use Bearer scheme');
        }

        $token = \substr($authHeader, 7); // Remove "Bearer " prefix

        if (empty($token)) {
            throw AuthorizationException::invalidToken('Bearer token is empty');
        }

        // Validate the token
        try {
            $accessToken = $this->tokenValidator->validateToken($token, $expectedAudience);
        } catch (\Throwable $e) {
            throw AuthorizationException::invalidToken($e->getMessage());
        }

        return $accessToken;
    }

    public function getResourceMetadata(): array
    {
        // RFC 9728 Protected Resource Metadata
        return [
            'resource' => $this->auth->serverUrl,
            'authorization_servers' => $this->auth->authorizationServers,
            'bearer_methods_supported' => ['header'],
            'resource_documentation' => $this->auth->serverUrl . '/docs',
        ];
    }

    public function requiresAuthorization(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // Check if path is explicitly unprotected
        foreach ($this->auth->unprotectedPaths as $unprotectedPath) {
            if (\str_starts_with($path, $unprotectedPath)) {
                return false;
            }
        }

        // All other paths require authorization by default
        return true;
    }

    public function createWwwAuthenticateHeader(?string $error = null, ?string $errorDescription = null): string
    {
        $header = 'Bearer';

        if ($error !== null) {
            $header .= " error=\"{$error}\"";
        }

        if ($errorDescription !== null) {
            $header .= ", error_description=\"{$errorDescription}\"";
        }

        // Add resource metadata URL per RFC 9728
        $metadataUrl = $this->auth->serverUrl . '/.well-known/oauth-protected-resource';
        $header .= ", resource_metadata=\"{$metadataUrl}\"";

        return $header;
    }

    public function getAuthorizationServerMetadata(): array
    {
        // RFC 8414 OAuth 2.0 Authorization Server Metadata
        $issuer = $this->auth->issuer ?: $this->auth->authorizationServers[0] ?? $this->auth->serverUrl;
        
        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $this->auth->authorizationEndpoint ?: $issuer . '/authorize',
            'token_endpoint' => $this->auth->tokenEndpoint ?: $issuer . '/token',
            'jwks_uri' => $issuer . '/.well-known/jwks.json',
            'scopes_supported' => $this->auth->supportedScopes,
            'response_types_supported' => $this->auth->supportedResponseTypes,
            'grant_types_supported' => $this->auth->supportedGrantTypes,
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
                'private_key_jwt',
            ],
            'revocation_endpoint' => $issuer . '/revoke',
            'introspection_endpoint' => $issuer . '/introspect',
            'code_challenge_methods_supported' => ['S256'],
            'resource_metadata' => $this->auth->serverUrl . '/.well-known/oauth-protected-resource',
        ];
    }

    public function getOpenIdConnectDiscoveryMetadata(): array
    {
        // OpenID Connect Discovery 1.0 metadata
        $issuer = $this->auth->issuer ?: $this->auth->authorizationServers[0] ?? $this->auth->serverUrl;
        
        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $this->auth->authorizationEndpoint ?: $issuer . '/authorize',
            'token_endpoint' => $this->auth->tokenEndpoint ?: $issuer . '/token',
            'userinfo_endpoint' => $issuer . '/userinfo',
            'jwks_uri' => $issuer . '/.well-known/jwks.json',
            'scopes_supported' => array_merge($this->auth->supportedScopes, ['openid', 'profile', 'email']),
            'response_types_supported' => $this->auth->supportedResponseTypes,
            'grant_types_supported' => $this->auth->supportedGrantTypes,
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => [$this->auth->algorithm],
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
                'private_key_jwt',
            ],
            'claims_supported' => [
                'iss', 'sub', 'aud', 'exp', 'iat', 'auth_time', 'nonce',
                'name', 'email', 'email_verified', 'preferred_username',
            ],
            'code_challenge_methods_supported' => ['S256'],
            'end_session_endpoint' => $issuer . '/logout',
            'revocation_endpoint' => $issuer . '/revoke',
            'introspection_endpoint' => $issuer . '/introspect',
        ];
    }
}
