<?php
/**
 * /classes/DomainMOD/SiteHost.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * SiteHost Domain Queue connector contributed by David McNeill <david@rackteck.co.nz>.
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

class SiteHost
{
    public $format;
    public $log;
    public $base_url = 'https://api.sitehost.nz/1.5/';

    // Test-only hook: when set, it replaces the HTTP request (see the test harness)
    public static $test_transport = null;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.sitehost');
    }

    public function getApiUrl($command, $client_id, $domain = '', $page_number = 1, $page_size = 100)
    {
        $params = array('client_id' => $client_id);

        if ($command == 'domainlist') {
            $params['filters[page_number]'] = (int) $page_number;
            $params['filters[page_size]'] = (int) $page_size;
            return $this->base_url . 'srs/list_domains.json?' . http_build_query($params);
        } elseif ($command == 'info') {
            $params['domain'] = $domain;
            return $this->base_url . 'srs/get_domain.json?' . http_build_query($params);
        } elseif ($command == 'nameservers') {
            $params['domain'] = $domain;
            return $this->base_url . 'srs/list_name_servers.json?' . http_build_query($params);
        } elseif ($command == 'privacy') {
            // Privacy lives on list_domains (private), not get_domain
            $params['filters[domain]'] = $domain;
            $params['filters[page_number]'] = 1;
            $params['filters[page_size]'] = 1;
            return $this->base_url . 'srs/list_domains.json?' . http_build_query($params);
        } else {
            return 'Unable to build API URL';
        }
    }

    // Returns array(HTTP code, decoded JSON or null). $api_key is appended as a query parameter.
    public function apiCall($full_url, $api_key)
    {
        $sep = str_contains($full_url, '?') ? '&' : '?';
        $full_url .= $sep . 'apikey=' . rawurlencode($api_key);

        if (self::$test_transport !== null) {
            list($http_code, $body) = call_user_func(self::$test_transport, $full_url);
        } else {
            $handle = curl_init($full_url);
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

    public function getDomainList($api_key, $client_id)
    {
        $domain_list = array();
        $page_number = 1;
        $page_size = 100;
        $total_pages = 1;

        /*
         * On any failure return '' rather than 0 and an empty array: the Domain Queue treats any other result (even
         * 0 and an empty array, since 0 != '' in PHP 8) as a complete list and marks it finished, so it would never
         * be retried
         */
        do {

            $api_url = $this->getApiUrl('domainlist', $client_id, '', $page_number, $page_size);
            list($http_code, $array_results) = $this->apiCall($api_url, $api_key);

            if ($http_code != 200 || !is_array($array_results) || empty($array_results['status'])
                || !isset($array_results['return']['data']) || !is_array($array_results['return']['data'])) {

                $this->logFailure('Unable to get domain list', $http_code, $array_results, $api_key, $client_id);
                return array('', '');

            }

            $total_pages = max(1, (int) ($array_results['return']['total_pages'] ?? 1));

            foreach ($array_results['return']['data'] as $item) {

                if (!is_array($item) || empty($item['domain']) || !is_string($item['domain'])) {

                    $this->logFailure('Unable to get domain list (unexpected response)', $http_code, null, $api_key, $client_id);
                    return array('', '');

                }

                $domain_list[] = strtolower($item['domain']);

            }

            $page_number++;

        } while ($page_number <= $total_pages);

        return array(count($domain_list), $domain_list);
    }

    public function getFullInfo($api_key, $client_id, $domain)
    {
        $failed = array('', array(), '', '');

        list($http_code, $array_results) = $this->apiCall($this->getApiUrl('info', $client_id, $domain), $api_key);

        if ($http_code != 200 || !is_array($array_results) || empty($array_results['status'])
            || empty($array_results['return']['Domain'])) {

            $this->logFailure('Unable to get domain details', $http_code, $array_results, $api_key, $client_id, $domain);
            return $failed;

        }

        $ret = $array_results['return'];
        $expiration_date = $this->processExpiry($ret['DateBilledUntil'] ?? '');
        $autorenewal_status = $this->processAutorenew(isset($ret['autorenew_term']) ? (int) $ret['autorenew_term'] > 0 : false);

        list($ns_code, $ns_results) = $this->apiCall($this->getApiUrl('nameservers', $client_id, $domain), $api_key);
        $dns_servers = array();
        if ($ns_code == 200 && is_array($ns_results) && !empty($ns_results['status']) && isset($ns_results['return'])
            && is_array($ns_results['return'])) {
            foreach ($ns_results['return'] as $ns) {
                if (is_array($ns) && !empty($ns['name']) && is_string($ns['name'])) {
                    $dns_servers[] = strtolower($ns['name']);
                }
            }
        }
        $dns_servers = $this->processDns($dns_servers);

        // Privacy is on the list endpoint, not get_domain
        $privacy_status = '0';
        list($priv_code, $priv_results) = $this->apiCall($this->getApiUrl('privacy', $client_id, $domain), $api_key);
        if ($priv_code == 200 && is_array($priv_results) && !empty($priv_results['status'])
            && !empty($priv_results['return']['data'][0]) && is_array($priv_results['return']['data'][0])) {
            $privacy_status = $this->processPrivacy(!empty($priv_results['return']['data'][0]['private'])
                && (string) $priv_results['return']['data'][0]['private'] !== '0');
        }

        if ($expiration_date == '' || $expiration_date == '0000-00-00' || $expiration_date == '1970-01-01') {
            $this->logFailure('Unable to get domain details (unexpected expiry)', $http_code, null, $api_key, $client_id, $domain);
            return $failed;
        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, true);
    }

    public function processExpiry($date_str)
    {
        if (!is_string($date_str) || $date_str === '' || str_starts_with($date_str, '0000-00-00')) {
            return '';
        }
        $unix = strtotime($date_str);
        return $unix ? gmdate('Y-m-d', $unix) : '';
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty($dns_result)) {
            $dns_servers = array_values(array_filter($dns_result));
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

    protected function logFailure($message, $http_code, $array_results, $api_key, $client_id, $domain = '')
    {
        $log_extra = array(
            'HTTP Code' => $http_code,
            'API Key' => $this->format->obfusc($api_key),
            'Client ID' => $client_id
        );
        if ($domain !== '') $log_extra = array('Domain' => $domain) + $log_extra;
        if (is_array($array_results) && isset($array_results['msg']) && is_string($array_results['msg'])) {
            $log_extra['Message'] = $array_results['msg'];
        }
        $this->log->error($message, $log_extra);
    }

} //@formatter:on
