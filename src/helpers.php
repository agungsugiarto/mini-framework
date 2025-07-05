<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Laminas\Diactoros\Exception\InvalidArgumentException;
use Laminas\Diactoros\Exception\UnrecognizedProtocolVersionException;
use Laminas\Diactoros\Response\RedirectResponse;
use Laminas\Diactoros\UploadedFile;
use Mini\Framework\Bus\PendingDispatch;
use Mini\Framework\Exceptions\Ignition\Renderers\ErrorPageRenderer;
use Mini\Framework\Http\Redirector;
use Psr\Http\Message\UploadedFileInterface;

if (! function_exists('ddd')) {
    function ddd()
    {
        $args = func_get_args();

        if (count($args) === 0) {
            throw new Exception('You should pass at least 1 argument to `ddd`');
        }

        call_user_func_array('dump', $args);

        $renderer = app()->make(ErrorPageRenderer::class);

        $exception = new Exception('Dump, Die, Debug');

        $renderer->render($exception);

        exit();
    }
}

if (! function_exists('abort')) {
    /**
     * Throw an HttpException with the given data.
     *
     * @param  int  $code
     * @param  string  $message
     * @return void
     *
     * @throws Mini\Framework\Exceptions\HttpException
     * @throws Mini\Framework\Exceptions\NotFoundException
     */
    function abort($code, $message = '', array $headers = [])
    {
        app()->abort($code, $message, $headers);
    }
}

if (! function_exists('abort_if')) {
    /**
     * Throw an HttpException with the given data if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  int  $code
     * @param  string  $message
     * @return void
     *
     * @throws Mini\Framework\Exceptions\HttpException
     * @throws Mini\Framework\Exceptions\NotFoundException
     */
    function abort_if($boolean, $code, $message = '', array $headers = [])
    {
        if ($boolean) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('abort_unless')) {
    /**
     * Throw an HttpException with the given data unless the given condition is true.
     *
     * @param  bool  $boolean
     * @param  int  $code
     * @param  string  $message
     * @return void
     *
     * @throws Mini\Framework\Exceptions\HttpException
     * @throws Mini\Framework\Exceptions\NotFoundException
     */
    function abort_unless($boolean, $code, $message = '', array $headers = [])
    {
        if (! $boolean) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $make
     * @return mixed|Mini\Framework\Application
     */
    function app($make = null, array $parameters = [])
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}

if (! function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     * @return string
     */
    function base_path($path = '')
    {
        return app()->basePath().($path ? '/'.$path : $path);
    }
}

if (! function_exists('broadcast')) {
    /**
     * Begin broadcasting an event.
     *
     * @param  mixed|null  $event
     * @return Illuminate\Broadcasting\PendingBroadcast
     */
    function broadcast($event = null)
    {
        return app(BroadcastFactory::class)->event($event);
    }
}

if (! function_exists('decrypt')) {
    /**
     * Decrypt the given value.
     *
     * @param  string  $value
     * @return string
     */
    function decrypt($value)
    {
        return app('encrypter')->decrypt($value);
    }
}

if (! function_exists('dispatch')) {
    /**
     * Dispatch a job to its appropriate handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    function dispatch($job)
    {
        return new PendingDispatch($job);
    }
}

if (! function_exists('dispatch_now')) {
    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param  mixed  $job
     * @param  mixed  $handler
     * @return mixed
     */
    function dispatch_now($job, $handler = null)
    {
        return app(Dispatcher::class)->dispatchNow($job, $handler);
    }
}

if (! function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('config');
        }

        if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

if (! function_exists('database_path')) {
    /**
     * Get the path to the database directory of the install.
     *
     * @param  string  $path
     * @return string
     */
    function database_path($path = '')
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('encrypt')) {
    /**
     * Encrypt the given value.
     *
     * @param  string  $value
     * @return string
     */
    function encrypt($value)
    {
        return app('encrypter')->encrypt($value);
    }
}

if (! function_exists('event')) {
    /**
     * Dispatch an event and call the listeners.
     *
     * @param  object|string  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    function event($event, $payload = [], $halt = false)
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}

if (! function_exists('info')) {
    /**
     * Write some information to the log.
     *
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    function info($message, $context = [])
    {
        return app('Psr\Log\LoggerInterface')->info($message, $context);
    }
}

if (! function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param  string|null  $to
     * @param  int  $status
     * @param  array  $headers
     * @return Redirector|RedirectResponse
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        $redirector = new Redirector(app());

        if (is_null($to)) {
            return $redirector;
        }

        return $redirector->to($to, $status, $headers, $secure);
    }
}

if (! function_exists('report')) {
    /**
     * Report an exception.
     *
     * @return void
     */
    function report(Throwable $exception)
    {
        app(ExceptionHandler::class)->report($exception);
    }
}

if (! function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     *
     * @param  string  $path
     * @return string
     */
    function resource_path($path = '')
    {
        return app()->resourcePath($path);
    }
}

if (! function_exists('route')) {
    /**
     * Generate a URL to a named route.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @param  bool|null  $secure
     * @return string
     */
    function route($name, $parameters = [], $secure = null)
    {
        return app('url')->route($name, $parameters, $secure);
    }
}

if (! function_exists('session')) {
    /**
     * Get / set the specified session value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|Illuminate\Session\Store|Illuminate\Session\SessionManager
     */
    function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('session');
        }

        if (is_array($key)) {
            return app('session')->put($key);
        }

        return app('session')->get($key, $default);
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function storage_path($path = '')
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param  string|null  $id
     * @param  array  $replace
     * @param  string|null  $locale
     * @return Illuminate\Contracts\Translation\Translator|string|array|null
     */
    function trans($id = null, $replace = [], $locale = null)
    {
        if (is_null($id)) {
            return app('translator');
        }

        return app('translator')->get($id, $replace, $locale);
    }
}

if (! function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return string|array|null
     */
    function __($key, $replace = [], $locale = null)
    {
        return app('translator')->get($key, $replace, $locale);
    }
}

if (! function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param  string  $id
     * @param  int|array|Countable  $number
     * @param  string|null  $locale
     * @return string
     */
    function trans_choice($id, $number, array $replace = [], $locale = null)
    {
        return app('translator')->choice($id, $number, $replace, $locale);
    }
}

if (! function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param  string  $path
     * @param  mixed  $parameters
     * @param  bool|null  $secure
     * @return string
     */
    function url($path = null, $parameters = [], $secure = null)
    {
        return app('url')->to($path, $parameters, $secure);
    }
}

if (! function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @return Illuminate\Contracts\Validation\Validator
     */
    function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
    {
        $factory = app('validator');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data, $rules, $messages, $customAttributes);
    }
}

