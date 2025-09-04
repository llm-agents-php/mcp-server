<?php

declare(strict_types=1);

namespace Mcp\Server\Elements;

use Mcp\Server\Contracts\HandlerInterface;

class RegisteredElement implements \JsonSerializable
{
    public function __construct(
        public readonly HandlerInterface $handler,
        public readonly bool $isManual = false,
    ) {}

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
