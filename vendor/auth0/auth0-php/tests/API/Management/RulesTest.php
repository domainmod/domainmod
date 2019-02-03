<?php
namespace Auth0\Tests\API;

use Auth0\SDK\API\Management;
use Auth0\SDK\Exception\CoreException;

/**
 * Class RulesTest.
 *
 * @package Auth0\Tests\API\Management
 */
class RulesTest extends ApiTests
{

    /**
     * Rules API client.
     *
     * @var Management\Rules
     */
    protected static $api;

    /**
     * Sets up API client for the testing class.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$api = self::getApiStatic( 'rules', ['read', 'create', 'delete', 'update'] );
    }

    /**
     * Test that get methods work as expected.
     *
     * @return void
     *
     * @throws CoreException Thrown when there is a problem with parameters passed to the method.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testGet()
    {
        $results = self::$api->getAll();
        $this->assertNotEmpty($results);

        // Check getting a single rule by a known ID.
        $get_rule_id = $results[0]['id'];
        $result      = self::$api->get($get_rule_id);
        $this->assertNotEmpty($result);
        $this->assertEquals($results[0]['id'], $get_rule_id);

        // Iterate through the results to see if we have enabled and disabled Rules.
        $has_enabled  = false;
        $has_disabled = false;
        foreach ($results as $result) {
            if ($result['enabled']) {
                $has_enabled = true;
            } else {
                $has_disabled = true;
            }
        }

        // Check enabled rules.
        $enabled_results = self::$api->getAll(true);
        if ($has_enabled) {
            $this->assertNotEmpty($enabled_results);
        } else {
            $this->assertEmpty($enabled_results);
        }

        // Check disabled rules.
        $disabled_results = self::$api->getAll(false);
        if ($has_disabled) {
            $this->assertNotEmpty($disabled_results);
        } else {
            $this->assertEmpty($disabled_results);
        }
    }

    /**
     * Test that get methods respect fields.
     *
     * @return void
     *
     * @throws CoreException Thrown when there is a problem with parameters passed to the method.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testGetWithFields()
    {
        $fields = ['id', 'name'];

        $fields_results = self::$api->getAll(null, $fields, true);
        $this->assertNotEmpty($fields_results);
        $this->assertCount(count($fields), $fields_results[0]);

        $get_rule_id   = $fields_results[0]['id'];
        $fields_result = self::$api->get($get_rule_id, $fields, true);
        $this->assertNotEmpty($fields_result);
        $this->assertCount(count($fields), $fields_result);
    }

    /**
     * Test that getAll method respects pagination.
     *
     * @return void
     *
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testGetAllPagination()
    {
        $paged_results = self::$api->getAll(null, null, null, 0, 2);
        $this->assertCount(2, $paged_results);

        // Second page of 1 result.
        $paged_results_2 = self::$api->getAll(null, null, null, 1, 1);
        $this->assertCount(1, $paged_results_2);
        $this->assertEquals($paged_results[1]['id'], $paged_results_2[0]['id']);
    }

    /**
     * Test that create, update, and delete methods work as expected.
     *
     * @return void
     *
     * @throws CoreException Thrown when there is a problem with parameters passed to the method.
     * @throws \Exception Thrown by the HTTP client when there is a problem with the API call.
     */
    public function testCreateUpdateDelete()
    {
        $create_data = [
            'name' => 'test-create-rule-'.rand(),
            'script' => 'function (user, context, callback) { callback(null, user, context); }',
            'enabled' => true,
        ];

        $create_result = self::$api->create($create_data);
        $this->assertNotEmpty($create_result['id']);
        $this->assertEquals($create_data['enabled'], $create_result['enabled']);
        $this->assertEquals($create_data['name'], $create_result['name']);
        $this->assertEquals($create_data['script'], $create_result['script']);

        $test_rule_id = $create_result['id'];
        $update_data  = [
            'name' => 'test-create-rule-'.rand(),
            'script' => 'function (user, context, cb) { cb(null, user, context); }',
            'enabled' => false,
        ];

        $update_result = self::$api->update($test_rule_id, $update_data);
        $this->assertEquals($update_data['enabled'], $update_result['enabled']);
        $this->assertEquals($update_data['name'], $update_result['name']);
        $this->assertEquals($update_data['script'], $update_result['script']);

        $delete_result = self::$api->delete($test_rule_id);
        $this->assertNull($delete_result);
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

        // Test that the create method throws an exception if no "name" field is passed.
        $caught_create_no_name_exception = false;
        try {
            self::$api->create(['script' => 'function(){}']);
        } catch (CoreException $e) {
            $caught_create_no_name_exception = $this->errorHasString($e, 'Missing required "name" field');
        }

        $this->assertTrue($caught_create_no_name_exception);

        // Test that the create method throws an exception if no "script" field is passed.
        $caught_create_no_script_exception = false;
        try {
            self::$api->create(['name' => 'test-create-rule-'.rand()]);
        } catch (CoreException $e) {
            $caught_create_no_script_exception = $this->errorHasString($e, 'Missing required "script" field');
        }

        $this->assertTrue($caught_create_no_script_exception);

        // Test that the update method throws an exception if the $id parameter is empty.
        $caught_update_no_id_exception = false;
        try {
            self::$api->update(null, []);
        } catch (CoreException $e) {
            $caught_update_no_id_exception = $this->errorHasString($e, 'Invalid "id" parameter');
        }

        $this->assertTrue($caught_update_no_id_exception);
    }
}
