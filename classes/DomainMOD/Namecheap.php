<?php
/**
 * /classes/DomainMOD/Namecheap.php
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

class Namecheap
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.namecheap');
    }

    public function getApiUrl($api_key, $command, $domain, $account_username, $api_ip_address, $page_number)
    {
        // registrar APIs take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $domain = Domain::forApi($domain);
        $base_url = 'https://api.namecheap.com/xml.response?&ApiUser=' . $account_username . '&ApiKey=' . $api_key . '&UserName=' . $account_username;
        if ($command == 'domainlist') {
            return $base_url . '&Command=namecheap.domains.getList&ClientIp=' . $api_ip_address . '&PageSize=100' . '&Page=' . $page_number;
        } elseif ($command == 'info') {
            return $base_url . '&Command=namecheap.domains.getinfo&ClientIp=' . $api_ip_address . '&DomainName=' . $domain;
        } elseif ($command == 'autorenewal') {
            return $base_url . '&Command=namecheap.domains.getList&SearchTerm=' . $domain . '&ClientIp=' . $api_ip_address;
        } else {
            return 'Unable to build API URL';
        }
    }

    public function apiCall($full_url)
    {
        $handle = HttpClient::init($full_url);
        $result = HttpClient::exec($handle, 'Namecheap API');
        return $result;
    }

    public function getDomainList($account_username, $api_key, $api_ip_address)
    {
        $page_size = 100; // matches PageSize in getApiUrl()
        $domain_list = array();
        $domain_count = 0;
        $page_count = 1;

        while (true) {

            $api_url = $this->getApiUrl($api_key, 'domainlist', '', $account_username, $api_ip_address, $page_count);
            $api_results = $this->apiCall($api_url);
            $array_results = RegistrarResponse::parseXml($api_results);

            // confirm that the api call was successful (a failure on any page fails the whole list, so the Domain
            // Queue retries it rather than finishing with only the pages read so far)
            if (RegistrarResponse::get($array_results, 0, '@attributes', 'Status') !== 'OK'
                || !is_array(RegistrarResponse::get($array_results, 0, 'CommandResponse', 'DomainGetListResult'))) {

                $log_message = 'Unable to get domain list';
                $log_extra = array('Username' => $account_username, 'API Key' => $this->format->obfusc($api_key), 'IP Address' => $api_ip_address, 'Page' => $page_count);
                $this->log->error($log_message, $log_extra);

                return array('', '');

            }

            $page = RegistrarResponse::toXmlList(RegistrarResponse::get($array_results, 0, 'CommandResponse', 'DomainGetListResult', 'Domain'));

            foreach ($page as $domain) {

                $name = RegistrarResponse::text(RegistrarResponse::get($domain, '@attributes', 'Name'));
                if ($name === '') {

                    $this->log->error('Unable to read a domain in the domain list');

                    // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                    return array('', '');

                }
                $domain_list[] = $name;
                $domain_count++;

            }

            // a short (or empty) page is the last one
            if (count($page) < $page_size) break;

            $page_count++;

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $api_key, $api_ip_address, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($api_key, 'info', $domain, $account_username, $api_ip_address, '');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]['@attributes']['Status'] == "OK") {

            // get expiration date
            $expiration_date = date('Y-m-d', strtotime($array_results[0]['CommandResponse']['DomainGetInfoResult']['DomainDetails']['ExpiredDate']));

            // get dns servers
            $dns_result = $array_results[0]['CommandResponse']['DomainGetInfoResult']['DnsDetails']['Nameserver'];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_result = $array_results[0]['CommandResponse']['DomainGetInfoResult']['Whoisguard']['@attributes']['Enabled'];
            $privacy_status = $this->processPrivacy($privacy_result);

        } else {

            $log_message = 'Unable to get partial domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API Key' => $this->format->obfusc($api_key), 'IP Address' => $api_ip_address);
            $this->log->error($log_message, $log_extra);

        }

        // since the auto renewal status can only be retrieved through Namecheap's getList command, I have to re-run
        // a new command just for this
        $api_url = $this->getApiUrl($api_key, 'autorenewal', $domain, $account_username, $api_ip_address, '');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]['@attributes']['Status'] == "OK") {

            // stays empty (manual renewal) if the domain isn't in the list
            $autorenewal_result = '';

            foreach ($array_results[0]['CommandResponse']['DomainGetListResult']['Domain'] as $value) {

                if (Domain::sameDomain($value['Name'] ?? '', $domain)) {

                    $autorenewal_result = $value['AutoRenew'];

                }

            }

            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get auto renewal status';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'API Key' => $this->format->obfusc($api_key), 'IP Address' => $api_ip_address);
            $this->log->error($log_message, $log_extra);

        }

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    public function convertToArray($api_result)
    {
        $xml = simplexml_load_string($api_result);
        $json = json_encode((array($xml)), true);
        return json_decode($json, true);
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
        if ($privacy_result == 'True') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'true') {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
