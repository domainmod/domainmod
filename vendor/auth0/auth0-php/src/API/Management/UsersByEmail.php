<?php

namespace Auth0\SDK\API\Management;

class UsersByEmail extends GenericResource
{
    public function get($params = [])
    {
        $client = $this->apiClient->get()
            ->addPath('users-by-email');

        foreach ($params as $param => $value) {
            $client->withParam($param, $value);
        }

        return $client->call();
    }
}
