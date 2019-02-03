<?php
/**
 * Created by PhpStorm.
 * User: germanlena
 * Date: 4/22/15
 * Time: 3:06 PM
 */

namespace Auth0\SDK\API\Helpers;

use Auth0\SDK\API\Header\Header;
use Auth0\SDK\API\Header\ContentType;

class ApiClient
{

    const API_VERSION = '5.3.2';

    protected static $infoHeadersDataEnabled = true;

    protected static $infoHeadersData;

    public static function setInfoHeadersData(InformationHeaders $infoHeadersData)
    {
        if (! self::$infoHeadersDataEnabled) {
            return null;
        }

        self::$infoHeadersData = $infoHeadersData;
    }

    public static function getInfoHeadersData()
    {
        if (! self::$infoHeadersDataEnabled) {
            return null;
        }

        if (self::$infoHeadersData === null) {
            self::$infoHeadersData = new InformationHeaders;

            self::$infoHeadersData->setPackage('auth0-php', self::API_VERSION);
            self::$infoHeadersData->setEnvProperty('php', phpversion());
        }

        return self::$infoHeadersData;
    }

    public static function disableInfoHeaders()
    {
        self::$infoHeadersDataEnabled = false;
    }

    protected $domain;

    protected $basePath;

    protected $headers;

    protected $guzzleOptions;

    protected $returnType;

    public function __construct($config)
    {
        $this->basePath      = $config['basePath'];
        $this->domain        = $config['domain'];
        $this->returnType    = isset( $config['returnType'] ) ? $config['returnType'] : null;
        $this->headers       = isset($config['headers']) ? $config['headers'] : [];
        $this->guzzleOptions = isset($config['guzzleOptions']) ? $config['guzzleOptions'] : [];

        if (self::$infoHeadersDataEnabled) {
            $this->headers[] = new Header('Auth0-Client', self::getInfoHeadersData()->build());
        }
    }

    public function __call($name, $arguments)
    {
        $builder = new RequestBuilder([
            'domain' => $this->domain,
            'basePath' => $this->basePath,
            'method' => $name,
            'guzzleOptions' => $this->guzzleOptions,
            'returnType' => $this->returnType,
        ]);

        return $builder->withHeaders($this->headers);
    }

    /**
     * Create a new RequestBuilder.
     * Similar to the above but does not use a magic method.
     *
     * @param string $method - HTTP method to use (GET, POST, PATCH, etc).
     *
     * @return RequestBuilder
     */
    public function method($method)
    {
        $method  = strtolower($method);
        $builder = new RequestBuilder([
            'domain' => $this->domain,
            'basePath' => $this->basePath,
            'method' => $method,
            'guzzleOptions' => $this->guzzleOptions,
            'returnType' => $this->returnType,
        ]);
        $builder->withHeaders($this->headers);

        if (in_array($method, [ 'patch', 'post', 'put' ])) {
            $builder->withHeader(new ContentType('application/json'));
        }

        return $builder;
    }
}
