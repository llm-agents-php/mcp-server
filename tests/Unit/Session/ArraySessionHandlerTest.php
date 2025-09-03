<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Session;

use Mcp\Server\Session\ArraySessionHandler;
use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Defaults\SystemClock;
use Mcp\Server\Tests\Mocks\Clock\FixedClock;

const SESSION_ID_ARRAY_1 = 'array-session-id-1';
const SESSION_ID_ARRAY_2 = 'array-session-id-2';
const SESSION_ID_ARRAY_3 = 'array-session-id-3';
const SESSION_DATA_1 = '{"user_id":101,"cart":{"items":[{"id":"prod_A","qty":2},{"id":"prod_B","qty":1}],"total":150.75},"theme":"dark"}';
const SESSION_DATA_2 = '{"user_id":102,"preferences":{"notifications":true,"language":"en"},"last_login":"2024-07-15T10:00:00Z"}';
const SESSION_DATA_3 = '{"guest":true,"viewed_products":["prod_C","prod_D"]}';
const DEFAULT_TTL_ARRAY = 3600;

beforeEach(function (): void {
    $this->fixedClock = new FixedClock();
    $this->handler = new ArraySessionHandler(DEFAULT_TTL_ARRAY, $this->fixedClock);
});

it('implements SessionHandlerInterface', function (): void {
    expect($this->handler)->toBeInstanceOf(SessionHandlerInterface::class);
});

it('constructs with a default TTL and SystemClock if no clock provided', static function (): void {
    $handler = new ArraySessionHandler();
    expect($handler->ttl)->toBe(DEFAULT_TTL_ARRAY);
    $reflection = new \ReflectionClass($handler);
    $clockProp = $reflection->getProperty('clock');
    expect($clockProp->getValue($handler))->toBeInstanceOf(SystemClock::class);
});

it('constructs with a custom TTL and injected clock', static function (): void {
    $customTtl = 1800;
    $clock = new FixedClock();
    $handler = new ArraySessionHandler($customTtl, $clock);
    expect($handler->ttl)->toBe($customTtl);
    $reflection = new \ReflectionClass($handler);
    $clockProp = $reflection->getProperty('clock');
    expect($clockProp->getValue($handler))->toBe($clock);
});

it('writes session data and reads it back correctly', function (): void {
    $writeResult = $this->handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);
    expect($writeResult)->toBeTrue();

    $readData = $this->handler->read(SESSION_ID_ARRAY_1);
    expect($readData)->toBe(SESSION_DATA_1);
});

it('returns false when reading a non-existent session', function (): void {
    $readData = $this->handler->read('non-existent-session-id');
    expect($readData)->toBeFalse();
});

it('overwrites existing session data on subsequent write', function (): void {
    $this->handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);
    $updatedData = '{"user_id":101,"cart":{"items":[{"id":"prod_A","qty":3}],"total":175.25},"theme":"light"}';
    $this->handler->write(SESSION_ID_ARRAY_1, $updatedData);

    $readData = $this->handler->read(SESSION_ID_ARRAY_1);
    expect($readData)->toBe($updatedData);
});

it('returns false and removes data when reading an expired session due to handler TTL', static function (): void {
    $ttl = 60;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler($ttl, $fixedClock);
    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds($ttl + 1);

    $readData = $handler->read(SESSION_ID_ARRAY_1);
    expect($readData)->toBeFalse();

    $reflection = new \ReflectionClass($handler);
    $storeProp = $reflection->getProperty('store');
    $internalStore = $storeProp->getValue($handler);
    expect($internalStore)->not->toHaveKey(SESSION_ID_ARRAY_1);
});

it('does not return data if read exactly at TTL expiration time', static function (): void {
    $shortTtl = 60;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler($shortTtl, $fixedClock);
    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds($shortTtl);

    $readData = $handler->read(SESSION_ID_ARRAY_1);
    expect($readData)->toBe(SESSION_DATA_1);

    $fixedClock->addSecond();

    $readDataExpired = $handler->read(SESSION_ID_ARRAY_1);
    expect($readDataExpired)->toBeFalse();
});


it('updates timestamp on write, effectively extending session life', static function (): void {
    $veryShortTtl = 5;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler($veryShortTtl, $fixedClock);

    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds(3);

    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_2);

    $fixedClock->addSeconds(3);

    $readData = $handler->read(SESSION_ID_ARRAY_1);
    expect($readData)->toBe(SESSION_DATA_2);
});

