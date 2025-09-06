<?php

declare(strict_types=1);

namespace Mcp\Server;

use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\ServerCapabilities;

final readonly class Configuration
{
    /**
     * @param string|null $instructions Instructions describing how to use the server and its features.
     */
    public function __construct(
        public Implementation $serverInfo,
        public ServerCapabilities $capabilities,
        public ?string $instructions = null,
    ) {}
}
