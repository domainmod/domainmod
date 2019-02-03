<?php
namespace Auth0\Tests\API;

use Auth0\SDK\API\Helpers\RequestBuilder;
use Auth0\SDK\API\Management;
use Auth0\SDK\Exception\CoreException;

class RequestBuilderTest extends ApiTests
{

    public function testUrl()
    {
        $builder = new RequestBuilder(
            [
                'domain' => 'www.domain.com',
                'basePath' => '/api',
                'method' => 'get',
            ]
        );

        $this->assertEquals('', $builder->getUrl());

        $builder->path1();

        $this->assertEquals('path1', $builder->getUrl());

        $builder->path2(3);

        $this->assertEquals('path1/path2/3', $builder->getUrl());
    }

    public function testParams()
    {
        $builder = new RequestBuilder(
            [
                'domain' => 'www.domain.com',
                'method' => 'get',
            ]
        );

        // Adding a parameter should be reflected in the RequestBuilder object.
        $builder->withParam('param1', 'value1');
        $this->assertEquals('?param1=value1', $builder->getParams());

        // Adding a second parameter should be reflected in the RequestBuilder object.
        $builder->withParam('param2', 'value2');
        $this->assertEquals('?param1=value1&param2=value2', $builder->getParams());

        // Adding a parameter array should be reflected in the RequestBuilder object.
        $builder->withParams(
            [
                ['key' => 'param3','value' => 'value3'],
                ['key' => 'param1','value' => 'value4'],
            ]
        );
        $this->assertEquals('?param1=value4&param2=value2&param3=value3', $builder->getParams());

        // Adding a parameter dictionary should be reflected in the RequestBuilder object.
        $builder->withDictParams([ 'param4' => 'value4', 'param2' => 'value5']);
        $this->assertEquals('?param1=value4&param2=value5&param3=value3&param4=value4', $builder->getParams());
    }

    public function testFullUrl()
    {
        $builder = new RequestBuilder(
            [
                'domain' => 'www.domain.com',
                'method' => 'get',
            ]
        );

        $builder->path(2)
            ->subpath()
            ->withParams(
                [
                    ['key' => 'param1', 'value' => 'value1'],
                    ['key' => 'param2', 'value' => 'value2'],
                ]
            );

        $this->assertEquals('path/2/subpath?param1=value1&param2=value2', $builder->getUrl());
    }

    public function testGetGuzzleOptions()
    {
        $builder = new RequestBuilder(
            [
                'domain' => 'www.domain.com',
                'method' => 'get',
            ]
        );

        $options = $builder->getGuzzleOptions();

        $this->assertArrayHasKey('base_uri', $options);
        $this->assertEquals('www.domain.com', $options['base_uri']);
    }

    public function testgGetGuzzleOptionsWithBasePath()
    {
        $builder = new RequestBuilder(
            [
                'domain' => 'www.domain.com',
                'basePath' => '/api',
                'method' => 'get',
            ]
        );

        $options = $builder->getGuzzleOptions();

        $this->assertArrayHasKey('base_uri', $options);
        $this->assertEquals('www.domain.com/api', $options['base_uri']);
    }

    /**
     * Test that the return type is set properly and returns the correct result.
     */
    public function testReturnType()
    {
        $env   = self::getEnvStatic();
        $token = self::getTokenStatic($env, ['tenant_settings' => ['actions' => ['read']]]);

        // Test default return type matches "body".
        $api             = new Management($token, $env['DOMAIN'], []);
        $results_default = $api->tenants->get();
        $this->assertTrue( is_array( $results_default ) );

        $api          = new Management($token, $env['DOMAIN'], [], 'body');
        $results_body = $api->tenants->get();
        $this->assertEquals( $results_default, $results_body );

        // Test that "headers" return type contains expected keys.
        $api             = new Management($token, $env['DOMAIN'], [], 'headers');
        $results_headers = $api->tenants->get();
        $this->assertArrayHasKey( 'x-ratelimit-limit', $results_headers );
        $this->assertArrayHasKey( 'x-ratelimit-remaining', $results_headers );
        $this->assertArrayHasKey( 'x-ratelimit-reset', $results_headers );

        // Test that "object" return type returns the correct object type.
        $api            = new Management($token, $env['DOMAIN'], [], 'object');
        $results_object = $api->tenants->get();
        $this->assertInstanceOf( 'GuzzleHttp\Psr7\Response', $results_object );

        // Test that an invalid return type throws an error.
        $caught_return_type_error = false;
        try {
            $api = new Management($token, $env['DOMAIN'], [], '__invalid_return_type__');
            $api->tenants->get();
        } catch (CoreException $e) {
            $caught_return_type_error = $this->errorHasString( $e, 'Invalid returnType' );
        }

        $this->assertTrue( $caught_return_type_error );
    }
}
