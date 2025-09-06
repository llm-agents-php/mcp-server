<?php

declare(strict_types=1);

namespace Mcp\Server\Session;

use Mcp\Server\Contracts\SessionIdGeneratorInterface;
use Random\RandomException;

final readonly class SessionIdGenerator implements SessionIdGeneratorInterface
{
    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return \bin2hex(\random_bytes(16)); // 32 hex characters
    }
}
