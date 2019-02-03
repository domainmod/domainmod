<?php

namespace Auth0\Tests\API;

use Auth0\SDK\API\Management;

/**
 * Class LogsTest.
 * Tests the Auth0\SDK\API\Management\Logs class.
 *
 * @package Auth0\Tests\API
 */
class LogsTest extends ApiTests
{

    /**
     * Logs API client.
     *
     * @var mixed
     */
    protected static $api;

    /**
     * Sets up API client for entire testing class.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $env   = self::getEnvStatic();
        $token = self::getTokenStatic($env, [ 'logs' => [ 'actions' => ['read'] ] ]);
        $api   = new Management($token, $env['DOMAIN'], ['timeout' => 30]);

        self::$api = $api->logs;
    }

    /**
     * Test a general search.
     *
     * @return void
     */
    public function testLogSearchAndGetById()
    {
        $search_results = self::$api->search([
            'fields' => '_id,log_id,date',
            'include_fields' => true,
        ]);
        $this->assertNotEmpty($search_results);
        $this->assertNotEmpty($search_results[0]['_id']);
        $this->assertNotEmpty($search_results[0]['log_id']);
        $this->assertNotEmpty($search_results[0]['date']);
        $this->assertCount(3, $search_results[0]);

        // Test getting a single log result with a valid ID from above.
        $one_log = self::$api->get($search_results[0]['log_id']);
        $this->assertNotEmpty($one_log);
        $this->assertEquals($search_results[0]['log_id'], $one_log['log_id']);
    }

    /**
     * Test pagination parameters.
     *
     * @return void
     */
    public function testLogSearchPagination()
    {
        $expected_count = 5;
        $search_results = self::$api->search([
            // Fields here to speed up API call.
            'fields' => '_id,log_id',
            'include_fields' => true,

            // Second page of 5 results.
            'page' => 1,
            'per_page' => $expected_count,

            // Include totals to check pagination.
            'include_totals' => true,
        ]);

        $this->assertCount($expected_count, $search_results['logs']);
        $this->assertEquals($expected_count, $search_results['length']);

        // Starting on 2nd page so starting result should be equal to the number per page.
        $this->assertEquals($expected_count, $search_results['start']);
    }
}
