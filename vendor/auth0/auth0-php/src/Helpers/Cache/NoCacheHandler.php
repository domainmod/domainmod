<?php

namespace Auth0\SDK\Helpers\Cache;

class NoCacheHandler implements CacheHandler
{

    /**
     *
     * @param  string $key
     * @return null
     */
    public function get($key)
    {
        return null;
    }

    /**
     *
     * @param string $key
     */
    public function delete($key)
    {
    }

    /**
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
    }
}
