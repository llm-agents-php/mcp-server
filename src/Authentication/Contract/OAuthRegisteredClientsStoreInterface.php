<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Contract;

use Mcp\Server\Authentication\Dto\OAuthClientInformation;

/**
 * Store for registered OAuth clients.
 */
interface OAuthRegisteredClientsStoreInterface
{
    /**
     * Get client information by client ID.
     *
     * @throws \RuntimeException if client retrieval fails
     */
    public function getClient(string $clientId): ?OAuthClientInformation;

    /**
     * Register a new client (optional - for dynamic registration).
     *
     * @throws \RuntimeException if client registration fails
     */
    public function registerClient(OAuthClientInformation $client): OAuthClientInformation;
}
