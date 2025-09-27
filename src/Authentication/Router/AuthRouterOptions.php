<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;

/**
 * Configuration for OAuth router.
 */
final readonly class AuthRouterOptions
{
    /**
     * @param string[] $scopesSupported
     */
    public function __construct(
        public OAuthServerProviderInterface $provider,
        public string $issuerUrl,
        public ?string $baseUrl = null,
        public ?string $serviceDocumentationUrl = null,
        public array $scopesSupported = [],
        public ?string $resourceName = null,
    ) {}
}
