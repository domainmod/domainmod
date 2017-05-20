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
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->log = new Log('domainqueue.class');
    }

    public function processQueueList($dbcon)
    {
        // process domain lists in the queue
        $result = $this->getQueueList($dbcon);

        $this->markProcessingList($dbcon);

        if (mysqli_num_rows($result) > 0) {

            $log_message = '[START] Processing Domain Queue Lists';
            $this->log->info($log_message);

            $api = new Api($this->db);

            while ($row = mysqli_fetch_object($result)) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($dbcon, $api_key, $row->account_id);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple($this->db);
                    $api_key = $api->getKey($row->account_id);
                    $account_id = $registrar->getAccountId($api_key);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $account_id);

                } elseif ($row->api_registrar_name == 'DreamHost') {

                    $registrar = new DreamHost($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($dbcon, $api_key, $row->account_id);

                } elseif ($row->api_registrar_name == 'Dynadot') {

                    $registrar = new Dynadot($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

                } elseif ($row->api_registrar_name == 'eNom') {

                    $registrar = new Enom($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'Fabulous') {

                    $registrar = new Fabulous($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'Freenom') {

                    $registrar = new Freenom($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $account_password);

                } elseif ($row->api_registrar_name == 'GoDaddy') {

                    $registrar = new GoDaddy($this->db);
                    list($api_key, $api_secret) = $api->getKeySecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

                } elseif ($row->api_registrar_name == 'Internet.bs') {

                    $registrar = new InternetBs($this->db);
                    list($api_key, $api_secret) = $api->getKeySecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key, $api_secret);

                } elseif ($row->api_registrar_name == 'Name.com') {

                    $registrar = new NameCom($this->db);
                    list($account_username, $api_key) = $api->getUserKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

                } elseif ($row->api_registrar_name == 'NameBright') {

                    $registrar = new NameBright($this->db);
                    list($account_username, $api_app_name, $api_secret) = $api->getUserAppSecret($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_app_name, $api_secret);

                } elseif ($row->api_registrar_name == 'Namecheap') {

                    $registrar = new Namecheap($this->db);
                    list($account_username, $api_key, $api_ip_address) = $api->getUserKeyIp($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key, $api_ip_address);

                } elseif ($row->api_registrar_name == 'NameSilo') {

                    $registrar = new NameSilo($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($api_key);

                } elseif ($row->api_registrar_name == 'OpenSRS') {

                    $registrar = new OpenSrs($this->db);
                    list($account_username, $api_key) = $api->getUserKey($row->account_id);
                    list($domain_count, $domain_list) = $registrar->getDomainList($account_username, $api_key);

                } else {

                    return "Invalid Domain Registrar";

                }

                // make sure the domain list was successfully retrieved
                if ($domain_count != '' && $domain_list != '') {

                    // update the domain count
                    $this->updateDomainCount($dbcon, $row->id, $domain_count);

                    foreach ($domain_list as $domain) {

                        $this->importToDomainQueue($dbcon, $row->api_registrar_id, $domain, $row->owner_id, $row->registrar_id, $row->account_id, $row->created_by);

                    }

                    $this->markFinishedList($dbcon, $row->id);

                } else {

                    $this->markNotProcessingList($dbcon, $row->id);

                }

            }

            $log_message = '[END] Processing Domain Queue Lists';
            $this->log->info($log_message);

        } else {

            $log_message = 'No Domain Queue Lists to process';
            $this->log->info($log_message);

        }

        $this->copyToHistoryList($dbcon);

        return 'Domain List Queue Processed<BR>';

    }

    public function processQueueDomain($dbcon)
    {
        // process domains in the queue
        $result = $this->getQueueDomain($dbcon);
        $this->markProcessingDomain($dbcon);

        if (mysqli_num_rows($result) > 0) {

            $log_message = '[START] Processing domains in the Domain Queue';
            $this->log->info($log_message);

            $api = new Api($this->db);

            while ($row = mysqli_fetch_object($result)) {

                if ($row->api_registrar_name == 'Above.com') {

                    $registrar = new AboveCom($this->db);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($dbcon, $row->account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'DNSimple') {

                    $registrar = new DnSimple($this->db);
                    $api_key = $api->getKey($row->account_id);
                    $account_id = $registrar->getAccountId($api_key);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'DreamHost') {

                    $registrar = new DreamHost($this->db);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($dbcon, $row->account_id, $row->domain);

                } elseif ($row->api_registrar_name == 'Dynadot') {

                    $registrar = new Dynadot($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'eNom') {

                    $registrar = new Enom($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'Fabulous') {

                    $registrar = new Fabulous($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'Freenom') {

                    $registrar = new Freenom($this->db);
                    list($account_username, $account_password) = $api->getUserPass($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $account_password, $row->domain);

                } elseif ($row->api_registrar_name == 'GoDaddy') {

                    $registrar = new GoDaddy($this->db);
                    list($api_key, $api_secret) = $api->getKeySecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Internet.bs') {

                    $registrar = new InternetBs($this->db);
                    list($api_key, $api_secret) = $api->getKeySecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Name.com') {

                    $registrar = new NameCom($this->db);
                    list($account_username, $api_key) = $api->getUserKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'NameBright') {

                    $registrar = new NameBright($this->db);
                    list($account_username, $api_app_name, $api_secret) = $api->getUserAppSecret($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_app_name, $api_secret, $row->domain);

                } elseif ($row->api_registrar_name == 'Namecheap') {

                    $registrar = new Namecheap($this->db);
                    list($account_username, $api_key, $api_ip_address) = $api->getUserKeyIp($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $api_ip_address, $row->domain);

                } elseif ($row->api_registrar_name == 'NameSilo') {

                    $registrar = new NameSilo($this->db);
                    $api_key = $api->getKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'OpenSRS') {

                    $registrar = new OpenSrs($this->db);
                    list($account_username, $api_key) = $api->getUserKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($account_username, $api_key, $row->domain);

                } elseif ($row->api_registrar_name == 'ResellerClub') {

                    $registrar = new ResellerClub($this->db);
                    list($reseller_id, $api_key) = $api->getReselleridKey($row->account_id);
                    list($expiration_date, $dns_servers, $privacy_status, $autorenew_status) = $registrar->getFullInfo($reseller_id, $api_key, $row->domain);

                } else {
                    
                    return "Invalid Domain Registrar";

                }

                // make sure the domain details was successfully retrieved
                if ($expiration_date != '' && $expiration_date != '0000-00-00' && $expiration_date != '1970-01-01'
                    && $dns_servers != '' && $privacy_status != '' && $autorenew_status != '') {

                    $created_by = $row->created_by;
                    list($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status)
                        = $this->updateDomain($dbcon, $row->id, $row->domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by);

                    // only process the domain if $ready_to_import = 1 (ie. all the information is valid)
                    if ($ready_to_import == '1') {

                        $new_domain_id = $this->importToMainDb($dbcon, $row->id);

                        // updates queue with the new domain id
                        $this->updateNewDomainId($dbcon, $row->id, $new_domain_id);

                        // markFinishedDomain confirms that the domain was added to the main domain database before marketing it as finished
                        $this->markFinishedDomain($dbcon, $row->id, $row->domain, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status);

                    }

                } else {

                    $this->markNotProcessingDomain($dbcon, $row->id);

                }

            }

            $log_message = '[END] Processing domains in the Domain Queue';
            $this->log->info($log_message);

        } else {

            $log_message = 'No domains in the Domain Queue to process';
            $this->log->info($log_message);

        }

        $this->copyToHistoryDomain($dbcon);

        return 'Domain Queue Processed<BR>';

    }

    public function getQueueList($dbcon)
    {
        $error = new Error();
        $sql = "SELECT dql.id, dql.api_registrar_id, dql.owner_id, dql.registrar_id, dql.account_id, dql.created_by,
                    ar.name AS api_registrar_name
                FROM domain_queue_list AS dql, api_registrars AS ar
                WHERE dql.api_registrar_id = ar.id
                  AND dql.processing = '0'
                  AND dql.ready_to_import = '0'
                  AND dql.finished = '0'
                  AND dql.copied_to_history = '0'
                ORDER BY dql.insert_time DESC";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function getQueueDomain($dbcon)
    {
        $error = new Error();
        $sql = "SELECT dq.id, dq.api_registrar_id, dq.domain, dq.account_id, dq.created_by, ar.name AS api_registrar_name
                FROM domain_queue AS dq, api_registrars AS ar
                WHERE dq.api_registrar_id = ar.id
                  AND dq.processing = '0'
                  AND dq.ready_to_import = '0'
                  AND dq.finished = '0'
                  AND dq.copied_to_history = '0'
                  AND dq.already_in_domains = '0'
                  AND dq.already_in_queue = '0'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function markProcessingList($dbcon)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue_list
                SET processing = '1'
                WHERE processing = '0'
                  AND ready_to_import = '0'
                  AND finished = '0'
                  AND copied_to_history = '0'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function updateDomainCount($dbcon, $list_id, $domain_count)
    {
        $sql = "UPDATE domain_queue_list
                SET domain_count = '" . $domain_count . "'
                WHERE id = '" . $list_id . "'";
        mysqli_query($dbcon, $sql);
        return;
    }

    public function markProcessingDomain($dbcon)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET processing = '1'
                WHERE processing = '0'
                  AND ready_to_import = '0'
                  AND finished = '0'
                  AND copied_to_history = '0'
                  AND already_in_domains = '0'
                  AND already_in_queue = '0'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function updateDomain($dbcon, $queue_domain_id, $domain, $expiration_date, $dns_servers, $privacy_status, $autorenew_status, $created_by)
    {
        $error = new Error();
        $this->updateExpirationDate($dbcon, $queue_domain_id, $expiration_date);
        $dns_id = $this->updateDnsServers($dbcon, $queue_domain_id, $dns_servers, $created_by);
        $ip_id = $this->updateIp($dbcon, $queue_domain_id, $domain, $created_by);
        $cat_id = $this->updateCategory($dbcon, $queue_domain_id, $created_by);
        $hosting_id = $this->updateHosting($dbcon, $queue_domain_id, $created_by);
        $privacy_status = $this->updatePrivacy($dbcon, $queue_domain_id, $privacy_status);
        $autorenew_status = $this->updateRenewStatus($dbcon, $queue_domain_id, $autorenew_status);

        // don't mark the domain as ready to import if the information isn't valid
        if ($expiration_date != '0000-00-00' && $dns_id != '0' && $dns_id != '' && $ip_id != '0' && $ip_id != ''
            && $cat_id != '0' && $cat_id != '' && $hosting_id != '0' && $hosting_id != '' && $privacy_status != ''
            && $autorenew_status != '') {

            $sql = "UPDATE domain_queue
                    SET ready_to_import = '1'
                    WHERE id = '" . $queue_domain_id . "'";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            $ready_to_import = '1';

        } else {

            $ready_to_import = '0';

        }

        return array($ready_to_import, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status);
    }

    public function updateExpirationDate($dbcon, $queue_domain_id, $expiration_date)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET expiry_date = '" . $expiration_date . "'
                WHERE id = '" . $queue_domain_id . "'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function updateDnsServers($dbcon, $queue_domain_id, $dns_servers, $created_by)
    {
        $error = new Error();
        $time = new Time();
        $has_match = '';

        // lower case the DNS servers for accurate matching
        $lower_value = array();
        foreach ($dns_servers as $value) {
            $lower_value[] = strtolower($value);
        }
        $dns_servers = $lower_value;

        // Check to see if the DNS servers already exist
        $sql = "SELECT id, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
                FROM dns
                ORDER BY update_time DESC, insert_time DESC";
        $result = mysqli_query($dbcon, $sql);

        // Cycle through the existing DNS servers to see if there's a match
        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

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

            $system = new System();
            $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');

            $sql = "INSERT INTO dns
                    (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, notes, number_of_servers,
                     creation_type_id, created_by, insert_time)
                    VALUES
                    ('[created by queue]', '$new_servers[0]', '$new_servers[1]', '$new_servers[2]', '$new_servers[3]',
                        '$new_servers[4]', '$new_servers[5]', '$new_servers[6]', '$new_servers[7]', '$new_servers[8]',
                        '$new_servers[9]', '" . $time->timeBasic() . " - Created by queue.', '$number_of_servers',
                        '" . $creation_type_id . "', '" . $created_by . "', '" . $time->stamp() . "')";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            $new_dns_id = mysqli_insert_id($dbcon);

        }

        $sql_update = "UPDATE domain_queue
                       SET dns_id = '" . $new_dns_id . "'
                       WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql_update) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $new_dns_id;

    }

    public function updateIp($dbcon, $queue_domain_id, $domain, $created_by)
    {
        $error = new Error();
        $time = new Time();
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
        $sql = "SELECT id, ip
                FROM ip_addresses
                ORDER BY update_time DESC, insert_time DESC";
        $result = mysqli_query($dbcon, $sql);

        // Cycle through the existing IPs to see if there's a match
        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                // If there's a match use it
                if ($live_ip == $row->ip) {

                    $new_ip_id = $row->id;
                    $has_match = '1';

                }

            }

        }

        // If the IP doesn't exist create a new one
        if ($has_match != '1') {
            
            $system = new System();
            $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');

            $sql = "INSERT INTO ip_addresses
                    (`name`, ip, rdns, notes, creation_type_id, created_by, insert_time)
                    VALUES
                    ('[created by queue]', '$live_ip', '$rdns', '" . $time->timeBasic() . " - Created by queue.',
                     '" . $creation_type_id . "', '" . $created_by . "', '" . $time->stamp() . "')";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            $new_ip_id = mysqli_insert_id($dbcon);

        }

        $sql_update = "UPDATE domain_queue
                       SET ip_id = '" . $new_ip_id . "'
                       WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql_update) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $new_ip_id;

    }

    public function updateCategory($dbcon, $queue_domain_id, $created_by)
    {

        // Check to see if there's an existing '[created by queue]' category
        $error = new Error();
        $time = new Time();

        $sql = "SELECT id
                FROM categories
                WHERE `name` = '[created by queue]'
                ORDER BY update_time DESC, insert_time DESC";
        $result = mysqli_query($dbcon, $sql);

        // If there's an existing '[created by queue]' category use it
        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $category_id = $row->id;

            }

        } else { // If there isn't an existing '[created by queue]' category create one

            $system = new System();
            $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');
    
            $sql = "INSERT INTO categories
                    (`name`, stakeholder, creation_type_id, created_by, insert_time)
                    VALUES
                    ('[created by queue]', '[created by queue]', '" . $creation_type_id . "', '" . $created_by . "', '" . $time->stamp() . "')";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
    
            $category_id = mysqli_insert_id($dbcon);

        }

        $sql = "UPDATE domain_queue
                SET cat_id = '" . $category_id . "'
                WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $category_id;

    }

    public function updateHosting($dbcon, $queue_domain_id, $created_by)
    {
        // Check to see if there's an existing '[created by queue]' host
        $error = new Error();
        $time = new Time();

        $sql = "SELECT id
                FROM hosting
                WHERE `name` = '[created by queue]'
                ORDER BY update_time DESC, insert_time DESC";
        $result = mysqli_query($dbcon, $sql);

        // If there's an existing '[created by queue]' category use it
        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $hosting_id = $row->id;

            }

        } else { // If there isn't an existing '[created by queue]' host create one

            $system = new System();
            $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');
            
            $sql = "INSERT INTO hosting
                    (`name`, creation_type_id, created_by,insert_time)
                    VALUES
                    ('[created by queue]', '" . $creation_type_id . "', '" . $created_by . "', '" . $time->stamp() . "')";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
    
            $hosting_id = mysqli_insert_id($dbcon);

        }

        $sql = "UPDATE domain_queue
                SET hosting_id = '" . $hosting_id . "'
                WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $hosting_id;
    }

    public function updatePrivacy($dbcon, $queue_domain_id, $privacy_status)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET privacy = '" . $privacy_status . "'
                WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $privacy_status;
    }

    public function updateRenewStatus($dbcon, $queue_domain_id, $autorenew_status)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET autorenew = '" . $autorenew_status . "'
                WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return $autorenew_status;
    }

    public function importToDomainQueue($dbcon, $api_registrar_id, $domain, $owner_id, $registrar_id, $account_id, $created_by)
    {
        $error = new Error();
        $time = new Time();

        $domain_temp = new Domain();
        $tld = $domain_temp->getTld($domain);

        // check to make sure that the domain isn't already in the main domain table
        $sql = "SELECT id
                FROM domains
                WHERE domain = '" . $domain . "'";
        $result = mysqli_query($dbcon, $sql);

        // already in the main domain table
        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $sql = "INSERT INTO domain_queue
                        (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, processing, ready_to_import, finished, already_in_domains, created_by, insert_time)
                        VALUES
                        ('$api_registrar_id', '$row->id', '$owner_id', '$registrar_id', '$account_id', '$domain', '$tld', '0', '1', '1', '1', '$created_by', '" . $time->stamp() . "')";
                mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            }

        } else {

            // check to make sure that the domain isn't already in the domain queue
            $sql_temp = "SELECT id
                         FROM domain_queue
                         WHERE domain = '" . $domain . "'";
            $result_temp = mysqli_query($dbcon, $sql_temp);

            if (mysqli_num_rows($result_temp) > 0) {

                // already in the domain queue
                $sql = "INSERT INTO domain_queue
                        (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, processing, ready_to_import, finished, already_in_queue, created_by, insert_time)
                        VALUES
                        ('$api_registrar_id', '$owner_id', '$registrar_id', '$account_id', '$domain', '$tld', '0', '1', '1', '1', '$created_by', '" . $time->stamp() . "')";
                mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            } else { // if it's not in the main domain table or the domain queue, insert it into the queue

                $sql = "INSERT INTO domain_queue
                        (api_registrar_id, owner_id, registrar_id, account_id, domain, tld, created_by, insert_time)
                        VALUES
                        ('$api_registrar_id', '$owner_id', '$registrar_id', '$account_id', '$domain', '$tld', '$created_by', '" . $time->stamp() . "')";
                mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            }

        }

        $_SESSION['s_domains_in_queue'] = '1';

        return;

    }

    public function importToMainDb($dbcon, $queue_domain_id)
    {
        $error = new Error();
        $maint = new Maintenance();
        $time = new Time();

        $sql = "SELECT id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                    hosting_id, autorenew, privacy, created_by, insert_time
                FROM domain_queue
                WHERE id = '" . $queue_domain_id . "'
                  AND ready_to_import = '1'
                  AND already_in_domains != '1'
                  AND already_in_queue != '1'
                ORDER BY insert_time ASC";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $system = new System();
                $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');

                $sql_move = "INSERT INTO domains
                             (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                              hosting_id, notes, autorenew, privacy, creation_type_id, created_by, insert_time)
                             VALUES
                             ('$row->owner_id', '$row->registrar_id', '$row->account_id', '$row->domain', '$row->tld',
                              '$row->expiry_date', '$row->cat_id', '$row->dns_id', '$row->ip_id', '$row->hosting_id',
                              '" . $time->timeBasic() . " - Inserted by Queue.', '$row->autorenew', '$row->privacy',
                              '" . $creation_type_id . "', '$row->created_by', '" . $time->stamp() . "')";
                mysqli_query($dbcon, $sql_move) or $error->outputSqlError($dbcon, '1', 'ERROR');

                $new_domain_id = mysqli_insert_id($dbcon);

                $sql_move = "INSERT INTO domain_field_data
                             (domain_id, insert_time)
                             VALUES
                             ('$new_domain_id', '" . $time->stamp() . "')";
                mysqli_query($dbcon, $sql_move) or $error->outputSqlError($dbcon, '1', 'ERROR');

                // update domain's fees
                $maint->updateDomainFee($dbcon, $new_domain_id);

                $queryB = new QueryBuild();
                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($dbcon, $sql);

            }

        }

        return $new_domain_id;
    }

    public function updateNewDomainId($dbcon, $queue_domain_id, $new_domain_id)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET domain_id = '" . $new_domain_id . "'
                WHERE id = '" . $queue_domain_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return 'Updated Queue Domain With New Domain ID';
    }

    public function markFinishedList($dbcon, $list_id)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue_list
                SET processing = '0',
                    ready_to_import = '2',
                    finished = '1'
                WHERE id = '" . $list_id . "'";
        mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        return 'Successfully Added List To Domain Queue';

    }

    public function markFinishedDomain($dbcon, $queue_domain_id, $domain, $expiration_date, $dns_id, $ip_id, $cat_id, $hosting_id, $privacy_status, $autorenew_status)
    {
        $system = new System();
        $error = new Error();
        $creation_type_id = $system->getCreationTypeId($dbcon, 'Queue');

        // confirm that the domain was successfully imported into the main database before marking it as finished
        $sql = "SELECT id
                FROM domains
                WHERE domain = '" . $domain . "'
                  AND expiry_date = '" . $expiration_date . "'
                  AND dns_id = '" . $dns_id . "'
                  AND ip_id = '" . $ip_id . "'
                  AND cat_id = '" . $cat_id . "'
                  AND hosting_id = '" . $hosting_id . "'
                  AND privacy = '" . $privacy_status . "'
                  AND autorenew = '" . $autorenew_status . "'
                  AND creation_type_id = '" . $creation_type_id . "'
                  AND active = '1'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) == 1) {

            $sql = "UPDATE domain_queue
                    SET processing = '0',
                        ready_to_import = '2',
                        finished = '1'
                    WHERE id = '" . $queue_domain_id . "'";
            mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

            return 'Successfully Imported';

        } else {

            return 'Could Not Import ' . $domain . ' From The Queue';

        }

    }

    public function markNotProcessingList($dbcon, $list_id)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue_list
                SET processing = '0'
                WHERE id = '" . $list_id . "'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function markNotProcessingDomain($dbcon, $queue_domain_id)
    {
        $error = new Error();

        $sql = "UPDATE domain_queue
                SET processing = '0'
                WHERE id = '" . $queue_domain_id . "'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        return $result;
    }

    public function copyToHistoryList($dbcon)
    {
        $error = new Error();

        $sql = "SELECT api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time
                FROM domain_queue_list
                WHERE finished = '1'
                  AND copied_to_history = '0'
                ORDER BY insert_time ASC";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $sql_move = "INSERT INTO domain_queue_list_history
                             (api_registrar_id, domain_count, owner_id, registrar_id, account_id, created_by, insert_time)
                             VALUES
                             ('$row->api_registrar_id', '$row->domain_count', '$row->owner_id', '$row->registrar_id', '$row->account_id', '$row->created_by', '$row->insert_time')";
                mysqli_query($dbcon, $sql_move) or $error->outputSqlError($dbcon, '1', 'ERROR');
                
                $sql_update = "UPDATE domain_queue_list
                               SET copied_to_history = '1'
                               WHERE finished = '1'
                                 AND copied_to_history = '0'";
                mysqli_query($dbcon, $sql_update);

            }

        }

        return;
    }

    public function copyToHistoryDomain($dbcon)
    {
        $error = new Error();

        $sql = "SELECT api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id,
                    dns_id, ip_id, hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by,
                    insert_time
                FROM domain_queue
                WHERE finished = '1'
                  AND copied_to_history = '0'
                ORDER BY insert_time ASC";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $sql_move = "INSERT INTO domain_queue_history
                             (api_registrar_id, domain_id, owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                              hosting_id, autorenew, privacy, already_in_domains, already_in_queue, created_by, insert_time)
                             VALUES
                             ('$row->api_registrar_id', '$row->domain_id', '$row->owner_id', '$row->registrar_id',
                              '$row->account_id', '$row->domain', '$row->tld', '$row->expiry_date', '$row->cat_id',
                              '$row->dns_id', '$row->ip_id', '$row->hosting_id', '$row->autorenew', '$row->privacy',
                              '$row->already_in_domains', '$row->already_in_queue', '$row->created_by',
                              '$row->insert_time')";
                mysqli_query($dbcon, $sql_move) or $error->outputSqlError($dbcon, '1', 'ERROR');
                
                $sql_update = "UPDATE domain_queue
                               SET copied_to_history = '1'
                               WHERE finished = '1'
                                 AND copied_to_history = '0'";
                mysqli_query($dbcon, $sql_update);

            }

        }

        return;
    }

    public function clearFinished($dbcon)
    {

        $sql = "DELETE FROM domain_queue_list
                WHERE finished = '1'
                  AND copied_to_history = '1'";
        mysqli_query($dbcon, $sql);
        
        $this->checkListQueue($dbcon);

        $sql = "DELETE FROM domain_queue
                WHERE finished = '1'
                  AND copied_to_history = '1'";
        mysqli_query($dbcon, $sql);
        
        $this->checkDomainQueue($dbcon);

        return;
    }

    public function clearProcessing($dbcon)
    {
        $sql = "UPDATE domain_queue_list
                SET processing = '0'
                WHERE processing = '1'";
        mysqli_query($dbcon, $sql);

        $sql = "UPDATE domain_queue
                SET processing = '0'
                WHERE processing = '1'";
        mysqli_query($dbcon, $sql);
        
        return 'Queue Processing Cleared<BR>'; // This is used in a maintenance file so it needs a response message
    }

    public function clearQueues($dbcon)
    {

        $sql = "DELETE FROM domain_queue_list";
        mysqli_query($dbcon, $sql);

        $sql = "DELETE FROM domain_queue";
        mysqli_query($dbcon, $sql);
        
        return 'Queues Cleared';
    }
    
    public function checkListQueue($dbcon)
    {
        $sql = "SELECT id
                FROM domain_queue_list
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) == 0) {
            unset($_SESSION['s_domains_in_list_queue']);
            return '0';
        } else {
            $_SESSION['s_domains_in_list_queue'] = '1';
            return '1';
        }

    }

    public function checkDomainQueue($dbcon)
    {
        $sql = "SELECT id
                FROM domain_queue
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) == 0) {
            unset($_SESSION['s_domains_in_queue']);
            return '0';
        } else {
            $_SESSION['s_domains_in_queue'] = '1';
            return '1';
        }

    }

    public function checkProcessingLists($dbcon)
    {
        $sql = "SELECT id
                FROM domain_queue_list
                WHERE processing = '1'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) == 0) {
            unset($_SESSION['s_list_queue_processing']);
            return '0';
        } else {
            $_SESSION['s_list_queue_processing'] = '1';
            return '1';
        }

    }

    public function checkProcessingDomains($dbcon)
    {
        $sql = "SELECT id
                FROM domain_queue
                WHERE processing = '1'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql);

        if (mysqli_num_rows($result) == 0) {
            unset($_SESSION['s_domain_queue_processing']);
            return '0';
        } else {
            $_SESSION['s_domain_queue_processing'] = '1';
            return '1';
        }

    }

} //@formatter:on
