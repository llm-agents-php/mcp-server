<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

use Mcp\Server\Authentication\Dto\OAuthMetadata;
use Mcp\Server\Authentication\Dto\OAuthProtectedResourceMetadata;
use Mcp\Server\Authentication\Handler\AuthorizeHandler;
use Mcp\Server\Authentication\Handler\MetadataHandler;
use Mcp\Server\Authentication\Handler\RegisterHandler;
use Mcp\Server\Authentication\Handler\RevokeHandler;
use Mcp\Server\Authentication\Handler\TokenHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that handles OAuth endpoints.
 */
final readonly class McpAuthRouter implements MiddlewareInterface
{
    private OAuthMetadata $oauthMetadata;
    private AuthorizeHandler $authorizeHandler;
    private TokenHandler $tokenHandler;
    private ?RegisterHandler $registerHandler;
    private RevokeHandler $revokeHandler;
    private MetadataHandler $metadataHandler;

    public function __construct(
        AuthRouterOptions $options,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $this->oauthMetadata = $this->createOAuthMetadata($options);

        $this->authorizeHandler = new AuthorizeHandler($options->provider, $responseFactory, $streamFactory);
        $this->tokenHandler = new TokenHandler($options->provider, $responseFactory, $streamFactory);
        $this->registerHandler = new RegisterHandler(
            $options->provider->getClientsStore(),
            $responseFactory,
            $streamFactory,
        );
        $this->revokeHandler = new RevokeHandler($options->provider, $responseFactory, $streamFactory);

        // Create protected resource metadata
        $protectedResourceMetadata = new OAuthProtectedResourceMetadata(
            resource: $options->baseUrl ?? $this->oauthMetadata->getIssuer(),
            authorizationServers: [$this->oauthMetadata->getIssuer()],
            jwksUri: null,
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            bearerMethodsSupported: null,
            resourceSigningAlgValuesSupported: null,
            resourceName: $options->resourceName,
            resourceDocumentation: $options->serviceDocumentationUrl,
        );

        $this->metadataHandler = new MetadataHandler(
            $this->oauthMetadata,
            $protectedResourceMetadata,
            $responseFactory,
            $streamFactory,
        );
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match ([$path, $method]) {
            ['/.well-known/mcp-oauth-metadata', 'GET'] => $this->metadataHandler->handleOAuthMetadata($request),
            ['/.well-known/oauth-protected-resource', 'GET'] => $this->metadataHandler->handleProtectedResourceMetadata(
                $request,
            ),
            ['/.well-known/oauth-authorization-server', 'GET'] => $this->metadataHandler->handleOAuthMetadata(
                $request,
            ), // Backwards compatibility
            ['/oauth2/authorize', 'GET'] => $this->authorizeHandler->handle($request),
            ['/oauth2/token', 'POST'] => $this->tokenHandler->handle($request),
            ['/oauth2/revoke', 'POST'] => $this->revokeHandler->handle($request),
            ['/oauth2/register', 'POST'] => $this->registerHandler->handle($request),
            default => $handler->handle($request),
        };
    }

    private function createOAuthMetadata(AuthRouterOptions $options): OAuthMetadata
    {
        $this->checkIssuerUrl($options->issuerUrl);

        $baseUrl = $options->baseUrl ?? $options->issuerUrl;

        return new OAuthMetadata(
            issuer: $options->issuerUrl,
            authorizationEndpoint: $this->buildUrl('/oauth2/authorize', $baseUrl),
            tokenEndpoint: $this->buildUrl('/oauth2/token', $baseUrl),
            responseTypesSupported: ['code'],
            registrationEndpoint: $this->buildUrl('/oauth2/register', $baseUrl),
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            responseModesSupported: null,
            grantTypesSupported: ['authorization_code', 'refresh_token'],
            tokenEndpointAuthMethodsSupported: ['client_secret_post'],
            tokenEndpointAuthSigningAlgValuesSupported: null,
            serviceDocumentation: $options->serviceDocumentationUrl,
            revocationEndpoint: $this->buildUrl('/oauth2/revoke', $baseUrl),
            revocationEndpointAuthMethodsSupported: ['client_secret_post'],
            revocationEndpointAuthSigningAlgValuesSupported: null,
            introspectionEndpoint: null,
            introspectionEndpointAuthMethodsSupported: null,
            introspectionEndpointAuthSigningAlgValuesSupported: null,
            codeChallengeMethodsSupported: ['S256'],
        );
    }

    private function checkIssuerUrl(string $issuer): void
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

    private function buildUrl(string $path, string $baseUrl): string
    {
        return \rtrim($baseUrl, '/') . $path;
    }
}
