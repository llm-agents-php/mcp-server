<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Storage;

use Mcp\Server\Authorization\Contracts\ClientRepositoryInterface;
use Mcp\Server\Authorization\Entities\Client;

/**
 * In-memory client repository implementation
 */
final class InMemoryClientRepository implements ClientRepositoryInterface
{
    /** @var array<string, Client> */
    private array $clients = [];

    public function storeClient(Client $client): void
    {
        $this->clients[$client->clientId] = $client;
    }

    public function getClient(string $clientId): ?Client
    {
        return $this->clients[$clientId] ?? null;
    }

    public function revokeClient(string $clientId): void
    {
        unset($this->clients[$clientId]);
    }

    public function clientExists(string $clientId): bool
    {
        return isset($this->clients[$clientId]);
    }

    public function getAllClients(): array
    {
        return array_values($this->clients);
    }

    /**
     * Clear all clients from storage
     */
    public function clearAll(): void
    {
        $this->clients = [];
    }

    /**
     * Get client count
     */
    public function count(): int
    {
        return count($this->clients);
    }

    /**
     * Find clients by type
     *
     * @return Client[]
     */
    public function getClientsByType(string $clientType): array
    {
        return array_filter(
            $this->clients,
            fn(Client $client): bool => $client->clientType->value === $clientType,
        );
    }
}
