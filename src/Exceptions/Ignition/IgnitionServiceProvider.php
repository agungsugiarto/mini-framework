<?php

namespace Mini\Framework\Exceptions\Ignition;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewException;
use Mini\Framework\Application;
use Mini\Framework\Exceptions\Ignition\Commands\SolutionMakeCommand;
use Mini\Framework\Exceptions\Ignition\Commands\SolutionProviderMakeCommand;
use Mini\Framework\Exceptions\Ignition\Commands\TestCommand;
use Mini\Framework\Exceptions\Ignition\ContextProviders\LaravelContextProviderDetector;
use Mini\Framework\Exceptions\Ignition\Exceptions\InvalidConfig;
use Mini\Framework\Exceptions\Ignition\FlareMiddleware\AddJobs;
use Mini\Framework\Exceptions\Ignition\FlareMiddleware\AddLogs;
use Mini\Framework\Exceptions\Ignition\FlareMiddleware\AddQueries;
use Mini\Framework\Exceptions\Ignition\Http\Middleware\RunnableSolutionsEnabled;
use Mini\Framework\Exceptions\Ignition\Recorders\DumpRecorder\DumpRecorder;
use Mini\Framework\Exceptions\Ignition\Recorders\JobRecorder\JobRecorder;
use Mini\Framework\Exceptions\Ignition\Recorders\LogRecorder\LogRecorder;
use Mini\Framework\Exceptions\Ignition\Recorders\QueryRecorder\QueryRecorder;
use Mini\Framework\Exceptions\Ignition\Renderers\IgnitionExceptionRenderer;
use Mini\Framework\Exceptions\Ignition\Renderers\IgnitionWhoopsHandler;
use Mini\Framework\Exceptions\Ignition\Solutions\SolutionProviders\SolutionProviderRepository;
use Mini\Framework\Exceptions\Ignition\Support\FlareLogHandler;
use Mini\Framework\Exceptions\Ignition\Support\SentReports;
use Mini\Framework\Exceptions\Ignition\Views\ViewExceptionMapper;
use Monolog\Logger;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareMiddleware\AddSolutions;
use Spatie\Ignition\Config\FileConfigManager;
use Spatie\Ignition\Config\IgnitionConfig;
use Spatie\Ignition\Contracts\ConfigManager;
use Spatie\Ignition\Contracts\SolutionProviderRepository as SolutionProviderRepositoryContract;
use Spatie\Ignition\Ignition;

class IgnitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
        $this->registerFlare();
        $this->registerIgnition();
        $this->registerRenderer();
        $this->registerRecorders();
        $this->registerLogHandler();
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        $this->registerRoutes();
        $this->configureTinker();
        $this->registerViewExceptionMapper();
        $this->startRecorders();
        $this->configureQueue();
    }

    protected function registerConfig(): void
    {
        $this->app->configure('flare');
        $this->app->configure('ignition');
    }

    protected function registerCommands(): void
    {
        if ($this->app['config']->get('flare.key')) {
            $this->commands([
                TestCommand::class,
            ]);
        }

        if ($this->app['config']->get('ignition.register_commands')) {
            $this->commands([
                SolutionMakeCommand::class,
                SolutionProviderMakeCommand::class,
            ]);
        }
    }

    protected function registerRenderer(): void
    {
        if (interface_exists('Whoops\Handler\HandlerInterface')) {
            $this->app->bind(
                'Whoops\Handler\HandlerInterface',
                fn (Application $app) => $app->make(IgnitionWhoopsHandler::class)
            );
        }

        if (interface_exists('Illuminate\Contracts\Foundation\ExceptionRenderer')) {
            $this->app->bind(
                'Illuminate\Contracts\Foundation\ExceptionRenderer',
                fn (Application $app) => $app->make(IgnitionExceptionRenderer::class)
            );
        }
    }

    protected function registerFlare(): void
    {
        $this->app->singleton(Flare::class, function () {
            return Flare::make()
                ->setApiToken(config('flare.key') ?? '')
                ->setBaseUrl(config('flare.base_url', 'https://flareapp.io/api'))
                ->applicationPath(base_path())
                ->setStage(app()->environment())
                ->setContextProviderDetector(new LaravelContextProviderDetector)
                ->registerMiddleware($this->getFlareMiddleware())
                ->registerMiddleware(new AddSolutions(new SolutionProviderRepository($this->getSolutionProviders())));
        });

        $this->app->singleton(SentReports::class);
    }

    protected function registerIgnition(): void
    {
        $this->app->singleton(
            ConfigManager::class,
            fn () => new FileConfigManager(config('ignition.settings_file_path', ''))
        );

        $ignitionConfig = (new IgnitionConfig)
            ->merge(config('ignition', []))
            ->loadConfigFile();

        $solutionProviders = $this->getSolutionProviders();
        $solutionProviderRepository = new SolutionProviderRepository($solutionProviders);

        $this->app->singleton(IgnitionConfig::class, fn () => $ignitionConfig);

        $this->app->singleton(SolutionProviderRepositoryContract::class, fn () => $solutionProviderRepository);

        $this->app->singleton(
            Ignition::class,
            fn () => (new Ignition)
                ->applicationPath(base_path())
        );
    }

    protected function registerRecorders(): void
    {
        $this->app->singleton(DumpRecorder::class);

        $this->app->singleton(LogRecorder::class, function (Application $app): LogRecorder {
            return new LogRecorder(
                $app,
                config()->get('flare.flare_middleware.'.AddLogs::class.'.maximum_number_of_collected_logs')
            );
        });

        $this->app->singleton(
            QueryRecorder::class,
            function (Application $app): QueryRecorder {
                return new QueryRecorder(
                    $app,
                    config('flare.flare_middleware.'.AddQueries::class.'.report_query_bindings', true),
                    config('flare.flare_middleware.'.AddQueries::class.'.maximum_number_of_collected_queries', 200)
                );
            }
        );

        $this->app->singleton(JobRecorder::class, function (Application $app): JobRecorder {
            return new JobRecorder(
                $app,
                config('flare.flare_middleware.'.AddJobs::class.'.max_chained_job_reporting_depth', 5)
            );
        });
    }

    public function configureTinker(): void
    {
        if (! $this->app->runningInConsole()) {
            if (isset($_SERVER['argv']) && ['artisan', 'tinker'] === $_SERVER['argv']) {
                app(Flare::class)->sendReportsImmediately();
            }
        }
    }

    protected function registerViewExceptionMapper(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'map')) {
            return;
        }

        $handler->map(function (ViewException $viewException) {
            return $this->app->make(ViewExceptionMapper::class)->map($viewException);
        });
    }

    protected function registerRoutes(): void
    {
        $this->app->router->group([
            'as' => 'ignition.',
            'prefix' => config('ignition.housekeeping_endpoint_prefix'),
            'middleware' => [RunnableSolutionsEnabled::class],
            'namespace' => 'Mini\Framework\Exceptions\Ignition\Http\Controllers',
        ], function (\Mini\Framework\Routing\Router $router) {
            $router->get('health-check', ['uses' => 'HealthCheckController', 'as' => 'healthCheck']);
            $router->post('execute-solution', ['uses' => 'ExecuteSolutionController', 'as' => 'healthCheck']);
            $router->post('update-config', ['uses' => 'UpdateConfigController', 'as' => 'healthCheck']);
        });
    }

    protected function registerLogHandler(): void
    {
        $this->app->singleton('flare.logger', function ($app) {
            $handler = new FlareLogHandler(
                $app->make(Flare::class),
                $app->make(SentReports::class),
            );

            $logLevelString = config('logging.channels.flare.level', 'error');

            $logLevel = $this->getLogLevel($logLevelString);

            $handler->setMinimumReportLogLevel($logLevel);

            return tap(
                new Logger('Flare'),
                fn (Logger $logger) => $logger->pushHandler($handler)
            );
        });

        Log::extend('flare', fn ($app) => $app['flare.logger']);
    }

    protected function startRecorders(): void
    {
        foreach ($this->app->config['ignition.recorders'] ?? [] as $recorder) {
            $this->app->make($recorder)->start();
        }
    }

    protected function configureQueue(): void
    {
        if (! $this->app->bound('queue')) {
            return;
        }

        $queue = $this->app->get('queue');

        // Reset before executing a queue job to make sure the job's log/query/dump recorders are empty.
        // When using a sync queue this also reports the queued reports from previous exceptions.
        $queue->before(function () {
            $this->resetFlareAndLaravelIgnition();
            app(Flare::class)->sendReportsImmediately();
        });

        // Send queued reports (and reset) after executing a queue job.
        $queue->after(function () {
            $this->resetFlareAndLaravelIgnition();
        });

        // Note: the $queue->looping() event can't be used because it's not triggered on Vapor
    }

    protected function getLogLevel(string $logLevelString): int
    {
        $logLevel = Logger::getLevels()[strtoupper($logLevelString)] ?? null;

        if (! $logLevel) {
            throw InvalidConfig::invalidLogLevel($logLevelString);
        }

        return $logLevel;
    }

    protected function getFlareMiddleware(): array
    {
        return collect(config('flare.flare_middleware'))
            ->map(function ($value, $key) {
                if (is_string($key)) {
                    $middlewareClass = $key;
                    $parameters = $value ?? [];
                } else {
                    $middlewareClass = $value;
                    $parameters = [];
                }

                return new $middlewareClass(...array_values($parameters));
            })
            ->values()
            ->toArray();
    }

    protected function getSolutionProviders(): array
    {
        return collect(config('ignition.solution_providers'))
            ->reject(
                fn (string $class) => in_array($class, config('ignition.ignored_solution_providers'))
            )
            ->toArray();
    }

    protected function resetFlareAndLaravelIgnition(): void
    {
        $this->app->get(SentReports::class)->clear();
        $this->app->get(Ignition::class)->reset();

        if (config('flare.flare_middleware.'.AddLogs::class)) {
            $this->app->make(LogRecorder::class)->reset();
        }

        if (config('flare.flare_middleware.'.AddQueries::class)) {
            $this->app->make(QueryRecorder::class)->reset();
        }

        if (config('flare.flare_middleware.'.AddJobs::class)) {
            $this->app->make(JobRecorder::class)->reset();
        }

        $this->app->make(DumpRecorder::class)->reset();
    }
}
