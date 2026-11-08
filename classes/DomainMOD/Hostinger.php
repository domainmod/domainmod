<?php
/**
 * /classes/DomainMOD/Hostinger.php
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

class Hostinger
{
    public $format;
    public $log;
    public $base_url = 'https://developers.hostinger.com/api/domains/v1/';

    // Test-only hook: when set, it replaces the HTTP request (see the test harness)
    public static $test_transport = null;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.hostinger');
    }

    public function getApiUrl($command, $domain)
    {
        if ($command == 'domainlist') {
            return $this->base_url . 'portfolio';
        } elseif ($command == 'info') {
            return $this->base_url . 'portfolio/' . rawurlencode($domain);
        } else {
            return 'Unable to build API URL';
        }
    }

    // Returns array(HTTP code, decoded JSON or null)
    public function apiCall($full_url, $api_key)
    {
        $headers = array('Authorization: Bearer ' . $api_key, 'Accept: application/json');

        if (self::$test_transport !== null) {
            list($http_code, $body) = call_user_func(self::$test_transport, $full_url, $headers);
        } else {
            $handle = curl_init($full_url);
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($handle, CURLOPT_TIMEOUT, 30);
            $body = curl_exec($handle);
            $http_code = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            // No curl_close(): it has done nothing since PHP 8.0 and is deprecated in 8.5
        }

        $array_results = is_string($body) ? json_decode($body, true) : null;
        return array((int) $http_code, is_array($array_results) ? $array_results : null);
    }

    public function getDomainList($api_key)
    {
        $domain_list = array();

        list($http_code, $array_results) = $this->apiCall($this->getApiUrl('domainlist', ''), $api_key);

        /*
         * On any failure return '' rather than 0 and an empty array: the Domain Queue treats any other result (even
         * 0 and an empty array, since 0 != '' in PHP 8) as a complete list and marks it finished, so it would never
         * be retried
         */
        if ($http_code != 200 || !is_array($array_results) || !array_is_list($array_results)) {

            $this->logFailure('Unable to get domain list', $http_code, $array_results, $api_key);
            return array('', '');

        }

        foreach ($array_results as $domain) {

            if (!is_array($domain) || !array_key_exists('domain', $domain)) {

                $this->logFailure('Unable to get domain list (unexpected response)', $http_code, null, $api_key);
                return array('', '');

            }

            // An order that doesn't have a domain name yet has nothing to import
            if ($domain['domain'] === null) continue;

            if (!is_string($domain['domain']) || $domain['domain'] === '') {

                $this->logFailure('Unable to get domain list (unexpected response)', $http_code, null, $api_key);
                return array('', '');

            }

            $domain_list[] = strtolower($domain['domain']);

        }

        return array(count($domain_list), $domain_list);
    }

    public function getFullInfo($api_key, $domain)
    {
        $failed = array('', array(), '', '');

        list($http_code, $array_results) = $this->apiCall($this->getApiUrl('info', $domain), $api_key);

        if ($http_code != 200 || !is_array($array_results)) {

            $this->logFailure('Unable to get domain details', $http_code, $array_results, $api_key, $domain);
            return $failed;

        }

        $expires_at = $array_results['expires_at'] ?? null;
        $name_servers = $array_results['name_servers'] ?? null;
        $privacy = $array_results['is_privacy_protected'] ?? null;

        // Not registered yet (no expiry): leave the domain in the queue to retry
        if (!is_string($expires_at) || $expires_at === '') {

            $this->log->notice('Domain has no expiry date at Hostinger yet, will retry', array('Domain' => $domain));
            return $failed;

        }

        $dns_servers = is_array($name_servers) ? array_values(array_filter($name_servers,
            function ($name_server) { return is_string($name_server) && $name_server !== ''; })) : array();

        // The expiry must start with a real date (YYYY-MM-DD)
        $expiration_date = substr($expires_at, 0, 10);
        $date_parts = explode('-', $expiration_date);
        $valid_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)
            && checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0]);

        if (!$valid_date || empty($dns_servers) || !is_bool($privacy)) {

            $this->logFailure('Unable to get domain details (unexpected response)', $http_code, null, $api_key, $domain);
            return $failed;

        }

        // Hostinger doesn't return the auto renewal status, so it's always No (see the registrar's notes)
        return array($expiration_date, $dns_servers, $this->processPrivacy($privacy), '0');
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
        return ($privacy_result) ? '1' : '0';
    }

    public function processAutorenew($autorenewal_result)
    {
        return ($autorenewal_result) ? '1' : '0';
    }

    protected function logFailure($message, $http_code, $array_results, $api_key, $domain = '')
    {
        $log_extra = array('HTTP Code' => $http_code, 'API Token' => $this->format->obfusc($api_key));
        if ($domain !== '') $log_extra = array('Domain' => $domain) + $log_extra;
        if (is_array($array_results) && isset($array_results['message']) && is_string($array_results['message'])) {
            $log_extra['Message'] = $array_results['message'];
        }
        $this->log->error($message, $log_extra);
    }
} //@formatter:on
