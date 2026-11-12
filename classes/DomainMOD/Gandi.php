<?php
/**
 * /classes/DomainMOD/Gandi.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

class Gandi
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.gandi');
    }

    public function getApiUrl($command, $domain)
    {
        // registrar APIs take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $domain = Domain::forApi($domain);
        $base_url = 'https://api.gandi.net/v5/domain/';
        if ($command == 'domainlist') {
            return $base_url . 'domains';
        } elseif ($command == 'info') {
            return $base_url . 'domains/' . $domain;
        } else {
            return 'Unable to build API URL';
        }
    }

    /*
     * Gandi's personal access tokens use "Authorization: Bearer"; its older API keys, now deprecated, use
     * "Authorization: Apikey". The saved key could be either, so try it as a token first, and only if Gandi refuses it
     * (401 or 403) try it as an API key
     */
    public function apiCall($full_url, $api_key)
    {
        list($status, $result) = $this->request($full_url, 'Bearer ' . $api_key);

        if ($status === 401 || $status === 403) {
            list($status, $result) = $this->request($full_url, 'Apikey ' . $api_key);
        }

        return $result;
    }

    // One GET request: array(HTTP status, body or false)
    public function request($full_url, $authorization)
    {
        $handle = HttpClient::init($full_url);
        curl_setopt($handle, CURLOPT_ENCODING, '');
        curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('authorization: ' . $authorization));
        $result = HttpClient::exec($handle, 'Gandi API');
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return array($status, $result);
    }

    public function getDomainList($api_key)
    {
        $api_url = $this->getApiUrl('domainlist', '');
        $api_results = $this->apiCall($api_url, $api_key);
        $array_results = RegistrarResponse::parseJson($api_results);

        // confirm that the api call was successful
        if (!(RegistrarResponse::isJsonList($api_results))) {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

            // a failed call isn't an account with no domains, so the Domain Queue retries the list
            return array('', '');

        }

        $domain_list = array();
        $domain_count = 0;

        foreach (RegistrarResponse::toList($array_results) as $item) {

            $domain = RegistrarResponse::text(RegistrarResponse::get($item, 'fqdn'));
            if ($domain === '') {

                $this->log->error('Unable to read a domain in the domain list');

                // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                return array('', '');

            }
            $domain_list[] = $domain;
            $domain_count++;

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl('info', $domain);
        $api_results = $this->apiCall($api_url, $api_key);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results['dates']['registry_ends_at'])) {

            // get expiration date
            $expiration_date = substr($array_results['dates']['registry_ends_at'], 0, 10);

            // Gandi doesn't return the privacy status, so all domains get zero to public by default
            $privacy_status = '0';

            // get auto renewal status
            $autorenewal_result = (string) $array_results['autorenew']['enabled'];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

            // get dns servers
            $dns_result = $array_results['nameservers'];
            $dns_servers = $this->processDns($dns_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain);
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
