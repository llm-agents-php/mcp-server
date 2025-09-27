<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth error response as defined in RFC 6749 Section 4.1.2.1.
 */
final readonly class OAuthErrorResponse implements \JsonSerializable
{
    public function __construct(
        public string $error,
        public ?string $errorDescription = null,
        public ?string $errorUri = null,
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'error' => $this->error,
            'error_description' => $this->errorDescription,
            'error_uri' => $this->errorUri,
        ], static fn($value) => $value !== null);
    }
}
