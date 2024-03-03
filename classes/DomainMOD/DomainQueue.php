<?php
/**
 * /classes/DomainMOD/DomainQueue.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

class DomainQueue
{
    public $api;
    public $deeb;
    public $domain;
    public $log;
    public $maint;
    public $queryB;
    public $system;
    public $time;

    public function __construct()
    {
        $this->api = new Api();
        $this->deeb = Database::getInstance();
        $this->domain = new Domain();
        $this->log = new Log('class.domainqueue');
        $this->maint = new Maintenance();
        $this->queryB = new QueryBuild();
        $this->system = new System();
        $this->time = new Time();
    }

    public function processQueueList()
    {
        // process domain lists in the queue
        $result = $this->getQueueList();

        $this->markProcessingList();

        if (!$result) {

            $log_message = 'No Domain Queue Lists to process';
            $this->log->info($log_message);

        }  else {

            $log_message = '[START] Processing Domain Queue Lists';
            $this->log->notice($log_message);

            foreach ($result as $row) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $row->account_id);

                } elseif ($row->api_registrar_name == 'Cloudflare') {

                    $registrar = new Cloudflare();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $this->api->getAccountId($row->account_id);
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_id, $api_key);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $this->api->getAccountId($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $account_id);

                } elseif ($row->api_registrar_name == 'DreamHost') {

                    $registrar = new DreamHost();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $row->account_id);

                } elseif ($row->api_registrar_name == 'Dynadot') {

                    $registrar = new Dynadot();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

                } elseif ($row->api_registrar_name == 'eNom') {

                    $registrar = new Enom();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'Fabulous') {

                    $registrar = new Fabulous();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'Freenom') {

                    $registrar = new Freenom();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'Gandi') {

                    $registrar = new Gandi();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

                } elseif ($row->api_registrar_name == 'GoDaddy') {

                    $registrar = new GoDaddy();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

                } elseif ($row->api_registrar_name == 'Internet.bs') {

                    $registrar = new InternetBs();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

                } elseif ($row->api_registrar_name == 'Name.com') {

                    $registrar = new NameCom();
                    list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

                } elseif ($row->api_registrar_name == 'NameBright') {

                    $registrar = new NameBright();
                    list($account_username, $api_app_name, $api_secret) = $this->api->getUserAppSecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_app_name, $api_secret);

                } elseif ($row->api_registrar_name == 'Namecheap') {

                    $registrar = new Namecheap();
                    list($account_username, $api_key, $api_ip_address) = $this->api->getUserKeyIp($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key, $api_ip_address);

                } elseif ($row->api_registrar_name == 'NameSilo') {

                    $registrar = new NameSilo();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

                } elseif ($row->api_registrar_name == 'OpenSRS') {

                    $registrar = new OpenSrs();
                    list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

                } elseif ($row->api_registrar_name == 'Porkbun') {

                    $registrar = new Porkbun();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

                } else {

                    return _('Invalid Domain Registrar');

                }

                // make sure the domain list was successfully retrieved
                if ($domain_count != '' && $domain_list != '') {

                    // update the domain count
                    $this->updateDomainCount($row->id, $domain_count);

                    foreach ($domain_list as $domain) {

                        $this->importToDomainQueue($row->api_registrar_id, $domain, $row->owner_id, $row->registrar_id, $row->account_id, $row->created_by);

                    }

                    $this->markFinishedList($row->id);

                } else {

                    $this->markNotProcessingList($row->id);

                }

            }

            $log_message = '[END] Processing Domain Queue Lists';
            $this->log->notice($log_message);

        }

        $this->copyToHistoryList();

        return _('Domain List Queue Processed') . '<BR>';
    }

    public function processQueueDomain()
    {
        // process domains in the queue
        $result = $this->getQueueDomain();

        $this->markProcessingDomain();

        if (!$result) {

            $log_message = 'No domains in the Domain Queue to process';
            $this->log->info($log_message);

        } else {

            $log_message = '[START] Processing domains in the Domain Queue';
            $this->log->notice($log_message);

            foreach ($result as $row) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom();
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($row->account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'Cloudflare') {

                    $registrar = new Cloudflare();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $this->api->getAccountId($row->account_id);
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_id, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $this->api->getAccountId($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'DreamHost') {

                    $registrar = new DreamHost();
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($row->account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'Dynadot') {

                    $registrar = new Dynadot();
                    $api_key = $this->api->getKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'eNom') {

                    $registrar = new Enom();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'Fabulous') {

                    $registrar = new Fabulous();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'Freenom') {

                    $registrar = new Freenom();
                    list($account_username, $account_password) = $this->api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'Gandi') {

                    $registrar = new Gandi();
                    $api_key = $this->api->getKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'GoDaddy') {

                    $registrar = new GoDaddy();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Internet.bs') {

                    $registrar = new InternetBs();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Name.com') {

                    $registrar = new NameCom();
                    list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'NameBright') {

                    $registrar = new NameBright();
                    list($account_username, $api_app_name, $api_secret) = $this->api->getUserAppSecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_app_name, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Namecheap') {

                    $registrar = new Namecheap();
                    list($account_username, $api_key, $api_ip_address) = $this->api->getUserKeyIp($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $api_ip_address, $row->domain);

                } elseif ($row->api_registrar_name == 'NameSilo') {

                    $registrar = new NameSilo();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_status, $expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'OpenSRS') {

                    $registrar = new OpenSrs();
                    list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'Porkbun') {

                    $registrar = new Porkbun();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'ResellerClub') {

                    $registrar = new ResellerClub();
                    list($reseller_id, $api_key) = $this->api->getResellerIdKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($reseller_id, $api_key, $row->domain);

                } else {

                    return _('Invalid Domain Registrar');

                }

                // make sure the domain details were successfully retrieved
                if ($expiration_date != '' && $expiration_date != '0000-00-00' && $expiration_date != '1970-01-01'
                    && $dns_servers != '' && $privacy_status != '' && $autorenew_status != '') {

                    $created_by = $row->created_by;
                    list($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status)
                        = $this->updateDomain($row->id, $row->domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by);

                    // only process the domain if $ready_to_import = 1 (ie. all the information is valid)
                    if ($ready_to_import == '1') {

                        $new_domain_id = $this->importToMainDb($row->id);

                        // updates queue with the new domain id
                        $this->updateNewDomainId($row->id, $new_domain_id);

                        // markFinishedDomain confirms that the domain was added to the main domain database before marketing it as finished
                        $this->markFinishedDomain($row->id, $row->domain, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status);

                    }

                } else {

                    if ($domain_status == strtolower(_('Invalid'))) {

                        $this->markInvalidDomain($row->id);

                    }

                    $this->markNotProcessingDomain($row->id);

                }

            }

            $log_message = '[END] Processing domains in the Domain Queue';
            $this->log->notice($log_message);

        }

        $this->copyToHistoryDomain();

        return _('Domain Queue Processed') . '<BR>';
    }

    public function getQueueList()
    {
        return $this->deeb->cnxx->query("
            SELECT dql.id, dql.api_registrar_id, dql.owner_id, dql.registrar_id, dql.account_id, dql.created_by,
                ar.name AS api_registrar_name
            FROM domain_queue_list AS dql, api_registrars AS ar
            WHERE dql.api_registrar_id = ar.id
              AND dql.processing = '0'
              AND dql.ready_to_import = '0'
              AND dql.finished = '0'
              AND dql.copied_to_history = '0'
            ORDER BY dql.insert_time DESC")->fetchAll();
    }

    public function getQueueDomain()
    {
        return $this->deeb->cnxx->query("
            SELECT dq.id, dq.api_registrar_id, dq.domain, dq.account_id, dq.created_by, ar.name AS api_registrar_name
            FROM domain_queue AS dq, api_registrars AS ar
            WHERE dq.api_registrar_id = ar.id
              AND dq.processing = '0'
              AND dq.ready_to_import = '0'
              AND dq.finished = '0'
              AND dq.copied_to_history = '0'
              AND dq.already_in_domains = '0'
              AND dq.already_in_queue = '0'")->fetchAll();
    }

    public function markProcessingList()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue_list
            SET processing = '1'
            WHERE processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'");
    }

    public function updateDomainCount($list_id, $domain_count)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET domain_count = :domain_count
            WHERE id = :id");
        $stmt->bindValue('domain_count', $domain_count, \PDO::PARAM_INT);
        $stmt->bindValue('id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markProcessingDomain()
    {
        $this->deeb->cnxx->query("
            UPDATE domain_queue
            SET processing = '1'
            WHERE processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'
              AND already_in_domains = '0'
              AND already_in_queue = '0'");
    }

    public function updateDomain($queue_domain_id, $domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $this->updateExpirationDate($queue_domain_id, $expiration_date);
        $dns_id = $this->updateDnsServers($queue_domain_id, $dns_servers, $created_by);
        $ip_id = $this->updateIp($queue_domain_id, $domain, $created_by);
        $cat_id = $this->updateCategory($queue_domain_id, $created_by);
        $hosting_id = $this->updateHosting($queue_domain_id, $created_by);
        $this->updatePrivacy($queue_domain_id, $privacy_status);
        $this->updateRenewStatus($queue_domain_id, $autorenew_status);

        // don't mark the domain as ready to import if the information isn't valid
        if ($expiration_date != '0000-00-00' && $dns_id != '0' && $dns_id != '' && $ip_id != '0' && $ip_id != ''
            && $cat_id != '0' && $cat_id != '' && $hosting_id != '0' && $hosting_id != '' && $privacy_status != ''
            && $autorenew_status != '') {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET ready_to_import = '1'
                WHERE id = :queue_domain_id");
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

            $ready_to_import = '1';

        } else {

            $ready_to_import = '0';

        }

        return array($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status);
    }

    public function updateExpirationDate($queue_domain_id, $expiration_date)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET expiry_date = :expiration_date
            WHERE id = :queue_domain_id");
        $stmt->bindValue('expiration_date', $expiration_date, \PDO::PARAM_STR);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateDnsServers($queue_domain_id, $dns_servers, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $has_match = '';

        // lower case the DNS servers for accurate matching
        $lower_value = array();
        foreach ($dns_servers as $value) {
            $lower_value[] = strtolower($value);
        }
        $dns_servers = $lower_value;

        $result = $pdo->query("
            SELECT id, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $existing_dns = array($row->dns1, $row->dns2, $row->dns3, $row->dns4, $row->dns5, $row->dns6, $row->dns7,
                    $row->dns8, $row->dns9, $row->dns10);
                $filtered_dns = array_filter($existing_dns);

                // lower case the DNS servers for accurate matching
                $lower_value = array();
                foreach ($filtered_dns as $value) {
                    $lower_value[] = strtolower($value);
                }
                $filtered_dns = $lower_value;

                // If there's a match use it
                if (array_count_values($filtered_dns) == array_count_values($dns_servers)) {

                    $new_dns_id = $row->id;
                    $has_match = '1';

                }

            }

        }

        // If the DNS Profile doesn't exist create a new one
        if ($has_match != '1') {

            $new_servers = array();
            $count = 0;

            // Make sure DNS servers were returned
            foreach ($dns_servers as $server) {

                $new_servers[$count++] = strtolower($server);

            }

            $clean_servers = array_filter($new_servers);
            $number_of_servers = count($clean_servers);

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO dns
                (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, notes, number_of_servers,
                 creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :new_servers0, :new_servers1, :new_servers2, :new_servers3, :new_servers4,
                 :new_servers5, :new_servers6, :new_servers7, :new_servers8, :new_servers9, :notes, :number_of_servers,
                 :creation_type_id, :created_by, :insert_time)");

            if (!isset($new_servers[0])) $new_servers[0] = '';
            if (!isset($new_servers[1])) $new_servers[1] = '';
            if (!isset($new_servers[2])) $new_servers[2] = '';
            if (!isset($new_servers[3])) $new_servers[3] = '';
            if (!isset($new_servers[4])) $new_servers[4] = '';
            if (!isset($new_servers[5])) $new_servers[5] = '';
            if (!isset($new_servers[6])) $new_servers[6] = '';
            if (!isset($new_servers[7])) $new_servers[7] = '';
            if (!isset($new_servers[8])) $new_servers[8] = '';
            if (!isset($new_servers[9])) $new_servers[9] = '';

            $temp_notes = $this->time->timeBasic() . ' - Created by queue.';

            $stmt->bindValue('new_servers0', $new_servers[0], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers1', $new_servers[1], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers2', $new_servers[2], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers3', $new_servers[3], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers4', $new_servers[4], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers5', $new_servers[5], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers6', $new_servers[6], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers7', $new_servers[7], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers8', $new_servers[8], \PDO::PARAM_STR);
            $stmt->bindValue('new_servers9', $new_servers[9], \PDO::PARAM_STR);
            $stmt->bindValue('notes', $temp_notes, \PDO::PARAM_LOB);
            $stmt->bindValue('number_of_servers', $number_of_servers, \PDO::PARAM_INT);
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $new_dns_id = $pdo->lastInsertId('id');

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET dns_id = :new_dns_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_dns_id', $new_dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $new_dns_id;
    }

    public function updateIp($queue_domain_id, $domain, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $has_match = '';

        // get the rDNS for the IP
        $live_ip = gethostbyname($domain . '.');

        // If the domain doesn't resolve assign an IP and rDNS of 0.0.0.0
        if ($live_ip == $domain || $live_ip == $domain . '.') {

            $live_ip = '0.0.0.0';
            $rdns = '0.0.0.0';

        } else {

            $rdns = gethostbyaddr($live_ip);

        }

        // Check to see if the IP already exists
        $result = $pdo->query("
            SELECT id, ip
            FROM ip_addresses
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        if ($result) {

            // Cycle through the existing IPs to see if there's a match
            foreach ($result as $row) {

                // If there's a match use it
                if ($live_ip == $row->ip) {

                    $new_ip_id = $row->id;
                    $has_match = '1';

                }

            }

        }

        // If the IP doesn't exist create a new one
        if ($has_match != '1') {
            
            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO ip_addresses
                (`name`, ip, rdns, notes, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :live_ip, :rdns, :notes, :creation_type_id, :created_by, :insert_time)");

            $temp_notes = $this->time->timeBasic() . ' - Created by queue.';

            $stmt->bindValue('live_ip', $live_ip, \PDO::PARAM_STR);
            $stmt->bindValue('rdns', $rdns, \PDO::PARAM_STR);
            $stmt->bindValue('notes', $temp_notes, \PDO::PARAM_LOB);
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $new_ip_id = $pdo->lastInsertId('id');

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET ip_id = :new_ip_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_ip_id', $new_ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $new_ip_id;
    }

    public function updateCategory($queue_domain_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id
            FROM categories
            WHERE `name` = '" . _('[created by queue]') . "'
            ORDER BY update_time DESC, insert_time DESC
            LIMIT 1")->fetchColumn();

        if (!$result) { // If there isn't an existing '[created by queue]' category create one

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO categories
                (`name`, stakeholder, creation_type_id, created_by, insert_time)
                VALUES
                ('" . _('[created by queue]') . "', '" . _('[created by queue]') . "', :creation_type_id, :created_by, :insert_time)");
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $category_id = $pdo->lastInsertId('id');

        } else { // If there's an existing '[created by queue]' category use it

            $category_id = $result;

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET cat_id = :category_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('category_id', $category_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $category_id;
    }

    public function updateHosting($queue_domain_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        // Check to see if there's an existing '[created by queue]' host
        $result = $pdo->query("
            SELECT id
            FROM hosting
            WHERE `name` = '" . _('[created by queue]') . "'
            ORDER BY update_time DESC, insert_time DESC
            LIMIT 1")->fetchColumn();

        if (!$result) { // If there isn't an existing '[created by queue]' host create one

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $stmt = $pdo->prepare("
                INSERT INTO hosting
                (`name`, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :creation_type_id, :created_by, :insert_time)");
            $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

            $hosting_id = $pdo->lastInsertId('id');

        } else { // If there's an existing '[created by queue]' category use it

            $hosting_id = $result;

        }

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET hosting_id = :hosting_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('hosting_id', $hosting_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $hosting_id;
    }

    public function updatePrivacy($queue_domain_id, $privacy_status)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET privacy = :privacy_status
            WHERE id = :queue_domain_id");
        $stmt->bindValue('privacy_status', $privacy_status, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function updateRenewStatus($queue_domain_id, $autorenew_status)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET autorenew = :autorenew_status
            WHERE id = :queue_domain_id");
        $stmt->bindValue('autorenew_status', $autorenew_status, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function importToDomainQueue($api_registrar_id, $domain, $owner_id, $registrar_id, $account_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $tld = $this->domain->getTld($domain);

        // check to make sure that the domain isn't already in the main domain table
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        if ($result) { // already in the main domain table

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, processing,
                 ready_to_import, finished, already_in_domains, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1',
                 '1', :created_by, :insert_time)");
            $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain_id', $result, \PDO::PARAM_INT);
            $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
            $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
            $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
            $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->execute();

        } else { // not already in the main domain table

            // check to make sure that the domain isn't already in the domain queue
            $stmt = $pdo->prepare("
                SELECT id
                FROM domain_queue
                WHERE domain = :domain");
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetchColumn();

            if ($result) { // already in the domain queue

                $stmt = $pdo->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, processing, ready_to_import, finished, already_in_queue, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1', '1', :created_by, :insert_time)");
                $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
                $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
                $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
                $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
                $bind_timestamp = $this->time->stamp();
                $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
                $stmt->execute();

            } else { // if it's not in the main domain table or the domain queue

                $stmt = $pdo->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :created_by, :insert_time)");
                $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
                $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
                $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
                $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
                $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
                $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
                $bind_timestamp = $this->time->stamp();
                $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
                $stmt->execute();

            }

        }

        $_SESSION['s_domains_in_queue'] = '1';
    }

    public function importToMainDb($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id, autorenew, privacy, created_by, insert_time
            FROM domain_queue
            WHERE id = :queue_domain_id
              AND ready_to_import = '1'
              AND already_in_domains != '1'
              AND already_in_queue != '1'
            ORDER BY insert_time ASC");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (!$result) {

            $log_message = 'Unable to retrieve domains from queue';
            $log_extra = array('Queue Domain ID' => $queue_domain_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domains
                (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id,
                 notes, autorenew, privacy, creation_type_id, created_by, insert_time)
                VALUES
                (:owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date, :cat_id, :dns_id, :ip_id,
                 :hosting_id, :notes, :autorenew, :privacy, :creation_type_id, :created_by, :insert_time)");
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('expiry_date', $bind_expiry_date, \PDO::PARAM_STR);
            $stmt->bindParam('cat_id', $bind_cat_id, \PDO::PARAM_INT);
            $stmt->bindParam('dns_id', $bind_dns_id, \PDO::PARAM_INT);
            $stmt->bindParam('ip_id', $bind_ip_id, \PDO::PARAM_INT);
            $stmt->bindParam('hosting_id', $bind_hosting_id, \PDO::PARAM_INT);
            $bind_temp_notes = $this->time->timeBasic() . " - Inserted by Queue.";
            $stmt->bindValue('notes', $bind_temp_notes, \PDO::PARAM_LOB);
            $stmt->bindParam('autorenew', $bind_autorenew, \PDO::PARAM_INT);
            $stmt->bindParam('privacy', $bind_privacy, \PDO::PARAM_INT);
            $bind_creation_type_id = $this->system->getCreationTypeId('Queue');
            $stmt->bindValue('creation_type_id', $bind_creation_type_id, \PDO::PARAM_INT);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                INSERT INTO domain_field_data
                (domain_id, insert_time)
                VALUES
                (:domain_id, :insert_time)");
            $stmt2->bindParam('domain_id', $new_domain_id, \PDO::PARAM_INT);
            $bind_timestamp = $this->time->stamp();
            $stmt2->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_owner_id = $row->owner_id;
                $bind_registrar_id = $row->registrar_id;
                $bind_account_id = $row->account_id;
                $bind_domain = $row->domain;
                $bind_tld = $row->tld;
                $bind_expiry_date = $row->expiry_date;
                $bind_cat_id = $row->cat_id;
                $bind_dns_id = $row->dns_id;
                $bind_ip_id = $row->ip_id;
                $bind_hosting_id = $row->hosting_id;
                $bind_autorenew = $row->autorenew;
                $bind_privacy = $row->privacy;
                $bind_created_by = $row->created_by;
                $stmt->execute();

                $new_domain_id = $pdo->lastInsertId('id');

                $stmt2->execute();

                // update domain's fees
                $this->maint->updateDomainFee($new_domain_id);

                $sql = $this->queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $this->system->checkForRows($sql);

            }

            return $new_domain_id;
        }
    }

    public function updateNewDomainId($queue_domain_id, $new_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET domain_id = :new_domain_id
            WHERE id = :queue_domain_id");
        $stmt->bindValue('new_domain_id', $new_domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markFinishedList($list_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET processing = '0',
                ready_to_import = '2',
                finished = '1'
            WHERE id = :list_id");
        $stmt->bindValue('list_id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markFinishedDomain($queue_domain_id, $domain, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status)
    {
        $pdo = $this->deeb->cnxx;

        $creation_type_id = $this->system->getCreationTypeId('Queue');

        // confirm that the domain was successfully imported into the main database before marking it as finished
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain
              AND expiry_date = :expiration_date
              AND dns_id = :dns_id
              AND ip_id = :ip_id
              AND cat_id = :cat_id
              AND hosting_id = :hosting_id
              AND privacy = :privacy_status
              AND autorenew = :autorenew_status
              AND creation_type_id = :creation_type_id
              AND active = '1'");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->bindValue('expiration_date', $expiration_date, \PDO::PARAM_STR);
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('hosting_id', $hosting_id, \PDO::PARAM_INT);
        $stmt->bindValue('privacy_status', $privacy_status, \PDO::PARAM_INT);
        $stmt->bindValue('autorenew_status', $autorenew_status, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to mark queue domain as finished';
            $log_extra = array('Queue Domain ID' => $queue_domain_id, 'Domain' => $domain, 'Expiry Date' =>
                $expiration_date, 'DNS ID' => $dns_id, 'IP ID' => $ip_id, 'Category ID' => $cat_id, 'Hosting ID' =>
                $hosting_id, 'Privacy Status' => $privacy_status, 'Autorenew Status' => $autorenew_status,
                'Creation Type ID' => $creation_type_id);
            $this->log->critical($log_message, $log_extra);

        } else {

            $stmt = $pdo->prepare("
                UPDATE domain_queue
                SET processing = '0',
                    ready_to_import = '2',
                    finished = '1'
                WHERE id = :queue_domain_id");
            $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
            $stmt->execute();

        }
    }

    public function markNotProcessingList($list_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE id = :list_id");
        $stmt->bindValue('list_id', $list_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function markNotProcessingDomain($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET processing = '0'
            WHERE id = :queue_domain_id");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function markInvalidDomain($queue_domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE domain_queue
            SET finished = '1',
                invalid_domain = '1'
            WHERE id = :queue_domain_id");
        $stmt->bindValue('queue_domain_id', $queue_domain_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public function copyToHistoryList()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time
            FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC")->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue List results to copy to history table';
            $this->log->info($log_message);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue_list_history
                (api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_count, :owner_id, :registrar_id, :account_id, :created_by,
                 :insert_time)");
            $stmt->bindParam('api_registrar_id', $bind_api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain_count', $bind_domain_count, \PDO::PARAM_INT);
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $stmt->bindParam('insert_time', $bind_insert_time, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_api_registrar_id = $row->api_registrar_id;
                $bind_domain_count = $row->domain_count;
                $bind_owner_id = $row->owner_id;
                $bind_registrar_id = $row->registrar_id;
                $bind_account_id = $row->account_id;
                $bind_created_by = $row->created_by;
                $bind_insert_time = $row->insert_time;
                $stmt->execute();

            }

            $this->deeb->cnxx->query("
                UPDATE domain_queue_list
                SET copied_to_history = '1'
                WHERE finished = '1'
                  AND copied_to_history = '0'");

        }
    }

    public function copyToHistoryDomain()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                dns_id, ip_id, hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by,
                insert_time
            FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC")->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue results to copy to history table';
            $this->log->info($log_message);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO domain_queue_history
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                 dns_id, ip_id, hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by,
                 insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date,
                 :cat_id, :dns_id, :ip_id, :hosting_id, :autorenew, :privacy, :already_in_domains,
                 :already_in_queue, :created_by, :insert_time)");
            $stmt->bindParam('api_registrar_id', $bind_api_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain_id', $bind_domain_id, \PDO::PARAM_INT);
            $stmt->bindParam('owner_id', $bind_owner_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('account_id', $bind_account_id, \PDO::PARAM_INT);
            $stmt->bindParam('domain', $bind_domain, \PDO::PARAM_STR);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('expiry_date', $bind_expiry_date, \PDO::PARAM_STR);
            $stmt->bindParam('cat_id', $bind_cat_id, \PDO::PARAM_INT);
            $stmt->bindParam('dns_id', $bind_dns_id, \PDO::PARAM_INT);
            $stmt->bindParam('ip_id', $bind_ip_id, \PDO::PARAM_INT);
            $stmt->bindParam('hosting_id', $bind_hosting_id, \PDO::PARAM_INT);
            $stmt->bindParam('autorenew', $bind_autorenew, \PDO::PARAM_INT);
            $stmt->bindParam('privacy', $bind_privacy, \PDO::PARAM_INT);
            $stmt->bindParam('already_in_domains', $bind_already_in_domains, \PDO::PARAM_INT);
            $stmt->bindParam('already_in_queue', $bind_already_in_queue, \PDO::PARAM_INT);
            $stmt->bindParam('created_by', $bind_created_by, \PDO::PARAM_INT);
            $stmt->bindParam('insert_time', $bind_insert_time, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_api_registrar_id = $row->api_registrar_id;
                $bind_domain_id = $row->domain_id;
                $bind_owner_id = $row->owner_id;
                $bind_registrar_id = $row->registrar_id;
                $bind_account_id = $row->account_id;
                $bind_domain = $row->domain;
                $bind_tld = $row->tld;
                $bind_expiry_date = $row->expiry_date;
                $bind_cat_id = $row->cat_id;
                $bind_dns_id = $row->dns_id;
                $bind_ip_id = $row->ip_id;
                $bind_hosting_id = $row->hosting_id;
                $bind_autorenew = $row->autorenew;
                $bind_privacy = $row->privacy;
                $bind_already_in_domains = $row->already_in_domains;
                $bind_already_in_queue = $row->already_in_queue;
                $bind_created_by = $row->created_by;
                $bind_insert_time = $row->insert_time;
                $stmt->execute();

            }

            $this->deeb->cnxx->query("
                UPDATE domain_queue
                SET copied_to_history = '1'
                WHERE finished = '1'
                  AND copied_to_history = '0'");

        }
    }

    public function clearFinished()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("
            DELETE FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkListQueue();

        $pdo->query("
            DELETE FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkDomainQueue();
    }

    public function clearProcessing()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE processing = '1'");

        $pdo->query("
            UPDATE domain_queue
            SET processing = '0'
            WHERE processing = '1'");

        return _('Queue Processing Cleared') . '<BR>';
    }

    public function clearQueues()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("DELETE FROM domain_queue_list");

        $pdo->query("DELETE FROM domain_queue");

        return _('Queues Cleared') . '<BR>';
    }
    
    public function checkListQueue()
    {
        $result =  $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue_list
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domains_in_list_queue']);
            return '0';

        } else {

            $_SESSION['s_domains_in_list_queue'] = '1';
            return '1';

        }
    }

    public function checkDomainQueue()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domains_in_queue']);
            return '0';

        } else {

            $_SESSION['s_domains_in_queue'] = '1';
            return '1';

        }
    }

    public function checkProcessingLists()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue_list
            WHERE processing = '1'
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_list_queue_processing']);
            return '0';

        } else {

            $_SESSION['s_list_queue_processing'] = '1';
            return '1';

        }
    }

    public function checkProcessingDomains()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM domain_queue
            WHERE processing = '1'
            LIMIT 1")->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domain_queue_processing']);
            return '0';

        } else {

            $_SESSION['s_domain_queue_processing'] = '1';
            return '1';

        }
    }

} //@formatter:on
