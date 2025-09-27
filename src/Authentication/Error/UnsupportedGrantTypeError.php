<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Unsupported grant type error (400).
 */
final class UnsupportedGrantTypeError extends OAuthError
{
    public function __construct(string $message = 'Unsupported grant type', ?\Throwable $previous = null)
    {
        parent::__construct('unsupported_grant_type', $message, null, $previous);
    }
}
