<?php

namespace Auth0\SDK\API\Management;

use Auth0\SDK\API\Header\ContentType;

class Emails extends GenericResource
{
    /**
     *
     * @param  null|string|array $fields
     * @param  null|string|array $include_fields
     * @return mixed
     */
    public function getEmailProvider($fields = null, $include_fields = null)
    {
        $request = $this->apiClient->get()
        ->emails()
        ->provider();

        if ($fields !== null) {
            if (is_array($fields)) {
                $fields = implode(',', $fields);
            }

            $request->withParam('fields', $fields);
        }

        if ($include_fields !== null) {
            $request->withParam('include_fields', $include_fields);
        }

        return $request->call();
    }

    /**
     *
     * @param  array $data
     * @return mixed
     */
    public function configureEmailProvider($data)
    {
        return $this->apiClient->post()
        ->emails()
        ->provider()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     *
     * @param  array $data
     * @return mixed
     */
    public function updateEmailProvider($data)
    {
        return $this->apiClient->patch()
        ->emails()
        ->provider()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     *
     * @return mixed
     */
    public function deleteEmailProvider()
    {
        return $this->apiClient->delete()
        ->emails()
        ->provider()
        ->call();
    }
}
