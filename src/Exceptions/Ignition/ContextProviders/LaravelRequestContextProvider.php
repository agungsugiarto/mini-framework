<?php

namespace Mini\Framework\Exceptions\Ignition\ContextProviders;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class LaravelRequestContextProvider extends RequestContextProvider
{
    protected ?ServerRequestInterface $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /** @return array<string, mixed>|null */
    public function getUser(): ?array
    {
        try {
            /** @var object|null $user */
            /** @phpstan-ignore-next-line */
            $user = $this->request?->user();

            if (! $user) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        try {
            if (method_exists($user, 'toFlare')) {
                return $user->toFlare();
            }

            if (method_exists($user, 'toArray')) {
                return $user->toArray();
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function getRoute(): ?array
    {
        /**
         * @phpstan-ignore-next-line
         *
         * @var ServerRequestInterface|null $route
         */
        $route = $this->request->route();

        if (! $route) {
            return null;
        }

        return [
            'route' => $route[1]['as'] ?? '',
            'routeParameters' => $route[2] ?? [],
            'controllerAction' => $route[1]['uses'] ?? '',
            'middleware' => array_values($route[1]['middleware'] ?? []),
        ];
    }

    /** @return array<int, mixed> */
    public function toArray(): array
    {
        $properties = parent::toArray();

        if ($route = $this->getRoute()) {
            $properties['route'] = $route;
        }

        if ($user = $this->getUser()) {
            $properties['user'] = $user;
        }

        return $properties;
    }
}
