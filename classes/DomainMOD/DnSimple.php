<?php
/**
 * /classes/DomainMOD/DnSimple.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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

class DnSimple
{

    public function getApiUrl($account_id, $command, $domain)
    {
        $base_url = 'https://api.dnsimple.com/v2/';
        if ($command == 'accountid') {
            return $base_url . 'whoami';
        } elseif ($command == 'domainlist') {
            return $base_url . $account_id . '/domains';
        } elseif ($command == 'info') {
            return $base_url . $account_id . '/domains/' . $domain;
        } elseif ($command == 'dns') {
            return $base_url . $account_id . '/registrar/domains/' . $domain . '/delegation';
        } else {
            return 'Unable to build API URL';
        }
    }

    public function apiCall($api_key, $full_url)
    {
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json'));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getAccountId($api_key)
    {
        $api_url = $this->getApiUrl('', 'accountid', '');
        $api_results = $this->apiCall($api_key, $api_url);
        $array_results = $this->convertToArray($api_results);

        return $array_results['data']['account']['id'];
    }

    public function getDomainList($api_key, $account_id)
    {
        $api_url = $this->getApiUrl($account_id, 'domainlist', '');
        $api_results = $this->apiCall($api_key, $api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results['data'][0]['name'])) {

            $domain_list = array();
            $domain_count = 0;

            foreach ($array_results['data'] AS $domain) {

                $domain_list[] = $domain['name'];
                $domain_count++;

            }

        } else {

            // if the API call failed assign empty values
            $domain_list = '';
            $domain_count = '';

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $account_id, $domain)
    {
        $api_url = $this->getApiUrl($account_id, 'info', $domain);
        $api_results = $this->apiCall($api_key, $api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results['data']['name'])) {

            // get expiration date
            $expiration_date = $array_results['data']['expires_on'];

            // get privacy status
            $privacy_result = $array_results['data']['private_whois'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = $array_results['data']['auto_renew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

            // get dns servers
            $api_url = $this->getApiUrl($account_id, 'dns', $domain);
            $api_results = $this->apiCall($api_key, $api_url);
            $array_results = $this->convertToArray($api_results);
            $dns_servers = $this->processDns($array_results['data']);

        } else {

            // if the API call failed assign empty values
            $expiration_date = '';
            $dns_servers = '';
            $privacy_status = '';
            $autorenewal_status = '';

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }
    
    public function convertToArray($api_result)
    {
        return json_decode($api_result, TRUE);
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
        if ($privacy_result == 'true') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'true') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
