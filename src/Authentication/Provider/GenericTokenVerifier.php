<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Provider;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\Contract\OAuthTokenVerifierInterface;
use Mcp\Server\Authentication\Contract\TokenIntrospectionClientInterface;
use Mcp\Server\Authentication\DefaultAuthInfo;
use Mcp\Server\Authentication\Dto\UserProfile;
use Mcp\Server\Authentication\Error\InvalidTokenError;

/**
 * Generic OAuth token verifier supporting multiple providers.
 */
final readonly class GenericTokenVerifier implements OAuthTokenVerifierInterface
{
    public function __construct(
        private TokenIntrospectionConfig $config,
        private TokenIntrospectionClientInterface $client,
    ) {}

    /**
     * @throws InvalidTokenError
     */
    public function verifyAccessToken(string $token): AuthInfo
    {
        // Verify token and get user profile
        $tokenData = $this->verifyToken($token);
        $userProfile = $this->getUserProfile($token, $tokenData);

        // Create AuthInfo
        return $this->createAuthInfo($token, $tokenData, $userProfile);
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidTokenError
     */
    private function verifyToken(string $token): array
    {
        // Try introspection first if configured and enabled
        if ($this->config->useIntrospection && $this->config->introspectionUrl !== null) {
            try {
                return $this->client->introspectToken(
                    $token,
                    $this->config->introspectionUrl,
                    $this->config->headers,
                );
            } catch (\Throwable $e) {
                // If introspection fails and we have userinfo URL, fall back to it
                if ($this->config->userinfoUrl !== null) {
                    // For userinfo-only flow, we'll get token info from the user profile
                    return [];
                }

                throw $e;
            }
        }

        // For providers without introspection, we'll validate through userinfo endpoint
        if ($this->config->userinfoUrl !== null) {
            // Token validation will happen when we call getUserInfo
            return [];
        }

        throw new InvalidTokenError('No token verification endpoint configured');
    }

    private function getUserProfile(string $token, array $tokenData): UserProfile
    {
        if ($this->config->userinfoUrl === null) {
            // Try to build user profile from introspection data
            return $this->buildUserProfileFromIntrospection($tokenData);
        }

        // Get user info from userinfo endpoint
        $userInfo = $this->client->getUserInfo($token, $this->config->userinfoUrl);

        return $this->buildUserProfileFromUserInfo($userInfo);
    }

    private function buildUserProfileFromIntrospection(array $data): UserProfile
    {
        // Map introspection fields to standard profile fields
        $mappedData = $this->mapFields($data, $this->config->userFieldMapping);

        return new UserProfile(
            sub: $mappedData['sub'] ?? $data['sub'] ?? 'unknown',
            preferredUsername: $mappedData['preferred_username'] ?? $data['username'] ?? null,
            name: $mappedData['name'] ?? $data['name'] ?? null,
            email: $mappedData['email'] ?? $data['email'] ?? null,
            emailVerified: $mappedData['email_verified'] ?? $data['email_verified'] ?? null,
            givenName: $mappedData['given_name'] ?? $data['given_name'] ?? null,
            familyName: $mappedData['family_name'] ?? $data['family_name'] ?? null,
            picture: $mappedData['picture'] ?? $data['picture'] ?? null,
            extra: $data,
        );
    }

    private function buildUserProfileFromUserInfo(array $data): UserProfile
    {
        // Map provider fields to standard profile fields
        $mappedData = $this->mapFields($data, $this->config->userFieldMapping);

        return new UserProfile(
            sub: $mappedData['sub'] ?? $data['id'] ?? (string) ($data['user_id'] ?? 'unknown'),
            preferredUsername: $mappedData['preferred_username'] ?? $data['login'] ?? $data['username'] ?? null,
            name: $mappedData['name'] ?? $data['name'] ?? null,
            email: $mappedData['email'] ?? $data['email'] ?? null,
            emailVerified: $mappedData['email_verified'] ?? $data['email_verified'] ?? null,
            givenName: $mappedData['given_name'] ?? $data['given_name'] ?? null,
            familyName: $mappedData['family_name'] ?? $data['family_name'] ?? null,
            picture: $mappedData['picture'] ?? $data['avatar_url'] ?? $data['picture'] ?? null,
            extra: $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $mapping
     * @return array<string, mixed>
     */
    private function mapFields(array $data, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $sourceField => $targetField) {
            if (isset($data[$sourceField])) {
                $mapped[$targetField] = $data[$sourceField];
            }
        }

        return $mapped;
    }

    private function createAuthInfo(string $token, array $tokenData, UserProfile $userProfile): AuthInfo
    {
        // Extract client ID
        $clientId = $tokenData['client_id'] ?? $tokenData['aud'] ?? $userProfile->sub;

        // Extract scopes
        $scopes = [];
        if (isset($tokenData['scope'])) {
            $scopes = \is_array($tokenData['scope'])
                ? $tokenData['scope']
                : \explode(' ', (string) $tokenData['scope']);
        } elseif (isset($tokenData['scopes'])) {
            $scopes = \is_array($tokenData['scopes']) ? $tokenData['scopes'] : [$tokenData['scopes']];
        }

        // Extract expiration
        $expiresAt = $tokenData['exp'] ?? null;
        if ($expiresAt !== null && !\is_int($expiresAt)) {
            $expiresAt = (int) $expiresAt;
        }

        // Extract resource/audience
        $resource = $tokenData['aud'] ?? $tokenData['resource'] ?? null;

        // Combine all extra data
        $extra = \array_merge(
            ['user_profile' => $userProfile->jsonSerialize()],
            $tokenData,
        );

        return new DefaultAuthInfo(
            token: $token,
            clientId: (string) $clientId,
            scopes: $scopes,
            expiresAt: $expiresAt,
            resource: $resource,
            extra: $extra,
        );
    }
}
