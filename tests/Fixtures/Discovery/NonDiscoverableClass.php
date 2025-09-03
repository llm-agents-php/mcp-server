<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Fixtures\Discovery;

class NonDiscoverableClass
{
    public function someMethod(): string
    {
        return "Just a regular method.";
    }
}

interface MyDiscoverableInterface {}

trait MyDiscoverableTrait
{
    public function traitMethod(): void {}
}

enum MyDiscoverableEnum
{
    case Alpha;
}
