<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication;

/**
 * Parameters for OAuth authorization flow.
 */
final readonly class AuthorizationParams
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        public string $codeChallenge,
        public string $redirectUri,
        public ?string $state = null,
        public array $scopes = [],
        public ?string $resource = null,
    ) {}
}
