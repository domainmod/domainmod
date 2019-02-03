<?php
namespace GJClasses;

use Auth0\SDK\Auth0 as Auth0SDK;

class Auth0
{
    public $auth0;

    public function __construct()
    {
        $this->auth0 = new Auth0SDK([
            'domain' => GJC_AUTH0_DOMAIN,
            'client_id' => GJC_AUTH0_CLIENT_ID,
            'client_secret' => GJC_AUTH0_CLIENT_SECRET,
            'redirect_uri' => GJC_AUTH0_CALLBACK_URL,
            'audience' => GJC_AUTH0_AUDIENCE,
            'scope' => GJC_AUTH0_SCOPE,
            'persist_id_token' => GJC_AUTH0_PER_ID_TOKEN,
            'persist_access_token' => GJC_AUTH0_PER_ACCESS_TOKEN,
            'persist_refresh_token' => GJC_AUTH0_PER_REFRESH_TOKEN,
        ]);
    }

    public function check()
    {
        $user_info = $this->auth0->getUser();

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
        $this->auth0->login();
    }

    public function getUser()
    {
        return $this->auth0->getUser();
    }

    public function logout()
    {
        $this->auth0->logout();
        $logout_url = sprintf('http://%s/v2/logout?client_id=%s&returnTo=%s', GJC_AUTH0_DOMAIN, GJC_AUTH0_CLIENT_ID, GJC_AUTH0_LOGIN_URL);
        header('Location: ' . $logout_url);
        exit;
    }

}
