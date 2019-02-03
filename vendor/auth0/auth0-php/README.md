# Auth0 PHP SDK

[![Latest Stable Version](https://poser.pugx.org/auth0/auth0-php/v/stable)](https://packagist.org/packages/auth0/auth0-php)
[![Build Status](https://travis-ci.org/auth0/auth0-PHP.png)](https://travis-ci.org/auth0/auth0-PHP)
[![Code Climate](https://codeclimate.com/github/auth0/auth0-PHP/badges/gpa.svg)](https://codeclimate.com/github/auth0/auth0-PHP)
[![License](https://poser.pugx.org/auth0/auth0-php/license)](https://packagist.org/packages/auth0/auth0-php)
[![Total Downloads](https://poser.pugx.org/auth0/auth0-php/downloads)](https://packagist.org/packages/auth0/auth0-php)

The Auth0 PHP SDK provides straight-forward and tested methods for accessing Authentication and Management API endpoints. This README describes how to get started and provides simple examples of how to use the SDK.

For more details about how to install this SDK into an existing project or how to download a preconfigured seed project, see:

* [Basic PHP application quickstart](https://auth0.com/docs/quickstart/webapp/php/)
* [PHP API quickstart](https://auth0.com/docs/quickstart/backend/php/)

## Upgrading Through 5.1.0?

Please see the notes in the [changelog](CHANGELOG.md#510-2018-03-02) regarding state validation.

## Installation

We recommend installing the SDK with [Composer](https://getcomposer.org/doc/00-intro.md). If you already have Composer installed globally, run the following:

```
$ composer require auth0/auth0-php
```

Otherwise, [download Composer locally](https://getcomposer.org/doc/00-intro.md#locally) and run:

```
php composer.phar require auth0/auth0-php
``` 

This will create `composer.json` and `composer.lock` files in the directory where the command was run, along with a vendor folder containing this SDK and its dependencies. 

Finally, include the Composer autoload file in your project to use the SDK:

```php
require __DIR__ . '/vendor/autoload.php';

use Auth0\SDK\Auth0;
```

The examples below use [PHP Dotenv](https://github.com/josegonzalez/php-dotenv) to store and load sensitive Auth0 credentials from the environment rather than hard-coding them into your application. PHP Dotenv is a dependency of this SDK, so if you followed the steps above to install via Composer, the class is available for you to use in your project. 

First, you'll need a free Auth0 account and an Application:

1. Go to [auth0.com/signup](https://auth0.com/signup) and create your account.
2. Once you are in the dashboard, go to **Applications**, then **Create Application**.
3. Give your Application a name, select **Regular Web Application**, then **Create**
4. Click the **Settings** tab for the required credentials used below.

Next, create a `.env` file and add the following values:

```
# Auth0 tenant domain, found in your Application settings
AUTH0_DOMAIN="tenant.auth0.com"

# Auth0 Client ID, found in your Application settings
AUTH0_CLIENT_ID="Client ID goes here"

# Auth0 Client Secret, found in your Application settings
AUTH0_CLIENT_SECRET="Client Secret goes here"

# URL to handle the authentication callback
# Save this URL in the "Allowed Callback URLs" field in the Auth0 dashboard
AUTH0_CALLBACK_URL="Callback URL goes here"

# Audience for profile data endpoint calls after authentication
AUTH0_AUTH_AUDIENCE="https://${AUTH0_DOMAIN}/userinfo/"

# Audience for Management API calls (not required for authentication calls)
AUTH0_MANAGEMENT_AUDIENCE="https://${AUTH0_DOMAIN}/api/v2/"

# API token for accessing the Management API (not required for authentication calls)
AUTH0_MANAGEMENT_API_TOKEN="API token goes here"
```

In your application below the Composer autoload `require`, add:

```php
// ... other use declarations
use josegonzalez\Dotenv\Loader;

// Setup environment vars
$Dotenv = new Loader(__DIR__ . '/.env');
$Dotenv->parse()->putenv(true);

// Get environment variables
echo 'My Auth0 domain is ' . getenv('AUTH0_DOMAIN') . '!';
```

## Usage - Authentication API

This SDK provides easy-to-implement methods to access the [Authentication API](https://auth0.com/docs/api/authentication). Some common authentication operations are explained below with examples. For additional information and capabilities, please see the methods in the `\Auth0\SDK\API\Authentication` class. Avoid using any methods marked `@deprecated` as they will be removed in the next major version and may not be enabled for your account.

The examples below assume that you followed the steps in the [Installation section](#installation) above and are using a `.env` file and loader to store credentials.

### Login

The easiest and most secure way to handle logins to a web application is to use the [Authentication Code grant](https://auth0.com/docs/api-auth/tutorials/authorization-code-grant) combined with Auth0's Universal Login page. In short, that process is:

1. A user requesting access is redirected to the Universal Login Page.
2. The user authenticates using one of many possible connections: social (Twitter or Facebook); database (email and password); passwordless (email or a mobile device).
3. The user is redirected back to your application's callback URL with a `code` and `state` parameter if successful or an `error` and `error_description` if not.
4. If the authentication was successful, the `state` parameter is validated.
5. If the `state` is valid, the `code` parameter is exchanged with Auth0 for an access token.
6. If the exchange is successful, the access token is used to call an Auth0 `/userinfo` endpoint, which returns the authenticated user's information.
7. This information can be used to create an account, to start an application-specific session, or to persist as the user session.

The PHP SDK handles most of the previous steps. Your application needs to:

1. Determine a login action (for example: click a link, visit walled content, etc.) and call  `Auth0::login()`
2. Handle returned errors.

A simple implementation of these steps looks like this:

```php
// Example #1
// login.php
use Auth0\SDK\Auth0;

// Initialize the Auth0 class with required credentials.
$auth0 = new Auth0([

    // See Installation above to setup environment variables.
    'domain' => getenv('AUTH0_DOMAIN'),
    'client_id' => getenv('AUTH0_CLIENT_ID'),
    'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
    'audience' => getenv('AUTH0_AUTH_AUDIENCE'),

    // This would be the URL for this file in this example.
    'redirect_uri' => getenv('AUTH0_LOGIN_CALLBACK_URL'),

    // The scope determines what data is provided by the /userinfo endpoint.
    // There must be at least one valid scope included here for anything to be returned from /userinfo.
    'scope' => 'openid',
]);

if (! empty($_GET['error']) || ! empty($_GET['error_description'])) {
    // Handle errors sent back by Auth0.
}

// If there is a user persisted (PHP session by default), return that.
// Otherwise, look for a "code" and "state" URL parameter to validate and exchange, respectively.
// If the state validation and code exchange are successful, return the userinfo.
$userinfo = $auth0->getUser();

// We have no persisted user and no "code" parameter so we redirect to the Universal Login Page.
if (empty($userinfo)) {
    $auth0->login();
}

// We either have a persisted user or a successful code exchange.
var_dump($userinfo);

// This is where a user record in a local database could be retrieved or created.
// Redirect somewhere to remove "code" and "state" parameters to avoid a fatal error on refresh.

```

Loading the script above in your browser should:

1. Immediately redirect you to an Auth0 login page for your tenant.
2. After successfully logging in using any connection, redirect you back to your app.
3. Display the returned user information:

```php
array(1) { ["sub"]=> string(30) "auth0|4b12v471de68e34446mq7c2v" }
```

### Profile

Once a user has authenticated, we can use their persisted data to determine whether they are allowed to access sensitive site pages, like a user profile. 

Using the example above, we'll add additional [scope](https://auth0.com/docs/api-auth/tutorials/adoption/scope-custom-claims) to make the profile a little more interesting:

```php
// login.php

// ...
	'scope' => 'openid email name nickname picture updated_at profile',
// ...
```

Once someone has logged in requesting the new user claims, let's redirect to a profile page:


```php
// login.php

//...
// var_dump($userinfo);
header('Location: /profile.php');

```

This profile page will return all the data we retrieved from the `/userinfo` endpoint and stored in our session. The data displayed here is controlled by the `scope` parameter we passed to the `Auth0` class. More information on the claims we can pass to `scope` is [here](https://auth0.com/docs/api-auth/tutorials/adoption/scope-custom-claims).


```php
// Example #2
// profile.php
use Auth0\SDK\Store\SessionStore;

// Get our persistent storage interface to get the stored userinfo.
$store = new SessionStore();
$userinfo = $store->get('user');

if ($userinfo) {
    // The $userinfo keys below will not exist if the user does not have that data.
    printf(
        '<h1>Hi %s!</h1>
        <p><img width="100" src="%s"></p>
        <p><strong>Last update:</strong> %s</p>
        <p><strong>Contact:</strong> %s %s</p>',
        isset($userinfo['nickname']) ? strip_tags($userinfo['nickname']) : '[unknown]',
        isset($userinfo['picture'])
            ? filter_var($userinfo['picture'], FILTER_SANITIZE_URL)
            : 'https://www.gravatar.com/avatar/?d=retro',
        isset($userinfo['updated_at']) ? date('j/m/Y', strtotime($userinfo['updated_at'])) : '[unknown]',
        isset($userinfo['email'])
            ? filter_var($userinfo['email'], FILTER_SANITIZE_EMAIL)
            : '[unknown]',
        !empty($userinfo[ 'email_verified' ]) ? '✓' : '✗'
    );
} else {
    echo '<p>Please login to view your profile.</p>';
}
```

### Logout

In addition to logging in, we also want users to be able to log out. When users log out, they must invalidate their session for the application. For this SDK, that means destroying their persistent user and token data:

```php
// Example #2
// logout.php
use Auth0\SDK\Auth0;
use Auth0\SDK\API\Authentication;

$auth0 = new Auth0([
    'domain' => getenv('AUTH0_DOMAIN'),
    'client_id' => getenv('AUTH0_CLIENT_ID'),
    'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
    'redirect_uri' => getenv('AUTH0_LOGIN_BASIC_CALLBACK_URL'),
]);

// Log out of the local application.
$auth0->logout();

```

If you're using SSO and want this to also end their session at Auth0, redirect to the Auth0 logout URL after logging out locally:

```php
// Setup the Authentication class with required credentials.
// No API calls are made on instantiation.
$auth0_api = new Authentication(getenv('AUTH0_DOMAIN'));

// Get the Auth0 logout URL to end the Auth0 session as well.
$auth0_logout = $auth0_api->get_logout_link(

    // This needs to be saved in the "Allowed Logout URLs" field in your Application settings.
    getenv('AUTH0_LOGOUT_RETURN_URL'),
    // Indicate the specific Application.
    getenv('AUTH0_CLIENT_ID')
);

header('Location: ' . $auth0_logout);
exit;
```

More information about the logout process can be found [on our Docs site](https://auth0.com/docs/logout).

### Client Credentials Grant

A [Client Credentials grant](https://auth0.com/docs/api-auth/tutorials/client-credentials) gives an application access to an API as long as the application is:

- allowed to perform a Client Credentials grant (advanced settings on the Application settings page)
- authorized for the API providing the grant (Applications tab for the API in question)

Successful authentication for this grant will result in an access token being issued for the API requested. 

An example of requesting an access token for the Management API is below:

```php
// Example #5
use \Auth0\SDK\API\Authentication;
use \Auth0\SDK\Exception\ApiException;
use \GuzzleHttp\Exception\ClientException;

$auth0_api = new Authentication(getenv('AUTH0_DOMAIN'));

$config = [
    // Required for a Client Credentials grant.
    // Application must allow this grant type and be authorized for the API requested
    'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
    'client_id' => getenv('AUTH0_CLIENT_ID'),

    // Also required, found in the API settings page.
    'audience' => getenv('AUTH0_MANAGEMENT_AUDIENCE'),
];

try {
    $result = $auth0_api->client_credentials($config);
    echo '<pre>' . print_r($result, true) . '</pre>';
    die();
} catch (ClientException $e) {
    echo 'Caught: ClientException - ' . $e->getMessage();
} catch (ApiException $e) {
    echo 'Caught: ApiException - ' . $e->getMessage();
}
```

If the grant was successful, you should see the following:

```
Array
(
    [access_token] => eyJ0eXAi...eyJpc3Mi...QoB2c24w
    [scope] => read:users read:clients
    [expires_in] => 86400
    [token_type] => Bearer
)
```

See the [Usage - Management API](#usage-management-api) section below for more information on how to use this access token. 

## Usage - Decoding and Verifying JWTs

This SDK also includes an interface to the [Firebase PHP JWT library](https://github.com/firebase/php-jwt), used to decode and verify JSON web tokens (JWT). The `JWTVerifier` class has a single method, `verifyAndDecode()`, which accepts a JWT and either returns a decoded token or throws an error. More information on JWTs and how to build and decode them can be found [here on jwt.io](https://jwt.io/).

The decoder can work with both HS256 and RS256 tokens. Both types require the algorithm and valid audiences to be indicated before processing. Additionally, HS256 tokens require the client secret while RS256 tokens require an authorized issuer. The issuer is used to fetch a JWKs file during the decoding process as well. ([More about signing algorithms here](https://auth0.com/blog/navigating-rs256-and-jwks/).)

Here is an example of a small, URL-based JWT decoder:


```php
// Example #4
// decode-jwt.php
use Auth0\SDK\JWTVerifier;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Exception\CoreException;

// Do we have an ID token?
if (empty($_GET[ 'id_token' ])) {
    echo '<code>No "id_token" URL parameter!</code> ';
    exit;
}

// Do we have a valid algorithm?
if (empty($_GET[ 'token_alg' ]) || ! in_array($_GET[ 'token_alg' ], [ 'HS256', 'RS256' ])) {
    echo '<code>Missing or invalid "token_alg" URL parameter!</code> ';
    exit;
}

$config = [
    'supported_algs' => [ $_GET[ 'token_alg' ] ],
    'client_secret' => getenv('AUTH0_CLIENT_SECRET'),
];

if ('HS256' === $_GET[ 'token_alg' ]) {
    $config['client_secret'] = getenv('AUTH0_CLIENT_SECRET');
} else {
    $config['authorized_iss'] = [ 'https://' . getenv('AUTH0_DOMAIN') . '/' ];
}

try {
    $verifier = new JWTVerifier($config);
    $decoded_token = $verifier->verifyAndDecode($_GET[ 'id_token' ]);
    echo '<pre>' . print_r($decoded_token, true) . '</pre>';
} catch (InvalidTokenException $e) {
    echo 'Caught: InvalidTokenException - ' . $e->getMessage();
} catch (CoreException $e) {
    echo 'Caught: CoreException - ' . $e->getMessage();
} catch (\Exception $e) {
    echo 'Caught: Exception - ' . $e->getMessage();
}
```

Additional parameters for the `JWTVerifier` configuration array are:

- **cache**: Receives an instance of `Auth0\SDK\Helpers\Cache\CacheHandler` (Supported `FileSystemCacheHandler` and `NoCacheHandler`). Defaults to `NoCacheHandler` (RS256 only).
- **guzzle_options**: Configuration propagated to Guzzle when fetching the JWKs (RS256 only). These options are [documented here](http://docs.guzzlephp.org/en/stable/request-options.html).
- **secret\_base64\_encoded**: When `true`, it will decode the secret used to verify the token signature. This is only used for HS256 tokens and defaults to `true`. Your Application settings will say whether the Client Secret provided is encoded or not. 

## Usage - Management API

This SDK also provides a wrapper for the Management API, which is used to perform operations on your Auth0 tenant. Using this API, you can:

- Search for and create users
- Create and update Applications
- Retrieve log entries
- Manage rules 

... and much more. See our [documentation](https://auth0.com/docs/api/management/v2) for information on what's possible and the examples below for how to authenticate and access this API. 

### Authentication

In order to use the Management API, you must authenticate one of two ways:

- For temporary access or testing, you can [manually generate an API token](https://auth0.com/docs/api/management/v2/tokens#get-a-token-manually) and save it in your `.env` file
- For extended access, you can create and execute and Client Credentials grant ([detailed above](#client-credentials-grant)) when access is required

Regardless of the method, the token generated must have the scopes required for the operations your app wants to execute. Consult the [API documentation](https://auth0.com/docs/api/management/v2) for the scopes required for the specific endpoint you're trying to access.

To grant the scopes needed: 

1. Go to [APIs](https://manage.auth0.com/#/apis) > Auth0 Management API > **Machine to Machine Applications** tab.
2. Find your Application and authorize it.
3. Click the arrow to expand the row and select the scopes required.

Now you can authenticate one of the two ways above and use that token to perform operations:

```php
use Auth0\SDK\API\Management;

$access_token = getenv('AUTH0_MANAGEMENT_API_TOKEN');
if ( empty( $access_token ) ) {
	// See "Client Credentials Grant" above
	$access_token = get_access_token();
}
$mgmt_api = new Management( $access_token, getenv('AUTH0_DOMAIN') );
```

The `Management` class stores access to endpoints as properties of its instances. The best way to see what endpoints are covered is to read through the `\Auth0\SDK\API\Management::__construct()` method. 

### Example - Search Users by Email

This endpoint is documented [here](https://auth0.com/docs/api/management/v2#!/Users/get_users).

```php
$results = $mgmt_api->users->search([
    'q' => 'josh'
]);

if (! empty($results)) {
    echo '<h2>User Search</h2>';
    foreach ($results as $datum) {
        printf(
            '<p><strong>%s</strong> &lt;%s&gt; - %s</p>',
            !empty($datum['nickname']) ? $datum['nickname'] : 'No nickname',
            !empty($datum['email']) ? $datum['email'] : 'No email',
            $datum['user_id']
        );
    }
}
```

### Example - Get All Clients

This endpoint is documented [here](https://auth0.com/docs/api/management/v2#!/Clients/get_clients).

```php
$results = $mgmt_api->clients->getAll();

if (! empty($results)) {
    echo '<h2>Get All Clients</h2>';
    foreach ($results as $datum) {
        printf(
            '<p><strong>%s</strong> - %s</p>',
            $datum['name'],
            $datum['client_id']
        );
    }
}
```

## Contributing

We provide and maintain SDKs for the benefit of our developer community. Feedback, detailed bug reports, and focused PRs are appreciated. Thank you in advance!

When contributing to this SDK, please:

- Maintain the minimum PHP version (found under `require.php` in `composer.json`).
- Code to the [PSR-2 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md).
- Write tests and run them with `composer test`.
- Keep PRs focused and change the minimum number of lines to achieve your goal.

To run tests on the SDK, you'll need to create a `.env` file in the root of this package with the following entries:

- `DOMAIN` - Auth0 domain for your test tenant
- `APP_CLIENT_ID` - Client ID for a test Regular Web Application
- `APP_CLIENT_SECRET` - Client Secret for a test Regular Web Application
- `NIC_ID` - Client ID for a test Non-Interactive Client Application
- `NIC_SECRET` - Client Secret for a test Non-Interactive Client Application
- `GLOBAL_CLIENT_ID` - Client ID for your tenant (found in Tenant > Settings > Advanced)
- `GLOBAL_CLIENT_SECRET` - Client Secret for your tenant (found in Tenant > Settings > Advanced)

This file is automatically excluded from Git with the `.gitignore` for this repo. 

We're working on test coverage and quality but please note that newer tenants might see errors (typically `404`) for endpoints that are no longer available. Another common error is a `429` for too many requests. 

## Troubleshooting

> I am getting `curl error 60: SSL certificate problem: self-signed certificate in certificate chain` on Windows

This is a common issue with latest PHP versions under **Windows OS** (related to an incompatibility between windows and openssl CAs database).

1. Download this CA database `https://curl.haxx.se/ca/cacert.pem` to `c:/cacert.pem`.
2. Edit your php.ini and add `openssl.cafile=c:/cacert.pem`. (It should point to the file you downloaded.)

> My host does not allow using Composer

This SDK uses Composer for maintaining dependencies (required external PHP libraries). If Composer is not allowed or installed on your host, install Composer locally, follow the installation instructions there, then upload your entire application, vendor folder included, to your host.

## What is Auth0?

Auth0 helps you to:

* Add authentication with [multiple authentication sources](https://auth0.com/docs/identityproviders), either social like **Google, Facebook, Microsoft Account, LinkedIn, GitHub, Twitter, Box, Salesforce, among others**, or enterprise identity systems like **Windows Azure AD, Google Apps, Active Directory, ADFS or any SAML Identity Provider**.
* Add authentication through more traditional [username/password databases](https://auth0.com/docs/connections/database/custom-db).
* Add support for [linking different user accounts](https://auth0.com/docs/link-accounts) with the same user.
* Support for generating signed [JSON Web Tokens](https://auth0.com/docs/jwt) to call your APIs and **flow the user identity** securely.
* Analytics of how, when, and where users are logging in.
* Pull data from other sources and add it to the user profile, through [JavaScript rules](https://auth0.com/docs/rules/current).

## Issue Reporting

If you have found a bug or if you have a feature request, please report them at this repository issues section. Please do not report security vulnerabilities on the public GitHub issue tracker. The [Responsible Disclosure Program](https://auth0.com/whitehat) details the procedure for disclosing security issues.

## Author

[Auth0](https://auth0.com)

## License

This project is licensed under the MIT license. See the [LICENSE](LICENSE.txt) file for more info.
