<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Invalid client error (401).
 */
final class InvalidClientError extends OAuthError
{
    public function __construct(string $message = 'Invalid client', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_client', $message, null, $previous);
    }
}
