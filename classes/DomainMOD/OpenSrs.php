<?php
/**
 * /classes/DomainMOD/OpenSrs.php
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

class OpenSrs
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.opensrs');
    }

    public function domainList()
    {
        $start_date = gmdate("Y-m-d", mktime(date("H") - 12, date("i"), date("s"), date("m"), date("d"), date("Y")));
        $end_date = gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + 19));

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
    }

    public function domainInfo($domain)
    {
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
    }

    public function domainPrivacy($domain)
    {
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
    }

    public function apiCall($xml, $account_username, $api_key)
    {
        $handle = curl_init('https://rr-n1-tor.opensrs.net:55443'); // Production Environment
        // $handle = curl_init('https://horizon.opensrs.net:55443'); // Test environment
        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            'Content-Type:text/xml',
            'X-Username:' . $account_username,
            'X-Signature:' . md5(md5($xml . $api_key) .  $api_key)));
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($handle);
        return $result;
    }

    public function getDomainList($account_username, $api_key)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_xml = $this->domainList();
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $api_call_status = $this->apiStatus($api_results);

        if ($api_call_status == '1') {

            foreach (preg_split("/((\r?\n)|(\r\n?))/", $api_results) as $xml_line) {

                if (preg_match('/<item key="name">(.*)<\/item>/', $xml_line, $match)) {
                    $domain_list[] = $match[1];
                    $domain_count++;
                }

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

        // get the partial domain details (expiration date, dns servers, and auto renewal status)
        $api_xml = $this->domainInfo($domain);
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $api_call_status = $this->apiStatus($api_results);

        if ($api_call_status == '1') {

            $dns_result = array();

            foreach (preg_split("/((\r?\n)|(\r\n?))/", $api_results) as $xml_line) {

                // get expiration date
                if (preg_match('/<item key="expiredate">(.*)<\/item>/', $xml_line, $match)) {
                    $expiration_date = $this->processExpiry($match[1]);
                }

                // get dns servers
                if (preg_match('/<item key="name">(.*)<\/item>/', $xml_line, $match)) {
                    $dns_result[] = $match[1];
                }

                // get auto renewal status
                if (preg_match('/<item key="auto_renew">(.*)<\/item>/', $xml_line, $match)) {
                    $autorenewal_status = $this->processAutorenew($match[1]);
                }

            }
            $dns_servers = $this->processDns($dns_result);

        } else {

            $log_message = 'Unable to get partial domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

        }

        // get the privacy status
        $api_xml = $this->domainPrivacy($domain);
        $api_results = $this->apiCall($api_xml, $account_username, $api_key);
        $api_call_status = $this->apiStatus($api_results);

        if ($api_call_status == '1') {

            foreach (preg_split("/((\r?\n)|(\r\n?))/", $api_results) as $xml_line) {

                // get privacy status
                if (preg_match('/<item key="state">(.*)<\/item>/', $xml_line, $match)) {
                    $privacy_status = $this->processPrivacy($match[1]);
                }

            }

        } else {

            $log_message = 'Unable to get privacy status';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function apiStatus($api_results)
    {
        $status = '';
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $api_results) as $xml_line) {
            if (preg_match('/<item key="response_code">200<\/item>/', $xml_line)) {
                $status = '1';
            }
        }
        return $status;
    }

    public function processExpiry($expiry_result)
    {
        $unix_expiration_date = strtotime($expiry_result);
        return gmdate("Y-m-d", $unix_expiration_date);
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
        if ($privacy_result == 'enabled') {
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
