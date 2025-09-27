<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Exception;

use Mcp\Server\Exception\McpServerException;

/**
 * Exception for invalid or malformed tokens
 */
class InvalidTokenException extends McpServerException
{
    public function __construct(
        string $message = 'Invalid token',
        public readonly string $reason = 'malformed',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $previous);
    }

    /**
     * Create exception for malformed token
     */
    public static function malformed(string $details = ''): self
    {
        $message = 'Token is malformed';
        if ($details) {
            $message .= ": {$details}";
        }

        return new self($message, 'malformed');
    }

    /**
     * Create exception for expired token
     */
    public static function expired(): self
    {
        return new self('Token has expired', 'expired');
    }

    /**
     * Create exception for invalid audience
     */
    public static function invalidAudience(string $expected, string $actual): self
    {
        return new self(
            "Token audience mismatch: expected '{$expected}', got '{$actual}'",
            'invalid_audience',
        );
    }

    /**
     * Create exception for invalid signature
     */
    public static function invalidSignature(): self
    {
        return new self('Token signature verification failed', 'invalid_signature');
    }

    /**
     * Create exception for unsupported token format
     */
    public static function unsupportedFormat(string $format = 'unknown'): self
    {
        return new self("Unsupported token format: {$format}", 'unsupported_format');
    }
}
