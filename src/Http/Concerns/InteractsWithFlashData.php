<?php

namespace Mini\Framework\Http\Concerns;

use Illuminate\Contracts\Session\Session;
use RuntimeException;

trait InteractsWithFlashData
{
    /**
     * Retrieve an old input item.
     *
     * @param string|null       $key
     * @param string|array|null $default
     *
     * @return string|array|null
     */
    public function old($key = null, $default = null)
    {
        return $this->hasSession() ? $this->session()->getOldInput($key, $default) : $default;
    }

    /**
     * Flash the input for the current request to the session.
     *
     * @return void
     */
    public function flash()
    {
        $this->session()->flashInput($this->input());
    }

    /**
     * Flash only some of the input to the session.
     *
     * @param array|mixed $keys
     *
     * @return void
     */
    public function flashOnly($keys)
    {
        $this->session()->flashInput(
            $this->only(is_array($keys) ? $keys : func_get_args())
        );
    }

    /**
     * Flash only some of the input to the session.
     *
     * @param array|mixed $keys
     *
     * @return void
     */
    public function flashExcept($keys)
    {
        $this->session()->flashInput(
            $this->except(is_array($keys) ? $keys : func_get_args())
        );
    }

    /**
     * Flush all of the old input from the session.
     *
     * @return void
     */
    public function flush()
    {
        $this->session()->flashInput([]);
    }

    /**
     * Get the session associated with the request.
     *
     * @return \Illuminate\Session\Store
     *
     * @throws RuntimeException
     */
    public function session()
    {
        if (! $this->hasSession()) {
            throw new RuntimeException('Session store not set on request.');
        }

        return $this->getAttribute('session');
    }

    /**
     * Whether the request contains a Session object.
     *
     * This method does not give any information about the state of the session object,
     * like whether the session is started or not. It is just a way to check if this Request
     * is associated with a Session instance.
     *
     * @return bool
     */
    public function hasSession()
    {
        return $this->getAttribute('session') instanceof Session;
    }
}
