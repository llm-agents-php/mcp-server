<?php

declare(strict_types=1);

namespace PhpMcp\Server\Elements;

use JsonSerializable;
use PhpMcp\Server\Contracts\HandlerInterface;
use PhpMcp\Server\Handlers\DefaultHandler;

class RegisteredElement implements JsonSerializable
{
    public readonly HandlerInterface $handler;

    public function __construct(
        HandlerInterface|callable|array|string $handler,
        public readonly bool $isManual = false,
    ) {
        $this->handler = $handler instanceof HandlerInterface
            ? $handler
            : new DefaultHandler($handler);
    }

    public function toArray(): array
    {
        return [
            'handler' => $this->handler,
            'isManual' => $this->isManual,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
