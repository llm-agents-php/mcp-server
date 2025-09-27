<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Storage;

use Mcp\Server\Authorization\Contracts\TokenRepositoryInterface;
use Mcp\Server\Authorization\Entities\AccessToken;

/**
 * In-memory token repository implementation
 */
final class InMemoryTokenRepository implements TokenRepositoryInterface
{
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    public function storeToken(AccessToken $token): void
    {
        $this->tokens[$token->tokenId] = $token;
    }

    public function getToken(string $tokenId): ?AccessToken
    {
        return $this->tokens[$tokenId] ?? null;
    }

    public function revokeToken(string $tokenId): void
    {
        unset($this->tokens[$tokenId]);
    }

    public function isTokenValid(string $tokenId): bool
    {
        $token = $this->getToken($tokenId);
        
        if ($token === null) {
            return false;
        }
        
        return !$token->isExpired();
    }

    /**
     * Get all stored tokens (useful for debugging/testing)
     * 
     * @return AccessToken[]
     */
    public function getAllTokens(): array
    {
        return array_values($this->tokens);
    }

    /**
     * Clear all tokens from storage
     */
    public function clearAll(): void
    {
        $this->tokens = [];
    }

    /**
     * Get token count
     */
    public function count(): int
    {
        return count($this->tokens);
    }
}
