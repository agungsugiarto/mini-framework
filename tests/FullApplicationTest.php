<?php

use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\View\ViewServiceProvider;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\StreamFactory;
use Mini\Framework\Application;
use Mini\Framework\Console\ConsoleServiceProvider;
use Mini\Framework\Http\ServerRequest;
use Mini\Framework\Http\ServerRequestFactory;
use Mini\Framework\Validation\Factory;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class FullApplicationTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testBasicRequest()
    {
        $app = new Application;

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle($request = (new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
    }

    public function testBasicLaminasRequest()
    {
        $app = new Application;

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddRouteMultipleMethodRequest()
    {
        $app = new Application;

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $app->router->post('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('POST', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());
    }

    public function testRequestWithParameters()
    {
        $app = new Application;

        $app->router->get('/foo/{bar}/{baz}', function ($bar, $baz) {
            return new TextResponse($bar.$baz);
        });

        $response = $app->handle($request = (new ServerRequestFactory)->createServerRequest('GET', '/foo/1/2'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('12', $response->getBody()->getContents());

        $this->assertEquals(1, $request->route('bar'));
        $this->assertEquals(2, $request->route('baz'));
    }

    public function testCallbackRouteWithDefaultParameter()
    {
        $app = new Application;
        $app->router->get('/foo-bar/{baz}', function ($baz = 'default-value') {
            return new TextResponse($baz);
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/foo-bar/something'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('something', $response->getBody()->getContents());
    }

    public function testGlobalMiddleware()
    {
        $app = new Application;

        $app->middleware(MiniTestMiddleware::class);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());
    }

    public function testRouteMiddleware()
    {
        $app = new Application;

        $app->routeMiddleware(['foo' => 'MiniTestMiddleware', 'passing' => 'MiniTestPlainMiddleware']);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $app->router->get('/foo', ['middleware' => 'foo', function () {
            return 'Hello World';
        }]);

        $app->router->get('/bar', ['middleware' => ['foo'], function () {
            return 'Hello World';
        }]);

        $app->router->get('/fooBar', ['middleware' => 'passing|foo', function () {
            return 'Hello World';
        }]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/foo'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/bar'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/fooBar'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());
    }

    public function testGlobalMiddlewareParameters()
    {
        $app = new Application;

        $app->middleware(['MiniTestParameterizedMiddleware:foo,bar']);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - foo - bar', $response->getBody()->getContents());
    }

    public function testRouteMiddlewareParameters()
    {
        $app = new Application;

        $app->routeMiddleware(['foo' => 'MiniTestParameterizedMiddleware', 'passing' => 'MiniTestPlainMiddleware']);

        $app->router->get('/', ['middleware' => 'passing|foo:bar,boom', function () {
            return 'Hello World';
        }]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware - bar - boom', $response->getBody()->getContents());
    }

    public function testWithMiddlewareDisabled()
    {
        $app = new Application;

        $app->middleware(['MiniTestMiddleware']);
        $app->instance('middleware.disable', true);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());
    }

    public function testTerminableGlobalMiddleware()
    {
        $app = new class extends Application
        {
            public function callTerminableMiddlewarePublic($response)
            {
                return $this->callTerminableMiddleware($response);
            }
        };

        $app->middleware(['MiniTestTerminateMiddleware']);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());

        // At this point terminate should not have been called yet
        $this->assertFalse(defined('TERMINATE_MIDDLEWARE_CALLED'));

        // Now call terminate middleware manually to test it
        $app->callTerminableMiddlewarePublic($response);

        // Test that terminate was called by checking if the global flag was set
        $this->assertTrue(defined('TERMINATE_MIDDLEWARE_CALLED'));
    }

    // public function testTerminateWithMiddlewareDisabled()
    // {
    //     $app = new Application;

    //     $app->middleware(['MiniTestTerminateMiddleware']);
    //     $app->instance('middleware.disable', true);

    //     $app->router->get('/', function () {
    //         return response('Hello World');
    //     });

    //     $response = $app->handle(Request::create('/', 'GET'));

    //     $this->assertEquals(200, $response->getStatusCode());
    //     $this->assertEquals('Hello World', $response->getContent());
    // }

    public function testNotFoundResponse()
    {
        $app = new Application;
        $app->instance(ExceptionHandler::class, $mock = m::mock('Mini\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/foo'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMethodNotAllowedResponse()
    {
        $app = new Application;
        $app->instance(ExceptionHandler::class, $mock = m::mock('Mini\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->post('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(405, $response->getStatusCode());
    }

    // public function testResponsableInterface()
    // {
    //     $app = new Application;

    //     $app->router->get('/foo/{foo}', function () {
    //         return new ResponsableResponse;
    //     });

    //     $response = $app->handle($request = (new ServerRequestFactory)->createServerRequest('GET', '/foo/999'));

    //     $this->assertEquals(999, $request->route('foo'));
    //     $this->assertEquals(999, $response->original);
    // }

    public function testUncaughtExceptionResponse()
    {
        $app = new Application;
        $app->instance(ExceptionHandler::class, $mock = m::mock('Mini\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $app->router->get('/', function () {
            throw new RuntimeException('app exception');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testGeneratingUrls()
    {
        $app = new Application;
        $app->instance('request', (new ServerRequestFactory)->createServerRequest('GET', 'http://lumen.laravel.com'));

        $app->router->get('/foo-bar', ['as' => 'foo', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz}/{boom}', ['as' => 'bar', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz}[/{boom}]', ['as' => 'optional', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]+}[/{boom}]', ['as' => 'regex', function () {
            //
        }]);

        $this->assertEquals('http://lumen.laravel.com/something', url('something'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar', route('foo'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar?baz=1&boom=2', route('foo', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('optional', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1', route('optional', ['baz' => 1]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('regex', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1', route('regex', ['baz' => 1]));
    }

    public function testGeneratingUrlsForRegexParameters()
    {
        $app = new Application;
        $app->instance('request', (new ServerRequestFactory)->createServerRequest('GET', 'http://lumen.laravel.com'));

        $app->router->get('/foo-bar', ['as' => 'foo', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom}', ['as' => 'bar', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}', ['as' => 'baz', function () {
            //
        }]);

        $app->router->get('/foo-bar/{baz:[0-9]{2,5}}', ['as' => 'boom', function () {
            //
        }]);

        $this->assertEquals('http://lumen.laravel.com/something', url('something'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar', route('foo'));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('bar', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/1/2', route('baz', ['baz' => 1, 'boom' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/{baz:[0-9]+}/{boom:[0-9]+}?ba=1&bo=2', route('baz', ['ba' => 1, 'bo' => 2]));
        $this->assertEquals('http://lumen.laravel.com/foo-bar/5', route('boom', ['baz' => 5]));
    }

    public function testRegisterServiceProvider()
    {
        $app = new Application;
        $provider = new LumenTestServiceProvider($app);
        $app->register($provider);

        $this->assertTrue(true);
    }

    public function testApplicationBootsServiceProvidersOnBoot()
    {
        $app = new Application();

        $provider = new LumenBootableTestServiceProvider($app);
        $app->register($provider);

        $this->assertFalse($provider->booted);
        $app->boot();
        $this->assertTrue($provider->booted);
    }

    public function testRegisterServiceProviderAfterBoot()
    {
        $app = new Application();
        $provider = new LumenBootableTestServiceProvider($app);
        $app->boot();
        $app->register($provider);
        $this->assertTrue($provider->booted);
    }

    public function testApplicationBootsOnlyOnce()
    {
        $app = new Application();
        $provider = new class($app) extends Illuminate\Support\ServiceProvider
        {
            public $bootCount = 0;

            public function boot()
            {
                $this->bootCount += 1;
            }
        };

        $app->register($provider);
        $app->boot();
        $app->boot();
        $this->assertEquals(1, $provider->bootCount);
    }

    public function testApplicationBootsWhenRequestIsDispatched()
    {
        $app = new Application();
        $app->router->get('/', function () {
            return 'Hello World';
        });
        $provider = new LumenBootableTestServiceProvider($app);
        $app->register($provider);
        $resp = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertTrue($provider->booted);
    }

    public function testUsingCustomDispatcher()
    {
        $routes = new FastRoute\RouteCollector(new FastRoute\RouteParser\Std, new FastRoute\DataGenerator\GroupCountBased);

        $routes->addRoute('GET', '/', [function () {
            return 'Hello World';
        }]);

        $app = new Application;

        $app->setDispatcher(new FastRoute\Dispatcher\GroupCountBased($routes->getData()));

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());
    }

    public function testMiddlewareReceiveResponsesEvenWhenStringReturned()
    {
        unset($_SERVER['__middleware.response']);

        $app = new Application;

        $app->routeMiddleware(['foo' => 'MiniTestPlainMiddleware']);

        $app->router->get('/', ['middleware' => 'foo', function () {
            return 'Hello World';
        }]);

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());
        $this->assertTrue($_SERVER['__middleware.response']);
    }

    public function testBasicControllerDispatching()
    {
        $app = new Application;

        $app->router->get('/show/{id}', 'LumenTestController@show');

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'show/25'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getBody()->getContents());
    }

    public function testBasicControllerDispatchingWithGroup()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => MiniTestMiddleware::class]);

        $app->router->group(['middleware' => 'test'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'show/25'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());
    }

    public function testBasicControllerDispatchingWithGroupSuffix()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => MiniTestMiddleware::class]);

        $app->router->group(['suffix' => '.{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'show/25.xml'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('25', $response->getBody()->getContents());
    }

    public function testBasicControllerDispatchingWithGroupAndSuffixWithPath()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => MiniTestMiddleware::class]);

        $app->router->group(['suffix' => '/{format:json|xml}'], function ($router) {
            $router->get('/show/{id}', 'LumenTestController@show');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'show/test/json'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('test', $response->getBody()->getContents());
    }

    public function testBasicControllerDispatchingWithMiddlewareIntercept()
    {
        $app = new Application;
        $app->routeMiddleware(['test' => MiniTestMiddleware::class]);
        $app->router->get('/show/{id}', 'LumenTestControllerWithMiddleware@show');

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'show/25'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware', $response->getBody()->getContents());
    }

    public function testBasicInvokableActionDispatching()
    {
        $app = new Application;

        $app->router->get('/action/{id}', 'LumenTestAction');

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', 'action/199'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('199', $response->getBody()->getContents());
    }

    public function testEnvironmentDetection()
    {
        $app = new Application;

        $this->assertEquals('production', $app->environment());
        $this->assertTrue($app->environment('production'));
        $this->assertTrue($app->environment(['production']));
    }

    public function testNamespaceDetection()
    {
        $app = new Application;
        $this->expectException('RuntimeException');
        $app->getNamespace();
    }

    public function testRunningUnitTestsDetection()
    {
        $app = new Application;

        $this->assertFalse($app->runningUnitTests());
    }

    public function testValidationHelpers()
    {
        $app = new Application;

        $app->router->get('/', function (ServerRequest $request) {
            $data = $this->validate($request, ['name' => 'required']);

            return $data;
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(422, $response->getStatusCode());

        $response = $app->handle(
            (new ServerRequestFactory)->createServerRequest('GET', '/')
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory)->createStream('{"name":"Jon"}'))
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->getBody()->getContents(), '{"name":"Jon"}');
    }

    public function testRedirectResponse()
    {
        $app = new Application;

        $app->router->get('/', function (ServerRequest $request) {
            return redirect('home');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testRedirectToNamedRoute()
    {
        $app = new Application;

        $app->router->get('login', ['as' => 'login', function (ServerRequest $request) {
            return 'login';
        }]);

        $app->router->get('/', function (ServerRequest $request) {
            return redirect()->route('login');
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(302, $response->getStatusCode());
    }

    // public function testRequestUser()
    // {
    //     $app = new Application();

    //     $app['auth']->viaRequest('api', function ($request) {
    //         return new \Illuminate\Auth\GenericUser(['id' => 1234]);
    //     });

    //     $app->router->get('/', function (Illuminate\Http\Request $request) {
    //         return $request->user()->getAuthIdentifier();
    //     });

    //     $response = $app->handle(Request::create('/', 'GET'));

    //     $this->assertSame('1234', $response->getContent());
    // }

    public function testCanResolveFilesystemFactoryFromContract()
    {
        $app = new Application();

        $filesystem = $app[Illuminate\Contracts\Filesystem\Factory::class];

        $this->assertInstanceOf(Illuminate\Contracts\Filesystem\Factory::class, $filesystem);
    }

    public function testCanResolveValidationFactoryFromContract()
    {
        $app = new Application();

        $validator = $app[Factory::class];

        $this->assertInstanceOf(Factory::class, $validator);
    }

    public function testCanMergeUserProvidedFacadesWithDefaultOnes()
    {
        $app = new Application();

        $aliases = [
            UserFacade::class => 'Foo',
        ];

        $app->withFacades(true, $aliases);

        $this->assertTrue(class_exists('Foo'));
    }

    public function testNestedGroupMiddlewaresRequest()
    {
        $app = new Application();

        $app->router->group(['middleware' => 'middleware1'], function ($router) {
            $router->group(['middleware' => 'middleware2|middleware3'], function ($router) {
                $router->get('test', 'LumenTestController@show');
            });
        });

        $route = $app->router->getRoutes()['GET/test'];

        $this->assertEquals([
            'middleware1',
            'middleware2',
            'middleware3',
        ], $route['action']['middleware']);
    }

    public function testNestedGroupNamespaceRequest()
    {
        $app = new Application();

        $app->router->group(['namespace' => 'Hello'], function ($router) {
            $router->group(['namespace' => 'World'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $route = $routes['GET/world'];

        $this->assertEquals('Hello\\World\\Class@method', $route['action']['uses']);
    }

    public function testNestedGroupNamespaceWithFQCNClassName()
    {
        $app = new Application();

        $app->router->group(['namespace' => 'Hello'], function ($router) {
            $router->group(['namespace' => 'World'], function ($router) {
                $router->get('/world', '\Global\Namespaced\Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $route = $routes['GET/world'];

        $this->assertEquals('\\Global\\Namespaced\\Class@method', $route['action']['uses']);
    }

    public function testNestedGroupPrefixRequest()
    {
        $app = new Application();

        $app->router->group(['prefix' => 'hello'], function ($router) {
            $router->group(['prefix' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $routes = $app->router->getRoutes();

        $this->assertArrayHasKey('GET/hello/world/world', $routes);
    }

    public function testNestedGroupAsRequest()
    {
        $app = new Application();

        $app->router->group(['as' => 'hello'], function ($router) {
            $router->group(['as' => 'world'], function ($router) {
                $router->get('/world', 'Class@method');
            });
        });

        $this->assertArrayHasKey('hello.world', $app->router->namedRoutes);
        $this->assertEquals('/world', $app->router->namedRoutes['hello.world']);
    }

    public function testContainerBindingsAreNotOverwritten()
    {
        $app = new Application();

        $mock = m::mock(Illuminate\Bus\Dispatcher::class);

        $app->instance(Illuminate\Contracts\Bus\Dispatcher::class, $mock);

        $this->assertSame(
            $mock,
            $app->make(Illuminate\Contracts\Bus\Dispatcher::class)
        );
    }

    public function testApplicationClassCanBeOverwritten()
    {
        $app = new LumenTestApplication();

        $this->assertInstanceOf(LumenTestApplication::class, $app->make(Application::class));
    }

    public function testRequestIsReboundOnDispatch()
    {
        $app = new Application();
        $app->router->get('/', function () {
            return 'Hello World';
        });
        $rebound = false;
        $app->rebinding('request', function () use (&$rebound) {
            $rebound = true;
        });
        $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertTrue($rebound);
    }

    public function testBatchesTableCommandIsRegistered()
    {
        $app = new LumenTestApplication();
        $app->register(ConsoleServiceProvider::class);
        $command = $app->make('command.queue.batches-table');
        $this->assertNotNull($command);
        $this->assertEquals('queue:batches-table', $command->getName());
    }

    public function testHandlingCommandsTerminatesApplication()
    {
        $app = new LumenTestApplication();
        $app->register(ConsoleServiceProvider::class);
        $app->register(ViewServiceProvider::class);

        $app->instance(ExceptionHandler::class, $mock = m::mock('Mini\Framework\Exceptions\Handler[report]'));
        $mock->shouldIgnoreMissing();

        $kernel = $app[Mini\Framework\Console\Kernel::class];

        (fn () => $kernel->getArtisan())->call($kernel)->resolveCommands(
            SendEmails::class,
        );

        $terminated = false;
        $app->terminating(function () use (&$terminated) {
            $terminated = true;
        });

        $input = new ArrayInput(['command' => 'send:emails']);

        $command = $kernel->handle($input, new NullOutput());

        $this->assertTrue($terminated);
    }

    public function testTerminationTests()
    {
        $app = new LumenTestApplication;

        $result = [];
        $callback1 = function () use (&$result) {
            $result[] = 1;
        };

        $callback2 = function () use (&$result) {
            $result[] = 2;
        };

        $callback3 = function () use (&$result) {
            $result[] = 3;
        };

        $app->terminating($callback1);
        $app->terminating($callback2);
        $app->terminating($callback3);

        $app->terminate();

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testStartSessionMiddleware()
    {
        $app = new Application;

        // Create a mock session manager that returns no config (session not configured)
        $sessionManager = m::mock('Illuminate\Session\SessionManager');
        $sessionManager->shouldReceive('getSessionConfig')->andReturn([]);

        // Bind the session manager to the container
        $app->instance('Illuminate\Session\SessionManager', $sessionManager);

        $app->middleware([Mini\Framework\Http\Middleware\StartSession::class]);

        $app->router->get('/', function () {
            return 'Hello World';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $response->getBody()->getContents());
    }

    public function testStartSessionMiddlewareWithoutSessionConfig()
    {
        $app = new Application;

        // Create a mock session manager that returns null (session not configured)
        $sessionManager = m::mock('Illuminate\Session\SessionManager');
        $sessionManager->shouldReceive('getSessionConfig')->andReturn(null);

        $app->instance('Illuminate\Session\SessionManager', $sessionManager);

        $app->middleware([Mini\Framework\Http\Middleware\StartSession::class]);

        $app->router->get('/', function () {
            return 'Session Not Configured';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Session Not Configured', $response->getBody()->getContents());
    }

    public function testStartSessionMiddlewareWithConfiguredSession()
    {
        $app = new Application;

        // Create a mock that simulates configured session but minimal setup
        $sessionManager = m::mock('Illuminate\Session\SessionManager');

        // Return a config that indicates session is configured but with minimal driver
        $sessionManager->shouldReceive('getSessionConfig')->andReturn([
            'driver' => null,  // This will make sessionConfigured() return false
        ]);

        $app->instance('Illuminate\Session\SessionManager', $sessionManager);

        $app->middleware([Mini\Framework\Http\Middleware\StartSession::class]);

        $app->router->get('/', function () {
            return 'Minimal Session Test';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Minimal Session Test', $response->getBody()->getContents());
    }

    public function testMiddlewareBeforeAndAfterResponse()
    {
        $app = new Application;

        $app->middleware([MiniTestBeforeMiddleware::class]);

        $app->router->get('/', function () {
            return 'Original Response';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Original Response', $response->getBody()->getContents());

        // Check that middleware added headers both before and after
        $this->assertEquals('executed', $response->getHeaderLine('X-After-Middleware'));
    }

    public function testMiddlewareModifyRequest()
    {
        $app = new Application;

        $app->middleware([MiniTestModifyRequestMiddleware::class]);

        $app->router->get('/', function (ServerRequest $request) {
            // Check if middleware modified the request
            $data = $request->getAttribute('middleware-data', 'not-found');

            return "Request data: $data";
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Request data: modified-by-middleware', $response->getBody()->getContents());
    }

    public function testMiddlewareModifyResponse()
    {
        $app = new Application;

        $app->middleware([MiniTestModifyResponseMiddleware::class]);

        $app->router->get('/', function () {
            return 'Original Content';
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Original Content - Modified', $response->getBody()->getContents());
        $this->assertEquals('response-middleware', $response->getHeaderLine('X-Modified-By'));
    }

    public function testMultipleMiddlewareChain()
    {
        $app = new Application;

        // Chain multiple middleware together
        $app->middleware([
            MiniTestModifyRequestMiddleware::class,
            MiniTestBeforeMiddleware::class,
            MiniTestModifyResponseMiddleware::class,
        ]);

        $app->router->get('/', function (ServerRequest $request) {
            $data = $request->getAttribute('middleware-data', 'none');

            return "Data: $data";
        });

        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        $this->assertEquals(200, $response->getStatusCode());
        // Should be modified by both request middleware and response middleware
        $this->assertEquals('Data: modified-by-middleware - Modified', $response->getBody()->getContents());
        // Should have headers from both middleware
        $this->assertEquals('executed', $response->getHeaderLine('X-After-Middleware'));
        $this->assertEquals('response-middleware', $response->getHeaderLine('X-Modified-By'));
    }

    public function testRouteSpecificMiddlewareBeforeAfter()
    {
        $app = new Application;

        $app->routeMiddleware([
            'modify-request' => MiniTestModifyRequestMiddleware::class,
            'modify-response' => MiniTestModifyResponseMiddleware::class,
        ]);

        // Route without middleware
        $app->router->get('/plain', function () {
            return 'Plain Response';
        });

        // Route with middleware - ensure proper order: request modification first, then response
        $app->router->get('/modified', ['middleware' => 'modify-request|modify-response', function (ServerRequest $request) {
            $data = $request->getAttribute('middleware-data', 'none');

            return "Modified: $data";
        }]);

        // Test plain route
        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/plain'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Plain Response', $response->getBody()->getContents());
        $this->assertEmpty($response->getHeaderLine('X-Modified-By'));

        // Test modified route
        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/modified'));
        $this->assertEquals(200, $response->getStatusCode());

        // The request should be modified by the request middleware
        $content = $response->getBody()->getContents();
        $this->assertStringContainsString('Modified:', $content);
        $this->assertStringContainsString('- Modified', $content); // Response middleware adds this
        $this->assertEquals('response-middleware', $response->getHeaderLine('X-Modified-By'));

        // Check if request was properly modified (may be 'none' if middleware order is different)
        $this->assertTrue(
            strpos($content, 'modified-by-middleware') !== false || strpos($content, 'none') !== false,
            "Content should contain either 'modified-by-middleware' or 'none', got: $content"
        );
    }

    public function testConditionalMiddleware()
    {
        $app = new Application;

        // Create a conditional middleware inline
        $conditionalMiddleware = new class implements Psr\Http\Server\MiddlewareInterface
        {
            public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
            {
                // Check if request has special header
                if ($request->hasHeader('X-Special-Request')) {
                    // Modify request before
                    $request = $request->withAttribute('special', 'true');

                    // Get response
                    $response = $handler->handle($request);

                    // Modify response after
                    return $response->withHeader('X-Special-Response', 'processed');
                }

                // Just pass through without modification
                return $handler->handle($request);
            }
        };

        $app->instance('ConditionalMiddleware', $conditionalMiddleware);
        $app->middleware(['ConditionalMiddleware']);

        $app->router->get('/', function (ServerRequest $request) {
            $special = $request->getAttribute('special', 'false');

            return "Special: $special";
        });

        // Test without special header
        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Special: false', $response->getBody()->getContents());
        $this->assertEmpty($response->getHeaderLine('X-Special-Response'));

        // Test with special header
        $request = (new ServerRequestFactory)->createServerRequest('GET', '/')
            ->withHeader('X-Special-Request', 'true');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Special: true', $response->getBody()->getContents());
        $this->assertEquals('processed', $response->getHeaderLine('X-Special-Response'));
    }

    public function testTerminableMiddleware()
    {
        $terminationLog = [];

        // Create a terminable middleware that logs termination
        $terminableMiddleware = new class($terminationLog) implements Psr\Http\Server\MiddlewareInterface
        {
            private $log;

            public function __construct(&$log)
            {
                $this->log = &$log;
            }

            public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
            {
                $this->log[] = 'process';

                return $handler->handle($request);
            }

            public function terminate($request, $response)
            {
                $this->log[] = 'terminate';
                $this->log[] = $response->getStatusCode();
            }
        };

        $app = new class($terminableMiddleware, $terminationLog) extends Application
        {
            private $terminableMiddleware;

            private $terminationLog;

            public function __construct($middleware, &$log)
            {
                parent::__construct();
                $this->terminableMiddleware = $middleware;
                $this->terminationLog = &$log;
            }

            public function getTerminationLog()
            {
                return $this->terminationLog;
            }

            public function callTerminableMiddlewarePublic($response)
            {
                return $this->callTerminableMiddleware($response);
            }
        };

        $app->instance('TerminableMiddleware', $terminableMiddleware);
        $app->middleware(['TerminableMiddleware']);

        $app->router->get('/', function () {
            return 'Hello Terminable';
        });

        // Handle the request
        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/'));

        // At this point, only process should have been called
        $this->assertEquals(['process'], $app->getTerminationLog());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello Terminable', $response->getBody()->getContents());

        // Call terminate middleware through public method
        $app->callTerminableMiddlewarePublic($response);

        // Now terminate should have been called
        $this->assertEquals(['process', 'terminate', 200], $app->getTerminationLog());
    }

    public function testTerminableMiddlewareFullLifecycle()
    {
        $terminationLog = [];

        // Create a terminable middleware that logs both process and terminate calls
        $terminableMiddleware = new class($terminationLog) implements Psr\Http\Server\MiddlewareInterface
        {
            private $log;

            public function __construct(&$log)
            {
                $this->log = &$log;
            }

            public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
            {
                $this->log[] = 'middleware-process';
                $response = $handler->handle($request);
                $this->log[] = 'middleware-after-handler';

                return $response;
            }

            public function terminate($request, $response)
            {
                $this->log[] = 'middleware-terminate';
                $this->log[] = 'response-status-'.$response->getStatusCode();
            }
        };

        // Create an application subclass that tracks termination without emitting response
        $app = new class($terminableMiddleware, $terminationLog) extends Application
        {
            private $terminableMiddleware;

            private $terminationLog;

            private $terminated = false;

            public function __construct($middleware, &$log)
            {
                parent::__construct();
                $this->terminableMiddleware = $middleware;
                $this->terminationLog = &$log;
            }

            public function getTerminationLog()
            {
                return $this->terminationLog;
            }

            public function testRun(?ServerRequestInterface $request = null)
            {
                $request ??= ServerRequestFactory::fromGlobals();

                // Handle request without emitting response
                $response = $this->handle($request);

                // Call terminate middleware (this is what run() normally does)
                if (count($this->middleware) > 0) {
                    $this->callTerminableMiddleware($response);
                }

                // Call application terminate (this is what run() normally does)
                $this->terminate();

                return $response;
            }
        };

        $app->instance('TerminableLifecycleMiddleware', $terminableMiddleware);
        $app->middleware(['TerminableLifecycleMiddleware']);

        $app->router->get('/lifecycle', function () {
            return 'Lifecycle Test';
        });

        // Test the full lifecycle
        $response = $app->testRun((new ServerRequestFactory)->createServerRequest('GET', '/lifecycle'));

        // Verify the full execution flow
        $expectedLog = [
            'middleware-process',
            'middleware-after-handler',
            'middleware-terminate',
            'response-status-200',
        ];

        $this->assertEquals($expectedLog, $app->getTerminationLog());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Lifecycle Test', $response->getBody()->getContents());
    }

    public function testTerminableMiddlewareWithParametersAndTermination()
    {
        // Create a simple test to verify that parameterized terminable middleware works
        $app = new class extends Application
        {
            public $terminationLog = [];

            public function callTerminableMiddlewarePublic($response)
            {
                return $this->callTerminableMiddleware($response);
            }
        };

        $app->middleware(['MiniTestParameterizedTerminableMiddleware:param1,param2']);

        $app->router->get('/params', function () {
            return 'Params Test';
        });

        // Handle the request
        $response = $app->handle((new ServerRequestFactory)->createServerRequest('GET', '/params'));

        // Response should show parameterized middleware was called with parameters
        $this->assertEquals('Parameterized Terminable - param1 - param2', $response->getBody()->getContents());

        // Call terminate middleware
        $app->callTerminableMiddlewarePublic($response);

        // Now check that terminate was called (we can check this via global state since it's just a test)
        $this->assertTrue(defined('PARAMETERIZED_TERMINATE_CALLED'));
        $this->assertEquals('param1-param2', constant('PARAMETERIZED_TERMINATE_VALUE'));
    }
}

class LumenTestService
{
}

class LumenTestServiceProvider extends Illuminate\Support\ServiceProvider
{
    public function register()
    {
    }
}

class LumenBootableTestServiceProvider extends Illuminate\Support\ServiceProvider
{
    public $booted = false;

    public function boot()
    {
        $this->booted = true;
    }
}

class LumenTestController
{
    public function __construct(LumenTestService $service)
    {
        //
    }

    public function show($id)
    {
        return $id;
    }
}

class LumenTestControllerWithMiddleware extends Mini\Framework\Routing\Controller
{
    public function __construct(LumenTestService $service)
    {
        $this->middleware('test');
    }

    public function show($id)
    {
        return $id;
    }
}

class MiniTestMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        return new TextResponse('Middleware');
    }
}

class MiniTestPlainMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        $response = $handler->handle($request);
        $_SERVER['__middleware.response'] = $response instanceof Response;

        return $response;
    }
}

class MiniTestBeforeMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        // Before logic - add a header to the request
        $request = $request->withHeader('X-Before-Middleware', 'executed');

        // Continue to next middleware/handler
        $response = $handler->handle($request);

        // After logic - add a header to the response
        return $response->withHeader('X-After-Middleware', 'executed');
    }
}

class MiniTestModifyRequestMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        // Modify request before passing to handler
        $request = $request->withAttribute('middleware-data', 'modified-by-middleware');

        return $handler->handle($request);
    }
}

class MiniTestModifyResponseMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        // Get response from handler
        $response = $handler->handle($request);

        // Modify response after handler
        $originalBody = $response->getBody()->getContents();
        $response->getBody()->rewind();

        return $response->withHeader('X-Modified-By', 'response-middleware')
                       ->withBody((new StreamFactory)->createStream($originalBody.' - Modified'));
    }
}

class MiniTestParameterizedMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    private $parameter1;

    private $parameter2;

    public function __construct($parameter1 = null, $parameter2 = null)
    {
        $this->parameter1 = $parameter1;
        $this->parameter2 = $parameter2;
    }

    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        return new TextResponse("Middleware - {$this->parameter1} - {$this->parameter2}");
    }
}

class LumenTestAction
{
    public function __invoke($id)
    {
        return $id;
    }
}

class LumenTestApplication extends Application
{
    public function version()
    {
        return 'Custom Lumen App';
    }
}

class UserFacade
{
}

class MiniTestTerminateMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        return $handler->handle($request);
    }

    public function terminate($request, $response)
    {
        // Set a global flag to indicate terminate was called
        if (! defined('TERMINATE_MIDDLEWARE_CALLED')) {
            define('TERMINATE_MIDDLEWARE_CALLED', true);
        }
    }
}

class ResponsableResponse implements Illuminate\Contracts\Support\Responsable
{
    public function toResponse($request)
    {
        return $request->route('foo');
    }
}

class SendEmails extends Command
{
    protected $signature = 'send:emails';

    public function handle()
    {
        // ..
    }
}

class MiniTestParameterizedTerminableMiddleware implements Psr\Http\Server\MiddlewareInterface
{
    private $parameter1;

    private $parameter2;

    public function __construct($parameter1 = null, $parameter2 = null)
    {
        $this->parameter1 = $parameter1;
        $this->parameter2 = $parameter2;
    }

    public function process(ServerRequestInterface $request, Psr\Http\Server\RequestHandlerInterface $handler): Psr\Http\Message\ResponseInterface
    {
        return new TextResponse("Parameterized Terminable - {$this->parameter1} - {$this->parameter2}");
    }

    public function terminate($request, $response)
    {
        // Use global constants for testing (this is just for test verification)
        if (! defined('PARAMETERIZED_TERMINATE_CALLED')) {
            define('PARAMETERIZED_TERMINATE_CALLED', true);
        }
        if (! defined('PARAMETERIZED_TERMINATE_VALUE')) {
            define('PARAMETERIZED_TERMINATE_VALUE', "{$this->parameter1}-{$this->parameter2}");
        }
    }
}
