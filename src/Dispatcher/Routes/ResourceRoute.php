<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher\Routes;

use Mcp\Server\Context;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\Paginator;
use Mcp\Server\Dispatcher\RequestMethod;
use Mcp\Server\Exception\McpServerException;
use Mcp\Server\Session\SubscriptionManager;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\ListResourcesRequest;
use PhpMcp\Schema\Request\ListResourceTemplatesRequest;
use PhpMcp\Schema\Request\ReadResourceRequest;
use PhpMcp\Schema\Request\ResourceSubscribeRequest;
use PhpMcp\Schema\Request\ResourceUnsubscribeRequest;
use PhpMcp\Schema\Result\EmptyResult;
use PhpMcp\Schema\Result\ListResourcesResult;
use PhpMcp\Schema\Result\ListResourceTemplatesResult;
use PhpMcp\Schema\Result\ReadResourceResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ResourceRoute implements RouteInterface
{
    public function __construct(
        private ReferenceProviderInterface $registry,
        private SubscriptionManager $subscriptionManager,
        private LoggerInterface $logger = new NullLogger(),
        private Paginator $paginationHelper = new Paginator(),
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::ResourcesList->value,
            RequestMethod::ResourcesTemplatesList->value,
            RequestMethod::ResourcesRead->value,
            RequestMethod::ResourcesSubscribe->value,
            RequestMethod::ResourcesUnsubscribe->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::ResourcesList->value => $this->handleResourcesList(
                ListResourcesRequest::fromRequest($request),
            ),
            RequestMethod::ResourcesTemplatesList->value => $this->handleResourceTemplateList(
                ListResourceTemplatesRequest::fromRequest($request),
            ),
            RequestMethod::ResourcesRead->value => $this->handleResourceRead(
                ReadResourceRequest::fromRequest($request),
                $context,
            ),
            RequestMethod::ResourcesSubscribe->value => $this->handleResourceSubscribe(
                ResourceSubscribeRequest::fromRequest($request),
                $context,
            ),
            RequestMethod::ResourcesUnsubscribe->value => $this->handleResourceUnsubscribe(
                ResourceUnsubscribeRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handleResourcesList(ListResourcesRequest $request): ListResourcesResult
    {
        $allItems = $this->registry->getResources();
        $pagination = $this->paginationHelper->paginate(
            $allItems,
            $request->cursor,
        );

        return new ListResourcesResult($pagination['items'], $pagination['nextCursor']);
    }

    private function handleResourceTemplateList(ListResourceTemplatesRequest $request): ListResourceTemplatesResult
    {
        $allItems = $this->registry->getResourceTemplates();
        $pagination = $this->paginationHelper->paginate(
            $allItems,
            $request->cursor,
        );

        return new ListResourceTemplatesResult($pagination['items'], $pagination['nextCursor']);
    }

    /**
     * @throws McpServerException
     */
    private function handleResourceRead(ReadResourceRequest $request, Context $context): ReadResourceResult
    {
        $uri = $request->uri;

        $registeredResource = $this->registry->getResource($uri);

        if (!$registeredResource) {
            throw McpServerException::invalidParams("Resource URI '{$uri}' not found.");
        }

        $result = $registeredResource->read($uri, $context);

        return new ReadResourceResult($result);
    }

    private function handleResourceSubscribe(ResourceSubscribeRequest $request, Context $context): EmptyResult
    {
        $this->subscriptionManager->subscribe($context->session->getId(), $request->uri);
        return new EmptyResult();
    }

    private function handleResourceUnsubscribe(ResourceUnsubscribeRequest $request, Context $context): EmptyResult
    {
        $this->subscriptionManager->unsubscribe($context->session->getId(), $request->uri);
        return new EmptyResult();
    }
}
