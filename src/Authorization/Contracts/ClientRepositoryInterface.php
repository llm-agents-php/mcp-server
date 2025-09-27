<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Contracts;

use Mcp\Server\Authorization\Entities\Client;

/**
 * Client repository interface for managing OAuth clients
 */
interface ClientRepositoryInterface
{
    /**
     * Store a client registration
     */
    public function storeClient(Client $client): void;
    
    /**
     * Retrieve a client by its identifier
     */
    public function getClient(string $clientId): ?Client;
    
    /**
     * Remove a client from storage
     */
    public function revokeClient(string $clientId): void;
    
    /**
     * Check if a client exists
     */
    public function clientExists(string $clientId): bool;
    
    /**
     * Get all registered clients
     * 
     * @return Client[]
     */
    public function getAllClients(): array;
}
