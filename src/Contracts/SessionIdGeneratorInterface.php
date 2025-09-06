<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

interface SessionIdGeneratorInterface
{
    public function generate(): string;
}
