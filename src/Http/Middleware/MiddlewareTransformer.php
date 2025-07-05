<?php

namespace Mini\Framework\Http\Middleware;

use Illuminate\Container\Container;
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
    public function transform(array $middleware): array
    {
        return array_map(
            fn ($middlewareName) => fn ($request, $next) => $this->resolveMiddleware($middlewareName)->process(
                $request,
                new PipelineRequestHandler($next)
            ),
            $middleware
        );
    }

    /**
     * Resolve middleware instance from middleware name.
     */
    public function resolveMiddleware(string $middlewareName): MiddlewareInterface
    {
        [$class, $parameterString] = array_pad(explode(':', $middlewareName, 2), 2, null);

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
