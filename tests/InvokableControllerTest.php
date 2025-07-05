<?php

namespace Tests\Routing;

use Mini\Framework\Application;
use Mini\Framework\Testing\TestCase;

/**
 * Test class to verify invokable controller functionality
 */
class InvokableControllerTest extends TestCase
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
    public function it_supports_string_invokable_controller()
    {
        $app = new Application;

        // Test string invokable controller
        $app->router->get('/search', SearchController::class);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/search', $routes);
        $this->assertEquals(SearchController::class.'@__invoke', $routes['GET/search']['action']['uses']);
    }

    /** @test */
    public function it_supports_array_invokable_controller_single_element()
    {
        $app = new Application;

        // Test single element array [ClassName]
        $app->router->get('/export', [ExportController::class]);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/export', $routes);
        $this->assertEquals(ExportController::class.'@__invoke', $routes['GET/export']['action']['uses']);
    }

    /** @test */
    public function it_supports_explicit_invoke_method()
    {
        $app = new Application;

        // Test explicit __invoke method
        $app->router->get('/report', [ReportController::class, '__invoke']);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/report', $routes);
        $this->assertEquals(ReportController::class.'@__invoke', $routes['GET/report']['action']['uses']);
    }

    /** @test */
    public function it_supports_invokable_with_middleware()
    {
        $app = new Application;

        // Test invokable with middleware
        $app->router->get('/admin-export', [
            'middleware' => 'auth|admin',
            'uses' => AdminExportController::class,
        ]);

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/admin-export', $routes);
        $this->assertEquals(AdminExportController::class.'@__invoke', $routes['GET/admin-export']['action']['uses']);
        $this->assertEquals(['auth', 'admin'], $routes['GET/admin-export']['action']['middleware']);
    }

    /** @test */
    public function it_supports_invokable_with_named_route()
    {
        $app = new Application;

        // Test invokable with named route
        $app->router->get('/dashboard-export', [
            'as' => 'dashboard.export',
            'middleware' => 'auth',
            'uses' => DashboardExportController::class,
        ]);

        $routes = $app->router->getRoutes();
        $namedRoutes = $app->router->namedRoutes;

        $this->assertArrayHasKey('GET/dashboard-export', $routes);
        $this->assertEquals(DashboardExportController::class.'@__invoke', $routes['GET/dashboard-export']['action']['uses']);
        $this->assertEquals(['auth'], $routes['GET/dashboard-export']['action']['middleware']);
        $this->assertEquals('/dashboard-export', $namedRoutes['dashboard.export']);
    }

    /** @test */
    public function it_supports_invokable_in_route_groups()
    {
        $app = new Application;

        // Test invokable controllers in route groups
        $app->router->group(['prefix' => 'api/v1', 'middleware' => 'api'], function ($router) {
            $router->get('/search', SearchController::class);
            $router->post('/export', [ExportController::class]);
            $router->get('/report', [ReportController::class, '__invoke']);
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/api/v1/search', $routes);
        $this->assertArrayHasKey('POST/api/v1/export', $routes);
        $this->assertArrayHasKey('GET/api/v1/report', $routes);

        $this->assertEquals(SearchController::class.'@__invoke', $routes['GET/api/v1/search']['action']['uses']);
        $this->assertEquals(ExportController::class.'@__invoke', $routes['POST/api/v1/export']['action']['uses']);
        $this->assertEquals(ReportController::class.'@__invoke', $routes['GET/api/v1/report']['action']['uses']);

        // All should have api middleware from group
        $this->assertEquals(['api'], $routes['GET/api/v1/search']['action']['middleware']);
        $this->assertEquals(['api'], $routes['POST/api/v1/export']['action']['middleware']);
        $this->assertEquals(['api'], $routes['GET/api/v1/report']['action']['middleware']);
    }

    /** @test */
    public function it_maintains_backward_compatibility_with_explicit_method()
    {
        $app = new Application;

        // Test that explicit method syntax still works
        $app->router->get('/users', [UserController::class, 'index']);
        $app->router->get('/old-style', 'UserController@show');

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/users', $routes);
        $this->assertArrayHasKey('GET/old-style', $routes);
        $this->assertEquals(UserController::class.'@index', $routes['GET/users']['action']['uses']);
        $this->assertEquals('UserController@show', $routes['GET/old-style']['action']['uses']);
    }

    /** @test */
    public function it_supports_invokable_with_namespace_groups()
    {
        $app = new Application;

        // Test namespace with invokable controllers
        $app->router->group(['namespace' => 'App\\Controllers'], function ($router) {
            $router->get('/admin', 'AdminController');
            $router->post('/user', [UserController::class, 'store']);
            $router->get('/profile', ProfileController::class);
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/admin', $routes);
        $this->assertArrayHasKey('POST/user', $routes);
        $this->assertArrayHasKey('GET/profile', $routes);

        // Check namespace is correctly applied
        $this->assertEquals('App\\Controllers\\AdminController@__invoke', $routes['GET/admin']['action']['uses']);
        $this->assertEquals(UserController::class.'@store', $routes['POST/user']['action']['uses']); // Class constant should not get namespace

        // For invokable with full class name, namespace should not interfere
        $this->assertEquals(ProfileController::class.'@__invoke', $routes['GET/profile']['action']['uses']);
    }

    /** @test */
    public function it_handles_namespace_with_invokable_and_middleware()
    {
        $app = new Application;

        // Test complex namespace + middleware + invokable
        $app->router->group([
            'namespace' => 'App\\Admin',
            'prefix' => 'admin',
            'middleware' => 'auth|admin',
        ], function ($router) {
            $router->get('/dashboard', DashboardController::class);
            $router->get('/reports', [
                'middleware' => 'permission:view-reports',
                'uses' => ReportsController::class,
            ]);
            $router->post('/export', [
                'as' => 'admin.export',
                'middleware' => 'export',
                'uses' => ExportController::class,
            ]);
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/admin/dashboard', $routes);
        $this->assertArrayHasKey('GET/admin/reports', $routes);
        $this->assertArrayHasKey('POST/admin/export', $routes);

        // Check invokable detection with namespace
        $this->assertEquals(DashboardController::class.'@__invoke', $routes['GET/admin/dashboard']['action']['uses']);
        $this->assertEquals(ReportsController::class.'@__invoke', $routes['GET/admin/reports']['action']['uses']);
        $this->assertEquals(ExportController::class.'@__invoke', $routes['POST/admin/export']['action']['uses']);

        // Check middleware inheritance
        $this->assertEquals(['auth', 'admin'], $routes['GET/admin/dashboard']['action']['middleware']);
        $this->assertEquals(['auth', 'admin', 'permission:view-reports'], $routes['GET/admin/reports']['action']['middleware']);
        $this->assertEquals(['auth', 'admin', 'export'], $routes['POST/admin/export']['action']['middleware']);
    }

    /** @test */
    public function it_distinguishes_between_string_and_class_names_in_namespace()
    {
        $app = new Application;

        // Test that string controller names get namespace, but class constants don't
        $app->router->group(['namespace' => 'App\\Controllers'], function ($router) {
            // String should get namespace
            $router->get('/string-controller', 'StringController');

            // Class constant should NOT get namespace (already fully qualified)
            $router->get('/class-controller', FullyQualifiedController::class);

            // Array with class constant should NOT get namespace
            $router->post('/array-controller', [FullyQualifiedController::class]);

            // String in array should get namespace
            $router->put('/mixed-controller', [
                'middleware' => 'auth',
                'uses' => 'MixedController',
            ]);
        });

        $routes = $app->router->getRoutes();

        // String gets namespace
        $this->assertEquals('App\\Controllers\\StringController@__invoke', $routes['GET/string-controller']['action']['uses']);

        // Class constant doesn't get namespace (already fully qualified)
        $this->assertEquals(FullyQualifiedController::class.'@__invoke', $routes['GET/class-controller']['action']['uses']);
        $this->assertEquals(FullyQualifiedController::class.'@__invoke', $routes['POST/array-controller']['action']['uses']);

        // String in array gets namespace
        $this->assertEquals('App\\Controllers\\MixedController@__invoke', $routes['PUT/mixed-controller']['action']['uses']);
    }

    /** @test */
    public function it_supports_nested_namespace_groups_with_invokable()
    {
        $app = new Application;

        // Test nested namespace groups
        $app->router->group(['namespace' => 'App'], function ($router) {
            $router->group(['namespace' => 'Controllers'], function ($router) {
                $router->group(['namespace' => 'Admin'], function ($router) {
                    $router->get('/dashboard', 'DashboardController');
                    $router->get('/users', AdminUserController::class);
                });
            });
        });

        $routes = $app->router->getRoutes();

        // Nested namespace should work
        $this->assertEquals('App\\Controllers\\Admin\\DashboardController@__invoke', $routes['GET/dashboard']['action']['uses']);

        // Class constant should not get nested namespace
        $this->assertEquals(AdminUserController::class.'@__invoke', $routes['GET/users']['action']['uses']);
    }
}

/**
 * Test controllers for invokable tests
 */
class SearchController
{
    public function __invoke()
    {
        return ['message' => 'Search invoked'];
    }
}

class ExportController
{
    public function __invoke()
    {
        return ['message' => 'Export invoked'];
    }
}

class ReportController
{
    public function __invoke()
    {
        return ['message' => 'Report invoked'];
    }
}

class AdminExportController
{
    public function __invoke()
    {
        return ['message' => 'Admin export invoked'];
    }
}

class DashboardExportController
{
    public function __invoke()
    {
        return ['message' => 'Dashboard export invoked'];
    }
}

class UserController
{
    public function index()
    {
        return ['message' => 'User index'];
    }

    public function show()
    {
        return ['message' => 'User show'];
    }
}

class AdminController
{
    public function __invoke()
    {
        return ['message' => 'Admin invoked'];
    }
}

class ProfileController
{
    public function __invoke()
    {
        return ['message' => 'Profile invoked'];
    }
}

class DashboardController
{
    public function __invoke()
    {
        return ['message' => 'Dashboard invoked'];
    }
}

class ReportsController
{
    public function __invoke()
    {
        return ['message' => 'Reports invoked'];
    }
}

class FullyQualifiedController
{
    public function __invoke()
    {
        return ['message' => 'Fully qualified controller invoked'];
    }
}

class StringController
{
    public function __invoke()
    {
        return ['message' => 'String controller invoked'];
    }
}

class MixedController
{
    public function __invoke()
    {
        return ['message' => 'Mixed controller invoked'];
    }
}

class AdminUserController
{
    public function __invoke()
    {
        return ['message' => 'Admin user controller invoked'];
    }
}
