<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication;

use Mcp\Server\Authentication\Dto\UserProfile;

/**
 * Default implementation of AuthInfo.
 */
final readonly class DefaultAuthInfo implements AuthInfo
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        private string $token,
        private string $clientId,
        private array $scopes,
        private ?UserProfile $profile = null,
        private ?int $expiresAt = null,
        private ?string $resource = null,
    ) {}

    public function getToken(): string
    {
        return $this->token;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function getUserProfile(): ?UserProfile
    {
        return $this->profile;
    }
}
