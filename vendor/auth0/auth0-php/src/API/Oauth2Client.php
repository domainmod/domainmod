<?php
namespace Auth0\SDK\API;

use Auth0\SDK\API\Helpers\ApiClient;
use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\ApiException;
use Auth0\SDK\Store\EmptyStore;
use Auth0\SDK\Store\SessionStore;
use OAuth2\Client;

/**
 * This class provides access to Auth0 Platform.
 *
 * @deprecated - Deprecated in 5.2.1; use \Auth0\SDK\Auth0 instead.
 *
 * @codeCoverageIgnore - Deprecated
 */
class Oauth2Client
{

    /**
     * Available keys to persist data.
     *
     * @var array
     */
    public $persistantMap = [
        'refresh_token',
        'access_token',
        'user',
        'id_token'
    ];

    /**
     * Auth0 URL Map.
     *
     * @var array
     */
    public static $URL_MAP = [
        'api'           => 'https://{domain}/api/',
        'authorize'     => 'https://{domain}/authorize/',
        'token'     => 'https://{domain}/oauth/token/',
        'user_info'     => 'https://{domain}/userinfo/',
    ];

    /**
     * Auth0 Domain.
     *
     * @var string
     */
    protected $domain;

    /**
     * Auth0 Client ID
     *
     * @var string
     */
    protected $client_id;

    /**
     * Auth0 Client Secret
     *
     * @var string
     */
    protected $client_secret;

    /**
     * Auth0 Refresh Token
     *
     * @var string
     */
    protected $refresh_token;

    /**
     * Redirect URI needed on OAuth2 requests.
     *
     * @var string
     */
    protected $redirect_uri;

    /**
     * Debug mode flag.
     *
     * @var boolean
     */
    protected $debug_mode;

    /**
     * Debugger function.
     * Will be called only if $debug_mode is true.
     *
     * @var \Closure
     */
    protected $debugger;

    /**
     * The access token retrieved after authorization.
     * NULL means that there is no authorization yet.
     *
     * @var string
     */
    protected $access_token;

    /**
     * JWT for identity information
     *
     * @var string
     */
    protected $id_token;

    /**
     * The user object
     *
     * @var string
     */
    protected $user;

    /**
     * Storage object
     *
     * @var string
     */
    protected $store;

    /**
     * OAuth2 Client.
     *
     * @var \OAuth2\Client
     */
    protected $oauth_client;

