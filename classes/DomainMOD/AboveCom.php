<?php
/**
 * /classes/DomainMOD/AboveCom.php
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

class AboveCom
{
    public $deeb;
    public $format;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->format = new Format();
        $this->log = new Log('class.abovecom');
    }

    public function getApiUrl($api_key, $command)
    {
        $base_url = 'https://www.above.com/registrar/api/query.html?key=' . $api_key;
        if ($command == 'domainlist') {
            return $base_url . '&query=my_domains&limit=1000';
        } else {
            return _('Unable to build API URL');
        }
    }

    public function apiCall($full_url)
    {
        $handle = HttpClient::init($full_url);
        $result = HttpClient::exec($handle, 'Above.com API');
        return $result;
    }

    public function getDomainList($api_key, $account_id)
    {
        $api_url = $this->getApiUrl($api_key, 'domainlist');
        $api_results = $this->apiCall($api_url);
        $array_results = RegistrarResponse::parseXml($api_results);

        // confirm that the api call was successful
        if (RegistrarResponse::text(RegistrarResponse::get($array_results, 0, '@attributes', 'code')) !== '100'
            || !RegistrarResponse::isXmlContainer($array_results, 0, 'domains')) {

            $log_message = 'Unable to get domain list';
            $log_extra = array('API Key' => $this->format->obfusc($api_key), 'Account ID' => $account_id);
            $this->log->error($log_message, $log_extra);

            // a failed call isn't an account with no domains, so the Domain Queue retries the list
            return array('', '');

        }

        // read every record first, so an unreadable one fails the whole list before anything is saved
        $records = RegistrarResponse::toXmlList(RegistrarResponse::get($array_results, 0, 'domains', 'r'));
        foreach ($records as $value) {

            if (RegistrarResponse::text(RegistrarResponse::get($value, '@attributes', 'domain')) === '') {

                $this->log->error('Unable to read a domain in the domain list');

                // retried on the next run, rather than finishing with domains missing
                return array('', '');

            }

        }

        $domain_list = array();
        $domain_count = 0;

        $pdo = $this->deeb->cnxx;
        $stmt = $pdo->prepare("
            INSERT INTO domain_queue_temp
            (account_id, domain, expiry_date, ns1, ns2, ns3, ns4, ns5, ns6, ns7, ns8, ns9, ns10, autorenew, privacy)
            VALUES
            (:account_id, :domain, :expiry_date, :ns1, :ns2, :ns3, :ns4, :ns5, :ns6, :ns7, :ns8, :ns9, :ns10,
             :autorenew, :privacy)");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);

        foreach ($records as $value) {

            // cached under the stored form (münchen.de), which getFullInfo() is later given
            $domain = Domain::normalizeOrKeep(RegistrarResponse::text(RegistrarResponse::get($value, '@attributes', 'domain')));
            $domain_list[] = $domain;
            $expiry_date = $this->processExpiry(RegistrarResponse::text(RegistrarResponse::get($value, 'expiry')));
            $autorenew = $this->processAutorenew(RegistrarResponse::text(RegistrarResponse::get($value, 'autorenew')));
            $privacy = $this->processPrivacy(RegistrarResponse::text(RegistrarResponse::get($value, 'privacy')));

            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->bindValue('expiry_date', $expiry_date, \PDO::PARAM_STR);
            // each domain's own name servers (an empty one is saved as empty, not carried over from the last domain)
            for ($i = 1; $i <= 10; $i++) {
                $stmt->bindValue('ns' . $i, RegistrarResponse::text(RegistrarResponse::get($value, 'dns' . $i)), \PDO::PARAM_STR);
            }
            $stmt->bindValue('autorenew', $autorenew, \PDO::PARAM_INT);
            $stmt->bindValue('privacy', $privacy, \PDO::PARAM_INT);
            $stmt->execute();

            $domain_count++;

        }

        return array($domain_count, $domain_list);
    }

    public function getFullInfo($account_id, $domain)
    {
        $pdo = $this->deeb->cnxx;

        $expiration_date = '';
        $dns_servers = array();
        $privacy_status = '';
        $autorenewal_status = '';

        $stmt = $pdo->prepare("
            SELECT id, expiry_date, ns1, ns2, ns3, ns4, ns5, ns6, ns7, ns8, ns9, ns10, autorenew, privacy
            FROM domain_queue_temp
            WHERE account_id = :account_id
              AND domain = :domain
            ORDER BY id ASC");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to get domain details';
            $log_extra = array('Domain' => $domain, 'Account ID' => $account_id);
            $this->log->error($log_message, $log_extra);

        } else {

            $expiration_date = $result->expiry_date;

            $dns_result = array();

            $dns_result[0] = $result->ns1;
            $dns_result[1] = $result->ns2;
            $dns_result[2] = $result->ns3;
            $dns_result[3] = $result->ns4;
            $dns_result[4] = $result->ns5;
            $dns_result[5] = $result->ns6;
            $dns_result[6] = $result->ns7;
            $dns_result[7] = $result->ns8;
            $dns_result[8] = $result->ns9;
            $dns_result[9] = $result->ns10;
            $dns_servers = $this->processDns($dns_result);

            $privacy_status = $result->privacy;

            $autorenewal_status = $result->autorenew;

            $stmt = $pdo->prepare("
                DELETE FROM domain_queue_temp
                WHERE id = :id");
            $stmt->bindValue('id', $result->id, \PDO::PARAM_INT);
            $stmt->execute();

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
        if ($privacy_result == 'on') {
            $privacy_status = '1';
        } else {
            $privacy_status = '0';
        }
        return $privacy_status;
    }

    public function processAutorenew($autorenewal_result)
    {
        if ($autorenewal_result == 'on') {
            $autorenewal_status = '1';
        } else { // result messages - 'do not renew', 'no renew option'
            $autorenewal_status = '0';
        }
        return $autorenewal_status;
    }

} //@formatter:on
