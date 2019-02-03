<?php

namespace Auth0\SDK\Helpers;

use Auth0\SDK\API\Helpers\RequestBuilder;
use Auth0\SDK\Helpers\Cache\CacheHandler;
use Auth0\SDK\Helpers\Cache\NoCacheHandler;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

/**
 * Class JWKFetcher.
 *
 * @package Auth0\SDK\Helpers
 */
class JWKFetcher
{

    /**
     * Cache handler or null for no caching.
     *
     * @var CacheHandler|null
     */
    private $cache;

    /**
     * Options for the Guzzle HTTP client.
     *
     * @var array
     */
    private $guzzleOptions;

    /**
     * JWKFetcher constructor.
     *
     * @param CacheHandler|null $cache         Cache handler or null for no caching.
     * @param array             $guzzleOptions Options for the Guzzle HTTP client.
     */
    public function __construct(CacheHandler $cache = null, array $guzzleOptions = [])
    {
        if ($cache === null) {
            $cache = new NoCacheHandler();
        }

        $this->cache         = $cache;
        $this->guzzleOptions = $guzzleOptions;
    }

    /**
     * Convert a certificate to PEM format.
     *
     * @param string $cert X509 certificate to convert to PEM format.
     *
     * @return string
     */
    protected function convertCertToPem($cert)
    {
        $output  = '-----BEGIN CERTIFICATE-----'.PHP_EOL;
        $output .= chunk_split($cert, 64, PHP_EOL);
        $output .= '-----END CERTIFICATE-----'.PHP_EOL;
        return $output;
    }

    // phpcs:disable
    /**
     * Appends the default JWKS path to a token issuer to return all keys from a JWKS.
     * TODO: Deprecate, use $this->getJwksX5c() instead and explain why/how.
     *
     * @param string $iss
     *
     * @return array|mixed|null
     *
     * @throws \Exception
     *
     * @codeCoverageIgnore
     */
    public function fetchKeys($iss)
    {
        $url = "{$iss}.well-known/jwks.json";

        if (($secret = $this->cache->get($url)) === null) {
            $secret = [];

            $request = new RequestBuilder([
                'domain' => $iss,
                'basePath' => '.well-known/jwks.json',
                'method' => 'GET',
                'guzzleOptions' => $this->guzzleOptions
            ]);
            $jwks    = $request->call();

            foreach ($jwks['keys'] as $key) {
                $secret[$key['kid']] = $this->convertCertToPem($key['x5c'][0]);
            }

            $this->cache->set($url, $secret);
        }

        return $secret;
    }
    // phpcs:enable

    /**
     * Fetch x509 cert for RS256 token decoding.
     *
     * @param string      $jwks_url URL to the JWKS.
     * @param string|null $kid      Key ID to use; returns first JWK if $kid is null or empty.
     *
     * @return string|null - Null if an x5c key could not be found for a key ID or if the JWKS is empty/invalid.
     */
    public function requestJwkX5c($jwks_url, $kid = null)
    {
        $cache_key = $jwks_url.'|'.$kid;

        $x5c = $this->cache->get($cache_key);
        if (! is_null($x5c)) {
            return $x5c;
        }

        $jwks = $this->requestJwks($jwks_url);
        $jwk  = $this->findJwk($jwks, $kid);

        if ($this->subArrayHasEmptyFirstItem($jwk, 'x5c')) {
            return null;
        }

        $x5c = $this->convertCertToPem($jwk['x5c'][0]);
        $this->cache->set($cache_key, $x5c);
        return $x5c;
    }

    /**
     * Get a JWKS from a specific URL.
     *
     * @param string $jwks_url URL to the JWKS.
     *
     * @return mixed|string
     *
     * @throws RequestException If $jwks_url is empty or malformed.
     * @throws ClientException  If the JWKS cannot be retrieved.
     *
     * @codeCoverageIgnore
     */
    protected function requestJwks($jwks_url)
    {
        $request = new RequestBuilder([
            'domain' => $jwks_url,
            'method' => 'GET',
            'guzzleOptions' => $this->guzzleOptions
        ]);
        return $request->call();
    }

    /**
     * Get a JWK from a JWKS using a key ID, if provided.
     *
     * @param array       $jwks JWKS to parse.
     * @param null|string $kid  Key ID to return; returns first JWK if $kid is null or empty.
     *
     * @return array|null Null if the keys array is empty or if the key ID is not found.
     *
     * @codeCoverageIgnore
     */
    private function findJwk(array $jwks, $kid = null)
    {
        if ($this->subArrayHasEmptyFirstItem($jwks, 'keys')) {
            return null;
        }

        if (! $kid) {
            return $jwks['keys'][0];
        }

        foreach ($jwks['keys'] as $key) {
            if (isset($key['kid']) && $key['kid'] === $kid) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Check if an array within an array has a non-empty first item.
     *
     * @param array|null $array Main array to check.
     * @param string     $key   Key pointing to a sub-array.
     *
     * @return boolean
     *
     * @codeCoverageIgnore
     */
    private function subArrayHasEmptyFirstItem($array, $key)
    {
        return empty($array) || ! is_array($array[$key]) || empty($array[$key][0]);
    }
}
