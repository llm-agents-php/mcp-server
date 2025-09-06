<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Transports\Middleware;

use Mcp\Server\Tests\TestCase;
use Mcp\Server\Transports\Middleware\MiddlewareAdapter;
use Mcp\Server\Transports\Middleware\MiddlewareUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

/**
 * Unit tests for MiddlewareUtils
 */
final class MiddlewareUtilsTest extends TestCase
{
    public static function staticMiddleware(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        return new Response(200, [], 'static middleware');
    }

    public function testNormalizeMiddlewareWithEmptyArray(): void
    {
        $result = MiddlewareUtils::normalizeMiddleware([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testNormalizeMiddlewareWithReactCallable(): void
    {
        $reactMiddleware = (static fn(ServerRequestInterface $request, callable $next): ResponseInterface => new Response(200));

        $result = MiddlewareUtils::normalizeMiddleware([$reactMiddleware]);

        $this->assertCount(1, $result);
        $this->assertSame($reactMiddleware, $result[0]);
        $this->assertTrue(\is_callable($result[0]));
    }

    public function testNormalizeMiddlewareWithPsr15Middleware(): void
    {
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(200);
            }
        };

        $result = MiddlewareUtils::normalizeMiddleware([$psr15Middleware]);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(MiddlewareAdapter::class, $result[0]);
        $this->assertTrue(\is_callable($result[0]));
    }

    public function testNormalizeMiddlewareWithMixedTypes(): void
    {
        $reactMiddleware = (static fn(ServerRequestInterface $request, callable $next): ResponseInterface => new Response(200));

        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(200);
            }
        };

        $anotherReactMiddleware = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(201));

        $middlewares = [$reactMiddleware, $psr15Middleware, $anotherReactMiddleware];
        $result = MiddlewareUtils::normalizeMiddleware($middlewares);

        $this->assertCount(3, $result);
        $this->assertSame($reactMiddleware, $result[0]);
        $this->assertInstanceOf(MiddlewareAdapter::class, $result[1]);
        $this->assertSame($anotherReactMiddleware, $result[2]);

        // Ensure all are callable
        foreach ($result as $middleware) {
            $this->assertTrue(\is_callable($middleware));
        }
    }

    public function testNormalizeMiddlewareWithMultiplePsr15Middleware(): void
    {
        $middleware1 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(200, [], 'middleware1');
            }
        };

        $middleware2 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(200, [], 'middleware2');
            }
        };

        $result = MiddlewareUtils::normalizeMiddleware([$middleware1, $middleware2]);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(MiddlewareAdapter::class, $result[0]);
        $this->assertInstanceOf(MiddlewareAdapter::class, $result[1]);
        $this->assertNotSame($result[0], $result[1]);
    }

    public function testNormalizeMiddlewareWithInvalidMiddleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware must be callable or implement MiddlewareInterface');

        MiddlewareUtils::normalizeMiddleware(['not-callable-or-middleware']);
    }

    public function testNormalizeMiddlewareWithMultipleInvalidTypes(): void
    {
        $validMiddleware = (static fn(ServerRequestInterface $request, callable $next): ResponseInterface => new Response(200));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware must be callable or implement MiddlewareInterface');

        MiddlewareUtils::normalizeMiddleware([$validMiddleware, 'invalid', 123]);
    }

    public function testNormalizeMiddlewareWithObject(): void
    {
        $invalidObject = new \stdClass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware must be callable or implement MiddlewareInterface');

        MiddlewareUtils::normalizeMiddleware([$invalidObject]);
    }

    public function testNormalizeMiddlewarePreservesOrder(): void
    {
        $middleware1 = (static fn(ServerRequestInterface $request, callable $next): ResponseInterface => $next($request)->withHeader('X-MW1', 'true'));

        $middleware2 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request)->withHeader('X-MW2', 'true');
            }
        };

        $middleware3 = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200, [], 'final'));

        $result = MiddlewareUtils::normalizeMiddleware([$middleware1, $middleware2, $middleware3]);

        $this->assertCount(3, $result);
        $this->assertSame($middleware1, $result[0]);
        $this->assertInstanceOf(MiddlewareAdapter::class, $result[1]);
        $this->assertSame($middleware3, $result[2]);
    }

    public function testNormalizeMiddlewareWithCallableObject(): void
    {
        $callableObject = new class {
            public function __invoke(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return new Response(200, [], 'callable object');
            }
        };

        $result = MiddlewareUtils::normalizeMiddleware([$callableObject]);

        $this->assertCount(1, $result);
        $this->assertSame($callableObject, $result[0]);
        $this->assertTrue(\is_callable($result[0]));
    }

    public function testNormalizeMiddlewareWithMethodCallback(): void
    {
        $service = new class {
            public function middleware(ServerRequestInterface $request, callable $next): ResponseInterface
            {
                return new Response(200, [], 'method callback');
            }
        };

        $callback = $service->middleware(...);
        $result = MiddlewareUtils::normalizeMiddleware([$callback]);

        $this->assertCount(1, $result);
        $this->assertSame($callback, $result[0]);
        $this->assertTrue(\is_callable($result[0]));
    }

    public function testNormalizeMiddlewareWithStaticCallback(): void
    {
        $callback = [self::class, 'staticMiddleware'];
        $result = MiddlewareUtils::normalizeMiddleware([$callback]);

        $this->assertCount(1, $result);
        $this->assertSame($callback, $result[0]);
        $this->assertTrue(\is_callable($result[0]));
    }

    public function testNormalizeMiddlewareIntegrationTest(): void
    {
        $reactMiddleware = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
            $response = $next($request);
            return $response->withHeader('X-React', 'processed');
        };

        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request);
                return $response->withHeader('X-PSR15', 'processed');
            }
        };

        $finalHandler = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200, [], 'Success'));

        $normalized = MiddlewareUtils::normalizeMiddleware([$reactMiddleware, $psr15Middleware]);

        // Simulate MiddlewareRunner behavior step by step
        $request = new ServerRequest('GET', '/test');

        // Get the PSR-15 adapter
        $psr15Adapter = $normalized[1];
        $this->assertInstanceOf(MiddlewareAdapter::class, $psr15Adapter);

        // Call first middleware (React) which will call the PSR-15 adapter
        $response = $reactMiddleware($request, static fn($req) =>
            // The PSR-15 adapter should be called with request and next
            $psr15Adapter($req, $finalHandler));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', (string) $response->getBody());
        $this->assertEquals('processed', $response->getHeaderLine('X-React'));
        $this->assertEquals('processed', $response->getHeaderLine('X-PSR15'));
    }
}
