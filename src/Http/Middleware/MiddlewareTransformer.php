<?php

namespace Mini\Framework\Http\Middleware;

use Closure;
use Illuminate\Container\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionClass;

class MiddlewareTransformer
{
    /**
     * Create a new middleware transformer instance.
     */
    public function __construct(protected Container $container) {}

    /**
     * Transform middleware to work with Laravel Pipeline using PSR-15 pattern.
     */
    public function transform(array $middlewares): array
    {
        return array_map(function ($middleware) {
            return function (RequestInterface $request, Closure $next) use ($middleware) {
                return $this->resolveMiddleware($middleware)->process(
                    $request,
                    new PipelineRequestHandler($next)
                );
            };
        }, $middlewares);
    }

    /**
     * Resolve middleware instance from middleware name.
     */
    public function resolveMiddleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        [$class, $parameterString] = array_pad(explode(':', $middleware, 2), 2, null);

        if (! $parameterString) {
            return $this->container->make($class);
        }

        $parameterValues = explode(',', $parameterString);

        $parameterNames = array_map(
            fn ($param) => $param->getName(),
            (new ReflectionClass($class))->getConstructor()?->getParameters() ?? []
        );

        $parameters = array_combine(
            array_slice($parameterNames, 0, count($parameterValues)),
            $parameterValues
        );

        return $this->container->makeWith($class, $parameters);
    }
}
