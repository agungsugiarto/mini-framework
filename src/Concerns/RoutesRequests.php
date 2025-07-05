<?php

namespace Mini\Framework\Concerns;

use ArrayObject;
use Closure;
use FastRoute\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use JsonSerializable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mini\Framework\Exceptions\HttpResponseException;
use Mini\Framework\Exceptions\MethodNotAllowedHttpException;
use Mini\Framework\Exceptions\NotFoundHttpException;
use Mini\Framework\Http\ServerRequest;
use Mini\Framework\Http\ServerRequestFactory;
use Mini\Framework\Routing\Controller;
use Mini\Framework\Routing\Pipeline;
use Mini\Framework\Routing\RoutingClosure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Throwable;

trait RoutesRequests
{
    /**
     * All of the global middleware for the application.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * All of the route specific middleware short-hands.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * The current route being dispatched.
     *
     * @var array
     */
    protected $currentRoute;

    /**
     * The FastRoute dispatcher.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Add new middleware to the application.
     *
     * @param Closure|array $middleware
     *
     * @return $this
     */
    public function middleware($middleware)
    {
        if (! is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = array_unique(array_merge($this->middleware, $middleware));

        return $this;
    }

    /**
     * Define the route middleware for the application.
     *
     * @return $this
     */
    public function routeMiddleware(array $middleware)
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);

