<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Validators;

use Mcp\Server\Authorization\Contracts\TokenValidatorInterface;
use Mcp\Server\Authorization\Entities\AccessToken;
use Mcp\Server\Authorization\Exception\InvalidTokenException;

/**
 * JWT token validator implementation
 */
final readonly class JwtTokenValidator implements TokenValidatorInterface
{
    public function __construct(
        private string $publicKey,
        private string $algorithm = 'RS256',
    ) {}

    public function validateToken(string $token, string $expectedAudience): AccessToken
    {
        if (!$this->supports($token)) {
            throw InvalidTokenException::unsupportedFormat('Not a JWT token');
        }

        $payload = $this->decodeAndValidateJwt($token);
        
        // Validate audience (RFC 8707)
        $audience = $payload['aud'] ?? null;
        if ($audience !== $expectedAudience) {
            throw InvalidTokenException::invalidAudience(
                $expectedAudience, 
                (string) $audience
            );
        }

        // Extract token information
        $tokenId = $payload['jti'] ?? $this->generateTokenId($token);
        $clientId = $payload['client_id'] ?? $payload['azp'] ?? 'unknown';
        $subject = $payload['sub'] ?? 'anonymous';
        $issuedAt = isset($payload['iat']) ? (int) $payload['iat'] : null;
        $expiresAt = isset($payload['exp']) ? (int) $payload['exp'] : null;

        // Extract additional claims (remove standard JWT claims)
        $standardClaims = ['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti', 'client_id', 'azp'];
        $claims = array_diff_key($payload, array_flip($standardClaims));

        return new AccessToken(
            tokenId: $tokenId,
            clientId: $clientId,
            audience: $audience,
            subject: $subject,
            claims: $claims,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
        );
    }

    public function supports(string $token): bool
    {
        // JWT tokens have 3 parts separated by dots
        $parts = explode('.', $token);
        return count($parts) === 3;
    }

    /**
     * Decode and validate JWT token
     * 
     * @throws InvalidTokenException
     */
    private function decodeAndValidateJwt(string $token): array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw InvalidTokenException::malformed('JWT must have 3 parts');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Decode header
        $header = $this->base64UrlDecode($headerEncoded);
        if (!$header) {
            throw InvalidTokenException::malformed('Invalid JWT header encoding');
        }

        $headerData = json_decode($header, true);
        if (!is_array($headerData)) {
            throw InvalidTokenException::malformed('Invalid JWT header JSON');
        }

        // Verify algorithm
        $alg = $headerData['alg'] ?? null;
        if ($alg !== $this->algorithm) {
            throw InvalidTokenException::malformed("Unsupported algorithm: {$alg}");
        }

        // Decode payload
        $payload = $this->base64UrlDecode($payloadEncoded);
        if (!$payload) {
            throw InvalidTokenException::malformed('Invalid JWT payload encoding');
        }

        $payloadData = json_decode($payload, true);
        if (!is_array($payloadData)) {
            throw InvalidTokenException::malformed('Invalid JWT payload JSON');
        }

        // Verify signature
        $signature = $this->base64UrlDecode($signatureEncoded);
        if (!$signature) {
            throw InvalidTokenException::malformed('Invalid JWT signature encoding');
        }

        if (!$this->verifySignature($headerEncoded . '.' . $payloadEncoded, $signature)) {
            throw InvalidTokenException::invalidSignature();
        }

        // Check expiration if present (but not required per requirements)
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            throw InvalidTokenException::expired();
        }

        // Check not before if present
        if (isset($payloadData['nbf']) && $payloadData['nbf'] > time()) {
            throw InvalidTokenException::malformed('Token not yet valid (nbf claim)');
        }

        return $payloadData;
    }

    /**
     * Verify JWT signature
     */
    private function verifySignature(string $data, string $signature): bool
    {
        return match ($this->algorithm) {
            'RS256' => $this->verifyRsa256($data, $signature),
            'HS256' => $this->verifyHmac256($data, $signature),
            default => false,
        };
    }

    /**
     * Verify RSA SHA256 signature
     */
    private function verifyRsa256(string $data, string $signature): bool
    {
        $publicKey = openssl_pkey_get_public($this->publicKey);
        if (!$publicKey) {
            throw InvalidTokenException::malformed('Invalid public key');
        }

        $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Verify HMAC SHA256 signature
     */
    private function verifyHmac256(string $data, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $data, $this->publicKey, true);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate token ID from JWT content
     */
    private function generateTokenId(string $token): string
    {
        return hash('sha256', $token);
    }
}
