<?php

declare(strict_types=1);

namespace Mcp\Server\Authorization\Contracts;

use Mcp\Server\Authorization\Entities\AccessToken;
use Mcp\Server\Authorization\Exception\AuthorizationException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resource server interface for protecting MCP endpoints
 */
interface ResourceServerInterface
{
    /**
     * Extract and validate access token from HTTP request
     * 
     * @throws AuthorizationException When authorization fails
     */
    public function validateRequest(ServerRequestInterface $request, string $expectedAudience): AccessToken;
    
    /**
     * Generate Protected Resource Metadata per RFC 9728
     */
    public function getResourceMetadata(): array;
    
    /**
     * Check if a request requires authorization
     */
    public function requiresAuthorization(ServerRequestInterface $request): bool;
    
    /**
     * Create WWW-Authenticate response header per RFC 6750 and RFC 9728
     */
    public function createWwwAuthenticateHeader(?string $error = null, ?string $errorDescription = null): string;
    
    /**
     * Generate OAuth 2.0 Authorization Server Metadata per RFC 8414
     */
    public function getAuthorizationServerMetadata(): array;
    
    /**
     * Generate OpenID Connect Discovery metadata
     */
    public function getOpenIdConnectDiscoveryMetadata(): array;
}
