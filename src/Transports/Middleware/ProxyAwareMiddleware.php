<?php

declare(strict_types=1);

namespace Mcp\Server\Transports\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that modifies incoming requests to reflect proxy-forwarded headers
 * This allows the rest of the application to work with the "real" external URI
 */
final readonly class ProxyAwareMiddleware implements MiddlewareInterface
{
    public function __construct(
        private bool $trustProxy = true,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->trustProxy) {
            return $handler->handle($request);
        }

        $uri = $request->getUri();
        $hasProxyHeaders = false;

        // Get proxy-forwarded scheme (protocol)
        $scheme = $this->getForwardedScheme($request);
        if ($scheme !== null && $scheme !== $uri->getScheme()) {
            $uri = $uri->withScheme($scheme);
            $hasProxyHeaders = true;
        }

        // Get proxy-forwarded host
        $host = $this->getForwardedHost($request);
        if ($host !== null && $host !== $uri->getHost()) {
            $uri = $uri->withHost($host);
            $hasProxyHeaders = true;
        }

        // Get proxy-forwarded port
        $port = $this->getForwardedPort($request);
        if ($port !== null) {
            // Only set port if it's not the default for the scheme
            if (!$this->isDefaultPort($uri->getScheme(), $port)) {
                $uri = $uri->withPort($port);
            } else {
                $uri = $uri->withPort(null);
            }
            $hasProxyHeaders = true;
        }

        // Only create a new request if we actually found proxy headers
        if ($hasProxyHeaders) {
            $request = $request->withUri($uri);
        }

        return $handler->handle($request);
    }

    /**
     * Get the forwarded scheme from proxy headers
     */
    private function getForwardedScheme(ServerRequestInterface $request): ?string
    {
        // Check X-Forwarded-Proto header (most common)
        $proto = $request->getHeaderLine('X-Forwarded-Proto');
        if (!empty($proto)) {
            return \strtolower(\trim(\explode(',', $proto)[0]));
        }

        // Check X-Forwarded-Ssl header
        $ssl = $request->getHeaderLine('X-Forwarded-Ssl');
        if (\strtolower(\trim($ssl)) === 'on') {
            return 'https';
        }

        // Check X-Forwarded-Scheme header
        $scheme = $request->getHeaderLine('X-Forwarded-Scheme');
        if (!empty($scheme)) {
            return \strtolower(\trim($scheme));
        }

        return null;
    }

    /**
     * Get the forwarded host from proxy headers
     */
    private function getForwardedHost(ServerRequestInterface $request): ?string
    {
        // Check X-Forwarded-Host header
        $host = $request->getHeaderLine('X-Forwarded-Host');
        if (!empty($host)) {
            // Take the first host if multiple are specified
            return \trim(\explode(',', $host)[0]);
        }

        return null;
    }

    /**
     * Get the forwarded port from proxy headers
     */
    private function getForwardedPort(ServerRequestInterface $request): ?int
    {
        // Check X-Forwarded-Port header
        $port = $request->getHeaderLine('X-Forwarded-Port');
        if (!empty($port)) {
            $portValue = \trim(\explode(',', $port)[0]);
            if (\is_numeric($portValue)) {
                return (int) $portValue;
            }
        }

        return null;
    }

    /**
     * Check if the port is the default for the given scheme
     */
    private function isDefaultPort(string $scheme, int $port): bool
    {
        return match (\strtolower($scheme)) {
            'http' => $port === 80,
            'https' => $port === 443,
            default => false,
        };
    }
}
