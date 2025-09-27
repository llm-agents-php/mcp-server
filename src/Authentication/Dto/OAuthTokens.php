<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth tokens response as defined in RFC 6749.
 */
final readonly class OAuthTokens implements \JsonSerializable
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public ?string $idToken = null,
        public ?int $expiresIn = null,
        public ?string $scope = null,
        public ?string $refreshToken = null,
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'id_token' => $this->idToken,
            'expires_in' => $this->expiresIn,
            'scope' => $this->scope,
            'refresh_token' => $this->refreshToken,
        ], static fn($value) => $value !== null);
    }
}
