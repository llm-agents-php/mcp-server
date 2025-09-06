<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Exception\ToolNotFoundException;
use PhpMcp\Schema\Content\Content;
use PhpMcp\Schema\Content\TextContent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private ReferenceProviderInterface $registry,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function call(string $toolName, array $arguments, Context $context): array
    {
        $tool = $this->registry->getTool($toolName);

        if (!$tool) {
            throw new ToolNotFoundException("Tool '{$toolName}' not found.");
        }

        $this->logger->debug('Calling tool', [
            'name' => $tool->schema->name,
        ]);

        $result = $tool->handler->handle($arguments, $context);

        return $this->formatResult($result);
    }

    /**
     * Formats the result of a tool execution into an array of MCP Content items.
     *
     * - If the result is already a Content object, it's wrapped in an array.
     * - If the result is an array:
     *   - If all elements are Content objects, the array is returned as is.
     *   - If it's a mixed array (Content and non-Content items), non-Content items are
     *     individually formatted (scalars to TextContent, others to JSON TextContent).
     *   - If it's an array with no Content items, the entire array is JSON-encoded into a single TextContent.
     * - Scalars (string, int, float, bool) are wrapped in TextContent.
     * - null is represented as TextContent('(null)').
     * - Other objects are JSON-encoded and wrapped in TextContent.
     *
     * @param mixed $toolExecutionResult The raw value returned by the tool's PHP method.
     * @return Content[] The content items for CallToolResult.
     * @throws \JsonException
     */
    protected function formatResult(mixed $toolExecutionResult): array
    {
        if ($toolExecutionResult instanceof Content) {
            return [$toolExecutionResult];
        }

        if (\is_array($toolExecutionResult)) {
            if (empty($toolExecutionResult)) {
                return [TextContent::make('[]')];
            }

            $allAreContent = true;
            $hasContent = false;

            foreach ($toolExecutionResult as $item) {
                if ($item instanceof Content) {
                    $hasContent = true;
                } else {
                    $allAreContent = false;
                }
            }

            if ($allAreContent && $hasContent) {
                return $toolExecutionResult;
            }

            if ($hasContent) {
                $result = [];
                foreach ($toolExecutionResult as $item) {
                    if ($item instanceof Content) {
                        $result[] = $item;
                    } else {
                        $result = \array_merge($result, $this->formatResult($item));
                    }
                }
                return $result;
            }
        }

        if ($toolExecutionResult === null) {
            return [TextContent::make('(null)')];
        }

        if (\is_bool($toolExecutionResult)) {
            return [TextContent::make($toolExecutionResult ? 'true' : 'false')];
        }

        if (\is_scalar($toolExecutionResult)) {
            return [TextContent::make($toolExecutionResult)];
        }

        $jsonResult = \json_encode(
            $toolExecutionResult,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return [TextContent::make($jsonResult)];
    }
}
