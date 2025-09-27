<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Contracts;

use Mcp\Server\Authorization\Entities\AccessToken;
use Mcp\Server\Authorization\Exception\InvalidTokenException;

/**
 * Token validator interface for JWT and other token formats
 */
interface TokenValidatorInterface
{
    /**
     * Validate and parse an access token
     *
     * @throws InvalidTokenException When token is invalid, expired, or malformed
     */
    public function validateToken(string $token, string $expectedAudience): AccessToken;

    /**
     * Check if this validator supports the given token format
     */
    public function supports(string $token): bool;
}
