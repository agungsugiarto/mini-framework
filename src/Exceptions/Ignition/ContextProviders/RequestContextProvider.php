<?php

namespace Mini\Framework\Exceptions\Ignition\ContextProviders;

use Laminas\Diactoros\UploadedFile;
use Mini\Framework\Http\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Spatie\FlareClient\Context\ContextProvider;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Throwable;

class RequestContextProvider implements ContextProvider
{
    protected ?ServerRequestInterface $request;

    public function __construct(?ServerRequestInterface $request = null)
    {
        $this->request = $request ?? ServerRequestFactory::fromGlobals();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequest(): array
    {
        return [
            'url' => $this->request->getUri()->getPath(),
            'ip' => $this->request->getServerParams()['REMOTE_ADDR'],
            'method' => $this->request->getMethod(),
            'useragent' => $this->request->getHeaderLine('User-Agent'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getFiles(): array
    {
        if (is_null($this->request->getUploadedFiles())) {
            return [];
        }

        return $this->mapFiles($this->request->getUploadedFiles());
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array<string, string>
     */
    protected function mapFiles(array $files): array
    {
        return array_map(function ($file) {
            if (is_array($file)) {
                return $this->mapFiles($file);
            }

            if (! $file instanceof UploadedFile) {
                return;
            }

            try {
                $fileSize = $file->getSize();
            } catch (RuntimeException $e) {
                $fileSize = 0;
            }

            try {
                $mimeType = $file->getClientMediaType();
            } catch (InvalidArgumentException $e) {
                $mimeType = 'undefined';
            }

            return [
                'pathname' => $file->getStream()->detach(),
                'size' => $fileSize,
                'mimeType' => $mimeType,
            ];
        }, $files);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        try {
            $session = $this->request->getAttribute('session');
        } catch (Throwable $exception) {
            $session = [];
        }

        return $session ? $this->getValidSessionData($session) : [];
    }

    protected function getValidSessionData($session): array
    {
        if (! method_exists($session, 'all')) {
            return [];
        }

        try {
            json_encode($session->all());
        } catch (Throwable $e) {
            return [];
        }

        return $session->all();
    }

    /**
     * @return array<int|string, mixed
     */
    public function getCookies(): array
    {
        return $this->request->getCookieParams();
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeaders(): array
    {
        /** @var array<string, list<string|null>> $headers */
        $headers = $this->request->getHeaders();

        return array_filter(
            array_map(
                fn (array $header) => $header[0],
                $headers
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(): array
    {
        return [
            'queryString' => $this->request->getQueryParams(),
            'body' => $this->request->getBody()->getContents(),
            'files' => $this->getFiles(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'request' => $this->getRequest(),
            'request_data' => $this->getRequestData(),
            'headers' => $this->getHeaders(),
            'cookies' => $this->getCookies(),
            'session' => $this->getSession(),
        ];
    }
}
