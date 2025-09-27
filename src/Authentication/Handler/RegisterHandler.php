<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Error\InvalidRequestError;
use Mcp\Server\Authentication\Error\OAuthError;
use Mcp\Server\Authentication\Error\ServerError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Handles OAuth dynamic client registration requests.
 */
final readonly class RegisterHandler
{
    public function __construct(
        private OAuthRegisteredClientsStoreInterface $clientsStore,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Only POST method is allowed
            if ($request->getMethod() !== 'POST') {
                throw new InvalidRequestError('Method not allowed');
            }

            // Parse JSON request body
            $body = (string) $request->getBody();
            $data = \json_decode($body, true);

            if (\json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidRequestError('Invalid JSON in request body');
            }

            if (!\is_array($data)) {
                throw new InvalidRequestError('Request body must be a JSON object');
            }

            // Validate client metadata
            $this->validateClientMetadata($data);

            // Generate client credentials
            $clientId = $this->generateClientId();
            $clientSecret = $this->generateClientSecret();

            // Create client information
            $client = new OAuthClientInformation(
                clientId: $clientId,
                clientSecret: $clientSecret,
                clientIdIssuedAt: \time(),
                clientSecretExpiresAt: null, // Never expires for simplicity
            );

            // Register the client
            $registeredClient = $this->clientsStore->registerClient($client);

            // Return the registered client information
            $json = \json_encode($registeredClient->jsonSerialize());
            $body = $this->streamFactory->createStream($json);

            return $this->responseFactory
                ->createResponse(201)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'no-store')
                ->withBody($body);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable) {
            $serverError = new ServerError('Internal Server Error');

            return $this->createErrorResponse($serverError);
        }
    }

    private function validateClientMetadata(array $data): void
    {
        // Basic validation - in a real implementation you'd validate more fields
        // like redirect_uris, grant_types, response_types, etc.

        if (isset($data['redirect_uris'])) {
            if (!\is_array($data['redirect_uris'])) {
                throw new InvalidRequestError('redirect_uris must be an array');
            }

            foreach ($data['redirect_uris'] as $uri) {
                if (!\is_string($uri) || !\filter_var($uri, FILTER_VALIDATE_URL)) {
                    throw new InvalidRequestError('Invalid redirect_uri format');
                }
            }
        }

        if (isset($data['client_name']) && !\is_string($data['client_name'])) {
            throw new InvalidRequestError('client_name must be a string');
        }

        if (isset($data['scope']) && !\is_string($data['scope'])) {
            throw new InvalidRequestError('scope must be a string');
        }
    }

    private function generateClientId(): string
    {
        return 'client_' . \bin2hex(\random_bytes(16));
    }

    private function generateClientSecret(): string
    {
        return \bin2hex(\random_bytes(32));
    }

    private function createErrorResponse(OAuthError $error): ResponseInterface
    {
        $json = \json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->streamFactory->createStream($json);

        $status = $error instanceof ServerError ? 500 : 400;

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($body);
    }
}
