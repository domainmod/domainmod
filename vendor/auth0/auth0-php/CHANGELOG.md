# Change Log

## [5.3.2](https://github.com/auth0/auth0-PHP/tree/5.3.2) (2018-11-2)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.3.1...5.3.2)

**Closed issues**
- Something is wrong with the latest release 5.3.1 [\#303](https://github.com/auth0/auth0-PHP/issues/303)

**Fixed**
- Fix info headers Extend error in dependant libs [\#304](https://github.com/auth0/auth0-PHP/pull/304) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.3.1](https://github.com/auth0/auth0-PHP/tree/5.3.1) (2018-10-31)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.3.0...5.3.1)

**Closed issues**
- Array to String exception when audience is an array [\#296](https://github.com/auth0/auth0-PHP/issues/296)
- Passing accessToken from frontend to PHP API [\#281](https://github.com/auth0/auth0-PHP/issues/281)
- Deprecated method email_code_passwordless_verify [\#280](https://github.com/auth0/auth0-PHP/issues/280)

**Added**
- Fix documentation for Auth0 constructor options [\#298](https://github.com/auth0/auth0-PHP/pull/298) ([biganfa](https://github.com/biganfa))

**Changed**
- Change telemetry headers to new format and add tests [\#300](https://github.com/auth0/auth0-PHP/pull/300) ([joshcanhelp](https://github.com/joshcanhelp))

**Fixed**
- Fix bad exception message generation [\#297](https://github.com/auth0/auth0-PHP/pull/297) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.3.0](https://github.com/auth0/auth0-PHP/tree/5.3.0) (2018-10-09)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.2.0...5.3.0)

**Closed issues**
- Question: Handling rate limits [\#277](https://github.com/auth0/auth0-PHP/issues/277)
- Allow configuration of the JWKS URL [\#276](https://github.com/auth0/auth0-PHP/issues/276)
- Allow changing the session key name [\#273](https://github.com/auth0/auth0-PHP/issues/273)
- SessionStore overrides PHP session cookie lifetime setting [\#215](https://github.com/auth0/auth0-PHP/issues/215)

**Added**
- Add custom JWKS path and kid check to JWKFetcher + tests [\#287](https://github.com/auth0/auth0-PHP/pull/287) ([joshcanhelp](https://github.com/joshcanhelp))
- Add config keys for session base name and cookie expires [\#279](https://github.com/auth0/auth0-PHP/pull/279) ([joshcanhelp](https://github.com/joshcanhelp))
- Add return request object [\#278](https://github.com/auth0/auth0-PHP/pull/278) ([joshcanhelp](https://github.com/joshcanhelp))
- Add pagination and tests to Resource Servers [\#275](https://github.com/auth0/auth0-PHP/pull/275) ([joshcanhelp](https://github.com/joshcanhelp))
- Fix formatting, code standards scan [\#274](https://github.com/auth0/auth0-PHP/pull/274) ([joshcanhelp](https://github.com/joshcanhelp))
- Add pagination, docs, and better tests for Rules [\#272](https://github.com/auth0/auth0-PHP/pull/272) ([joshcanhelp](https://github.com/joshcanhelp))
- Adding pagination, tests, + docs to Client Grants; minor test suite refactor [\#271](https://github.com/auth0/auth0-PHP/pull/271) ([joshcanhelp](https://github.com/joshcanhelp))
- Add tests, docblocks for Logs endpoints [\#270](https://github.com/auth0/auth0-PHP/pull/270) ([joshcanhelp](https://github.com/joshcanhelp))
- Add PHP_CodeSniffer + ruleset config [\#267](https://github.com/auth0/auth0-PHP/pull/267) ([joshcanhelp](https://github.com/joshcanhelp))
- Add session state and dummy state handler tests [\#266](https://github.com/auth0/auth0-PHP/pull/266) ([joshcanhelp](https://github.com/joshcanhelp))

**Changed**
- Build/PHPCS: update/improve the PHPCS configuration [\#284](https://github.com/auth0/auth0-PHP/pull/284) ([jrfnl](https://github.com/jrfnl))

**Deprecated**
- Deprecate Auth0\SDK\API\Oauth2Client class [\#269](https://github.com/auth0/auth0-PHP/pull/269) ([joshcanhelp](https://github.com/joshcanhelp))

**Removed**
- Remove examples, add links to Quickstarts [\#293](https://github.com/auth0/auth0-PHP/pull/293) ([joshcanhelp](https://github.com/joshcanhelp))

**Fixed**
- Whitespace pass with new standards using composer phpcbf [\#268](https://github.com/auth0/auth0-PHP/pull/268) ([joshcanhelp](https://github.com/joshcanhelp))

**Security**
- Add ID token validation [\#285](https://github.com/auth0/auth0-PHP/pull/285) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.2.0](https://github.com/auth0/auth0-PHP/tree/5.2.0) (2018-06-13)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.1.1...5.2.0)

**Closed issues**
- getAppMetadata - how to use? [\#248](https://github.com/auth0/auth0-PHP/issues/248)
- Auth0 class missing action to renew access token [\#234](https://github.com/auth0/auth0-PHP/issues/234)
- DOC maj [\#217](https://github.com/auth0/auth0-PHP/issues/217)

**Added**
- User pagination and fields, docblocks, formatting, test improvements [\#261](https://github.com/auth0/auth0-PHP/pull/261) ([joshcanhelp](https://github.com/joshcanhelp))
- Unit test for withDictParams method [\#260](https://github.com/auth0/auth0-PHP/pull/260) ([joshcanhelp](https://github.com/joshcanhelp))
- Pagination, additional parameters, and tests for the Connections endpoint [\#258](https://github.com/auth0/auth0-PHP/pull/258) ([joshcanhelp](https://github.com/joshcanhelp))
- Renew tokens method for Auth0 client class [\#257](https://github.com/auth0/auth0-PHP/pull/257) ([jspetrak](https://github.com/jspetrak))
- Clients endpoint pagination and improvements [\#256](https://github.com/auth0/auth0-PHP/pull/256) ([joshcanhelp](https://github.com/joshcanhelp))
- Add email template endpoints [\#251](https://github.com/auth0/auth0-PHP/pull/251) ([joshcanhelp](https://github.com/joshcanhelp))

**Changed**
- Code style scan and fixes [\#250](https://github.com/auth0/auth0-PHP/pull/250) ([joshcanhelp](https://github.com/joshcanhelp))

**Fixed**
- Fix PHPUnit test. [\#262](https://github.com/auth0/auth0-PHP/pull/262) ([maurobonfietti](https://github.com/maurobonfietti))
- Allow $page to be null for Clients so pagination is not triggered [\#259](https://github.com/auth0/auth0-PHP/pull/259) ([joshcanhelp](https://github.com/joshcanhelp))
- Rewrite README; add news and notes to CHANGELOG [\#253](https://github.com/auth0/auth0-PHP/pull/253) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.1.1](https://github.com/auth0/auth0-PHP/tree/5.1.1) (2018-04-03)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.1.0...5.1.1)

**Closed issues**
- State Handler with Custom Session Store [\#233](https://github.com/auth0/auth0-PHP/issues/233)
- Implement ResourceServices::getAll [\#200](https://github.com/auth0/auth0-PHP/issues/200)

**Added**
- Implement ResourceServices::getAll() [\#236](https://github.com/auth0/auth0-PHP/pull/236) ([joshcanhelp](https://github.com/joshcanhelp))

**Fixed**
- Incorrect type hint on SessionStateHandler __construct [\#235](https://github.com/auth0/auth0-PHP/pull/235) ([joshcanhelp](https://github.com/joshcanhelp))
- Auth0 class documentation fixed for store and state handler [\#232](https://github.com/auth0/auth0-PHP/pull/232) ([jspetrak](https://github.com/jspetrak))
- Fixing minor code quality issues [\#231](https://github.com/auth0/auth0-PHP/pull/231) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.1.0](https://github.com/auth0/auth0-PHP/tree/5.1.0) (2018-03-02)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.0.6...5.1.0)

[State validation](https://auth0.com/docs/protocols/oauth2/oauth-state) was added in 5.1.0 for improved security. By default, this uses session storage and will happen automatically if you are using a combination of `Auth0::login()` and any method which calls `Auth0::exchange()` in your callback.

If you need to use a different storage method, implement your own [StateHandler](https://github.com/auth0/auth0-PHP/blob/master/src/API/Helpers/State/StateHandler.php) and set it using the `state_handler` config key when you initialize an `Auth0` instance.

If you are using `Auth0::exchange()` and a method other than `Auth0::login()` to generate the Authorize URL, you can disable automatic state validation by setting the `state_handler` key to `false` when you initialize the `Auth0` instance. It is **highly recommended** to implement state validation, either automatically or otherwise

**Closed issues**
- Support for php-jwt 5 [\#210](https://github.com/auth0/auth0-PHP/issues/210)

**Added**
- Adding tests for state handler; correcting storage method used [\#228](https://github.com/auth0/auth0-PHP/pull/228) ([joshcanhelp](https://github.com/joshcanhelp))

**Changed**
- Bumping JWT package version [\#229](https://github.com/auth0/auth0-PHP/pull/229) ([joshcanhelp](https://github.com/joshcanhelp))

## [5.0.6](https://github.com/auth0/auth0-PHP/tree/5.0.4) (2017-11-24)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.0.4...5.0.6)

**Added**
- Add support for the new users by email API [\#213](https://github.com/auth0/auth0-PHP/pull/213) ([erichard](https://github.com/erichard))

**Fixed**
- Fixes build [\#211](https://github.com/auth0/auth0-PHP/pull/211) ([aknosis](https://github.com/aknosis))

## [5.0.4](https://github.com/auth0/auth0-PHP/tree/5.0.4) (2017-06-26)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/5.0.0...5.0.4)

**Added**
- Added setter for debugger [\#149](https://github.com/auth0/auth0-PHP/pull/149) ([AxaliaN](https://github.com/AxaliaN))

**Changed**
- Restructured tests and fixed hhvm build [\#164](https://github.com/auth0/auth0-PHP/pull/164) ([Nyholm](https://github.com/Nyholm))
- Update .env.example with more appropriate values [\#148](https://github.com/auth0/auth0-PHP/pull/148) ([AmaanC](https://github.com/AmaanC))

**Removed**
- Remove non-essential dev package [\#157](https://github.com/auth0/auth0-PHP/pull/157) ([Nyholm](https://github.com/Nyholm))

## [3.4.0](https://github.com/auth0/auth0-PHP/tree/3.4.0) (2016-06-21)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.7...3.4.0)

**Closed issues:**

- More descriptive error message when code exchange fails [\#86](https://github.com/auth0/auth0-PHP/issues/86)

**Merged pull requests:**

- Correctly build logout url query string [\#87](https://github.com/auth0/auth0-PHP/pull/87) ([robinvdvleuten](https://github.com/robinvdvleuten))

## [3.3.7](https://github.com/auth0/auth0-PHP/tree/3.3.7) (2016-06-09)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.6...3.3.7)

## [3.3.6](https://github.com/auth0/auth0-PHP/tree/3.3.6) (2016-06-09)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.5...3.3.6)

**Merged pull requests:**

- $this-\>access\_token is an array, not object [\#85](https://github.com/auth0/auth0-PHP/pull/85) ([dev101](https://github.com/dev101))

## [3.3.5](https://github.com/auth0/auth0-PHP/tree/3.3.5) (2016-05-24)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.4...3.3.5)

**Closed issues:**

- Create password change ticket fails [\#84](https://github.com/auth0/auth0-PHP/issues/84)
- UnexpectedValueException is used in Auth0JWT.php but is not defined [\#80](https://github.com/auth0/auth0-PHP/issues/80)
- Add support for auth api endpoints \(/ro\) [\#22](https://github.com/auth0/auth0-PHP/issues/22)

## [3.3.4](https://github.com/auth0/auth0-PHP/tree/3.3.4) (2016-05-24)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.3...3.3.4)

## [3.3.3](https://github.com/auth0/auth0-PHP/tree/3.3.3) (2016-05-24)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.2.3...3.3.3)

## [2.2.3](https://github.com/auth0/auth0-PHP/tree/2.2.3) (2016-05-10)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.2...2.2.3)

## [3.3.2](https://github.com/auth0/auth0-PHP/tree/3.3.2) (2016-05-10)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.1...3.3.2)

## [3.3.1](https://github.com/auth0/auth0-PHP/tree/3.3.1) (2016-05-10)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.2.2...3.3.1)

## [2.2.2](https://github.com/auth0/auth0-PHP/tree/2.2.2) (2016-05-10)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.3.0...2.2.2)

## [3.3.0](https://github.com/auth0/auth0-PHP/tree/3.3.0) (2016-05-09)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.2.1...3.3.0)

**Merged pull requests:**

- deleted uneccessary code, fixed typos [\#83](https://github.com/auth0/auth0-PHP/pull/83) ([Amialc](https://github.com/Amialc))
- Add Docker support [\#82](https://github.com/auth0/auth0-PHP/pull/82) ([smtx](https://github.com/smtx))
- changed UnexpectedValueException to CoreException [\#81](https://github.com/auth0/auth0-PHP/pull/81) ([dryror](https://github.com/dryror))
- Added auth api support [\#78](https://github.com/auth0/auth0-PHP/pull/78) ([glena](https://github.com/glena))

## [3.2.1](https://github.com/auth0/auth0-PHP/tree/3.2.1) (2016-05-02)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.2.1...3.2.1)

## [2.2.1](https://github.com/auth0/auth0-PHP/tree/2.2.1) (2016-04-27)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.2.0...2.2.1)

**Closed issues:**

- outdated dependency in api example [\#75](https://github.com/auth0/auth0-PHP/issues/75)

**Merged pull requests:**

- dependencies update in basic api example [\#79](https://github.com/auth0/auth0-PHP/pull/79) ([Amialc](https://github.com/Amialc))

## [3.2.0](https://github.com/auth0/auth0-PHP/tree/3.2.0) (2016-04-15)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.2.0...3.2.0)

- Now the SDK supports RS256 codes, it will decode using the `.well-known/jwks.json` endpoint to fetch the public key

## [2.2.0](https://github.com/auth0/auth0-PHP/tree/2.2.0) (2016-04-15)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.1.0...2.2.0)

**Notes**
- Now the SDK fetches the user using the `tokeninfo` endpoint to be fully compliant with the openid spec
- Now the SDK supports RS256 codes, it will decode using the `.well-known/jwks.json` endpoint to fetch the public key

**Closed issues:**

- /tokeninfo API support [\#76](https://github.com/auth0/auth0-PHP/issues/76)
- Specify GuzzleHttp config [\#73](https://github.com/auth0/auth0-PHP/issues/73)

**Merged pull requests:**

- Fix typo in DocBlock [\#77](https://github.com/auth0/auth0-PHP/pull/77) ([tflight](https://github.com/tflight))

## [3.1.0](https://github.com/auth0/auth0-PHP/tree/3.1.0) (2016-03-10)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.0.1...3.1.0)

**Closed issues:**

- API seed incomptaible with auth0-php 3 [\#70](https://github.com/auth0/auth0-PHP/issues/70)
-  "cURL error 60: SSL certificate problem: self signed certificate in certificate chain \(see http://curl.haxx.se/libcurl/c/libcurl-errors.html\)", [\#69](https://github.com/auth0/auth0-PHP/issues/69)
- basic-webapp outdated dependencies [\#68](https://github.com/auth0/auth0-PHP/issues/68)
- basic-webapp project relative path [\#67](https://github.com/auth0/auth0-PHP/issues/67)
- Typo on README [\#63](https://github.com/auth0/auth0-PHP/issues/63)
- Missing updateAppMetadata\(\) method? [\#59](https://github.com/auth0/auth0-PHP/issues/59)

**Merged pull requests:**

- 3.1.0 [\#74](https://github.com/auth0/auth0-PHP/pull/74) ([glena](https://github.com/glena))
- Compatibility with new version of Auth0php [\#72](https://github.com/auth0/auth0-PHP/pull/72) ([Annyv2](https://github.com/Annyv2))
- depedencies update, fix routes to css and js [\#71](https://github.com/auth0/auth0-PHP/pull/71) ([Amialc](https://github.com/Amialc))
- update lock version [\#66](https://github.com/auth0/auth0-PHP/pull/66) ([Amialc](https://github.com/Amialc))
- Fixed typo [\#65](https://github.com/auth0/auth0-PHP/pull/65) ([thijsvdanker](https://github.com/thijsvdanker))
- Update README.md [\#64](https://github.com/auth0/auth0-PHP/pull/64) ([Annyv2](https://github.com/Annyv2))
- Test travis env vars [\#62](https://github.com/auth0/auth0-PHP/pull/62) ([glena](https://github.com/glena))
- Fix typo [\#58](https://github.com/auth0/auth0-PHP/pull/58) ([vboctor](https://github.com/vboctor))

## [3.0.1](https://github.com/auth0/auth0-PHP/tree/3.0.1) (2016-02-03)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.11...3.0.1)

**Merged pull requests:**

- Fixed Importing users [\#61](https://github.com/auth0/auth0-PHP/pull/61) ([polishdeveloper](https://github.com/polishdeveloper))

## [1.0.11](https://github.com/auth0/auth0-PHP/tree/1.0.11) (2016-01-27)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/3.0.0...1.0.11)

**Closed issues:**

- Exception: Cannot handle token prior to \[timestamp\] [\#56](https://github.com/auth0/auth0-PHP/issues/56)

**Merged pull requests:**

- Fix ApiConnections class name [\#60](https://github.com/auth0/auth0-PHP/pull/60) ([bjyoungblood](https://github.com/bjyoungblood))

## [3.0.0](https://github.com/auth0/auth0-PHP/tree/3.0.0) (2016-01-18)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.1.2...3.0.0)

**General 3.x notes**
- SDK api changes, now the Auth0 API client is not build of static classes anymore. Usage example:

```php
$token = "eyJhbGciO....eyJhdWQiOiI....1ZVDisdL...";
$domain = "account.auth0.com";
$guzzleOptions = [ ... ];

$auth0Api = new \Auth0\SDK\Auth0Api($token, $domain, $guzzleOptions); /* $guzzleOptions is optional */

$usersList = $auth0Api->users->search([ "q" => "email@test.com" ]);
```

**Closed issues:**

- Missing instruccions step 2 Configure Auth0 PHP Plugin [\#55](https://github.com/auth0/auth0-PHP/issues/55)
- Outdated Lock [\#52](https://github.com/auth0/auth0-PHP/issues/52)
- Deprecated method in basic-webapp [\#50](https://github.com/auth0/auth0-PHP/issues/50)

**Merged pull requests:**

- V3 with new API and full support for API V2 [\#57](https://github.com/auth0/auth0-PHP/pull/57) ([glena](https://github.com/glena))

## [2.1.2](https://github.com/auth0/auth0-PHP/tree/2.1.2) (2016-01-14)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.1.1...2.1.2)

**Merged pull requests:**

- Update Lock [\#53](https://github.com/auth0/auth0-PHP/pull/53) ([Annyv2](https://github.com/Annyv2))
- Update index.php [\#51](https://github.com/auth0/auth0-PHP/pull/51) ([Annyv2](https://github.com/Annyv2))
- Update lock [\#45](https://github.com/auth0/auth0-PHP/pull/45) ([Annyv2](https://github.com/Annyv2))

## [2.1.1](https://github.com/auth0/auth0-PHP/tree/2.1.1) (2015-11-29)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.1.0...2.1.1)

**Merged pull requests:**

- Fix Closure namespace issue [\#49](https://github.com/auth0/auth0-PHP/pull/49) ([mkeasling](https://github.com/mkeasling))

## [2.1.0](https://github.com/auth0/auth0-PHP/tree/2.1.0) (2015-11-24)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/2.0.0...2.1.0)

**Closed issues:**

- Update to use v3.0 of firebase/php-jwt [\#47](https://github.com/auth0/auth0-PHP/issues/47)

**Merged pull requests:**

- 2.0.1 updated JWT dependency [\#48](https://github.com/auth0/auth0-PHP/pull/48) ([glena](https://github.com/glena))

## [2.0.0](https://github.com/auth0/auth0-PHP/tree/2.0.0) (2015-11-23)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.10...2.0.0)

**General 2.x notes**

- Session storage now returns null (and null is expected by the sdk) if there is no info stored (this change was made since false is a valid value to be stored in session).
- Guzzle 6.1 required

**Closed issues:**

- Guzzle 6 [\#43](https://github.com/auth0/auth0-PHP/issues/43)
- User is null not false [\#41](https://github.com/auth0/auth0-PHP/issues/41)
- Issues with PHP Seed project [\#38](https://github.com/auth0/auth0-PHP/issues/38)
- authParams... how do I retrieve the results? [\#37](https://github.com/auth0/auth0-PHP/issues/37)

**Merged pull requests:**

- 2.x.x dev [\#46](https://github.com/auth0/auth0-PHP/pull/46) ([glena](https://github.com/glena))
- Update README.md [\#40](https://github.com/auth0/auth0-PHP/pull/40) ([Annyv2](https://github.com/Annyv2))
- Update composer instructions [\#39](https://github.com/auth0/auth0-PHP/pull/39) ([iWader](https://github.com/iWader))

## [1.0.10](https://github.com/auth0/auth0-PHP/tree/1.0.10) (2015-09-23)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.9...1.0.10)

**Closed issues:**

- Improve error message when no id\_token is received after code exchange [\#35](https://github.com/auth0/auth0-PHP/issues/35)
- PHP should be 5.4+, not 5.3+ [\#34](https://github.com/auth0/auth0-PHP/issues/34)

**Merged pull requests:**

- Release 1.0.10 [\#36](https://github.com/auth0/auth0-PHP/pull/36) ([glena](https://github.com/glena))
- Remove code that rewrites user\_id property in $body [\#33](https://github.com/auth0/auth0-PHP/pull/33) ([Ring](https://github.com/Ring))

## [1.0.9](https://github.com/auth0/auth0-PHP/tree/1.0.9) (2015-08-03)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.8...1.0.9)

**Closed issues:**

- Stable dependencies in composer.json instead of "dev-master" [\#30](https://github.com/auth0/auth0-PHP/issues/30)

**Merged pull requests:**

- tagged adoy to ~1.3 [\#31](https://github.com/auth0/auth0-PHP/pull/31) ([glena](https://github.com/glena))
- Bad reference in Android PHP API Seed Project Readme file \#67 [\#29](https://github.com/auth0/auth0-PHP/pull/29) ([glena](https://github.com/glena))

## [1.0.8](https://github.com/auth0/auth0-PHP/tree/1.0.8) (2015-07-27)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.7...1.0.8)

**Closed issues:**

- Class 'JWT' not found [\#25](https://github.com/auth0/auth0-PHP/issues/25)
- Correct way to use the JWT Token generated in API v2 if we want expanded scope [\#19](https://github.com/auth0/auth0-PHP/issues/19)

**Merged pull requests:**

- Fix create client api call + new create user example [\#28](https://github.com/auth0/auth0-PHP/pull/28) ([glena](https://github.com/glena))

## [1.0.7](https://github.com/auth0/auth0-PHP/tree/1.0.7) (2015-07-17)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.6...1.0.7)

**Closed issues:**

- Error at Auth0JWT::encode when using custom payload [\#23](https://github.com/auth0/auth0-PHP/issues/23)
- Error in composer install [\#21](https://github.com/auth0/auth0-PHP/issues/21)
- Test [\#20](https://github.com/auth0/auth0-PHP/issues/20)

**Merged pull requests:**

- v1.0.7 [\#26](https://github.com/auth0/auth0-PHP/pull/26) ([glena](https://github.com/glena))
- Readme file call URL port fixed [\#18](https://github.com/auth0/auth0-PHP/pull/18) ([jose-e-rodriguez](https://github.com/jose-e-rodriguez))
- ApiUsers link account identities fix [\#16](https://github.com/auth0/auth0-PHP/pull/16) ([deboorn](https://github.com/deboorn))

## [1.0.6](https://github.com/auth0/auth0-PHP/tree/1.0.6) (2015-06-12)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.5...1.0.6)

**Merged pull requests:**

- Make Auth0::setUser public in order to let update the stored user [\#17](https://github.com/auth0/auth0-PHP/pull/17) ([glena](https://github.com/glena))

## [1.0.5](https://github.com/auth0/auth0-PHP/tree/1.0.5) (2015-06-02)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.4...1.0.5)

**Merged pull requests:**

- Updates the changed endpoints \(tickets\) [\#15](https://github.com/auth0/auth0-PHP/pull/15) ([glena](https://github.com/glena))
- Api users search link accounts fix [\#14](https://github.com/auth0/auth0-PHP/pull/14) ([deboorn](https://github.com/deboorn))
- Auth0JWT encode fix to allow scope with null custom payload [\#13](https://github.com/auth0/auth0-PHP/pull/13) ([deboorn](https://github.com/deboorn))

## [1.0.4](https://github.com/auth0/auth0-PHP/tree/1.0.4) (2015-05-19)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.3...1.0.4)

## [1.0.3](https://github.com/auth0/auth0-PHP/tree/1.0.3) (2015-05-15)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.2...1.0.3)

**Merged pull requests:**

- Applied the new Info Headers schema [\#12](https://github.com/auth0/auth0-PHP/pull/12) ([glena](https://github.com/glena))

## [1.0.2](https://github.com/auth0/auth0-PHP/tree/1.0.2) (2015-05-13)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.1...1.0.2)

**Closed issues:**

- EU tenants are getting Unauthorize on api calls [\#10](https://github.com/auth0/auth0-PHP/issues/10)
- PHP Fatal error:  Class 'Auth0\SDK\API\ApiUsers' not found in vendor/auth0/auth0-php/src/Auth0.php on line 256 [\#9](https://github.com/auth0/auth0-PHP/issues/9)

**Merged pull requests:**

- Fix EU api calls and autoloading issue [\#11](https://github.com/auth0/auth0-PHP/pull/11) ([glena](https://github.com/glena))

## [1.0.1](https://github.com/auth0/auth0-PHP/tree/1.0.1) (2015-05-12)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/1.0.0...1.0.1)

**Closed issues:**

- SDK Client headers spec compliant [\#7](https://github.com/auth0/auth0-PHP/issues/7)
- Example is out of date [\#5](https://github.com/auth0/auth0-PHP/issues/5)

**Merged pull requests:**

- SDK Client headers spec compliant \#7 [\#8](https://github.com/auth0/auth0-PHP/pull/8) ([glena](https://github.com/glena))

## [1.0.0](https://github.com/auth0/auth0-PHP/tree/1.0.0) (2015-05-07)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/0.6.6...1.0.0)

**General 1.x notes** 

- Now, all the SDK is under the namespace `\Auth0\SDK`
- The exceptions were moved to the namespace `\Auth0\SDK\Exceptions`
- The Auth0 class, now provides two methods to access the user metadata, `getUserMetadata` and `getAppMetadata`. For more info, check the [API v2 changes](https://auth0.com/docs/apiv2Changes)
- The Auth0 class, now provides a way to update the UserMetadata with the method `updateUserMetadata`. Internally, it uses the [update user endpoint](https://auth0.com/docs/apiv2#!/users/patch_users_by_id), check the method documentation for more info.
- The new service `\Auth0\SDK\API\ApiUsers` provides an easy way to consume the API v2 Users endpoints.
- A simple API client (`\Auth0\SDK\API\ApiClient`) is also available to use.
- A JWT generator and decoder is also available (`\Auth0\SDK\Auth0JWT`)
- Now provides an interface for the [Authentication API](https://auth0.com/docs/auth-api).

**Closed issues:**

- Unexpected token [\#4](https://github.com/auth0/auth0-PHP/issues/4)

**Merged pull requests:**

- Auth0 API v2 support [\#6](https://github.com/auth0/auth0-PHP/pull/6) ([glena](https://github.com/glena))
- Fixed port number on PHP README [\#2](https://github.com/auth0/auth0-PHP/pull/2) ([mgonto](https://github.com/mgonto))

## [0.6.6](https://github.com/auth0/auth0-PHP/tree/0.6.6) (2014-04-14)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/0.6.5...0.6.6)

**Closed issues:**

- generateUrl\(\) in BaseAuth0 is creating bad URLs [\#1](https://github.com/auth0/auth0-PHP/issues/1)

## [0.6.5](https://github.com/auth0/auth0-PHP/tree/0.6.5) (2014-04-02)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/0.6.4...0.6.5)

## [0.6.4](https://github.com/auth0/auth0-PHP/tree/0.6.4) (2014-02-13)
[Full Changelog](https://github.com/auth0/auth0-PHP/compare/0.6.3...0.6.4)

## [0.6.3](https://github.com/auth0/auth0-PHP/tree/0.6.3) (2014-01-06)


\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
