<?php
namespace Auth0\SDK\API\Helpers;

use Firebase\JWT\JWT;

/**
 * Class TokenGenerator.
 * Generates HS256 ID tokens.
 *
 * @package Auth0\SDK\API\Helpers
 */
class TokenGenerator
{
    /**
     * Default token expiration time.
     */
    const DEFAULT_LIFETIME = 3600;

    /**
     * Audience for the ID token.
     *
     * @var string
     */
    protected $audience;

    /**
     * Secret used to encode the token.
     *
     * @var string
     */
    protected $secret;

    /**
     * TokenGenerator constructor.
     *
     * @param string $audience ID token audience to set.
     * @param string $secret   Token encryption secret to encode the token.
     */
    public function __construct($audience, $secret)
    {
        $this->audience = $audience;
        $this->secret   = $secret;
    }

    /**
     * Create the ID token.
     *
     * @param array   $scopes         Array of scopes to include.
     * @param integer $lifetime       Lifetime of the token, in seconds.
     * @param boolean $secret_encoded True to base64 decode the client secret.
     *
     * @return string
     */
    public function generate(array $scopes, $lifetime = self::DEFAULT_LIFETIME, $secret_encoded = true)
    {
        $time           = time();
        $payload        = [
            'iat' => $time,
            'scopes' => $scopes,
            'exp' => $time + $lifetime,
            'aud' => $this->audience,
        ];
        $payload['jti'] = md5(json_encode($payload));

        $secret = $secret_encoded ? base64_decode(strtr($this->secret, '-_', '+/')) : $this->secret;

        return JWT::encode($payload, $secret);
    }
}
