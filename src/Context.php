<?php

declare(strict_types=1);

namespace PhpMcp\Server;

use PhpMcp\Server\Contracts\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Context
{
    public function __construct(
        public SessionInterface $session,
        public ?ServerRequestInterface $request = null,
    ) {
    }
}
