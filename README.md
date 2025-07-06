# PHP Mini Framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/agungsugiarto/mini-framework.svg?style=flat-square)](https://packagist.org/packages/agungsugiarto/mini-framework)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/agungsugiarto/mini-framework/tests.yml?branch=3.x&label=tests&style=flat-square)](https://github.com/agungsugiarto/mini-framework/actions?query=workflow%3Atests+branch%3A3.x)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/agungsugiarto/mini-framework/phpcs.yml?branch=3.x&label=code%20style&style=flat-square)](https://github.com/agungsugiarto/mini-framework/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3A3.x)
[![Total Downloads](https://img.shields.io/packagist/dt/agungsugiarto/mini-framework.svg?style=flat-square)](https://packagist.org/packages/agungsugiarto/mini-framework)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

PHP Mini Framework is a fast and lightweight micro-framework for building web applications with expressive and elegant syntax. This framework is a fork of Laravel Lumen that has been specifically modified and enhanced with modern PSR standards implementation, with a unique focus on seamless PSR-15 middleware integration using Laravel Pipeline as its dispatcher. This innovative approach delivers optimal performance while maintaining developer-friendly experience and universal middleware compatibility for modern PHP applications.

## Table of Contents

- [Framework Architecture](#framework-architecture)
- [MiddlewareTransformer: The Core](#middlewaretransformer-the-core)
- [Installation and Usage](#installation-and-usage)
- [Testing and Quality Assurance](#testing-and-quality-assurance)
- [Documentation and Resources](#documentation-and-resources)
- [Community & Support](#community--support)
- [License](#license)

## Framework Architecture

### PSR Standards Implementation

Modern PSR standards implementation for interoperability (PSR-3, 7, 11, 15):

- **PSR-3**: Logger Interface with Monolog integration for systematic logging
- **PSR-7**: HTTP Message Interface (ServerRequest/Response) for standard HTTP communication
- **PSR-11**: Container Interface with auto-wiring for dependency injection  
- **PSR-15**: HTTP Server Request Handlers & Middleware for processing pipeline

### Core Architecture

1. **Application**: Integrated PSR-15 RequestHandler + PSR-11 Container
2. **HTTP Layer**: PSR-7 messages with Laravel-style helpers for development ease
3. **Routing**: Fast nikic/fast-route with intelligent auto-response transformation
4. **Middleware**: PSR-15 pipeline with smart parameter injection and Laravel Pipeline as dispatcher

### Key Features

**Middleware with Auto Parameter Injection:**
```php
// Zero-config parameter injection for middleware
'middleware' => 'throttle:60,1|cache:3600,redis'

class ThrottleMiddleware implements MiddlewareInterface {
    public function __construct(
        private int $maxAttempts,    // Auto: 60
        private int $decayMinutes    // Auto: 1  
    ) {}
}
```

**Smart Response Transformation:**
```php
return $user;                    // Model → JSON 201
return ['data' => $users];       // Array → JSON
return "Hello World";            // String → HTML
```

### MiddlewareTransformer: The Core Innovation

Mini Framework's `MiddlewareTransformer` bridges PSR-15 middleware with Laravel Pipeline, enabling seamless integration of any PSR-15 middleware within Laravel's familiar ecosystem.

**Core Transform Method:**
```php
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
```

**How the Transform Process Works:**

1. **Input**: Array of middleware definitions (strings, classes, instances)
   ```php
   ['auth:api', 'throttle:60,5', CustomMiddleware::class]
   ```

2. **Transform Output**: Array of Laravel Pipeline-compatible closures
   ```php
   [
       // Auth middleware closure with parameter
       function($request, $next) { 
           return (new AuthMiddleware('api'))->process(
               $request, 
               new PipelineRequestHandler($next)
           ); 
       },
       
       // Throttle middleware closure with parameters
       function($request, $next) { 
           return (new ThrottleMiddleware(60, 5))->process(
               $request, 
               new PipelineRequestHandler($next)
           ); 
       },
       
       // Custom middleware closure
       function($request, $next) { 
           return (new CustomMiddleware())->process(
               $request, 
               new PipelineRequestHandler($next)
           ); 
       }
   ]
   ```

3. **Pipeline Execution**: Laravel Pipeline processes each closure sequentially
   ```php
   $pipeline = new Pipeline($container);
   $response = $pipeline
       ->send($request)
       ->through($transformedMiddlewares)  // Our closures
       ->then(fn (ServerRequestInterface $request): ResponseInterface => $then($request));
   ```

**The Bridge Mechanism:**
- Each closure receives Laravel Pipeline's `($request, $next)` signature
- `PipelineRequestHandler` converts `$next` closure to PSR-15 `RequestHandlerInterface`
- PSR-15 middleware's `process()` method is called with proper interfaces
- Response flows back through the pipeline chain

**PipelineRequestHandler - The Bridge:**
```php
class PipelineRequestHandler implements RequestHandlerInterface
{
    public function __construct(private Closure $next) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->next)($request);  // Calls Laravel Pipeline's $next
    }
}
```

This simple adapter class enables PSR-15 middleware to call the next middleware in Laravel Pipeline by:
1. **Wrapping** Laravel's `$next` closure in PSR-15 `RequestHandlerInterface`
2. **Converting** Pipeline's `$next($request)` call to PSR-15's `$handler->handle($request)`
3. **Maintaining** the middleware chain flow without breaking either standard

**Smart Parameter Injection:**
```php
// Definition: 'throttle:100,5'
// Resolves to: new ThrottleMiddleware(100, 5)
class ThrottleMiddleware implements MiddlewareInterface {
    public function __construct(private int $maxAttempts, private int $decayMinutes) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Your throttling logic here
        return $handler->handle($request);
    }
}
```

**Key Benefits:**
- **Universal Compatibility**: Any PSR-15 middleware works instantly
- **Pipeline Integration**: Maintains Laravel's familiar middleware execution flow
- **Smart Resolution**: Auto-injection with reflection and container integration
- **Performance Optimized**: Minimal overhead with efficient closure transformation

### PSR-15 Middleware Examples

**Basic Middleware:**
```php
class CustomMiddleware implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute('processed_at', time());
        $response = $handler->handle($request);
        return $response->withHeader('X-Processed-By', 'CustomMiddleware');
    }
}
```

**Parameterized Middleware:**
```php
class ThrottleMiddleware implements MiddlewareInterface {
    public function __construct(private int $maxAttempts, private int $decayMinutes) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Throttling logic with injected parameters
        if ($this->exceedsLimit($request)) {
            return new JsonResponse(['error' => 'Too many requests'], 429);
        }
        return $handler->handle($request);
    }
}
// Usage: 'throttle:100,5' auto-injects maxAttempts=100, decayMinutes=5
```

### Performance and Optimization

- **Lightweight**: Minimal overhead compared to full Laravel framework
- **Fast Routing**: nikic/fast-route + optimal auto-response transformation
- **Smart Middleware**: PSR-15 bridge with parameter injection for maximum flexibility
- **Exception Safe**: Bulletproof pipeline execution with proper error handling
- **Terminable Lifecycle**: Full support for middleware termination cleanup

### Core Dependencies

Core libraries used: 
- [`laminas/diactoros`](https://github.com/laminas/laminas-diactoros) - PSR-7 HTTP message implementation
- [`nikic/fast-route`](https://github.com/nikic/FastRoute) - High-performance router
- [`illuminate/*`](https://github.com/illuminate) - Laravel components (Container, Pipeline, etc.)
- [`spatie/ignition`](https://github.com/spatie/ignition) - Advanced error handling and debugging

### What Makes Mini Framework Special?

**PSR-15 Bridge with Laravel Pipeline** - Seamlessly use any PSR-15 middleware within Laravel's pipeline ecosystem without compatibility issues.

**Smart Parameter Injection** - Auto-inject constructor parameters from middleware definitions using reflection with performance caching.

**Universal Middleware Compatibility** - Compatible with all existing PSR-15 middleware libraries, enabling code reuse across different frameworks.

## Installation and Usage

### System Requirements
- PHP 8.1 or higher
- Composer for dependency management

### Installation Methods

#### Create New Application (Recommended)
```bash
# Create new application from starter template
composer create-project agungsugiarto/mini-application my-app
cd my-app
```

#### Install Framework Only
```bash
# Install framework as dependency
composer require agungsugiarto/mini-framework
```

### Environment Configuration
```bash
# Create environment file
cp .env.example .env

# Configure database and other settings in .env
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=mini_framework
```

### Quick Start - Simple Application
```php
<?php
require_once 'vendor/autoload.php';

$app = new Mini\Framework\Application();

// Basic route
$app->router->get('/', function () {
    return 'Hello Mini Framework!';
});

// Route with parameters
$app->router->get('/api/users/{id}', function ($id) {
    return ['user_id' => $id, 'framework' => 'mini'];
});

// Route with middleware
$app->router->get('/protected', [
    'middleware' => 'auth:api',
    function () {
        return 'Protected content';
    }
]);

$app->run();
```

### Middleware Usage Examples

**Global Middleware:**
```php
$app->middleware([
    'cors',
    'throttle:60,1',
    CustomLoggerMiddleware::class
]);
```

**Route-Specific Middleware:**
```php
$app->routeMiddleware([
    'auth' => AuthMiddleware::class,
    'throttle' => ThrottleMiddleware::class,
    'cache' => CacheMiddleware::class,
]);

$app->router->get('/api/data', [
    'middleware' => 'auth|throttle:100,5|cache:300',
    function () {
        return ['data' => 'sensitive information'];
    }
]);
```

**Pipeline Execution Flow:**
```php
// 1. Middleware string parsing
'auth|throttle:100,5|cache:300' 
// Becomes: ['auth', 'throttle:100,5', 'cache:300']

// 2. MiddlewareTransformer.transform() output
[
    // Each closure wraps PSR-15 middleware in Laravel Pipeline format
    function($request, $next) { 
        $authMiddleware = new AuthMiddleware(); // No params
        return $authMiddleware->process($request, new PipelineRequestHandler($next)); 
    },
    function($request, $next) { 
        $throttleMiddleware = new ThrottleMiddleware(100, 5); // Auto-injected params
        return $throttleMiddleware->process($request, new PipelineRequestHandler($next)); 
    },
    function($request, $next) { 
        $cacheMiddleware = new CacheMiddleware(300); // Auto-injected param
        return $cacheMiddleware->process($request, new PipelineRequestHandler($next)); 
    }
]

// 3. Laravel Pipeline execution
$pipeline->send($request)
    ->through($transformedMiddlewares)
    ->then(fn (ServerRequestInterface $request): ResponseInterface => $then($request));

// Execution order: auth → throttle → cache → route handler → cache ← throttle ← auth
```

**Custom PSR-15 Middleware:**
```php
class ApiKeyMiddleware implements MiddlewareInterface
{
    public function __construct(private string $requiredKey) {}
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = $request->getHeaderLine('X-API-Key');
        
        if ($apiKey !== $this->requiredKey) {
            return new JsonResponse(['error' => 'Invalid API key'], 401);
        }
        
        return $handler->handle($request);
    }
}

// Register and use
$app->routeMiddleware(['apikey' => ApiKeyMiddleware::class]);
$app->router->get('/secure', ['middleware' => 'apikey:secret123', $handler]);
```

## Testing and Quality Assurance

The framework includes comprehensive testing capabilities with PHPUnit and custom testing traits to ensure code reliability and maintainability.

### Running Tests
```bash
# Run all tests
./vendor/bin/phpunit

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage

# Test specific files
./vendor/bin/phpunit tests/FullApplicationTest.php
./vendor/bin/phpunit tests/Cors/CorsServiceTest.php
```

### Test Configuration
```bash
# Copy phpunit configuration
cp phpunit.xml.dist phpunit.xml

# Customize test environment as needed
```

## Documentation and Resources

### Standards & Ecosystem
- [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/) | [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7/)
- [Laravel Container](https://laravel.com/docs/container) | [Laravel Pipeline](https://laravel.com/docs/helpers#pipeline)

### Project Links
- [GitHub Repository](https://github.com/agungsugiarto/mini-framework) | [Packagist Package](https://packagist.org/packages/agungsugiarto/mini-framework)
- [Starter Application](https://github.com/agungsugiarto/mini-application)

## Community & Support

**Get Help:** [GitHub Discussions](https://github.com/agungsugiarto/mini-framework/discussions) • [Issue Tracker](https://github.com/agungsugiarto/mini-framework/issues) • [Documentation](https://github.com/agungsugiarto/mini-framework/blob/3.x/README.md)

**Contribute:** [Contributors](https://github.com/agungsugiarto/mini-framework/graphs/contributors) • [GitHub Repository](https://github.com/agungsugiarto/mini-framework)

## License

MIT License - see [LICENSE.md](https://github.com/agungsugiarto/mini-framework/blob/3.x/LICENSE.md) for complete details.

---

**Mini Framework** - Combining the power of PSR-15 with the ease of Laravel Pipeline for optimal development experience.
