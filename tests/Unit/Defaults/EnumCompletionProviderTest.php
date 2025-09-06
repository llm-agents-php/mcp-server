<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults;

use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Defaults\EnumCompletionProvider;
use Mcp\Server\Tests\Unit\Defaults\Fixtures as TestEnums;
use PHPUnit\Framework\TestCase;

final class EnumCompletionProviderTest extends TestCase
{
    private SessionInterface $sessionMock;

    public function testConstructorWithValidStringEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        $this->assertSame([
            'first_value',
            'second_value',
            'another_value',
            'similar_value',
        ], $completions);
    }

    public function testConstructorWithValidIntEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestIntEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        // Int-backed enums should fall back to case names
        $this->assertSame(['ONE', 'TWO', 'THREE'], $completions);
    }

    public function testConstructorWithValidUnitEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestUnitEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        $this->assertSame(['ALPHA', 'BETA', 'GAMMA'], $completions);
    }

    public function testConstructorWithSingleCaseEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestSingleCaseEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        $this->assertSame(['only_value'], $completions);
    }

    public function testConstructorWithLargeEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestLargeEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        $expectedValues = [
            'value_001',
            'value_002',
            'value_003',
            'value_004',
            'value_005',
            'prefix_a',
            'prefix_b',
            'prefix_c',
            'different_001',
            'different_002',
        ];

        $this->assertSame($expectedValues, $completions);
    }

    public function testConstructorWithInvalidClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass is not an enum');

        new EnumCompletionProvider(\stdClass::class);
    }

    public function testConstructorWithNonExistentClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class NonExistentEnum is not an enum');

        new EnumCompletionProvider('NonExistentEnum');
    }

    public function testGetCompletionsWithEmptyCurrentValue(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('', $this->sessionMock);

        $this->assertSame([
            'first_value',
            'second_value',
            'another_value',
            'similar_value',
        ], $completions);
    }

    public function testGetCompletionsWithMatchingPrefix(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('s', $this->sessionMock);

        $this->assertSame(['second_value', 'similar_value'], $completions);
    }

    public function testGetCompletionsWithSpecificPrefix(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('first', $this->sessionMock);

        $this->assertSame(['first_value'], $completions);
    }

    public function testGetCompletionsWithNoMatches(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('xyz', $this->sessionMock);

        $this->assertSame([], $completions);
    }

    public function testGetCompletionsWithFullMatch(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('first_value', $this->sessionMock);

        $this->assertSame(['first_value'], $completions);
    }

    public function testGetCompletionsWithPartialMatch(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('anot', $this->sessionMock);

        $this->assertSame(['another_value'], $completions);
    }

    public function testGetCompletionsIsCaseSensitive(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('First', $this->sessionMock);

        $this->assertSame([], $completions);
    }

    public function testGetCompletionsWithUnitEnumFiltering(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestUnitEnum::class);

        $allCompletions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame(['ALPHA', 'BETA', 'GAMMA'], $allCompletions);

        $filteredCompletions = $provider->getCompletions('A', $this->sessionMock);
        $this->assertSame(['ALPHA'], $filteredCompletions);

        $filteredCompletions = $provider->getCompletions('G', $this->sessionMock);
        $this->assertSame(['GAMMA'], $filteredCompletions);
    }

    public function testGetCompletionsWithLargeEnumFiltering(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestLargeEnum::class);

        $valueCompletions = $provider->getCompletions('value', $this->sessionMock);
        $this->assertSame([
            'value_001',
            'value_002',
            'value_003',
            'value_004',
            'value_005',
        ], $valueCompletions);

        $prefixCompletions = $provider->getCompletions('prefix', $this->sessionMock);
        $this->assertSame([
            'prefix_a',
            'prefix_b',
            'prefix_c',
        ], $prefixCompletions);

        $differentCompletions = $provider->getCompletions('different', $this->sessionMock);
        $this->assertSame([
            'different_001',
            'different_002',
        ], $differentCompletions);
    }

    public function testGetCompletionsWithSingleCaseEnum(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestSingleCaseEnum::class);

        $allCompletions = $provider->getCompletions('', $this->sessionMock);
        $this->assertSame(['only_value'], $allCompletions);

        $matchingCompletions = $provider->getCompletions('only', $this->sessionMock);
        $this->assertSame(['only_value'], $matchingCompletions);

        $noMatchCompletions = $provider->getCompletions('other', $this->sessionMock);
        $this->assertSame([], $noMatchCompletions);
    }

    public function testSessionIsNotUsedInCompletion(): void
    {
        // Verify that session is not interacted with during completion
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->never())->method($this->anything());

        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $provider->getCompletions('first', $sessionMock);
    }

    public function testCompletionResultsAreReIndexed(): void
    {
        $provider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $completions = $provider->getCompletions('s', $this->sessionMock);

        // Results should be re-indexed (array_values used)
        $this->assertSame(['second_value', 'similar_value'], $completions);
        $this->assertSame([0, 1], \array_keys($completions));
    }

    public function testEnumCasesAreProcessedCorrectly(): void
    {
        // Test that different enum types are handled properly
        $stringProvider = new EnumCompletionProvider(TestEnums\TestStringEnum::class);
        $intProvider = new EnumCompletionProvider(TestEnums\TestIntEnum::class);
        $unitProvider = new EnumCompletionProvider(TestEnums\TestUnitEnum::class);

        // String enum should use values
        $stringCompletions = $stringProvider->getCompletions('', $this->sessionMock);
        $this->assertContains('first_value', $stringCompletions);
        $this->assertNotContains('FIRST', $stringCompletions);

        // Int enum should use names (since int values are not strings)
        $intCompletions = $intProvider->getCompletions('', $this->sessionMock);
        $this->assertContains('ONE', $intCompletions);
        $this->assertNotContains('1', $intCompletions);

        // Unit enum should use names
        $unitCompletions = $unitProvider->getCompletions('', $this->sessionMock);
        $this->assertContains('ALPHA', $unitCompletions);
    }

    protected function setUp(): void
    {
        $this->sessionMock = $this->createMock(SessionInterface::class);
    }
}
