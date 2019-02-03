<?php
/**
 * Main entry point to the Auth0 SDK
 *
 * @package Auth0\SDK
 */

namespace Auth0\SDK;

use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\ApiException;
use Auth0\SDK\Store\EmptyStore;
use Auth0\SDK\Store\SessionStore;
use Auth0\SDK\Store\StoreInterface;
use Auth0\SDK\API\Authentication;
use Auth0\SDK\API\Helpers\State\StateHandler;
use Auth0\SDK\API\Helpers\State\SessionStateHandler;
use Auth0\SDK\API\Helpers\State\DummyStateHandler;
use Firebase\JWT\JWT;

/**
 * Class Auth0
 * Provides access to Auth0 authentication functionality.
 *
 * @package Auth0\SDK
 */
class Auth0
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
        'id_token',
    ];

    /**
     * Auth0 URL Map (not currently used in the SDK)
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
     * Auth0 Domain, found in Application settings
     *
     * @var string
     */
    protected $domain;

    /**
     * Auth0 Client ID, found in Application settings
     *
     * @var string
     */
    protected $clientId;

    /**
     * Auth0 Client Secret, found in Application settings
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * True if the client secret is base64 encoded, false if not.
     * This information can be found in your Auth0 Application settings below the Client Secret field.
     *
     * @var boolean
     */
    protected $clientSecretEncoded;

    /**
     * Response mode
     *
     * @var string
     */
    protected $responseMode = 'query';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType = 'code';

    /**
     * Audience for the API being used
     *
     * @var string
     */
    protected $audience;

    /**
     * Scope for ID tokens and /userinfo endpoint
     *
     * @var string
     */
    protected $scope;

    /**
     * Auth0 Refresh Token
     *
     * @var string
     */
    protected $refreshToken;

    /**
     * Redirect URI needed on OAuth2 requests, aka callback URL
     *
     * @var string
     */
    protected $redirectUri;

    /**
     * Debug mode flag.
     *
     * @var boolean
     */
    protected $debugMode;

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
    protected $accessToken;

    /**
     * JWT for identity information
     *
     * @var string
     */
    protected $idToken;

    /**
     * Storage engine for persistence
     *
     * @var StoreInterface
     */
    protected $store;

    /**
     * The user object provided by Auth0
     *
     * @var string
     */
    protected $user;

    /**
     * Authentication Client.
     *
     * @var \Auth0\SDK\API\Authentication
     */
    protected $authentication;

    /**
     * Configuration options for Guzzle HTTP client.
     *
     * @var array
     *
     * @see http://docs.guzzlephp.org/en/stable/request-options.html
     */
    protected $guzzleOptions;

    /**
     * Algorithm used for ID token validation.
     * Can be "HS256" or "RS256" only.
     *
     * @var string
     */
    protected $idTokenAlg;

    /**
     * Valid audiences for ID tokens.
     *
     * @var array
     */
    protected $idTokenAud = [];

    /**
     * Valid issuer(s) for ID tokens.
     *
     * @var array
     */
    protected $idTokenIss = [];

    /**
     * State Handler.
     *
     * @var StateHandler
     */
    protected $stateHandler;

    /**
     * BaseAuth0 Constructor.
     *
     * @param  array $config - Required configuration options.
     * Configuration:
     *     - domain                 (String)  Required. Auth0 domain for your tenant
     *     - client_id              (String)  Required. Client ID found in the Application settings
     *     - client_secret          (String)  Required. Client Secret found in the Application settings
     *     - redirect_uri           (String)  Required. Authentication callback URI
     *     - response_mode          (String)  Optional. Default `query`
     *     - response_type          (String)  Optional. Default `code`
     *     - persist_user           (Boolean) Optional. Persist the user info, default true
     *     - persist_access_token   (Boolean) Optional. Persist the access token, default false
     *     - persist_refresh_token  (Boolean) Optional. Persist the refresh token, default false
     *     - persist_id_token       (Boolean) Optional. Persist the ID token, default false
     *     - store                  (Mixed)   Optional. A class that implements StorageInterface or false for none;
     *                                                  leave empty to default to SessionStore
     *     - state_handler          (Mixed)   Optional. A class that implements StateHandler of false for none;
     *                                                  leave empty to default to SessionStore SessionStateHandler
     *     - debug                  (Boolean) Optional. Turn on debug mode, default false
     *     - guzzle_options         (Object)  Optional. Options passed to Guzzle
     *     - session_base_name      (String)  Optional. A common prefix for all session keys. Default `auth0_`
     *     - session_cookie_expires (Integer) Optional. Seconds for session cookie to expire (if default store is used). Default `604800`
     * @throws CoreException If `domain` is not provided.
     * @throws CoreException If `client_id` is not provided.
     * @throws CoreException If `client_secret` is not provided.
     * @throws CoreException If `redirect_uri` is not provided.
     */
    public function __construct(array $config)
    {
        if (empty($config['domain'])) {
            throw new CoreException('Invalid domain');
        }

        if (empty($config['client_id'])) {
            throw new CoreException('Invalid client_id');
        }

        if (empty($config['client_secret'])) {
            throw new CoreException('Invalid client_secret');
        }

        if (empty($config['redirect_uri'])) {
            throw new CoreException('Invalid redirect_uri');
        }

        $this->domain              = $config['domain'];
        $this->clientId            = $config['client_id'];
        $this->clientSecret        = $config['client_secret'];
        $this->clientSecretEncoded = ! empty( $config['secret_base64_encoded'] );
        $this->redirectUri         = $config['redirect_uri'];

        if (isset($config['audience'])) {
            $this->audience = $config['audience'];
        }

        if (isset($config['response_mode'])) {
            $this->responseMode = $config['response_mode'];
        }

        if (isset($config['response_type'])) {
            $this->responseType = $config['response_type'];
        }

        if (isset($config['scope'])) {
            $this->scope = $config['scope'];
        }

        if (isset($config['guzzle_options'])) {
            $this->guzzleOptions = $config['guzzle_options'];
        }

        // If a token algorithm is passed, make sure it's a specific string.
        if (! empty($config['id_token_alg'])) {
            if (! in_array( $config['id_token_alg'], ['HS256', 'RS256'] )) {
                throw new CoreException('Invalid id_token_alg; must be "HS256" or "RS256"');
            }

            $this->idTokenAlg = $config['id_token_alg'];
        }

        // If a token audience is passed, make sure it's an array.
        if (! empty($config['id_token_aud'])) {
            if (! is_array( $config['id_token_aud'] )) {
                throw new CoreException('Invalid id_token_aud; must be an array of string values');
            }

            $this->idTokenAud = $config['id_token_aud'];
        }

        // If a token issuer is passed, make sure it's an array.
        if (! empty($config['id_token_iss'])) {
            if (! is_array( $config['id_token_iss'] )) {
                throw new CoreException('Invalid id_token_iss; must be an array of string values');
            }

            $this->idTokenIss = $config['id_token_iss'];
        }

        $this->debugMode = isset($config['debug']) ? $config['debug'] : false;

        // User info is persisted by default.
        if (isset($config['persist_user']) && false === $config['persist_user']) {
            $this->dontPersist('user');
        }

        // Access token is not persisted by default.
        if (! isset($config['persist_access_token']) || false === $config['persist_access_token']) {
            $this->dontPersist('access_token');
        }

        // Refresh token is not persisted by default.
        if (! isset($config['persist_refresh_token']) || false === $config['persist_refresh_token']) {
            $this->dontPersist('refresh_token');
        }

        // ID token is not persisted by default.
        if (! isset($config['persist_id_token']) || false === $config['persist_id_token']) {
            $this->dontPersist('id_token');
        }

        $session_base_name = ! empty( $config['session_base_name'] ) ? $config['session_base_name'] : SessionStore::BASE_NAME;

        $session_cookie_expires = isset( $config['session_cookie_expires'] ) ? $config['session_cookie_expires'] : SessionStore::COOKIE_EXPIRES;

        if (isset($config['store'])) {
            if ($config['store'] === false) {
                $emptyStore = new EmptyStore();
                $this->setStore($emptyStore);
            } else {
                $this->setStore($config['store']);
            }
        } else {
            $sessionStore = new SessionStore($session_base_name, $session_cookie_expires);
            $this->setStore($sessionStore);
        }

        if (isset($config['state_handler'])) {
            if ($config['state_handler'] === false) {
                $this->stateHandler = new DummyStateHandler();
            } else {
                $this->stateHandler = $config['state_handler'];
            }
        } else {
            $stateStore         = new SessionStore($session_base_name, $session_cookie_expires);
            $this->stateHandler = new SessionStateHandler($stateStore);
        }

        $this->authentication = new Authentication(
            $this->domain,
            $this->clientId,
            $this->clientSecret,
            $this->audience,
            $this->scope,
            $this->guzzleOptions
        );

        $this->user         = $this->store->get('user');
        $this->accessToken  = $this->store->get('access_token');
        $this->idToken      = $this->store->get('id_token');
        $this->refreshToken = $this->store->get('refresh_token');
    }

    /**
     * Redirect to the hosted login page for a specific client
     *
     * @param null  $state            - state value.
     * @param null  $connection       - connection to use.
     * @param array $additionalParams - additional, valid parameters.
     *
     * @return void
     *
     * @see \Auth0\SDK\API\Authentication::get_authorize_link()
     * @see https://auth0.com/docs/api/authentication#login
     */
    public function login($state = null, $connection = null, array $additionalParams = [])
    {
        $params = [];
        if ($this->audience) {
            $params['audience'] = $this->audience;
        }

        if ($this->scope) {
            $params['scope'] = $this->scope;
        }

        if ($state === null) {
            $state = $this->stateHandler->issue();
        } else {
            $this->stateHandler->store($state);
        }

        $params['response_mode'] = $this->responseMode;

        if (! empty($additionalParams) && is_array($additionalParams)) {
            $params = array_replace($params, $additionalParams);
        }

        $url = $this->authentication->get_authorize_link(
            $this->responseType,
            $this->redirectUri,
            $connection,
            $state,
            $params
        );

        header('Location: '.$url);
        exit;
    }

    /**
     * Get userinfo from persisted session or from a code exchange
     *
     * @return array|null
     *
     * @throws ApiException (see self::exchange()).
     * @throws CoreException (see self::exchange()).
     */
    public function getUser()
    {
        if (! $this->user) {
            $this->exchange();
        }

        return $this->user;
    }

    /**
     * Get access token from persisted session or from a code exchange
     *
     * @return string|null
     *
     * @throws ApiException (see self::exchange()).
     * @throws CoreException (see self::exchange()).
     */
    public function getAccessToken()
    {
        if (! $this->accessToken) {
            $this->exchange();
        }

        return $this->accessToken;
    }

    /**
     * Get ID token from persisted session or from a code exchange
     *
     * @return string|null
     *
     * @throws ApiException (see self::exchange()).
     * @throws CoreException (see self::exchange()).
     */
    public function getIdToken()
    {
        if (! $this->idToken) {
            $this->exchange();
        }

        return $this->idToken;
    }

    /**
     * Get refresh token from persisted session or from a code exchange
     *
     * @return string|null
     *
     * @throws ApiException (see self::exchange()).
     * @throws CoreException (see self::exchange()).
     */
    public function getRefreshToken()
    {
        if (! $this->refreshToken) {
            $this->exchange();
        }

        return $this->refreshToken;
    }

    /**
     * Exchange authorization code for access, ID, and refresh tokens
     *
     * @throws CoreException - if an active session already or state cannot be validated.
     * @throws ApiException - if access token is invalid.
     *
     * @return boolean
     *
     * @see https://auth0.com/docs/api-auth/tutorials/authorization-code-grant
     */
    public function exchange()
    {
        $code = $this->getAuthorizationCode();
        if (! $code) {
            return false;
        }

        $state = $this->getState();

        if (! $this->stateHandler->validate($state)) {
            throw new CoreException('Invalid state');
        }

        if ($this->user) {
            throw new CoreException('Can\'t initialize a new session while there is one active session already');
        }

        $response = $this->authentication->code_exchange($code, $this->redirectUri);

        if (empty($response['access_token'])) {
            throw new ApiException('Invalid access_token - Retry login.');
        }

        $accessToken = $response['access_token'];

        $refreshToken = false;
        if (isset($response['refresh_token'])) {
            $refreshToken = $response['refresh_token'];
        }

        $idToken = false;
        if (isset($response['id_token'])) {
            $idToken = $response['id_token'];
        }

        $this->setAccessToken($accessToken);
        $this->setIdToken($idToken);
        $this->setRefreshToken($refreshToken);

        $user = $this->authentication->userinfo($accessToken);
        $this->setUser($user);

        return true;
    }

    /**
     * Renews the access token and ID token using an existing refresh token.
     * Scope "offline_access" must be declared in order to obtain refresh token for later token renewal.
     *
     * @throws CoreException If the Auth0 object does not have access token and refresh token
     * @throws ApiException If the Auth0 API did not renew access and ID token properly
     * @link   https://auth0.com/docs/tokens/refresh-token/current
     */
    public function renewTokens()
    {
        if (! $this->accessToken) {
            throw new CoreException('Can\'t renew the access token if there isn\'t one valid');
        }

        if (! $this->refreshToken) {
            throw new CoreException('Can\'t renew the access token if there isn\'t a refresh token available');
        }

        $response = $this->authentication->oauth_token([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ]);

        if (empty($response['access_token']) || empty($response['id_token'])) {
            throw new ApiException('Token did not refresh correctly. Access or ID token not provided.');
        }

        $this->setAccessToken($response['access_token']);
        $this->setIdToken($response['id_token']);
    }

    /**
     * Set the user property to a userinfo array and, if configured, persist
     *
     * @param array $user - userinfo from Auth0.
     *
     * @return $this
     */
    public function setUser(array $user)
    {
        if (in_array('user', $this->persistantMap)) {
            $this->store->set('user', $user);
        }

        $this->user = $user;
        return $this;
    }

    /**
     * Sets and persists the access token.
     *
     * @param string $accessToken - access token returned from the code exchange.
     *
     * @return \Auth0\SDK\Auth0
     */
    public function setAccessToken($accessToken)
    {
        if (in_array('access_token', $this->persistantMap)) {
            $this->store->set('access_token', $accessToken);
        }

        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * Sets, validates, and persists the ID token.
     *
     * @param string $idToken - ID token returned from the code exchange.
     *
     * @return \Auth0\SDK\Auth0
     *
     * @throws CoreException
     * @throws Exception\InvalidTokenException
     */
    public function setIdToken($idToken)
    {
        $jwtVerifier = new JWTVerifier([
            'valid_audiences' => ! empty($this->idTokenAud) ? $this->idTokenAud : [ $this->clientId ],
            'supported_algs' => $this->idTokenAlg ? [ $this->idTokenAlg ] : [ 'HS256', 'RS256' ],
            'authorized_iss' => $this->idTokenIss ? $this->idTokenIss : [ 'https://'.$this->domain.'/' ],
            'client_secret' => $this->clientSecret,
            'secret_base64_encoded' => $this->clientSecretEncoded,
            'guzzle_options' => $this->guzzleOptions,
        ]);
        $jwtVerifier->verifyAndDecode( $idToken );

        if (in_array('id_token', $this->persistantMap)) {
            $this->store->set('id_token', $idToken);
        }

        $this->idToken = $idToken;
        return $this;
    }

    /**
     * Sets and persists the refresh token.
     *
     * @param string $refreshToken - refresh token returned from the code exchange.
     *
     * @return \Auth0\SDK\Auth0
     */
    public function setRefreshToken($refreshToken)
    {
        if (in_array('refresh_token', $this->persistantMap)) {
            $this->store->set('refresh_token', $refreshToken);
        }

        $this->refreshToken = $refreshToken;
        return $this;
    }

    /**
     * Get the authorization code from POST or GET, depending on response_mode
     *
     * @return string|null
     *
     * @see https://auth0.com/docs/api-auth/tutorials/authorization-code-grant
     */
    protected function getAuthorizationCode()
    {
        $code = null;
        if ($this->responseMode === 'query' && isset($_GET['code'])) {
            $code = $_GET['code'];
        } else if ($this->responseMode === 'form_post' && isset($_POST['code'])) {
            $code = $_POST['code'];
        }

        return $code;
    }

    /**
     * Get the state from POST or GET, depending on response_mode
     *
     * @return string|null
     *
     * @see https://auth0.com/docs/api-auth/tutorials/authorization-code-grant
     */
    protected function getState()
    {
        $state = null;
        if ($this->responseMode === 'query' && isset($_GET['state'])) {
            $state = $_GET['state'];
        } else if ($this->responseMode === 'form_post' && isset($_POST['state'])) {
            $state = $_POST['state'];
        }

        return $state;
    }

    /**
     * Delete any persistent data and clear out all stored properties
     *
     * @return void
     */
    public function logout()
    {
        $this->deleteAllPersistentData();
        $this->accessToken  = null;
        $this->user         = null;
        $this->idToken      = null;
        $this->refreshToken = null;
    }

    /**
     * Delete all persisted data
     *
     * @return void
     */
    public function deleteAllPersistentData()
    {
        foreach ($this->persistantMap as $key) {
            $this->store->delete($key);
        }
    }

    /**
     * Removes $name from the persistantMap, thus not persisting it when we set the value.
     *
     * @param string $name - value to remove from persistence.
     *
     * @return void
     */
    private function dontPersist($name)
    {
        $key = array_search($name, $this->persistantMap);
        if ($key !== false) {
            unset($this->persistantMap[$key]);
        }
    }

    /**
     * Set the storage engine that implements StoreInterface
     *
     * @param StoreInterface $store - storage engine to use.
     *
     * @return \Auth0\SDK\Auth0
     */
    public function setStore(StoreInterface $store)
    {
        $this->store = $store;
        return $this;
    }

    /**
     * Set the debugger closure
     *
     * @param \Closure $debugger - debugger closure to use.
     *
     * @return void
     */
    public function setDebugger(\Closure $debugger)
    {
        $this->debugger = $debugger;
    }
}
