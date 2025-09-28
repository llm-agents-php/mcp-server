<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher\Routes;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\Paginator;
use Mcp\Server\Dispatcher\RequestMethod;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\GetPromptRequest;
use PhpMcp\Schema\Request\ListPromptsRequest;
use PhpMcp\Schema\Result\GetPromptResult;
use PhpMcp\Schema\Result\ListPromptsResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class PromptRoute implements RouteInterface
{
    public function __construct(
        private ReferenceProviderInterface $registry,
        private LoggerInterface $logger = new NullLogger(),
        private Paginator $paginationHelper = new Paginator(),
    ) {}

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
        $allItems = $this->registry->getPrompts();
        $pagination = $this->paginationHelper->paginate(
            $allItems,
            $request->cursor,
        );

        return new ListPromptsResult($pagination['items'], $pagination['nextCursor']);
    }

    /**
     * @throws McpServerException
     */
    private function handlePromptGet(GetPromptRequest $request, Context $context): GetPromptResult
    {
        $promptName = $request->name;
        $arguments = $request->arguments;

        $registeredPrompt = $this->registry->getPrompt($promptName);
        if (!$registeredPrompt) {
            throw McpServerException::invalidParams("Prompt '{$promptName}' not found.");
        }

        $arguments = (array)$arguments;

        foreach ($registeredPrompt->schema->arguments as $argDef) {
            if ($argDef->required && !\array_key_exists($argDef->name, $arguments)) {
                throw McpServerException::invalidParams(
                    "Missing required argument '{$argDef->name}' for prompt '{$promptName}'.",
                );
            }
        }

        $result = $registeredPrompt->get($arguments, $context);

        return new GetPromptResult($result, $registeredPrompt->schema->description);
    }
}
