<?php
/**
 * /classes/DomainMOD/DreamHost.php
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

class DreamHost
{

    public function getApiUrl($api_key, $command)
    {
        $base_url = 'https://api.dreamhost.com/?key=' . $api_key;
        if ($command == 'domainlist') {
            return $base_url . '&cmd=domain-list_registrations&format=json';
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

    public function getDomainList($dbcon, $api_key, $account_id)
    {
        $error = new Error();
        $api_url = $this->getApiUrl($api_key, 'domainlist');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['result'] == 'success') {

            $domain_list = array();
            $domain_count = 0;

            foreach ($array_results['data'] AS $value) {

                $domain_list[] = $value['domain'];
                $expiry_date = $this->processExpiry($value['expires']);
                if ($value['ns1'] != '' && $value['ns1'] != 'unknown') $ns1 = $value['ns1'];
                if ($value['ns2'] != '' && $value['ns2'] != 'unknown') $ns2 = $value['ns2'];
                if ($value['ns3'] != '' && $value['ns3'] != 'unknown') $ns3 = $value['ns3'];
                if ($value['ns4'] != '' && $value['ns4'] != 'unknown') $ns4 = $value['ns4'];
                if ($value['ns5'] != '' && $value['ns5'] != 'unknown') $ns5 = $value['ns5'];
                if ($value['ns6'] != '' && $value['ns6'] != 'unknown') $ns6 = $value['ns6'];
                if ($value['ns7'] != '' && $value['ns7'] != 'unknown') $ns7 = $value['ns7'];
                if ($value['ns8'] != '' && $value['ns8'] != 'unknown') $ns8 = $value['ns8'];
                if ($value['ns9'] != '' && $value['ns9'] != 'unknown') $ns9 = $value['ns9'];
                if ($value['ns10'] != '' && $value['ns10'] != 'unknown') $ns10 = $value['ns10'];
                $autorenew = $this->processAutorenew($value['autorenew']);
                $privacy = '0';

                $sql = "INSERT INTO domain_queue_temp
                        (account_id, domain, expiry_date, ns1, ns2, ns3, ns4, ns5, ns6, ns7, ns8, ns9, ns10, autorenew, privacy)
                        VALUES
                        ('" . $account_id . "', '" . $domain_list[$domain_count] . "', '" . $expiry_date . "', '" . $ns1 . "', '" . $ns2 . "', '" . $ns3 . "', '" . $ns4 . "', '" . $ns5 . "', '" . $ns6 . "', '" . $ns7 . "', '" . $ns8 . "', '" . $ns9 . "', '" . $ns10 . "', '" . $autorenew . "', '" . $privacy . "')";
                $result = mysqli_query($dbcon, $sql) or $error->outputOldSqlError($dbcon);

                $domain_count++;

            }

        } else {

            // if the API call failed assign empty values
            $domain_list = '';
            $domain_count = '';

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($dbcon, $account_id, $domain)
    {
        $error = new Error();
        $sql = "SELECT id, expiry_date, ns1, ns2, ns3, ns4, ns5, ns6, ns7, ns8, ns9, ns10, autorenew, privacy
                FROM domain_queue_temp
                WHERE account_id = '" . $account_id . "'
                  AND domain = '" . $domain . "'
                ORDER BY id ASC";
        $result = mysqli_query($dbcon, $sql) or $error->outputOldSqlError($dbcon);

         $dns_result = array();

        while ($row = mysqli_fetch_object($result)) {

            $expiration_date = $row->expiry_date;

            $dns_result[0] = $row->ns1;
            $dns_result[1] = $row->ns2;
            $dns_result[2] = $row->ns3;
            $dns_result[3] = $row->ns4;
            $dns_result[4] = $row->ns5;
            $dns_result[5] = $row->ns6;
            $dns_result[6] = $row->ns7;
            $dns_result[7] = $row->ns8;
            $dns_result[8] = $row->ns9;
            $dns_result[9] = $row->ns10;
            $dns_servers = $this->processDns($dns_result);

            $privacy_status = $row->privacy;

            $autorenewal_status = $row->autorenew;

            $sql_temp = "DELETE FROM domain_queue_temp
                         WHERE id = '" . $row->id . "'";
            mysqli_query($dbcon, $sql_temp) or $error->outputOldSqlError($dbcon);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function convertToArray($api_result)
    {
        return json_decode($api_result, TRUE);
    }

    public function processExpiry($expiry_result)
    {
        $unix_expiration_date = strtotime($expiry_result);
        return gmdate("Y-m-d", $unix_expiration_date);
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

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'yes') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
