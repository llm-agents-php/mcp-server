<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults;

use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Defaults\ListCompletionProvider;
use Mcp\Server\Tests\Unit\Defaults\Fixtures\CompletionProviderTestData;
use PHPUnit\Framework\TestCase;

final class ListCompletionProviderTest extends TestCase
{
    private SessionInterface $sessionMock;

    public function testConstructorWithValidList(): void
    {
        $values = CompletionProviderTestData::basicStringList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame($values, $completions);
    }

    public function testConstructorWithEmptyList(): void
    {
        $values = CompletionProviderTestData::emptyList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame([], $completions);
    }

    public function testConstructorWithSingleItemList(): void
    {
        $values = CompletionProviderTestData::singleItemList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame(['single_item'], $completions);
    }

    public function testGetCompletionsWithEmptyCurrentValue(): void
    {
        $values = CompletionProviderTestData::fruitList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame($values, $completions);
    }

    public function testGetCompletionsWithMatchingPrefix(): void
    {
        $values = CompletionProviderTestData::fruitList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('a', $this->sessionMock);
        $this->assertSame(['apple', 'apricot', 'avocado'], $completions);
    }

    public function testGetCompletionsWithSpecificPrefix(): void
    {
        $values = CompletionProviderTestData::fruitList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('ap', $this->sessionMock);
        $this->assertSame(['apple', 'apricot'], $completions);
    }

    public function testGetCompletionsWithNoMatches(): void
    {
        $values = CompletionProviderTestData::basicStringList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('xyz', $this->sessionMock);
        $this->assertSame([], $completions);
    }

    public function testGetCompletionsWithFullMatch(): void
    {
        $values = CompletionProviderTestData::basicStringList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('apple', $this->sessionMock);
        $this->assertSame(['apple'], $completions);
    }

    public function testGetCompletionsWithPartialMatch(): void
    {
        $values = CompletionProviderTestData::applicationList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('app', $this->sessionMock);
        $this->assertSame(['application', 'apply', 'appreciate'], $completions);
    }

    public function testGetCompletionsIsCaseSensitive(): void
    {
        $values = CompletionProviderTestData::mixedCaseList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('apple', $this->sessionMock);
        $this->assertSame([], $completions);

        $completions = $provider->getCompletions('Apple', $this->sessionMock);
        $this->assertSame(['Apple'], $completions);
    }

    public function testGetCompletionsWithDuplicateValues(): void
    {
        $values = CompletionProviderTestData::duplicateValuesList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('a', $this->sessionMock);
        $this->assertSame(['apple', 'apple'], $completions);
    }

    public function testGetCompletionsWithNumericStrings(): void
    {
        $values = CompletionProviderTestData::numericStringsList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('12', $this->sessionMock);
        $this->assertSame(['123', '124'], $completions);
    }

    public function testGetCompletionsWithSpecialCharacters(): void
    {
        $values = CompletionProviderTestData::emailList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('test@', $this->sessionMock);
        $this->assertSame(['test@email.com', 'test@domain.org'], $completions);
    }

    public function testGetCompletionsWithEmptyStrings(): void
    {
        $values = CompletionProviderTestData::listWithEmptyStrings();
        $provider = new ListCompletionProvider($values);

        $allCompletions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame($values, $allCompletions);

        $filteredCompletions = $provider->getCompletions('a', $this->sessionMock);
        $this->assertSame(['apple'], $filteredCompletions);
    }

    public function testGetCompletionsWithWhitespaceStrings(): void
    {
        $values = CompletionProviderTestData::listWithWhitespace();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions(' ', $this->sessionMock);
        $this->assertSame([' apple', ' banana '], $completions);

        $completions = $provider->getCompletions('apple', $this->sessionMock);
        $this->assertSame(['apple '], $completions);
    }

    public function testGetCompletionsWithUnicodeStrings(): void
    {
        $values = CompletionProviderTestData::unicodeList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('ré', $this->sessionMock);
        $this->assertSame(['résumé'], $completions);

        $completions = $provider->getCompletions('pi', $this->sessionMock);
        $this->assertSame(['piñata'], $completions);
    }

