<?php

namespace Mini\Framework\Exceptions\Ignition\Renderers;

use Mini\Framework\Exceptions\Ignition\ContextProviders\LaravelContextProviderDetector;
use Mini\Framework\Exceptions\Ignition\Solutions\SolutionTransformers\LaravelSolutionTransformer;
use Mini\Framework\Exceptions\Ignition\Support\LaravelDocumentationLinkFinder;
use Spatie\FlareClient\Flare;
use Spatie\Ignition\Config\IgnitionConfig;
use Spatie\Ignition\Contracts\SolutionProviderRepository;
use Spatie\Ignition\Ignition;
use Throwable;

class ErrorPageRenderer
{
    public function render(Throwable $throwable): void
    {
        $vitejsAutoRefresh = '';

        if (class_exists('Illuminate\Foundation\Vite')) {
            $vite = app(\Illuminate\Foundation\Vite::class);

            if (is_file($vite->hotFile())) {
                $vitejsAutoRefresh = $vite->__invoke([]);
            }
        }

        app(Ignition::class)
            ->resolveDocumentationLink(
                fn (Throwable $throwable) => (new LaravelDocumentationLinkFinder)->findLinkForThrowable($throwable)
            )
            ->setFlare(app(Flare::class))
            ->setConfig(app(IgnitionConfig::class))
            ->setSolutionProviderRepository(app(SolutionProviderRepository::class))
            ->setContextProviderDetector(new LaravelContextProviderDetector)
            ->setSolutionTransformerClass(LaravelSolutionTransformer::class)
            ->applicationPath(base_path())
            ->addCustomHtmlToHead($vitejsAutoRefresh)
            ->renderException($throwable);
    }
}
