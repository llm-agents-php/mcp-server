<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults;

use Mcp\Server\Context;
use Mcp\Server\Contracts\HandlerInterface;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\SessionInterface;
use Mcp\Server\Defaults\ToolExecutor;
use Mcp\Server\Elements\RegisteredTool;
use Mcp\Server\Exception\ToolNotFoundException;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\Tool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ToolExecutorTest extends TestCase
{
    private ReferenceProviderInterface&MockObject $registry;
    private LoggerInterface&MockObject $logger;
    private ToolExecutor $toolExecutor;
    private Context $context;

    public function testCallSuccessfullyExecutesTool(): void
    {
        $toolName = 'test_tool';
        $arguments = ['param1' => 'value1'];
        $expectedResult = 'test result';

        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($arguments, $this->context)
            ->willReturn($expectedResult);

        $toolSchema = Tool::make(
            name: $toolName,
            inputSchema: ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            description: 'Test tool',
        );
        $registeredTool = new RegisteredTool($toolSchema, $handler);

        $this->registry
            ->expects($this->once())
            ->method('getTool')
            ->with($toolName)
            ->willReturn($registeredTool);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Calling tool', ['name' => $toolName]);

        $result = $this->toolExecutor->call($toolName, $arguments, $this->context);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals($expectedResult, $result[0]->text);
    }

    public function testCallThrowsToolNotFoundExceptionWhenToolDoesNotExist(): void
    {
        $toolName = 'nonexistent_tool';
        $arguments = [];

        $this->registry
            ->expects($this->once())
            ->method('getTool')
            ->with($toolName)
            ->willReturn(null);

        $this->expectException(ToolNotFoundException::class);
        $this->expectExceptionMessage("Tool 'nonexistent_tool' not found.");

        $this->toolExecutor->call($toolName, $arguments, $this->context);
    }

    public function testFormatResultWithContentObject(): void
    {
        $content = TextContent::make('test content');
        $handler = $this->createToolHandlerMock($content);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertSame($content, $result[0]);
    }

    public function testFormatResultWithArrayOfContentObjects(): void
    {
        $content1 = TextContent::make('content 1');
        $content2 = TextContent::make('content 2');
        $contentArray = [$content1, $content2];

        $handler = $this->createToolHandlerMock($contentArray);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(2, $result);
        $this->assertSame($content1, $result[0]);
        $this->assertSame($content2, $result[1]);
    }

    public function testFormatResultWithEmptyArray(): void
    {
        $handler = $this->createToolHandlerMock([]);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('[]', $result[0]->text);
    }

    public function testFormatResultWithMixedArray(): void
    {
        $content = TextContent::make('content');
        $mixedArray = [$content, 'string', 42, true];

        $handler = $this->createToolHandlerMock($mixedArray);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(4, $result);
        $this->assertSame($content, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[1]);
        $this->assertEquals('string', $result[1]->text);
        $this->assertInstanceOf(TextContent::class, $result[2]);
        $this->assertEquals('42', $result[2]->text);
        $this->assertInstanceOf(TextContent::class, $result[3]);
        $this->assertEquals('true', $result[3]->text);
    }

    public function testFormatResultWithArrayOfNonContentItems(): void
    {
        $array = ['key1' => 'value1', 'key2' => 'value2'];
        $handler = $this->createToolHandlerMock($array);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);

        $expectedJson = \json_encode(
            $array,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        $this->assertEquals($expectedJson, $result[0]->text);
    }

    public function testFormatResultWithNull(): void
    {
        $handler = $this->createToolHandlerMock(null);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('(null)', $result[0]->text);
    }

    public function testFormatResultWithBooleanTrue(): void
    {
        $handler = $this->createToolHandlerMock(true);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('true', $result[0]->text);
    }

    public function testFormatResultWithBooleanFalse(): void
    {
        $handler = $this->createToolHandlerMock(false);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('false', $result[0]->text);
    }

    public function testFormatResultWithString(): void
    {
        $testString = 'Hello, World!';
        $handler = $this->createToolHandlerMock($testString);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals($testString, $result[0]->text);
    }

    public function testFormatResultWithInteger(): void
    {
        $testInt = 42;
        $handler = $this->createToolHandlerMock($testInt);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('42', $result[0]->text);
    }

    public function testFormatResultWithFloat(): void
    {
        $testFloat = 3.14;
        $handler = $this->createToolHandlerMock($testFloat);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);
        $this->assertEquals('3.14', $result[0]->text);
    }

    public function testFormatResultWithComplexObject(): void
    {
        $object = (object) ['name' => 'test', 'value' => 123, 'nested' => ['a', 'b']];
        $handler = $this->createToolHandlerMock($object);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]);

        $expectedJson = \json_encode(
            $object,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        $this->assertEquals($expectedJson, $result[0]->text);
    }

    public function testFormatResultWithNestedArraysInMixedContent(): void
    {
        $content = TextContent::make('content');
        $nestedArray = ['nested' => ['deep' => 'value']];
        $mixedArray = [$content, $nestedArray];

        $handler = $this->createToolHandlerMock($mixedArray);
        $result = $this->executeToolWithHandler($handler);

        $this->assertCount(2, $result);
        $this->assertSame($content, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[1]);

        $expectedJson = \json_encode(
            $nestedArray,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        $this->assertEquals($expectedJson, $result[1]->text);
    }

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ReferenceProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->toolExecutor = new ToolExecutor($this->registry, $this->logger);
        $this->context = new Context(session: $this->createMock(SessionInterface::class));
    }

    private function createToolHandlerMock(mixed $returnValue): HandlerInterface&MockObject
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturn($returnValue);

        return $handler;
    }

    private function executeToolWithHandler(HandlerInterface $handler): array
    {
        $toolName = 'test_tool';
        $arguments = [];

        $toolSchema = Tool::make(
            name: $toolName,
            inputSchema: ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            description: 'Test tool',
        );
        $registeredTool = new RegisteredTool($toolSchema, $handler);

        $this->registry
            ->expects($this->once())
            ->method('getTool')
            ->with($toolName)
            ->willReturn($registeredTool);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Calling tool', ['name' => $toolName]);

        return $this->toolExecutor->call($toolName, $arguments, $this->context);
    }
}
