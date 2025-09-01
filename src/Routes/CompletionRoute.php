<?php

declare(strict_types=1);

namespace PhpMcp\Server\Routes;

use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CompletionCompleteRequest;
use PhpMcp\Schema\Result\CompletionCompleteResult;
use PhpMcp\Server\Configuration;
use PhpMcp\Server\Context;
use PhpMcp\Server\Contracts\RouteInterface;
use PhpMcp\Server\Exception\McpServerException;
use PhpMcp\Server\Registry;
use PhpMcp\Server\RequestMethod;
use Psr\Container\ContainerInterface;

final readonly class CompletionRoute implements RouteInterface
{
    private ContainerInterface $container;

    public function __construct(
        private Registry $registry,
        private Configuration $configuration,
    ) {
        $this->container = $this->configuration->container;
    }

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

            return $registeredPrompt->complete($this->container, $argumentName, $currentValue, $context->session);
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
                $this->container,
                $argumentName,
                $currentValue,
                $context->session,
            );
        } else {
            throw McpServerException::invalidParams("Invalid ref type '{$ref->type}' for completion complete request.");
        }
    }
}
