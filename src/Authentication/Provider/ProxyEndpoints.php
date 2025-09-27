<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

/**
 * Configuration for proxy endpoints.
 */
final readonly class ProxyEndpoints
{
    public function __construct(
        public string $authorizationUrl,
        public string $tokenUrl,
        public ?string $revocationUrl = null,
        public ?string $registrationUrl = null,
    ) {}
}
