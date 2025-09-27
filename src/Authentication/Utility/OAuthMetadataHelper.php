<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Utility;

/**
 * Helper function to construct the OAuth 2.0 Protected Resource Metadata URL
 * from a given server URL. This replaces the path with the standard metadata endpoint.
 */
final readonly class OAuthMetadataHelper
{
    public static function getOAuthProtectedResourceMetadataUrl(string $serverUrl): string
    {
        $parsed = \parse_url($serverUrl);
        $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $baseUrl .= ':' . $parsed['port'];
        }

        return $baseUrl . '/.well-known/oauth-protected-resource';
    }
}
