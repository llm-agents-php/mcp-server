<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Contracts;

use Mcp\Server\Authorization\Entities\AccessToken;

/**
 * Token repository interface for storing and retrieving tokens
 */
interface TokenRepositoryInterface
{
    /**
     * Store an access token
     */
    public function storeToken(AccessToken $token): void;
    
    /**
     * Retrieve a token by its identifier
     */
    public function getToken(string $tokenId): ?AccessToken;
    
    /**
     * Remove a token from storage
     */
    public function revokeToken(string $tokenId): void;
    
    /**
     * Check if a token exists and is valid
     */
    public function isTokenValid(string $tokenId): bool;
}
