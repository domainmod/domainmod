<?php
/**
 * /classes/DomainMOD/Namecom.php
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

class Namecom
{

    public function getApiKey($connection, $account_id)
    {
        $error = new Error();
        $sql = "SELECT username, api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $account_username = $row->username;
                $api_key = $row->api_key;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($account_username, $api_key);
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
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, TRUE );
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getDomainList($account_username, $api_key)
    {
        $api_url = $this->getApiUrl('domainlist', '');
        $api_results = $this->apiCall($api_url, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['result']['message'] == "Command Successful") {

            $domain_list = array();
            $domain_count = 0;

            foreach(array_keys($array_results['domains']) as $domain) {

                $domain_list[] = $domain;
                $domain_count++;

            }

        } else {

            // if the API call failed assign empty values
            $domain_list = '';
            $domain_count = '';

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $api_key, $domain)
    {
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
