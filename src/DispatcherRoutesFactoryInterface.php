<?php

declare(strict_types=1);

namespace Mcp\Server;

use Mcp\Server\Contracts\RouteInterface;

interface DispatcherRoutesFactoryInterface
{
    /**
     * @return RouteInterface[]
     */
    public function create(): array;
}
