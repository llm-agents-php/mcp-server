<?php

declare(strict_types=1);

namespace Mcp\Server\Elements;

use Mcp\Server\Contracts\HandlerInterface;
use PhpMcp\Schema\Tool;

final class RegisteredTool extends RegisteredElement
{
    public function __construct(
        public readonly Tool $schema,
        HandlerInterface $handler,
        bool $isManual = false,
    ) {
        parent::__construct($handler, $isManual);
    }

    public static function fromArray(array $data): self|false
    {
        try {
            if (!isset($data['schema']) || !isset($data['handler'])) {
                return false;
            }

            return new self(
                Tool::fromArray($data['schema']),
                $data['handler'],
                $data['isManual'] ?? false,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public function toArray(): array
    {
        return [
            'schema' => $this->schema->toArray(),
            ...parent::toArray(),
        ];
    }
}
