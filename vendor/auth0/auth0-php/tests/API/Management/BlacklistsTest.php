<?php
namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\Tests\API\ApiTests;

class BlacklistsTest extends ApiTests
{

    private $domain;

    public function testBlacklistAndGet()
    {
        $env   = $this->getEnv();
        $token = $this->getToken(
            $env, [
                'tokens' => [
                    'actions' => ['blacklist']
                ]
            ]
        );

        $this->domain = $env['DOMAIN'];

        $api = new Management($token, $env['DOMAIN']);

        $aud = $env['GLOBAL_CLIENT_ID'];
        $jti = 'somerandomJTI'.rand();

        $api->blacklists->blacklist($aud, $jti);

        $all = $api->blacklists->getAll($aud);

        $found = false;
        foreach ($all as $value) {
            if ($value['aud'] === $aud && $value['jti'] === $jti) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Blacklisted token not found');
    }
}
