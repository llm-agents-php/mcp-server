<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth protected resource metadata as defined in RFC 8705.
 */
final readonly class OAuthProtectedResourceMetadata implements \JsonSerializable
{
    /**
     * @param string[] $authorizationServers
     * @param string[]|null $scopesSupported
     * @param string[]|null $bearerMethodsSupported
     * @param string[]|null $resourceSigningAlgValuesSupported
     */
    public function __construct(
        public string $resource,
        public array $authorizationServers,
        public ?string $jwksUri = null,
        public ?array $scopesSupported = null,
        public ?array $bearerMethodsSupported = null,
        public ?array $resourceSigningAlgValuesSupported = null,
        public ?string $resourceName = null,
        public ?string $resourceDocumentation = null,
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'resource' => $this->resource,
            'authorization_servers' => $this->authorizationServers,
            'jwks_uri' => $this->jwksUri,
            'scopes_supported' => $this->scopesSupported,
            'bearer_methods_supported' => $this->bearerMethodsSupported,
            'resource_signing_alg_values_supported' => $this->resourceSigningAlgValuesSupported,
            'resource_name' => $this->resourceName,
            'resource_documentation' => $this->resourceDocumentation,
        ], static fn($value) => $value !== null);
    }
}
