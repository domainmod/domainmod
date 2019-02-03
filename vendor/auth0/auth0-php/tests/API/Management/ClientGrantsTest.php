<?php

namespace Auth0\Tests\API;

use Auth0\SDK\API\Management;
use Auth0\SDK\Exception\CoreException;

/**
 * Class ClientGrantsTest.
 * Tests the Auth0\SDK\API\Management\ClientGrants class.
 *
 * @package Auth0\Tests\API
 */
class ClientGrantsTest extends ApiTests
{

    /**
     * Existing test API audience identifier.
     */
    const TESTS_API_AUDIENCE = 'tests';

    /**
     * Client Grants API client.
     *
     * @var Management\ClientGrants
     */
    protected static $api;

    /**
     * Valid test scopes for the "tests" API.
     * Used for testing create and update.
     *
     * @var array
     */
    protected static $scopes = ['test:scope1', 'test:scope2'];

    /**
     * Sets up API client for the testing class.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$api = self::getApiStatic( 'client_grants', ['read', 'create', 'delete', 'update'] );
    }

    /**
     * Test that get methods work as expected.
     *
     * @return void
     *
     * @throws CoreException Thrown when there is a problem with parameters passed to the method.
     * @throws \Exception Thrown by the Guzzle HTTP client when there is a problem with the API call.
     */
    public function testGet()
    {
        $all_results = self::$api->getAll();
        $this->assertNotEmpty($all_results);

        $expected_client_id = $all_results[0]['client_id'] ?: null;
        $this->assertNotNull($expected_client_id);

        $expected_audience = $all_results[0]['audience'] ?: null;
        $this->assertNotNull($expected_audience);

        $audience_results = self::$api->getByAudience($expected_audience);
        $this->assertNotEmpty($audience_results);
        $this->assertEquals($expected_audience, $audience_results[0]['audience']);

        $client_id_results = self::$api->getByClientId($expected_client_id);
        $this->assertNotEmpty($client_id_results);
        $this->assertEquals($expected_client_id, $client_id_results[0]['client_id']);
    }

    /**
     * Test that pagination parameters are passed to the endpoint.
     *
     * @return void
     *
     * @throws \Exception Thrown by the Guzzle HTTP client when there is a problem with the API call.
     */
    public function testGetWithPagination()
    {
        $expected_count = 2;

        $results_1 = self::$api->getAll([], 0, $expected_count);
        $this->assertCount($expected_count, $results_1);

        $expected_page = 1;
        $results_2     = self::$api->getAll([], $expected_page, 1);
        $this->assertCount(1, $results_2);
        $this->assertEquals($results_1[$expected_page]['client_id'], $results_2[0]['client_id']);
        $this->assertEquals($results_1[$expected_page]['audience'], $results_2[0]['audience']);
    }

    /**
     * Test that the "include_totals" parameter works.
     *
     * @return void
     *
     * @throws \Exception Thrown by the Guzzle HTTP client when there is a problem with the API call.
     */
    public function testGetAllIncludeTotals()
    {
        $expected_page  = 0;
        $expected_count = 2;

        $results = self::$api->getAll(['include_totals' => true], $expected_page, $expected_count);
        $this->assertArrayHasKey('total', $results);
        $this->assertEquals($expected_page * $expected_count, $results['start']);
        $this->assertEquals($expected_count, $results['limit']);
        $this->assertNotEmpty($results['client_grants']);
    }

    /**
     * Test that we can create, update, and delete a Client Grant.
     *
     * @return void
     *
     * @throws CoreException Thrown when there is a problem with parameters passed to the method.
     * @throws \Exception Thrown by the Guzzle HTTP client when there is a problem with the API call.
     */
    public function testCreateUpdateDeleteGrant()
    {
        $client_id = self::$env['APP_CLIENT_ID'];
        $audience  = self::TESTS_API_AUDIENCE;

        // Create a Client Grant with just one of the testing scopes.
        $create_result = self::$api->create($client_id, $audience, [self::$scopes[0]]);
        $this->assertArrayHasKey('id', $create_result);
        $this->assertEquals($client_id, $create_result['client_id']);
        $this->assertEquals($audience, $create_result['audience']);
        $this->assertEquals([self::$scopes[0]], $create_result['scope']);

        $grant_id = $create_result['id'];

        // Test patching the created Client Grant.
        $update_result = self::$api->update($grant_id, self::$scopes);
        $this->assertEquals(self::$scopes, $update_result['scope']);

        // Test deleting the created Client Grant.
        $delete_result = self::$api->delete($grant_id);
        $this->assertNull($delete_result);
    }

    /**
     * Test that create method throws errors correctly.
     *
     * @return void
     *
     * @throws \Exception Thrown by the Guzzle HTTP client when there is a problem with the API call.
     */
    public function testCreateGrantExceptions()
    {
        $throws_missing_client_id_exception = false;
        try {
            self::$api->create('', self::TESTS_API_AUDIENCE, []);
        } catch (CoreException $e) {
            $throws_missing_client_id_exception = $this->errorHasString($e, 'Empty or invalid "client_id" parameter');
        }

        $this->assertTrue($throws_missing_client_id_exception);

        $throws_missing_audience_exception = false;
        try {
            self::$api->create(self::$env['APP_CLIENT_ID'], '', []);
        } catch (CoreException $e) {
            $throws_missing_audience_exception = $this->errorHasString($e, 'Empty or invalid "audience" parameter');
        }

        $this->assertTrue($throws_missing_audience_exception);
    }
}
