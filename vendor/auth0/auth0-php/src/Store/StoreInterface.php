<?php
namespace Auth0\SDK\Store;

/*
 * This file is part of Auth0-PHP package.
 *
 * (c) Auth0
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * This interface must be implemented by stores
 *
 * @author Auth0
 */
interface StoreInterface
{
    /**
     * Set a value on the store
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value);
    /**
     * Get a value from the store by a given key
     *
     * @param  string     $key
     * @param  mixed|null $default
     * @return mixed
     */
    public function get($key, $default = null);
    /**
     * Remove a value from the store
     *
     * @param  string $key
     * @return mixed
     */
    public function delete($key);
}
