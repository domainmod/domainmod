<?php
/**
 * /classes/DomainMOD/Assets.php
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

class Assets
{
    public $currency;
    public $deeb;
    public $error;
    public $log;
    public $system;
    public $time;

    public function __construct()
    {
        $this->currency = new Currency();
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.assets');
        $this->system = new System();
        $this->time = new Time();
    }

    public function getRegistrar($registrar_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM registrars
            WHERE id = :registrar_id");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar name';
            $log_extra = array('Registrar ID' => $registrar_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getRegistrarByAcc($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT r.name
            FROM registrars AS r, registrar_accounts AS ra
            WHERE r.id = ra.registrar_id
              AND ra.id = :account_id
            LIMIT 1");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar name';
            $log_extra = array('Account ID' => $account_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getSslType($type_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT type
            FROM ssl_cert_types
            WHERE id = :type_id");
        $stmt->bindValue('type_id', $type_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve SSL Type';
            $log_extra = array('SSL Type ID' => $type_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getDnsName($dns_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM dns
            WHERE id = :dns_id");
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve DNS Profile name';
            $log_extra = array('DNS Profile ID' => $dns_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getIpName($ip_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM ip_addresses
            WHERE id = :ip_id");
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve IP Address name';
            $log_extra = array('IP Address ID' => $ip_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getIpAndName($ip_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`, ip
            FROM ip_addresses
            WHERE id = :ip_id");
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve IP Address name & IP Address';
            $log_extra = array('IP Address ID' => $ip_id);
            $this->log->critical($log_message, $log_extra);
            return array($log_message, '');

        } else {

            return array($result->name, $result->ip);

        }
    }

    public function getSslProvider($ssl_provider_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM ssl_providers
            WHERE id = :ssl_provider_id");
        $stmt->bindValue('ssl_provider_id', $ssl_provider_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve SSL Provider name';
            $log_extra = array('SSL Provider ID' => $ssl_provider_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getOwner($owner_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM owners
            WHERE id = :owner_id");
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Owner name';
            $log_extra = array('Owner ID' => $owner_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getCat($cat_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM categories
            WHERE id = :cat_id");
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Category';
            $log_extra = array('Category ID' => $cat_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getCatId($category_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = :category_name");
        $stmt->bindValue('category_name', $category_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Category ID';
            $log_extra = array('Category' => $category_name, 'Category ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getFeeId($registrar_id, $tld)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM fees
            WHERE registrar_id = :registrar_id
              AND tld = :tld");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Fee ID';
            $log_extra = array('Registrar ID' => $registrar_id, 'TLD' => $tld, 'Fee ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getHostId($host_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM hosting
            WHERE name = :host_name");
        $stmt->bindValue('host_name', $host_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Hosting ID';
            $log_extra = array('Host Name' => $host_name, 'Host ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getRegistrarId($registrar_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM registrars
            WHERE name = :registrar_name");
        $stmt->bindValue('registrar_name', $registrar_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar ID';
            $log_extra = array('Registrar' => $registrar_name, 'Registrar ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getIpAddressId($ip_address)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM ip_addresses
            WHERE ip = :ip_address");
        $stmt->bindValue('ip_address', $ip_address, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve IP Address ID';
            $log_extra = array('IP Address' => $ip_address, 'IP Address ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getRegistrarAccountId($registrar_id, $registrar_username)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM registrar_accounts
            WHERE registrar_id = :registrar_id
              AND username = :registrar_username
            LIMIT 1");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('registrar_username', $registrar_username, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar Account ID';
            $log_extra = array('Registrar ID' => $registrar_id, 'Registrar Account ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getOwnerId($owner_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM owners
            WHERE name = :owner_name
            LIMIT 1");
        $stmt->bindValue('owner_name', $owner_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Owner ID';
            $log_extra = array('Owner' => $owner_name, 'Owner ID' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function checkForCatByName($category_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM categories
            WHERE name = :category_name");
        $stmt->bindValue('category_name', $category_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForFee($registrar_id, $tld)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM fees
            WHERE registrar_id = :registrar_id
              AND tld = :tld");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForHostByName($host_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM hosting
            WHERE name = :host_name");
        $stmt->bindValue('host_name', $host_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForRegistrarByName($registrar_name)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM registrars
            WHERE name = :registrar_name");
        $stmt->bindValue('registrar_name', $registrar_name, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForIpAddress($ip_address)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM ip_addresses
            WHERE ip = :ip_address");
        $stmt->bindValue('ip_address', $ip_address, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForRegistrarAccount($registrar_id, $registrar_username)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM registrar_accounts
            WHERE registrar_id = :registrar_id
              AND username = :registrar_username
            LIMIT 1");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('registrar_username', $registrar_username, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function checkForDnsProfile($dns_servers)
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            ORDER BY update_time DESC, insert_time DESC")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $existing_dns = array($row->dns1, $row->dns2, $row->dns3, $row->dns4, $row->dns5, $row->dns6,
                    $row->dns7, $row->dns8, $row->dns9, $row->dns10);
                $filtered_dns = array_filter($existing_dns);

                // lower case the DNS servers for accurate matching
                $lower_value = array();
                foreach ($filtered_dns as $value) {
                    $lower_value[] = strtolower($value);
                }
                $filtered_dns = $lower_value;

                // If there's a match return the ID
                if (array_count_values($filtered_dns) == array_count_values($dns_servers)) {

                    return $row->id;

                }

            }

        }

        return false;
    }

    public function checkForOwner($registrar_account_owner)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `id`
            FROM owners
            WHERE name = :registrar_account_owner
            LIMIT 1");
        $stmt->bindValue('registrar_account_owner', $registrar_account_owner, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            return false;

        }

        return true;

    }

    public function getUsername($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT username
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar Account Username';
            $log_extra = array('Account ID' => $account_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function createCategory($category, $stakeholder, $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                INSERT INTO categories
                (`name`, stakeholder, notes, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:category, :stakeholder, :notes, :creation_type_id, :created_by, :insert_time, :update_time)");
        $stmt->bindValue('category', $category, \PDO::PARAM_STR);
        $stmt->bindValue('stakeholder', $stakeholder, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createFee($registrar_id, $tld, $registration_fee, $renewal_fee, $transfer_fee, $privacy_fee,
                              $misc_fee, $currency)
    {
        $pdo = $this->deeb->cnxx;

        $currency_id = $this->currency->getCurrencyId($currency);

        $stmt = $pdo->prepare("
                INSERT INTO fees
                (registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id,
                 fee_fixed, insert_time, update_time)
                VALUES
                (:registrar_id, :tld, :initial_fee, :renewal_fee, :transfer_fee, :privacy_fee, :misc_fee, :currency_id,
                 1, :insert_time, :update_time)");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->bindValue('initial_fee', strval($registration_fee), \PDO::PARAM_STR);
        $stmt->bindValue('renewal_fee', strval($renewal_fee), \PDO::PARAM_STR);
        $stmt->bindValue('transfer_fee', strval($transfer_fee), \PDO::PARAM_STR);
        $stmt->bindValue('privacy_fee', strval($privacy_fee), \PDO::PARAM_STR);
        $stmt->bindValue('misc_fee', strval($misc_fee), \PDO::PARAM_STR);
        $stmt->bindValue('currency_id', $currency_id, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createCurrencyConversion($currency, $user_id)
    {
        $pdo = $this->deeb->cnxx;

        $currency_id = $this->currency->getCurrencyId($currency);

        $stmt = $pdo->prepare("
            INSERT INTO currency_conversions
            (currency_id, user_id, conversion, insert_time, update_time)
            VALUES
            (:currency_id, :user_id, '1', :timestamp_insert, :timestamp_update)");
        $stmt->bindValue('currency_id', $currency_id, \PDO::PARAM_INT);
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('timestamp_insert', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('timestamp_update', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createDnsProfile($name, $dns_servers, $dns_ip_1, $dns_ip_2, $dns_ip_3, $dns_ip_4, $dns_ip_5,
                                     $dns_ip_6, $dns_ip_7, $dns_ip_8, $dns_ip_9, $dns_ip_10, $notes,
                                     $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $new_servers = array();
        $count = 0;

        // Make sure the supplied DNS servers are valid
        foreach ($dns_servers as $server) {

            $new_servers[$count++] = strtolower($server);

        }

        $clean_servers = array_filter($new_servers);
        $number_of_servers = count($clean_servers);

        $stmt = $pdo->prepare("
                INSERT INTO dns
                (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7,
                 ip8, ip9, ip10, notes, number_of_servers, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:dns_name, :new_servers0, :new_servers1, :new_servers2, :new_servers3, :new_servers4,
                 :new_servers5, :new_servers6, :new_servers7, :new_servers8, :new_servers9, :new_ip1, :new_ip2,
                 :new_ip3, :new_ip4, :new_ip5, :new_ip6, :new_ip7, :new_ip8, :new_ip9, :new_ip10, :notes,
                 :number_of_servers, :creation_type_id, :created_by, :insert_time, :update_time)");

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

        $stmt->bindValue('dns_name', $name, \PDO::PARAM_STR);
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
        $stmt->bindValue('new_ip1', $dns_ip_1, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip2', $dns_ip_2, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip3', $dns_ip_3, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip4', $dns_ip_4, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip5', $dns_ip_5, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip6', $dns_ip_6, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip7', $dns_ip_7, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip8', $dns_ip_8, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip9', $dns_ip_9, \PDO::PARAM_STR);
        $stmt->bindValue('new_ip10', $dns_ip_10, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('number_of_servers', $number_of_servers, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createHost($name, $url, $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                INSERT INTO hosting
                (`name`, url, notes, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:name, :url, :notes, :creation_type_id, :created_by, :insert_time, :update_time)");
        $stmt->bindValue('name', $name, \PDO::PARAM_STR);
        $stmt->bindValue('url', $url, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createRegistrar($registrar, $registrar_url, $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id
            FROM api_registrars
            WHERE `name` = :registrar");
        $stmt->bindValue('registrar', $registrar, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $api_registrar_id = 0;

        } else {

            $api_registrar_id = $result;

        }

        $stmt = $pdo->prepare("
                INSERT INTO registrars
                (`name`, url, api_registrar_id, notes, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:registrar, :registrar_url, :api_registrar_id, :notes, :creation_type_id, :created_by, :insert_time,
                 :update_time)");
        $stmt->bindValue('registrar', $registrar, \PDO::PARAM_STR);
        $stmt->bindValue('registrar_url', $registrar_url, \PDO::PARAM_STR);
        $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createRegistrarAccount($owner_id, $registrar_id, $registrar_account_email_address,
                                           $registrar_username, $registrar_account_password, $registrar_account_id,
                                           $reseller, $reseller_id, $api_app_name, $api_key, $api_secret, $api_ip_id,
                                           $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                INSERT INTO registrar_accounts
                (owner_id, registrar_id, email_address, username, password, account_id, reseller, reseller_id,
                 api_app_name, api_key, api_secret, api_ip_id, notes, creation_type_id, created_by, insert_time,
                 update_time)
                VALUES
                (:owner_id, :registrar_id, :email_address, :username, :password, :account_id, :reseller, :reseller_id,
                 :api_app_name, :api_key, :api_secret, :api_ip_id, :notes, :creation_type_id, :created_by, :insert_time,
                 :update_time)");
        $stmt->bindValue('owner_id', $owner_id, \PDO::PARAM_INT);
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('email_address', $registrar_account_email_address, \PDO::PARAM_STR);
        $stmt->bindValue('username', $registrar_username, \PDO::PARAM_STR);
        $stmt->bindValue('password', $registrar_account_password, \PDO::PARAM_STR);
        $stmt->bindValue('account_id', $registrar_account_id, \PDO::PARAM_STR);
        $stmt->bindValue('reseller', $reseller, \PDO::PARAM_INT);
        $stmt->bindValue('reseller_id', $reseller_id, \PDO::PARAM_STR);
        $stmt->bindValue('api_app_name', $api_app_name, \PDO::PARAM_STR);
        $stmt->bindValue('api_key', $api_key, \PDO::PARAM_STR);
        $stmt->bindValue('api_secret', $api_secret, \PDO::PARAM_STR);
        $stmt->bindValue('api_ip_id', $api_ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createIpAddress($name, $ip_address, $rdns, $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                INSERT INTO ip_addresses
                (`name`, ip, rdns, notes, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:name, :ip_address, :rdns, :notes, :creation_type_id, :created_by, :insert_time, :update_time)");
        $stmt->bindValue('name', $name, \PDO::PARAM_STR);
        $stmt->bindValue('ip_address', $ip_address, \PDO::PARAM_STR);
        $stmt->bindValue('rdns', $rdns, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

    public function createOwner($registrar_account_owner, $notes, $creation_type_id, $created_by)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                INSERT INTO owners
                (`name`, notes, creation_type_id, created_by, insert_time, update_time)
                VALUES
                (:name, :notes, :creation_type_id, :created_by, :insert_time, :update_time)");
        $stmt->bindValue('name', $registrar_account_owner, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('created_by', $created_by, \PDO::PARAM_INT);
        $bind_timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $pdo->lastInsertId('id');

    }

} //@formatter:on
