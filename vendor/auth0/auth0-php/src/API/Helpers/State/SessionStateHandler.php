<?php

namespace Auth0\SDK\API\Helpers\State;

use Auth0\SDK\Store\StoreInterface;

/*
 * This file is part of Auth0-PHP package.
 *
 * (c) Auth0
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

/**
 * Session based implementation of StateHandler.
 *
 * @author Auth0
 */
class SessionStateHandler implements StateHandler
{
    const STATE_NAME = 'webauth_state';

    private $store;

    /**
     *
     * @param StoreInterface $store
     */
    public function __construct(StoreInterface $store)
    {
        $this->store = $store;
    }

    /**
     * Generate state value to be used for the state param value during authorization.
     *
     * @return string
     */
    public function issue()
    {
        $state = uniqid('', true);
        $this->store($state);
        return $state;
    }

    /**
     * Store a given state value to be used for the state param value during authorization.
     *
     * @param string $state
     *
     * @return mixed|void
     */
    public function store($state)
    {
        $this->store->set(self::STATE_NAME, $state);
    }

    /**
     * Perform validation of the returned state with the previously generated state.
     *
     * @param string $state
     *
     * @return boolean
     */
    public function validate($state)
    {
        $valid = $this->store->get(self::STATE_NAME) == $state;
        $this->store->delete(self::STATE_NAME);
        return $valid;
    }
}
