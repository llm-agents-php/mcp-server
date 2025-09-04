<?php

declare(strict_types=1);

namespace Mcp\Server\Contracts;

use Mcp\Server\Context;

interface HandlerInterface
{
    /**
     * Handle the execution with proper argument preparation and type casting.
     *
     * @param array $arguments The raw arguments to pass to the handler
     * @param Context $context The execution context
     * @return mixed The result of the handler execution
     */
    public function handle(
        array $arguments,
        Context $context,
    ): mixed;
}