it('destroys an existing session and it cannot be read', function (): void {
    $this->handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);
    expect($this->handler->read(SESSION_ID_ARRAY_1))->toBe(SESSION_DATA_1);

    $destroyResult = $this->handler->destroy(SESSION_ID_ARRAY_1);
    expect($destroyResult)->toBeTrue();
    expect($this->handler->read(SESSION_ID_ARRAY_1))->toBeFalse();

    $reflection = new \ReflectionClass($this->handler);
    $storeProp = $reflection->getProperty('store');
    expect($storeProp->getValue($this->handler))->not->toHaveKey(SESSION_ID_ARRAY_1);
});

it('destroy returns true and does nothing for a non-existent session', function (): void {
    $destroyResult = $this->handler->destroy('non-existent-id');
    expect($destroyResult)->toBeTrue();
});

it('garbage collects only sessions older than maxLifetime', static function (): void {
    $gcMaxLifetime = 100;
    $handlerTtl = 300;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler($handlerTtl, $fixedClock);

    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds(50);
    $handler->write(SESSION_ID_ARRAY_2, SESSION_DATA_2);

    $fixedClock->addSeconds(80);

    $deletedSessions = $handler->gc($gcMaxLifetime);

    expect($deletedSessions)->toBeArray()->toEqual([SESSION_ID_ARRAY_1]);
    expect($handler->read(SESSION_ID_ARRAY_1))->toBeFalse();
    expect($handler->read(SESSION_ID_ARRAY_2))->toBe(SESSION_DATA_2);
});

it('garbage collection respects maxLifetime precisely', static function (): void {
    $maxLifetime = 60;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler(300, $fixedClock);

    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds($maxLifetime);
    $deleted = $handler->gc($maxLifetime);
    expect($deleted)->toBeEmpty();
    expect($handler->read(SESSION_ID_ARRAY_1))->toBe(SESSION_DATA_1);

    $fixedClock->addSecond();
    $deleted2 = $handler->gc($maxLifetime);
    expect($deleted2)->toEqual([SESSION_ID_ARRAY_1]);
    expect($handler->read(SESSION_ID_ARRAY_1))->toBeFalse();
});

it('garbage collection returns empty array if no sessions meet criteria', function (): void {
    $this->handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);
    $this->handler->write(SESSION_ID_ARRAY_2, SESSION_DATA_2);

    $this->fixedClock->addSeconds(DEFAULT_TTL_ARRAY / 2);

    $deletedSessions = $this->handler->gc(DEFAULT_TTL_ARRAY);
    expect($deletedSessions)->toBeArray()->toBeEmpty();
    expect($this->handler->read(SESSION_ID_ARRAY_1))->toBe(SESSION_DATA_1);
    expect($this->handler->read(SESSION_ID_ARRAY_2))->toBe(SESSION_DATA_2);
});

it('garbage collection correctly handles an empty store', function (): void {
    $deletedSessions = $this->handler->gc(DEFAULT_TTL_ARRAY);
    expect($deletedSessions)->toBeArray()->toBeEmpty();
});

it('garbage collection removes multiple expired sessions', static function (): void {
    $maxLifetime = 30;
    $fixedClock = new FixedClock();
    $handler = new ArraySessionHandler(300, $fixedClock);

    $handler->write(SESSION_ID_ARRAY_1, SESSION_DATA_1);

    $fixedClock->addSeconds(20);
    $handler->write(SESSION_ID_ARRAY_2, SESSION_DATA_2);

    $fixedClock->addSeconds(20);
    $handler->write(SESSION_ID_ARRAY_3, SESSION_DATA_3);

    $fixedClock->addSeconds(20);

    $deleted = $handler->gc($maxLifetime);
    expect($deleted)->toHaveCount(2)->toContain(SESSION_ID_ARRAY_1)->toContain(SESSION_ID_ARRAY_2);
    expect($handler->read(SESSION_ID_ARRAY_1))->toBeFalse();
    expect($handler->read(SESSION_ID_ARRAY_2))->toBeFalse();
    expect($handler->read(SESSION_ID_ARRAY_3))->toBe(SESSION_DATA_3);
});
