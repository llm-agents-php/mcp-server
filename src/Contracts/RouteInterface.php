<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use Mcp\Server\Context;

interface RouteInterface
{
    /**
     * Returns array of methods this route handles
     */
    public function getMethods(): array;

    /**
     * Handle a request for methods this route supports
     */
    public function handleRequest(Request $request, Context $context): Result;

    /**
     * Handle a notification for methods this route supports
     */
    public function handleNotification(Notification $notification, Context $context): void;
}
