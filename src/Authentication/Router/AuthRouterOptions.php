<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

/**
 * Configuration for OAuth router.
 */
final readonly class AuthRouterOptions
{
    /**
     * @param string[] $scopesSupported
     * @param string[] $requiredScopes
     */
    public function __construct(
        public string $issuerUrl,
        public ?string $baseUrl = null,
        public ?string $serviceDocumentationUrl = null,
        public array $scopesSupported = [],
        public ?string $resourceName = null,
        public array $requiredScopes = [],
        public ?string $resourceMetadataUrl = null,
    ) {}
}
