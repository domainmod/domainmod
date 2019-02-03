<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\Exception\CoreException;

/**
 * Class Rules.
 * Handles requests to the Rules endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management
 */
class Rules extends GenericResource
{
    /**
     * Get all Rules, by page if desired.
     * Required scope: "read:rules"
     *
     * @param null|boolean      $enabled        Retrieves rules that match the value, otherwise all rules are retrieved.
     * @param null|string|array $fields         Fields to include or exclude from the result.
     * @param null|boolean      $include_fields True to include $fields, false to exclude $fields.
     * @param null|integer      $page           Page number to get, zero-based.
     * @param null|integer      $per_page       Number of results to get, null to return the default number.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Rules/get_rules
     */
    public function getAll($enabled = null, $fields = null, $include_fields = null, $page = null, $per_page = null)
    {
        $params = [];

        // Only return enabled Rules.
        if ($enabled !== null) {
            $params['enabled'] = (bool) $enabled;
        }

        // Fields to include or exclude from results.
        if (! empty($fields)) {
            $params['fields'] = is_array($fields) ? implode(',', $fields) : $fields;
            if (null !== $include_fields) {
                $params['include_fields'] = $include_fields;
            }
        }

        // Pagination parameters.
        if (null !== $page) {
            $params['page'] = abs((int) $page);
        }

        if (null !== $per_page) {
            $params['per_page'] = abs((int) $per_page);
        }

        return $this->apiClient->method('get')
            ->addPath('rules')
            ->withDictParams($params)
            ->call();
    }

    /**
     * Get a single rule by ID.
     * Required scope: "read:rules"
     *
     * @param string            $id             Rule ID to get.
     * @param null|string|array $fields         Fields to include or exclude from the result.
     * @param null|boolean      $include_fields True to include $fields, false to exclude $fields.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $id is empty or not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Rules/get_rules_by_id
     */
    public function get($id, $fields = null, $include_fields = null)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        $params = [];

        // Fields to include or exclude from results.
        if (! empty($fields)) {
            $params['fields'] = is_array($fields) ? implode(',', $fields) : $fields;
            if (null !== $include_fields) {
                $params['include_fields'] = $include_fields;
            }
        }

        return $this->apiClient->method('get')
            ->addPath('rules', $id)
            ->withDictParams($params)
            ->call();
    }

    /**
     * Delete a rule by ID.
     * Required scope: "delete:rules"
     *
     * @param string $id Rule ID to delete.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $id is empty or not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Rules/delete_rules_by_id
     */
    public function delete($id)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        return $this->apiClient->method('delete')
            ->addPath('rules', $id)
            ->call();
    }

    /**
     * Create a new Rule.
     * Required scope: "create:rules"
     *
     * @param array $data Dictionary array of keys and values to create a Rule.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when required "script" or "name" fields are missing or empty.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Rules/post_rules
     * @link https://auth0.com/docs/rules/current#create-rules-with-the-management-api
     */
    public function create(array $data)
    {
        if (empty($data['name'])) {
            throw new CoreException('Missing required "name" field.');
        }

        if (empty($data['script'])) {
            throw new CoreException('Missing required "script" field.');
        }

        return $this->apiClient->method('post')
            ->addPath('rules')
            ->withBody(json_encode($data))
            ->call();
    }

    /**
     * Update a Rule by ID.
     * Required scope: "update:rules"
     *
     * @param string $id   Rule ID to delete.
     * @param array  $data Rule data to update.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $id is empty or not a string or if $data is empty.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function update($id, array $data)
    {
        if (empty($id) || ! is_string($id)) {
            throw new CoreException('Invalid "id" parameter.');
        }

        return $this->apiClient->method('patch')
            ->addPath('rules', $id)
            ->withBody(json_encode($data))
            ->call();
    }
}
