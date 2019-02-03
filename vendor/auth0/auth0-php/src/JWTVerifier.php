<?php

namespace Auth0\SDK;

use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\InvalidTokenException;

use Auth0\SDK\Helpers\Cache\CacheHandler;
use Auth0\SDK\Helpers\JWKFetcher;
use Firebase\JWT\JWT;

/**
 * Class JWTVerifier.
 * Used to validate JWTs issued by Auth0.
 *
 * @package Auth0\SDK
 */
class JWTVerifier
{

    /**
     * Instance of JWKFetcher, injected or instantiated with this class's config options.
     *
     * @var JWKFetcher|null
     */
    protected $JWKFetcher;

    /**
     * Algorithms supported.
     * Only pass in the expected algorithm (HS256 or RS256).
     *
     * @var array
     */
    protected $supported_algs = ['HS256'];

    /**
     * Audiences expected in the token.
     *
     * @var array
     */
    protected $valid_audiences;

    /**
     * Authorized issuing domain.
     * Required for RS256 tokens.
     *
     * @var array|null
     */
    protected $authorized_iss;

    /**
     * Application Client Secret.
     * Required for HS256 tokens.
     *
     * @var string|null
     */
    protected $client_secret;

    /**
     * Path to the JWKS for RS256 tokens.
     *
     * @var string
     */
    protected $jwks_path = '.well-known/jwks.json';

    /**
     * JWTVerifier Constructor.
     *
     * @param array           $config     Uses the following keys:
     *                - valid_audiences (Array) - Required; list of audiences accepted by the service.
     *                - client_secret (String) - Required for HS256; Auth0 Application Client Secret.
     *                - authorized_iss (Array) - Required for RS256; list of issuers trusted by the service.
     *                - supported_algs (Array) - List of supported algorithms; defaults to HS256.
     *                - cache (CacheHandler) - Optional. Instance of CacheHandler to cache the JWKs.
     *                - guzzle_options (Array) - Extra Guzzle HTTP client options used when getting a JWKS.
     *                - jwks_path (string) - Path from the issuer domain to the JWKS; used for RS256.
     * @param JWKFetcher|null $jwkFetcher Instance of the JWKFetcher class to inject or null to instantiate.
     *
     * @throws CoreException If the suported_algs config key is set.
     * @throws CoreException If the valid_audiences config key is empty.
     * @throws CoreException If the token supports RS256 and the authorized_iss config key is empty.
     * @throws CoreException If the the token supports HS256 and the client_secret config key is empty.
     */
    public function __construct(array $config, JWKFetcher $jwkFetcher = null)
    {
        $cache         = null;
        $guzzleOptions = [];

        // Allow for dependency injection of a JWKFetcher object.
        $this->JWKFetcher = $jwkFetcher;
        if (! $this->JWKFetcher instanceof JWKFetcher) {
            // CacheHandler implementation to be used in JWKFetcher.
            if (isset($config['cache']) && $config['cache'] instanceof CacheHandler) {
                $cache = $config['cache'];
            }

            // Pass in Guzzle client options, if present.
            if (isset($config['guzzle_options']) && is_array($config['guzzle_options'])) {
                $guzzleOptions = $config['guzzle_options'];
            }

            $this->JWKFetcher = new JWKFetcher($cache, $guzzleOptions);
        }

        // JWKS path to use; see variable declaration above for default.
        if (isset($config['jwks_path'])) {
            $this->jwks_path = (string) $config['jwks_path'];
        }

        // Legacy misspelling in JWT library.
        if (isset($config['suported_algs'])) {
            throw new CoreException('`suported_algs` was properly renamed to `supported_algs`');
        }

        // Make sure we have audiences to check.
        if (empty($config['valid_audiences'])) {
            throw new CoreException('The audience is mandatory');
        }

        $this->valid_audiences = $config['valid_audiences'];

        // Set the supported algorithms if passed; see variable declaration above for default.
        if (isset($config['supported_algs'])) {
            $this->supported_algs = $config['supported_algs'];
        }

        // Check for algorithms that are not HS256 or RS256.
        $unsupported_algs = array_diff( $this->supported_algs, [ 'HS256', 'RS256' ] );
        if (! empty( $unsupported_algs )) {
            throw new CoreException('Cannot support the following algorithm(s): '.implode( ', ', $unsupported_algs ));
        }

        // Set if the authorized issuer is passed; enforce if RS256.
        if (! empty( $config['authorized_iss'] )) {
            $this->authorized_iss = $config['authorized_iss'];
        } else if ($this->supportsAlg( 'RS256' )) {
            throw new CoreException('The token iss property is required when accepting RS256 signed tokens');
        }

        // Only store the client_secret if this is an HS256 token.
        if ($this->supportsAlg( 'HS256' )) {
            // HS256 tokens require a client_secret.
            if (empty($config['client_secret'])) {
                throw new CoreException('The client_secret is required when accepting HS256 signed tokens');
            }

            if (! isset($config['secret_base64_encoded']) || $config['secret_base64_encoded']) {
                // If secret_base64_encoded is not passed or it is passed as truth-y, decode the client secret.
                $this->client_secret = $this->decodeB64($config['client_secret']);
            } else {
                // Otherwise, leave as-is.
                $this->client_secret = $config['client_secret'];
            }
        }
    }

