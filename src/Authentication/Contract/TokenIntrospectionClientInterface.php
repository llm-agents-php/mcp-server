<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Contract;

/**
 * HTTP client interface for OAuth token introspection.
 */
interface TokenIntrospectionClientInterface
{
    /**
     * Introspect a token using RFC 7662 endpoint.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException if introspection fails
     */
    public function introspectToken(string $token, string $introspectionUrl, ?array $headers = null): array;

    /**
     * Get user info from OAuth userinfo endpoint.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException if userinfo request fails
     */
    public function getUserInfo(string $token, string $userinfoUrl): array;
}
