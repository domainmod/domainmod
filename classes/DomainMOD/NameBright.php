<?php
/**
 * /classes/DomainMOD/NameBright.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

class NameBright
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.namebright');
    }

    public function getApiUrl($account_username, $api_app_name, $api_secret, $command, $domain)
    {
        $base_url = 'https://api.namebright.com/';
        if ($command == 'accesstoken') {
            $full_url = $base_url . "auth/token";
            $post_fields = 'grant_type=client_credentials&client_id=' . urlencode($account_username) . ':' . urlencode($api_app_name) . '&client_secret=' . urlencode($api_secret);
        } elseif ($command == 'domainlist') {
            $full_url = $base_url . "rest/account/domains?domainsPerPage=100";
            $post_fields = '';
        } elseif ($command == 'info') {
            $full_url = $base_url . "rest/account/domains/" . $domain;
            $post_fields = '';
        } elseif ($command == 'dns') {
            $full_url = $base_url . "rest/account/domains/" . $domain . "/nameservers";
            $post_fields = '';
        } else {
            return array(_('Unable to build API URL'), '');
        }

        return array($full_url, $post_fields);
    }

    public function apiCall($full_url, $post_fields, $access_token, $command)
    {
        if ($command == 'accesstoken') {

            $handle = curl_init($full_url);
            curl_setopt($handle, CURLOPT_HEADER, true);
            curl_setopt($handle, CURLOPT_HEADER, 'Content-Type: application/x-www-form-urlencoded');
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($handle);
            curl_close($handle);

        } else {

            $handle = curl_init($full_url);
            curl_setopt($handle, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $access_token,
                'Accept: application/json'));
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($handle);
            curl_close($handle);

        }
        return $result;
    }

    public function getDomainList($account_username, $api_app_name, $api_secret)
    {
        $domain_list = array();
        $domain_count = 0;

        // get an access token
        list($api_url, $post_fields) = $this->getApiUrl($account_username, $api_app_name, $api_secret, 'accesstoken', '');
        $api_results = $this->apiCall($api_url, $post_fields, '', 'accesstoken');
        $array_results = $this->convertToArray($api_results);
        $access_token = $array_results['access_token'];

        list($api_url, $post_fields) = $this->getApiUrl($account_username, $api_app_name, $api_secret, 'domainlist', '');
        $api_results = $this->apiCall($api_url, '', $access_token, 'domainlist');
        $array_results = $this->convertToArray($api_results);

        if (isset($array_results['Domains'])) {

            foreach ($array_results['Domains'] as $domain) {

                $domain_list[] = $domain['DomainName'];
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('Username' => $account_username, 'API App Name' => $api_app_name, 'API Secret' => $this->format->obfusc($api_secret));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $api_app_name, $api_secret, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        // get an access token
        list($api_url, $post_fields) = $this->getApiUrl($account_username, $api_app_name, $api_secret, 'accesstoken', '');
        $api_results = $this->apiCall($api_url, $post_fields, '', 'accesstoken');
        $array_results = $this->convertToArray($api_results);
        $access_token = $array_results['access_token'];

        list($api_url, $post_fields) = $this->getApiUrl($account_username, $api_app_name, $api_secret, 'info', $domain);
        $api_results = $this->apiCall($api_url, '', $access_token, 'info');
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results["DomainName"])) {

            // get expiration date
            $expiration_date = substr($array_results['ExpirationDate'], 0, 10);

            // get privacy status
            $privacy_result = (string) $array_results['WhoIsPrivacy'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = (string) $array_results['AutoRenew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get partial domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API App Name' => $api_app_name, 'API Secret' => $this->format->obfusc($api_secret));
            $this->log->error($log_message, $log_extra);

        }

        // get dns servers
        list($api_url, $post_fields) = $this->getApiUrl($account_username, $api_app_name, $api_secret, 'dns', $domain);
        $api_results = $this->apiCall($api_url, '', $access_token, 'dns');
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results["DomainName"])) {

            $dns_result = $array_results['NameServers'];
            $dns_servers = $this->processDns($dns_result);

        } else {

            $log_message = 'Unable to get DNS servers';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API App Name' => $api_app_name, 'API Secret' => $this->format->obfusc($api_secret));
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
