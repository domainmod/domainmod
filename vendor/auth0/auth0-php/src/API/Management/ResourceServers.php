<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\Exception\CoreException;

/**
 * Class ResourceServers.
 * Handles requests to the Resource Servers endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management
 */
class ResourceServers extends GenericResource
{
    /**
     * Get all Resource Servers, by page if desired.
     * Required scope: "read:resource_servers"
     *
     * @param null|integer $page     Page number to get, zero-based.
     * @param null|integer $per_page Number of results to get, null to return the default number.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Resource_Servers/get_resource_servers
     */
    public function getAll($page = null, $per_page = null)
    {
        $params = [];

        // Pagination parameters.
        if (null !== $page) {
            $params['page'] = abs((int) $page);
        }

        if (null !== $per_page) {
            $params['per_page'] = abs((int) $per_page);
        }

        return $this->apiClient->method('get')
            ->withDictParams($params)
            ->addPath('resource-servers')
            ->call();
    }

    /**
     * Get a single Resource Server by ID or API identifier.
     * Required scope: "read:resource_servers"
     *
     * @param string $id Resource Server ID or identifier to get.
     *
     * @return mixed
     *
     * @throws CoreException Thrown if the id parameter is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Resource_Servers/get_resource_servers_by_id
     */
    public function get($id)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        return $this->apiClient->method('get')
            ->addPath('resource-servers', $id)
            ->call();
    }

    /**
     * Create a new Resource Server.
     * Required scope: "create:resource_servers"
     *
     * @param string $identifier API identifier to use.
     * @param array  $data       Additional fields to add.
     *
     * @return mixed
     *
     * @throws CoreException Thrown if the identifier parameter or data field is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Resource_Servers/post_resource_servers
     */
    public function create($identifier, array $data)
    {
        // Backwards-compatibility with previously-unused $identifier parameter.
        if (empty($data['identifier'])) {
            $data['identifier'] = $identifier;
        }

        if (empty($data['identifier']) || ! is_string($data['identifier'])) {
            throw new CoreException('Invalid "identifier" field.');
        }

        return $this->apiClient->method('post')
            ->addPath('resource-servers')
            ->withBody(json_encode($data))
            ->call();
    }

    /**
     * Delete a Resource Server by ID.
     * Required scope: "delete:resource_servers"
     *
     * @param string $id Resource Server ID or identifier to delete.
     *
     * @return mixed
     *
     * @throws CoreException Thrown if the id parameter is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Resource_Servers/delete_resource_servers_by_id
     */
    public function delete($id)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        return $this->apiClient->method('delete')
            ->addPath('resource-servers', $id)
            ->call();
    }

    /**
     * Update a Resource Server by ID.
     * Required scope: "update:resource_servers"
     *
     * @param string $id   Resource Server ID or identifier to update.
     * @param array  $data Data to update.
     *
     * @return mixed
     *
     * @throws CoreException Thrown if the id parameter is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Resource_Servers/patch_resource_servers_by_id
     */
    public function update($id, array $data)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        return $this->apiClient->method('patch')
            ->addPath('resource-servers', $id)
            ->withBody(json_encode($data))
            ->call();
    }
}
