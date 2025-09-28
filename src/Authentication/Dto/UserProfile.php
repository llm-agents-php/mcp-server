<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Dto;

/**
 * Normalized user profile across OAuth providers.
 */
final readonly class UserProfile implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $extra Provider-specific additional data
     */
    public function __construct(
        public string|int $sub,
        public ?string $preferredUsername = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?bool $emailVerified = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $picture = null,
        public array $extra = [],
    ) {}

    public function jsonSerialize(): array
    {
        return \array_filter([
            'sub' => $this->sub,
            'preferred_username' => $this->preferredUsername,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
            'picture' => $this->picture,
            'extra' => $this->extra,
        ], static fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}
