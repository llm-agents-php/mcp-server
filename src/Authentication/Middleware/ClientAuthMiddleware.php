<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Middleware;

use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Error\InvalidClientError;
use Mcp\Server\Authentication\Error\OAuthError;
use Mcp\Server\Authentication\Error\ServerError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that authenticates OAuth clients.
 *
 * Supports both client_secret_post and client_secret_basic authentication methods.
 */
final readonly class ClientAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private OAuthRegisteredClientsStoreInterface $clientsStore,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            $client = $this->authenticateClient($request);

            // Add authenticated client to request attributes
            $request = $request->withAttribute('client', $client);

            return $handler->handle($request);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable) {
            $serverError = new ServerError('Internal Server Error');

            return $this->createErrorResponse($serverError);
        }
    }

    private function authenticateClient(ServerRequestInterface $request)
    {
        $clientId = null;
        $clientSecret = null;

        // Try client_secret_post first (from request body)
        $body = (string) $request->getBody();
        \parse_str($body, $params);

        if (!empty($params['client_id'])) {
            $clientId = $params['client_id'];
            $clientSecret = $params['client_secret'] ?? null;
        }

        // Try client_secret_basic (Authorization header) if not found in body
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
        $client = $this->clientsStore->getClient($clientId);

        if ($client === null) {
            throw new InvalidClientError('Invalid client_id');
        }

        // Verify client secret if the client has one
        if ($client->getClientSecret() !== null) {
            if ($clientSecret === null || !\hash_equals($client->getClientSecret(), $clientSecret)) {
                throw new InvalidClientError('Invalid client_secret');
            }
        }

        return $client;
    }

    private function createErrorResponse(OAuthError $error): ResponseInterface
    {
        $json = \json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->streamFactory->createStream($json);

        $status = $error instanceof ServerError ? 500 : 401;

        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($body);
    }
}
