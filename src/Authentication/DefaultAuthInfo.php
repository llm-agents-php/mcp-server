<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication;

/**
 * Default implementation of AuthInfo.
 */
final readonly class DefaultAuthInfo implements AuthInfo
{
    /**
     * @param string[] $scopes
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private string $token,
        private string $clientId,
        private array $scopes,
        private ?int $expiresAt = null,
        private ?string $resource = null,
        private array $extra = [],
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

    public function getExtra(): array
    {
        return $this->extra;
    }
}
