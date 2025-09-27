<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Insufficient scope error (403).
 */
final class InsufficientScopeError extends OAuthError
{
    public function __construct(string $message = 'Insufficient scope', ?\Throwable $previous = null)
    {
        parent::__construct('insufficient_scope', $message, null, $previous);
    }
}
