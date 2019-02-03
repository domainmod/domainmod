<?php


namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Authentication;
use Auth0\Tests\API\ApiTests;

class AuthApiDBConnectionsTest extends ApiTests
{

    protected $email;

    protected $connection = 'Username-Password-Authentication';

    protected function setUp()
    {
        $this->email = 'test-dbconnections-user'.rand().'@test.com';
    }

    public function testSignup()
    {
        $env = $this->getEnv();

        $api = new Authentication($env['DOMAIN'], $env['APP_CLIENT_ID']);

        $email      = $this->email;
        $password   = 'Bqn8LEsu68p38TmFvsWW';
        $connection = $this->connection;

        $response = $api->dbconnections_signup($email, $password, $connection);

        $this->assertArrayHasKey('_id', $response);
        $this->assertArrayHasKey('email_verified', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertEquals($email, $response['email']);
    }

    public function testChangePassword()
    {
        $env = $this->getEnv();

        $api = new Authentication($env['DOMAIN'], $env['APP_CLIENT_ID']);

        $email      = $this->email;
        $connection = $this->connection;

        $response = $api->dbconnections_change_password($email, $connection);

        $this->assertNotEmpty($response);
        $this->assertContains('email', $response);
    }
}
