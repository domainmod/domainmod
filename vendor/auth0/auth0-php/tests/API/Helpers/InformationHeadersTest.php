<?php
namespace Auth0\Tests\Api\Helpers;

use Auth0\SDK\API\Helpers\InformationHeaders;
use Auth0\SDK\API\Helpers\ApiClient;

/**
 * Class InformationHeadersTest
 *
 * @package Auth0\Tests\Api\Helpers
 */
class InformationHeadersTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Set the package data and make sure it's returned correctly.
     *
     * @return void
     */
    public function testThatSetPackageSetsDataCorrectly()
    {
        $header = new InformationHeaders();
        $header->setPackage( 'test_name', '1.2.3' );
        $header_data = $header->get();

        $this->assertCount(2, $header_data);
        $this->assertArrayHasKey('name', $header_data);
        $this->assertEquals('test_name', $header_data['name']);
        $this->assertArrayHasKey('version', $header_data);
        $this->assertEquals('1.2.3', $header_data['version']);
    }

    /**
     * Set and override an env property and make sure it's returned correctly.
     *
     * @return void
     */
    public function testThatSetEnvPropertySetsDataCorrectly()
    {
        $header = new InformationHeaders();
        $header->setEnvProperty( 'test_env_name', '2.3.4' );
        $header_data = $header->get();

        $this->assertArrayHasKey('env', $header_data);
        $this->assertCount(1, $header_data['env']);
        $this->assertArrayHasKey('test_env_name', $header_data['env']);
        $this->assertEquals('2.3.4', $header_data['env']['test_env_name']);

        $header->setEnvProperty( 'test_env_name', '3.4.5' );
        $header_data = $header->get();
        $this->assertEquals('3.4.5', $header_data['env']['test_env_name']);

        $header->setEnvProperty( 'test_env_name_2', '4.5.6' );
        $header_data = $header->get();
        $this->assertEquals('4.5.6', $header_data['env']['test_env_name_2']);
    }

    /**
     * Set and override an env property with the deprecated method and make sure it's returned correctly.
     *
     * @return void
     */
    public function testThatSetEnvironmentSetsDataCorrectly()
    {
        $header = new InformationHeaders();
        $header->setEnvironment( 'test_env_name', '2.3.4' );
        $header_data = $header->get();

        $this->assertArrayHasKey('env', $header_data);
        $this->assertCount(1, $header_data['env']);
        $this->assertArrayHasKey('test_env_name', $header_data['env']);
        $this->assertEquals('2.3.4', $header_data['env']['test_env_name']);

        $header->setEnvironment( 'test_env_name', '3.4.5' );
        $header_data = $header->get();
        $this->assertEquals('3.4.5', $header_data['env']['test_env_name']);

        $header->setEnvironment( 'test_env_name_2', '4.5.6' );
        $header_data = $header->get();
        $this->assertEquals('4.5.6', $header_data['env']['test_env_name_2']);
    }

    /**
     * Set the package and env and make sure it's built correctly.
     *
     * @return void
     */
    public function testThatBuildReturnsCorrectData()
    {
        $header      = new InformationHeaders();
        $header_data = [
            'name' => 'test_name_2',
            'version' => '5.6.7',
            'env' => [
                'test_env_name_3' => '6.7.8',
            ],
        ];
        $header->setPackage( $header_data['name'], $header_data['version'] );
        $header->setEnvProperty( 'test_env_name_3', '6.7.8' );

        $header_built = base64_decode($header->build());
        $this->assertEquals( json_encode($header_data), $header_built );
    }

    /**
     * Extend existing headers and make sure existing data stays intact.
     *
     * @link https://github.com/auth0/jwt-auth-bundle/blob/master/src/JWTAuthBundle.php
     * @link https://github.com/auth0/laravel-auth0/blob/master/src/Auth0/Login/LoginServiceProvider.php
     *
     * @return void
     */
    public function testThatExtendedHeadersBuildCorrectly()
    {
        $headers     = ApiClient::getInfoHeadersData();
        $new_headers = InformationHeaders::Extend($headers);

        $new_headers->setEnvironment('test_env_name_5', '8.9.10');
        $new_headers->setPackage('test_name_4', '7.8.9');

        $new_header_data = $new_headers->get();

        $this->assertEquals( 'test_name_4', $new_header_data['name'] );
        $this->assertEquals( '7.8.9', $new_header_data['version'] );

        $this->assertArrayHasKey('env', $new_header_data);
        $this->assertArrayHasKey('php', $new_header_data['env']);
        $this->assertEquals( phpversion(), $new_header_data['env']['php'] );
        $this->assertArrayHasKey('auth0-php', $new_header_data['env']);
        $this->assertEquals(ApiClient::API_VERSION, $new_header_data['env']['auth0-php']);
    }
}
