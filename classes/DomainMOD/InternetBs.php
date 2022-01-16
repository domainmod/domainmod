<?php
/**
 * /classes/DomainMOD/InternetBs.php
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

class InternetBs
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.internetbs');
    }

    public function getApiUrl($api_key, $api_secret, $command, $domain)
    {
        $base_url = 'https://api.internet.bs/';
        if ($command == 'domainlist') {
            return $base_url . 'domain/List?apiKey=' . $api_key . '&password=' . $api_secret . '&ResponseFormat=JSON';
        } elseif ($command == 'info') {
            return $base_url . 'domain/Info?apiKey=' . $api_key . '&password=' . $api_secret . '&ResponseFormat=JSON&Domain=' . $domain;
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

    public function getDomainList($api_key, $api_secret)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_url = $this->getApiUrl($api_key, $api_secret, 'domainlist', '');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['status'] == 'SUCCESS') {

            foreach ($array_results['domain'] as $domain) {

                $domain_list[] = $domain;
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Key' => $this->format->obfusc($api_key), 'API Secret' => $this->format->obfusc($api_secret));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $api_secret, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($api_key, $api_secret, 'info', $domain);
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results['status'] == 'SUCCESS') {

            // get expiration date
            $expiry_result = $array_results['expirationdate'];
            $expiration_date = $this->processExpiry($expiry_result);

            // get dns servers
            $dns_result = $array_results['nameserver'];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_result = $array_results['privatewhois'];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = $array_results['autorenew'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'API Key' => $this->format->obfusc($api_key), 'API Secret' => $this->format->obfusc($api_secret));
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
        return str_replace('/', '-', $expiry_result);
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
        if ($privacy_result == 'FULL' || $privacy_result == 'PARTIAL') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'YES') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
