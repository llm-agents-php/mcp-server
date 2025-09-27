<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Entities;

/**
 * Represents an OAuth 2.1 client registration
 */
final readonly class Client implements \JsonSerializable
{
    public function __construct(
        public string $clientId,
        public string $clientName,
        public array $redirectUris,
        public ClientType $clientType,
        public ?string $clientSecret = null,
        public array $metadata = [],
    ) {}

    /**
     * Check if this is a confidential client
     */
    public function isConfidential(): bool
    {
        return $this->clientType === ClientType::CONFIDENTIAL;
    }

    /**
     * Check if this is a public client
     */
    public function isPublic(): bool
    {
        return $this->clientType === ClientType::PUBLIC;
    }

    /**
     * Validate if a redirect URI is allowed for this client
     */
    public function isValidRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->redirectUris, true);
    }

    public function jsonSerialize(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'redirect_uris' => $this->redirectUris,
            'client_type' => $this->clientType->value,
            'client_secret' => $this->clientSecret !== null ? '[REDACTED]' : null,
            'metadata' => $this->metadata,
        ];
    }
}
