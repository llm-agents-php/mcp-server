<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Dto\OAuthTokenRevocationRequest;
use Mcp\Server\Authentication\Error\InvalidClientError;
use Mcp\Server\Authentication\Error\InvalidRequestError;
use Mcp\Server\Authentication\Error\OAuthError;
use Mcp\Server\Authentication\Error\ServerError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Handles OAuth token revocation requests.
 */
final readonly class RevokeHandler
{
    public function __construct(
        private OAuthServerProviderInterface $provider,
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

            // Parse request body
            $body = (string) $request->getBody();
            \parse_str($body, $params);

            if (empty($params['token'])) {
                throw new InvalidRequestError('Missing token parameter');
            }

            // Authenticate client
            $client = $this->authenticateClient($request, $params);

            // Create revocation request
            $revocationRequest = new OAuthTokenRevocationRequest(
                token: $params['token'],
                tokenTypeHint: $params['token_type_hint'] ?? null,
            );

            // Revoke the token
            $this->provider->revokeToken($client, $revocationRequest);

            // Return successful response (200 OK with empty body)
            return $this->responseFactory
                ->createResponse(200)
                ->withHeader('Cache-Control', 'no-store');
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable) {
            $serverError = new ServerError('Internal Server Error');

            return $this->createErrorResponse($serverError);
        }
    }

    private function authenticateClient(ServerRequestInterface $request, array $params)
    {
        $clientId = null;
        $clientSecret = null;

        // Try client_secret_post first
        if (!empty($params['client_id'])) {
            $clientId = $params['client_id'];
            $clientSecret = $params['client_secret'] ?? null;
        }

        // Try client_secret_basic (Authorization header)
        if ($clientId === null) {
            $authHeader = $request->getHeaderLine('Authorization');
            if (\str_starts_with($authHeader, 'Basic ')) {
                $credentials = \base64_decode(\substr($authHeader, 6));
                if ($credentials !== false && \str_contains($credentials, ':')) {
                    [$clientId, $clientSecret] = \explode(':', $credentials, 2);
                }
            }
        }

        if ($clientId === null) {
            throw new InvalidClientError('Client authentication failed');
        }

        // Get client from store
        $client = $this->provider->getClientsStore()->getClient($clientId);

        if ($client === null) {
            throw new InvalidClientError('Invalid client_id');
        }

        // Verify client secret if present
        if ($client->getClientSecret() !== null && $client->getClientSecret() !== $clientSecret) {
            throw new InvalidClientError('Invalid client_secret');
        }

        return $client;
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
