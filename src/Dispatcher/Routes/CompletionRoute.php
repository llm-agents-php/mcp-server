<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher\Routes;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\RequestMethod;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CompletionCompleteRequest;
use PhpMcp\Schema\Result\CompletionCompleteResult;

final readonly class CompletionRoute implements RouteInterface
{
    public function __construct(
        private ReferenceProviderInterface $registry,
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::CompletionComplete->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::CompletionComplete->value => $this->handleCompletionComplete(
                CompletionCompleteRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    /**
     * @throws McpServerException
     */
    private function handleCompletionComplete(
        CompletionCompleteRequest $request,
        Context $context,
    ): CompletionCompleteResult {
        $ref = $request->ref;
        $argumentName = $request->argument['name'];
        $currentValue = $request->argument['value'];

        if ($ref->type === 'ref/prompt') {
            $identifier = $ref->name;
            $registeredPrompt = $this->registry->getPrompt($identifier);
            if (!$registeredPrompt) {
                throw McpServerException::invalidParams("Prompt '{$identifier}' not found.");
            }

            $foundArg = false;
            foreach ($registeredPrompt->schema->arguments as $arg) {
                if ($arg->name === $argumentName) {
                    $foundArg = true;
                    break;
                }
            }
            if (!$foundArg) {
                throw McpServerException::invalidParams(
                    "Argument '{$argumentName}' not found in prompt '{$identifier}'.",
                );
            }

            return $registeredPrompt->complete($argumentName, $currentValue, $context->session);
        } elseif ($ref->type === 'ref/resource') {
            $identifier = $ref->uri;
            $registeredResourceTemplate = $this->registry->getResourceTemplate($identifier);
            if (!$registeredResourceTemplate) {
                throw McpServerException::invalidParams("Resource template '{$identifier}' not found.");
            }

            $foundArg = false;
            foreach ($registeredResourceTemplate->getVariableNames() as $uriVariableName) {
                if ($uriVariableName === $argumentName) {
                    $foundArg = true;
                    break;
                }
            }

            if (!$foundArg) {
                throw McpServerException::invalidParams(
                    "URI variable '{$argumentName}' not found in resource template '{$identifier}'.",
                );
            }

            return $registeredResourceTemplate->complete(
                $argumentName,
                $currentValue,
                $context->session,
            );
        }

        throw McpServerException::invalidParams("Invalid ref type '{$ref->type}' for completion complete request.");
    }
}