if (! function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  array  $mergeData
     * @return Illuminate\View\View
     */
    function view($view = null, $data = [], $mergeData = [])
    {
        $factory = app('view');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}

if (! function_exists('invade')) {
    /**
     * This class offers an invade function that will allow you to read/write private properties of an object.
     * It will also allow you to set, get and call private methods.
     *
     * @see https://github.com/spatie/invade/blob/main/src/Invader.php
     */
    function invade(object $object)
    {
        return new class($object)
        {
            /** @var object */
            public $object;

            /** @var ReflectionClass */
            public $reflected;

            public function __construct(object $object)
            {
                $this->object = $object;
                $this->reflected = new ReflectionClass($object);
            }

            public function __get($name)
            {
                $property = $this->reflected->getProperty($name);

                $property->setAccessible(true);

                return $property->getValue($this->object);
            }

            public function __set($name, $value)
            {
                $property = $this->reflected->getProperty($name);

                $property->setAccessible(true);

                $property->setValue($this->object, $value);
            }

            public function __call($name, $arguments)
            {
                $method = $this->reflected->getMethod($name);

                $method->setAccessible(true);

                return $method->invoke($this->object, ...$arguments);
            }
        };
    }
}

if (! function_exists('normalizeServer')) {
    /**
     * Marshal the $_SERVER array.
     *
     * Pre-processes and returns the $_SERVER superglobal. In particularly, it
     * attempts to detect the Authorization header, which is often not aggregated
     * correctly under various SAPI/httpd combinations.
     *
     * @param  callable|null  $apacheRequestHeaderCallback  Callback that can be used to
     *                                                      retrieve Apache request headers. This defaults to
     *                                                      `apache_request_headers` under the Apache mod_php.
     * @return array either $server verbatim, or with an added HTTP_AUTHORIZATION header
     */
    function normalizeServer(array $server, ?callable $apacheRequestHeaderCallback = null): array
    {
        if ($apacheRequestHeaderCallback === null && is_callable('apache_request_headers')) {
            $apacheRequestHeaderCallback = 'apache_request_headers';
        }

        // If the HTTP_AUTHORIZATION value is already set, or the callback is not
        // callable, we return verbatim
        if (
            isset($server['HTTP_AUTHORIZATION'])
            || ! is_callable($apacheRequestHeaderCallback)
        ) {
            return $server;
        }

        $apacheRequestHeaders = $apacheRequestHeaderCallback();
        if (isset($apacheRequestHeaders['Authorization'])) {
            $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['Authorization'];

            return $server;
        }

        if (isset($apacheRequestHeaders['authorization'])) {
            $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['authorization'];

            return $server;
        }

        return $server;
    }
}

if (! function_exists('normalizeUploadedFiles')) {
    /**
     * Normalize uploaded files.
     *
     * Transforms each value into an UploadedFile instance, and ensures that nested
     * arrays are normalized.
     *
     * @return UploadedFileInterface[]
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    function normalizeUploadedFiles(array $files): array
    {
        /**
         * Traverse a nested tree of uploaded file specifications.
         *
         * @param  string[]|array[]  $tmpNameTree
         * @param  int[]|array[]  $sizeTree
         * @param  int[]|array[]  $errorTree
         * @param  string[]|array[]|null  $nameTree
         * @param  string[]|array[]|null  $typeTree
         * @return UploadedFile[]|array[]
         */
        $recursiveNormalize = static function (
            array $tmpNameTree,
            array $sizeTree,
            array $errorTree,
            ?array $nameTree = null,
            ?array $typeTree = null
        ) use (&$recursiveNormalize): array {
            $normalized = [];
            foreach ($tmpNameTree as $key => $value) {
                if (is_array($value)) {
                    // Traverse
                    $normalized[$key] = $recursiveNormalize(
                        $tmpNameTree[$key],
                        $sizeTree[$key],
                        $errorTree[$key],
                        $nameTree[$key] ?? null,
                        $typeTree[$key] ?? null
                    );

                    continue;
                }
                $normalized[$key] = createUploadedFile([
                    'tmp_name' => $tmpNameTree[$key],
                    'size' => $sizeTree[$key],
                    'error' => $errorTree[$key],
                    'name' => $nameTree[$key] ?? null,
                    'type' => $typeTree[$key] ?? null,
                ]);
            }

            return $normalized;
        };

        /**
         * Normalize an array of file specifications.
         *
         * Loops through all nested files (as determined by receiving an array to the
         * `tmp_name` key of a `$_FILES` specification) and returns a normalized array
         * of UploadedFile instances.
         *
         * This function normalizes a `$_FILES` array representing a nested set of
         * uploaded files as produced by the php-fpm SAPI, CGI SAPI, or mod_php
         * SAPI.
         *
         * @param  array  $files
         * @return UploadedFile[]
         */
        $normalizeUploadedFileSpecification = static function (array $files = []) use (&$recursiveNormalize): array {
            if (
                ! isset($files['tmp_name']) || ! is_array($files['tmp_name'])
                || ! isset($files['size']) || ! is_array($files['size'])
                || ! isset($files['error']) || ! is_array($files['error'])
            ) {
                throw new InvalidArgumentException(sprintf(
                    '$files provided to %s MUST contain each of the keys "tmp_name",'
                        .' "size", and "error", with each represented as an array;'
                        .' one or more were missing or non-array values',
                    __FUNCTION__
                ));
            }

            return $recursiveNormalize(
                $files['tmp_name'],
                $files['size'],
                $files['error'],
                $files['name'] ?? null,
                $files['type'] ?? null
            );
        };

        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;

                continue;
            }

            if (is_array($value) && isset($value['tmp_name']) && is_array($value['tmp_name'])) {
                $normalized[$key] = $normalizeUploadedFileSpecification($value);

                continue;
            }

            if (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = createUploadedFile($value);

                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = normalizeUploadedFiles($value);

                continue;
            }

            throw new InvalidArgumentException('Invalid value in files specification');
        }

        return $normalized;
    }
}

