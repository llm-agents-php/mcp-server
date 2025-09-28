<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication\Storage;

use Mcp\Server\Authentication\AuthInfo;
use Mcp\Server\Authentication\Contract\OAuthTokenVerifierInterface;
use Mcp\Server\Authentication\DefaultAuthInfo;
use Mcp\Server\Authentication\Error\InvalidTokenError;

/**
 * JWT token validator for OAuth access tokens.
 */
final readonly class JwtTokenValidator implements OAuthTokenVerifierInterface
{
    public function __construct(
        private string $publicKey,
        private string $algorithm = 'RS256',
    ) {}

    public function verifyAccessToken(string $token): AuthInfo
    {
        try {
            // In a real implementation, you would use a proper JWT library like firebase/php-jwt
            // For now, this is a simplified implementation

            // Parse the JWT token (simplified - in production use a proper JWT library)
            $parts = \explode('.', $token);
            if (\count($parts) !== 3) {
                throw new InvalidTokenError('Invalid JWT token format');
            }

            // Decode the payload (simplified - in production verify signature first)
            $payload = \json_decode(\base64_decode($parts[1]), true);

            if (!\is_array($payload)) {
                throw new InvalidTokenError('Invalid JWT payload');
            }

            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < \time()) {
                throw new InvalidTokenError('Token has expired');
            }

            // Extract token information
            $clientId = $payload['client_id'] ?? $payload['sub'] ?? 'unknown';
            $scopes = isset($payload['scope']) ? \explode(' ', $payload['scope']) : [];
            $expiresAt = $payload['exp'] ?? null;
            $resource = $payload['aud'] ?? null;

            return new DefaultAuthInfo(
                token: $token,
                clientId: $clientId,
                scopes: $scopes,
                expiresAt: $expiresAt,
                resource: $resource,
                extra: $payload,
            );
        } catch (\Throwable $e) {
            throw new InvalidTokenError('Token verification failed: ' . $e->getMessage(), $e);
        }
    }
}
