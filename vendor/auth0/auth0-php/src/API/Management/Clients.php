<?php

namespace Auth0\SDK\API\Management;

/**
 * Class Clients.
 * Handles requests to the Clients endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management
 */
class Clients extends GenericResource
{
    /**
     * Get all Clients by page.
     * Required scopes:
     *      - "read:clients" - For any call to this endpoint.
     *      - "read:client_keys" - To retrieve "client_secret" and "encryption_key" attributes.
     *
     * @param null|string|array $fields         Fields to include or exclude from the result:
     *      - Including only the fields required can speed up API calls significantly.
     *      - Arrays will be converted to comma-separated strings.
     * @param null|boolean      $include_fields True to include $fields, false to exclude $fields.
     * @param null|integer      $page           Page number to get, zero-based.
     * @param null|integer      $per_page       Number of results to get, null to return the default number.
     * @param array             $add_params     Additional API parameters, over-written by function params.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Clients/get_clients
     */
    public function getAll(
        $fields = null,
        $include_fields = null,
        $page = null,
        $per_page = null,
        array $add_params = []
    )
    {
        // Set additional parameters first so they are over-written by function parameters.
        $params = is_array($add_params) ? $add_params : [];

        // Results fields.
        if (! empty($fields)) {
            $params['fields'] = is_array($fields) ? implode(',', $fields) : $fields;
            if (null !== $include_fields) {
                $params['include_fields'] = $include_fields;
            }
        }

        // Pagination.
        if (null !== $page) {
            $params['page'] = abs((int) $page);
            if (null !== $per_page) {
                $params['per_page'] = $per_page;
            }
        }

        return $this->apiClient->method('get')
            ->addPath('clients')
            ->withDictParams($params)
            ->call();
    }

    /**
     * Get a single Client by ID.
     * Required scopes:
     *      - "read:clients" - For any call to this endpoint.
     *      - "read:client_keys" - To retrieve "client_secret" and "encryption_key" attributes.
     *
     * @param string            $client_id      Client ID to get.
     * @param null|string|array $fields         Fields to include or exclude from the result:
     *      - Including only the fields required can speed up API calls significantly.
     *      - Arrays will be converted to comma-separated strings.
     * @param null|boolean      $include_fields True to include $fields, false to exclude $fields.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Clients/get_clients_by_id
     */
    public function get($client_id, $fields = null, $include_fields = null)
    {
        $params = [];

        // Results fields.
        if (! empty($fields)) {
            $params['fields'] = is_array($fields) ? implode(',', $fields) : $fields;
            if (null !== $include_fields) {
                $params['include_fields'] = $include_fields;
            }
        }

        return $this->apiClient->method('get')
            ->addPath('clients', $client_id)
            ->withDictParams($params)
            ->call();
    }

    /**
     * Delete a Client by ID.
     * Required scope: "delete:clients"
     *
     * @param string $client_id Client ID to delete.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Clients/delete_clients_by_id
     */
    public function delete($client_id)
    {
        return $this->apiClient->method('delete')
            ->addPath('clients', $client_id)
            ->call();
    }

    /**
     * Create a new Client.
     * Required scope: "create:clients"
     *
     * @param array $data Client create data; "name" field is required.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Clients/post_clients
     */
    public function create(array $data)
    {
        if (empty($data['name'])) {
            throw new \Exception('Missing required "name" field.');
        }

        return $this->apiClient->method('post')
            ->addPath('clients')
            ->withBody(json_encode($data))
            ->call();
    }

    /**
     * Update a Client.
     * Required scopes:
     *      - "update:clients" - For any call to this endpoint.
     *      - "update:client_keys" - To update "client_secret" and "encryption_key" attributes.
     *
     * @param string $client_id Client ID to update.
     * @param array  $data      Client data to update.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Clients/patch_clients_by_id
     */
    public function update($client_id, array $data)
    {
        return $this->apiClient->method('patch')
            ->addPath('clients', $client_id)
            ->withBody(json_encode($data))
            ->call();
    }
}
