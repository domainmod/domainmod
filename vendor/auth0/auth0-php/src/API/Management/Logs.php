<?php

namespace Auth0\SDK\API\Management;

/**
 * Class Logs.
 * Access to the v2 Management API Logs endpoint.
 *
 * @package Auth0\SDK\API\Management
 */
class Logs extends GenericResource
{
    /**
     * Get a single Log event.
     * Required scope: "read:logs"
     *
     * @param string $log_id Log entry ID to get.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by Guzzle for API errors.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Logs/get_logs_by_id
     */
    public function get($log_id)
    {
        return $this->apiClient->method('get')
            ->addPath('logs', $log_id)
            ->call();
    }

    /**
     * Retrieves log entries that match the specified search criteria (or list all entries if no criteria is used).
     * Required scope: "read:logs"
     *
     * @param array $params Log search parameters to send:
     *      - Including a restricted "fields" parameter can speed up API calls significantly.
     *      - Results are paged by default; pass a "page" and "per_page" param to adjust what results are shown.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by Guzzle for API errors.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Logs/get_logs
     */
    public function search(array $params = [])
    {
        return $this->apiClient->method('get')
            ->addPath('logs')
            ->withDictParams($params)
            ->call();
    }
}
