<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Too many requests error (429).
 */
final class TooManyRequestsError extends OAuthError
{
    public function __construct(string $message = 'Too many requests', ?\Throwable $previous = null)
    {
        parent::__construct('too_many_requests', $message, null, $previous);
    }
}