if (! function_exists('createUploadedFile')) {
    /**
     * Create an uploaded file instance from an array of values.
     *
     * @param  array  $spec  a single $_FILES entry
     *
     * @throws InvalidArgumentException if one or more of the tmp_name,
     *                                  size, or error keys are missing from $spec
     */
    function createUploadedFile(array $spec): UploadedFile
    {
        if (
            ! isset($spec['tmp_name'])
            || ! isset($spec['size'])
            || ! isset($spec['error'])
        ) {
            throw new InvalidArgumentException(sprintf(
                '$spec provided to %s MUST contain each of the keys "tmp_name",'
                    .' "size", and "error"; one or more were missing',
                __FUNCTION__
            ));
        }

        return new UploadedFile(
            $spec['tmp_name'],
            (int) $spec['size'],
            $spec['error'],
            $spec['name'] ?? null,
            $spec['type'] ?? null
        );
    }
}

if (! function_exists('marshalHeadersFromSapi')) {
    /**
     * @param  array  $server  values obtained from the SAPI (generally `$_SERVER`)
     * @return array Header/value pairs
     */
    function marshalHeadersFromSapi(array $server): array
    {
        $contentHeaderLookup = isset($server['LAMINAS_DIACTOROS_STRICT_CONTENT_HEADER_LOOKUP'])
            ? static function (string $key): bool {
                static $contentHeaders = [
                    'CONTENT_TYPE' => true,
                    'CONTENT_LENGTH' => true,
                    'CONTENT_MD5' => true,
                ];

                return isset($contentHeaders[$key]);
            }
        : static fn (string $key): bool => str_starts_with($key, 'CONTENT_');

        $headers = [];
        foreach ($server as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if (str_starts_with($key, 'REDIRECT_')) {
                $key = substr($key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (array_key_exists($key, $server)) {
                    continue;
                }
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtr(strtolower(substr($key, 5)), '_', '-');
                $headers[$name] = $value;

                continue;
            }

            if ($contentHeaderLookup($key)) {
                $name = strtr(strtolower($key), '_', '-');
                $headers[$name] = $value;

                continue;
            }
        }

        return $headers;
    }
}

if (! function_exists('parseCookieHeader')) {
    /**
     * Parse a cookie header according to RFC 6265.
     *
     * PHP will replace special characters in cookie names, which results in other cookies not being available due to
     * overwriting. Thus, the server request should take the cookies from the request header instead.
     *
     * @param  string  $cookieHeader  a string cookie header value
     * @return array<non-empty-string, string> key/value cookie pairs
     */
    function parseCookieHeader($cookieHeader): array
    {
        preg_match_all('(
            (?:^\\n?[ \t]*|;[ ])
            (?P<name>[!#$%&\'*+-.0-9A-Z^_`a-z|~]+)
            =
            (?P<DQUOTE>"?)
                (?P<value>[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)
            (?P=DQUOTE)
            (?=\\n?[ \t]*$|;[ ])
        )x', $cookieHeader, $matches, PREG_SET_ORDER);

        $cookies = [];

        foreach ($matches as $match) {
            $cookies[$match['name']] = urldecode($match['value']);
        }

        return $cookies;
    }
}

if (! function_exists('marshalMethodFromSapi')) {
    /**
     * Retrieve the request method from the SAPI parameters.
     */
    function marshalMethodFromSapi(array $server): string
    {
        return $server['REQUEST_METHOD'] ?? 'GET';
    }
}

if (! function_exists('marshalProtocolVersionFromSapi')) {
    /**
     * Return HTTP protocol version (X.Y) as discovered within a `$_SERVER` array.
     *
     * @throws UnrecognizedProtocolVersionException if the
     *                                              $server['SERVER_PROTOCOL'] value is malformed
     */
    function marshalProtocolVersionFromSapi(array $server): string
    {
        if (! isset($server['SERVER_PROTOCOL'])) {
            return '1.1';
        }

        if (! preg_match('#^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $matches)) {
            throw UnrecognizedProtocolVersionException::forVersion(
                (string) $server['SERVER_PROTOCOL']
            );
        }

        return $matches['version'];
    }
}
