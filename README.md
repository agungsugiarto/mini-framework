# PHP Mini Framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/agungsugiarto/mini-framework.svg?style=flat-square)](https://packagist.org/packages/agungsugiarto/mini-framework)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/agungsugiarto/mini-framework/tests.yml?branch=3.x&label=tests&style=flat-square)](https://github.com/agungsugiarto/mini-framework/actions?query=workflow%3Atests+branch%3A3.x)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/agungsugiarto/mini-framework/phpcs.yml?branch=3.x&label=code%20style&style=flat-square)](https://github.com/agungsugiarto/mini-framework/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3A3.x)
[![Total Downloads](https://img.shields.io/packagist/dt/agungsugiarto/mini-framework.svg?style=flat-square)](https://packagist.org/packages/agungsugiarto/mini-framework)

## PHP Mini Framework

PHP Mini Framework is a fast and lightweight micro-framework for building web applications with expressive and elegant syntax. This framework is built based on Laravel Lumen but has been modified and enhanced with modern PSR standards implementation to provide better development experience and optimal performance.

## Framework Architecture

### PSR Standards Implementation

Modern PSR standards for interoperability (PSR-3, 7, 11, 15):

- **PSR-3**: Logger Interface with Monolog integration
- **PSR-7**: HTTP Message Interface (ServerRequest/Response)  
- **PSR-11**: Container Interface with auto-wiring
- **PSR-15**: HTTP Server Request Handlers & Middleware

### Core Architecture

1. **Application**: PSR-15 RequestHandler + PSR-11 Container
2. **HTTP Layer**: PSR-7 messages with Laravel-style helpers
3. **Routing**: Fast nikic/fast-route with auto-response transformation
4. **Middleware**: PSR-15 pipeline with smart parameter injection

### Quick Examples

**Middleware with Auto Parameter Injection:**
```php
// Zero-config parameter injection
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
return "Hello";                  // String → HTML
```

### 🚀 **MiddlewareTransformer: The Innovation**

**PSR-15 Bridge + Smart Parameter Injection:**
```php
// Input: 'throttle:60,1|cache:3600,redis' 
// Output: Auto-injected PSR-15 middleware closures

public function transform(array $middleware): array {
    return array_map(
        fn ($name) => fn ($request, $next) => $this->resolveMiddleware($name)->process(
            $request, new PipelineRequestHandler($next)
        ), $middleware
    );
}
```

**Key Innovation:**
- ✨ **Zero Config**: Auto parameter injection via reflection
- 🚀 **PSR Bridge**: Seamless PSR-15 ↔ Laravel Pipeline 
- ⚡ **Performance**: Lazy loading + cached reflection
- 🌐 **Universal**: Works with any PSR-15 middleware

### Performance

- ⚡ **Lightweight**: Minimal overhead vs full Laravel
- 🚀 **Fast Routing**: nikic/fast-route + auto-response transformation  
- 🧠 **Smart Middleware**: PSR-15 bridge with parameter injection
- 🛡️ **Exception Safe**: Bulletproof pipeline execution

### Dependencies

Core libraries: `laminas/diactoros`, `nikic/fast-route`, `illuminate/*`, `spatie/ignition`

**What makes this genius:**

1. **PSR-15 Bridge**: Converts PSR-15 `MiddlewareInterface` to Laravel Pipeline format
2. **Smart Parameter Injection**: Auto-injects constructor parameters using Reflection
3. **Container Integration**: Seamless dependency injection from IoC container

## Installation

### Requirements
- PHP 8.1 or higher
- Composer

### Create New Application (Recommended)
```bash
# Create new application from starter template
composer create-project agungsugiarto/mini-application my-app
cd my-app
```

### Install Framework Only
```bash
# Install framework as dependency
composer require agungsugiarto/mini-framework
```

### Environment Setup
```bash
# Create environment file
cp .env.example .env

# Configure your database and other settings in .env
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=mini_framework
```

### Quick Start
```php
<?php
require_once 'vendor/autoload.php';

$app = new Mini\Framework\Application();

$app->router->get('/', function () {
    return 'Hello Mini Framework!';
});

$app->router->get('/api/users/{id}', function ($id) {
    return ['user_id' => $id, 'framework' => 'mini'];
});

$app->run();
```

## Testing

The framework includes comprehensive testing capabilities with PHPUnit and custom testing traits.

### Running Tests
```bash
# Run all tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Test Configuration
```bash
# Copy phpunit configuration
cp phpunit.xml.dist phpunit.xml

# Run specific test files
./vendor/bin/phpunit tests/FullApplicationTest.php
./vendor/bin/phpunit tests/Cors/CorsServiceTest.php
```

## Documentation

- [PSR-7 HTTP Message](https://www.php-fig.org/psr/psr-7/)
- [PSR-15 HTTP Handlers](https://www.php-fig.org/psr/psr-15/)
- [Laravel Container](https://laravel.com/docs/container)

## License

MIT License - see [LICENSE.md](https://github.com/agungsugiarto/mini-framework/blob/3.x/LICENSE.md)
