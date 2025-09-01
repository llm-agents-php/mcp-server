<?php

declare(strict_types=1);

namespace PhpMcp\Server\Routes;

use JsonException;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\GetPromptRequest;
use PhpMcp\Schema\Request\ListPromptsRequest;
use PhpMcp\Schema\Result\GetPromptResult;
use PhpMcp\Schema\Result\ListPromptsResult;
use PhpMcp\Server\Configuration;
use PhpMcp\Server\Context;
use PhpMcp\Server\Contracts\RouteInterface;
use PhpMcp\Server\Exception\McpServerException;
use PhpMcp\Server\Registry;
use PhpMcp\Server\RequestMethod;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PromptRoute implements RouteInterface
{
    private ContainerInterface $container;
    private LoggerInterface $logger;

    public function __construct(
        private Registry $registry,
        private Configuration $configuration,
    ) {
        $this->container = $this->configuration->container;
        $this->logger = $this->configuration->logger;
    }

    public function getMethods(): array
    {
        return [
            RequestMethod::PromptsList->value,
            RequestMethod::PromptsGet->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::PromptsList->value => $this->handlePromptsList(ListPromptsRequest::fromRequest($request)),
            RequestMethod::PromptsGet->value => $this->handlePromptGet(
                GetPromptRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handlePromptsList(ListPromptsRequest $request): ListPromptsResult
    {
        $limit = $this->configuration->paginationLimit;
        $offset = $this->decodeCursor($request->cursor);
        $allItems = $this->registry->getPrompts();
        $pagedItems = array_slice($allItems, $offset, $limit);
        $nextCursor = $this->encodeNextCursor($offset, count($pagedItems), count($allItems), $limit);

        return new ListPromptsResult(array_values($pagedItems), $nextCursor);
    }

    private function handlePromptGet(GetPromptRequest $request, Context $context): GetPromptResult
    {
        $promptName = $request->name;
        $arguments = $request->arguments;

        $registeredPrompt = $this->registry->getPrompt($promptName);
        if (!$registeredPrompt) {
            throw McpServerException::invalidParams("Prompt '{$promptName}' not found.");
        }

        $arguments = (array) $arguments;

        foreach ($registeredPrompt->schema->arguments as $argDef) {
            if ($argDef->required && !array_key_exists($argDef->name, $arguments)) {
                throw McpServerException::invalidParams(
                    "Missing required argument '{$argDef->name}' for prompt '{$promptName}'.",
                );
            }
        }

        try {
            $result = $registeredPrompt->get($this->container, $arguments, $context);

            return new GetPromptResult($result, $registeredPrompt->schema->description);
        } catch (JsonException $e) {
            $this->logger->warning(
                'Failed to JSON encode prompt messages.',
                ['exception' => $e, 'promptName' => $promptName],
            );
            throw McpServerException::internalError("Failed to serialize prompt messages for '{$promptName}'.", $e);
        } catch (McpServerException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Prompt generation failed.', ['promptName' => $promptName, 'exception' => $e]);
            throw McpServerException::promptGenerationFailed($promptName, $e);
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
