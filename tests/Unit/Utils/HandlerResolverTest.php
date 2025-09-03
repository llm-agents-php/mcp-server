<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Utils;

use Mcp\Server\Utils\HandlerResolver;

class ValidHandlerClass
{
    public function __construct() {}

    public static function staticMethod(): void {}

    public function publicMethod(): void {}

    public function __destruct() {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}

class ValidInvokableClass
{
    public function __invoke(): void {}
}

class NonInvokableClass {}

abstract class AbstractHandlerClass
{
    abstract public function abstractMethod();
}

// Test closure support
it('resolves closures to ReflectionFunction', static function (): void {
    $closure = (static fn(string $input): string => "processed: $input");

    $resolved = HandlerResolver::resolve($closure);

    expect($resolved)->toBeInstanceOf(\ReflectionFunction::class);
    expect($resolved->getNumberOfParameters())->toBe(1);
    expect($resolved->getReturnType()->getName())->toBe('string');
});

it('resolves valid array handler', static function (): void {
    $handler = [ValidHandlerClass::class, 'publicMethod'];
    $resolved = HandlerResolver::resolve($handler);

    expect($resolved)->toBeInstanceOf(\ReflectionMethod::class);
    expect($resolved->getName())->toBe('publicMethod');
    expect($resolved->getDeclaringClass()->getName())->toBe(ValidHandlerClass::class);
});

it('resolves valid invokable class string handler', static function (): void {
    $handler = ValidInvokableClass::class;
    $resolved = HandlerResolver::resolve($handler);

    expect($resolved)->toBeInstanceOf(\ReflectionMethod::class);
    expect($resolved->getName())->toBe('__invoke');
    expect($resolved->getDeclaringClass()->getName())->toBe(ValidInvokableClass::class);
});

it('resolves static methods for manual registration', static function (): void {
    $handler = ValidHandlerClass::staticMethod(...);
    $resolved = HandlerResolver::resolve($handler);

    expect($resolved)->toBeInstanceOf(\ReflectionMethod::class);
    expect($resolved->getName())->toBe('staticMethod');
    expect($resolved->isStatic())->toBeTrue();
});

it('throws for invalid array handler format (count)', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class]);
})->throws(\InvalidArgumentException::class, 'Invalid array handler format. Expected [ClassName::class, \'methodName\'].');

it('throws for invalid array handler format (types)', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, 123]);
})->throws(\InvalidArgumentException::class, 'Invalid array handler format. Expected [ClassName::class, \'methodName\'].');

it('throws for non-existent class in array handler', static function (): void {
    HandlerResolver::resolve(['NonExistentClass', 'method']);
})->throws(\InvalidArgumentException::class, "Handler class 'NonExistentClass' not found");

it('throws for non-existent method in array handler', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, 'nonExistentMethod']);
})->throws(\InvalidArgumentException::class, "Handler method 'nonExistentMethod' not found in class");

it('throws for non-existent class in string handler', static function (): void {
    HandlerResolver::resolve('NonExistentInvokableClass');
})->throws(\InvalidArgumentException::class, 'Invalid handler format. Expected Closure, [ClassName::class, \'methodName\'] or InvokableClassName::class string.');

it('throws for non-invokable class string handler', static function (): void {
    HandlerResolver::resolve(NonInvokableClass::class);
})->throws(\InvalidArgumentException::class, "Invokable handler class '" . NonInvokableClass::class . "' must have a public '__invoke' method.");

it('throws for protected method handler', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, 'protectedMethod']);
})->throws(\InvalidArgumentException::class, 'must be public');

it('throws for private method handler', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, 'privateMethod']);
})->throws(\InvalidArgumentException::class, 'must be public');

it('throws for constructor as handler', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, '__construct']);
})->throws(\InvalidArgumentException::class, 'cannot be a constructor or destructor');

it('throws for destructor as handler', static function (): void {
    HandlerResolver::resolve([ValidHandlerClass::class, '__destruct']);
})->throws(\InvalidArgumentException::class, 'cannot be a constructor or destructor');

it('throws for abstract method handler', static function (): void {
    HandlerResolver::resolve([AbstractHandlerClass::class, 'abstractMethod']);
})->throws(\InvalidArgumentException::class, 'cannot be abstract');

// Test different closure types
it('resolves closures with different signatures', static function (): void {
    $noParams = (static fn() => 'test');
    $withParams = (static fn(int $a, string $b = 'default') => $a . $b);
    $variadic = (static fn(...$args) => $args);

    expect(HandlerResolver::resolve($noParams))->toBeInstanceOf(\ReflectionFunction::class);
    expect(HandlerResolver::resolve($withParams))->toBeInstanceOf(\ReflectionFunction::class);
    expect(HandlerResolver::resolve($variadic))->toBeInstanceOf(\ReflectionFunction::class);

    expect(HandlerResolver::resolve($noParams)->getNumberOfParameters())->toBe(0);
    expect(HandlerResolver::resolve($withParams)->getNumberOfParameters())->toBe(2);
    expect(HandlerResolver::resolve($variadic)->isVariadic())->toBeTrue();
});

// Test that we can distinguish between closures and callable arrays
it('distinguishes between closures and callable arrays', static function (): void {
    $closure = (static fn() => 'closure');
    $array = [ValidHandlerClass::class, 'publicMethod'];
    $string = ValidInvokableClass::class;

    expect(HandlerResolver::resolve($closure))->toBeInstanceOf(\ReflectionFunction::class);
    expect(HandlerResolver::resolve($array))->toBeInstanceOf(\ReflectionMethod::class);
    expect(HandlerResolver::resolve($string))->toBeInstanceOf(\ReflectionMethod::class);
});
