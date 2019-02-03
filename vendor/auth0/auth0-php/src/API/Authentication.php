<?php
/**
 * Authentication API wrapper
 *
 * PHP Version 5
 *
 * @package Auth0\SDK\API
 *
 * @see https://auth0.com/docs/api/authentication
 */
namespace Auth0\SDK\API;

use Auth0\SDK\API\Header\Authorization\AuthorizationBearer;
use Auth0\SDK\API\Header\ContentType;
use Auth0\SDK\API\Helpers\ApiClient;
use Auth0\SDK\Exception\ApiException;
use GuzzleHttp\Psr7;

/**
 * Class Authentication
 *
 * @package Auth0\SDK\API
 */
class Authentication
{

    private $client_id;

    private $client_secret;

    private $domain;

    private $apiClient;

    private $guzzleOptions;

    private $audience;

    private $scope;

    /**
     * Authentication constructor.
     *
     * @param string $domain
     * @param null   $client_id
     * @param null   $client_secret
     * @param null   $audience
     * @param null   $scope
     * @param array  $guzzleOptions
     */
    public function __construct(
        $domain,
        $client_id = null,
        $client_secret = null,
        $audience = null,
        $scope = null,
        $guzzleOptions = []
    )
    {
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
        $this->domain        = $domain;
        $this->guzzleOptions = $guzzleOptions;
        $this->audience      = $audience;
        $this->scope         = $scope;

        $this->setApiClient();
    }

    /**
     * Set an ApiClient for use in this object
     *
     * @return void
     */
    protected function setApiClient()
    {
        $apiDomain = "https://{$this->domain}";

        $client = new ApiClient(
            [
                'domain' => $apiDomain,
                'basePath' => '/',
                'guzzleOptions' => $this->guzzleOptions
            ]
        );

        $this->apiClient = $client;
    }

    /**
     * Builds and returns the `/authorize` url in order to initialize a new
     * authN/authZ transaction
     *
     * @param string $response_type
     * @param string $redirect_uri
     * @param string $connection        [optional]
     * @param string $state             [optional]
     * @param array  $additional_params [optional]
     *
     * @return string
     *
     * @see https://auth0.com/docs/api/authentication#!#get--authorize_db
     */
    public function get_authorize_link(
        $response_type,
        $redirect_uri,
        $connection = null,
        $state = null,
        $additional_params = []
    )
    {
        $additional_params['response_type'] = $response_type;
        $additional_params['redirect_uri']  = $redirect_uri;
        $additional_params['client_id']     = $this->client_id;

        if ($connection !== null) {
            $additional_params['connection'] = $connection;
        }

        if ($state !== null) {
            $additional_params['state'] = $state;
        }

        $query_string = Psr7\build_query($additional_params);

        return "https://{$this->domain}/authorize?{$query_string}";
    }

    /**
     * Build and return a SAMLP link
     *
     * @param string $client_id
     * @param string $connection
     *
     * @return string
     *
     * @see https://auth0.com/docs/connections/enterprise/samlp
     */
    public function get_samlp_link($client_id, $connection = '')
    {
        return "https://{$this->domain}/samlp/$client_id?connection=$connection";
    }

    /**
     * Build and return a SAMLP metadata link
     *
     * @param string $client_id
     *
     * @return string
     *
     * @see https://auth0.com/docs/connections/enterprise/samlp
     */
    public function get_samlp_metadata_link($client_id)
    {
        return "https://{$this->domain}/samlp/metadata/$client_id";
    }

    /**
     * Build and return a WS-Federation link
     *
     * @param string $client_id
     *
     * @return string
     *
     * @see https://auth0.com/docs/protocols/ws-fed
     */
    public function get_wsfed_link($client_id)
    {
        return "https://{$this->domain}/wsfed/$client_id";
    }

    /**
     * Build and return a WS-Federation metadata link
     *
     * @return string
     *
     * @see https://auth0.com/docs/protocols/ws-fed
     */
    public function get_wsfed_metadata_link()
    {
        return 'https://'.$this->domain.'/wsfed/FederationMetadata/2007-06/FederationMetadata.xml';
    }

    /**
     * Builds and returns the Logout url in order to terminate a SSO session
     *
     * @param null|string $returnTo
     * @param null|string $client_id
     * @param boolean     $federated
     *
     * @return string
     *
     * @see https://auth0.com/docs/api/authentication#logout
     */
    public function get_logout_link(
        $returnTo = null,
        $client_id = null,
        $federated = false
    )
    {
        $params = [];
        if ($returnTo !== null) {
            $params['returnTo'] = $returnTo;
        }

        if ($client_id !== null) {
            $params['client_id'] = $client_id;
        }

        if ($federated) {
            $params['federated'] = '';
        }

        $query_string = Psr7\build_query($params);

        return "https://{$this->domain}/v2/logout?$query_string";
    }

