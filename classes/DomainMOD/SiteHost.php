<?php
/**
 * /classes/DomainMOD/SiteHost.php
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
//@formatter:off
namespace DomainMOD;

class SiteHost
{
    public $format;
    public $log;

    // SiteHost returns its domain list in pages, so this is how many domains to ask for at a time, and a cap on how many
    // pages to read (10,000 domains) in case the list of pages never ends
    const PAGE_SIZE = 100;
    const MAX_PAGES = 100;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.sitehost');
    }

    public function getApiUrl($api_key, $client_id, $command, $domain, $page_number = 1)
    {
        $base_url = 'https://api.sitehost.nz/1.5/srs/';
        $parameters = array('apikey' => $api_key, 'client_id' => $client_id);

        if ($command == 'domainlist') {
            $parameters['filters[page_size]'] = self::PAGE_SIZE;
            $parameters['filters[page_number]'] = $page_number;
            return $base_url . 'list_domains.json?' . http_build_query($parameters);
        } elseif ($command == 'info') {
            // the domain list has everything except the DNS servers, including the privacy status that get_domain
            // doesn't return. The domain filter is a search, so the results are matched against the domain below
            $parameters['filters[domain]'] = $domain;
            $parameters['filters[page_size]'] = self::PAGE_SIZE;
            $parameters['filters[page_number]'] = $page_number;
            return $base_url . 'list_domains.json?' . http_build_query($parameters);
        } elseif ($command == 'dnsservers') {
            $parameters['domain'] = $domain;
            return $base_url . 'list_name_servers.json?' . http_build_query($parameters);
        } else {
            return _('Unable to build API URL');
        }
    }

    public function apiCall($full_url)
    {
        $handle = HttpClient::init($full_url);
        $result = HttpClient::exec($handle, 'SiteHost API');
        return $result;
    }

    public function getDomainList($api_key, $client_id)
    {
        $domain_list = array();
        $domain_count = 0;
        $page_number = 1;
        $total_pages = 1;

        while ($page_number <= $total_pages) {

            if ($page_number > self::MAX_PAGES) {

                $this->logFailure('Unable to get domain list (the list of pages did not end)', null, $api_key, $client_id);
                return array('', '');

            }

            $api_url = $this->getApiUrl($api_key, $client_id, 'domainlist', '', $page_number);
            $api_results = $this->apiCall($api_url);
            $array_results = RegistrarResponse::parseJson($api_results);

            // confirm that the api call was successful
            if (!(RegistrarResponse::get($array_results, 'status') === true && RegistrarResponse::isJsonListAt($api_results, 'return', 'data'))) {

                $this->logFailure('Unable to get domain list', $array_results, $api_key, $client_id);

                // a failed call isn't an account with no domains, so the Domain Queue retries the list
                return array('', '');

            }

            foreach (RegistrarResponse::toList(RegistrarResponse::get($array_results, 'return', 'data')) as $item) {

                $domain = RegistrarResponse::text(RegistrarResponse::get($item, 'domain'));
                if ($domain === '') {

                    $this->log->error('Unable to read a domain in the domain list');

                    // one unreadable record fails the whole list (it's retried), rather than finishing with domains missing
                    return array('', '');

                }
                $domain_list[] = $domain;
                $domain_count++;

            }

            // without the page count, a list that's only partly read could look complete
            $total_pages = $this->readCount(RegistrarResponse::get($array_results, 'return', 'total_pages'));
            if ($total_pages === null) {

                $this->logFailure('Unable to get domain list (no page count)', $array_results, $api_key, $client_id);
                return array('', '');

            }
            $page_number++;

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($api_key, $client_id, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        // find the domain in the search results, which can run to more than one page
        $domain_details = null;
        $array_results = null;
        $page_number = 1;
        $total_pages = 1;

        while ($domain_details === null && $page_number <= $total_pages && $page_number <= self::MAX_PAGES) {

            $api_url = $this->getApiUrl($api_key, $client_id, 'info', $domain, $page_number);
            $api_results = $this->apiCall($api_url);
            $array_results = RegistrarResponse::parseJson($api_results);

            // confirm that the api call was successful
            if (!(RegistrarResponse::get($array_results, 'status') === true && RegistrarResponse::isJsonListAt($api_results, 'return', 'data'))) {
                break;
            }

            foreach (RegistrarResponse::toList(RegistrarResponse::get($array_results, 'return', 'data')) as $item) {

                if (strtolower(RegistrarResponse::text(RegistrarResponse::get($item, 'domain'))) === strtolower($domain)) {
                    $domain_details = $item;
                    break;
                }

            }

            $total_pages = (int) $this->readCount(RegistrarResponse::get($array_results, 'return', 'total_pages'));
            $page_number++;

        }

        if ($domain_details === null) {

            $this->logFailure('Unable to get domain details', $array_results, $api_key, $client_id, $domain);
            return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);

        }

        // the privacy and auto renewal settings must be readable, or the domain would be imported with them turned off
        $privacy_result = RegistrarResponse::text(RegistrarResponse::get($domain_details, 'private'));
        $autorenewal_result = RegistrarResponse::text(RegistrarResponse::get($domain_details, 'autorenew_term'));
        if (!in_array($privacy_result, array('0', '1'), true) || $this->readCount($autorenewal_result) === null) {

            $this->logFailure('Unable to get domain details (unreadable privacy or auto renewal status)', null, $api_key, $client_id, $domain);
            return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);

        }

        // get dns servers. A failed call leaves the details empty, so the Domain Queue tries the domain again later
        // rather than importing it with placeholder DNS servers
        $dns_result = $this->getDnsServers($api_key, $client_id, $domain);
        if ($dns_result === null) {
            return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
        }
        $dns_servers = $this->processDns($dns_result);

        // get expiration date
        $expiration_date = $this->processExpiry(RegistrarResponse::text(RegistrarResponse::get($domain_details, 'datebilleduntil')));

        // get privacy status
        $privacy_status = $this->processPrivacy($privacy_result);

        // get auto renewal status
        $autorenewal_status = $this->processAutorenew($autorenewal_result);

        return array($expiration_date, $dns_servers, $privacy_status, $autorenewal_status);
    }

    // The domain's DNS servers, or null if the call failed
    public function getDnsServers($api_key, $client_id, $domain)
    {
        $dns_servers = array();

        $api_url = $this->getApiUrl($api_key, $client_id, 'dnsservers', $domain);
        $api_results = $this->apiCall($api_url);
        $array_results = RegistrarResponse::parseJson($api_results);

        // confirm that the api call was successful
        if (RegistrarResponse::get($array_results, 'status') === true && RegistrarResponse::isJsonListAt($api_results, 'return')) {

            foreach (RegistrarResponse::toList(RegistrarResponse::get($array_results, 'return')) as $item) {

                $dns_server = RegistrarResponse::text(RegistrarResponse::get($item, 'name'));
                if ($dns_server === '') {

                    // an unreadable record fails the call, rather than the domain being imported with DNS servers missing
                    $this->logFailure('Unable to read a DNS server', null, $api_key, $client_id, $domain);
                    return null;

                }
                $dns_servers[] = $dns_server;

            }

        } else {

            $this->logFailure('Unable to get DNS servers', $array_results, $api_key, $client_id, $domain);
            return null;

        }

        return $dns_servers;
    }

    public function processExpiry($expiry_result)
    {
        // SiteHost uses 0000-00-00 00:00:00 for a date that isn't set
        if ($expiry_result === '' || str_starts_with($expiry_result, '0000-00-00')) {
            return '';
        }
        return substr($expiry_result, 0, 10);
    }

    public function processDns($dns_result)
    {
        $dns_servers = array();
        if (!empty(array_filter($dns_result))) {
            $dns_servers = array_values(array_filter($dns_result));
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
        // autorenew_term is how many years each auto renewal is for, and 0 when auto renewal is off
        if ((int) $autorenewal_result > 0) {
            $autorenewal_status = '1';
        } else {
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

    // A whole number from the API (SiteHost returns them as strings), or null if it isn't one
    protected function readCount($value)
    {
        $value = RegistrarResponse::text($value);
        return preg_match('/^[0-9]+$/', $value) ? (int) $value : null;
    }

    protected function logFailure($log_message, $array_results, $api_key, $client_id, $domain = '')
    {
        $log_extra = array();
        if ($domain !== '') $log_extra['Domain'] = $domain;
        $log_extra['API Key'] = $this->format->obfusc($api_key);
        $log_extra['Client ID'] = $client_id;
        $api_message = RegistrarResponse::text(RegistrarResponse::get($array_results, 'msg'));
        if ($api_message !== '' && $api_key !== '') {
            // in case an error message ever echoes the request back
            $api_message = str_replace(array($api_key, rawurlencode($api_key), urlencode($api_key)), '[API key]', $api_message);
        }
        if ($api_message !== '') $log_extra['Message'] = $api_message;
        $this->log->error($log_message, $log_extra);
    }

} //@formatter:on
