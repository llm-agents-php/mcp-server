<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Unsupported response type error (400).
 */
final class UnsupportedResponseTypeError extends OAuthError
{
    public function __construct(string $message = 'Unsupported response type', ?\Throwable $previous = null)
    {
        parent::__construct('unsupported_response_type', $message, null, $previous);
    }
}
