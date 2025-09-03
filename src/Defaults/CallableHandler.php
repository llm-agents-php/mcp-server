<?php

declare(strict_types=1);

namespace Mcp\Server\Defaults;

use Mcp\Server\Context;
use Mcp\Server\Contracts\HandlerInterface;

final readonly class CallableHandler implements HandlerInterface
{
    public function __construct(
        private \Closure $handler,
    ) {}

    public function handle(
        array $arguments,
        Context $context,
    ): mixed {
        return \call_user_func($this->handler, $arguments);
    }
}
