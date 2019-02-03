<?php

namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\Tests\API\ApiTests;
use GuzzleHttp\Exception\ClientException;
use Auth0\SDK\Exception\CoreException;

/**
 * Class ResourceServersTest.
 *
 * @package Auth0\Tests\API\Management
 */
class ResourceServersTest extends ApiTests
{

    /**
     * Resource Server API client.
     *
     * @var Management\ResourceServers
     */
    protected static $api;

    /**
     * Resource Server identifier.
     *
     * @var string
     */
    protected static $serverIdentifier;

    /**
     * Test scopes to use.
     *
     * @var array
     */
    protected static $scopes = [
        [
            'value' => 'read:test1',
            'description' => 'Testing scope'
        ],
        [
            'value' => 'read:test2',
            'description' => 'Testing scope'
        ],
    ];

    /**
     * Sets up API client for the testing class.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$api              = self::getApiStatic( 'resource_servers', ['read', 'create', 'delete', 'update'] );
        self::$serverIdentifier = 'TEST_PHP_SDK_ID_'.uniqid();
    }

    /**
     * Test creating a Resource Server.
     *
     * @return void
     *
     * @throws CoreException Thrown if the identifier parameter or data field is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testCreate()
    {
        $create_data = [
            'name' => 'TEST_PHP_SDK_CREATE_'.uniqid(),
            'token_lifetime' => rand( 10000, 20000 ),
            'signing_alg' => 'HS256',
            // Only add a single scope so we can update it later.
            'scopes' => [self::$scopes[0]]
        ];

        $response = self::$api->create(self::$serverIdentifier, $create_data);

        $this->assertNotEmpty($response);
        $this->assertNotEmpty($response['id']);
        $this->assertEquals(self::$serverIdentifier, $response['identifier']);
        $this->assertEquals($create_data['name'], $response['name']);
        $this->assertEquals($create_data['token_lifetime'], $response['token_lifetime']);
        $this->assertEquals($create_data['signing_alg'], $response['signing_alg']);
        $this->assertEquals($create_data['scopes'], $response['scopes']);
    }

    /**
     * Test getting a Resource Server.
     *
     * @return void
     *
     * @throws CoreException Thrown if the identifier parameter or data field is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testGet()
    {
        $response = self::$api->get(self::$serverIdentifier);
        $this->assertNotEmpty($response);
        $this->assertEquals(self::$serverIdentifier, $response['identifier']);
    }

    /**
     * Test getting all Resource Servers and looking for the created one.
     *
     * @return void
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testGetAll()
    {
        $response = self::$api->getAll();

        // Should have at least the one we created and the management API.
        $this->assertGreaterThanOrEqual(2, count($response));

        // Make sure the one we created was found.
        $found_added = false;
        foreach ($response as $server) {
            if ($server['identifier'] === self::$serverIdentifier) {
                $found_added = true;
                break;
            }
        }

        $this->assertTrue($found_added);

        // Test pagination.
        $response_paged = self::$api->getAll(1, 1);
        $this->assertNotEmpty($response_paged);
        $this->assertEquals($response[1]['id'], $response_paged[0]['id']);
    }

    /**
     * Test updating the created Resource Server.
     *
     * @return void
     *
     * @throws CoreException Thrown if the identifier parameter or data field is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testUpdate()
    {
        $update_data = [
            'name' => 'TEST_PHP_SDK_UPDATE_'.uniqid(),
            'token_lifetime' => rand( 20001, 30000 ),
            'signing_alg' => 'RS256',
            'scopes' => self::$scopes
        ];

        $response = self::$api->update(self::$serverIdentifier, $update_data);

        $this->assertEquals($update_data['name'], $response['name']);
        $this->assertEquals($update_data['token_lifetime'], $response['token_lifetime']);
        $this->assertEquals($update_data['signing_alg'], $response['signing_alg']);
        $this->assertEquals($update_data['scopes'], $response['scopes']);
    }

    /**
     * Test deleting the Resource Server created above.
     *
     * @return void
     *
     * @throws CoreException Thrown if the identifier parameter or data field is empty or is not a string.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testDelete()
    {
        $response = self::$api->delete(self::$serverIdentifier);

        // Look for the resource server we just deleted.
        $get_server_throws_error = false;
        try {
            self::$api->get(self::$serverIdentifier);
        } catch (ClientException $e) {
            $get_server_throws_error = (404 === $e->getCode());
        }

        $this->assertNull($response);
        $this->assertTrue($get_server_throws_error);
    }

    /**
     * Test that exceptions are thrown for specific methods in specific cases.
     *
     * @return void
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testExceptions()
    {
        // Test that the get method throws an exception if the $id parameter is empty.
        $caught_get_no_id_exception = false;
        try {
            self::$api->get(null);
        } catch (CoreException $e) {
            $caught_get_no_id_exception = $this->errorHasString($e, 'Invalid "id" parameter');
        }

        $this->assertTrue($caught_get_no_id_exception);

        // Test that the delete method throws an exception if the $id parameter is empty.
        $caught_delete_no_id_exception = false;
        try {
            self::$api->delete(null);
        } catch (CoreException $e) {
            $caught_delete_no_id_exception = $this->errorHasString($e, 'Invalid "id" parameter');
        }

        $this->assertTrue($caught_delete_no_id_exception);

        // Test that the update method throws an exception if the $id parameter is empty.
        $caught_update_no_id_exception = false;
        try {
            self::$api->update(null, []);
        } catch (CoreException $e) {
            $caught_update_no_id_exception = $this->errorHasString($e, 'Invalid "id" parameter');
        }

        $this->assertTrue($caught_update_no_id_exception);

        // Test that the create method throws an exception if the $identifier parameter is empty.
        $caught_create_empty_identifier_param_exception = false;
        try {
            self::$api->create(null, []);
        } catch (CoreException $e) {
            $caught_create_empty_identifier_param_exception = $this->errorHasString($e, 'Invalid "identifier" field');
        }

        $this->assertTrue($caught_create_empty_identifier_param_exception);

        $caught_create_invalid_identifier_field_exception = false;
        try {
            self::$api->create('identifier', ['identifier' => 1234]);
        } catch (CoreException $e) {
            $caught_create_invalid_identifier_field_exception = $this->errorHasString($e, 'Invalid "identifier" field');
        }

        $this->assertTrue($caught_create_invalid_identifier_field_exception);
    }
}
