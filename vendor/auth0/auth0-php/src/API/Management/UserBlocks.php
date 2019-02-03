<?php

namespace Auth0\SDK\API\Management;

class UserBlocks extends GenericResource
{
    /**
     *
     * @param  string $user_id
     * @return mixed
     */
    public function get($user_id)
    {
        return $this->apiClient->get()
        ->addPath('user-blocks', $user_id)
        ->call();
    }

    /**
     *
     * @param  string $identifier
     * @return mixed
     */
    public function getByIdentifier($identifier)
    {
        return $this->apiClient->get()
        ->addPath('user-blocks')
        ->withParam('identifier', $identifier)
        ->call();
    }

    /**
     *
     * @param  string $user_id
     * @return mixed
     */
    public function unblock($user_id)
    {
        return $this->apiClient->delete()
        ->addPath('user-blocks', $user_id)
        ->call();
    }

    /**
     *
     * @param  string $identifier
     * @return mixed
     */
    public function unblockByIdentifier($identifier)
    {
        return $this->apiClient->delete()
        ->addPath('user-blocks')
        ->withParam('identifier', $identifier)
        ->call();
    }
}
