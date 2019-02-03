<?php

namespace Auth0\SDK\Helpers\Cache;

interface CacheHandler
{

    /**
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key);

    /**
     *
     * @param string $key
     */
    public function delete($key);

    /**
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value);
}
