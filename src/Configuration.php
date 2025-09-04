<?php

declare(strict_types=1);

namespace Mcp\Server;

use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\ServerCapabilities;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\LoopInterface;

/**
 * Value Object holding core configuration and shared dependencies for the MCP Server instance.
 *
 * This object is typically assembled by the ServerBuilder and passed to the Server constructor.
 */
final readonly class Configuration
{
    /**
     * @param Implementation $serverInfo Info about this MCP server application.
     * @param ServerCapabilities $capabilities Capabilities of this MCP server application.
     * @param LoggerInterface $logger PSR-3 Logger instance.
     * @param LoopInterface $loop ReactPHP Event Loop instance.
     * @param CacheInterface|null $cache Optional PSR-16 Cache instance for registry/state.
     * @param ContainerInterface $container PSR-11 DI Container for resolving handlers/dependencies.
     * @param int $paginationLimit Maximum number of items to return for list methods.
     * @param string|null $instructions Instructions describing how to use the server and its features.
     */
    public function __construct(
        public Implementation $serverInfo,
        public ServerCapabilities $capabilities,
        public LoggerInterface $logger,
        public LoopInterface $loop,
        public ?CacheInterface $cache,
        public ContainerInterface $container,
        public int $paginationLimit = 50,
        public ?string $instructions = null,
    ) {}
}
