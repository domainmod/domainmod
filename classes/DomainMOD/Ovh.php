<?php
/**
 * /classes/DomainMOD/Ovh.php
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

class Ovh
{
    public $format;
    public $log;

    // OVHcloud runs separate systems, and an account only works with its own one's API
    public $endpoints = array(
        'Canada' => 'https://ca.api.ovh.com/1.0',
        'Europe' => 'https://eu.api.ovh.com/1.0',
        'US' => 'https://api.us.ovhcloud.com/1.0');

    /*
     * State shared by every Ovh object in this PHP process (one cron run or page load): the endpoint that accepted each
     * set of credentials (keyed by a hash of them), and each endpoint's clock offset from ours
     */
    protected static $endpoint_for = array();
    protected static $time_offset = array();

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.ovh');
    }

    public function getApiUrl($command, $domain)
    {
        // registrar APIs take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $domain = Domain::forApi($domain);
        if ($command == 'domainlist') {
            return '/domain';
        } elseif ($command == 'info') {
            return '/domain/' . rawurlencode($domain);
        } elseif ($command == 'privacy') {
            return '/domain/' . rawurlencode($domain) . '/configurations/obfuscatedEmails';
        } else {
            return 'Unable to build API URL';
        }
    }

    // Returns array(HTTP code, decoded JSON or null)
    public function apiCall($base_url, $path, $app_key, $app_secret, $consumer_key)
    {
        $app_key = trim((string) $app_key);
        $app_secret = trim((string) $app_secret);
        $consumer_key = trim((string) $consumer_key);
        $full_url = $base_url . $path;
        $timestamp = (string) (time() + $this->getTimeOffset($base_url));

        // OVH's request signature: "$1$" + SHA1 of the secret, consumer key, method, URL, body, and timestamp
        $signature = '$1$' . sha1($app_secret . '+' . $consumer_key . '+GET+' . $full_url . '++' . $timestamp);

        $handle = HttpClient::init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'X-Ovh-Application: ' . $app_key,
            'X-Ovh-Consumer: ' . $consumer_key,
            'X-Ovh-Timestamp: ' . $timestamp,
            'X-Ovh-Signature: ' . $signature,
            'Accept: application/json'));
        $body = HttpClient::exec($handle, 'OVH API');
        $http_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $array_results = is_string($body) ? json_decode($body, true) : null;
        return array($http_code, is_array($array_results) ? $array_results : null);
    }

    // OVH rejects a signed request whose timestamp is more than a little off its own clock
    protected function getTimeOffset($base_url)
    {
        if (!isset(self::$time_offset[$base_url])) {

            $handle = HttpClient::init($base_url . '/auth/time');
            $body = HttpClient::exec($handle, 'OVH API');
            $server_time = is_string($body) ? trim($body) : '';
            self::$time_offset[$base_url] = ctype_digit($server_time) ? ((int) $server_time - time()) : 0;

        }
        return self::$time_offset[$base_url];
    }

    /*
     * Finds which OVH system the credentials belong to by listing the account's domains on each in turn, and returns
     * array(base URL, the decoded list), or array('', null) if none accepted them
     */
    protected function findEndpoint($app_key, $app_secret, $consumer_key)
    {
        $credential = hash('sha256', $app_key . "\n" . $app_secret . "\n" . $consumer_key);
        $path = $this->getApiUrl('domainlist', '');

        if (isset(self::$endpoint_for[$credential])) {
            $base_url = self::$endpoint_for[$credential];
            list($http_code, $array_results) = $this->apiCall($base_url, $path, $app_key, $app_secret, $consumer_key);
            return ($http_code === 200) ? array($base_url, $array_results) : array('', null);
        }

        foreach ($this->endpoints as $base_url) {
            list($http_code, $array_results) = $this->apiCall($base_url, $path, $app_key, $app_secret, $consumer_key);
            if ($http_code === 200) {
                self::$endpoint_for[$credential] = $base_url;
                return array($base_url, $array_results);
            }
        }

        $this->logFailure('Unable to connect to the OVH API with these credentials on any OVH system', 0, null, $app_key);
        return array('', null);
    }

    public function getDomainList($app_key, $app_secret, $consumer_key)
    {
        $domain_list = array();

        list($base_url, $array_results) = $this->findEndpoint($app_key, $app_secret, $consumer_key);

        /*
         * On any failure return '' rather than 0 and an empty array: the Domain Queue treats any other result (even
         * 0 and an empty array, since 0 != '' in PHP 8) as a complete list and marks it finished, so it would never
         * be retried
         */
        if ($base_url === '' || !is_array($array_results) || !array_is_list($array_results)) {

            if ($base_url !== '') $this->logFailure('Unable to get domain list (unexpected response)', 200, null, $app_key);
            return array('', '');

        }

        foreach ($array_results as $domain) {

            if (!is_string($domain) || $domain === '') {

                $this->logFailure('Unable to get domain list (unexpected response)', 200, null, $app_key);
                return array('', '');

            }

            $domain_list[] = strtolower($domain);

        }

        return array(count($domain_list), $domain_list);
    }

    public function getFullInfo($app_key, $app_secret, $consumer_key, $domain)
    {
        $failed = array('', array(), '', '');

        // the Domain Queue lists the account's domains first, which normally finds the OVH system already
        $credential = hash('sha256', $app_key . "\n" . $app_secret . "\n" . $consumer_key);
        $base_url = self::$endpoint_for[$credential] ?? '';
        if ($base_url === '') list($base_url, ) = $this->findEndpoint($app_key, $app_secret, $consumer_key);
        if ($base_url === '') return $failed;

        list($http_code, $array_results) = $this->apiCall($base_url, $this->getApiUrl('info', $domain), $app_key, $app_secret, $consumer_key);

        if ($http_code !== 200 || !is_array($array_results)) {

            $this->logFailure('Unable to get domain details', $http_code, $array_results, $app_key, $domain);
            return $failed;

        }

        $expires_at = $array_results['expirationDate'] ?? null;
        $name_servers = $array_results['nameServers'] ?? null;
        $renewal_state = $array_results['renewalState'] ?? null;

        // Not registered yet (no expiry): leave the domain in the queue to retry
        if (!is_string($expires_at) || $expires_at === '') {

            $this->log->notice('Domain has no expiry date at OVH yet, will retry', array('Domain' => $domain));
            return $failed;

        }

        $dns_servers = array();
        if (is_array($name_servers)) {
            foreach ($name_servers as $name_server) {
                $host = is_array($name_server) ? ($name_server['nameServer'] ?? null) : null;
                if (is_string($host) && $host !== '') $dns_servers[] = strtolower($host);
            }
        }

        // The expiry must start with a real date (YYYY-MM-DD)
        $expiration_date = substr($expires_at, 0, 10);
        $date_parts = explode('-', $expiration_date);
        $valid_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)
            && checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0]);

        if (!$valid_date || !is_string($renewal_state)) {

            $this->logFailure('Unable to get domain details (unexpected response)', $http_code, null, $app_key, $domain);
            return $failed;

        }

        // a domain with no name servers (an expired one, say) still imports, with the usual placeholders
        $dns_servers = $this->processDns($dns_servers);

        $privacy_status = $this->getPrivacy($base_url, $app_key, $app_secret, $consumer_key, $domain);
        if ($privacy_status === '') return $failed;

        return array($expiration_date, $dns_servers, $privacy_status, $this->processAutorenew($renewal_state));
    }

    // OVH hides the owner's email address in the public WHOIS when email obfuscation is done for the owner contact
    protected function getPrivacy($base_url, $app_key, $app_secret, $consumer_key, $domain)
    {
        list($http_code, $array_results) = $this->apiCall($base_url, $this->getApiUrl('privacy', $domain), $app_key, $app_secret, $consumer_key);

        if ($http_code !== 200 || !is_array($array_results) || !array_is_list($array_results)) {

            $this->logFailure('Unable to get the WHOIS privacy status', $http_code, $array_results, $app_key, $domain);
            return '';

        }

        foreach ($array_results as $obfuscation) {
            if (is_array($obfuscation) && ($obfuscation['type'] ?? '') === 'owner' && ($obfuscation['status'] ?? '') === 'done') {
                return '1';
            }
        }
        return '0';
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
        return ($privacy_result) ? '1' : '0';
    }

    public function processAutorenew($renewal_state)
    {
        return ($renewal_state === 'automatic_renew') ? '1' : '0';
    }

    protected function logFailure($message, $http_code, $array_results, $app_key, $domain = '')
    {
        $log_extra = array('HTTP Code' => $http_code, 'Application Key' => $this->format->obfusc($app_key));
        if ($domain !== '') $log_extra = array('Domain' => $domain) + $log_extra;
        if (is_array($array_results) && isset($array_results['message']) && is_string($array_results['message'])) {
            $log_extra['Message'] = $array_results['message'];
        }
        $this->log->error($message, $log_extra);
    }
} //@formatter:on
