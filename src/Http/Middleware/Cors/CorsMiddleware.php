<?php

namespace Mini\Framework\Http\Middleware\Cors;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /** @var CorsService */
    protected $cors;

    public function __construct(CorsService $cors)
    {
        $this->cors = $cors;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if we're dealing with CORS and if we should handle it
        if (! $this->isMatchingPath($request)) {
            return $handler->handle($request);
        }

        // For Preflight, return the Preflight response
        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);
            $response = $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        /** @var ResponseInterface Handle the request */
        $response = $handler->handle($request);

        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

        return $this->addHeaders($request, $response);
    }

    /**
     * Add the headers to the Response, if they don't exist yet.
     */
    protected function addHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (! $response->hasHeader('Access-Control-Allow-Origin')) {
            // Add the CORS headers to the Response
            $response = $this->cors->addActualRequestHeaders($response, $request);
        }

        return $response;
    }

    /**
     * The the path from the config, to see if the CORS Service should run.
     */
    protected function isMatchingPath(ServerRequestInterface $request): bool
    {
        // Get the paths from the config or the middleware
        $paths = $this->getPathsByHost($request->getUri()->getHost());

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($this->requestIs($request, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paths by given host or string values in config by default.
     *
     * @return array
     */
    protected function getPathsByHost(string $host)
    {
        $paths = config('cors.paths', []);
        // If where are paths by given host
        if (isset($paths[$host])) {
            return $paths[$host];
        }

        // Defaults
        return array_filter($paths, function ($path) {
            return is_string($path);
        });
    }

    protected function requestIs(ServerRequestInterface $request, ...$patterns)
    {
        $path = rawurldecode($request->getUri()->getPath());

        return collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $path));
    }
}
