<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Error\ServerError;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Client store for proxy provider.
 */
final readonly class ProxyClientsStore implements OAuthRegisteredClientsStoreInterface
{
    /**
     * @param callable(string $clientId):OAuthClientInformation|null $getClient
     * @param non-empty-string|null $registrationUrl
     */
    public function __construct(
        private \Closure $getClient,
        private ?string $registrationUrl,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function getClient(string $clientId): ?OAuthClientInformation
    {
        return ($this->getClient)($clientId);
    }

    public function registerClient(OAuthClientInformation $client): OAuthClientInformation
    {
        if ($this->registrationUrl === null) {
            throw new ServerError('No registration endpoint configured');
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->registrationUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(\json_encode($client->jsonSerialize())));

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 201) {
            throw new ServerError("Client registration failed: {$response->getStatusCode()}");
        }

        $data = \json_decode((string) $response->getBody(), true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new ServerError('Invalid JSON response from registration endpoint');
        }

        return new OAuthClientInformation(
            $data['client_id'],
            $data['client_secret'] ?? null,
            $data['client_id_issued_at'] ?? null,
            $data['client_secret_expires_at'] ?? null,
        );
    }
}
