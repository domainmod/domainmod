<?php
/**
 * /classes/DomainMOD/Freenom.php
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

class Freenom
{

    public function getApiUrl($account_username, $account_password, $domain, $command)
    {
        $base_url = 'https://api.freenom.com/v2/';
        if ($command == 'domainlist') {
            return $base_url . 'domain/list?results_per_page=10000&email=' . $account_username . '&password=' . $account_password;
        } elseif ($command == 'info') {
            return $base_url . 'domain/getinfo?domainname=' . $domain . '&email=' . $account_username . '&password=' . $account_password;
        } else {
            return 'Unable to build API URL';
        }
    }

    public function apiCall($full_url)
    {
        $handle = curl_init($full_url);
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, TRUE );
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getDomainList($account_username, $account_password)
    {
        $api_url = $this->getApiUrl($account_username, $account_password, '', 'domainlist');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['status'] == 'OK') {

            $domain_list = array();
            $domain_count = 0;

            foreach ($array_results['domain'] AS $domain) {

                $domain_list[] = $domain['domainname'];
                $domain_count++;

            }

        } else {

            // if the API call failed assign empty values
            $domain_list = '';
            $domain_count = '';

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $account_password, $domain)
    {
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
            foreach ($dns_result AS $nameserver) {
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
