<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Dispatcher;

use Mcp\Server\Dispatcher\Paginator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class PaginatorTest extends TestCase
{
    private Paginator $paginator;
    private LoggerInterface&MockObject $logger;

    public function testPaginateWithNullCursor(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e', 'f'];

        $result = $this->paginator->paginate($items, null);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
        $this->assertNotNull($result['nextCursor']);
        $this->assertSame('offset=3', \base64_decode($result['nextCursor']));
    }

    public function testPaginateWithValidCursor(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e', 'f'];
        $cursor = \base64_encode('offset=3');

        $result = $this->paginator->paginate($items, $cursor);

        $this->assertSame(['d', 'e', 'f'], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateEmptyItems(): void
    {
        $items = [];

        $result = $this->paginator->paginate($items, null);

        $this->assertSame([], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateSinglePage(): void
    {
        $items = ['a', 'b'];

        $result = $this->paginator->paginate($items, null);

        $this->assertSame(['a', 'b'], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateExactPageBoundary(): void
    {
        $items = ['a', 'b', 'c'];

        $result = $this->paginator->paginate($items, null);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateWithOffsetBeyondItems(): void
    {
        $items = ['a', 'b', 'c'];
        $cursor = \base64_encode('offset=10');

        $result = $this->paginator->paginate($items, $cursor);

        $this->assertSame([], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateWithPartialLastPage(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e'];
        $cursor = \base64_encode('offset=3');

        $result = $this->paginator->paginate($items, $cursor);

        $this->assertSame(['d', 'e'], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateWithMultiplePages(): void
    {
        $items = \range('a', 'j'); // 10 items

        // First page
        $result1 = $this->paginator->paginate($items, null);
        $this->assertSame(['a', 'b', 'c'], $result1['items']);
        $this->assertNotNull($result1['nextCursor']);

        // Second page
        $result2 = $this->paginator->paginate($items, $result1['nextCursor']);
        $this->assertSame(['d', 'e', 'f'], $result2['items']);
        $this->assertNotNull($result2['nextCursor']);

        // Third page
        $result3 = $this->paginator->paginate($items, $result2['nextCursor']);
        $this->assertSame(['g', 'h', 'i'], $result3['items']);
        $this->assertNotNull($result3['nextCursor']);

        // Last page
        $result4 = $this->paginator->paginate($items, $result3['nextCursor']);
        $this->assertSame(['j'], $result4['items']);
        $this->assertNull($result4['nextCursor']);
    }

    public function testInvalidBase64Cursor(): void
    {
        $items = ['a', 'b', 'c', 'd'];
        $invalidCursor = 'invalid-base64!@#';

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Received invalid pagination cursor (not base64)',
                ['cursor' => $invalidCursor],
            );

        $result = $this->paginator->paginate($items, $invalidCursor);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
    }

    public function testInvalidCursorFormat(): void
    {
        $items = ['a', 'b', 'c', 'd'];
        $invalidFormatCursor = \base64_encode('invalid-format');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Received invalid pagination cursor format',
                ['cursor' => 'invalid-format'],
            );

        $result = $this->paginator->paginate($items, $invalidFormatCursor);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
    }

    public function testCursorWithNonNumericOffset(): void
    {
        $items = ['a', 'b', 'c', 'd'];
        $invalidOffsetCursor = \base64_encode('offset=abc');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Received invalid pagination cursor format',
                ['cursor' => 'offset=abc'],
            );

        $result = $this->paginator->paginate($items, $invalidOffsetCursor);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
    }

    public function testCustomPaginationLimit(): void
    {
        $customPaginator = new Paginator(paginationLimit: 5, logger: $this->logger);
        $items = \range(1, 12);

        $result = $customPaginator->paginate($items, null);

        $this->assertSame([1, 2, 3, 4, 5], $result['items']);
        $this->assertNotNull($result['nextCursor']);
        $this->assertSame('offset=5', \base64_decode($result['nextCursor']));
    }

    public function testDefaultPaginationLimit(): void
    {
        $defaultPaginator = new Paginator();
        $items = \range(1, 100);

        $result = $defaultPaginator->paginate($items, null);

        $this->assertCount(50, $result['items']); // Default limit is 50
        $this->assertSame(\range(1, 50), $result['items']);
        $this->assertNotNull($result['nextCursor']);
    }

    public function testArrayValuesReindexing(): void
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $result = $this->paginator->paginate($items, null);

        // array_values should reindex the array
        $this->assertSame(['value1', 'value2', 'value3'], $result['items']);
        $this->assertSame([0, 1, 2], \array_keys($result['items']));
    }

    public function testZeroOffsetCursor(): void
    {
        $items = ['a', 'b', 'c', 'd'];
        $zeroOffsetCursor = \base64_encode('offset=0');

        $result = $this->paginator->paginate($items, $zeroOffsetCursor);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
        $this->assertNotNull($result['nextCursor']);
    }

    public function testCursorEncodingDecoding(): void
    {
        $offset = 42;
        $expectedCursorContent = "offset={$offset}";
        $cursor = \base64_encode($expectedCursorContent);

        // Verify we can decode what we encode
        $decoded = \base64_decode($cursor, true);
        $this->assertSame($expectedCursorContent, $decoded);

        // Test with large offset
        $largeOffset = 999999;
        $largeCursorContent = "offset={$largeOffset}";
        $largeCursor = \base64_encode($largeCursorContent);
        $decodedLarge = \base64_decode($largeCursor, true);
        $this->assertSame($largeCursorContent, $decodedLarge);
    }

    public function testEdgeCaseWithSingleItem(): void
    {
        $items = ['only-item'];

        $result = $this->paginator->paginate($items, null);

        $this->assertSame(['only-item'], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateWithAssociativeArrayPreservesValues(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
            ['id' => 4, 'name' => 'Diana'],
        ];

        $result = $this->paginator->paginate($items, null);

        $this->assertCount(3, $result['items']);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result['items'][0]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $result['items'][1]);
        $this->assertSame(['id' => 3, 'name' => 'Charlie'], $result['items'][2]);
        $this->assertNotNull($result['nextCursor']);
    }

    public function testPaginateWithNullLogger(): void
    {
        $paginatorWithNullLogger = new Paginator(paginationLimit: 2, logger: new NullLogger());
        $items = ['a', 'b', 'c', 'd'];
        $invalidCursor = 'invalid-base64!@#';

        // Should not throw any exceptions even with invalid cursor
        $result = $paginatorWithNullLogger->paginate($items, $invalidCursor);

        $this->assertSame(['a', 'b'], $result['items']);
        $this->assertNotNull($result['nextCursor']);
    }

    public function testPaginateWithLargeOffset(): void
    {
        $items = ['a', 'b', 'c'];
        $cursor = \base64_encode('offset=1000');

        $result = $this->paginator->paginate($items, $cursor);

        $this->assertSame([], $result['items']);
        $this->assertNull($result['nextCursor']);
    }

    public function testPaginateWithNegativeOffsetInCursor(): void
    {
        $items = ['a', 'b', 'c', 'd'];
        $negativeOffsetCursor = \base64_encode('offset=-5');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Received invalid pagination cursor format',
                ['cursor' => 'offset=-5'],
            );

        $result = $this->paginator->paginate($items, $negativeOffsetCursor);

        $this->assertSame(['a', 'b', 'c'], $result['items']);
    }

    public function testPaginateReturnsConsistentStructure(): void
    {
        $items = ['test'];

        $result = $this->paginator->paginate($items, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('nextCursor', $result);
        $this->assertIsArray($result['items']);
        $this->assertTrue(\is_string($result['nextCursor']) || \is_null($result['nextCursor']));
    }

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paginator = new Paginator(
            paginationLimit: 3,
            logger: $this->logger,
        );
    }
}
