<?php

declare(strict_types=1);

namespace Mcp\Server;

use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\ServerCapabilities;

/**
 * Value Object holding core configuration and shared dependencies for the MCP Server instance.
 *
 * This object is typically assembled by the ServerBuilder and passed to the Server constructor.
 */
final readonly class Configuration
{
    /**
     * @param Implementation $serverInfo Info about this MCP server application.
     * @param ServerCapabilities $capabilities Capabilities of this MCP server application.
     * @param int $paginationLimit Maximum number of items to return for list methods.
     * @param string|null $instructions Instructions describing how to use the server and its features.
     */
    public function __construct(
        public Implementation $serverInfo,
        public ServerCapabilities $capabilities,
        public int $paginationLimit = 50,
        public ?string $instructions = null,
    ) {}
}
