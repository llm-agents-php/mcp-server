<?php

declare(strict_types=1);

namespace Mcp\Server\Elements;

use PhpMcp\Schema\Content\BlobResourceContents;
use PhpMcp\Schema\Content\TextResourceContents;
use PhpMcp\Schema\Resource;
use Mcp\Server\Context;
use Mcp\Server\Contracts\HandlerInterface;

final class RegisteredResource extends RegisteredElement
{
    public function __construct(
        public readonly Resource $schema,
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
                Resource::fromArray($data['schema']),
                $data['handler'],
                $data['isManual'] ?? false,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Reads the resource content.
     *
     * @return array<TextResourceContents|BlobResourceContents> Array of ResourceContents objects.
     */
    public function read(string $uri, Context $context): array
    {
        $result = $this->handler->handle(['uri' => $uri], $context);

        return ResourceResultFormatter::format($result, $uri, $this->schema->mimeType);
    }

    public function toArray(): array
    {
        return [
            'schema' => $this->schema->toArray(),
            ...parent::toArray(),
        ];
    }
}
