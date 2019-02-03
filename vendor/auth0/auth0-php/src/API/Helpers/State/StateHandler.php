<?php

namespace Auth0\SDK\API\Helpers\State;

/*
 * This file is part of Auth0-PHP package.
 *
 * (c) Auth0
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

/**
 * This interface must be implemented by state handlers.
 *
 * @author Auth0
 */
interface StateHandler
{

    /**
     * Generate state value to be used for the state param value during authorization.
     *
     * @return string || null
     */
    public function issue();

    /**
     * Store a given state value to be used for the state param value during authorization.
     *
     * @param $state
     *
     * @return mixed
     */
    public function store($state);

    /**
     * Perform validation of the returned state with the previously generated state.
     *
     * @param string $state
     *
     * @return boolean result
     *
     * @throws \Exception
     */
    public function validate($state);
}
