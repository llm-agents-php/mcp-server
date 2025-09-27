<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Contract;

use Mcp\Server\Authentication\AuthInfo;

/**
 * Slim implementation useful for token verification only.
 */
interface OAuthTokenVerifierInterface
{
    /**
     * Verifies an access token and returns information about it.
     *
     * @throws \RuntimeException if token verification fails
     */
    public function verifyAccessToken(string $token): AuthInfo;
}
