<?php

declare(strict_types=1);

namespace PhpMcp\Server\Routes;

use JsonException;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CallToolRequest;
use PhpMcp\Schema\Request\ListToolsRequest;
use PhpMcp\Schema\Result\CallToolResult;
use PhpMcp\Schema\Result\ListToolsResult;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Server\Configuration;
use PhpMcp\Server\Context;
use PhpMcp\Server\Contracts\RouteInterface;
use PhpMcp\Server\Contracts\ToolExecutorInterface;
use PhpMcp\Server\Exception\McpServerException;
use PhpMcp\Server\Exception\ValidationException;
use PhpMcp\Server\Registry;
use PhpMcp\Server\RequestMethod;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ToolRoute implements RouteInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private Registry $registry,
        private ToolExecutorInterface $toolExecutor,
        private Configuration $configuration,
    ) {
        $this->logger = $this->configuration->logger;
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
        $limit = $this->configuration->paginationLimit;
        $offset = $this->decodeCursor($request->cursor);
        $allItems = $this->registry->getTools();
        $pagedItems = array_slice($allItems, $offset, $limit);
        $nextCursor = $this->encodeNextCursor($offset, count($pagedItems), count($allItems), $limit);

        return new ListToolsResult(array_values($pagedItems), $nextCursor);
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
        } catch (JsonException $e) {
            $this->logger->warning('Failed to JSON encode tool result.', ['tool' => $toolName, 'exception' => $e]);
            $errorMessage = "Failed to serialize tool result: {$e->getMessage()}";

            return new CallToolResult([new TextContent($errorMessage)], true);
        } catch (Throwable $toolError) {
            $this->logger->error('Tool execution failed.', ['tool' => $toolName, 'exception' => $toolError]);
            $errorMessage = "Tool execution failed: {$toolError->getMessage()}";

            return new CallToolResult([new TextContent($errorMessage)], true);
        }
    }

    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }

        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            $this->logger->warning('Received invalid pagination cursor (not base64)', ['cursor' => $cursor]);

            return 0;
        }

        if (preg_match('/^offset=(\d+)$/', $decoded, $matches)) {
            return (int) $matches[1];
        }

        $this->logger->warning('Received invalid pagination cursor format', ['cursor' => $decoded]);

        return 0;
    }

    private function encodeNextCursor(int $currentOffset, int $returnedCount, int $totalCount, int $limit): ?string
    {
        $nextOffset = $currentOffset + $returnedCount;
        if ($returnedCount > 0 && $nextOffset < $totalCount) {
            return base64_encode("offset={$nextOffset}");
        }

        return null;
    }
}
