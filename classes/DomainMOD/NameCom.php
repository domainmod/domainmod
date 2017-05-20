<?php
/**
 * /classes/DomainMOD/NameCom.php
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

class NameCom
{
    private $db;
    private $registrar;

    public function __construct($db)
    {
        $this->db = $db;
        $this->registrar = 'Name.com';
        $this->log = new Log('namecom.class');
        $this->format = new Format();
    }

    public function getApiUrl($command, $domain)
    {
        $base_url = 'https://api.name.com/api/';
        if ($command == 'domainlist') {
            return $base_url . 'domain/list';
        } elseif ($command == 'info') {
            return $base_url . 'domain/get/' . $domain;
        } elseif ($command == 'autorenewal') {
            return $base_url . 'domain/get/' . $domain;
        } else {
            return 'Unable to build API URL';
        }
    }

    public function apiCall($full_url, $account_username, $api_key)
    {
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Api-Username: ' . $account_username,
            'Api-Token: ' . $api_key));
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getDomainList($account_username, $api_key)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_url = $this->getApiUrl('domainlist', '');
        $api_results = $this->apiCall($api_url, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['result']['message'] == "Command Successful") {

            foreach(array_keys($array_results['domains']) as $domain) {

                $domain_list[] = $domain;
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('Username' => $account_username, 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $api_key, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl('info', $domain);
        $api_results = $this->apiCall($api_url, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['result']['message'] == "Command Successful") {

            // get expiration date
            $expiration_date = substr($array_results['expire_date'], 0, 10);

            // get dns servers
            $dns_result = $array_results['nameservers'];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_result = (string) $array_results['whois_privacy']['enabled'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = (string) $array_results['auto_renew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);

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

} //@formatter:on
