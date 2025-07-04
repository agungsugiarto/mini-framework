<?php

namespace Mini\Framework\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareHandler implements RequestHandlerInterface
{
    /**
     * Create a new middleware handler instance.
     *
     * @param callable $next
     */
    public function __construct(private $next)
    {
    }

    /**
     * Handle the request by calling the wrapped callable.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->next)($request);
    }
}