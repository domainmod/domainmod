<?php
namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\Tests\API\BasicCrudTest;

/**
 * Class ConnectionsTest.
 *
 * @package Auth0\Tests\API\Management
 */
class ConnectionsTest extends BasicCrudTest
{

    /**
     * Unique identifier name for Connections.
     *
     * @var string
     */
    protected $id_name = 'id';

    /**
     * Return the Connections API to test.
     *
     * @return Management\Connections
     */
    protected function getApiClient()
    {
        $token = $this->getToken(
            self::$env, [
                'connections' => ['actions' => ['create', 'read', 'delete', 'update']],
                'users' => ['actions' => ['delete']],
            ]
        );
        $api   = new Management($token, $this->domain);
        return $api->connections;
    }

    /**
     * Get the Connection create data to send with the test create call.
     *
     * @return array
     */
    protected function getCreateBody()
    {
        return [
            'name' => 'TEST-CREATE-CONNECTION-'.$this->rand,
            'strategy' => 'auth0',
            'options' => [
                'requires_username' => true,
                'passwordPolicy' => 'fair',
            ],
        ];
    }

    /**
     * Tests the \Auth0\SDK\API\Management\Connections::getAll() method.
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

        // Get the second page of Connections with 1 per page (second result).
        $paged_results = $this->api->getAll(null, $fields, true, $page_num, 1);

        // Make sure we only have one result, as requested.
        $this->assertEquals(1, count($paged_results));

        // Get many results (needs to include the created result if self::findCreatedItem === true).
        $many_results_per_page = 50;
        $many_results          = $this->api->getAll(null, $fields, true, 0, $many_results_per_page);

        // Make sure we have at least as many results as we requested.
        $this->assertLessThanOrEqual($many_results_per_page, count($many_results));

        // Make sure our paged result above appears in the right place.
        // $page_num here represents the expected location for the single entity retrieved above.
        $this->assertEquals($this->getId($paged_results[0]), $this->getId($many_results[$page_num]));

        return $many_results;
    }

    /**
     * Check that the Connection created matches the initial values sent.
     *
     * @param array $entity The created Connection to check against initial values.
     *
     * @return void
     */
    protected function afterCreate(array $entity)
    {
        $expected = $this->getCreateBody();
        $this->assertNotEmpty($entity[$this->id_name]);
        $this->assertEquals($expected['strategy'], $entity['strategy']);
        $this->assertEquals($expected['name'], $entity['name']);
        $this->assertEquals($expected['options']['requires_username'], $entity['options']['requires_username']);
        $this->assertEquals($expected['options']['passwordPolicy'], $entity['options']['passwordPolicy']);
    }

    /**
     * Get the Connection update data to send with the test update call.
     *
     * @return array
     */
    protected function getUpdateBody()
    {
        return [
            'options' => [
                'requires_username' => false,
                'passwordPolicy' => 'good',
            ],
        ];
    }

    /**
     * Update entity returned values check.
     *
     * @param array $entity Connection that was updated.
     *
     * @return void
     */
    protected function afterUpdate(array $entity)
    {
        $expected = $this->getUpdateBody();
        $this->assertEquals($expected['options']['requires_username'], $entity['options']['requires_username']);
        $this->assertEquals($expected['options']['passwordPolicy'], $entity['options']['passwordPolicy']);
    }
}
