<?php

declare(strict_types=1);

namespace Mcp\Server\Authentication;

/**
 * Information about a validated access token, provided to request handlers.
 */
interface AuthInfo
{
    /**
     * The access token.
     */
    public function getToken(): string;

    /**
     * The client ID associated with this token.
     */
    public function getClientId(): string;

    /**
     * Scopes associated with this token.
     *
     * @return string[]
     */
    public function getScopes(): array;

    /**
     * When the token expires (in seconds since epoch).
     */
    public function getExpiresAt(): ?int;

    /**
     * The RFC 8707 resource server identifier for which this token is valid.
     * If set, this MUST match the MCP server's resource identifier (minus hash fragment).
     */
    public function getResource(): ?string;

    /**
     * Additional data associated with the token.
     * This field should be used for any additional data that needs to be attached to the auth info.
     *
     * @return array<string, mixed>
     */
    public function getExtra(): array;
}
