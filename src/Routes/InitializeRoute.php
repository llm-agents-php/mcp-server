<?php

declare(strict_types=1);

namespace Mcp\Server\Routes;

use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Notification\InitializedNotification;
use PhpMcp\Schema\Request\InitializeRequest;
use PhpMcp\Schema\Request\PingRequest;
use PhpMcp\Schema\Result\EmptyResult;
use PhpMcp\Schema\Result\InitializeResult;
use Mcp\Server\Configuration;
use Mcp\Server\Context;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Protocol;
use Mcp\Server\RequestMethod;

final readonly class InitializeRoute implements RouteInterface
{
    public function __construct(
        private Configuration $configuration,
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::Initialize->value,
            RequestMethod::Ping->value,
            RequestMethod::NotificationsInitialized->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::Initialize->value => $this->handleInitialize(
                InitializeRequest::fromRequest($request),
                $context->session,
            ),
            RequestMethod::Ping->value => $this->handlePing(PingRequest::fromRequest($request)),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        match ($notification->method) {
            RequestMethod::NotificationsInitialized->value => $this->handleNotificationInitialized(
                InitializedNotification::fromNotification($notification),
                $context->session,
            ),
        };
    }

    private function handleInitialize(InitializeRequest $request, SessionInterface $session): InitializeResult
    {
        if (\in_array($request->protocolVersion, Protocol::SUPPORTED_PROTOCOL_VERSIONS)) {
            $protocolVersion = $request->protocolVersion;
        } else {
            $protocolVersion = Protocol::LATEST_PROTOCOL_VERSION;
        }

        $session->set('client_info', $request->clientInfo->toArray());
        $session->set('protocol_version', $protocolVersion);

        $serverInfo = $this->configuration->serverInfo;
        $capabilities = $this->configuration->capabilities;
        $instructions = $this->configuration->instructions;

        return new InitializeResult($protocolVersion, $capabilities, $serverInfo, $instructions);
    }

    private function handlePing(PingRequest $request): EmptyResult
    {
        return new EmptyResult();
    }

    private function handleNotificationInitialized(
        InitializedNotification $notification,
        SessionInterface $session,
    ): void {
        $session->set('initialized', true);
    }
}
