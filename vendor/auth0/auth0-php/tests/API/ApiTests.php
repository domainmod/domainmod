<?php
namespace Auth0\Tests\API;

use Auth0\Tests\Traits\ErrorHelpers;
use Auth0\SDK\API\Helpers\TokenGenerator;
use Auth0\SDK\API\Management;
use josegonzalez\Dotenv\Loader;

class ApiTests extends \PHPUnit_Framework_TestCase
{
    use ErrorHelpers;

    /**
     *
     * @var array
     */
    protected static $env = [];

    protected function getEnv()
    {
        return self::getEnvStatic();
    }

    protected static function getEnvStatic()
    {
        $env_path = '.env';
        if (file_exists($env_path)) {
            $loader = new Loader($env_path);
            $loader->parse()->putenv(true);
        }

        return [
            'GLOBAL_CLIENT_ID' => getenv('GLOBAL_CLIENT_ID'),
            'GLOBAL_CLIENT_SECRET' => getenv('GLOBAL_CLIENT_SECRET'),
            'APP_CLIENT_ID' => getenv('APP_CLIENT_ID'),
            'APP_CLIENT_SECRET' => getenv('APP_CLIENT_SECRET'),
            'NIC_ID' => getenv('NIC_ID'),
            'NIC_SECRET' => getenv('NIC_SECRET'),
            'DOMAIN' => getenv('DOMAIN'),
        ];
    }

    protected function getToken($env, $scopes)
    {
        return self::getTokenStatic($env, $scopes);
    }

    protected static function getTokenStatic($env, $scopes)
    {
        $generator = new TokenGenerator( $env['GLOBAL_CLIENT_ID'], $env['GLOBAL_CLIENT_SECRET'] );
        return $generator->generate($scopes);
    }

    /**
     * Return an API client used during self::setUpBeforeClass().
     *
     * @param string $endpoint Endpoint name used for token generation.
     * @param array  $actions  Actions required for token generation.
     *
     * @return mixed
     */
    protected static function getApiStatic($endpoint, array $actions)
    {
        self::$env  = self::getEnvStatic();
        $token      = self::getTokenStatic(self::$env, [$endpoint => ['actions' => $actions]]);
        $api_client = new Management($token, self::$env['DOMAIN']);
        return $api_client->$endpoint;
    }
}
