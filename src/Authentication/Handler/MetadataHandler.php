<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\Dto\OAuthMetadata;
use Mcp\Server\Authentication\Dto\OAuthProtectedResourceMetadata;
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

    /**
     * @throws \JsonException
     */
    public function handleOAuthMetadata(ServerRequestInterface $request): ResponseInterface
    {
        $json = \json_encode($this->oauthMetadata->jsonSerialize(), \JSON_THROW_ON_ERROR);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($body);
    }

    /**
     * @throws \JsonException
     */
    public function handleProtectedResourceMetadata(ServerRequestInterface $request): ResponseInterface
    {
        $json = \json_encode($this->protectedResourceMetadata->jsonSerialize(), \JSON_THROW_ON_ERROR);
        $body = $this->streamFactory->createStream($json);

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody($body);
    }
}
