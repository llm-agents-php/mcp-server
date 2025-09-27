<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * OAuth server error (500).
 */
final class ServerError extends OAuthError
{
    public function __construct(string $message = 'Internal Server Error', ?\Throwable $previous = null)
    {
        parent::__construct('server_error', $message, null, $previous);
    }
}
