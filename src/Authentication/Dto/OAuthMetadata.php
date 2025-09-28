<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth authorization server metadata as defined in RFC 8414.
 */
final readonly class OAuthMetadata implements \JsonSerializable
{
    /**
     * @param string[] $responseTypesSupported
     * @param string[]|null $responseModesSupported
     * @param string[]|null $grantTypesSupported
     * @param string[]|null $tokenEndpointAuthMethodsSupported
     * @param string[]|null $tokenEndpointAuthSigningAlgValuesSupported
     * @param string[]|null $scopesSupported
     * @param string[]|null $revocationEndpointAuthMethodsSupported
     * @param string[]|null $revocationEndpointAuthSigningAlgValuesSupported
     * @param string[]|null $introspectionEndpointAuthMethodsSupported
     * @param string[]|null $introspectionEndpointAuthSigningAlgValuesSupported
     * @param string[]|null $codeChallengeMethodsSupported
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $registrationEndpoint,
        public string $revocationEndpoint,
        public string $tokenEndpoint,
        public array $responseTypesSupported,
        public ?array $scopesSupported = null,
        public ?array $responseModesSupported = null,
        public ?array $grantTypesSupported = null,
        public ?array $tokenEndpointAuthMethodsSupported = null,
        public ?array $tokenEndpointAuthSigningAlgValuesSupported = null,
        public ?string $serviceDocumentation = null,
        public ?array $revocationEndpointAuthMethodsSupported = null,
        public ?array $revocationEndpointAuthSigningAlgValuesSupported = null,
        public ?string $introspectionEndpoint = null,
        public ?array $introspectionEndpointAuthMethodsSupported = null,
        public ?array $introspectionEndpointAuthSigningAlgValuesSupported = null,
        public ?array $codeChallengeMethodsSupported = null,
    ) {}

    public static function forProxy(string $issuer): self
    {
        return new self(
            tokenEndpoint: \rtrim($issuer, '/') . '/oauth2/token',
            authorizationEndpoint: \rtrim($issuer, '/') . '/oauth2/authorize',
            registrationEndpoint: \rtrim($issuer, '/') . '/oauth2/register',
            revocationEndpoint: \rtrim($issuer, '/') . '/oauth2/revoke',
            responseTypesSupported: ['code'],
            scopesSupported: ['read', 'write'],
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            tokenEndpointAuthMethodsSupported: ['client_secret_post', 'client_secret_basic'],
            codeChallengeMethodsSupported: ['S256'],
        );
    }

    public static function forGithub(string $issuer): self
    {
        return new self(
            issuer: $issuer,
            authorizationEndpoint: 'https://github.com/login/oauth/authorize',
            tokenEndpoint: 'https://github.com/login/oauth/access_token',
            revocationEndpoint: 'https://github.com/login/oauth/revoke',
            registrationEndpoint: 'https://github.com/login/oauth/register',
            responseTypesSupported: ['code'],
            scopesSupported: ['read', 'write'],
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            tokenEndpointAuthMethodsSupported: ['client_secret_post', 'client_secret_basic'],
            codeChallengeMethodsSupported: ['S256'],
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'response_types_supported' => $this->responseTypesSupported,
            'registration_endpoint' => $this->registrationEndpoint,
            'scopes_supported' => $this->scopesSupported,
            'response_modes_supported' => $this->responseModesSupported,
            'grant_types_supported' => $this->grantTypesSupported,
            'token_endpoint_auth_methods_supported' => $this->tokenEndpointAuthMethodsSupported,
            'token_endpoint_auth_signing_alg_values_supported' => $this->tokenEndpointAuthSigningAlgValuesSupported,
            'service_documentation' => $this->serviceDocumentation,
            'revocation_endpoint' => $this->revocationEndpoint,
            'revocation_endpoint_auth_methods_supported' => $this->revocationEndpointAuthMethodsSupported,
            'revocation_endpoint_auth_signing_alg_values_supported' => $this->revocationEndpointAuthSigningAlgValuesSupported,
            'introspection_endpoint' => $this->introspectionEndpoint,
            'introspection_endpoint_auth_methods_supported' => $this->introspectionEndpointAuthMethodsSupported,
            'introspection_endpoint_auth_signing_alg_values_supported' => $this->introspectionEndpointAuthSigningAlgValuesSupported,
            'code_challenge_methods_supported' => $this->codeChallengeMethodsSupported,
        ], static fn($value) => $value !== null);
    }
}
