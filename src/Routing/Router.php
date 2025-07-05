<?php

namespace Mini\Framework\Routing;

use Illuminate\Support\Arr;

class Router
{
    /**
     * The application instance.
     *
     * @var \Mini\Framework\Application
     */
    public $app;

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * All of the routes waiting to be registered.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * All of the named routes and URI pairs.
     *
     * @var array
     */
    public $namedRoutes = [];

    /**
     * Router constructor.
     *
     * @param  \Mini\Framework\Application  $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Register a set of routes with a set of shared attributes.
     *
     * @return void
     */
    public function group(array $attributes, \Closure $callback)
    {
        if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
            $attributes['middleware'] = explode('|', $attributes['middleware']);
        }

        $this->updateGroupStack($attributes);

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @return void
     */
    protected function updateGroupStack(array $attributes)
    {
        if (! empty($this->groupStack)) {
            $attributes = $this->mergeWithLastGroup($attributes);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return array
     */
    public function mergeGroup($new, $old)
    {
        $new['namespace'] = static::formatUsesPrefix($new, $old);

        $new['prefix'] = static::formatGroupPrefix($new, $old);

        if (isset($new['domain'])) {
            unset($old['domain']);
        }

        if (isset($old['as'])) {
            $new['as'] = $old['as'].(isset($new['as']) ? '.'.$new['as'] : '');
        }

        if (isset($old['suffix']) && ! isset($new['suffix'])) {
            $new['suffix'] = $old['suffix'];
        }

        return array_merge_recursive(Arr::except($old, ['namespace', 'prefix', 'as', 'suffix']), $new);
    }

    /**
     * Merge the given group attributes with the last added group.
     *
     * @param  array  $new
     * @return array
     */
    protected function mergeWithLastGroup($new)
    {
        return $this->mergeGroup($new, end($this->groupStack));
    }

    /**
     * Format the uses prefix for the new group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string|null
     */
    protected static function formatUsesPrefix($new, $old)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace']) && strpos($new['namespace'], '\\') !== 0
                ? trim($old['namespace'], '\\').'\\'.trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }

        return $old['namespace'] ?? null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string|null
     */
    protected static function formatGroupPrefix($new, $old)
    {
        $oldPrefix = $old['prefix'] ?? null;

        if (isset($new['prefix'])) {
            return trim($oldPrefix ?? '', '/').'/'.trim($new['prefix'], '/');
        }

        return $oldPrefix;
    }

    /**
     * Add a route to the collection.
     *
     * @param  array|string  $method
     * @param  string  $uri
     * @param  mixed  $action
     * @return void
     */
    public function addRoute($method, $uri, $action)
    {
        $action = $this->parseAction($action);

        $attributes = null;

        if ($this->hasGroupStack()) {
            $attributes = $this->mergeWithLastGroup([]);
        }

        if (isset($attributes) && is_array($attributes)) {
            if (isset($attributes['prefix'])) {
                $uri = trim($attributes['prefix'], '/').'/'.trim($uri, '/');
            }

            if (isset($attributes['suffix'])) {
                $uri = trim($uri, '/').rtrim($attributes['suffix'], '/');
            }

            $action = $this->mergeGroupAttributes($action, $attributes);
        }

        $uri = '/'.trim($uri, '/');

        if (isset($action['as'])) {
            $this->namedRoutes[$action['as']] = $uri;
        }

        if (is_array($method)) {
            foreach ($method as $verb) {
                $this->routes[$verb.$uri] = ['method' => $verb, 'uri' => $uri, 'action' => $action];
            }
        } else {
            $this->routes[$method.$uri] = ['method' => $method, 'uri' => $uri, 'action' => $action];
        }
    }

    /**
     * Parse the action into an array format.
     *
     * @param  mixed  $action
     * @return array
     */
    protected function parseAction($action)
    {
        if (is_string($action)) {
            // Handle single class name (invokable controller)
            // For string without @, always add @__invoke (will be determined if invokable after namespace merge)
            if (! str_contains($action, '@')) {
                return ['uses' => $action.'@__invoke'];
            }
            return ['uses' => $action];
        } elseif (is_array($action) && $this->isCallableArray($action)) {
            // Handle [class, method] syntax
            return ['uses' => $action[0].'@'.$action[1]];
        } elseif (is_array($action) && $this->isSingleClassArray($action)) {
            // Handle [class] syntax (single element array with class name)
            return ['uses' => $action[0].'@__invoke'];
        } elseif (is_array($action) && isset($action['uses']) && $this->isCallableArray($action['uses'])) {
            // Handle ['uses' => [class, method], 'middleware' => '...'] syntax
            $action['uses'] = $action['uses'][0].'@'.$action['uses'][1];

            if (isset($action['middleware']) && is_string($action['middleware'])) {
                $action['middleware'] = explode('|', $action['middleware']);
            }

            return $action;
        } elseif (is_array($action) && isset($action['uses']) && is_string($action['uses'])) {
            // Handle ['uses' => 'ClassName', 'middleware' => '...'] syntax for invokable
            if (! str_contains($action['uses'], '@')) {
                $action['uses'] = $action['uses'].'@__invoke';
            }

            if (isset($action['middleware']) && is_string($action['middleware'])) {
                $action['middleware'] = explode('|', $action['middleware']);
            }

            return $action;
        } elseif (! is_array($action)) {
            return [$action];
        }

        if (isset($action['middleware']) && is_string($action['middleware'])) {
            $action['middleware'] = explode('|', $action['middleware']);
        }

        return $action;
    }

    /**
     * Determine if the given array is a callable array [class, method].
     *
     * @param  mixed  $action
     * @return bool
     */
    protected function isCallableArray($action)
    {
        return is_array($action)
            && count($action) === 2
            && isset($action[0])
            && isset($action[1])
            && is_string($action[0])
            && is_string($action[1])
            && class_exists($action[0])
            && ! isset($action['uses'])
            && ! isset($action['middleware'])
            && ! isset($action['as'])
            && ! isset($action['prefix'])
            && ! isset($action['namespace']);
    }

    /**
     * Determine if the given array is a single class array [ClassName].
     *
     * @param  mixed  $action
     * @return bool
     */
    protected function isSingleClassArray($action)
    {
        return is_array($action)
            && count($action) === 1
            && isset($action[0])
            && is_string($action[0])
            && class_exists($action[0])
            && ! isset($action['uses'])
            && ! isset($action['middleware'])
            && ! isset($action['as'])
            && ! isset($action['prefix'])
            && ! isset($action['namespace']);
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return ! empty($this->groupStack);
    }

    /**
     * Merge the group attributes into the action.
     *
     * @return array
     */
    protected function mergeGroupAttributes(array $action, array $attributes)
    {
        $namespace = $attributes['namespace'] ?? null;
        $middleware = $attributes['middleware'] ?? null;
        $as = $attributes['as'] ?? null;

        return $this->mergeNamespaceGroup(
            $this->mergeMiddlewareGroup(
                $this->mergeAsGroup($action, $as),
                $middleware),
            $namespace
        );
    }

    /**
     * Merge the namespace group into the action.
     *
     * @param  string  $namespace
     * @return array
     */
    protected function mergeNamespaceGroup(array $action, $namespace = null)
    {
        if (isset($namespace, $action['uses'])) {
            $action['uses'] = $this->prependGroupNamespace($action['uses'], $namespace);
        }

        return $action;
    }

    /**
     * Prepend the namespace onto the use clause.
     *
     * @param  string  $class
     * @param  string  $namespace
     * @return string
     */
    protected function prependGroupNamespace($class, $namespace = null)
    {
        if (! is_string($class)) {
            return $class;
        }

        $parts = explode('@', $class);
        $className = $parts[0];
        $method = $parts[1] ?? null;

        $shouldAddNamespace = $namespace !== null
            && strpos($className, '\\') !== 0
            && strpos($className, '\\') === false;

        if ($shouldAddNamespace) {
            $className = $namespace.'\\'.$className;
        }

        return $method ? $className.'@'.$method : $className;
    }

    /**
     * Merge the middleware group into the action.
     *
     * @param  array  $middleware
     * @return array
     */
    protected function mergeMiddlewareGroup(array $action, $middleware = null)
    {
        if (isset($middleware)) {
            if (isset($action['middleware'])) {
                $action['middleware'] = array_merge($middleware, $action['middleware']);
            } else {
                $action['middleware'] = $middleware;
            }
        }

        return $action;
    }

    /**
     * Merge the as group into the action.
     *
     * @param  string  $as
     * @return array
     */
    protected function mergeAsGroup(array $action, $as = null)
    {
        if (isset($as) && ! empty($as)) {
            if (isset($action['as'])) {
                $action['as'] = $as.'.'.$action['as'];
            } else {
                $action['as'] = $as;
            }
        }

        return $action;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function head($uri, $action)
    {
        $this->addRoute('HEAD', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function get($uri, $action)
    {
        $this->addRoute('GET', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function post($uri, $action)
    {
        $this->addRoute('POST', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function put($uri, $action)
    {
        $this->addRoute('PUT', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function patch($uri, $action)
    {
        $this->addRoute('PATCH', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function delete($uri, $action)
    {
        $this->addRoute('DELETE', $uri, $action);

        return $this;
    }

    /**
     * Register a route with the application.
     *
     * @param  string  $uri
     * @param  mixed  $action
     * @return $this
     */
    public function options($uri, $action)
    {
        $this->addRoute('OPTIONS', $uri, $action);

        return $this;
    }

    /**
     * Get the raw routes for the application.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