    /**
     * Verify and decode a JWT.
     *
     * @param string $jwt JWT to verify and decode.
     *
     * @return mixed
     *
     * @throws InvalidTokenException If the token does not have 3 sections.
     * @throws InvalidTokenException If the algorithm used to sign the token is not supported.
     * @throws InvalidTokenException If the token does not have a valid audience.
     * @throws CoreException If an RS256 token is missing a key ID.
     * @throws CoreException If an RS256 token does not have a valid issuer.
     * @throws CoreException If the token cannot be decoded.
     */
    public function verifyAndDecode($jwt)
    {
        $tks = explode('.', $jwt);

        if (count($tks) !== 3) {
            throw new InvalidTokenException('Wrong number of segments');
        }

        try {
            $head_decoded = $this->decodeTokenSegment($tks[0]);
            $body_decoded = $this->decodeTokenSegment($tks[1]);
        } catch (\DomainException $e) {
            throw new InvalidTokenException('Malformed token.');
        }

        if (! is_object($head_decoded) || ! is_object($body_decoded)) {
            throw new InvalidTokenException('Malformed token.');
        }

        if (empty($head_decoded->alg)) {
            throw new InvalidTokenException('Token algorithm not found');
        }

        if (! $this->supportsAlg($head_decoded->alg)) {
            throw new InvalidTokenException('Token algorithm not supported');
        }

        // Validate the token audience, if present.
        if (! empty($body_decoded->aud)) {
            $audience = is_array($body_decoded->aud) ? $body_decoded->aud : [$body_decoded->aud];
            if (! count(array_intersect($audience, $this->valid_audiences))) {
                $message  = 'Invalid token audience '.implode( ', ', $audience );
                $message .= '; expected '.implode( ', ', $this->valid_audiences );
                throw new InvalidTokenException($message);
            }
        }

        if ('HS256' === $head_decoded->alg) {
            $secret = $this->client_secret;
        } else {
            if (empty($head_decoded->kid)) {
                throw new CoreException('Token key ID is missing for RS256 token');
            }

            if (empty($body_decoded->iss) || ! in_array($body_decoded->iss, $this->authorized_iss)) {
                throw new CoreException('We cannot trust on a token issued by `'.$body_decoded->iss.'`');
            }

            $jwks_url                   = $body_decoded->iss.$this->jwks_path;
            $secret[$head_decoded->kid] = $this->JWKFetcher->requestJwkX5c($jwks_url, $head_decoded->kid);
        }

        try {
            return $this->decodeToken($jwt, $secret);
        } catch (\Exception $e) {
            throw new CoreException($e->getMessage());
        }
    }

    /**
     * Wrapper for JWT::decode().
     *
     * @param string       $jwt    JWT to decode.
     * @param string|array $secret Secret to use.
     *
     * @return mixed
     *
     * @codeCoverageIgnore
     */
    protected function decodeToken($jwt, $secret)
    {
        return JWT::decode($jwt, $secret, $this->supported_algs);
    }

    /**
     * Base64 decode a string.
     *
     * @param string $encoded Base64 encoded string.
     *
     * @return string
     *
     * @codeCoverageIgnore
     */
    private function decodeB64($encoded)
    {
        return JWT::urlsafeB64Decode($encoded);
    }

    /**
     * Base64 and JSON decode a string.
     *
     * @param string $segment Base64 encoded JSON string.
     *
     * @return object
     *
     * @codeCoverageIgnore
     */
    private function decodeTokenSegment($segment)
    {
        return JWT::jsonDecode($this->decodeB64($segment));
    }

    /**
     * Check whether the $alg parameter is supported.
     *
     * @param string $alg Algorithm to check.
     *
     * @return boolean
     *
     * @codeCoverageIgnore
     */
    private function supportsAlg($alg)
    {
        return in_array( $alg, $this->supported_algs );
    }
}
