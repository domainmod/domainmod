<?php
namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\Tests\API\BasicCrudTest;

/**
 * Class UsersTest.
 *
 * @package Auth0\Tests\API\Management
 */
class UsersTest extends BasicCrudTest
{

    /**
     * Unique identifier name for Users.
     *
     * @var string
     */
    protected $id_name = 'user_id';

    /**
     * Should the results returned by getAll be searched for the created user?
     *
     * @var boolean
     */
    protected $findCreatedItem = false;

    /**
     * Connection to create the user in.
     *
     * @var string
     */
    protected $connection = 'Username-Password-Authentication';

    /**
     * Return the Users API to test.
     *
     * @return Management\Users
     */
    protected function getApiClient()
    {
        $token = $this->getToken(self::$env, ['users' => ['actions' => ['create', 'read', 'delete', 'update']]]);
        $api   = new Management($token, $this->domain);
        return $api->users;
    }

    /**
     * Get the User create data to send with the test create call.
     *
     * @return array
     */
    protected function getCreateBody()
    {
        return [
            'connection' => $this->connection,
            'email' => 'test-create-user-'.$this->rand.'@auth0.com',
            'password' => 'Y6t82hQjpXCMd3oD7Zsc',
            'picture' => 'https://cdn.auth0.com/styleguide/components/1.0.8/media/logos/img/badge.png',
            'email_verified' => true,
            'user_metadata' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];
    }

    /**
     * Tests the \Auth0\SDK\API\Management\Users::getAll() method.
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

        // Get the second page of Users with 1 per page (second result).
        $paged_results = $this->api->getAll(
            [
                'sort' => 'created_at:1'
            ], $fields, true, $page_num, 1
        );

        // Make sure we only have one result, as requested.
        $this->assertEquals(1, count($paged_results));

        // Get many results (needs to include the created result if self::findCreatedItem === true).
        $many_results_per_page = 50;
        $many_results          = $this->api->getAll(
            [
                'sort' => 'created_at:1'
            ], $fields, true, 0, $many_results_per_page
        );

        // Make sure we have at least as many results as we requested.
        $this->assertLessThanOrEqual($many_results_per_page, count($many_results));

        // Make sure our paged result above appears in the right place.
        // $page_num here represents the expected location for the single entity retrieved above.
        $this->assertEquals($this->getId($paged_results[0]), $this->getId($many_results[$page_num]));

        return $many_results;
    }

    /**
     * Check that the User created matches the initial values sent.
     *
     * @param array $entity The created User to check against initial values.
     *
     * @return void
     */
    protected function afterCreate(array $entity)
    {
        $expected = $this->getCreateBody();
        $this->assertNotEmpty($this->getId($entity));
        $this->assertEquals($expected['email'], $entity['email']);
        $this->assertTrue($entity['email_verified']);
        $this->assertEquals($expected['user_metadata']['key1'], $entity['user_metadata']['key1']);
        $this->assertEquals($expected['user_metadata']['key2'], $entity['user_metadata']['key2']);
    }

    /**
     * Get the User update data to send with the test update call.
     *
     * @return array
     */
    protected function getUpdateBody()
    {
        return [
            'email' => 'test-update-user-'.$this->rand.'@auth0.com',
            'email_verified' => false,
            'user_metadata' => [
                'key1' => 'value4',
                'key3' => 'value3',
            ],
        ];
    }

    /**
     * Update entity returned values check.
     *
     * @param array $entity User that was updated.
     *
     * @return void
     */
    protected function afterUpdate(array $entity)
    {
        $expected = $this->getUpdateBody();
        $this->assertEquals($expected['email'], $entity['email']);
        $this->assertFalse($entity['email_verified']);
        $this->assertEquals($expected['user_metadata']['key1'], $entity['user_metadata']['key1']);
        $this->assertEquals($expected['user_metadata']['key3'], $entity['user_metadata']['key3']);
    }

    /**
     * Test whether the User create function throws errors correctly.
     *
     * @return void
     */
    public function testRequiredUserCreateFields()
    {
        // Try to create a user without a Connection.
        $caught_connection_error = false;
        try {
            $this->api->create([]);
        } catch (\Exception $e) {
            $caught_connection_error = $this->errorHasString($e, 'Missing required "connection" field');
        }

        $this->assertTrue($caught_connection_error);

        // Try to create an "sms" connection user missing a phone number.
        $caught_phone_error = false;
        try {
            $this->api->create([ 'connection' => 'sms' ]);
        } catch (\Exception $e) {
            $caught_phone_error = $this->errorHasString($e, 'Missing required "phone_number" field');
        }

        $this->assertTrue($caught_phone_error);

        // Try to create an "email" connection user missing an email.
        $caught_email_error = false;
        try {
            $this->api->create([ 'connection' => 'email' ]);
        } catch (\Exception $e) {
            $caught_email_error = $this->errorHasString($e, 'Missing required "email" field');
        }

        $this->assertTrue($caught_email_error);

        // Try to create a DB connection user missing an email.
        $caught_db_email_error = false;
        try {
            $this->api->create([ 'connection' => $this->connection ]);
        } catch (\Exception $e) {
            $caught_db_email_error = $this->errorHasString($e, 'Missing required "email" field');
        }

        $this->assertTrue($caught_db_email_error);
    }
}
