<?php
namespace Auth0\SDK\API;

use Auth0\SDK\API\Management\Blacklists;
use Auth0\SDK\API\Management\Clients;
use Auth0\SDK\API\Management\ClientGrants;
use Auth0\SDK\API\Management\Connections;
use Auth0\SDK\API\Management\DeviceCredentials;
use Auth0\SDK\API\Management\Emails;
use Auth0\SDK\API\Management\EmailTemplates;
use Auth0\SDK\API\Management\Jobs;
use Auth0\SDK\API\Management\Logs;
use Auth0\SDK\API\Management\ResourceServers;
use Auth0\SDK\API\Management\Rules;
use Auth0\SDK\API\Management\Stats;
use Auth0\SDK\API\Management\Tenants;
use Auth0\SDK\API\Management\Tickets;
use Auth0\SDK\API\Management\UserBlocks;
use Auth0\SDK\API\Management\Users;
use Auth0\SDK\API\Management\UsersByEmail;

use Auth0\SDK\API\Helpers\ApiClient;
use Auth0\SDK\API\Header\Authorization\AuthorizationBearer;

class Management
{

    /**
     *
     * @var string
     */
    private $token;

    /**
     *
     * @var string
     */
    private $domain;

    /**
     *
     * @var ApiClient
     */
    private $apiClient;

    /**
     *
     * @var array
     */
    private $guzzleOptions;

    /**
     *
     * @var string
     */
    private $returnType;

    /**
     *
     * @var Blacklists
     */
    public $blacklists;

    /**
     *
     * @var Clients
     */
    public $clients;

    /**
     *
     * @var ClientGrants
     */
    public $client_grants;

    /**
     *
     * @var Connections
     */
    public $connections;

    /**
     *
     * @var DeviceCredentials
     */
    public $deviceCredentials;

    /**
     *
     * @var Emails
     */
    public $emails;

    /**
     *
     * @var EmailTemplates
     */
    public $emailTemplates;

    /**
     *
     * @var Jobs
     */
    public $jobs;

    /**
     *
     * @var Logs
     */
    public $logs;

    /**
     *
     * @var Rules
     */
    public $rules;

    /**
     *
     * @var ResourceServers
     */
    public $resource_servers;

    /**
     *
     * @var Stats
     */
    public $stats;

    /**
     *
     * @var Tenants
     */
    public $tenants;

    /**
     *
     * @var Tickets
     */
    public $tickets;

    /**
     *
     * @var UserBlocks
     */
    public $userBlocks;

    /**
     *
     * @var Users
     */
    public $users;

    /**
     *
     * @var UsersByEmail
     */
    public $usersByEmail;

    /**
     * Management constructor.
     *
     * @param string      $token
     * @param string      $domain
     * @param array       $guzzleOptions
     * @param string|null $returnType
     */
    public function __construct($token, $domain, $guzzleOptions = [], $returnType = null)
    {
        $this->token         = $token;
        $this->domain        = $domain;
        $this->guzzleOptions = $guzzleOptions;
        $this->returnType    = $returnType;

        $this->setApiClient();

        $this->blacklists        = new Blacklists($this->apiClient);
        $this->clients           = new Clients($this->apiClient);
        $this->client_grants     = new ClientGrants($this->apiClient);
        $this->connections       = new Connections($this->apiClient);
        $this->deviceCredentials = new DeviceCredentials($this->apiClient);
        $this->emails            = new Emails($this->apiClient);
        $this->emailTemplates    = new EmailTemplates($this->apiClient);
        $this->jobs              = new Jobs($this->apiClient);
        $this->logs              = new Logs($this->apiClient);
        $this->rules             = new Rules($this->apiClient);
        $this->resource_servers  = new ResourceServers($this->apiClient);
        $this->stats             = new Stats($this->apiClient);
        $this->tenants           = new Tenants($this->apiClient);
        $this->tickets           = new Tickets($this->apiClient);
        $this->userBlocks        = new UserBlocks($this->apiClient);
        $this->users             = new Users($this->apiClient);
        $this->usersByEmail      = new UsersByEmail($this->apiClient);
    }

    protected function setApiClient()
    {
        $apiDomain = "https://{$this->domain}";

        $client = new ApiClient([
            'domain' => $apiDomain,
            'basePath' => '/api/v2/',
            'guzzleOptions' => $this->guzzleOptions,
            'returnType' => $this->returnType,
            'headers' => [
                new AuthorizationBearer($this->token)
            ]
        ]);

        $this->apiClient = $client;
    }
}
