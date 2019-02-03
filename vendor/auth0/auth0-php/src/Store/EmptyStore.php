<?php

namespace Auth0\SDK\Store;

/*
 * This file is part of Auth0-PHP package.
 *
 * (c) Auth0
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */



/**
 * This class is a mockup store, that discards the values, its a way of saying no store.
 *
 * @author Auth0
 */
class EmptyStore implements StoreInterface
{
    public function set($key, $value)
    {
    }

    public function get($key, $default = null)
    {
        return $default;
    }

    public function delete($key)
    {
    }
}
