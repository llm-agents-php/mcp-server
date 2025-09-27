<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Invalid token error (401).
 */
final class InvalidTokenError extends OAuthError
{
    public function __construct(string $message = 'Invalid token', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_token', $message, null, $previous);
    }
}
