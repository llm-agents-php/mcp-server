<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher\Routes;

use Mcp\Server\Context;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\RequestMethod;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\SetLogLevelRequest;
use PhpMcp\Schema\Result\EmptyResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class LoggingRoute implements RouteInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::LoggingSetLevel->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::LoggingSetLevel->value => $this->handleLoggingSetLevel(
                SetLogLevelRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handleLoggingSetLevel(SetLogLevelRequest $request, Context $context): EmptyResult
    {
        $level = $request->level;

        $context->session->set('log_level', $level->value);

        $this->logger->info("Log level set to '{$level->value}'.", ['sessionId' => $context->session->getId()]);

        return new EmptyResult();
    }
}
