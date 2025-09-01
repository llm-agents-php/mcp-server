<?php

declare(strict_types=1);

namespace PhpMcp\Server\Routes;

use JsonException;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Notification;
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
use PhpMcp\Server\Configuration;
use PhpMcp\Server\Context;
use PhpMcp\Server\Contracts\RouteInterface;
use PhpMcp\Server\Exception\McpServerException;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Session\SubscriptionManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ResourceRoute implements RouteInterface
{
    private ContainerInterface $container;
    private LoggerInterface $logger;

    public function __construct(
        private Registry $registry,
        private SubscriptionManager $subscriptionManager,
        private Configuration $configuration,
    ) {
        $this->container = $this->configuration->container;
        $this->logger = $this->configuration->logger;
    }

    public function getMethods(): array
    {
        return [
            'resources/list',
            'resources/templates/list',
            'resources/read',
            'resources/subscribe',
            'resources/unsubscribe',
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            'resources/list' => $this->handleResourcesList(ListResourcesRequest::fromRequest($request)),
            'resources/templates/list' => $this->handleResourceTemplateList(
                ListResourceTemplatesRequest::fromRequest($request),
            ),
            'resources/read' => $this->handleResourceRead(ReadResourceRequest::fromRequest($request), $context),
            'resources/subscribe' => $this->handleResourceSubscribe(
                ResourceSubscribeRequest::fromRequest($request),
                $context,
            ),
            'resources/unsubscribe' => $this->handleResourceUnsubscribe(
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
        $limit = $this->configuration->paginationLimit;
        $offset = $this->decodeCursor($request->cursor);
        $allItems = $this->registry->getResources();
        $pagedItems = array_slice($allItems, $offset, $limit);
        $nextCursor = $this->encodeNextCursor($offset, count($pagedItems), count($allItems), $limit);

        return new ListResourcesResult(array_values($pagedItems), $nextCursor);
    }

    private function handleResourceTemplateList(ListResourceTemplatesRequest $request): ListResourceTemplatesResult
    {
        $limit = $this->configuration->paginationLimit;
        $offset = $this->decodeCursor($request->cursor);
        $allItems = $this->registry->getResourceTemplates();
        $pagedItems = array_slice($allItems, $offset, $limit);
        $nextCursor = $this->encodeNextCursor($offset, count($pagedItems), count($allItems), $limit);

        return new ListResourceTemplatesResult(array_values($pagedItems), $nextCursor);
    }

    private function handleResourceRead(ReadResourceRequest $request, Context $context): ReadResourceResult
    {
        $uri = $request->uri;

        $registeredResource = $this->registry->getResource($uri);

        if (!$registeredResource) {
            throw McpServerException::invalidParams("Resource URI '{$uri}' not found.");
        }

        try {
            $result = $registeredResource->read($this->container, $uri, $context);

            return new ReadResourceResult($result);
        } catch (JsonException $e) {
            $this->logger->warning('Failed to JSON encode resource content.', ['exception' => $e, 'uri' => $uri]);
            throw McpServerException::internalError("Failed to serialize resource content for '{$uri}'.", $e);
        } catch (McpServerException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Resource read failed.', ['uri' => $uri, 'exception' => $e]);
            throw McpServerException::resourceReadFailed($uri, $e);
        }
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
