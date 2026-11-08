<?php
namespace GJClasses;

use Auth0\SDK\Auth0 as Auth0SDK;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Exception\NetworkException;
use Auth0\SDK\Exception\StateException;

class Auth0
{
    public $auth0;

    public function __construct()
    {
        $this->auth0 = new Auth0SDK([
            'domain' => GJC_AUTH0_DOMAIN,
            'clientId' => GJC_AUTH0_CLIENT_ID,
            'clientSecret' => GJC_AUTH0_CLIENT_SECRET,
            'cookieSecret' => GJC_AUTH0_COOKIE_SECRET,
            'redirectUri' => GJC_AUTH0_CALLBACK_URL,
            'audience' => [GJC_AUTH0_AUDIENCE],
            'scope' => explode(' ', GJC_AUTH0_SCOPE),
            'persistIdToken' => GJC_AUTH0_PER_ID_TOKEN,
            'persistAccessToken' => GJC_AUTH0_PER_ACCESS_TOKEN,
            'persistRefreshToken' => GJC_AUTH0_PER_REFRESH_TOKEN,
            'queryUserInfo' => true,
            'cookieSecure' => str_starts_with(GJC_AUTH0_CALLBACK_URL, 'https://'),
        ]);
    }

    public function check()
    {
        $user_info = $this->auth0->getUser();

        // On the callback page, exchange the code Auth0 sent back for the user's session
        if (!$user_info && isset($_GET['code'], $_GET['state'])) {

            // A stale, replayed, or failed callback falls through to the login redirect below
            try {
                $this->auth0->exchange(GJC_AUTH0_CALLBACK_URL);
                $user_info = $this->auth0->getUser();
            } catch (StateException | NetworkException | InvalidTokenException | \JsonException $e) {
                $user_info = null;
            }

        }

        if (!$user_info) {

            header("Location: " . GJC_AUTH0_LOGIN_URL);
            exit;

        } else {

            return $user_info;

        }

    }

    public function errorCheck($error, $message)
    {
        if (isset($error) && $error != '') {
            echo 'Error: ' . $message;
            exit;
        }
        return;
    }

    public function login()
    {
        header('Location: ' . $this->auth0->login());
        exit;
    }

    public function getUser()
    {
        return $this->auth0->getUser();
    }

    public function logout()
    {
        header('Location: ' . $this->auth0->logout(GJC_AUTH0_LOGIN_URL));
        exit;
    }

}
