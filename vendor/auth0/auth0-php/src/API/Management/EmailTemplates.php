<?php
/**
 *
 * @package Auth0\SDK\API\Management
 */
namespace Auth0\SDK\API\Management;

use \Auth0\SDK\Exception\CoreException;

/**
 * Class EmailTemplates.
 * Handles requests to the Email Templates endpoint of the v2 Management API.
 *
 * @package Auth0\SDK\API\Management\EmailTemplates
 */
class EmailTemplates extends GenericResource
{
    /**
     *
     * @var string
     */
    const TEMPLATE_VERIFY_EMAIL = 'verify_email';

    /**
     *
     * @var string
     */
    const TEMPLATE_RESET_EMAIL = 'reset_email';

    /**
     *
     * @var string
     */
    const TEMPLATE_WELCOME_EMAIL = 'welcome_email';

    /**
     *
     * @var string
     */
    const TEMPLATE_BLOCKED_ACCOUNT = 'blocked_account';

    /**
     *
     * @var string
     */
    const TEMPLATE_STOLEN_CREDENTIALS = 'stolen_credentials';

    /**
     *
     * @var string
     */
    const TEMPLATE_ENROLLMENT_EMAIL = 'enrollment_email';

    /**
     *
     * @var string
     */
    const TEMPLATE_CHANGE_PASSWORD = 'change_password';

    /**
     *
     * @var string
     */
    const TEMPLATE_PASSWORD_RESET = 'password_reset';

    /**
     *
     * @var string
     */
    const TEMPLATE_MFA_OOB_CODE = 'mfa_oob_code';

    /**
     * Get an email template by name.
     * See docs @link below for valid names and fields.
     * Required scope: "read:email_templates"
     *
     * @param string $templateName - the email template name to get (see constants defined for this class).
     *
     * @return array
     *
     * @throws \Exception - if a 200 response was not returned from the API.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Email_Templates/get_email_templates_by_templateName
     */
    public function get($templateName)
    {
        return $this->apiClient->method('get')
            ->addPath('email-templates', $templateName)
            ->call();
    }

    /**
     * Patch an email template by name.
     * This will update only the email template data fields provided (see HTTP PATCH).
     * See docs @link below for valid names, fields, and possible responses.
     * Required scope: "update:email_templates"
     *
     * @param string $templateName - the email template name to patch (see constants defined for this class).
     * @param array  $data         - an array of data to update.
     *
     * @return array - updated data for the template name provided.
     *
     * @throws \Exception - if a 200 response was not returned from the API.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Email_Templates/patch_email_templates_by_templateName
     */
    public function patch($templateName, $data)
    {
        return $this->apiClient->method('patch')
            ->addPath('email-templates', $templateName)
            ->withBody(json_encode($data))
            ->call();
    }

    /**
     * Create an email template by name.
     * See docs @link below for valid names and fields.
     * Required scope: "create:email_templates"
     *
     * @param string  $template    - the template name to create (see constants defined for this class).
     * @param boolean $enabled     - is the email template enabled?
     * @param string  $from        - the email address the email should come from.
     * @param string  $subject     - the email subject.
     * @param string  $body        - the email body in the syntax indicated below.
     * @param string  $syntax      - the email body syntax to use.
     * @param string  $resultUrl   - URL where a click-through should land.
     * @param integer $urlLifetime - URL lifetime, in seconds.
     *
     * @return mixed|string
     *
     * @throws \Exception - if a 200 response was not returned from the API.
     *
     * @link https://auth0.com/docs/api/management/v2#!/Email_Templates/post_email_templates
     */
    public function create(
        $template,
        $enabled,
        $from,
        $subject,
        $body,
        $syntax = 'liquid',
        $resultUrl = '',
        $urlLifetime = 0
    )
    {
        // Required fields
        $data = [
            'template' => (string) $template,
            'enabled' => (bool) $enabled,
            'from' => (string) $from,
            'subject' => (string) $subject,
            'body' => (string) $body,
            'syntax' => (string) $syntax,
            'urlLifetimeInSeconds' => abs((int) $urlLifetime)
        ];

        if (! empty($resultUrl)) {
            $data['resultUrl'] = filter_var($resultUrl, FILTER_SANITIZE_URL);
        }

        return $this->apiClient->method('post')
            ->addPath('email-templates')
            ->withBody(json_encode($data))
            ->call();
    }
}
