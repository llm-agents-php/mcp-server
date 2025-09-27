<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Exception;

use Mcp\Server\Exception\McpServerException;

/**
 * Exception for client-related errors
 */
class InvalidClientException extends McpServerException
{
    public function __construct(
        string $message = 'Invalid client',
        public readonly string $clientId = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 400, $previous);
    }

    /**
     * Create exception for unknown client
     */
    public static function notFound(string $clientId): self
    {
        return new self("Client not found: {$clientId}", $clientId);
    }

    /**
     * Create exception for invalid client credentials
     */
    public static function invalidCredentials(string $clientId): self
    {
        return new self("Invalid client credentials for: {$clientId}", $clientId);
    }

    /**
     * Create exception for invalid redirect URI
     */
    public static function invalidRedirectUri(string $clientId, string $redirectUri): self
    {
        return new self(
            "Invalid redirect URI '{$redirectUri}' for client: {$clientId}",
            $clientId
        );
    }
}
