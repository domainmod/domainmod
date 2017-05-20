<?php
/**
 * /classes/DomainMOD/Api.php
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

class Api
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->error = new Error();
        $this->log = new Log('api.class');
        $this->assets = new Assets($this->db);
    }

    public function getKey($account_id)
    {
        $sql = "SELECT api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message = '';

            while ($row = mysqli_fetch_object($result)) {

                return $row->api_key;

            }

        } else {

            $log_message = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message, $log_extra);

        }

        return $log_message;

    }

    public function getKeySecret($account_id)
    {
        $sql = "SELECT api_key, api_secret
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->api_key, $row->api_secret);

            }

        } else {

            $log_message1 = 'Unable to retrieve API Key';
            $log_message2 = 'Unable to retrieve API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);

        }

        return array($log_message1, $log_message2);
    }

    public function getUserKey($account_id)
    {
        $sql = "SELECT username, api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->username, $row->api_key);

            }

        } else {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);

        }

        return array($log_message1, $log_message2);
    }

    public function getUserAppSecret($account_id)
    {
        $sql = "SELECT username, api_app_name, api_secret
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';
            $log_message3 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->username, $row->api_app_name, $row->api_secret);

            }

        } else {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API App Name';
            $log_message3 = 'Unable to retrieve API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            $this->log->error($log_message3, $log_extra);

        }

        return array($log_message1, $log_message2, $log_message3);
    }

    public function getUserKeyIp($account_id)
    {
        $sql = "SELECT ra.username, ra.api_key, ip.ip
                FROM registrar_accounts AS ra, ip_addresses AS ip
                WHERE ra.api_ip_id = ip.id
                  AND ra.id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';
            $log_message3 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->username, $row->api_key, $row->ip);

            }

        } else {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API Key';
            $log_message3 = 'Unable to retrieve IP Address';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            $this->log->error($log_message3, $log_extra);

        }

        return array($log_message1, $log_message2, $log_message3);
    }

    public function getReselleridKey($account_id)
    {
        $sql = "SELECT reseller_id, api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->reseller_id, $row->api_key);

            }

        } else {

            $log_message1 = 'Unable to retrieve Reseller ID';
            $log_message2 = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);

        }

        return array($log_message1, $log_message2);
    }

    public function getUserPass($account_id)
    {
        $sql = "SELECT username, `password`
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message1 = '';
            $log_message2 = '';

            while ($row = mysqli_fetch_object($result)) {

                return array($row->username, $row->password);

            }

        } else {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve Password';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id), 'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);

        }

        return array($log_message1, $log_message2);
    }

    public function getApiRegistrarName($api_registrar_id)
    {
        $sql = "SELECT `name`
                FROM api_registrars
                WHERE id = '" . $api_registrar_id . "'";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message = '';

            while ($row = mysqli_fetch_object($result)) {

                return $row->name;

            }

        } else {

            $log_message = 'Unable to retrieve API Registrar Name';
            $log_extra = array('API Registrar ID' => $api_registrar_id);
            $this->log->error($log_message, $log_extra);

        }

        return $log_message;
    
    }

} //@formatter:on
