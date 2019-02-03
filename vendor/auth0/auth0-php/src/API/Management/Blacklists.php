<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\API\Header\ContentType;

class Blacklists extends GenericResource
{
    /**
     *
     * @param  string $aud
     * @return mixed
     */
    public function getAll($aud)
    {
        return $this->apiClient->get()
            ->blacklists()
            ->tokens()
            ->withParam('aud', $aud)
            ->call();
    }

    /**
     *
     * @param  string $aud
     * @param  string $jti
     * @return mixed
     */
    public function blacklist($aud, $jti)
    {
        return $this->apiClient->post()
            ->blacklists()
            ->tokens()
            ->withHeader(new ContentType('application/json'))
            ->withBody(json_encode([
                'aud' => $aud,
                'jti' => $jti
            ]))
            ->call();
    }
}
