<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\Exception\CoreException;

/**
 * Class ClientGrants.
 * Handles requests to the Client Grants endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management
 */
class ClientGrants extends GenericResource
{

    /**
     * Get all Client Grants, by page if desired.
     * Required scope: "read:client_grants"
     *
     * @param array        $params   Additional URL parameters to send:
     *      - "audience" to filter be a specific API audience identifier.
     *      - "client_id" to return an object.
     *      - "include_totals" to return an object.
     * @param null|integer $page     The page number, zero based.
     * @param null|integer $per_page The amount of entries per page.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/get_client_grants
     */
    public function getAll(array $params = [], $page = null, $per_page = null)
    {
        if (null !== $page) {
            $params['page'] = abs((int) $page);
        }

        if (null !== $per_page) {
            $params['per_page'] = abs((int) $per_page);
        }

        return $this->apiClient->method('get')
            ->addPath('client-grants')
            ->withDictParams($params)
            ->call();
    }

    /**
     * Get Client Grants by audience.
     * Required scope: "read:client_grants"
     *
     * @param string       $audience API Audience to filter by.
     * @param null|integer $page     The page number, zero based.
     * @param null|integer $per_page The amount of entries per page.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $audience is empty or not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/get_client_grants
     */
    public function getByAudience($audience, $page = null, $per_page = null)
    {
        if (empty($audience) || ! is_string($audience)) {
            throw new CoreException('Empty or invalid "audience" parameter.');
        }

        return $this->getAll(['audience' => $audience], $page, $per_page);
    }

    /**
     * Get Client Grants by Client ID.
     * Required scope: "read:client_grants"
     *
     * @param string       $client_id Client ID to filter by.
     * @param null|integer $page      The page number, zero based.
     * @param null|integer $per_page  The amount of entries per page.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $client_id is empty or not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/get_client_grants
     */
    public function getByClientId($client_id, $page = null, $per_page = null)
    {
        if (empty($client_id) || ! is_string($client_id)) {
            throw new CoreException('Empty or invalid "client_id" parameter.');
        }

        return $this->getAll(['client_id' => $client_id], $page, $per_page);
    }

    /**
     * Create a new Client Grant.
     * Required scope: "create:client_grants"
     *
     * @param string $client_id Client ID to receive the grant.
     * @param string $audience  Audience identifier for the API being granted.
     * @param array  $scope     Array of scopes for the grant.
     *
     * @return mixed
     *
     * @throws CoreException Thrown when $client_id or $audience are empty or not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/post_client_grants
     */
    public function create($client_id, $audience, array $scope = [])
    {
        if (empty($client_id) || ! is_string($client_id)) {
            throw new CoreException('Empty or invalid "client_id" parameter.');
        }

        if (empty($audience) || ! is_string($audience)) {
            throw new CoreException('Empty or invalid "audience" parameter.');
        }

        return $this->apiClient->method('post')
            ->addPath('client-grants')
            ->withBody(json_encode([
                'client_id' => $client_id,
                'audience' => $audience,
                'scope' => $scope,
            ]))
            ->call();
    }

    /**
     * Delete a Client Grant by ID.
     * Required scope: "delete:client_grants"
     *
     * @param string $id Client Grant ID to delete.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/delete_client_grants_by_id
     */
    public function delete($id)
    {
        return $this->apiClient->method('delete')
            ->addPath('client-grants', $id)
            ->call();
    }

    /**
     * Update an existing Client Grant.
     * Required scope: "update:client_grants"
     *
     * @param string $id    Client Grant ID to update.
     * @param array  $scope Array of scopes to update; will replace existing scopes, not merge.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Client_Grants/patch_client_grants_by_id
     */
    public function update($id, array $scope)
    {
        return $this->apiClient->method('patch')
            ->addPath('client-grants', $id)
            ->withBody(json_encode(['scope' => $scope,]))
            ->call();
    }

    /**
     * Get a Client Grant.
     * TODO: Deprecate, cannot get a Client Grant by ID.
     *
     * @param string      $id       Client Grant ID.
     * @param null|string $audience Client Grant audience to filter by.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function get($id, $audience = null)
    {
        $request = $this->apiClient->get()
            ->addPath('client-grants');

        if ($audience !== null) {
            $request = $request->withParam('audience', $audience);
        }

        return $request->call();
    }
}
