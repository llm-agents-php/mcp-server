<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Storage;

use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;

/**
 * In-memory implementation of OAuth client repository for development/testing.
 */
final class InMemoryClientRepository implements OAuthRegisteredClientsStoreInterface
{
    /**
     * @var array<string, OAuthClientInformation>
     */
    public function __construct(private array $clients = []) {}

    public function getClient(string $clientId): ?OAuthClientInformation
    {
        return $this->clients[$clientId] ?? null;
    }

    public function registerClient(OAuthClientInformation $client): OAuthClientInformation
    {
        $this->clients[$client->getClientId()] = $client;
        return $client;
    }
}
