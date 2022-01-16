<?php
/**
 * /classes/DomainMOD/Freenom.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

class Freenom
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.freenom');
    }

    public function getApiUrl($account_username, $account_password, $domain, $command)
    {
        $base_url = 'https://api.freenom.com/v2/';
        if ($command == 'domainlist') {
            return $base_url . 'domain/list?results_per_page=10000&email=' . $account_username . '&password=' . $account_password;
        } elseif ($command == 'info') {
            return $base_url . 'domain/getinfo?domainname=' . $domain . '&email=' . $account_username . '&password=' . $account_password;
        } else {
            return _('Unable to build API URL');
        }
    }

    public function apiCall($full_url)
    {
        $handle = curl_init($full_url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getDomainList($account_username, $account_password)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_url = $this->getApiUrl($account_username, $account_password, '', 'domainlist');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['status'] == 'OK') {

            foreach ($array_results['domain'] as $domain) {

                $domain_list[] = $domain['domainname'];
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $account_password, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($account_username, $account_password, $domain, 'info');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['status'] == 'OK') {

            // get expiration date
            $expiration_date = $this->processExpiry($array_results['domain'][0]['expirationdate']);

            // get dns servers
            $dns_result = $array_results['domain'][0]['nameserver'];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_status = $this->processPrivacy($array_results['domain'][0]['idshield']);

            // get auto renewal status
            $autorenewal_status = $this->processAutorenew($array_results['domain'][0]['autorenew']);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, true);
    }

    public function processExpiry($expiry_result)
    {
        $expiry_formatted = substr_replace($expiry_result, '-', 4, 0);
        return substr_replace($expiry_formatted, '-', 7, 0);
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty($dns_result)) {
            $dns_servers = array();
            foreach ($dns_result as $nameserver) {
                $dns_servers[] = $nameserver['hostname'];
            }
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'enabled') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'enabled') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
