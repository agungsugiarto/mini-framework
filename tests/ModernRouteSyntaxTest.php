<?php

namespace Tests\Routing;

use Mini\Framework\Application;
use Mini\Framework\Testing\TestCase;

/**
 * Test class to verify modern route syntax functionality
 */
class ModernRouteSyntaxTest extends TestCase
{
    /**
     * Create the application instance for testing.
     *
     * @return \Mini\Framework\Application
     */
    public function createApplication()
    {
        return new Application;
    }
    /** @test */
    public function it_supports_basic_array_syntax()
    {
        $app = new Application;

        // Test basic [class, method] syntax
        $app->router->get('/users', [TestController::class, 'index']);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/users', $routes);
        $this->assertEquals(TestController::class.'@index', $routes['GET/users']['action']['uses']);
    }

    /** @test */
    public function it_supports_array_syntax_with_middleware()
    {
        $app = new Application;

        // Test array syntax with middleware
        $app->router->get('/admin', [
            'middleware' => 'auth|admin',
            'uses' => [TestController::class, 'admin']
        ]);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/admin', $routes);
        $this->assertEquals(TestController::class.'@admin', $routes['GET/admin']['action']['uses']);
        $this->assertEquals(['auth', 'admin'], $routes['GET/admin']['action']['middleware']);
    }

    /** @test */
    public function it_supports_array_syntax_with_named_routes()
    {
        $app = new Application;

        // Test named routes with array syntax
        $app->router->get('/dashboard', [
            'as' => 'dashboard',
            'uses' => [TestController::class, 'dashboard']
        ]);

        $routes = $app->router->getRoutes();
        $namedRoutes = $app->router->namedRoutes;

        $this->assertArrayHasKey('GET/dashboard', $routes);
        $this->assertEquals(TestController::class.'@dashboard', $routes['GET/dashboard']['action']['uses']);
        $this->assertEquals('/dashboard', $namedRoutes['dashboard']);
    }

    /** @test */
    public function it_supports_array_syntax_in_route_groups()
    {
        $app = new Application;

        // Test array syntax within route groups
        $app->router->group(['prefix' => 'api/v1', 'middleware' => 'api'], function ($router) {
            $router->get('/users', [TestController::class, 'index']);
            $router->post('/users', [TestController::class, 'store']);
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/api/v1/users', $routes);
        $this->assertArrayHasKey('POST/api/v1/users', $routes);
        $this->assertEquals(TestController::class.'@index', $routes['GET/api/v1/users']['action']['uses']);
        $this->assertEquals(TestController::class.'@store', $routes['POST/api/v1/users']['action']['uses']);
        $this->assertEquals(['api'], $routes['GET/api/v1/users']['action']['middleware']);
    }

    /** @test */
    public function it_maintains_backward_compatibility_with_string_syntax()
    {
        $app = new Application;

        // Test that old string syntax still works
        $app->router->get('/old-style', 'TestController@index');

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/old-style', $routes);
        $this->assertEquals('TestController@index', $routes['GET/old-style']['action']['uses']);
    }

    /** @test */
    public function it_supports_invokable_controllers_with_array_syntax()
    {
        $app = new Application;

        // Test invokable controller with array syntax
        $app->router->get('/invokable', [InvokableTestController::class, '__invoke']);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/invokable', $routes);
        $this->assertEquals(InvokableTestController::class.'@__invoke', $routes['GET/invokable']['action']['uses']);
    }
}

/**
 * Test controller for routing tests
 */
class TestController
{
    public function index()
    {
        return ['message' => 'Index method'];
    }

    public function store()
    {
        return ['message' => 'Store method'];
    }

    public function admin()
    {
        return ['message' => 'Admin method'];
    }

    public function dashboard()
    {
        return ['message' => 'Dashboard method'];
    }
}

/**
 * Invokable test controller
 */
class InvokableTestController
{
    public function __invoke()
    {
        return ['message' => 'Invokable controller'];
    }
}
