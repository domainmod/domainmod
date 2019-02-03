<?php

namespace Auth0\SDK;

/**
 * This class provides access to Auth0 JWT decoder.
 *
 * @deprecated - Provided for bring backwards-compat and will be soon removed; use Auth0\SDK\JWTVerifier instead.
 *
 * @codeCoverageIgnore - Deprecated
 */
class Auth0JWT
{

    public static function decode($jwt, $valid_audiences, $client_secret, array $authorized_iss = [], $cache = null)
    {
        $verifier = new JWTVerifier([
            'valid_audiences' => is_array($valid_audiences) ? $valid_audiences : [$valid_audiences],
            'client_secret' => $client_secret,
            'authorized_iss' => $authorized_iss,
            'cache' => $cache,
        ]);

        return $verifier->verifyAndDecode($jwt);
    }
}
