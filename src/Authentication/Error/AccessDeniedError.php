<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Error;

/**
 * Access denied error (403).
 */
final class AccessDeniedError extends OAuthError
{
    public function __construct(string $message = 'Access denied', ?\Throwable $previous = null)
    {
        parent::__construct('access_denied', $message, null, $previous);
    }
}
