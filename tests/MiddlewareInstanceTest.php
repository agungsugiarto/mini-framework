<?php

use Mini\Framework\Application;
use Mini\Framework\Http\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareInstanceTest extends TestCase
{
    public function test_route_middleware_with_instance_objects()
    {
        $app = new Application;

        // Create middleware instances
        $throttleMiddleware = new TestThrottleMiddleware;
        $corsMiddleware = new TestCorsMiddleware;

        // Register route with middleware instances
        $app->router->get('/test', [
            'middleware' => [$throttleMiddleware, $corsMiddleware],
            function () {
                return 'Hello World';
            },
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/test'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());

        // Check that middleware was executed
        $this->assertTrue($throttleMiddleware->wasExecuted());
        $this->assertTrue($corsMiddleware->wasExecuted());
    }

    public function test_route_middleware_mixed_instances_and_strings()
    {
        $app = new Application;

        // Register middleware alias
        $app->routeMiddleware([
            'auth' => TestAuthMiddleware::class,
        ]);

        // Create middleware instance
        $throttleMiddleware = new TestThrottleMiddleware;

        // Register route with mixed middleware (instance + string)
        $app->router->get('/mixed', [
            'middleware' => [$throttleMiddleware, 'auth'],
            function () {
                return 'Mixed Middleware Test';
            },
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/mixed'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Mixed Middleware Test', $response->getBody()->getContents());

        // Check that instance middleware was executed
        $this->assertTrue($throttleMiddleware->wasExecuted());
    }

    public function test_route_middleware_with_class_names()
    {
        $app = new Application;

        // Register route with class name middleware
        $app->router->get('/class', [
            'middleware' => [TestThrottleMiddleware::class, TestCorsMiddleware::class],
            function () {
                return 'Class Middleware Test';
            },
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/class'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Class Middleware Test', $response->getBody()->getContents());
    }

    public function test_route_middleware_with_parameters_and_instances()
    {
        $app = new Application;

        // Register middleware aliases
        $app->routeMiddleware([
            'throttle' => TestParameterizedMiddleware::class,
        ]);

        // Create middleware instance
        $corsMiddleware = new TestCorsMiddleware;

        // Register route with parameterized string + instance
        $app->router->get('/params', [
            'middleware' => ['throttle:60,1', $corsMiddleware],
            function () {
                return 'Parameterized Test';
            },
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/params'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Parameterized Test', $response->getBody()->getContents());

        // Check that instance middleware was executed
        $this->assertTrue($corsMiddleware->wasExecuted());
    }

    public function test_controller_middleware_with_instances()
    {
        $app = new Application;

        // Register route with controller that has middleware instances
        $app->router->get('/controller', [TestControllerWithInstanceMiddleware::class, 'index']);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/controller'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Controller with instances', $response->getBody()->getContents());
    }

    public function test_user_requested_middleware_syntax_with_controller()
    {
        $app = new Application;

        // Register CORS middleware alias
        $app->routeMiddleware([
            'cors' => CorsMiddleware::class,
        ]);

        // Create the middleware instance as requested by the user
        $throttleMiddleware = new ThrottleRequests;

        // Register route with exact syntax requested by user
        $app->router->get('example', [
            'middleware' => [$throttleMiddleware, 'cors'],
            'uses' => [ExampleController::class, 'index'],
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/example'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Example Controller Response', $response->getBody()->getContents());

        // Check that middleware was executed
        $this->assertTrue($throttleMiddleware->wasExecuted());
    }

    public function test_user_requested_middleware_syntax_with_closure()
    {
        $app = new Application;

        // Register CORS middleware alias
        $app->routeMiddleware([
            'cors' => CorsMiddleware::class,
        ]);

        // Create the middleware instance as requested by the user
        $throttleMiddleware = new ThrottleRequests;

        // Register route with middleware instances and closure
        $app->router->get('example-closure', [
            'middleware' => [$throttleMiddleware, 'cors'],
            function () {
                return 'Closure Response';
            },
        ]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/example-closure'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Closure Response', $response->getBody()->getContents());

        // Check that middleware was executed
        $this->assertTrue($throttleMiddleware->wasExecuted());
    }
}

// Test middleware classes
class TestThrottleMiddleware implements MiddlewareInterface
{
    private bool $executed = false;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->executed = true;

        // Add header to indicate this middleware ran
        $response = $handler->handle($request);

        return $response->withHeader('X-Throttle-Middleware', 'executed');
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

class TestCorsMiddleware implements MiddlewareInterface
{
    private bool $executed = false;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->executed = true;

        // Add CORS headers
        $response = $handler->handle($request);

        return $response->withHeader('X-CORS-Middleware', 'executed')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

class TestAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Simple auth check
        $response = $handler->handle($request);

        return $response->withHeader('X-Auth-Middleware', 'executed');
    }
}

class TestParameterizedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxAttempts = 60,
        private int $decayMinutes = 1
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Add headers showing the parameters were injected
        $response = $handler->handle($request);

        return $response->withHeader('X-Max-Attempts', (string) $this->maxAttempts)
            ->withHeader('X-Decay-Minutes', (string) $this->decayMinutes);
    }
}

class TestControllerWithInstanceMiddleware
{
    private array $middleware = [];

    public function __construct()
    {
        // Add middleware instances in constructor
        $this->middleware['throttle_instance'] = [];
        $this->middleware['cors_instance'] = [];
    }

    public function getMiddlewareForMethod(string $method): array
    {
        return [
            new TestThrottleMiddleware,
            new TestCorsMiddleware,
        ];
    }

    public function index()
    {
        return 'Controller with instances';
    }
}

// Test classes for the user's exact scenario
class ThrottleRequests implements MiddlewareInterface
{
    private bool $executed = false;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->executed = true;

        // Add header to indicate this middleware ran
        $response = $handler->handle($request);

        return $response->withHeader('X-Throttle-Requests', 'executed');
    }

    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

class ExampleController
{
    public function index()
    {
        return 'Example Controller Response';
    }
}

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response->withHeader('X-CORS', 'executed')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }
}
