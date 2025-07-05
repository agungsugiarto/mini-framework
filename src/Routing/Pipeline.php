<?php

namespace Mini\Framework\Routing;

use Closure as BaseClosure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Pipeline\Pipeline as BasePipeline;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * This extended pipeline catches any exceptions that occur during each slice.
 *
 * The exceptions are converted to HTTP responses for proper middleware handling.
 */
class Pipeline extends BasePipeline
{
    /**
     * Get a Closure that represents a slice of the application onion.
     *
     * @return BaseClosure
     */
    protected function carry()
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    $slice = parent::carry();

                    return ($slice($stack, $pipe))($passable);
                } catch (Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * Get the initial slice to begin the stack call.
     *
     * @return BaseClosure
     */
    protected function prepareDestination(BaseClosure $destination)
    {
        return function ($passable) use ($destination) {
            try {
                return $destination($passable);
            } catch (Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Handle the given exception.
     *
     * @param  mixed  $passable
     * @return mixed
     */
    protected function handleException($passable, Throwable $e)
    {
        if (! $this->container->bound(ExceptionHandler::class) || ! $passable instanceof ServerRequestInterface) {
            throw $e;
        }

        $handler = $this->container->make(ExceptionHandler::class);

        $handler->report($e);

        return $handler->render($passable, $e);
    }
}
