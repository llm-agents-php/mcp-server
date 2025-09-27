<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization;

use Mcp\Server\Authorization\Server\ResourceServer;
use Mcp\Server\Authorization\Storage\InMemoryClientRepository;
use Mcp\Server\Authorization\Storage\InMemoryTokenRepository;
use Mcp\Server\Authorization\Validators\JwtTokenValidator;

final readonly class OAuth
{
    public function __construct(
        public string $publicKey,
        public string $serverUrl,
        public array $authorizationServers,
        public string $algorithm = 'RS256',
        public array $unprotectedPaths = ['/.well-known', '/health'],
        public string $issuer = '',
        public string $authorizationEndpoint = '',
        public string $tokenEndpoint = '',
        public array $supportedGrantTypes = ['authorization_code', 'client_credentials'],
        public array $supportedResponseTypes = ['code'],
        public array $supportedScopes = ['mcp:read', 'mcp:write'],
    ) {}
}
