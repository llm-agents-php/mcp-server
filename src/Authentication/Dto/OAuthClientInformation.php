<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth client information as used in dynamic registration and client authentication.
 */
final readonly class OAuthClientInformation implements \JsonSerializable
{
    public function __construct(
        public string $clientId,
        public ?string $clientSecret = null,
        public ?int $clientIdIssuedAt = null,
        public ?int $clientSecretExpiresAt = null,
    ) {}

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getClientIdIssuedAt(): ?int
    {
        return $this->clientIdIssuedAt;
    }

    public function getClientSecretExpiresAt(): ?int
    {
        return $this->clientSecretExpiresAt;
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'client_id_issued_at' => $this->clientIdIssuedAt,
            'client_secret_expires_at' => $this->clientSecretExpiresAt,
        ], static fn($value) => $value !== null);
    }
}
