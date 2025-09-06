<?php

declare(strict_types=1);

namespace Mcp\Server\Dispatcher;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class Paginator
{
    public function __construct(
        private int $paginationLimit = 50,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Complete pagination logic: decode cursor, slice items, and encode next cursor
     *
     * @param array $allItems All items to paginate
     * @param ?string $cursor Current cursor position
     * @return array{items: array, nextCursor: ?string}
     */
    public function paginate(array $allItems, ?string $cursor): array
    {
        $offset = $this->decodeCursor($cursor);
        $pagedItems = \array_slice($allItems, $offset, $this->paginationLimit);
        $nextCursor = $this->encodeNextCursor($offset, \count($pagedItems), \count($allItems));

        return [
            'items' => \array_values($pagedItems),
            'nextCursor' => $nextCursor,
        ];
    }

    /**
     * Decode the base64 cursor to offset with error handling
     */
    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }

        $decoded = \base64_decode($cursor, true);
        if ($decoded === false) {
            $this->logger->warning('Received invalid pagination cursor (not base64)', ['cursor' => $cursor]);
            return 0;
        }

        if (\preg_match('/^offset=(\d+)$/', $decoded, $matches)) {
            return (int) $matches[1];
        }

        $this->logger->warning('Received invalid pagination cursor format', ['cursor' => $decoded]);
        return 0;
    }

    /**
     * Create the next page cursor based on the pagination state
     */
    private function encodeNextCursor(int $currentOffset, int $returnedCount, int $totalCount): ?string
    {
        $nextOffset = $currentOffset + $returnedCount;
        if ($returnedCount > 0 && $nextOffset < $totalCount) {
            return \base64_encode("offset={$nextOffset}");
        }

        return null;
    }
}
