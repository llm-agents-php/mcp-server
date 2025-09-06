<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Transports\Middleware;

use Mcp\Server\Tests\TestCase;
use Mcp\Server\Transports\Middleware\MiddlewareAdapter;
use Mcp\Server\Transports\Middleware\RequestHandler;
use Mcp\Server\Transports\Middleware\MiddlewareUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Io\MiddlewareRunner;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

/**
 * Integration tests for React MiddlewareRunner with custom MiddlewareAdapter and RequestHandler
 */
final class MiddlewareRunnerIntegrationTest extends TestCase
{
    public function testMiddlewareRunnerWithSingleReactCallable(): void
    {
        $request = new ServerRequest('GET', '/');
        $expectedResponse = new Response(200, [], 'Hello World');

        // Final middleware (no $next parameter)
        $middleware = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(
            200,
            [],
            'Hello World',
        ));

        $runner = new MiddlewareRunner([$middleware]);
        $response = $runner($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', (string) $response->getBody());
    }

    public function testMiddlewareRunnerWithSinglePsr15Middleware(): void
    {
        $request = new ServerRequest('GET', '/');

        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return new Response(200, [], 'PSR-15 Response');
            }
        };

        // Final handler (no $next parameter)
        $finalHandler = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(
            404,
            [],
            'Not Found',
        ));

        $runner = new MiddlewareRunner([new MiddlewareAdapter($psr15Middleware), $finalHandler]);

        $response = $runner($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PSR-15 Response', (string) $response->getBody());
    }

    public function testMiddlewareRunnerWithMixedMiddlewareTypes(): void
    {
        $request = new ServerRequest('GET', '/');

        // React-style middleware (with $next)
        $reactMiddleware = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
            $response = $next($request);
            return $response->withHeader('X-React-Middleware', 'true');
        };

        // PSR-15 middleware
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $response = $handler->handle($request);
                return $response->withHeader('X-PSR15-Middleware', 'true');
            }
        };

        // Final handler (no $next)
        $finalHandler = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(
            200,
            [],
            'Final Response',
        ));

        $middlewares = [
            $reactMiddleware,
            new MiddlewareAdapter($psr15Middleware),
            $finalHandler,
        ];

        $runner = new MiddlewareRunner($middlewares);
        $response = $runner($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Final Response', (string) $response->getBody());
        $this->assertTrue($response->hasHeader('X-React-Middleware'));
        $this->assertTrue($response->hasHeader('X-PSR15-Middleware'));
        $this->assertEquals('true', $response->getHeaderLine('X-React-Middleware'));
        $this->assertEquals('true', $response->getHeaderLine('X-PSR15-Middleware'));
    }

    public function testMiddlewareRunnerWithMiddlewareUtils(): void
    {
        $request = new ServerRequest('GET', '/');

        // React callable (with $next)
        $reactMiddleware = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
            $response = $next($request);
            return $response->withHeader('X-React', 'processed');
        };

        // PSR-15 middleware
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $response = $handler->handle($request);
                return $response->withHeader('X-PSR15', 'processed');
            }
        };

        // Final handler (no $next)
        $finalHandler = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(
            201,
            [],
            'Created',
        ));

        // Use MiddlewareUtils to normalize
        $normalizedMiddleware = MiddlewareUtils::normalizeMiddleware([
            $reactMiddleware,
            $psr15Middleware,
        ]);

        $allMiddleware = [...$normalizedMiddleware, $finalHandler];
        $runner = new MiddlewareRunner($allMiddleware);

        $response = $runner($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Created', (string) $response->getBody());
        $this->assertEquals('processed', $response->getHeaderLine('X-React'));
        $this->assertEquals('processed', $response->getHeaderLine('X-PSR15'));
    }

    public function testMiddlewareRunnerExecutionOrder(): void
    {
        $request = new ServerRequest('GET', '/');
        $executionOrder = [];

        $middleware1 = static function (ServerRequestInterface $request, callable $next) use (
            &$executionOrder,
        ): ResponseInterface {
            $executionOrder[] = 'middleware1-before';
            $response = $next($request);
            $executionOrder[] = 'middleware1-after';
            return $response;
        };

        $psr15Middleware = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(
                private array &$executionOrder,
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $this->executionOrder[] = 'psr15-before';
                $response = $handler->handle($request);
                $this->executionOrder[] = 'psr15-after';
                return $response;
            }
        };

        $finalHandler = static function (ServerRequestInterface $request) use (&$executionOrder): ResponseInterface {
            $executionOrder[] = 'final-handler';
            return new Response(200, [], 'OK');
        };

        $runner = new MiddlewareRunner([
            $middleware1,
            new MiddlewareAdapter($psr15Middleware),
            $finalHandler,
        ]);

        $runner($request);

        $this->assertEquals([
            'middleware1-before',
            'psr15-before',
            'final-handler',
            'psr15-after',
            'middleware1-after',
        ], $executionOrder);
    }

    public function testMiddlewareRunnerWithNoMiddleware(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No middleware to run');

        $runner = new MiddlewareRunner([]);
        $runner(new ServerRequest('GET', '/'));
    }

    public function testMiddlewareRunnerWithExceptionInMiddleware(): void
    {
        $request = new ServerRequest('GET', '/');

        // Final middleware should not expect $next parameter when no more middleware
        $throwingMiddleware = static function (ServerRequestInterface $request): ResponseInterface {
            throw new \RuntimeException('Middleware error');
        };

        $runner = new MiddlewareRunner([$throwingMiddleware]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware error');

        $runner($request);
    }

    public function testMiddlewareRunnerWithExceptionInPsr15Middleware(): void
    {
        $request = new ServerRequest('GET', '/');

        $throwingPsr15 = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                throw new \InvalidArgumentException('PSR-15 error');
            }
        };

        // Need a final handler
        $finalHandler = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200));

        $runner = new MiddlewareRunner([new MiddlewareAdapter($throwingPsr15), $finalHandler]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-15 error');

        $runner($request);
    }

    public function testMiddlewareAdapterWithRequestModification(): void
    {
        $request = new ServerRequest('GET', '/');

        $modifyingMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $modifiedRequest = $request->withAttribute('modified', true);
                return $handler->handle($modifiedRequest);
            }
        };

        $finalHandler = static function (ServerRequestInterface $request): ResponseInterface {
            $modified = $request->getAttribute('modified', false);
            return new Response(200, [], $modified ? 'modified' : 'unmodified');
        };

        $runner = new MiddlewareRunner([
            new MiddlewareAdapter($modifyingMiddleware),
            $finalHandler,
        ]);

        $response = $runner($request);

        $this->assertEquals('modified', (string) $response->getBody());
    }

    public function testRequestHandlerWithClosure(): void
    {
        $closure = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(
            200,
            [],
            'Handler response',
        ));

        $handler = new RequestHandler($closure);
        $response = $handler->handle(new ServerRequest('GET', '/'));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('Handler response', (string) $response->getBody());
    }

    public function testMiddlewareRunnerWithComplexMiddlewareChain(): void
    {
        $request = new ServerRequest('GET', '/api/test');

        // Authentication middleware (PSR-15)
        $authMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                if (!$request->hasHeader('Authorization')) {
                    return new Response(401, [], 'Unauthorized');
                }
                return $handler->handle($request->withAttribute('authenticated', true));
            }
        };

        // Logging middleware (React)
        $loggingMiddleware = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
            $response = $next($request);
            return $response->withHeader('X-Logged', 'true');
        };

        // CORS middleware (PSR-15)
        $corsMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                $response = $handler->handle($request);
                return $response->withHeader('Access-Control-Allow-Origin', '*');
            }
        };

        // Final handler
        $apiHandler = static function (ServerRequestInterface $request): ResponseInterface {
            $authenticated = $request->getAttribute('authenticated', false);
            return new Response(200, [], \json_encode([
                'message' => 'API Response',
                'authenticated' => $authenticated,
            ]));
        };

        // Test with authorization header
        $authorizedRequest = $request->withHeader('Authorization', 'Bearer token');

        $runner = new MiddlewareRunner([
            new MiddlewareAdapter($authMiddleware),
            $loggingMiddleware,
            new MiddlewareAdapter($corsMiddleware),
            $apiHandler,
        ]);

        $response = $runner($authorizedRequest);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Logged'));
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));

        $body = \json_decode((string) $response->getBody(), true);
        $this->assertEquals('API Response', $body['message']);
        $this->assertTrue($body['authenticated']);

        // Test without authorization header
        $unauthorizedResponse = $runner($request);
        $this->assertEquals(401, $unauthorizedResponse->getStatusCode());
        $this->assertEquals('Unauthorized', (string) $unauthorizedResponse->getBody());
    }

    public function testMiddlewareUtilsNormalizeMiddlewareWithInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware must be callable or implement MiddlewareInterface');

        MiddlewareUtils::normalizeMiddleware(['not-callable-or-middleware']);
    }

    public function testMiddlewareAdapterCallableInvocation(): void
    {
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return new Response(200, [], 'PSR-15 via adapter');
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $request = new ServerRequest('GET', '/');

        $nextCalled = false;
        $next = static function (ServerRequestInterface $request) use (&$nextCalled): ResponseInterface {
            $nextCalled = true;
            return new Response(200, [], 'Next called');
        };

        $response = $adapter($request, $next);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('PSR-15 via adapter', (string) $response->getBody());
        $this->assertFalse($nextCalled); // PSR-15 middleware doesn't call next in this case
    }

    public function testMiddlewareRunnerWithMultipleReactMiddleware(): void
    {
        $request = new ServerRequest('GET', '/test');
        $executionOrder = [];

        $middleware1 = static function (ServerRequestInterface $request, callable $next) use (
            &$executionOrder,
        ): ResponseInterface {
            $executionOrder[] = 'react1-start';
            $response = $next($request->withAttribute('mw1', 'processed'));
            $executionOrder[] = 'react1-end';
            return $response->withHeader('X-MW1', 'true');
        };

        $middleware2 = static function (ServerRequestInterface $request, callable $next) use (
            &$executionOrder,
        ): ResponseInterface {
            $executionOrder[] = 'react2-start';
            $response = $next($request->withAttribute('mw2', 'processed'));
            $executionOrder[] = 'react2-end';
            return $response->withHeader('X-MW2', 'true');
        };

        $finalHandler = static function (ServerRequestInterface $request) use (&$executionOrder): ResponseInterface {
            $executionOrder[] = 'final-handler';
            $mw1 = $request->getAttribute('mw1', 'none');
            $mw2 = $request->getAttribute('mw2', 'none');
            return new Response(200, [], \json_encode(['mw1' => $mw1, 'mw2' => $mw2]));
        };

        $runner = new MiddlewareRunner([$middleware1, $middleware2, $finalHandler]);
        $response = $runner($request);

        $this->assertEquals(
            ['react1-start', 'react2-start', 'final-handler', 'react2-end', 'react1-end'],
            $executionOrder,
        );
        $this->assertEquals('true', $response->getHeaderLine('X-MW1'));
        $this->assertEquals('true', $response->getHeaderLine('X-MW2'));

        $body = \json_decode((string) $response->getBody(), true);
        $this->assertEquals('processed', $body['mw1']);
        $this->assertEquals('processed', $body['mw2']);
    }
}
