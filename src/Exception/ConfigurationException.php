<?php

declare(strict_types=1);

namespace Mcp\Server\Exception;

/**
 * Exception related to invalid server configuration.
 */
class ConfigurationException extends McpServerException
{
    // No specific JSON-RPC code, usually an internal setup issue.
    // Code 0 is appropriate.
}
