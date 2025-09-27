<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Entities;

/**
 * Represents an OAuth 2.1 access token with audience binding
 */
final readonly class AccessToken implements \JsonSerializable
{
    public function __construct(
        public string $tokenId,
        public string $clientId,
        public string $audience,
        public string $subject,
        public array $claims = [],
        public ?int $issuedAt = null,
        public ?int $expiresAt = null,
    ) {}

    /**
     * Check if token is expired (if expiration is set)
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false; // No expiration set
        }

        return $this->expiresAt < time();
    }

    /**
     * Check if token is valid for the given audience
     */
    public function isValidForAudience(string $expectedAudience): bool
    {
        return $this->audience === $expectedAudience;
    }

    /**
     * Get a specific claim value
     */
    public function getClaim(string $name, mixed $default = null): mixed
    {
        return $this->claims[$name] ?? $default;
    }

    public function jsonSerialize(): array
    {
        return [
            'token_id' => $this->tokenId,
            'client_id' => $this->clientId,
            'audience' => $this->audience,
            'subject' => $this->subject,
            'claims' => $this->claims,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
