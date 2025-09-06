<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Transports\Middleware;

use Mcp\Server\Tests\TestCase;
use Mcp\Server\Transports\Middleware\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;

/**
 * Unit tests for RequestHandler
 */
final class RequestHandlerTest extends TestCase
{
    public function testRequestHandlerImplementsPsr15Interface(): void
    {
        $handler = new RequestHandler(static fn() => new Response(200));

        $this->assertInstanceOf(\Psr\Http\Server\RequestHandlerInterface::class, $handler);
    }

    public function testRequestHandlerCallsClosureWithRequest(): void
    {
        $receivedRequest = null;
        $expectedResponse = new Response(200, [], 'Test response');

        $closure = static function (ServerRequestInterface $request) use (&$receivedRequest, $expectedResponse): ResponseInterface {
            $receivedRequest = $request;
            return $expectedResponse;
        };

        $handler = new RequestHandler($closure);
        $originalRequest = new ServerRequest('GET', '/test');

        $response = $handler->handle($originalRequest);

        $this->assertSame($expectedResponse, $response);
        $this->assertSame($originalRequest, $receivedRequest);
    }

    public function testRequestHandlerWithSimpleResponse(): void
    {
        $closure = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(201, ['X-Test' => 'value'], 'Created'));

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('POST', '/api/create');
        $response = $handler->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('value', $response->getHeaderLine('X-Test'));
        $this->assertEquals('Created', (string) $response->getBody());
    }

    public function testRequestHandlerWithRequestAttributes(): void
    {
        $closure = static function (ServerRequestInterface $request): ResponseInterface {
            $value = $request->getAttribute('test-attr', 'default');
            return new Response(200, [], "Attribute: {$value}");
        };

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('GET', '/');
        $request = $request->withAttribute('test-attr', 'custom-value');

        $response = $handler->handle($request);

        $this->assertEquals('Attribute: custom-value', (string) $response->getBody());
    }

    public function testRequestHandlerWithExceptionInClosure(): void
    {
        $closure = static function (ServerRequestInterface $request): ResponseInterface {
            throw new \RuntimeException('Handler error');
        };

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('GET', '/');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler error');

        $handler->handle($request);
    }

    public function testRequestHandlerRejectsPromiseInterface(): void
    {
        $closure = (static fn(ServerRequestInterface $request): PromiseInterface =>
            // Create a resolved promise (this should still trigger the exception)
            \React\Promise\resolve(new Response(200)));

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('GET', '/');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Promise-based handlers cannot be used in PSR-15 context');

        $handler->handle($request);
    }

    public function testRequestHandlerWithDifferentRequestMethods(): void
    {
        $closure = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200, [], "Method: {$request->getMethod()}"));

        $handler = new RequestHandler($closure);

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $request = new ServerRequest($method, '/');
            $response = $handler->handle($request);

            $this->assertEquals("Method: {$method}", (string) $response->getBody());
        }
    }

    public function testRequestHandlerWithComplexRequestProcessing(): void
    {
        $closure = static function (ServerRequestInterface $request): ResponseInterface {
            $data = [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'headers' => \count($request->getHeaders()),
                'body_size' => $request->getBody()->getSize(),
                'attributes' => $request->getAttributes(),
            ];

            return new Response(200, ['Content-Type' => 'application/json'], \json_encode($data));
        };

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('POST', 'https://example.com/api/test?param=value');
        $request = $request
            ->withHeader('Authorization', 'Bearer token')
            ->withHeader('Content-Type', 'application/json')
            ->withAttribute('user_id', 123)
            ->withAttribute('role', 'admin');

        $response = $handler->handle($request);
        $responseData = \json_decode((string) $response->getBody(), true);

        $this->assertEquals('POST', $responseData['method']);
        $this->assertEquals('https://example.com/api/test?param=value', $responseData['uri']);
        $this->assertGreaterThan(0, $responseData['headers']);
        $this->assertEquals(123, $responseData['attributes']['user_id']);
        $this->assertEquals('admin', $responseData['attributes']['role']);
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testRequestHandlerConstructorStoresClosure(): void
    {
        $closure = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200));

        $handler = new RequestHandler($closure);

        // Use reflection to verify the closure is stored
        $reflection = new \ReflectionClass($handler);
        $handlerProperty = $reflection->getProperty('handler');

        $this->assertSame($closure, $handlerProperty->getValue($handler));
    }

    public function testRequestHandlerWithReadonlyProperty(): void
    {
        $closure = (static fn(ServerRequestInterface $request): ResponseInterface => new Response(200));

        $handler = new RequestHandler($closure);

        $reflection = new \ReflectionClass($handler);
        $handlerProperty = $reflection->getProperty('handler');

        $this->assertTrue($handlerProperty->isReadOnly());
    }

    public function testRequestHandlerWithMutlipleInvocations(): void
    {
        $callCount = 0;

        $closure = static function (ServerRequestInterface $request) use (&$callCount): ResponseInterface {
            $callCount++;
            return new Response(200, [], "Call #{$callCount}");
        };

        $handler = new RequestHandler($closure);
        $request = new ServerRequest('GET', '/');

        $response1 = $handler->handle($request);
        $response2 = $handler->handle($request);
        $response3 = $handler->handle($request);

        $this->assertEquals('Call #1', (string) $response1->getBody());
        $this->assertEquals('Call #2', (string) $response2->getBody());
        $this->assertEquals('Call #3', (string) $response3->getBody());
        $this->assertEquals(3, $callCount);
    }

    public function testRequestHandlerWithDifferentResponseTypes(): void
    {
        $responses = [
            new Response(200, [], 'OK'),
            new Response(404, [], 'Not Found'),
            new Response(500, ['X-Error' => 'Internal'], 'Server Error'),
            new Response(201, ['Location' => '/new-resource'], ''),
        ];

        foreach ($responses as $index => $expectedResponse) {
            $closure = (static fn(ServerRequestInterface $request): ResponseInterface => $expectedResponse);

            $handler = new RequestHandler($closure);
            $request = new ServerRequest('GET', "/test{$index}");
            $actualResponse = $handler->handle($request);

            $this->assertSame($expectedResponse, $actualResponse);
        }
    }

    public function testRequestHandlerClosureReceivesCorrectRequestState(): void
    {
        $closure = static function (ServerRequestInterface $request): ResponseInterface {
            // Verify we can access all request properties correctly
            $queryParams = $request->getQueryParams();
            $headers = $request->getHeaders();
            $body = (string) $request->getBody();
            $attributes = $request->getAttributes();

            return new Response(200, [], \json_encode([
                'query_params' => $queryParams,
                'header_count' => \count($headers),
                'body_length' => \strlen($body),
                'attribute_count' => \count($attributes),
            ]));
        };

        $handler = new RequestHandler($closure);

        $request = new ServerRequest('POST', '/test?foo=bar&baz=qux');
        $request = $request
            ->withHeader('X-Test-1', 'value1')
            ->withHeader('X-Test-2', 'value2')
            ->withBody(new \React\Http\Io\BufferedBody('{"test": "data"}'))
            ->withAttribute('attr1', 'val1')
            ->withAttribute('attr2', 'val2');

        $response = $handler->handle($request);
        $data = \json_decode((string) $response->getBody(), true);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $data['query_params']);
        $this->assertGreaterThanOrEqual(2, $data['header_count']); // At least our 2 X-Test headers
        $this->assertEquals(16, $data['body_length']); // Length of '{"test": "data"}'
        $this->assertEquals(2, $data['attribute_count']);
    }
}
