<?php

namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\Tests\API\BasicCrudTest;

/**
 * Class ClientsTest
 *
 * @package Auth0\Tests\API\Management
 */
class ClientsTest extends BasicCrudTest
{

    /**
     * Unique identifier name for Clients.
     *
     * @var string
     */
    protected $id_name = 'client_id';

    /**
     * Return the Clients API to test.
     *
     * @return Management\Clients
     */
    protected function getApiClient()
    {
        $token = $this->getToken(self::$env, [ 'clients' => [ 'actions' => ['create', 'read', 'delete', 'update' ] ] ]);
        $api   = new Management($token, $this->domain);
        return $api->clients;
    }

    /**
     * Get the Client create data to send with the test create call.
     *
     * @return array
     */
    protected function getCreateBody()
    {
        return [
            'name' => 'TEST-CREATE-CLIENT-'.$this->rand,
            'app_type' => 'regular_web',
            'sso' => false,
            'description' => '__Auth0_PHP_initial_app_description__',
        ];
    }

    /**
     * Tests the \Auth0\SDK\API\Management\Clients::getAll() method.
     *
     * @return mixed
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    protected function getAllEntities()
    {
        $fields   = array_keys($this->getCreateBody());
        $fields[] = $this->id_name;
        $page_num = 1;

        // Get the second page of Clients with 1 per page (second result).
        $paged_results = $this->api->getAll($fields, true, $page_num, 1);

        // Make sure we only have one result, as requested.
        $this->assertEquals(1, count($paged_results));

        // Make sure we only have the 4 fields we requested.
        $this->assertEquals(count($fields), count($paged_results[0]));

        // Get many results (needs to include the created result if self::findCreatedItem === true).
        $many_results_per_page = 50;
        $many_results          = $this->api->getAll($fields, true, 0, $many_results_per_page);

        // Make sure we have at least as many results as we requested.
        $this->assertLessThanOrEqual($many_results_per_page, count($many_results));

        // Make sure our paged result above appears in the right place.
        // $page_num here represents the expected location for the single entity retrieved above.
        $this->assertEquals($this->getId($paged_results[0]), $this->getId($many_results[$page_num]));

        return $many_results;
    }

    /**
     * Check that the Client created matches the initial values sent.
     *
     * @param array $entity The created Client to check against initial values.
     *
     * @return void
     */
    protected function afterCreate(array $entity)
    {
        $expected = $this->getCreateBody();
        $this->assertNotEmpty($this->getId($entity));
        $this->assertEquals($expected['name'], $entity['name']);
        $this->assertEquals($expected['app_type'], $entity['app_type']);
        $this->assertEquals($expected['sso'], $entity['sso']);
        $this->assertEquals($expected['description'], $entity['description']);
    }

    /**
     * Get the Client values that should be updated.
     *
     * @return array
     */
    protected function getUpdateBody()
    {
        return [
            'name' => 'TEST-UPDATE-CLIENT-',
            'app_type' => 'native',
            'sso' => true,
            'description' => '__Auth0_PHP_updated_app_description__',
        ];
    }

    /**
     * Update entity returned values check.
     *
     * @param array $entity Client that was updated.
     *
     * @return void
     */
    protected function afterUpdate(array $entity)
    {
        $expected = $this->getUpdateBody();
        $this->assertEquals($expected['name'], $entity['name']);
        $this->assertEquals($expected['app_type'], $entity['app_type']);
        $this->assertEquals($expected['sso'], $entity['sso']);
        $this->assertEquals($expected['description'], $entity['description']);
    }
}
