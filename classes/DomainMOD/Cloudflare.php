<?php
/**
 * /classes/DomainMOD/Cloudflare.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

class Cloudflare
{
    public $deeb;
    public $format;
    public $log;
    public $base_url = 'https://api.cloudflare.com/client/v4/';
    public $registrar_path = 'registrar'; // the tests use 'registrar-sandbox'

    /*
     * State shared by every Cloudflare object in this PHP process (one cron run or page load), keyed by a hash of the
     * credential (never the credential itself). DomainQueue creates a new object for each domain, so this can't live
     * on the object.
     */
    protected static $auth_methods = array();  // which authentication worked: 'bearer' or 'legacy'
    protected static $cooldown_until = array(); // don't call Cloudflare again before this time (rate limited)
    protected static $blocked = array();        // waited too long this run: no more calls until the run ends
    protected static $waited = array();         // seconds spent waiting inline this run

    // Test-only hooks: when set, they replace the HTTP request and the wait (see the test harness)
    public static $test_transport = null;
    public static $test_sleep = null;

    const SHORT_RETRY_AFTER = 15;   // wait inline for a Retry-After up to this many seconds
    const DEFAULT_RETRY_AFTER = 10; // a 429 with no Retry-After
    const DEFAULT_COOLDOWN = 60;    // cooldown after the retries are used up
    const MAX_RETRIES = 2;          // per request, for 429s and for server errors
    const SERVER_ERROR_WAIT = 5;
    const MAX_INLINE_WAIT = 60;     // per credential, per run
    const MAX_PAGES = 1000;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.cloudflare');
        $this->deeb = Database::getInstance();
    }

    public function getDomainList($account_username, $account_id, $api_key)
    {
        $domain_list = array();
        $seen_cursors = array();
        $cursor = '';
        $pages = 0;

        do {

            $api_url = $this->registrarUrl($account_id, 'registrations') . '?per_page=50&sort_by=name';
            if ($cursor !== '') $api_url .= '&cursor=' . rawurlencode($cursor);

            $array_results = $this->apiCall($api_url, $account_username, $api_key);

            /*
             * All or nothing, and a failure returns '' rather than 0 and an empty array: the Domain Queue treats any
             * other result (even 0 and an empty array, since 0 != '' in PHP 8) as a complete list and marks it
             * finished, so a partial or failed list would never be retried
             */
            if (!is_array($array_results) || empty($array_results['success']) || !isset($array_results['result'])
                || !is_array($array_results['result'])) {

                $this->logFailure('Unable to get domain list', $array_results, $account_username, $account_id, $api_key);
                return array('', '');

            }

            foreach ($array_results['result'] as $registration) {

                // Anything unexpected fails the whole list rather than leaving a domain out
                if (!is_array($registration) || !isset($registration['domain_name'])
                    || !is_string($registration['domain_name']) || $registration['domain_name'] === '') {

                    $this->logFailure('Unable to get domain list (unexpected response)', $array_results,
                        $account_username, $account_id, $api_key);
                    return array('', '');

                }

                $domain_list[] = strtolower($registration['domain_name']);

            }

            $cursor = (string) ($array_results['result_info']['cursor'] ?? '');
            $pages++;

            if ($cursor !== '' && (isset($seen_cursors[$cursor]) || $pages >= self::MAX_PAGES)) {

                $this->logFailure('Unable to get domain list (the list of pages did not end)', $array_results,
                    $account_username, $account_id, $api_key);
                return array('', '');

            }

            $seen_cursors[$cursor] = true;

        } while ($cursor !== '');

        return array(count($domain_list), $domain_list);
    }

    public function getFullInfo($account_username, $account_id, $api_key, $domain)
    {
        $failed = array('', array(), '', '');

        $api_url = $this->registrarUrl($account_id, 'registrations/' . rawurlencode(Domain::forApi($domain)));
        $array_results = $this->apiCall($api_url, $account_username, $api_key);

        $expires_at = $array_results['result']['expires_at'] ?? null;
        if (!is_array($array_results) || empty($array_results['success'])
            || !isset($array_results['result']['domain_name']) || !is_string($array_results['result']['domain_name'])
            || ($expires_at !== null && !is_string($expires_at))) {

            $this->logFailure('Unable to get domain details', $array_results, $account_username, $account_id, $api_key,
                $domain);
            return $failed;

        }

        $registration = $array_results['result'];

        // Pending registrations and transfers have no expiry yet: leave the domain in the queue until they're active
        if (empty($registration['expires_at'])) {

            $log_message = 'Domain is pending at Cloudflare (no expiry date yet), will retry';
            $log_extra = array('Domain' => $domain, 'Status' => $registration['status'] ?? '');
            $this->log->notice($log_message, $log_extra);
            return $failed;

        }

        // The expiry must start with a real date (YYYY-MM-DD), or the lookup is retried
        $expiration_date = substr($registration['expires_at'], 0, 10);
        $date_parts = explode('-', $expiration_date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)
            || !checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0])) {

            $this->logFailure('Unable to get domain details (unexpected expiry date)', $array_results, $account_username,
                $account_id, $api_key, $domain);
            return $failed;

        }

        $privacy_status = $this->processPrivacy(($registration['privacy_mode'] ?? '') === 'redaction' ? '1' : '0');
        $autorenewal_status = $this->processAutorenew(!empty($registration['auto_renew']) ? '1' : '0');

        // Name servers come from the domain's Cloudflare zone. Without them, the whole lookup counts as failed, so
        // the domain isn't imported with an empty DNS profile
        $dns_servers = $this->getNameServers($account_username, $account_id, $api_key, $domain);
        if (empty($dns_servers)) return $failed;

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function getNameServers($account_username, $account_id, $api_key, $domain)
    {
        $api_url = $this->base_url . 'zones?name=' . rawurlencode(Domain::forApi($domain)) . '&account.id=' . rawurlencode($account_id);
        $array_results = $this->apiCall($api_url, $account_username, $api_key);

        $name_servers = array();
        if (is_array($array_results) && !empty($array_results['success'])
            && !empty($array_results['result'][0]['name_servers']) && is_array($array_results['result'][0]['name_servers'])) {

            $name_servers = array_values(array_filter($array_results['result'][0]['name_servers'],
                function ($name_server) { return is_string($name_server) && $name_server !== ''; }));

        }

        if (empty($name_servers)) {

            $this->logFailure('Unable to get name servers (the API Token needs Zone Read access to this domain\'s zone)',
                $array_results, $account_username, $account_id, $api_key, $domain);

        }

        return $name_servers;
    }

    /*
     * One API call, with authentication, rate limiting, and retries. Returns the decoded JSON response, or null if
     * there's no usable response.
     */
    public function apiCall($full_url, $account_username, $api_key)
    {
        $credential = hash('sha256', $api_key);

        if (!empty(self::$blocked[$credential])
            || (isset(self::$cooldown_until[$credential]) && self::$cooldown_until[$credential] > time())) {

            $this->log->warning('Skipping Cloudflare API call: rate limited, will retry on a later run',
                array('URL' => $full_url));
            return null;

        }

        $method = self::$auth_methods[$credential] ?? $this->guessAuthMethod($api_key);
        $tried_legacy = false;
        $rate_limit_retries = 0;
        $server_error_retries = 0;

        while (true) {

            $use_legacy = ($method === 'legacy');
            if ($use_legacy) {
                $headers = array('X-Auth-Email: ' . $account_username, 'X-Auth-Key: ' . $api_key);
            } else {
                $headers = array('Authorization: Bearer ' . $api_key);
            }
            $headers[] = 'Content-Type: application/json';

            list($http_code, $body, $response_headers) = $this->sendRequest($full_url, $headers);
            $array_results = is_string($body) ? json_decode($body, true) : null;

            // A credential whose format doesn't say what it is: if it isn't a token, try it as a Global API Key
            if ($method === 'unknown' && !$tried_legacy && $this->isAuthError($http_code, $array_results)) {

                $method = 'legacy';
                $tried_legacy = true;
                continue;

            }

            if ($http_code == 429) {

                $given_retry_after = isset($response_headers['retry-after']) ? (int) $response_headers['retry-after'] : 0;
                $retry_after = $given_retry_after > 0 ? $given_retry_after : self::DEFAULT_RETRY_AFTER;

                if ($retry_after > self::SHORT_RETRY_AFTER || $rate_limit_retries >= self::MAX_RETRIES) {

                    // Wait what Cloudflare asked for, or the default cooldown if it didn't say
                    $cooldown = $given_retry_after > 0 ? $given_retry_after : self::DEFAULT_COOLDOWN;
                    self::$cooldown_until[$credential] = time() + $cooldown;
                    $this->log->warning('Cloudflare API rate limit reached, will retry on a later run',
                        array('URL' => $full_url, 'Retry-After' => $retry_after));
                    return null;

                }

                if (!$this->wait($credential, $retry_after, $full_url)) return null;
                $rate_limit_retries++;
                continue;

            }

            if ($http_code >= 500 || $http_code == 0) {

                if ($server_error_retries >= self::MAX_RETRIES) {

                    $this->log->error('Cloudflare API unavailable, will retry on a later run',
                        array('URL' => $full_url, 'HTTP Code' => $http_code));
                    return null;

                }

                if (!$this->wait($credential, self::SERVER_ERROR_WAIT, $full_url)) return null;
                $server_error_retries++;
                continue;

            }

            if ($http_code >= 200 && $http_code < 300) {
                self::$auth_methods[$credential] = $use_legacy ? 'legacy' : 'bearer';
            }

            return is_array($array_results) ? $array_results : null;

        }
    }

    public function guessAuthMethod($api_key)
    {
        if (preg_match('/^cf(ut|at)_/', $api_key)) return 'bearer'; // API Tokens
        if (strpos($api_key, 'cfk_') === 0) return 'legacy'; // Global API Key
        if (preg_match('/^[0-9a-f]{37,45}$/', $api_key) && strlen($api_key) != 40) return 'legacy'; // older Global API Key
        return 'unknown'; // an older API Token, or a 40-character Global API Key
    }

    public function isAuthError($http_code, $array_results)
    {
        if ($http_code == 401 || $http_code == 403) return true;
        if ($http_code == 400 && is_array($array_results) && !empty($array_results['errors'])) {
            foreach ($array_results['errors'] as $error) {
                if (in_array((int) ($error['code'] ?? 0), array(6003, 6103, 6111, 9103, 9106, 9109, 10000), true)) return true;
            }
        }
        return false;
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, true);
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty($dns_result)) {
            $dns_servers = array_filter($dns_result);
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == '1') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == '1') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

    protected function registrarUrl($account_id, $path)
    {
        return $this->base_url . 'accounts/' . rawurlencode($account_id) . '/' . $this->registrar_path . '/' . $path;
    }

    // Wait inline, unless that would take this run's total wait for the credential past the limit, or run past an
    // overall deadline (see HttpClient::setDeadline()).
    protected function wait($credential, $seconds, $full_url)
    {
        // The wait is still owed, so later calls with this credential hold off until it would have ended.
        $remaining = HttpClient::remaining();
        if ($remaining !== null && $remaining <= $seconds + 1) {

            self::$cooldown_until[$credential] = time() + $seconds;
            $this->log->warning('Cloudflare API wait skipped, because it would run past the time limit',
                array('URL' => $full_url));
            return false;

        }

        $waited = self::$waited[$credential] ?? 0;
        if ($waited + $seconds > self::MAX_INLINE_WAIT) {

            self::$blocked[$credential] = true;
            $this->log->warning('Cloudflare API waits used up for this run, will retry on a later run',
                array('URL' => $full_url));
            return false;

        }

        self::$waited[$credential] = $waited + $seconds;
        if (self::$test_sleep !== null) {
            call_user_func(self::$test_sleep, $seconds);
        } else {
            sleep($seconds);
        }
        return true;
    }

    // Returns array(HTTP code, body, lower-cased response headers)
    protected function sendRequest($full_url, $headers)
    {
        if (self::$test_transport !== null) return call_user_func(self::$test_transport, $full_url, $headers);

        $response_headers = array();
        $handle = HttpClient::init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$response_headers) {
            $parts = explode(':', $header, 2);
            if (count($parts) == 2) $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return strlen($header);
        });
        $body = HttpClient::exec($handle, 'Cloudflare API');
        $http_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        // No curl_close(): it has done nothing since PHP 8.0 and is deprecated in 8.5

        return array($http_code, $body === false ? null : $body, $response_headers);
    }

    protected function logFailure($message, $array_results, $account_username, $account_id, $api_key, $domain = '')
    {
        $log_extra = array('Username' => $account_username, 'Account ID' => $this->format->obfusc($account_id),
                           'API Key' => $this->format->obfusc($api_key));
        if ($domain !== '') $log_extra = array('Domain' => $domain) + $log_extra;
        if (is_array($array_results) && !empty($array_results['errors'])) {
            $log_extra['Errors'] = json_encode($array_results['errors']);
        }
        $this->log->error($message, $log_extra);
    }

} //@formatter:on
