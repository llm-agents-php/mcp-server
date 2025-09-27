<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\AuthorizationParams;
use Mcp\Server\Authentication\Contract\OAuthRegisteredClientsStoreInterface;
use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Dto\OAuthTokenRevocationRequest;
use Mcp\Server\Authentication\Dto\OAuthTokens;
use Mcp\Server\Authentication\Error\ServerError;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Implements an OAuth server that proxies requests to another OAuth server.
 */
final readonly class ProxyProvider implements OAuthServerProviderInterface
{
    private OAuthRegisteredClientsStoreInterface $clientsStore;

    public function __construct(
        private ProxyEndpoints $endpoints,
        private \Closure $verifyAccessToken,
        private \Closure $getClient,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
        $this->clientsStore = new ProxyClientsStore(
            $this->getClient,
            $this->endpoints->registrationUrl,
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    public function getClientsStore(): OAuthRegisteredClientsStoreInterface
    {
        return $this->clientsStore;
    }

    public function skipLocalPkceValidation(): bool
    {
        return true; // Let upstream server handle PKCE validation
    }

    public function authorize(
        OAuthClientInformation $client,
        AuthorizationParams $params,
        ResponseInterface $response,
    ): ResponseInterface {
        // Build authorization URL with all required parameters
        $url = $this->endpoints->authorizationUrl;
        $queryParams = [
            'client_id' => $client->getClientId(),
            'response_type' => 'code',
            'redirect_uri' => $params->redirectUri,
            'code_challenge' => $params->codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        // Add optional parameters
        if ($params->state !== null) {
            $queryParams['state'] = $params->state;
        }
        if (!empty($params->scopes)) {
            $queryParams['scope'] = \implode(' ', $params->scopes);
        }
        if ($params->resource !== null) {
            $queryParams['resource'] = $params->resource;
        }

        $targetUrl = $url . '?' . \http_build_query($queryParams);

        // Create redirect response
        return $response
            ->withStatus(302)
            ->withHeader('Location', $targetUrl);
    }

    public function challengeForAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
    ): string {
        // In proxy setup, we don't store the code challenge ourselves
        // Instead, we proxy the token request and let the upstream server validate it
        return '';
    }

    public function exchangeAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
        ?string $codeVerifier = null,
        ?string $redirectUri = null,
        ?string $resource = null,
    ): OAuthTokens {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getClientId(),
            'code' => $authorizationCode,
        ];

        if ($client->getClientSecret() !== null) {
            $params['client_secret'] = $client->getClientSecret();
        }

        if ($codeVerifier !== null) {
            $params['code_verifier'] = $codeVerifier;
        }

        if ($redirectUri !== null) {
            $params['redirect_uri'] = $redirectUri;
        }

        if ($resource !== null) {
            $params['resource'] = $resource;
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->endpoints->tokenUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(\json_encode($params)));

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new ServerError("Token exchange failed: {$response->getStatusCode()}");
        }

        $data = \json_decode((string) $response->getBody(), true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new ServerError('Invalid JSON response from token endpoint');
        }

        return new OAuthTokens(
            $data['access_token'],
            $data['token_type'] ?? 'Bearer',
            $data['id_token'] ?? null,
            $data['expires_in'] ?? null,
            $data['scope'] ?? null,
            $data['refresh_token'] ?? null,
        );
    }

    public function exchangeRefreshToken(
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null,
    ): OAuthTokens {
        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getClientId(),
            'refresh_token' => $refreshToken,
        ];

        if ($client->getClientSecret() !== null) {
            $params['client_secret'] = $client->getClientSecret();
        }

        if (!empty($scopes)) {
            $params['scope'] = \implode(' ', $scopes);
        }

        if ($resource !== null) {
            $params['resource'] = $resource;
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->endpoints->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(\http_build_query($params)));

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new ServerError("Token refresh failed: {$response->getStatusCode()}");
        }

        $data = \json_decode((string) $response->getBody(), true);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new ServerError('Invalid JSON response from token endpoint');
        }

        return new OAuthTokens(
            $data['access_token'],
            $data['token_type'] ?? 'Bearer',
            $data['id_token'] ?? null,
            $data['expires_in'] ?? null,
            $data['scope'] ?? null,
            $data['refresh_token'] ?? null,
        );
    }

    public function verifyAccessToken(string $token): AuthInfo
    {
        return ($this->verifyAccessToken)($token);
    }

    public function revokeToken(
        OAuthClientInformation $client,
        OAuthTokenRevocationRequest $request,
    ): void {
        if ($this->endpoints->revocationUrl === null) {
            throw new ServerError('No revocation endpoint configured');
        }

        $params = [
            'token' => $request->token,
            'client_id' => $client->getClientId(),
        ];

        if ($client->getClientSecret() !== null) {
            $params['client_secret'] = $client->getClientSecret();
        }

        if ($request->tokenTypeHint !== null) {
            $params['token_type_hint'] = $request->tokenTypeHint;
        }

        $httpRequest = $this->requestFactory
            ->createRequest('POST', $this->endpoints->revocationUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(\http_build_query($params)));

        $response = $this->httpClient->sendRequest($httpRequest);

        if ($response->getStatusCode() !== 200) {
            throw new ServerError("Token revocation failed: {$response->getStatusCode()}");
        }
    }
}
