<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Mcp\Server\Context;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;

interface DispatcherInterface
{
    /**
     * @throws McpServerException
     */
    public function handleRequest(Request $request, Context $context): Result;

    public function handleNotification(Notification $notification, Context $context): void;
}