        return $this;
    }

    /**
     * Run the application and send the response.
     *
     * @return void
     */
    public function run(?ServerRequestInterface $request = null)
    {
        $request ??= ServerRequestFactory::fromGlobals();

        (new SapiEmitter)->emit($response = $this->handle($request));

        if (count($this->middleware) > 0) {
            $this->callTerminableMiddleware($response);
        }

        $this->app->terminate();
    }

    /**
     * Call the terminable middleware.
     *
     * @param mixed $response
     *
     * @return void
     */
    protected function callTerminableMiddleware($response)
    {
        if ($this->shouldSkipMiddleware()) {
            return;
        }

        $response = $this->prepareResponse($response);

        foreach ($this->middleware as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $instance = $this->resolveMiddleware($middleware);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($this->make('request'), $response);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        [$method, $pathInfo] = $this->parseIncomingRequest($request);

        try {
            $this->boot();

            return $this->sendThroughPipeline($this->middleware, function (ServerRequestInterface $request) use ($method, $pathInfo) {
                $this->instance(RequestInterface::class, $request);
                $this->instance(ServerRequestInterface::class, $request);
                $this->instance(ServerRequest::class, $request);

                if (isset($this->router->getRoutes()[$method.$pathInfo])) {
                    return $this->handleFoundRoute([true, $this->router->getRoutes()[$method.$pathInfo]['action'], []]);
                }

                return $this->handleDispatcherResponse(
                    $this->createDispatcher()->dispatch($method, $pathInfo)
                );
            });
        } catch (Throwable $e) {
            return $this->prepareResponse($this->sendExceptionToHandler($e));
        }
    }

    /**
     * Parse the incoming request and return the method and path info.
     *
     * @return array
     */
    protected function parseIncomingRequest(ServerRequestInterface $request)
    {
        $this->instance(RequestInterface::class, $request);
        $this->instance(ServerRequestInterface::class, $request);
        $this->instance(ServerRequest::class, $request);

        return [$request->getMethod(), '/'.trim($request->getUri()->getPath(), '/')];
    }

    /**
     * Create a FastRoute dispatcher instance for the application.
     *
     * @return Dispatcher
     */
    protected function createDispatcher()
    {
        return $this->dispatcher ?: \FastRoute\simpleDispatcher(function ($r) {
            foreach ($this->router->getRoutes() as $route) {
                $r->addRoute($route['method'], $route['uri'], $route['action']);
            }
        });
    }

    /**
     * Set the FastRoute dispatcher instance.
     *
     * @return void
     */
    public function setDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Handle the response from the FastRoute dispatcher.
     *
     * @param array $routeInfo
     *
     * @return mixed
     */
    protected function handleDispatcherResponse($routeInfo)
    {
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NotFoundHttpException;
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new MethodNotAllowedHttpException($routeInfo[1]);
            case Dispatcher::FOUND:
                return $this->handleFoundRoute($routeInfo);
        }
    }

    /**
     * Handle a route found by the dispatcher.
     *
     * @param array $routeInfo
     *
     * @return mixed
     */
    protected function handleFoundRoute($routeInfo)
    {
        $this->currentRoute = $routeInfo;

        $this['request']->setRouteResolver(function () {
            return $this->currentRoute;
        });

        $this['request']->withAttribute('route', $this['request']->route());

        $action = $routeInfo[1];

        // Pipe through route middleware...
        if (isset($action['middleware'])) {
            $middleware = $this->gatherMiddlewareClassNames($action['middleware']);

            return $this->prepareResponse($this->sendThroughPipeline($middleware, function () {
                return $this->callActionOnArrayBasedRoute($this['request']->route());
            }));
        }

        return $this->prepareResponse(
            $this->callActionOnArrayBasedRoute($routeInfo)
        );
    }

    /**
     * Call the Closure or invokable on the array based route.
     *
     * @param array $routeInfo
     *
     * @return mixed
     */
    protected function callActionOnArrayBasedRoute($routeInfo)
    {
        $action = $routeInfo[1];

        if (isset($action['uses'])) {
            return $this->prepareResponse($this->callControllerAction($routeInfo));
        }

        foreach ($action as $value) {
            if ($value instanceof Closure) {
                $callable = $value->bindTo(new RoutingClosure);
                break;
            }

            if (is_object($value) && is_callable($value)) {
                $callable = $value;
                break;
            }
        }

        if (! isset($callable)) {
            throw new RuntimeException('Unable to resolve route handler.');
        }

        try {
            return $this->prepareResponse($this->call($callable, $routeInfo[2]));
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Call a controller based route.
     *
     * @param array $routeInfo
     *
     * @return mixed
     */
    protected function callControllerAction($routeInfo)
    {
        $uses = $routeInfo[1]['uses'];

        if (is_string($uses) && ! Str::contains($uses, '@')) {
            $uses .= '@__invoke';
        }

        [$controller, $method] = explode('@', $uses);

        if (! method_exists($instance = $this->make($controller), $method)) {
            throw new NotFoundHttpException;
        }

        if ($instance instanceof Controller) {
            return $this->callController($instance, $method, $routeInfo);
        } else {
            return $this->callControllerCallable(
                [$instance, $method], $routeInfo[2]
            );
        }
    }

    /**
     * Send the request through a controller.
     *
     * @param mixed  $instance
     * @param string $method
     * @param array  $routeInfo
     *
     * @return mixed
     */
    protected function callController($instance, $method, $routeInfo)
    {
        $middleware = $instance->getMiddlewareForMethod($method);

        if (count($middleware) > 0) {
            return $this->callControllerWithMiddleware(
                $instance, $method, $routeInfo, $middleware
            );
        } else {
            return $this->callControllerCallable(
                [$instance, $method], $routeInfo[2]
            );
        }
    }

    /**
     * Send the request through a set of controller middleware.
     *
     * @param mixed  $instance
     * @param string $method
     * @param array  $routeInfo
     * @param array  $middleware
     *
     * @return mixed
     */
    protected function callControllerWithMiddleware($instance, $method, $routeInfo, $middleware)
    {
        $middleware = $this->gatherMiddlewareClassNames($middleware);

        return $this->sendThroughPipeline($middleware, function () use ($instance, $method, $routeInfo) {
            return $this->callControllerCallable([$instance, $method], $routeInfo[2]);
        });
    }

    /**
     * Call a controller callable and return the response.
     *
     * @return ResponseInterface
     */
    protected function callControllerCallable(callable $callable, array $parameters = [])
    {
        try {
            return $this->prepareResponse(
                $this->call($callable, $parameters)
            );
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Gather the full class names for the middleware short-cut string.
     *
     * @param string|array $middleware
     *
     * @return array
     */
    protected function gatherMiddlewareClassNames($middleware)
    {
        $middleware = is_string($middleware) ? explode('|', $middleware) : (array) $middleware;

        return array_map(function ($name) {
            [$name, $parameters] = array_pad(explode(':', $name, 2), 2, null);

            return Arr::get($this->routeMiddleware, $name, $name).($parameters ? ':'.$parameters : '');
        }, $middleware);
    }

    /**
     * Send the request through the pipeline with the given callback.
     *
     * @return ResponseInterface
     */
    protected function sendThroughPipeline(array $middleware, Closure $then)
    {
        if (count($middleware) > 0 && ! $this->shouldSkipMiddleware()) {
            $request = $this->make('request');

            // Transform middleware to work with Laravel Pipeline using PSR-15 pattern
            $transformedMiddleware = array_map(fn ($middlewareName) => fn ($request, $next) => $this->resolveMiddleware($middlewareName)->process(
                $request,
                new class($next) implements RequestHandlerInterface
                {
                    public function __construct(private $next)
                    {
                    }

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return ($this->next)($request);
                    }
                }
            ),
                $middleware
            );

            return (new Pipeline($this))
                ->send($request)
                ->through($transformedMiddleware)
                ->then(fn (ServerRequestInterface $request): ResponseInterface => $then($request));
        }

        return $then($this->make('request'));
    }

    /**
     * Prepare the response for sending.
     *
     * @param mixed|ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function prepareResponse($response)
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if ($response instanceof Model) {
            return new JsonResponse($response, 201);
        }

        if ($response instanceof Stringable) {
            return (new ResponseFactory)->createResponse()
                ->withBody((new StreamFactory)->createStream((string) $response));
        }

        if (
            $response instanceof Arrayable ||
            $response instanceof Jsonable ||
            $response instanceof ArrayObject ||
            $response instanceof JsonSerializable ||
            $response instanceof stdClass ||
            is_array($response)
        ) {
            return new JsonResponse($response);
        }

        if (empty($response)) {
            return new EmptyResponse();
        }

        return (new ResponseFactory)->createResponse()
            ->withBody((new StreamFactory)->createStream((string) $response));
    }

    /**
     * Determines whether middleware should be skipped during request.
     *
     * @return bool
     */
    protected function shouldSkipMiddleware()
    {
        return $this->bound('middleware.disable') && $this->make('middleware.disable') === true;
    }

    /**
     * Resolve middleware instance from middleware name.
     *
     * @return object
     */
    protected function resolveMiddleware(string $middlewareName)
    {
        [$class, $parameterString] = array_pad(explode(':', $middlewareName, 2), 2, null);

        if (! $parameterString) {
            return $this->make($class);
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

        return $this->makeWith($class, $parameters);
    }
}
