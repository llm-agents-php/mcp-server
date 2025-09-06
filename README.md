# PHP MCP Server SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/llm/mcp-server.svg?style=flat-square)](https://packagist.org/packages/llm/mcp-server)
[![Total Downloads](https://img.shields.io/packagist/dt/llm/mcp-server.svg?style=flat-square)](https://packagist.org/packages/llm/mcp-server)
[![License](https://img.shields.io/packagist/l/llm/mcp-server.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/llm/mcp-server.svg?style=flat-square)](https://packagist.org/packages/llm/mcp-server)

A comprehensive PHP SDK for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io/introduction)
servers. Create production-ready MCP servers in PHP with modern architecture, flexible transport options, and
comprehensive feature support.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
    - [Framework Integration](#framework-integration)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Transport Options](#transport-options)
    - [STDIO Transport](#stdio-transport-recommended-for-cli)
    - [HTTP Transport with SSE](#http-transport-with-sse)
    - [Streamable HTTP Transport](#streamable-http-transport)
- [Working with Tools](#working-with-tools)
    - [Basic Tool Registration](#basic-tool-registration)
    - [Advanced Tool with Multiple Content Types](#advanced-tool-with-multiple-content-types)
    - [Error Handling in Tools](#error-handling-in-tools)
- [Working with Resources](#working-with-resources)
- [Working with Prompts](#working-with-prompts)
- [HTTP Middleware](#http-middleware)
    - [Built-in Middleware](#built-in-middleware)
        - [CORS Middleware](#cors-middleware)
        - [Authentication Middleware](#authentication-middleware)
    - [Custom Middleware](#custom-middleware)
- [Advanced Configuration](#advanced-configuration)
    - [Complete Server Setup](#complete-server-setup)
    - [Event Store for Resumability](#event-store-for-resumability)
- [Common Patterns](#common-patterns)
    - [Handler Factory Pattern](#handler-factory-pattern)
    - [Decorator Pattern for Handlers](#decorator-pattern-for-handlers)
    - [Class-Based Tool Handlers with Schema Mapping](#class-based-tool-handlers-with-schema-mapping)
- [Ecosystem & Extensions](#ecosystem--extensions)
- [Contributing](#contributing)
- [License](#license)
- [Acknowledgments](#acknowledgments)

> This SDK implements the **MCP 2025-03-26** specification with full backward compatibility support.

## Features

- **Complete MCP Implementation**: Full support for tools, resources, prompts, and logging
- **Multiple Transport Options**: STDIO, HTTP with SSE, and streamable HTTP transports
- **Session Management**: Built-in session handling with configurable storage backends
- **Event-Driven Architecture**: ReactPHP-powered asynchronous operations
- **Middleware Support**: PSR-15 compatible HTTP middleware system
- **Type Safety**: Full PHP 8.3+ type declarations and comprehensive error handling
- **Extensible Design**: Pluggable components with clear interfaces
- **Production Ready**: Comprehensive logging, error handling, and testing support

### What's NOT Included

This SDK focuses on core MCP functionality and intentionally **does not include**:

- **Resource Discovery**: Automatic scanning and registration of resources from filesystem or annotations
- **Schema Generation**: Automatic generation of JSON schemas from PHP code or docblocks
- **Request Validation**: Built-in validation of incoming requests against defined schemas
- **Framework Integration**: Direct integration with web frameworks (Laravel, Symfony, etc.)

**Why?** These features are better implemented as separate packages or framework-specific bridges that can:

- Provide opinionated conventions for specific use cases
- Integrate deeply with framework ecosystems
- Offer different approaches to schema management
- Maintain focused, single-responsibility packages

## Requirements

- **PHP** >= 8.3 (required for advanced type features and performance)
- **Extensions**: `json`, `mbstring`, `pcre` (typically bundled with PHP)

## Installation

```bash
composer require llm/mcp-server
```

### Framework Integration

For enhanced developer experience with automatic discovery and validation:

**Spiral Framework**: Use the [spiral/mcp-server](https://github.com/spiral-packages/mcp-server) bridge

```bash
composer require spiral/mcp-server
```

**Other Frameworks**: Framework-specific bridges are planned. Contributions welcome!

## Quick Start

Here's a minimal working example that demonstrates core functionality:

```php
<?php

require_once 'vendor/autoload.php';

use Mcp\Server\Server;
use Mcp\Server\Configuration;
use Mcp\Server\Registry;
use Mcp\Server\Protocol;
use Mcp\Server\Defaults\CallableHandler;
use Mcp\Server\Defaults\ToolExecutor;
use Mcp\Server\Session\SessionManager;
use Mcp\Server\Session\ArraySessionHandler;
use Mcp\Server\Dispatcher;
use Mcp\Server\Dispatcher\RoutesFactory;
use Mcp\Server\Session\SubscriptionManager;
use Mcp\Server\Transports\StdioServerTransport;
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\ServerCapabilities;
use PhpMcp\Schema\Content\TextContent;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;

// Create server configuration
$serverInfo = new Implementation('my-server', '1.0.0');
$capabilities = new ServerCapabilities(tools: true);
$configuration = new Configuration($serverInfo, $capabilities);

// Set up core components
$loop = Loop::get();
$logger = new NullLogger();
$registry = new Registry($logger);
$sessionHandler = new ArraySessionHandler();
$sessionManager = new SessionManager($sessionHandler, $logger, $loop);
$subscriptionManager = new SubscriptionManager($logger);
$toolExecutor = new ToolExecutor($registry, $logger);

// Create dispatcher
$routesFactory = new RoutesFactory(
    $configuration, 
    $registry, 
    $subscriptionManager, 
    $toolExecutor,
);
$dispatcher = new Dispatcher($logger, $routesFactory);

// Create protocol handler
$protocol = new Protocol(
    $configuration,
    $registry,
    $sessionManager,
    $dispatcher,
    $subscriptionManager,
    $logger,
);

// Register a simple tool
$greetTool = Tool::make(
    'greet',
    'Greets a person by name',
    ['name' => ['type' => 'string', 'description' => 'Name to greet']],
);

$greetHandler = new CallableHandler(function($args) {
    return [TextContent::make("Hello, " . ($args['name'] ?? 'World') . "!")];
});

$registry->registerTool($greetTool, $greetHandler);

// Create and start server
$server = new Server($protocol, $sessionManager, $logger);
$transport = new StdioServerTransport();
$server->listen($transport);
```

**Expected Output**: The server will listen on STDIN/STDOUT and respond to MCP requests.

**To Test**: Save as `server.php` and run `php server.php`, then send MCP messages via STDIN.

## Core Concepts

### Architecture Overview

The SDK follows a layered architecture:

```
┌─────────────────┐
│    Transport    │  (STDIO, HTTP, Streamable HTTP)
├─────────────────┤
│    Protocol     │  (MCP message handling)
├─────────────────┤
│   Dispatcher    │  (Route requests to handlers)
├─────────────────┤
│    Registry     │  (Tools, Resources, Prompts)
├─────────────────┤
│   Handlers      │  (Your business logic)
└─────────────────┘
```

### Key Components

- **Server**: Main orchestrator that binds protocol to transport
- **Protocol**: Handles MCP message parsing and session management
- **Registry**: Manages tools, resources, and prompts registration
- **Transport**: Communication layer (STDIO, HTTP, etc.)
- **Session Manager**: Handles client sessions and state persistence

## Transport Options

### STDIO Transport (Recommended for CLI)

Perfect for command-line tools and process-based communication:

```php
use Mcp\Server\Transports\StdioServerTransport;

$transport = new StdioServerTransport();
$server->listen($transport);
```

**Use Cases**: CLI tools, subprocess communication, development/testing

### HTTP Transport with SSE

For web-based applications with real-time communication:

```php
use Mcp\Server\Transports\HttpServer;
use Mcp\Server\Transports\HttpServerTransport;
use React\EventLoop\Loop;

$loop = Loop::get();
$httpServer = new HttpServer($loop, '127.0.0.1', 8080, '/mcp');
$transport = new HttpServerTransport($httpServer);
$server->listen($transport);
```

**Use Cases**: Web applications, browser-based clients, dashboard integrations

### Streamable HTTP Transport

Advanced HTTP transport with JSON response and resumability support:

```php
use Mcp\Server\Transports\StreamableHttpServerTransport;
use Mcp\Server\Defaults\InMemoryEventStore;

$eventStore = new InMemoryEventStore();
$transport = new StreamableHttpServerTransport(
    httpServer: $httpServer,
    enableJsonResponse: true,
    stateless: false,
    eventStore: $eventStore
);
$server->listen($transport);
```

**Use Cases**: High-performance applications, stateless deployments, fault-tolerant systems

## Working with Tools

Tools are executable functions that AI assistants can call to perform actions.

### Basic Tool Registration

```php
use PhpMcp\Schema\Tool;
use Mcp\Server\Defaults\CallableHandler;
use PhpMcp\Schema\Content\TextContent;

// Define tool schema
$calculatorTool = Tool::make(
    name: 'calculator',
    description: 'Performs basic mathematical operations',
    inputSchema: [
        'operation' => [
            'type' => 'string',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
            'description' => 'The operation to perform'
        ],
        'a' => ['type' => 'number', 'description' => 'First number'],
        'b' => ['type' => 'number', 'description' => 'Second number']
    ]
);

// Create handler
$calculatorHandler = new CallableHandler(function($args) {
    $a = $args['a'];
    $b = $args['b'];
    $operation = $args['operation'];
    
    $result = match($operation) {
        'add' => $a + $b,
        'subtract' => $a - $b,
        'multiply' => $a * $b,
        'divide' => $b !== 0 ? $a / $b : throw new InvalidArgumentException('Division by zero'),
        default => throw new InvalidArgumentException('Unknown operation')
    };
    
    return [TextContent::make("Result: $result")];
});

$registry->registerTool($calculatorTool, $calculatorHandler);
```

### Advanced Tool with Multiple Content Types

```php
use PhpMcp\Schema\Content\ImageContent;
use PhpMcp\Schema\Content\BlobResourceContents;

$imageProcessorHandler = new CallableHandler(function($args) {
    $imagePath = $args['image_path'];
    
    // Process image (example)
    $imageData = file_get_contents($imagePath);
    $base64Image = base64_encode($imageData);
    
    return [
        TextContent::make("Processed image: $imagePath"),
        ImageContent::make($base64Image, 'image/jpeg'),
    ];
});
```

### Error Handling in Tools

```php
use Mcp\Server\Exception\ValidationException;

$validatedToolHandler = new CallableHandler(function($args) {
    if (!isset($args['required_param'])) {
        throw new ValidationException([
            [
                'pointer' => '/required_param',
                'keyword' => 'required',
                'message' => 'This parameter is required'
            ]
        ]);
    }
    
    // Tool logic here
    return [TextContent::make('Success!')];
});
```

## Working with Resources

Resources provide read-only access to data that AI assistants can reference.

### Static Resources

```php
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\Content\TextResourceContents;

$docResource = Resource::make(
    uri: 'file:///docs/readme.txt',
    name: 'README Documentation',
    description: 'Application documentation',
    mimeType: 'text/plain'
);

$docHandler = new CallableHandler(function($args) {
    $content = file_get_contents(__DIR__ . '/README.txt');
    return [TextResourceContents::make($args['uri'], 'text/plain', $content)];
});

$registry->registerResource($docResource, $docHandler);
```

### Dynamic Resource Templates

Resource templates use URI patterns to handle multiple similar resources:

```php
use PhpMcp\Schema\ResourceTemplate;

$userTemplate = ResourceTemplate::make(
    uriTemplate: 'user://{user_id}/profile',
    name: 'User Profile Template',
    description: 'Access user profile data',
    mimeType: 'application/json'
);

$userHandler = new CallableHandler(function($args) {
    $userId = $args['user_id'];
    
    // Fetch user data from database/API
    $userData = getUserData($userId);
    
    return [TextResourceContents::make(
        $args['uri'],
        'application/json',
        json_encode($userData)
    )];
});

$registry->registerResourceTemplate($userTemplate, $userHandler);
```

### Resource with Completion Providers

```php
use Mcp\Server\Defaults\ListCompletionProvider;

$completionProvider = new ListCompletionProvider(['admin', 'user', 'guest']);

$registry->registerResourceTemplate(
    $userTemplate,
    $userHandler,
    completionProviders: ['user_id' => $completionProvider]
);
```

## Working with Prompts

Prompts provide templated text generation for AI assistants.

### Basic Prompt

```php
use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\Content\PromptMessage;
use PhpMcp\Schema\Enum\Role;

$codeReviewPrompt = Prompt::make(
    name: 'code_review',
    description: 'Generate code review prompt',
    arguments: [
        'code' => ['type' => 'string', 'required' => true, 'description' => 'Code to review'],
        'language' => ['type' => 'string', 'description' => 'Programming language']
    ]
);

$codeReviewHandler = new CallableHandler(function($args) {
    $code = $args['code'];
    $language = $args['language'] ?? 'unknown';
    
    $systemPrompt = "You are a senior software engineer reviewing {$language} code.";
    $userPrompt = "Please review this code:\n\n```{$language}\n{$code}\n```";
    
    return [
        PromptMessage::make(Role::User, TextContent::make($systemPrompt)),
        PromptMessage::make(Role::User, TextContent::make($userPrompt))
    ];
});

$registry->registerPrompt($codeReviewPrompt, $codeReviewHandler);
```

### Advanced Prompt with Multiple Message Types

```php
$conversationPrompt = new CallableHandler(function($args) {
    $context = $args['context'];
    $question = $args['question'];
    
    return [
        [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => "Context: $context"
            ]
        ],
        [
            'role' => 'user', 
            'content' => [
                'type' => 'text',
                'text' => "Question: $question"
            ]
        ]
    ];
});
```

## Session Management

### Custom Session Handlers

Implement the `SessionHandlerInterface` for custom storage:

```php
use Mcp\Server\Contracts\SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo) {}
    
    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: false;
    }
    
    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = NOW()'
        );
        return $stmt->execute([$id, $data]);
    }
    
    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }
    
    public function gc(int $maxLifetime): array
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$maxLifetime]);
        return []; // Return deleted session IDs if needed
    }
}
```

### Cache-Based Session Storage

```php
use Mcp\Server\Session\CacheSessionHandler;
use Mcp\Server\Defaults\FileCache;

$cache = new FileCache('/tmp/mcp_sessions.cache');
$sessionHandler = new CacheSessionHandler($cache, ttl: 7200);
$sessionManager = new SessionManager($sessionHandler, $logger, $loop);
```

## HTTP Middleware

### Built-in Middleware

#### CORS Middleware

```php
use Mcp\Server\Transports\Middleware\CorsMiddleware;

$corsMiddleware = new CorsMiddleware(
    allowedOrigins: ['https://myapp.com', 'https://localhost:3000'],
    allowedMethods: ['GET', 'POST', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'Mcp-Session-Id'],
    maxAge: 86400,
);

$httpServer = new HttpServer(
    $loop,
    '127.0.0.1',
    8080,
    '/mcp',
    middleware: [$corsMiddleware],
);
```

#### Authentication Middleware

```php
use Mcp\Server\Transports\Middleware\AuthenticationMiddleware;

$authMiddleware = new AuthenticationMiddleware(
    authenticator: function($request, $authHeader) {
        // Bearer token validation
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        
        $token = substr($authHeader, 7);
        return validateToken($token); // Your validation logic
    },
    protectedPaths: ['/mcp']
);
```

### Custom Middleware

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        
        $this->logger->info('Request started', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath()
        ]);
        
        $response = $handler->handle($request);
        
        $duration = microtime(true) - $start;
        $this->logger->info('Request completed', [
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms'
        ]);
        
        return $response;
    }
}
```

## Advanced Configuration

### Complete Server Setup

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Mcp\Server\Dispatcher\Paginator;

// Advanced logging
$logger = new Logger('mcp-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

// Custom pagination
$paginator = new Paginator(paginationLimit: 100, logger: $logger);

// Server configuration with all features
$serverInfo = new Implementation(
    name: 'advanced-mcp-server',
    version: '2.0.0'
);

$capabilities = new ServerCapabilities(
    tools: true,
    resources: true,
    resourcesSubscribe: true,
    resourcesListChanged: true,
    prompts: true,
    promptsListChanged: true,
    logging: true,
    completions: true
);

$configuration = new Configuration(
    serverInfo: $serverInfo,
    capabilities: $capabilities,
    instructions: 'This server provides comprehensive MCP functionality with advanced features.'
);

// Enhanced routes factory
$routesFactory = new RoutesFactory(
    configuration: $configuration,
    registry: $registry,
    subscriptionManager: $subscriptionManager,
    toolExecutor: $toolExecutor,
    pagination: $paginator,
    logger: $logger
);
```

### Event Store for Resumability

```php
use Mcp\Server\Defaults\InMemoryEventStore;

class RedisEventStore implements EventStoreInterface
{
    public function __construct(private Redis $redis) {}
    
    public function storeEvent(string $streamId, string $message): string
    {
        $eventId = $streamId . '_' . time() . '_' . uniqid();
        $this->redis->hSet("stream:$streamId", $eventId, $message);
        return $eventId;
    }
    
    public function replayEventsAfter(string $lastEventId, callable $sendCallback): void
    {
        // Extract stream ID from event ID
        $streamId = explode('_', $lastEventId)[0];
        $events = $this->redis->hGetAll("stream:$streamId");
        
        $found = false;
        foreach ($events as $eventId => $message) {
            if ($eventId === $lastEventId) {
                $found = true;
                continue;
            }
            
            if ($found) {
                $sendCallback($eventId, $message);
            }
        }
    }
}
```

## Performance Optimization

### Caching Strategies

```php
use Mcp\Server\Defaults\FileCache;
use Mcp\Server\Contracts\HandlerInterface;

final readonly class CachedResourceHandler implements HandlerInterface
{
    public function __construct(
        private HandlerInterface $delegate,
        private CacheInterface $cache,
        private int $ttl = 3600,
    ) {}
    
    public function handle(array $arguments, Context $context): mixed
    {
        $cacheKey = 'resource:' . md5(serialize($arguments));
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $this->delegate->handle($arguments, $context);
        $this->cache->set($cacheKey, $result, $this->ttl);
        
        return $result;
    }
}
```

### Memory Management

```php
// Periodic memory cleanup
$server = new Server($protocol, $sessionManager, $logger);

$loop->addPeriodicTimer(60, function() use ($logger) {
    $memoryUsage = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    $logger->info('Memory status', [
        'current_mb' => round($memoryUsage / 1024 / 1024, 2),
        'peak_mb' => round($peakMemory / 1024 / 1024, 2),
    ]);
    
    // Force garbage collection if memory usage is high
    if ($memoryUsage > 100 * 1024 * 1024) { // 100MB
        gc_collect_cycles();
    }
});
```

### Handler Factory Pattern

```php
final readonly class HandlerFactory
{
    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {}
    
    public function createHandler(string $handlerClass): HandlerInterface
    {
        return new CallableHandler(function($args) use ($handlerClass) {
            $instance = $this->container->get($handlerClass);
            return $instance->execute($args);
        });
    }
}

// Usage
$toolHandler = $handlerFactory->createHandler(CalculatorService::class);
$registry->registerTool($calculatorTool, $toolHandler);
```

### Decorator Pattern for Handlers

```php
use Mcp\Server\Contracts\HandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class LoggingHandlerDecorator implements HandlerInterface
{
    public function __construct(
        private HandlerInterface $handler,
        private LoggerInterface $logger,
    ) {}
    
    public function handle(array $arguments, Context $context): mixed
    {
        $start = microtime(true);
        $this->logger->debug('Handler execution started', ['arguments' => $arguments]);
        
        try {
            $result = $this->handler->handle($arguments, $context);
            $duration = microtime(true) - $start;
            $this->logger->info('Handler execution completed', ['duration' => $duration]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Handler execution failed', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

### Class-Based Tool Handlers with Schema Mapping

For more advanced use cases with automatic schema generation and request mapping, you can create handlers that work with
DTO classes:

```php
use Spiral\McpServer\SchemaMapperInterface;

final readonly class ClassHandler implements HandlerInterface
{
    public function __construct(
        private FactoryInterface $factory,
        private SchemaMapperInterface $schemaMapper,
        private \ReflectionClass $class,
        private ?string $schemaClass = null,
    ) {}

    public function handle(
        array $arguments,
        Context $context,
    ): mixed {
        /** @var callable $tool */
        $tool = $this->factory->make($this->class->getName());

        if ($this->schemaClass === null) {
            return $tool();
        }

        // Map raw arguments to strongly-typed DTO
        $object = $this->schemaMapper->toObject(
            json: \json_encode($arguments),
            class: $this->schemaClass,
        );

        return $tool($object);
    }
}
```

**Schema Mapper Interface:**

```php
interface SchemaMapperInterface
{
    /**
     * Generate JSON schema from PHP class
     * @param class-string $class
     */
    public function toJsonSchema(string $class): array;

    /**
     * Map JSON to strongly-typed PHP object
     * @template T of object
     * @param class-string<T>|null $class
     * @return T
     */
    public function toObject(string $json, ?string $class = null): object;
}
```

**Implementation with Valinor and spiral/json-schema-generator:**

```php
use CuyZ\Valinor\Mapper\TreeMapper;
use Spiral\JsonSchemaGenerator\Generator as JsonSchemaGenerator;

final readonly class SchemaMapper implements SchemaMapperInterface
{
    public function __construct(
        private JsonSchemaGenerator $generator,
        private TreeMapper $mapper,
    ) {}

    public function toJsonSchema(string $class): array
    {
        if (\json_validate($class)) {
            return \json_decode($class, associative: true);
        }

        if (\class_exists($class)) {
            return $this->generator->generate($class)->jsonSerialize();
        }

        throw new \InvalidArgumentException("Invalid class or JSON schema: {$class}");
    }

    public function toObject(string $json, ?string $class = null): object
    {
        if ($class === null) {
            return \json_decode($json, associative: false);
        }

        return $this->mapper->map($class, \json_decode($json, associative: true));
    }
}
```

**Usage Example:**

```php
// DTO class
final readonly class CalculatorRequest
{
    public function __construct(
        public string $operation,
        public float $a,
        public float $b,
    ) {}
}

// Tool implementation
final readonly class Calculator
{
    public function __invoke(CalculatorRequest $request): float
    {
        return match($request->operation) {
            'add' => $request->a + $request->b,
            'subtract' => $request->a - $request->b,
            'multiply' => $request->a * $request->b,
            'divide' => $request->b !== 0.0 ? $request->a / $request->b 
                : throw new InvalidArgumentException('Division by zero'),
            default => throw new InvalidArgumentException('Unknown operation')
        };
    }
}

// Registration with automatic schema generation
$schema = $schemaMapper->toJsonSchema(CalculatorRequest::class);
$tool = Tool::make('calculator', 'Performs calculations', $schema);
$handler = new ClassHandler(
  $factory, 
  $schemaMapper, 
  new \ReflectionClass(Calculator::class), 
  CalculatorRequest::class,
);
$registry->registerTool($tool, $handler);
```

## Contributing

We welcome contributions! Please follow these guidelines:

### Development Setup

```bash
git clone https://github.com/your-org/php-mcp-server.git
cd php-mcp-server
composer install
composer test
```

### Code Style

We follow PSR-12 coding standards:

```bash
composer cs-fix  # Fix code style
composer cs-check # Check code style
composer refactor # Run rector 
```

### Testing

```bash
composer test        # Run all tests
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

## Ecosystem & Extensions

### Framework Bridges

**Spiral Framework**: [spiral/mcp-server](https://github.com/spiral-packages/mcp-server)

- Automatic resource discovery via annotations
- JSON schema generation from PHP classes
- Built-in request/response validation
- Dependency injection integration
- Configuration management

### Planned Extensions

- **Laravel Bridge**: Integration with Laravel's service container and validation
- **Symfony Bridge**: Symfony bundle with automatic service discovery

## Acknowledgments

- Built on the [Model Context Protocol](https://modelcontextprotocol.io/) specification
- Powered by [ReactPHP](https://reactphp.org/) for async operations
- Uses [PSR standards](https://www.php-fig.org/) for maximum interoperability