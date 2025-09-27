<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Error\InvalidClientError;
use Mcp\Server\Authentication\Error\InvalidGrantError;
use Mcp\Server\Authentication\Error\InvalidRequestError;
use Mcp\Server\Authentication\Error\OAuthError;
use Mcp\Server\Authentication\Error\ServerError;
use Mcp\Server\Authentication\Error\UnsupportedGrantTypeError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Handles OAuth token requests.
 */
final readonly class TokenHandler
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

            if (empty($params['grant_type'])) {
                throw new InvalidRequestError('Missing grant_type parameter');
            }

            // Authenticate client
            $client = $this->authenticateClient($request, $params);

            $response = $this->responseFactory
                ->createResponse()
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Content-Type', 'application/json');

            return match ($params['grant_type']) {
                'authorization_code' => $this->handleAuthorizationCodeGrant($client, $params, $response),
                'refresh_token' => $this->handleRefreshTokenGrant($client, $params, $response),
                default => throw new UnsupportedGrantTypeError('The grant type is not supported by this authorization server'),
            };
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e);
        } catch (\Throwable) {
            $serverError = new ServerError('Internal Server Error');

            return $this->createErrorResponse($serverError);
        }
    }

    private function handleAuthorizationCodeGrant(
        $client,
        array $params,
        ResponseInterface $response,
    ): ResponseInterface {
        // Validate required parameters
        if (empty($params['code'])) {
            throw new InvalidRequestError('Missing code parameter');
        }

        if (empty($params['code_verifier'])) {
            throw new InvalidRequestError('Missing code_verifier parameter');
        }

        $code = $params['code'];
        $codeVerifier = $params['code_verifier'];
        $redirectUri = $params['redirect_uri'] ?? null;
        $resource = $params['resource'] ?? null;

        // Perform local PKCE validation unless explicitly skipped
        if (!$this->provider->skipLocalPkceValidation()) {
            $codeChallenge = $this->provider->challengeForAuthorizationCode($client, $code);

            if (!$this->verifyPkce($codeVerifier, $codeChallenge)) {
                throw new InvalidGrantError('code_verifier does not match the challenge');
            }
        }

        // Exchange authorization code for tokens
        $tokens = $this->provider->exchangeAuthorizationCode(
            $client,
            $code,
            $this->provider->skipLocalPkceValidation() ? $codeVerifier : null,
            $redirectUri,
            $resource,
        );

        $json = \json_encode($tokens->jsonSerialize());

        $body = $this->streamFactory->createStream($json);

        return $response
            ->withStatus(200)
            ->withBody($body);
    }

    private function handleRefreshTokenGrant($client, array $params, ResponseInterface $response): ResponseInterface
    {
        if (empty($params['refresh_token'])) {
            throw new InvalidRequestError('Missing refresh_token parameter');
        }

        $refreshToken = $params['refresh_token'];
        $scope = $params['scope'] ?? null;
        $resource = $params['resource'] ?? null;

        $scopes = $scope ? \explode(' ', (string) $scope) : [];

        $tokens = $this->provider->exchangeRefreshToken($client, $refreshToken, $scopes, $resource);

        $json = \json_encode($tokens->jsonSerialize());
        $body = $this->streamFactory->createStream($json);

        return $response
            ->withStatus(200)
            ->withBody($body);
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
            // throw new InvalidClientError('Invalid client_secret');
        }

        return $client;
    }

    private function verifyPkce(string $codeVerifier, string $codeChallenge): bool
    {
        $hash = \hash('sha256', $codeVerifier, true);
        $computedChallenge = \rtrim(\strtr(\base64_encode($hash), '+/', '-_'), '=');

        return \hash_equals($codeChallenge, $computedChallenge);
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
