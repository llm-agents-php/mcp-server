<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Transports\Middleware;

use Mcp\Server\Tests\TestCase;
use Mcp\Server\Transports\Middleware\MiddlewareAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

/**
 * Unit tests for MiddlewareAdapter
 */
final class MiddlewareAdapterTest extends TestCase
{
    public function testMiddlewareAdapterImplementsCallableInterface(): void
    {
        $psr15Middleware = $this->createMock(MiddlewareInterface::class);
        $adapter = new MiddlewareAdapter($psr15Middleware);

        $this->assertTrue(\is_callable($adapter));
    }

    public function testMiddlewareAdapterConvertsToReactFormat(): void
    {
        $expectedResponse = new Response(200, [], 'PSR-15 response');

        $psr15Middleware = new readonly class($expectedResponse) implements MiddlewareInterface {
            public function __construct(
                private ResponseInterface $response,
            ) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $request = new ServerRequest('GET', '/test');

        $nextCalled = false;
        $next = static function () use (&$nextCalled) {
            $nextCalled = true;
            return new Response(404, [], 'Not found');
        };

        $response = $adapter($request, $next);

        $this->assertSame($expectedResponse, $response);
        $this->assertFalse($nextCalled);
    }

    public function testMiddlewareAdapterPassesRequestToHandler(): void
    {
        $originalRequest = new ServerRequest('POST', '/api/test');
        $originalRequest = $originalRequest->withHeader('X-Test', 'value');

        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                // Verify the request is passed correctly
                return new Response(200, [], \json_encode([
                    'method' => $request->getMethod(),
                    'uri' => (string) $request->getUri(),
                    'header' => $request->getHeaderLine('X-Test'),
                ]));
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $next = (static fn() => new Response(500, [], 'Should not be called'));

        $response = $adapter($originalRequest, $next);
        $body = \json_decode((string) $response->getBody(), true);

        $this->assertEquals('POST', $body['method']);
        $this->assertEquals('/api/test', $body['uri']);
        $this->assertEquals('value', $body['header']);
    }

    public function testMiddlewareAdapterWithChaining(): void
    {
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $handler->handle($request->withAttribute('middleware', 'processed'));
                return $response->withHeader('X-Middleware', 'applied');
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $request = new ServerRequest('GET', '/');

        $next = static function (ServerRequestInterface $request): ResponseInterface {
            $processed = $request->getAttribute('middleware', 'not-processed');
            return new Response(200, [], "Request was: {$processed}");
        };

        $response = $adapter($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Request was: processed', (string) $response->getBody());
        $this->assertEquals('applied', $response->getHeaderLine('X-Middleware'));
    }

    public function testMiddlewareAdapterWithExceptionInPsr15Middleware(): void
    {
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw new \LogicException('PSR-15 middleware error');
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $request = new ServerRequest('GET', '/');
        $next = (static fn() => new Response(200));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PSR-15 middleware error');

        $adapter($request, $next);
    }

    public function testMiddlewareAdapterRequestHandlerReceivesCorrectClosure(): void
    {
        $nextCallCount = 0;
        $receivedRequests = [];

        $psr15Middleware = new class($nextCallCount, $receivedRequests) implements MiddlewareInterface {
            public function __construct(
                private int &$nextCallCount,
                private array &$receivedRequests,
            ) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                // Call handler multiple times to test closure behavior
                $this->receivedRequests[] = $request;
                $response1 = $handler->handle($request);
                $response2 = $handler->handle($request->withAttribute('second-call', true));

                return $response2;
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $request = new ServerRequest('GET', '/');

        $next = static function (ServerRequestInterface $req) use (&$nextCallCount): ResponseInterface {
            $nextCallCount++;
            $secondCall = $req->getAttribute('second-call', false);
            return new Response(200, [], $secondCall ? 'second' : 'first');
        };

        $response = $adapter($request, $next);

        $this->assertEquals(2, $nextCallCount);
        $this->assertEquals('second', (string) $response->getBody());
        $this->assertCount(1, $receivedRequests);
    }

    public function testMiddlewareAdapterConstructorStoresMiddleware(): void
    {
        $psr15Middleware = $this->createMock(MiddlewareInterface::class);
        $adapter = new MiddlewareAdapter($psr15Middleware);

        // Use reflection to verify the middleware is stored
        $reflection = new \ReflectionClass($adapter);
        $middlewareProperty = $reflection->getProperty('middleware');

        $this->assertSame($psr15Middleware, $middlewareProperty->getValue($adapter));
    }

    public function testMiddlewareAdapterWithReadonlyProperties(): void
    {
        // Test that the readonly property constraint is respected
        $psr15Middleware = $this->createMock(MiddlewareInterface::class);
        $adapter = new MiddlewareAdapter($psr15Middleware);

        $reflection = new \ReflectionClass($adapter);
        $middlewareProperty = $reflection->getProperty('middleware');

        $this->assertTrue($middlewareProperty->isReadOnly());
    }

    public function testMiddlewareAdapterHandlesComplexRequestModifications(): void
    {
        $psr15Middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                // Apply multiple request modifications
                $modifiedRequest = $request
                    ->withAttribute('processed_by', 'psr15-middleware')
                    ->withAttribute('timestamp', \time())
                    ->withHeader('X-Processing', 'true')
                    ->withMethod('POST');

                $response = $handler->handle($modifiedRequest);

                return $response
                    ->withHeader('X-Response-Modified', 'true')
                    ->withStatus(201);
            }
        };

        $adapter = new MiddlewareAdapter($psr15Middleware);
        $originalRequest = new ServerRequest('GET', '/original');

        $next = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200, [], \json_encode([
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'processed_by' => $request->getAttribute('processed_by'),
            'has_timestamp' => $request->getAttribute('timestamp') !== null,
            'processing_header' => $request->getHeaderLine('X-Processing'),
        ])));

        $response = $adapter($originalRequest, $next);
        $body = \json_decode((string) $response->getBody(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('true', $response->getHeaderLine('X-Response-Modified'));
        $this->assertEquals('POST', $body['method']);
        $this->assertEquals('/original', $body['uri']);
        $this->assertEquals('psr15-middleware', $body['processed_by']);
        $this->assertTrue($body['has_timestamp']);
        $this->assertEquals('true', $body['processing_header']);
    }
}
