<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Context
{
    public function __construct(
        public SessionInterface $session,
        public ?ServerRequestInterface $request = null,
    ) {}
}
