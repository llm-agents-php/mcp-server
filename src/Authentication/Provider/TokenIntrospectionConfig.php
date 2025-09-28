<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

/**
 * Configuration for OAuth token introspection.
 */
final readonly class TokenIntrospectionConfig
{
    /**
     * @param array<string, string> $headers Additional headers for API requests
     * @param array<string, string> $userFieldMapping Mapping from provider fields to standard fields
     */
    public function __construct(
        public ?string $introspectionUrl = null,
        public ?string $userinfoUrl = null,
        public array $headers = [],
        public array $userFieldMapping = [],
        public int $cacheTtl = 300,
        public bool $useIntrospection = true,
    ) {}

    public static function forGitHub(?string $apiBaseUrl = null): self
    {
        $baseUrl = $apiBaseUrl ?? 'https://api.github.com';

        return new self(
            introspectionUrl: null, // GitHub doesn't support RFC 7662
            userinfoUrl: "{$baseUrl}/user",
            userFieldMapping: [
                'id' => 'sub',
                'login' => 'preferred_username',
                'name' => 'name',
                'email' => 'email',
            ],
            useIntrospection: false,
        );
    }

    public static function forGoogle(): self
    {
        return new self(
            introspectionUrl: 'https://oauth2.googleapis.com/tokeninfo',
            userinfoUrl: 'https://openidconnect.googleapis.com/v1/userinfo',
            userFieldMapping: [
                'sub' => 'sub',
                'email' => 'email',
                'name' => 'name',
                'given_name' => 'given_name',
                'family_name' => 'family_name',
            ],
        );
    }

    public static function forAuth0(string $domain): self
    {
        return new self(
            introspectionUrl: "https://{$domain}/oauth/token/introspect",
            userinfoUrl: "https://{$domain}/userinfo",
            userFieldMapping: [
                'sub' => 'sub',
                'email' => 'email',
                'name' => 'name',
                'nickname' => 'preferred_username',
            ],
        );
    }
}
