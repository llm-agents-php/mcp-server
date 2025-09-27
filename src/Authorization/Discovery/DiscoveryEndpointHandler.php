<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Discovery;

use Mcp\Server\Authorization\Contracts\ResourceServerInterface;

/**
 * Handles OAuth 2.1 discovery endpoint resolution and metadata generation
 */
final readonly class DiscoveryEndpointHandler
{
    public function __construct(
        private ResourceServerInterface $resourceServer,
        private DiscoveryUriBuilder $uriBuilder,
    ) {}

    /**
     * Check if the request path matches any discovery endpoint
     */
    public function isDiscoveryEndpoint(string $path): bool
    {
        return $this->isOAuthAuthorizationServerEndpoint($path) ||
               $this->isOpenIdConnectDiscoveryEndpoint($path) ||
               $this->isProtectedResourceMetadataEndpoint($path);
    }

    /**
     * Get metadata for the given discovery endpoint
     */
    public function getMetadataForPath(string $path): ?array
    {
        if ($this->isOAuthAuthorizationServerEndpoint($path)) {
            return $this->resourceServer->getAuthorizationServerMetadata();
        }

        if ($this->isOpenIdConnectDiscoveryEndpoint($path)) {
            return $this->resourceServer->getOpenIdConnectDiscoveryMetadata();
        }

        if ($this->isProtectedResourceMetadataEndpoint($path)) {
            return $this->resourceServer->getResourceMetadata();
        }

        return null;
    }

    /**
     * Get discovery URIs for a given issuer URL (for client implementation)
     */
    public function getDiscoveryUrisForIssuer(string $issuerUrl): array
    {
        return [
            'oauth_authorization_server' => $this->uriBuilder->buildOAuthDiscoveryUris($issuerUrl),
            'openid_connect_discovery' => $this->uriBuilder->buildOpenIdConnectDiscoveryUris($issuerUrl),
        ];
    }

    /**
     * Check if path is OAuth 2.0 Authorization Server Metadata endpoint
     */
    private function isOAuthAuthorizationServerEndpoint(string $path): bool
    {
        return $path === '/.well-known/oauth-authorization-server' ||
               \str_starts_with($path, '/.well-known/oauth-authorization-server/');
    }

    /**
     * Check if path is OpenID Connect Discovery endpoint
     */
    private function isOpenIdConnectDiscoveryEndpoint(string $path): bool
    {
        return $path === '/.well-known/openid-configuration' ||
               \str_ends_with($path, '/.well-known/openid-configuration') ||
               \str_starts_with($path, '/.well-known/openid-configuration/');
    }

    /**
     * Check if path is Protected Resource Metadata endpoint
     */
    private function isProtectedResourceMetadataEndpoint(string $path): bool
    {
        return $path === '/.well-known/oauth-protected-resource' ||
               \str_starts_with($path, '/.well-known/oauth-protected-resource/');
    }
}
