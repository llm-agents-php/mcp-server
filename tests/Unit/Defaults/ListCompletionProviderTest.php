<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults;

use Mcp\Server\Defaults\ListCompletionProvider;
use Mcp\Server\Contracts\SessionInterface;

beforeEach(function (): void {
    $this->session = \Mockery::mock(SessionInterface::class);
});

it('returns all values when current value is empty', function (): void {
    $values = ['apple', 'banana', 'cherry'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('', $this->session);

    expect($result)->toBe($values);
});

it('filters values based on current value prefix', function (): void {
    $values = ['apple', 'apricot', 'banana', 'cherry'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('ap', $this->session);

    expect($result)->toBe(['apple', 'apricot']);
});

it('returns empty array when no values match', function (): void {
    $values = ['apple', 'banana', 'cherry'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('xyz', $this->session);

    expect($result)->toBe([]);
});

it('works with single character prefix', function (): void {
    $values = ['apple', 'banana', 'cherry'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('a', $this->session);

    expect($result)->toBe(['apple']);
});

it('is case sensitive by default', function (): void {
    $values = ['Apple', 'apple', 'APPLE'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('A', $this->session);

    expect($result)->toEqual(['Apple', 'APPLE']);
});

it('handles empty values array', function (): void {
    $provider = new ListCompletionProvider([]);

    $result = $provider->getCompletions('test', $this->session);

    expect($result)->toBe([]);
});

it('preserves array order', function (): void {
    $values = ['zebra', 'apple', 'banana'];
    $provider = new ListCompletionProvider($values);

    $result = $provider->getCompletions('', $this->session);

    expect($result)->toBe(['zebra', 'apple', 'banana']);
});
