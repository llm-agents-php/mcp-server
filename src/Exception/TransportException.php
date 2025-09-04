<?php

declare(strict_types=1);

namespace Mcp\Server\Exception;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Error as JsonRpcError;

/**
 * Exception related to errors in the underlying transport layer
 * (e.g., socket errors, process management issues, SSE stream errors).
 */
class TransportException extends McpServerException
{
    public function toJsonRpcError(string|int $id): JsonRpcError
    {
        return new JsonRpcError(
            jsonrpc: '2.0',
            id: $id,
            code: Constants::INTERNAL_ERROR,
            message: 'Transport layer error: ' . $this->getMessage(),
            data: null,
        );
    }
}
