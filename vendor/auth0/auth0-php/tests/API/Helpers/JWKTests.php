<?php
namespace Auth0\Tests\Api\Helpers;

use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Cache\NoCacheHandler;
use Auth0\SDK\Helpers\Cache\FileSystemCacheHandler;
use Auth0\Tests\API\ApiTests;
use Auth0\Tests\CacheDecorator;

class JWKTest extends ApiTests
{
    public function testNoCache()
    {
        $env     = $this->getEnv();
        $cache   = new CacheDecorator(new NoCacheHandler());
        $fetcher = new JWKFetcher($cache);

        $keys = $fetcher->fetchKeys($env['DOMAIN']);
        $this->assertTrue(is_array($keys));

        $keys = $fetcher->fetchKeys($env['DOMAIN']);
        $this->assertTrue(is_array($keys));

        $this->assertEquals(2, $cache->count('get'));
        $this->assertEquals(2, $cache->count('set'));
        $this->assertEquals(0, $cache->count('delete'));
    }

    public function testFileSystemCache()
    {
        $env     = $this->getEnv();
        $cache   = new CacheDecorator(new FileSystemCacheHandler(md5(uniqid())));
        $fetcher = new JWKFetcher($cache);

        $keys = $fetcher->fetchKeys($env['DOMAIN']);
        $this->assertTrue(is_array($keys));

        $keys = $fetcher->fetchKeys($env['DOMAIN']);
        $this->assertTrue(is_array($keys));

        $this->assertEquals(2, $cache->count('get'));
        $this->assertEquals(1, $cache->count('set'));
        $this->assertEquals(0, $cache->count('delete'));

        $cache->delete('auth0-php.auth0.com.well-known/jwks.json');

        $keys = $fetcher->fetchKeys($env['DOMAIN']);
        $this->assertTrue(is_array($keys));

        $this->assertEquals(3, $cache->count('get'));
        $this->assertEquals(2, $cache->count('set'));
        $this->assertEquals(1, $cache->count('delete'));
    }
}
