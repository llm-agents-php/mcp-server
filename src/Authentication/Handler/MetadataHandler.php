<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\Dto\OAuthMetadata;
use Mcp\Server\Authentication\Dto\OAuthProtectedResourceMetadata;
use Mcp\Server\Authentication\Router\AuthRouterOptions;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Handles OAuth metadata endpoints.
 */
final readonly class MetadataHandler
{
    public function __construct(
        private OAuthMetadata $oauthMetadata,
        private OAuthProtectedResourceMetadata $protectedResourceMetadata,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public static function create(
        AuthRouterOptions $options,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): self {
        $oauthMetadata = self::createOAuthMetadata($options);

        // Create protected resource metadata
        $protectedResourceMetadata = new OAuthProtectedResourceMetadata(
            resource: $options->baseUrl ?? $oauthMetadata->getIssuer(),
            authorizationServers: [$oauthMetadata->getIssuer()],
            jwksUri: null,
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            bearerMethodsSupported: null,
            resourceSigningAlgValuesSupported: null,
            resourceName: $options->resourceName,
            resourceDocumentation: $options->serviceDocumentationUrl,
        );

        return new self(
            oauthMetadata: $oauthMetadata,
            protectedResourceMetadata: $protectedResourceMetadata,
            responseFactory: $responseFactory,
            streamFactory: $streamFactory,
        );
    }

    public function handleOAuthMetadata(ServerRequestInterface $request): ResponseInterface
    {
        $json = \json_encode($this->oauthMetadata->jsonSerialize(), JSON_THROW_ON_ERROR);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($body);
    }

    public function handleProtectedResourceMetadata(ServerRequestInterface $request): ResponseInterface
    {
        $json = \json_encode($this->protectedResourceMetadata->jsonSerialize(), JSON_THROW_ON_ERROR);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($body);
    }

    private static function createOAuthMetadata(AuthRouterOptions $options): OAuthMetadata
    {
        self::checkIssuerUrl($options->issuerUrl);

        $baseUrl = $options->baseUrl ?? $options->issuerUrl;

        return new OAuthMetadata(
            issuer: $options->issuerUrl,
            authorizationEndpoint: self::buildUrl('/oauth2/authorize', $baseUrl),
            tokenEndpoint: self::buildUrl('/oauth2/token', $baseUrl),
            responseTypesSupported: ['code'],
            registrationEndpoint: self::buildUrl('/oauth2/register', $baseUrl),
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            responseModesSupported: null,
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            tokenEndpointAuthMethodsSupported: ['client_secret_post'],
            tokenEndpointAuthSigningAlgValuesSupported: null,
            serviceDocumentation: $options->serviceDocumentationUrl,
            revocationEndpoint: self::buildUrl('/oauth2/revoke', $baseUrl),
            revocationEndpointAuthMethodsSupported: ['client_secret_post'],
            revocationEndpointAuthSigningAlgValuesSupported: null,
            introspectionEndpoint: null,
            introspectionEndpointAuthMethodsSupported: null,
            introspectionEndpointAuthSigningAlgValuesSupported: null,
            codeChallengeMethodsSupported: ['S256'],
        );
    }

    private static function checkIssuerUrl(string $issuer): void
    {
        $parsed = \parse_url($issuer);

        // Technically RFC 8414 does not permit a localhost HTTPS exemption, but this is necessary for testing
        if (
            $parsed['scheme'] !== 'https' &&
            !\in_array($parsed['host'] ?? '', ['localhost', '127.0.0.1'], true)
        ) {
            throw new \InvalidArgumentException('Issuer URL must be HTTPS');
        }

        if (isset($parsed['fragment'])) {
            throw new \InvalidArgumentException("Issuer URL must not have a fragment: {$issuer}");
        }

        if (isset($parsed['query'])) {
            throw new \InvalidArgumentException("Issuer URL must not have a query string: {$issuer}");
        }
    }

    private static function buildUrl(string $path, string $baseUrl): string
    {
        return \rtrim($baseUrl, '/') . $path;
    }
}
