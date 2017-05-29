<?php
/**
 * /classes/DomainMOD/DomainQueue.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
    public $domain;
    public $log;
    public $maint;
    public $queryB;
    public $system;
    public $time;

    public function __construct()
    {
        $this->api = new Api();
        $this->domain = new Domain();
        $this->log = new Log('domainqueue.class');
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
            $this->log->info($log_message);

            foreach ($result as $row) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom();
                    $api_key = $this->api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $row->account_id);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $registrar->getAccountId($api_key);
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

                } else {

                    return "Invalid Domain Registrar";

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
            $this->log->info($log_message);

        }

        $this->copyToHistoryList();

        return 'Domain List Queue Processed<BR>';
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
            $this->log->info($log_message);

            foreach ($result as $row) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom();
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($row->account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple();
                    $api_key = $this->api->getKey($row->account_id);
                    $account_id = $registrar->getAccountId($api_key);
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

                } elseif ($row->api_registrar_name == 'GoDaddy') {

                    $registrar = new GoDaddy();
                    list($api_key, $api_secret) = $this->api->getKeySecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

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
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'OpenSRS') {

                    $registrar = new OpenSrs();
                    list($account_username, $api_key) = $this->api->getUserKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'ResellerClub') {

                    $registrar = new ResellerClub();
                    list($reseller_id, $api_key) = $this->api->getReselleridKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($reseller_id, $api_key, $row->domain);

                } else {

                    return "Invalid Domain Registrar";

                }

                // make sure the domain details was successfully retrieved
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

                    $this->markNotProcessingDomain($row->id);

                }

            }

            $log_message = '[END] Processing domains in the Domain Queue';
            $this->log->info($log_message);

        }

        $this->copyToHistoryDomain();

        return 'Domain Queue Processed<BR>';
    }

    public function getQueueList()
    {
        $tmpq = $this->system->db()->query("
            SELECT dql.id, dql.api_registrar_id, dql.owner_id, dql.registrar_id, dql.account_id, dql.created_by,
                ar.name AS api_registrar_name
            FROM domain_queue_list AS dql, api_registrars AS ar
            WHERE dql.api_registrar_id = ar.id
              AND dql.processing = '0'
              AND dql.ready_to_import = '0'
              AND dql.finished = '0'
              AND dql.copied_to_history = '0'
            ORDER BY dql.insert_time DESC");

        return $tmpq->fetchAll();
    }

    public function getQueueDomain()
    {
        $tmpq = $this->system->db()->query("
            SELECT dq.id, dq.api_registrar_id, dq.domain, dq.account_id, dq.created_by, ar.name AS api_registrar_name
            FROM domain_queue AS dq, api_registrars AS ar
            WHERE dq.api_registrar_id = ar.id
              AND dq.processing = '0'
              AND dq.ready_to_import = '0'
              AND dq.finished = '0'
              AND dq.copied_to_history = '0'
              AND dq.already_in_domains = '0'
              AND dq.already_in_queue = '0'");

        return $tmpq->fetchAll();
    }

    public function markProcessingList()
    {
        $this->system->db()->query("
            UPDATE domain_queue_list
            SET processing = '1'
            WHERE processing = '0'
              AND ready_to_import = '0'
              AND finished = '0'
              AND copied_to_history = '0'");
    }

    public function updateDomainCount($list_id, $domain_count)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue_list
            SET domain_count = :domain_count
            WHERE id = :id");
        $tmpq->execute(array(
                       'domain_count' => $domain_count,
                       'id' => $list_id));
    }

    public function markProcessingDomain()
    {
        $this->system->db()->query("
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

            $tmpq = $this->system->db()->prepare("
                UPDATE domain_queue
                SET ready_to_import = '1'
                WHERE id = :queue_domain_id");
            $tmpq->execute(array('queue_domain_id' => $queue_domain_id));

            $ready_to_import = '1';

        } else {

            $ready_to_import = '0';

        }

        return array($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status);
    }

    public function updateExpirationDate($queue_domain_id, $expiration_date)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET expiry_date = :expiration_date
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'expiration_date' => $expiration_date,
                       'queue_domain_id' => $queue_domain_id));
    }

    public function updateDnsServers($queue_domain_id, $dns_servers, $created_by)
    {
        $has_match = '';

        // lower case the DNS servers for accurate matching
        $lower_value = array();
        foreach ($dns_servers as $value) {
            $lower_value[] = strtolower($value);
        }
        $dns_servers = $lower_value;

        $tmpq = $this->system->db()->query("
            SELECT id, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            ORDER BY update_time DESC, insert_time DESC");
        $result = $tmpq->fetchAll();

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

            $new_servers = '';
            $count = 0;

            // Make sure DNS servers were returned
            foreach($dns_servers as $server) {

                $new_servers[$count++] = strtolower($server);

            }

            $clean_servers = array_filter($new_servers);
            $number_of_servers = count($clean_servers);

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $tmpq = $this->system->db()->prepare("
                INSERT INTO dns
                (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, notes, number_of_servers,
                 creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :new_servers0, :new_servers1, :new_servers2, :new_servers3, :new_servers4,
                 :new_servers5, :new_servers6, :new_servers7, :new_servers8, :new_servers9, :notes, :number_of_servers,
                 :creation_type_id, :created_by, :insert_time)");
            $tmpq->execute(array(
                           'new_servers0' => $new_servers[0],
                           'new_servers1' => $new_servers[1],
                           'new_servers2' => $new_servers[2],
                           'new_servers3' => $new_servers[3],
                           'new_servers4' => $new_servers[4],
                           'new_servers5' => $new_servers[5],
                           'new_servers6' => $new_servers[6],
                           'new_servers7' => $new_servers[7],
                           'new_servers8' => $new_servers[8],
                           'new_servers9' => $new_servers[9],
                           'notes' => $this->time->timeBasic() . ' - Created by queue.',
                           'number_of_servers' => $number_of_servers,
                           'creation_type_id' => $creation_type_id,
                           'created_by' => $created_by,
                           'insert_time' => $this->time->stamp()));
            $new_dns_id = $this->system->db()->lastInsertId();
        }

        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET dns_id = :new_dns_id
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'new_dns_id' => $new_dns_id,
                       'queue_domain_id' => $queue_domain_id));

        return $new_dns_id;
    }

    public function updateIp($queue_domain_id, $domain, $created_by)
    {
        $has_match = '';

        // get the rDNS for the IP
        $live_ip = gethostbyname($domain);

        // If the domain doesn't resolve assign an IP and rDNS of 0.0.0.0
        if ($live_ip == $domain) {

            $live_ip = '0.0.0.0';
            $rdns = '0.0.0.0';

        } else {

            $rdns = gethostbyaddr($live_ip);

        }

        // Check to see if the IP already exists
        $tmpq = $this->system->db()->query("
            SELECT id, ip
            FROM ip_addresses
            ORDER BY update_time DESC, insert_time DESC");
        $result = $tmpq->fetchAll();

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

            $tmpq = $this->system->db()->prepare("
                INSERT INTO ip_addresses
                (`name`, ip, rdns, notes, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :live_ip, :rdns, :notes, :creation_type_id, :created_by, :insert_time)");
            $tmpq->execute(array(
                           'live_ip' => $live_ip,
                           'rdns' => $rdns,
                           'notes' => $this->time->timeBasic() . ' - Created by queue.',
                           'creation_type_id' => $creation_type_id,
                           'created_by' => $created_by,
                           'insert_time' => $this->time->stamp()));

            $new_ip_id = $this->system->db()->lastInsertId();

        }

        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET ip_id = :new_ip_id
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'new_ip_id' => $new_ip_id,
                       'queue_domain_id' => $queue_domain_id));

        return $new_ip_id;
    }

    public function updateCategory($queue_domain_id, $created_by)
    {
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM categories
            WHERE `name` = '[created by queue]'
            ORDER BY update_time DESC, insert_time DESC
            LIMIT 1");
        $result = $tmpq->fetchColumn();

        if (!$result) { // If there isn't an existing '[created by queue]' category create one

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $tmpq = $this->system->db()->prepare("
                INSERT INTO categories
                (`name`, stakeholder, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', '[created by queue]', :creation_type_id, :created_by, :insert_time)");
            $tmpq->execute(array(
                           'creation_type_id' => $creation_type_id,
                           'created_by' => $created_by,
                           'insert_time' => $this->time->stamp()));

            $category_id = $this->system->db()->lastInsertId();

        } else { // If there's an existing '[created by queue]' category use it

            $category_id = $result;

        }

        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET cat_id = :category_id
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'category_id' => $category_id,
                       'queue_domain_id' => $queue_domain_id));

        return $category_id;
    }

    public function updateHosting($queue_domain_id, $created_by)
    {
        // Check to see if there's an existing '[created by queue]' host
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM hosting
            WHERE `name` = '[created by queue]'
            ORDER BY update_time DESC, insert_time DESC
            LIMIT 1");
        $result = $tmpq->fetchColumn();

        if (!$result) { // If there isn't an existing '[created by queue]' host create one

            $creation_type_id = $this->system->getCreationTypeId('Queue');

            $tmpq = $this->system->db()->prepare("
                INSERT INTO hosting
                (`name`, creation_type_id, created_by, insert_time)
                VALUES
                ('[created by queue]', :creation_type_id, :created_by, :insert_time)");
            $tmpq->execute(array(
                           'creation_type_id' => $creation_type_id,
                           'created_by' => $created_by,
                           'insert_time' => $this->time->stamp()));

            $hosting_id = $this->system->db()->lastInsertId();

        } else { // If there's an existing '[created by queue]' category use it

            $hosting_id = $result;

        }

        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET hosting_id = :hosting_id
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'hosting_id' => $hosting_id,
                       'queue_domain_id' => $queue_domain_id));

        return $hosting_id;
    }

    public function updatePrivacy($queue_domain_id, $privacy_status)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET privacy = :privacy_status
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'privacy_status' => $privacy_status,
                       'queue_domain_id' => $queue_domain_id));
    }

    public function updateRenewStatus($queue_domain_id, $autorenew_status)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET autorenew = :autorenew_status
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'autorenew_status' => $autorenew_status,
                       'queue_domain_id' => $queue_domain_id));
    }

    public function importToDomainQueue($api_registrar_id, $domain, $owner_id, $registrar_id, $account_id, $created_by)
    {
        $tld = $this->domain->getTld($domain);

        // check to make sure that the domain isn't already in the main domain table
        $tmpq = $this->system->db()->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain");
        $tmpq->execute(array('domain' => $domain));
        $result = $tmpq->fetchColumn();

        if ($result) { // already in the main domain table

            $tmpq = $this->system->db()->prepare("
                INSERT INTO domain_queue
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, processing,
                 ready_to_import, finished, already_in_domains, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1',
                 '1', :created_by, :insert_time)");
            $tmpq->execute(array(
                           'api_registrar_id' => $api_registrar_id,
                           'domain_id' => $result,
                           'owner_id' => $owner_id,
                           'registrar_id' => $registrar_id,
                           'account_id' => $account_id,
                           'domain' => $domain,
                           'tld' => $tld,
                           'created_by' => $created_by,
                           'insert_time' => $this->time->stamp()));

        } else { // not already in the main domain table

            // check to make sure that the domain isn't already in the domain queue
            $tmpq = $this->system->db()->prepare("
                SELECT id
                FROM domain_queue
                WHERE domain = :domain");
            $tmpq->execute(array('domain' => $domain));
            $result = $tmpq->fetchColumn();

            if ($result) { // already in the domain queue

                $tmpq = $this->system->db()->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, processing, ready_to_import, finished, already_in_queue, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, '0', '1', '1', '1', :created_by, :insert_time)");
                $tmpq->execute(array(
                               'api_registrar_id' => $api_registrar_id,
                               'owner_id' => $owner_id,
                               'registrar_id' => $registrar_id,
                               'account_id' => $account_id,
                               'domain' => $domain,
                               'tld' => $tld,
                               'created_by' => $created_by,
                               'insert_time' => $this->time->stamp()));

            } else { // if it's not in the main domain table or the domain queue

                $tmpq = $this->system->db()->prepare("
                    INSERT INTO domain_queue
                    (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, created_by, insert_time)
                    VALUES
                    (:api_registrar_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :created_by, :insert_time)");
                $tmpq->execute(array(
                               'api_registrar_id' => $api_registrar_id,
                               'owner_id' => $owner_id,
                               'registrar_id' => $registrar_id,
                               'account_id' => $account_id,
                               'domain' => $domain,
                               'tld' => $tld,
                               'created_by' => $created_by,
                               'insert_time' => $this->time->stamp()));

            }

        }

        $_SESSION['s_domains_in_queue'] = '1';
    }

    public function importToMainDb($queue_domain_id)
    {
        $tmpq = $this->system->db()->prepare("
            SELECT id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id, autorenew, privacy, created_by, insert_time
            FROM domain_queue
            WHERE id = :queue_domain_id
              AND ready_to_import = '1'
              AND already_in_domains != '1'
              AND already_in_queue != '1'
            ORDER BY insert_time ASC");
        $tmpq->execute(array('queue_domain_id' => $queue_domain_id));
        $result = $tmpq->fetchAll();

        if (!$result) {

            $log_message = 'Unable to retrieve domains from queue';
            $log_extra = array('Queue Domain ID' => $queue_domain_id);
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            $tmpq = $this->system->db()->prepare("
                INSERT INTO domains
                (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id,
                 notes, autorenew, privacy, creation_type_id, created_by, insert_time)
                VALUES
                (:owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date, :cat_id, :dns_id, :ip_id,
                 :hosting_id, :notes, :autorenew, :privacy, :creation_type_id, :created_by, :insert_time)");

            $tmpq2 = $this->system->db()->prepare("
                INSERT INTO domain_field_data
                (domain_id, insert_time)
                VALUES
                (:domain_id, :insert_time)");

            foreach ($result as $row) {

                $creation_type_id = $this->system->getCreationTypeId('Queue');

                $tmpq->execute(array(
                               'owner_id' => $row->owner_id,
                               'registrar_id' => $row->registrar_id,
                               'account_id' => $row->account_id,
                               'domain' => $row->domain,
                               'tld' => $row->tld,
                               'expiry_date' => $row->expiry_date,
                               'cat_id' => $row->cat_id,
                               'dns_id' => $row->dns_id,
                               'ip_id' => $row->ip_id,
                               'hosting_id' => $row->hosting_id,
                               'notes' => $this->time->timeBasic() . " - Inserted by Queue.",
                               'autorenew' => $row->autorenew,
                               'privacy' => $row->privacy,
                               'creation_type_id' => $creation_type_id,
                               'created_by' => $row->created_by,
                               'insert_time' => $this->time->stamp()));

                $new_domain_id = $this->system->db()->lastInsertId();

                $tmpq2->execute(array(
                                'domain_id' => $new_domain_id,
                                'insert_time' => $this->time->stamp()));

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
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET domain_id = :new_domain_id
            WHERE id = :queue_domain_id");
        $tmpq->execute(array(
                       'new_domain_id' => $new_domain_id,
                       'queue_domain_id' => $queue_domain_id));
    }

    public function markFinishedList($list_id)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue_list
            SET processing = '0',
                ready_to_import = '2',
                finished = '1'
            WHERE id = :list_id");
        $tmpq->execute(array('list_id' => $list_id));
    }

    public function markFinishedDomain($queue_domain_id, $domain, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status)
    {
        $creation_type_id = $this->system->getCreationTypeId('Queue');

        // confirm that the domain was successfully imported into the main database before marking it as finished
        $tmpq = $this->system->db()->prepare("
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
        $tmpq->execute(array(
                       'domain' => $domain,
                       'expiration_date' => $expiration_date,
                       'dns_id' => $dns_id,
                       'ip_id' => $ip_id,
                       'cat_id' => $cat_id,
                       'hosting_id' => $hosting_id,
                       'privacy_status' => $privacy_status,
                       'autorenew_status' => $autorenew_status,
                       'creation_type_id' => $creation_type_id));
        $result = $tmpq->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to mark queue domain as finished';
            $log_extra = array('Queue Domain ID' => $queue_domain_id, 'Domain' => $domain, 'Expiry Date' =>
                $expiration_date, 'DNS ID' => $dns_id, 'IP ID' => $ip_id, 'Category ID' => $cat_id, 'Hosting ID' =>
                $hosting_id, 'Privacy Status' => $privacy_status, 'Autorenew Status' => $autorenew_status,
                'Creation Type ID' => $creation_type_id);
            $this->log->error($log_message, $log_extra);

        } else {

            $tmpq = $this->system->db()->prepare("
                UPDATE domain_queue
                SET processing = '0',
                    ready_to_import = '2',
                    finished = '1'
                WHERE id = :queue_domain_id");
            $tmpq->execute(array('queue_domain_id' => $queue_domain_id));

        }
    }

    public function markNotProcessingList($list_id)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE id = :list_id");
        $tmpq->execute(array('list_id' => $list_id));
    }

    public function markNotProcessingDomain($queue_domain_id)
    {
        $tmpq = $this->system->db()->prepare("
            UPDATE domain_queue
            SET processing = '0'
            WHERE id = :queue_domain_id");
        $tmpq->execute(array('queue_domain_id' => $queue_domain_id));
    }

    public function copyToHistoryList()
    {
        $tmpq = $this->system->db()->query("
            SELECT api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time
            FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC");
        $result = $tmpq->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue List results to copy to history table';
            $this->log->info($log_message);

        } else {

            $tmpq = $this->system->db()->prepare("
                INSERT INTO domain_queue_list_history
                (api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time)
                VALUES
                (:api_registrar_id, :domain_count, :owner_id, :registrar_id, :account_id, :created_by,
                 :insert_time)");

            foreach ($result as $row) {

                $tmpq->execute(array(
                               'api_registrar_id' => $row->api_registrar_id,
                               'domain_count' => $row->domain_count,
                               'owner_id' => $row->owner_id,
                               'registrar_id' => $row->registrar_id,
                               'account_id' => $row->account_id,
                               'created_by' => $row->created_by,
                               'insert_time' => $row->insert_time));

                $this->system->db()->query("
                    UPDATE domain_queue_list
                    SET copied_to_history = '1'
                    WHERE finished = '1'
                      AND copied_to_history = '0'");

            }

        }
    }

    public function copyToHistoryDomain()
    {
        $tmpq = $this->system->db()->query("
            SELECT api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                dns_id, ip_id, hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by,
                insert_time
            FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '0'
            ORDER BY insert_time ASC");
        $result = $tmpq->fetchAll();

        if (!$result) {

            $log_message = 'No Domain Queue results to copy to history table';
            $this->log->info($log_message);

        } else {

            $tmpq = $this->system->db()->prepare("
                INSERT INTO domain_queue_history
                (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                 dns_id, ip_id, hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by,
                 insert_time)
                VALUES
                (:api_registrar_id, :domain_id, :owner_id, :registrar_id, :account_id, :domain, :tld, :expiry_date,
                 :cat_id, :dns_id, :ip_id, :hosting_id, :autorenew, :privacy, :already_in_domains,
                 :already_in_queue, :created_by, :insert_time)");

            foreach ($result as $row) {

                $tmpq->execute(array(
                               'api_registrar_id' => $row->api_registrar_id,
                               'domain_id' => $row->domain_id,
                               'owner_id' => $row->owner_id,
                               'registrar_id' => $row->registrar_id,
                               'account_id' => $row->account_id,
                               'domain' => $row->domain,
                               'tld' => $row->tld,
                               'expiry_date' => $row->expiry_date,
                               'cat_id' => $row->cat_id,
                               'dns_id' => $row->dns_id,
                               'ip_id' => $row->ip_id,
                               'hosting_id' => $row->hosting_id,
                               'autorenew' => $row->autorenew,
                               'privacy' => $row->privacy,
                               'already_in_domains' => $row->already_in_domains,
                               'already_in_queue' => $row->already_in_queue,
                               'created_by' => $row->created_by,
                               'insert_time' => $row->insert_time));

                $this->system->db()->query("
                    UPDATE domain_queue
                    SET copied_to_history = '1'
                    WHERE finished = '1'
                      AND copied_to_history = '0'");

            }

        }
    }

    public function clearFinished()
    {
        $this->system->db()->query("
            DELETE FROM domain_queue_list
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkListQueue();

        $this->system->db()->query("
            DELETE FROM domain_queue
            WHERE finished = '1'
              AND copied_to_history = '1'");

        $this->checkDomainQueue();
    }

    public function clearProcessing()
    {
        $this->system->db()->query("
            UPDATE domain_queue_list
            SET processing = '0'
            WHERE processing = '1'");

        $this->system->db()->query("
            UPDATE domain_queue
            SET processing = '0'
            WHERE processing = '1'");

        return 'Queue Processing Cleared<BR>';
    }

    public function clearQueues()
    {
        $this->system->db()->query("DELETE FROM domain_queue_list");

        $this->system->db()->query("DELETE FROM domain_queue");

        return 'Queues Cleared<BR>';
    }
    
    public function checkListQueue()
    {
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM domain_queue_list
            LIMIT 1");
        $result = $tmpq->fetchColumn();

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
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM domain_queue
            LIMIT 1");
        $result = $tmpq->fetchColumn();

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
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM domain_queue_list
            WHERE processing = '1'
            LIMIT 1");
        $result = $tmpq->fetchColumn();

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
        $tmpq = $this->system->db()->query("
            SELECT id
            FROM domain_queue
            WHERE processing = '1'
            LIMIT 1");
        $result = $tmpq->fetchColumn();

        if (!$result) {

            unset($_SESSION['s_domain_queue_processing']);
            return '0';

        } else {

            $_SESSION['s_domain_queue_processing'] = '1';
            return '1';

        }
    }

} //@formatter:on