    /**
     * BaseAuth0 Constructor.
     *
     * @deprecated - Deprecated in 5.2.1; use \Auth0\SDK\Auth0 instead.
     *
     * Configuration:
     *     - domain (String) - Required. Should match your Auth0 domain
     *     - client_id (String) - Required. Client ID, found in Application > Settings in the Auth0 dashboard.
     *     - client_secret (String) - Required. Client Secret, found in Application > Settings in the Auth0 dashboard.
     *     - redirect_uri (String) - Required. The uri of the auth callback, used as a security method
     *     - persist_user (Boolean) - Optional. Indicates if you want to persist the user info, default true
     *     - persist_access_token (Boolean) - Optional. True to, persist the access token; default false
     *     - persist_refresh_token (Boolean) - Optional. True to persist the refresh token; default false
     *     - persist_id_token (Boolean) - Optional. Indicates if you want to persist the id token, default false
     *     - store (Mixed) - Optional. Indicates how we store the persisting methods; default is session store.
     *          You can pass false to avoid storing it or a class that implements a store (get, set, delete).
     *     - debug (Boolean) - Optional. Default false
     *
     * @param array $config Required
     *
     * @throws CoreException If `domain` is not provided.
     * @throws CoreException If `client_id` is not provided.
     * @throws CoreException If `client_secret` is not provided.
     * @throws CoreException If `redirect_uri` is not provided.
     */
    public function __construct(array $config)
    {
        // check for system requirements
        $this->checkRequirements();

        // now we are ready to go on...
        if (isset($config['domain'])) {
            $this->domain = $config['domain'];
        } else {
            throw new CoreException('Invalid domain');
        }

        if (isset($config['client_id'])) {
            $this->client_id = $config['client_id'];
        } else {
            throw new CoreException('Invalid client_id');
        }

        if (isset($config['client_secret'])) {
            $this->client_secret = $config['client_secret'];
        } else {
            throw new CoreException('Invalid client_secret');
        }

        if (isset($config['redirect_uri'])) {
            $this->redirect_uri = $config['redirect_uri'];
        } else {
            throw new CoreException('Invalid redirect_uri');
        }

        if (isset($config['debug'])) {
            $this->debug_mode = $config['debug'];
        } else {
            $this->debug_mode = false;
        }

        // User info is persisted unless said otherwise
        if (isset($config['persist_user']) && $config['persist_user'] === false) {
            $this->dontPersist('user');
        }

        // Access token is not persisted unless said otherwise
        if (! isset($config['persist_access_token']) || (isset($config['persist_access_token']) &&
                $config['persist_access_token'] === false)) {
            $this->dontPersist('access_token');
        }

        // Refresh token is not persisted unless said otherwise
        if (! isset($config['persist_refresh_token']) || (isset($config['persist_refresh_token']) &&
                $config['persist_refresh_token'] === false)) {
            $this->dontPersist('refresh_token');
        }

        // Id token is not per persisted unless said otherwise
        if (! isset($config['persist_id_token']) || (isset($config['persist_id_token']) &&
                $config['persist_id_token'] === false)) {
            $this->dontPersist('id_token');
        }

        if (isset($config['store'])) {
            if ($config['store'] === false) {
                $this->store = new EmptyStore();
            } else {
                $this->store = $config['store'];
            }
        } else {
            $this->store = new SessionStore();
        }

        $this->oauth_client = new Client($this->client_id, $this->client_secret);

        $this->user          = $this->store->get('user');
        $this->access_token  = $this->store->get('access_token');
        $this->id_token      = $this->store->get('id_token');
        $this->refresh_token = $this->store->get('refresh_token');

        if (! $this->access_token) {
            $this->oauth_client->setAccessToken($this->access_token);
        }
    }

    /**
     * Removes $name from the persistantMap, thus not persisting it when we set the value
     *
     * @param string $name The value to remove
     */
    private function dontPersist($name)
    {
        $key = array_search($name, $this->persistantMap);
        if ($key !== false) {
            unset($this->persistantMap[$key]);
        }
    }

    /**
     * Exchanges the code from the URI parameters for an access token, id token and user info
     *
     * @return boolean Whether it exchanged the code or not correctly
     * @throws ApiException
     */
    public function exchangeCode()
    {
        $code = isset($_GET['code']) ? $_GET['code'] : (isset($_POST['code']) ? $_POST['code'] : null);

        if (! isset($code)) {
            $this->debugInfo('No code found in _GET or _POST params.');
            return false;
        }

        $this->debugInfo('Code: '.$code);

        // Generate the url to the API that will give us the access token and id token
        $auth_url = $this->generateUrl('token');
        // Make the call
        $response = $this->oauth_client->getAccessToken($auth_url, 'authorization_code', [
            'code' => $code,
            'redirect_uri' => $this->redirect_uri
        ], [
            'Auth0-Client' => ApiClient::getInfoHeadersData()->build()
        ]);

        $auth0_response = $response['result'];

        if ($response['code'] !== 200) {
            if (isset($auth0_response['error'])) {
                throw new ApiException($auth0_response['error'].': '.$auth0_response['error_description']);
            } else {
                throw new ApiException($auth0_response);
            }
        }

        $this->debugInfo(json_encode($auth0_response));
        $access_token  = (isset($auth0_response['access_token'])) ? $auth0_response['access_token'] : false;
        $refresh_token = (isset($auth0_response['refresh_token'])) ? $auth0_response['refresh_token'] : false;
        $id_token      = (isset($auth0_response['id_token'])) ? $auth0_response['id_token'] : false;

        if (! $access_token) {
            throw new ApiException('Invalid access_token - Retry login.');
        }

        if (! $id_token) {
            // id_token is not mandatory anymore. There is no need to force openid connect
            $this->debugInfo('Missing id_token after code exchange. Remember to ask for openid scope.');
        }

        // Set the access token in the oauth client for future calls to the Auth0 API
        $this->oauth_client->setAccessToken($access_token);
        $this->oauth_client->setAccessTokenType(Client::ACCESS_TOKEN_BEARER);

        // Set it and persist it, if needed
        $this->setAccessToken($access_token);
        $this->setIdToken($id_token);
        $this->setRefreshToken($refresh_token);

        $userinfo_url = $this->generateUrl('user_info');
        $user         = $this->oauth_client->fetch($userinfo_url);

        $this->setUser($user['result']);

        return true;
    }

