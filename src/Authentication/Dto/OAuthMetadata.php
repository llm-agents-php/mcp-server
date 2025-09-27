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
        public string $tokenEndpoint,
        public array $responseTypesSupported,
        public ?string $registrationEndpoint = null,
        public ?array $scopesSupported = null,
        public ?array $responseModesSupported = null,
        public ?array $grantTypesSupported = null,
        public ?array $tokenEndpointAuthMethodsSupported = null,
        public ?array $tokenEndpointAuthSigningAlgValuesSupported = null,
        public ?string $serviceDocumentation = null,
        public ?string $revocationEndpoint = null,
        public ?array $revocationEndpointAuthMethodsSupported = null,
        public ?array $revocationEndpointAuthSigningAlgValuesSupported = null,
        public ?string $introspectionEndpoint = null,
        public ?array $introspectionEndpointAuthMethodsSupported = null,
        public ?array $introspectionEndpointAuthSigningAlgValuesSupported = null,
        public ?array $codeChallengeMethodsSupported = null,
    ) {}

    public function getIssuer(): string
    {
        return $this->issuer;
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
