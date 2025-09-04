<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Attributes;

use Mcp\Server\Attributes\McpTool;

it('instantiates with correct properties', static function (): void {
    // Arrange
    $name = 'test-tool-name';
    $description = 'This is a test description.';

    // Act
    $attribute = new McpTool(name: $name, description: $description);

    // Assert
    expect($attribute->name)->toBe($name);
    expect($attribute->description)->toBe($description);
});

it('instantiates with null values for name and description', static function (): void {
    // Arrange & Act
    $attribute = new McpTool(name: null, description: null);

    // Assert
    expect($attribute->name)->toBeNull();
    expect($attribute->description)->toBeNull();
});

it('instantiates with missing optional arguments', static function (): void {
    // Arrange & Act
    $attribute = new McpTool(); // Use default constructor values

    // Assert
    expect($attribute->name)->toBeNull();
    expect($attribute->description)->toBeNull();
});
