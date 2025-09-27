<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * OAuth token revocation request as defined in RFC 7009.
 */
final readonly class OAuthTokenRevocationRequest implements \JsonSerializable
{
    public function __construct(
        public string $token,
        public ?string $tokenTypeHint = null,
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'token' => $this->token,
            'token_type_hint' => $this->tokenTypeHint,
        ], static fn($value) => $value !== null);
    }
}
