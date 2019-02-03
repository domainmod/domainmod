<?php namespace Auth0\SDK\API\Management;

use Auth0\SDK\API\Helpers\ApiClient;

class GenericResource
{

    /**
     *
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * GenericResource constructor.
     *
     * @param ApiClient $apiClient
     */
    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     *
     * @return ApiClient
     */
    public function getApiClient()
    {
        return $this->apiClient;
    }
}