    public function testGetCompletionsWithFileNames(): void
    {
        $values = CompletionProviderTestData::specialCharactersList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('file.', $this->sessionMock);
        $this->assertSame(['file.txt'], $completions);

        $completions = $provider->getCompletions('file_', $this->sessionMock);
        $this->assertSame(['file_name.php'], $completions);

        $completions = $provider->getCompletions('file-', $this->sessionMock);
        $this->assertSame(['file-with-dashes.js'], $completions);
    }

    public function testGetCompletionsWithPaths(): void
    {
        $values = CompletionProviderTestData::pathList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('/home/', $this->sessionMock);
        $this->assertSame(['/home/user/documents', '/home/user/downloads'], $completions);

        $completions = $provider->getCompletions('/var/', $this->sessionMock);
        $this->assertSame(['/var/log/application', '/var/lib/database'], $completions);
    }

    public function testGetCompletionsWithVersions(): void
    {
        $values = CompletionProviderTestData::versionList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('1.', $this->sessionMock);
        $this->assertSame(['1.0.0', '1.0.1', '1.1.0'], $completions);

        $completions = $provider->getCompletions('2.0.0-', $this->sessionMock);
        $this->assertSame(['2.0.0-alpha', '2.0.0-beta'], $completions);

        $completions = $provider->getCompletions('10', $this->sessionMock);
        $this->assertSame(['10.0.0'], $completions);
    }

    public function testSessionIsNotUsedInCompletion(): void
    {
        // Verify that session is not interacted with during completion
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->never())->method($this->anything());

        $provider = new ListCompletionProvider(CompletionProviderTestData::basicStringList());
        $provider->getCompletions('a', $sessionMock);
    }

    public function testCompletionResultsAreReIndexed(): void
    {
        $values = CompletionProviderTestData::fruitList();
        $provider = new ListCompletionProvider($values);

        $completions = $provider->getCompletions('a', $this->sessionMock);

        // Results should be re-indexed (array_values used)
        $this->assertSame(['apple', 'apricot', 'avocado'], $completions);
        $this->assertSame([0, 1, 2], \array_keys($completions));
    }

    public function testLargeDatasetPerformance(): void
    {
        // Create a large dataset to ensure the filtering works efficiently
        $values = CompletionProviderTestData::largeDataset(10000);
        $provider = new ListCompletionProvider($values);

        $startTime = \microtime(true);
        $completions = $provider->getCompletions('item_999', $this->sessionMock);
        $endTime = \microtime(true);

        $this->assertLessThan(0.1, $endTime - $startTime); // Should complete in under 100ms
        $this->assertCount(11, $completions); // item_999, item_9990-item_9999
        $this->assertContains('item_999', $completions);
        $this->assertContains('item_9999', $completions);
    }

    public function testReadonlyPropertyAccess(): void
    {
        $values = CompletionProviderTestData::basicStringList();
        $provider = new ListCompletionProvider($values);

        // Test that the provider is readonly and values cannot be modified externally
        $reflection = new \ReflectionClass($provider);
        $this->assertTrue($reflection->isReadOnly());

        $valuesProperty = $reflection->getProperty('values');
        $this->assertTrue($valuesProperty->isReadOnly());
    }

    public function testImmutabilityOfProvidedArray(): void
    {
        $originalValues = CompletionProviderTestData::basicStringList();
        $values = $originalValues;

        $provider = new ListCompletionProvider($values);

        // Modify the original array after creating the provider
        $values[] = 'date';

        // Provider should still return the original values
        $completions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame($originalValues, $completions);
    }

    public function testEdgeCasesWithSingleItem(): void
    {
        $values = CompletionProviderTestData::singleItemList();
        $provider = new ListCompletionProvider($values);

        $allCompletions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame(['single_item'], $allCompletions);

        $matchingCompletions = $provider->getCompletions('single', $this->sessionMock);
        $this->assertSame(['single_item'], $matchingCompletions);

        $noMatchCompletions = $provider->getCompletions('other', $this->sessionMock);
        $this->assertSame([], $noMatchCompletions);
    }

    protected function setUp(): void
    {
        $this->sessionMock = $this->createMock(SessionInterface::class);
    }
}