    /**
     * Requests user info to Auth0 server.
     *
     * @return array
     *
     * @throws ApiException
     */
    public function getUser()
    {
        // Ensure we have the user info
        if ($this->user === null) {
            $this->exchangeCode();
        }

        if (! is_array($this->user)) {
            return null;
        }

        return $this->user;
    }

    /**
     * Updates the user metadata. This end up calling the path /users/{id_user}
     * To delete an attribute, just set it null. ie: [ 'old_attr' => null ]
     * It will only update the existing attrs and keep the others untouched
     *
     * TODO: Replace Auth0Api with Management
     *
     * @see https://auth0.com/docs/apiv2#!/users/patch_users_by_id
     *
     * @param array $metadata
     *
     * @throws ApiException
     */
    public function updateUserMetadata($metadata)
    {
        $auth0Api = new Auth0Api($this->getIdToken(), $this->domain);

        $user = $auth0Api->users->update($this->user['user_id'], ['user_metadata' => $metadata]);

        $this->setUser($user);
    }

    /**
     *
     * @return array
     */
    public function getUserMetadata()
    {
        return isset($this->user['user_metadata']) ? $this->user['user_metadata'] : [];
    }

    /**
     *
     * @return array
     */
    public function getAppMetadata()
    {
        return isset($this->user['app_metadata']) ? $this->user['app_metadata'] : [];
    }

