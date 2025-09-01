<?php

declare(strict_types=1);

namespace PhpMcp\Server\Contracts;

use PhpMcp\Server\Context;
use Psr\Container\ContainerInterface;

interface HandlerInterface
{
    /**
     * Handle the execution with proper argument preparation and type casting.
     *
     * @param ContainerInterface $container The dependency injection container
     * @param array $arguments The raw arguments to pass to the handler
     * @param Context $context The execution context
     * @return mixed The result of the handler execution
     */
    public function handle(
        ContainerInterface $container,
        array $arguments,
        Context $context,
    ): mixed;
}
