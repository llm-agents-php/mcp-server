<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Invalid grant error (400).
 */
final class InvalidGrantError extends OAuthError
{
    public function __construct(string $message = 'Invalid grant', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_grant', $message, null, $previous);
    }
}
