<?php

namespace Auth0\Tests\API\Management;

use Auth0\SDK\API\Management;
use Auth0\SDK\API\Management\EmailTemplates;
use Auth0\Tests\API\ApiTests;
use GuzzleHttp\Exception\ClientException;

/**
 * Class EmailTemplateTest
 *
 * @package Auth0\Tests\API\Management
 */
class EmailTemplateTest extends ApiTests
{
    /**
     * Email template to test
     *
     * @var string
     */
    const EMAIL_TEMPLATE_NAME = EmailTemplates::TEMPLATE_ENROLLMENT_EMAIL;

    /**
     * Management API token with scopes:
     *  - read:email_templates
     *  - create:email_templates
     *  - update:email_templates
     *  - read:email_provider
     *
     * @var string
     */
    protected static $token;

    /**
     * Valid tenant domain set in project .env file as `DOMAIN`
     *
     * @var string
     */
    protected static $domain;

    /**
     * Auth0 v2 Management API accessor
     *
     * @var Management
     */
    protected static $api;

    /**
     * Email template retrieved during class setup, tested later
     *
     * @var array
     */
    protected static $gotEmail = [];

    /**
     * If the email template was not found, this is the error code
     *
     * @var boolean
     */
    protected static $setUpEmailError;

    /**
     * Can this email template be created?
     *
     * @var boolean
     */
    protected static $mustCreate = false;

    /**
     * Test fixture for class
     *
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        $env = self::getEnvStatic();

        self::$domain = $env['DOMAIN'];
        self::$token  = self::getTokenStatic(
            $env, [
                'email_templates' => [ 'actions' => ['create', 'read', 'update'] ],
                'email_provider' => [ 'actions' => ['read'] ],
            ]
        );

        self::$api = new Management(self::$token, self::$domain);

        try {
            // Try to get the email template specified.
            self::$gotEmail = self::$api->emailTemplates->get(self::EMAIL_TEMPLATE_NAME);
        } catch (ClientException $e) {
            self::$setUpEmailError = $e->getCode();
            if (404 === self::$setUpEmailError) {
                // Could not find the email template so it can/must be created
                self::$mustCreate = true;
            }
        }
    }

    /**
     * Test fixture for each method
     */
    protected function assertPreConditions()
    {
        // Need to have an email provider setup for the tenant to perform this test.
        try {
            self::$api->emails->getEmailProvider();
        } catch (\Exception $e) {
            $this->markTestSkipped('Need to specify an email provider in the dashboard > Emails > Provider');
        }

        // If we don't have an email template and can't create, something sent wrong in self::setUpBeforeClass().
        if (! self::$mustCreate && empty(self::$gotEmail)) {
            $this->markTestSkipped(
                'Email template '.self::EMAIL_TEMPLATE_NAME.' not found with error '.self::$setUpEmailError
            );
        }
    }

    /**
     * Test if we got an email template, test create if we didn't
     *
     * @throws \Exception
     */
    public function testGotAnEmail()
    {
        if (self::$mustCreate) {
            $from_email     = 'test@'.self::$domain;
            self::$gotEmail = self::$api->emailTemplates->create(self::EMAIL_TEMPLATE_NAME, $from_email);
            $this->assertEquals($from_email, self::$gotEmail['from']);
        }

        $this->assertEquals(self::EMAIL_TEMPLATE_NAME, self::$gotEmail['template']);
    }

    /**
     * Test updating the email template
     *
     * @throws \Exception
     */
    public function testPatch()
    {
        $new_subject    = 'Email subject '.time();
        self::$gotEmail = self::$api->emailTemplates->patch(
            self::EMAIL_TEMPLATE_NAME, [
                'subject' => $new_subject,
            ]
        );

        $this->assertEquals($new_subject, self::$gotEmail['subject']);
    }
}
