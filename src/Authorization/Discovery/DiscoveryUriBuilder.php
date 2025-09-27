<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Discovery;

/**
 * Builds well-known discovery URIs per RFC 8414 and OpenID Connect Discovery
 */
final readonly class DiscoveryUriBuilder
{
    /**
     * Build OAuth 2.0 Authorization Server Metadata URIs per RFC 8414
     * 
     * @return string[] Priority-ordered list of discovery URIs
     */
    public function buildOAuthDiscoveryUris(string $issuerUrl): array
    {
        $parsedUrl = parse_url($issuerUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        if (isset($parsedUrl['port']) && $parsedUrl['port'] !== 80 && $parsedUrl['port'] !== 443) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }

        $path = $parsedUrl['path'] ?? '';
        $path = trim($path, '/');

        $uris = [];

        if (!empty($path)) {
            // Path insertion method: https://auth.example.com/.well-known/oauth-authorization-server/tenant1
            $uris[] = $baseUrl . '/.well-known/oauth-authorization-server/' . $path;
        }

        // Root method: https://auth.example.com/.well-known/oauth-authorization-server
        $uris[] = $baseUrl . '/.well-known/oauth-authorization-server';

        return $uris;
    }

    /**
     * Build OpenID Connect Discovery URIs per OpenID Connect Discovery 1.0
     * 
     * @return string[] Priority-ordered list of discovery URIs
     */
    public function buildOpenIdConnectDiscoveryUris(string $issuerUrl): array
    {
        $parsedUrl = parse_url($issuerUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        if (isset($parsedUrl['port']) && $parsedUrl['port'] !== 80 && $parsedUrl['port'] !== 443) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }

        $path = $parsedUrl['path'] ?? '';
        $path = trim($path, '/');

        $uris = [];

        if (!empty($path)) {
            // Path insertion method: https://auth.example.com/.well-known/openid-configuration/tenant1
            $uris[] = $baseUrl . '/.well-known/openid-configuration/' . $path;
            
            // Path appending method: https://auth.example.com/tenant1/.well-known/openid-configuration
            $uris[] = $baseUrl . '/' . $path . '/.well-known/openid-configuration';
        }

        // Root method: https://auth.example.com/.well-known/openid-configuration
        $uris[] = $baseUrl . '/.well-known/openid-configuration';

        return $uris;
    }

    /**
     * Build Protected Resource Metadata URIs per RFC 9728
     * 
     * @return string[] Priority-ordered list of discovery URIs
     */
    public function buildResourceMetadataUris(string $resourceUrl, ?string $specificPath = null): array
    {
        $parsedUrl = parse_url($resourceUrl);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        if (isset($parsedUrl['port']) && $parsedUrl['port'] !== 80 && $parsedUrl['port'] !== 443) {
            $baseUrl .= ':' . $parsedUrl['port'];
        }

        $uris = [];

        if ($specificPath !== null) {
            // Path-specific metadata
            $cleanPath = trim($specificPath, '/');
            $uris[] = $baseUrl . '/.well-known/oauth-protected-resource/' . $cleanPath;
        }

        // Root metadata
        $uris[] = $baseUrl . '/.well-known/oauth-protected-resource';

        return $uris;
    }
}
