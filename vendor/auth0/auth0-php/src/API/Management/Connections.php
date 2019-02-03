<?php

namespace Auth0\SDK\API\Management;

/**
 * Class Connections.
 * Handles requests to the Connections endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management
 */
class Connections extends GenericResource
{
    /**
     * Get all Connections by page.
     * Required scope: "read:connections"
     *
     * @param null|string       $strategy       Connection strategy to retrieve.
     * @param null|string|array $fields         Fields to include or exclude from the result.
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
     * @link https://auth0.com/docs/api/management/v2#!/Connections/get_connections
     */
    public function getAll(
        $strategy = null,
        $fields = null,
        $include_fields = null,
        $page = null,
        $per_page = null,
        array $add_params = []
    )
    {
        // Set additional parameters first so they are over-written by function parameters.
        $params = is_array($add_params) ? $add_params : [];

        // Connection strategy to filter results by.
        if (! empty($strategy)) {
            $params['strategy'] = $strategy;
        }

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
            ->addPath('connections')
            ->withDictParams($params)
            ->call();
    }

    /**
     * Get a single Connection by ID.
     * Required scope: "read:connections"
     *
     * @param string            $id             Connection ID to get.
     * @param null|string|array $fields         Fields to include or exclude from the result.
     *      - Including only the fields required can speed up API calls significantly.
     *      - Arrays will be converted to comma-separated strings.
     * @param null|boolean      $include_fields True to include $fields, false to exclude $fields.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Connections/get_connections_by_id
     */
    public function get($id, $fields = null, $include_fields = null)
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
            ->addPath('connections', $id)
            ->withDictParams($params)
            ->call();
    }

    /**
     * Delete a Connection by ID.
     * Required scope: "delete:connections"
     *
     * @param string $id Connection ID to delete.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Connections/delete_connections_by_id
     */
    public function delete($id)
    {
        return $this->apiClient->method('delete')
            ->addPath('connections', $id)
            ->call();
    }

    /**
     * Delete a specific User for a Connection.
     * Required scope: "delete:users"
     *
     * @param string $id    Auth0 database Connection ID (user_id with strategy of "auth0").
     * @param string $email Email of the user to delete.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Connections/delete_users_by_email
     */
    public function deleteUser($id, $email)
    {
        return $this->apiClient->method('delete')
            ->addPath('connections', $id)
            ->addPath('users')
            ->withParam('email', $email)
            ->call();
    }

    /**
     * Create a new Connection.
     * Required scope: "create:connections"
     *
     * @param array $data Connection create data; "name" and "strategy" fields are required.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Connections/post_connections
     */
    public function create(array $data)
    {
        if (empty($data['name'])) {
            throw new \Exception('Missing required "name" field.');
        }

        if (empty($data['strategy'])) {
            throw new \Exception('Missing required "strategy" field.');
        }

        return $this->apiClient->method('post')
            ->addPath('connections')
            ->withBody(json_encode($data))
            ->call();
    }

    /**
     * Update a Connection.
     * Required scope: "update:connections"
     *
     * @param string $id   Connection ID to update.
     * @param array  $data Connection data to update.
     *
     * @return mixed|string
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Connections/patch_connections_by_id
     */
    public function update($id, array $data)
    {
        return $this->apiClient->method('patch')
            ->addPath('connections', $id)
            ->withBody(json_encode($data))
            ->call();
    }
}
