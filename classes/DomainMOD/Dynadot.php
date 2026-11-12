<?php
/**
 * /classes/DomainMOD/Dynadot.php
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

class Dynadot
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.dynadot');
    }

    public function getApiUrl($api_key, $command, $domain)
    {
        // registrar APIs take the punycode form of the domain (xn--mnchen-3ya.de for münchen.de)
        $domain = Domain::forApi($domain);
        $base_url = 'https://api.dynadot.com/api3.xml?key=' . $api_key . '&';
        if ($command == 'domainlist') {
            return $base_url . "command=list_domain";
        } elseif ($command == 'info') {
            return $base_url . "command=domain_info&domain=" . $domain;
        } else {
            return _('Unable to build API URL');
        }
    }

    public function apiCall($full_url)
    {
        $handle = HttpClient::init($full_url);
        $result = HttpClient::exec($handle, 'Dynadot API');
        return $result;
    }

    public function getDomainList($api_key)
    {
        $api_url = $this->getApiUrl($api_key, 'domainlist', '');
        $api_results = $this->apiCall($api_url);
        $array_results = RegistrarResponse::parseXml($api_results);

        // confirm that the api call was successful
        if (!(RegistrarResponse::get($array_results, 0, 'ListDomainInfoHeader', 'Status') === 'success'
              && RegistrarResponse::isXmlContainer($array_results, 0, 'ListDomainInfoContent', 'DomainInfoList'))) {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Key' => $this->format->obfusc($api_key));
            $this->log->error($log_message, $log_extra);

            // a failed call isn't an account with no domains, so the Domain Queue retries the list
            return array('', '');

        }

        $domain_list = array();
        $domain_count = 0;

        // DomainInfo may hold every Domain, or there may be one DomainInfo per Domain, so read both
        foreach (RegistrarResponse::toXmlList(RegistrarResponse::get($array_results, 0, 'ListDomainInfoContent', 'DomainInfoList', 'DomainInfo')) as $info) {

            // a DomainInfo without a Domain is an unreadable record (the check below fails the list)
            $domains = RegistrarResponse::get($info, 'Domain');
            foreach ($domains === null ? array(array()) : RegistrarResponse::toXmlList($domains) as $item) {

                $domain = RegistrarResponse::text(RegistrarResponse::get($item, 'Name'));
                if ($domain === '') {

                    $this->log->error('Unable to read a domain in the domain list');

                    // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                    return array('', '');

                }
                $domain_list[] = $domain;
                $domain_count++;

            }

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($api_key, 'info', $domain);
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if ($array_results[0]["DomainInfoHeader"]["Status"] == 'success') {

            // get expiration date
            $expiry_result = $array_results[0]["DomainInfoContent"]["Domain"]["Expiration"];
            $expiration_date = $this->processExpiry($expiry_result);

            // get dns servers
            $dns_result = $array_results[0]["DomainInfoContent"]["Domain"]["NameServerSettings"]["NameServers"]["NameServer"];
            $dns_servers = $this->processDns($dns_result);

            // get privacy status
            $privacy_result = $array_results[0]["DomainInfoContent"]["Domain"]["Privacy"];
            $privacy_status = $this->processPrivacy($privacy_result);

            // get auto renewal status
            $autorenewal_result = $array_results[0]["DomainInfoContent"]["Domain"]["RenewOption"];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'API Key' => $this->format->obfusc($api_key));
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

    public function processExpiry($expiry_result)
    {
        $expiry_formatted = substr($expiry_result, 0, -3);
        return gmdate("Y-m-d", $expiry_formatted);
    }

    public function processDns($dns_result)
    {
        $servers = array();

        foreach ($dns_result as $server) {

            $servers[] = $server['ServerName'];

        }

        if (!empty($servers)) {
            $dns_servers = array_filter($servers);
        } else {
            $dns_servers[0] = 'no.dns-servers.1';
            $dns_servers[1] = 'no.dns-servers.2';
        }
        return $dns_servers;
    }

    public function processPrivacy($privacy_result)
    {
        if ($privacy_result == 'full' || $privacy_result == 'partial') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'auto-renew') {
            $autorenewal_status = '1';
        } else { // result messages - 'do not renew', 'no renew option'
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
