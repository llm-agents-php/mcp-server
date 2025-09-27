<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Invalid request error (400).
 */
final class InvalidRequestError extends OAuthError
{
    public function __construct(string $message = 'Invalid request', ?\Throwable $previous = null)
    {
        parent::__construct('invalid_request', $message, null, $previous);
    }
}
