<?php

namespace Mini\Framework\Http;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Laminas\Diactoros\Exception\InvalidArgumentException;
use Laminas\Diactoros\ServerRequest as BaseServerRequest;
use Mini\Framework\Http\Concerns\InteractsWithContentTypes;
use Mini\Framework\Http\Concerns\InteractsWithFlashData;
use Mini\Framework\Http\Concerns\InteractsWithInput;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest extends BaseServerRequest
{
    use InteractsWithContentTypes;
    use InteractsWithFlashData;
    use InteractsWithInput;
    use Macroable;

    /**
     * The route resolver callback.
     *
     * @var Closure
     */
    protected $routeResolver;

    /**
     * @param  array  $serverParams  Server parameters, typically from $_SERVER
     * @param  array  $uploadedFiles  Upload file information, a tree of UploadedFiles
     * @param  string|UriInterface|null  $uri  URI for the request, if any
     * @param  string|null  $method  HTTP method for the request, if any
     * @param  string|resource|StreamInterface  $body  message body, if any
     * @param  array  $headers  headers for the message, if any
     * @param  array  $cookieParams  cookies for the message, if any
     * @param  array  $queryParams  query params for the message, if any
     * @param  array|object|null  $parsedBody  the deserialized body parameters, if any
     * @param  string  $protocol  HTTP protocol version
     *
     * @throws InvalidArgumentException for any invalid value
     */
    public function __construct(
        private array $serverParams = [],
        array $uploadedFiles = [],
        $uri = null,
        ?string $method = null,
        $body = 'php://input',
        array $headers = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private $parsedBody = null,
        string $protocol = '1.1'
    ) {
        parent::__construct(
            $serverParams,
            $uploadedFiles,
            $uri,
            $method,
            $body,
            $headers,
            $cookieParams,
            $queryParams,
            $parsedBody,
            $protocol
        );
    }

    /**
     * Determine if the route name matches a given pattern.
     *
     * @param  mixed  $patterns
     * @return bool
     */
    public function routeIs(...$patterns)
    {
        if (! Arr::exists($route = $this->route()[1], 'as')) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $route['as'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the route handling the request.
     *
     * @param  string|null  $param
     * @param  mixed  $default
     * @return array|string
     */
    public function route($param = null, $default = null)
    {
        $route = ($this->getRouteResolver())();

        if (is_null($route) || is_null($param)) {
            return $route;
        }

        return Arr::get($route[2], $param, $default);
    }

    /**
     * Get the route resolver callback.
     *
     * @return Closure
     */
    public function getRouteResolver()
    {
        return $this->routeResolver ?: function () {
            //
        };
    }

    /**
     * Set the route resolver callback.
     *
     * @return $this
     */
    public function setRouteResolver(Closure $callback)
    {
        $this->routeResolver = $callback;

        return $this;
    }
}
