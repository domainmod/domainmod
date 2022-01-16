<?php
/**
 * /classes/DomainMOD/Enom.php
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

class Enom
{
    public $format;
    public $log;

    public function __construct()
    {
        $this->format = new Format();
        $this->log = new Log('class.enom');
    }

    public function getApiUrl($account_username, $account_password, $domain, $command)
    {
        $domainclass = new Domain();
        $domain_part = $domainclass->getDomainPart($domain);
        $tld = $domainclass->getTld($domain);

        $base_url = 'https://reseller.enom.com/interface.asp?command=';
        if ($command == 'domainlist') {
            return $base_url . 'AdvancedDomainSearch&uid=' . $account_username . '&pw=' . $account_password . '&ResponseType=XML';
        } elseif ($command == 'info') {
            return $base_url . 'GetDomainInfo&uid=' . $account_username . '&pw=' . $account_password . '&sld=' . $domain_part . '&tld=' . $tld . '&ResponseType=XML';
        } elseif ($command == 'privacy') {
            return $base_url . 'GetWPPSInfo&uid=' . $account_username . '&pw=' . $account_password . '&sld=' . $domain_part . '&tld=' . $tld . '&ResponseType=XML';
        } elseif ($command == 'autorenewal') {
            return $base_url . 'GetRenew&uid=' . $account_username . '&pw=' . $account_password . '&sld=' . $domain_part . '&tld=' . $tld . '&ResponseType=XML';
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

    public function getDomainList($account_username, $account_password)
    {
        $domain_list = array();
        $domain_count = 0;

        $api_url = $this->getApiUrl($account_username, $account_password, '', 'domainlist');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results[0]['DomainSearch']['Domains']['Domain'][0]['SLD'])) {

            foreach ($array_results[0]['DomainSearch']['Domains']['Domain'] as $domain) {

                $domain_list[] = $domain['SLD'] . '.' . $domain['TLD'];
                $domain_count++;

            }

        } else {

            $log_message = 'Unable to get domain list';
            $log_extra = array('Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
            $this->log->error($log_message, $log_extra);

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_username, $account_password, $domain)
    {
        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $api_url = $this->getApiUrl($account_username, $account_password, $domain, 'info');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results[0]['GetDomainInfo']['status']['expiration']) && isset($array_results[0]["GetDomainInfo"]["services"]["entry"][0]["configuration"]["dns"])) {

            // get expiration date
            $expiry_result = $array_results[0]['GetDomainInfo']['status']['expiration'];
            $expiration_date = $this->processExpiry($expiry_result);

            // get dns servers
            $dns_result = $array_results[0]["GetDomainInfo"]["services"]["entry"][0]["configuration"]["dns"];
            $dns_servers = $this->processDns($dns_result);

        } else {

            $log_message = 'Unable to get partial domain details';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
            $this->log->error($log_message, $log_extra);

        }

        // get privacy status
        $api_url = $this->getApiUrl($account_username, $account_password, $domain, 'privacy');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results[0]['GetWPPSInfo']['WPPSEnabled'])) {

            $privacy_result = $array_results[0]['GetWPPSInfo']['WPPSEnabled'];
            $privacy_status = $this->processPrivacy($privacy_result);

        } else {

            $log_message = 'Unable to get privacy status';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
            $this->log->error($log_message, $log_extra);

        }

        // get auto renewal status
        $api_url = $this->getApiUrl($account_username, $account_password, $domain, 'autorenewal');
        $api_results = $this->apiCall($api_url);
        $array_results = $this->convertToArray($api_results);

        // confirm that the api call was successful
        if (isset($array_results[0]["auto-renew"])) {

            $autorenewal_result = $array_results[0]["auto-renew"];
            $autorenewal_status = $this->processAutorenew($autorenewal_result);

        } else {

            $log_message = 'Unable to get auto renewal status';
            $log_extra = array('Domain' => $domain, 'Username' => $account_username, 'Password' => $this->format->obfusc($account_password));
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
