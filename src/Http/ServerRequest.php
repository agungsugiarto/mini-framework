<?php

namespace Mini\Framework\Http;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Laminas\Diactoros\ServerRequest as BaseServerRequest;
use Mini\Framework\Http\Concerns\InteractsWithContentTypes;
use Mini\Framework\Http\Concerns\InteractsWithFlashData;
use Mini\Framework\Http\Concerns\InteractsWithInput;

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
