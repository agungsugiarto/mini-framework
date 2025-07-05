<?php

namespace Mini\Framework\Http\Middleware;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PipelineRequestHandler implements RequestHandlerInterface
{
     /**
     * Create a new pipeline request handler instance.
     */
    public function __construct(private Closure $next)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->next)($request);
    }
}
