<?php

namespace Mini\Framework\Exceptions;

class HttpException extends \RuntimeException implements HttpExceptionInterface
{
    private $statusCode;

    private $headers;

    public function __construct(int $statusCode, ?string $message = '', ?\Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set response headers.
     *
     * @param  array  $headers  Response headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }
}
