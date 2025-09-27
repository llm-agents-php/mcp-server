<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Contract;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\AuthorizationParams;
use Mcp\Server\Authentication\Dto\OAuthClientInformation;
use Mcp\Server\Authentication\Dto\OAuthTokenRevocationRequest;
use Mcp\Server\Authentication\Dto\OAuthTokens;
use Psr\Http\Message\ResponseInterface;

/**
 * Implements an end-to-end OAuth server.
 */
interface OAuthServerProviderInterface
{
    /**
     * A store used to read information about registered OAuth clients.
     */
    public function getClientsStore(): OAuthRegisteredClientsStoreInterface;

    /**
     * Begins the authorization flow, which can either be implemented by this server itself
     * or via redirection to a separate authorization server.
     *
     * This server must eventually issue a redirect with an authorization response or an
     * error response to the given redirect URI. Per OAuth 2.1:
     * - In the successful case, the redirect MUST include the `code` and `state` (if present) query parameters.
     * - In the error case, the redirect MUST include the `error` query parameter, and MAY include
     *   an optional `error_description` query parameter.
     *
     * @throws \RuntimeException if authorization fails
     */
    public function authorize(
        OAuthClientInformation $client,
        AuthorizationParams $params,
        ResponseInterface $response,
    ): ResponseInterface;

    /**
     * Returns the `codeChallenge` that was used when the indicated authorization began.
     *
     * @throws \RuntimeException if code challenge retrieval fails
     */
    public function challengeForAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
    ): string;

    /**
     * Exchanges an authorization code for an access token.
     *
     * @throws \RuntimeException if token exchange fails
     */
    public function exchangeAuthorizationCode(
        OAuthClientInformation $client,
        string $authorizationCode,
        ?string $codeVerifier = null,
        ?string $redirectUri = null,
        ?string $resource = null,
    ): OAuthTokens;

    /**
     * Exchanges a refresh token for an access token.
     *
     * @param string[] $scopes
     *
     * @throws \RuntimeException if token exchange fails
     */
    public function exchangeRefreshToken(
        OAuthClientInformation $client,
        string $refreshToken,
        array $scopes = [],
        ?string $resource = null,
    ): OAuthTokens;

    /**
     * Verifies an access token and returns information about it.
     *
     * @throws \RuntimeException if token verification fails
     */
    public function verifyAccessToken(string $token): AuthInfo;

    /**
     * Revokes an access or refresh token. If unimplemented, token revocation is not supported (not recommended).
     *
     * If the given token is invalid or already revoked, this method should do nothing.
     *
     * @throws \RuntimeException if token revocation fails
     */
    public function revokeToken(
        OAuthClientInformation $client,
        OAuthTokenRevocationRequest $request,
    ): void;

    /**
     * Whether to skip local PKCE validation.
     *
     * If true, the server will not perform PKCE validation locally and will pass the
     * code_verifier to the upstream server.
     *
     * NOTE: This should only be true if the upstream server is performing the actual PKCE validation.
     */
    public function skipLocalPkceValidation(): bool;
}