    /**
     *
     * @param  $user
     * @return Oauth2Client
     */
    public function setUser($user)
    {
        $key = array_search('user', $this->persistantMap);
        if ($key !== false) {
            $this->store->set('user', $user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * Sets and persists $access_token.
     *
     * @param string $access_token
     *
     * @return Oauth2Client
     */
    public function setAccessToken($access_token)
    {
        $key = array_search('access_token', $this->persistantMap);
        if ($key !== false) {
            $this->store->set('access_token', $access_token);
        }

        $this->access_token = $access_token;

        return $this;
    }

    /**
     * Sets and persists $refresh_token.
     *
     * @param string $refresh_token
     *
     * @return Oauth2Client
     */
    public function setRefreshToken($refresh_token)
    {
        $key = array_search('refresh_token', $this->persistantMap);
        if ($key !== false) {
            $this->store->set('refresh_token', $refresh_token);
        }

        $this->refresh_token = $refresh_token;

        return $this;
    }

    /**
     * Gets an access_token
     *
     * @return string
     *
     * @throws ApiException
     */
    final public function getAccessToken()
    {
        if ($this->access_token === null) {
            $this->exchangeCode();
        }

        return $this->access_token;
    }

    /**
     * Gets a refresh_token
     *
     * @return string
     */
    final public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * Sets and persists $id_token.
     *
     * @param string $id_token
     *
     * @return Oauth2Client
     */
    public function setIdToken($id_token)
    {
        $key = array_search('id_token', $this->persistantMap);
        if ($key !== false) {
            $this->store->set('id_token', $id_token);
        }

        $this->id_token = $id_token;

        return $this;
    }

    /**
     * Gets the id token
     *
     * @return string
     *
     * @throws ApiException
     */
    final public function getIdToken()
    {
        if ($this->id_token === null) {
            $this->exchangeCode();
        }

        return $this->id_token;
    }

    /**
     * Logout (removes all persistent data)
     */
    final public function logout()
    {
        $this->deleteAllPersistentData();
        $this->access_token  = null;
        $this->user          = null;
        $this->id_token      = null;
        $this->refresh_token = null;
    }


    /**
     * Constructs an API URL.
     *
     * @param string $domain_key
     * @param string $path
     *
     * @return string
     */
    final protected function generateUrl($domain_key, $path = '/')
    {
        $base_domain = self::$URL_MAP[$domain_key];
        $base_domain = str_replace('{domain}', $this->domain, $base_domain);

        if ($path[0] === '/') {
            $path = substr($path, 1);
        }

        return $base_domain.$path;
    }

    /**
     * Checks for all dependencies of SDK or API.
     *
     * @throws CoreException If CURL extension is not found.
     * @throws CoreException If JSON extension is not found.
     */
    final public function checkRequirements()
    {
        if (! function_exists('curl_version')) {
            throw new CoreException('CURL extension is needed to use Auth0 SDK. Not found.');
        }

        if (! function_exists('json_decode')) {
            throw new CoreException('JSON extension is needed to use Auth0 SDK. Not found.');
        }
    }

    /**
     * If debug mode is set, sends $info to debugger \Closure.
     *
     * @param mixed $info Info to debug. It will be converted to string.
     */
    public function debugInfo($info)
    {
        if ($this->debug_mode && (is_object($this->debugger) && ($this->debugger instanceof \Closure))) {
            list(, $caller) = debug_backtrace(false);

            $caller_function = $caller['function'];
            $caller_class    = $caller['class'];

            $this->debugger->__invoke($caller_class.'::'.$caller_function.' > '.$info);
        }
    }

    /**
     * Deletes all persistent data, for every mapped key.
     */
    public function deleteAllPersistentData()
    {
        foreach ($this->persistantMap as $key) {
            $this->store->delete($key);
        }
    }

    /**
     * Sets $domain.
     *
     * @param string $domain
     *
     * @return Oauth2Client
     */
    final public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Gets $domain
     *
     * @return string
     */
    final public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Sets $client_id.
     *
     * @param string $client_id
     *
     * @return Oauth2Client
     */
    final public function setClientId($client_id)
    {
        $this->client_id = $client_id;

        return $this;
    }

    /**
     * Gets $client_id.
     *
     * @return string
     */
    final public function getClientId()
    {
        return $this->client_id;
    }

    /**
     * Sets $client_secret.
     *
     * @param string $client_secret
     *
     * @return Oauth2Client
     */
    final public function setClientSecret($client_secret)
    {
        $this->client_secret = $client_secret;

        return $this;
    }

    /**
     * Gets $client_secret.
     *
     * @return string
     */
    final public function getClientSecret()
    {
        return $this->client_secret;
    }

    /**
     * Sets $redirect_uri.
     *
     * @param string $redirect_uri
     *
     * @return Oauth2Client
     */
    final public function setRedirectUri($redirect_uri)
    {
        $this->redirect_uri = $redirect_uri;

        return $this;
    }

    /**
     * Gets $redirect_uri.
     *
     * @return string
     */
    final public function getRedirectUri()
    {
        return $this->redirect_uri;
    }

    /**
     * Sets $debug_mode.
     *
     * @param boolean $debug_mode
     *
     * @return Oauth2Client
     */
    final public function setDebugMode($debug_mode)
    {
        $this->debug_mode = $debug_mode;

        return $this;
    }

    /**
     * Gets $debug_mode.
     *
     * @return boolean
     */
    final public function getDebugMode()
    {
        return $this->debug_mode;
    }

    /**
     * Sets $debugger.
     *
     * @param \Closure $debugger
     *
     * @return Oauth2Client
     */
    final public function setDebugger(\Closure $debugger)
    {
        $this->debugger = $debugger;

        return $this;
    }

    /**
     * Gets $debugger.
     *
     * @return \Closure
     */
    final public function getDebugger()
    {
        return $this->debugger;
    }
}
