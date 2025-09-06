<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher\Routes;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Defaults\ToolExecutor;
use Mcp\Server\Dispatcher\Paginator;
use Mcp\Server\Dispatcher\RequestMethod;
use Mcp\Server\Exception\McpServerException;
use Mcp\Server\Exception\ValidationException;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CallToolRequest;
use PhpMcp\Schema\Request\ListToolsRequest;
use PhpMcp\Schema\Result\CallToolResult;
use PhpMcp\Schema\Result\ListToolsResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolRoute implements RouteInterface
{
    private ToolExecutorInterface $toolExecutor;

    public function __construct(
        private ReferenceProviderInterface $registry,
        ?ToolExecutorInterface $toolExecutor = null,
        private LoggerInterface $logger = new NullLogger(),
        private Paginator $paginationHelper = new Paginator(),
    ) {
        $this->toolExecutor = $toolExecutor ?: new ToolExecutor($this->registry, $this->logger);
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
        );

        return new ListToolsResult($pagination['items'], $pagination['nextCursor']);
    }

    /**
     * @throws McpServerException
     */
    private function handleToolCall(CallToolRequest $request, Context $context): CallToolResult
    {
        $toolName = $request->name;
        $arguments = $request->arguments;

        try {
            $result = $this->toolExecutor->call($toolName, $arguments, $context);

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
