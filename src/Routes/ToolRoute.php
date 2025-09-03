<?php

declare(strict_types=1);

namespace Mcp\Server\Routes;

use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CallToolRequest;
use PhpMcp\Schema\Request\ListToolsRequest;
use PhpMcp\Schema\Result\CallToolResult;
use PhpMcp\Schema\Result\ListToolsResult;
use PhpMcp\Schema\Content\TextContent;
use Mcp\Server\Context;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Defaults\ToolExecutor;
use Mcp\Server\Exception\McpServerException;
use Mcp\Server\Exception\ValidationException;
use Mcp\Server\Helpers\PaginationHelper;
use Mcp\Server\Registry;
use Mcp\Server\RequestMethod;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolRoute implements RouteInterface
{
    private ToolExecutorInterface $toolExecutor;

    public function __construct(
        private Registry $registry,
        ?ToolExecutorInterface $toolExecutor = null,
        private LoggerInterface $logger = new NullLogger(),
        private PaginationHelper $paginationHelper = new PaginationHelper(),
        private int $paginationLimit = 50,
    ) {
        $this->toolExecutor = $toolExecutor ?: new ToolExecutor($this->logger);
    }

    public function getMethods(): array
    {
        return [
            RequestMethod::ToolsList->value,
            RequestMethod::ToolsCall->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::ToolsList->value => $this->handleToolList(ListToolsRequest::fromRequest($request)),
            RequestMethod::ToolsCall->value => $this->handleToolCall(CallToolRequest::fromRequest($request), $context),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handleToolList(ListToolsRequest $request): ListToolsResult
    {
        $allItems = $this->registry->getTools();
        $pagination = $this->paginationHelper->paginate(
            $allItems,
            $request->cursor,
            $this->paginationLimit,
        );

        return new ListToolsResult($pagination['items'], $pagination['nextCursor']);
    }

    private function handleToolCall(CallToolRequest $request, Context $context): CallToolResult
    {
        $toolName = $request->name;
        $arguments = $request->arguments;

        $registeredTool = $this->registry->getTool($toolName);
        if (!$registeredTool) {
            throw McpServerException::methodNotFound("Tool '{$toolName}' not found.");
        }

        try {
            $result = $this->toolExecutor->call($registeredTool, $arguments, $context);

            return new CallToolResult($result, false);
        } catch (ValidationException $e) {
            throw McpServerException::invalidParams(
                $e->buildMessage($toolName),
                data: ['validation_errors' => $e->errors],
            );
        } catch (\Throwable $toolError) {
            $this->logger->error('Tool execution failed.', ['tool' => $toolName, 'exception' => $toolError]);
            $errorMessage = "Tool execution failed: {$toolError->getMessage()}";

            return new CallToolResult([new TextContent($errorMessage)], true);
        }
    }
}
