<?php
/**
 * /classes/DomainMOD/OpenSrs.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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

class OpenSrs
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
        if ($command == 'domainlist') {

            $start_date = gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") - 2));
            $end_date = gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + 15));

            $xml = <<<EOD
                    <?xml version='1.0' encoding='UTF-8' standalone='no' ?>
                    <!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
                    <OPS_envelope>
                    <header>
                        <version>0.9</version>
                    </header>
                    <body>
                    <data_block>
                        <dt_assoc>
                            <item key="protocol">XCP</item>
                            <item key="object">DOMAIN</item>
                            <item key="action">GET_DOMAINS_BY_EXPIREDATE</item>
                            <item key="attributes">
                             <dt_assoc>
                                    <item key="exp_from">$start_date</item>
                                    <item key="exp_to">$end_date</item>
                                    <item key="page">1</item>
                                    <item key="limit">10000</item>
                             </dt_assoc>
                            </item>
                        </dt_assoc>
                    </data_block>
                    </body>
                    </OPS_envelope>
EOD;
            return $xml;

        } elseif ($command == 'info') {

            $xml = <<<EOD
                    <?xml version='1.0' encoding='UTF-8' standalone='no' ?>
                    <!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
                    <OPS_envelope>
                    <header>
                        <version>0.9</version>
                    </header>
                    <body>
                    <data_block>
                        <dt_assoc>
                            <item key="protocol">XCP</item>
                            <item key="object">DOMAIN</item>
                            <item key="action">GET</item>
                            <item key="attributes">
                             <dt_assoc>
                                    <item key="domain">$domain</item>
                                    <item key="type">all_info</item>
                             </dt_assoc>
                            </item>
                        </dt_assoc>
                    </data_block>
                    </body>
                    </OPS_envelope>
EOD;
            return $xml;

        } elseif ($command == 'dns') {

            $xml = <<<EOD
                    <?xml version='1.0' encoding='UTF-8' standalone='no' ?>
                    <!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
                    <OPS_envelope>
                    <header>
                        <version>0.9</version>
                    </header>
                    <body>
                    <data_block>
                        <dt_assoc>
                            <item key="protocol">XCP</item>
                            <item key="object">DOMAIN</item>
                            <item key="action">GET</item>
                            <item key="attributes">
                             <dt_assoc>
                                    <item key="domain">$domain</item>
                                    <item key="type">nameservers</item>
                             </dt_assoc>
                            </item>
                        </dt_assoc>
                    </data_block>
                    </body>
                    </OPS_envelope>
EOD;
            return $xml;

        } elseif ($command == 'privacy') {

            $xml = <<<EOD
                    <?xml version='1.0' encoding='UTF-8' standalone='no' ?>
                    <!DOCTYPE OPS_envelope SYSTEM 'ops.dtd'>
                    <OPS_envelope>
                    <header>
                        <version>0.9</version>
                    </header>
                    <body>
                    <data_block>
                        <dt_assoc>
                            <item key="protocol">XCP</item>
                            <item key="object">DOMAIN</item>
                            <item key="action">GET</item>
                            <item key="attributes">
                             <dt_assoc>
                                    <item key="domain">$domain</item>
                                    <item key="type">whois_privacy_state</item>
                             </dt_assoc>
                            </item>
                        </dt_assoc>
                    </data_block>
                    </body>
                    </OPS_envelope>
EOD;
            return $xml;

        } else {

            return 'Unable to build API URL';

        }
    }

    public function apiCall($xml, $account_username, $api_key)
    {
        // $handle = curl_init('https://horizon.opensrs.net:55443'); // OpenSRS test environment
        $handle = curl_init('https://rr-n1-tor.opensrs.net:55443');
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Content-Type:text/xml',
            'X-Username:' . $account_username,
            'X-Signature:' . md5(md5($xml . $api_key) .  $api_key)));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($handle);
        return $result;
    }

    public function getDomainList($account_username, $api_key)
    {
        $api_xml = $this->getApiUrl('domainlist', '');
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]['body']['data_block']['dt_assoc']['item'][2] == 'Command successful') {

            $domain_list = array();
            $domain_count = 0;

            foreach ($array_results[0]['body']['data_block']['dt_assoc']['item'][4]['dt_assoc']['item'][0]['dt_array']['item'] AS $domain) {

                $domain_list[] = $domain['dt_assoc']['item'][1];
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
        // get the domain info (for expiration date and auto renewal status)
        $api_xml = $this->getApiUrl('info', $domain);
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        if ($array_results[0]['body']['data_block']['dt_assoc']['item'][2] == 'Query Successful') {

            // get expiration date
            $expiry_result = $array_results[0]['body']['data_block']['dt_assoc']['item'][4]['dt_assoc']['item'][6];
            $expiration_date = $this->processExpiry($expiry_result);

            // get auto renewal status
            $autorenewal_status = $array_results[0]['body']['data_block']['dt_assoc']['item'][4]['dt_assoc']['item'][0];

        } else {

            $expiration_date = '';
            $autorenewal_status = '';

        }

        // get dns servers
        $api_xml = $this->getApiUrl('dns', $domain);
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]['body']['data_block']['dt_assoc']['item'][2] == 'Query Successful') {

            $dns_result = array();

            foreach ($array_results[0]['body']['data_block']['dt_assoc']['item'][4]['dt_assoc']['item'][0]['dt_array']['item'] AS $server_list) {

                $dns_result[] = $server_list['dt_assoc']['item'][0];

            }

            $dns_servers = $this->processDns($dns_result);

        } else {

            // if the API call failed assign empty values
            $dns_servers = '';

        }

        // get privacy status
        $api_xml = $this->getApiUrl('privacy', $domain);
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]['body']['data_block']['dt_assoc']['item'][2] == 'Query Successful') {

            // get privacy status
            $privacy_result = $array_results[0]['body']['data_block']['dt_assoc']['item'][4]['dt_assoc']['item'][2];
            $privacy_status = $this->processPrivacy($privacy_result);

        } else {

            $privacy_status = '';

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);

    }

    public function convertToArray($api_result)
    {
        $xml = simplexml_load_string($api_result);
        $json = json_encode((array($xml)), TRUE);
        return json_decode($json, TRUE);
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

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'disabled') {
            $privacy_status = '0';
        } else { // 'enabled'
            $privacy_status = '1';
        }
        return $privacy_status;
    }

} //@formatter:on
