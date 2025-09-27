<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Router;

use Mcp\Server\Authentication\Dto\OAuthMetadata;

/**
 * Configuration for auth metadata router.
 */
final readonly class AuthMetadataOptions
{
    /**
     * @param string[] $scopesSupported
     */
    public function __construct(
        public OAuthMetadata $oauthMetadata,
        public string $resourceServerUrl,
        public ?string $serviceDocumentationUrl = null,
        public array $scopesSupported = [],
        public ?string $resourceName = null,
    ) {}
}
