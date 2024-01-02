<?php
/**
 * /classes/DomainMOD/Cloudflare.php
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

class Cloudflare
{
    public $deeb;
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.cloudflare');
        $this->deeb = Database::getInstance();
    }

    public function getApiUrl($account_id, $command, $domain)
    {
        $base_url = 'https://api.cloudflare.com/client/v4/';
        if ($command == 'domainlist') {
            $full_url = $base_url . 'zones?status=active&account.id=' . $account_id;
        } elseif ($command == 'info') {
            $full_url = $base_url . 'accounts/' . $account_id . "/registrar/domains/" . $domain;
        } else {
            return array(_('Unable to build API URL'), '');
        }

        return $full_url;
    }

    public function apiCall($full_url, $account_username, $api_key)
    {
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'X-Auth-Email: ' . $account_username,
            'X-Auth-Key: ' . $api_key,
            'Content-Type: application/json'));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($handle);
        curl_close($handle);

        return $result;
    }

    public function getDomainList($account_username, $account_id, $api_key)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_url = $this->getApiUrl($account_id, 'domainlist', '');
        $api_results = $this->apiCall($api_url, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        if (isset($array_results['result'][0]['name'])) {

            foreach ($array_results['result'] as $domain) {

                $domain_list[] = $domain['name'];
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('Username' => $account_username, 'Account ID' => $this->format->obfusc($account_id), 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $account_id, $api_key, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($account_id, 'info', $domain);
        $api_results = $this->apiCall($api_url, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results['result']['name'])) {

            // get expiration date
            $expiration_date = substr($array_results['result']['expires_at'], 0, 10);

            // get privacy status
            $privacy_result = (string) $array_results['result']['privacy'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = (string) $array_results['result']['auto_renew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

            // get DNS servers
            $dns_result = $array_results['result']['name_servers'];
            $dns_servers = $this->processDns($dns_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'Account ID' => $this->format->obfusc($account_id), 'API Key' => $this->format->obfusc($api_key));
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
