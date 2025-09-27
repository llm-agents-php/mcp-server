<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

use Mcp\Server\Authentication\Dto\OAuthProtectedResourceMetadata;
use Mcp\Server\Authentication\Handler\MetadataHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Metadata-only router for resource servers that don't provide authorization endpoints.
 */
final readonly class McpAuthMetadataRouter implements MiddlewareInterface
{
    private MetadataHandler $metadataHandler;

    public function __construct(
        AuthMetadataOptions $options,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ) {
        $protectedResourceMetadata = new OAuthProtectedResourceMetadata(
            resource: $options->resourceServerUrl,
            authorizationServers: [$options->oauthMetadata->getIssuer()],
            jwksUri: null,
            scopesSupported: empty($options->scopesSupported) ? null : $options->scopesSupported,
            bearerMethodsSupported: null,
            resourceSigningAlgValuesSupported: null,
            resourceName: $options->resourceName,
            resourceDocumentation: $options->serviceDocumentationUrl,
        );

        $this->metadataHandler = new MetadataHandler($options->oauthMetadata, $protectedResourceMetadata, $responseFactory, $streamFactory);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match ([$path, $method]) {
            ['/.well-known/oauth-protected-resource', 'GET'] => $this->metadataHandler->handleProtectedResourceMetadata($request),
            ['/.well-known/oauth-authorization-server', 'GET'] => $this->metadataHandler->handleOAuthMetadata($request),
            default => $handler->handle($request),
        };
    }
}
