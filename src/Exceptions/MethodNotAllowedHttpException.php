<?php

namespace Mini\Framework\Exceptions;

class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param  string[]  $allow  An array of allowed methods
     * @param  string|null  $message  The internal exception message
     * @param  \Throwable|null  $previous  The previous exception
     * @param  int  $code  The internal exception code
     */
    public function __construct(array $allow, ?string $message = '', ?\Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        $headers['Allow'] = strtoupper(implode(', ', $allow));

        parent::__construct(405, $message, $previous, $headers, $code);
    }
}
