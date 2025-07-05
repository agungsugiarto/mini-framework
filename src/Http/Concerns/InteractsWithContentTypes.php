<?php

namespace Mini\Framework\Http\Concerns;

use Illuminate\Support\Str;

trait InteractsWithContentTypes
{
    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        return Str::contains($this->getHeaderLine('Content-Type') ?? '', ['/json', '+json']);
    }

    /**
     * Returns true if the request is an XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     *
     * @return bool
     */
    public function isXmlHttpRequest()
    {
        return $this->getHeaderLine('X-Requested-With') == 'XMLHttpRequest';
    }

    /**
     * Determine if the request is the result of an AJAX call.
     *
     * @return bool
     */
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Determine if the request is the result of a PJAX call.
     *
     * @return bool
     */
    public function pjax()
    {
        return $this->getHeaderLine('X-PJAX') == true;
    }
}
