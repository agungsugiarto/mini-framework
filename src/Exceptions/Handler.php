<?php

namespace Mini\Framework\Exceptions;

use Exception;
use Illuminate\Console\View\Components\BulletList;
use Illuminate\Console\View\Components\Error;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\ExceptionRenderer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Throwable;

class Handler implements ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * Report or log an exception.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @return void
     *
     * @throws Exception
     */
    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            if ($e->report() !== false) {
                return;
            }
        }

        try {
            $logger = app(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e; // throw the original exception
        }

        $logger->error($e->getMessage(), ['exception' => $e]);
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  Throwable|HttpExceptionInterface  $e
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws Throwable|HttpExceptionInterface
     */
    public function render($request, Throwable $e)
    {
        if (method_exists($e, 'render')) {
            return $e->render($request);
        }

        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        } elseif ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        } elseif ($e instanceof ValidationException && $e->getResponse()) {
            return $e->getResponse();
        }

        return $request->isJson() || $request->ajax()
            ? $this->prepareJsonResponse($request, $e)
            : $this->prepareResponse($request, $e);
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  Throwable|HttpExceptionInterface  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        return new JsonResponse(
            $this->convertExceptionToArray($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @return array
     */
    protected function convertExceptionToArray(Throwable $e)
    {
        return config('app.debug', false) ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->map(function ($trace) {
                return Arr::except($trace, ['args']);
            })->all(),
        ] : [
            'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
        ];
    }

    /**
     * Prepare a response for the given exception.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  Throwable|HttpExceptionInterface  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function prepareResponse($request, Throwable $e)
    {
        $response = new HtmlResponse(
            $this->renderExceptionContent($e, config('app.debug', false)),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : []
        );

        $response->exception = $e;

        return $response;
    }

    /**
     * Get the response content for the given exception.
     *
     * @return string
     */
    protected function renderExceptionContent(Throwable $e, bool $debug)
    {
        try {
            return $debug && app()->has(ExceptionRenderer::class)
                ? $this->renderExceptionWithCustomRenderer($e)
                : $this->renderExceptionWithSymfony($e, $debug);
        } catch (Throwable $e) {
            return $this->renderExceptionWithSymfony($e, $debug);
        }
    }

    /**
     * Render an exception to a string using the registered `ExceptionRenderer`.
     *
     * @return string
     */
    protected function renderExceptionWithCustomRenderer(Throwable $e)
    {
        return app(ExceptionRenderer::class)->render($e);
    }

    /**
     * Render an exception to a string using Symfony.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @param  bool  $debug
     * @return string
     */
    protected function renderExceptionWithSymfony(Throwable $e, $debug)
    {
        $renderer = new HtmlErrorRenderer($debug);

        return $renderer->render($e)->getAsString();
    }

    /**
     * Render an exception to the console.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  Throwable|HttpExceptionInterface  $e
     * @return void
     */
    public function renderForConsole($output, Throwable $e)
    {
        if ($e instanceof CommandNotFoundException) {
            $message = str($e->getMessage())->explode('.')->first();

            if (! empty($alternatives = $e->getAlternatives())) {
                $message .= '. Did you mean one of these?';

                with(new Error($output))->render($message);
                with(new BulletList($output))->render($e->getAlternatives());

                $output->writeln('');
            } else {
                with(new Error($output))->render($message);
            }

            return;
        }

        (new ConsoleApplication)->renderThrowable($e, $output);
    }

    /**
     * Determine if the given exception is an HTTP exception.
     *
     * @param  Throwable|HttpExceptionInterface  $e
     * @return bool
     */
    protected function isHttpException(Throwable $e)
    {
        return $e instanceof HttpExceptionInterface;
    }
}
