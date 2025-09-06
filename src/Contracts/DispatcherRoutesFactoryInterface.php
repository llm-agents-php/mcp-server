<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

interface DispatcherRoutesFactoryInterface
{
    /**
     * @return RouteInterface[]
     */
    public function create(): array;
}
