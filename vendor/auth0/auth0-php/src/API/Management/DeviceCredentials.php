<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\API\Header\ContentType;

class DeviceCredentials extends GenericResource
{
    const TYPE_PUBLIC_KEY   = 'public_key';
    const TYPE_REFESH_TOKEN = 'refresh_token';

    /**
     *
     * @param  string            $user_id
     * @param  string            $client_id
     * @param  string            $type
     * @param  null|string|array $fields
     * @param  null|string|array $include_fields
     * @return mixed
     */
    public function getAll($user_id = null, $client_id = null, $type = null, $fields = null, $include_fields = null)
    {
        $request = $this->apiClient->get()
        ->addPath('device-credentials');

        if ($fields !== null) {
            if (is_array($fields)) {
                $fields = implode(',', $fields);
            }

            $request->withParam('fields', $fields);
        }

        if ($include_fields !== null) {
            $request->withParam('include_fields', $include_fields);
        }

        if ($user_id !== null) {
            $request->withParam('user_id', $user_id);
        }

        if ($client_id !== null) {
            $request->withParam('client_id', $client_id);
        }

        if ($type !== null) {
            $request->withParam('type', $type);
        }

        return $request->call();
    }

    /**
     *
     * @param  array $data
     * @return mixed
     */
    public function createPublicKey($data)
    {
        return $this->apiClient->post()
        ->addPath('device-credentials')
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     *
     * @param  string $id
     * @return mixed
     */
    public function deleteDeviceCredential($id)
    {
        return $this->apiClient->delete()
        ->addPath('device-credentials', $id)
        ->call();
    }
}
