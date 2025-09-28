<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

use Mcp\Server\Authentication\Contract\TokenIntrospectionClientInterface;
use Mcp\Server\Authentication\Error\InvalidTokenError;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP client for OAuth token introspection and userinfo requests.
 */
final readonly class TokenIntrospectionClient implements TokenIntrospectionClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function introspectToken(string $token, string $introspectionUrl, ?array $headers = null): array
    {
        $data = ['token' => $token];

        $request = $this->requestFactory
            ->createRequest('POST', $introspectionUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(\http_build_query($data)));

        if ($headers) {
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new InvalidTokenError("Token introspection failed: HTTP {$response->getStatusCode()}");
        }

        $body = (string)$response->getBody();
        $data = \json_decode($body, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidTokenError('Invalid JSON response from introspection endpoint');
        }

        if (!\is_array($data)) {
            throw new InvalidTokenError('Introspection response must be a JSON object');
        }

        // RFC 7662: active field indicates if token is active
        if (isset($data['active']) && !$data['active']) {
            throw new InvalidTokenError('Token is not active');
        }

        return $data;
    }

    public function getUserInfo(string $token, string $userinfoUrl): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', $userinfoUrl)
            ->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json');

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() === 401) {
            throw new InvalidTokenError('Invalid or expired token');
        }

        if ($response->getStatusCode() !== 200) {
            throw new InvalidTokenError("User info request failed: HTTP {$response->getStatusCode()}");
        }

        $body = (string)$response->getBody();
        $data = \json_decode($body, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidTokenError('Invalid JSON response from userinfo endpoint');
        }

        if (!\is_array($data)) {
            throw new InvalidTokenError('Userinfo response must be a JSON object');
        }

        return $data;
    }
}
