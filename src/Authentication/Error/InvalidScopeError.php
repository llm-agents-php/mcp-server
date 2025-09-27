<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Invalid scope error (400).
 */
final class InvalidScopeError extends OAuthError
{
    public function __construct(string $message = 'Invalid scope', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_scope', $message, null, $previous);
    }
}
