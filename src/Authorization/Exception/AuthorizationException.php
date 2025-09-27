<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Exception;

use Mcp\Server\Exception\McpServerException;

/**
 * Base exception for OAuth authorization failures
 */
class AuthorizationException extends McpServerException
{
    public function __construct(
        string $message = 'Authorization failed',
        public readonly string $errorCode = 'access_denied',
        public readonly ?string $errorDescription = null,
        int $httpStatusCode = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    /**
     * Create exception for invalid token
     */
    public static function invalidToken(string $reason = 'Invalid or expired token'): self
    {
        return new self(
            message: $reason,
            errorCode: 'invalid_token',
            errorDescription: $reason,
            httpStatusCode: 401,
        );
    }

    /**
     * Create exception for missing token
     */
    public static function missingToken(): self
    {
        return new self(
            message: 'Authorization header is required',
            errorCode: 'invalid_request',
            errorDescription: 'Missing Authorization header',
            httpStatusCode: 401,
        );
    }

    /**
     * Create exception for insufficient scope
     */
    public static function insufficientScope(string $requiredScope): self
    {
        return new self(
            message: "Insufficient scope: {$requiredScope} required",
            errorCode: 'insufficient_scope',
            errorDescription: "The request requires higher privileges than provided by the access token",
            httpStatusCode: 403,
        );
    }

    /**
     * Get error response data for JSON responses
     */
    public function getErrorResponse(): array
    {
        return array_filter([
            'error' => $this->errorCode,
            'error_description' => $this->errorDescription,
        ]);
    }
}