    /**
     * Authorize using an access token
     *
     * @param string $access_token
     * @param string $connection
     * @param string $scope
     * @param array  $additional_params
     *
     * @return mixed
     *
     * @deprecated - This feature is disabled by default for new tenants as of 8 June
     * 2017. Open the browser to do social authentication instead, which is
     * what Google and Facebook are recommending.
     *
     * @see https://auth0.com/docs/api/authentication#social-with-provider-s-access-token
     * @see https://developers.googleblog.com/2016/08/modernizing-oauth-interactions-in-native-apps.html
     * @see https://auth0.com/docs/api-auth/intro
     *
     * @codeCoverageIgnore - Deprecated
     */
    public function authorize_with_accesstoken(
        $access_token,
        $connection,
        $scope = 'openid',
        $additional_params = []
    )
    {
        $data = array_merge(
            $additional_params,
            [
                'client_id' => $this->client_id,
                'access_token' => $access_token,
                'connection' => $connection,
                'scope' => $scope,
            ]
        );

        return $this->apiClient->post()
        ->oauth()
        ->access_token()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Start passwordless login process for email
     *
     * @param string $email
     * @param string $type
     * @param array  $authParams
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#get-code-or-link
     */
    public function email_passwordless_start($email, $type, $authParams = [])
    {
        $data = [
            'client_id' => $this->client_id,
            'connection' => 'email',
            'send' => $type,
            'email' => $email,
        ];

        if (! empty($authParams)) {
            $data['authParams'] = $authParams;
        }

        return $this->apiClient->post()
        ->passwordless()
        ->start()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Start passwordless login process for SMS
     *
     * @param string $phone_number
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#get-code-or-link
     */
    public function sms_passwordless_start($phone_number)
    {
        $data = [
            'client_id' => $this->client_id,
            'connection' => 'sms',
            'phone_number' => $phone_number,
        ];

        return $this->apiClient->post()
        ->passwordless()
        ->start()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Verify SMS code
     *
     * @param string $phone_number
     * @param string $code
     * @param string $scope
     *
     * @return mixed
     *
     * @throws ApiException
     */
    public function sms_code_passwordless_verify(
        $phone_number,
        $code,
        $scope = 'openid'
    )
    {
        return $this->authorize_with_ro($phone_number, $code, $scope, 'sms');
    }

    /**
     * Verify email code
     *
     * @param string $email
     * @param string $code
     * @param string $scope
     *
     * @return mixed
     *
     * @throws ApiException
     */
    public function email_code_passwordless_verify($email, $code, $scope = 'openid')
    {
        return $this->authorize_with_ro($email, $code, $scope, 'email');
    }

    /**
     * DEPRECATED - This endpoint is part of the legacy authentication pipeline and
     * has been replaced in favor of the Password Grant. For more information on the
     * latest authentication pipeline refer to Introducing OIDC Conformant
     * Authentication.
     *
     * @param string      $username
     * @param string      $password
     * @param string      $scope
     * @param null|string $connection
     * @param null|string $id_token
     * @param null|string $device
     *
     * @return mixed
     *
     * @throws ApiException
     *
     * @deprecated Use `login` instead. Use only for passwordless verify
     *
     * @see https://auth0.com/docs/api/authentication#resource-owner
     * @see https://auth0.com/docs/api-auth/intro
     *
     * @codeCoverageIgnore - Deprecated
     */
    public function authorize_with_ro(
        $username,
        $password,
        $scope = 'openid',
        $connection = null,
        $id_token = null,
        $device = null
    )
    {
        $data = [
            'client_id' => $this->client_id,
            'username' => $username,
            'password' => $password,
            'scope' => $scope,
        ];
        if ($device !== null) {
            $data['device'] = $device;
        }

        if ($id_token !== null) {
            $data['id_token']   = $id_token;
            $data['grant_type'] = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
        } else {
            if ($connection === null) {
                throw new ApiException(
                    'You need to specify a connection for grant_type=password authentication'
                );
            }

            $data['grant_type'] = 'password';
            $data['connection'] = $connection;
        }

        return $this->apiClient->post()
        ->oauth()
        ->ro()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Get the current user's info
     *
     * @param string $access_token
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#user-profile
     */
    public function userinfo($access_token)
    {
        return $this->apiClient->get()
        ->userinfo()
        ->withHeader(new ContentType('application/json'))
        ->withHeader(new AuthorizationBearer($access_token))
        ->call();
    }

    /**
     * Obtain an impersonation URL to login as another user.
     * Impersonation functionality may be disabled by default for your tenant.
     *
     * @param string $access_token
     * @param string $user_id
     * @param string $protocol
     * @param string $impersonator_id
     * @param string $client_id
     * @param array  $additionalParameters
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#impersonation
     */
    public function impersonate(
        $access_token,
        $user_id,
        $protocol,
        $impersonator_id,
        $client_id,
        $additionalParameters = []
    )
    {
        $data = [
            'protocol' => $protocol,
            'impersonator_id' => $impersonator_id,
            'client_id' => $client_id,
            'additionalParameters' => $additionalParameters,
        ];

        return $this->apiClient->post()
        ->users($user_id)
        ->impersonate()
        ->withHeader(new ContentType('application/json'))
        ->withHeader(new AuthorizationBearer($access_token))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Makes a call to the `oauth/token` endpoint
     *
     * @param array $options - keys:
     *                       - options.grantType
     *                       - options.client_id
     *                       - options.client_secret
     *                       [optional] Only if grant type: client_credentials
     *                       - options.username
     *                       [optional] Only if grant type: password/password-realm
     *                       - options.password
     *                       [optional] Only if grant type: password/password-realm
     *                       - options.scope     [optional]
     *                       - options.audience  [optional]
     *
     * @return mixed|string
     *
     * @throws ApiException
     */
    public function oauth_token($options = [])
    {
        if (! isset($options['client_id'])) {
            $options['client_id'] = $this->client_id;
        }

        if (! isset($options['client_secret'])) {
            $options['client_secret'] = $this->client_secret;
        }

        if (! isset($options['grant_type'])) {
            throw new ApiException('grant_type is mandatory');
        }

        return $this->apiClient->post()
        ->oauth()
        ->token()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($options))
        ->call();
    }

    /**
     * Makes a call to the `oauth/token` endpoint with `authorization_code` grant type
     *
     * @param string $code
     * @param string $redirect_uri
     *
     * @return mixed|string
     *
     * @throws ApiException
     */
    public function code_exchange($code, $redirect_uri)
    {
        $options = [];

        $options['client_secret'] = $this->client_secret;
        $options['redirect_uri']  = $redirect_uri;
        $options['code']          = $code;
        $options['grant_type']    = 'authorization_code';

        return $this->oauth_token($options);
    }

    /**
     * Makes a call to the `oauth/token` endpoint with `password-realm` grant type
     *
     * @param array $options - keys:
     *                       - options.username
     *                       - options.password
     *                       - options.realm
     *                       - options.scope [optional]
     *                       - options.audience [optional]
     *
     * @return mixed|string
     *
     * @throws ApiException
     */
    public function login($options)
    {
        if (! isset($options['username'])) {
            throw new ApiException('username is mandatory');
        }

        if (! isset($options['password'])) {
            throw new ApiException('password is mandatory');
        }

        if (! isset($options['realm'])) {
            throw new ApiException('realm is mandatory');
        }

        $options['grant_type'] = 'http://auth0.com/oauth/grant-type/password-realm';

        return $this->oauth_token($options);
    }

    /**
     * Makes a call to the `oauth/token` endpoint with `password` grant type
     *
     * @param array $options - keys:
     *                       - options.username
     *                       - options.password
     *                       - options.scope [optional]
     *                       - options.scope [audience]
     *
     * @return mixed|string
     *
     * @throws ApiException
     *
     * @see https://auth0.com/docs/api-auth/grant/password
     */
    public function login_with_default_directory($options)
    {
        if (! isset($options['username'])) {
            throw new ApiException('username is mandatory');
        }

        if (! isset($options['password'])) {
            throw new ApiException('password is mandatory');
        }

        $options['grant_type'] = 'password';

        return $this->oauth_token($options);
    }

    /**
     * Makes a call to the `oauth/token` endpoint with `client_credentials` grant type
     *
     * @param array $options - keys:
     *                       - options.client_id
     *                       - options.client_secret
     *                       - options.scope [optional]
     *                       - options.audience [optional]
     *
     * @return mixed|string
     *
     * @throws ApiException
     *
     * @see https://auth0.com/docs/api-auth/grant/client-credentials
     */
    public function client_credentials($options)
    {
        if (! isset($options['client_secret'])) {
            $options['client_secret'] = $this->client_secret;
        }

        if (empty($options['client_secret'])) {
            throw new ApiException('client_secret is mandatory');
        }

        if (! isset($options['client_id'])) {
            $options['client_id'] = $this->client_id;
        }

        if (empty($options['client_id'])) {
            throw new ApiException('client_id is mandatory');
        }

        if (! isset($options['scope'])) {
            $options['scope'] = $this->scope;
        }

        if (! isset($options['audience'])) {
            $options['audience'] = $this->audience;
        }

        $options['grant_type'] = 'client_credentials';

        return $this->oauth_token($options);
    }

    /**
     * Create a new user using active authentication.
     * This endpoint only works for database connections.
     *
     * @param string $email
     * @param string $password
     * @param string $connection
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#signup
     */
    public function dbconnections_signup($email, $password, $connection)
    {
        $data = [
            'client_id' => $this->client_id,
            'email' => $email,
            'password' => $password,
            'connection' => $connection,
        ];

        return $this->apiClient->post()
        ->dbconnections()
        ->signup()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }

    /**
     * Send a change password email.
     * This endpoint only works for database connections.
     *
     * @param string      $email
     * @param string      $connection
     * @param null|string $password
     *
     * @return mixed
     *
     * @see https://auth0.com/docs/api/authentication#change-password
     */
    public function dbconnections_change_password(
        $email,
        $connection,
        $password = null
    )
    {
        $data = [
            'client_id' => $this->client_id,
            'email' => $email,
            'connection' => $connection,
        ];

        if ($password !== null) {
            $data['password'] = $password;
        }

        return $this->apiClient->post()
        ->dbconnections()
        ->change_password()
        ->withHeader(new ContentType('application/json'))
        ->withBody(json_encode($data))
        ->call();
    }
}
