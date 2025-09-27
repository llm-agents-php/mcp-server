<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Handler;

use Mcp\Server\Authentication\AuthorizationParams;
use Mcp\Server\Authentication\Contract\OAuthServerProviderInterface;
use Mcp\Server\Authentication\Error\InvalidClientError;
use Mcp\Server\Authentication\Error\InvalidRequestError;
use Mcp\Server\Authentication\Error\OAuthError;
use Mcp\Server\Authentication\Error\ServerError;
use Mcp\Server\Authentication\Error\UnsupportedResponseTypeError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Handles OAuth authorization requests.
 */
final readonly class AuthorizeHandler
{
    public function __construct(
        private OAuthServerProviderInterface $provider,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Only GET method is allowed for authorization endpoint
            if ($request->getMethod() !== 'GET') {
                throw new InvalidRequestError('Method not allowed');
            }

            $params = $request->getQueryParams();

            // Validate required parameters
            $this->validateRequiredParams($params);

            // Get client information
            $clientId = $params['client_id'];
            $client = $this->provider->getClientsStore()->getClient($clientId);

            if ($client === null) {
                throw new InvalidClientError('Invalid client_id');
            }

            // Validate response_type
            if ($params['response_type'] !== 'code') {
                throw new UnsupportedResponseTypeError('Only authorization_code flow is supported');
            }

            // Validate PKCE parameters
            if (empty($params['code_challenge']) || empty($params['code_challenge_method'])) {
                throw new InvalidRequestError('PKCE parameters are required');
            }

            if ($params['code_challenge_method'] !== 'S256') {
                throw new InvalidRequestError('Only S256 code_challenge_method is supported');
            }

            // Parse scopes
            $scopes = [];
            if (!empty($params['scope'])) {
                $scopes = \explode(' ', (string) $params['scope']);
            }

            // Create authorization params
            $authParams = new AuthorizationParams(
                codeChallenge: $params['code_challenge'],
                redirectUri: $params['redirect_uri'],
                state: $params['state'] ?? null,
                scopes: $scopes,
                resource: $params['resource'] ?? null,
            );

            // Create a basic response object
            $response = $this->responseFactory->createResponse();

            // Delegate to the provider
            return $this->provider->authorize($client, $authParams, $response);
        } catch (OAuthError $e) {
            return $this->createErrorResponse($e, $params['redirect_uri'] ?? null, $params['state'] ?? null);
        } catch (\Throwable) {
            $serverError = new ServerError('Internal Server Error');

            return $this->createErrorResponse($serverError, $params['redirect_uri'] ?? null, $params['state'] ?? null);
        }
    }

    private function validateRequiredParams(array $params): void
    {
        $required = ['client_id', 'response_type', 'redirect_uri', 'code_challenge', 'code_challenge_method'];

        foreach ($required as $param) {
            if (empty($params[$param])) {
                throw new InvalidRequestError("Missing required parameter: {$param}");
            }
        }

        // Validate redirect_uri format
        if (!\filter_var($params['redirect_uri'], FILTER_VALIDATE_URL)) {
            throw new InvalidRequestError('Invalid redirect_uri format');
        }
    }

    private function createErrorResponse(OAuthError $error, ?string $redirectUri, ?string $state): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        // If we have a redirect URI, redirect with error
        if ($redirectUri !== null) {
            $params = [
                'error' => $error->getErrorCode(),
                'error_description' => $error->getMessage(),
            ];

            if ($state !== null) {
                $params['state'] = $state;
            }

            $separator = \str_contains($redirectUri, '?') ? '&' : '?';
            $errorUrl = $redirectUri . $separator . \http_build_query($params);

            return $response
                ->withStatus(302)
                ->withHeader('Location', $errorUrl);
        }

        // Otherwise return JSON error
        $json = \json_encode($error->toResponseObject()->jsonSerialize());
        $body = $this->streamFactory->createStream($json);

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }
}
