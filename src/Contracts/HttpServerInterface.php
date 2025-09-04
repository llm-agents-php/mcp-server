<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;

interface HttpServerInterface extends EventEmitterInterface
{
    public function listen(callable $onRequest, callable $onClose): void;

    public function mcpPath(): string;

    public function onTick(\Closure $onTick): void;

    public function getLoop(): LoopInterface;

    public function isClosing(): bool;
}
