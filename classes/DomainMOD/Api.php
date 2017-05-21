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
    public function __construct()
    {
        $this->system = new System();
        $this->error = new Error();
        $this->log = new Log('api.class');
        $this->assets = new Assets();
    }

    public function getKey($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT api_key
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getKeySecret($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT api_key, api_secret
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve API Key';
            $log_message2 = 'Unable to retrieve API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            return array($log_message1, $log_message2);

        } else {

            return array($result->api_key, $result->api_secret);

        }
    }

    public function getUserKey($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT username, api_key
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            return array($log_message1, $log_message2);

        } else {

            return array($result->username, $result->api_key);

        }
    }

    public function getUserAppSecret($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT username, api_app_name, api_secret
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API App Name';
            $log_message3 = 'Unable to retrieve API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            $this->log->error($log_message3, $log_extra);
            return array($log_message1, $log_message2, $log_message3);

        } else {

            return array($result->username, $result->api_app_name, $result->api_secret);

        }
    }

    public function getUserKeyIp($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT ra.username, ra.api_key, ip.ip
                                              FROM registrar_accounts AS ra, ip_addresses AS ip
                                              WHERE ra.api_ip_id = ip.id
                                                AND ra.id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve API Key';
            $log_message3 = 'Unable to retrieve IP Address';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            $this->log->error($log_message3, $log_extra);
            return array($log_message1, $log_message2, $log_message3);

        } else {

            return array($result->username, $result->api_key, $result->ip);

        }
    }

    public function getReselleridKey($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT reseller_id, api_key
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve Reseller ID';
            $log_message2 = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            return array($log_message1, $log_message2);

        } else {

            return array($result->reseller_id, $result->api_key);

        }
    }

    public function getUserPass($account_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT username, `password`
                                              FROM registrar_accounts
                                              WHERE id = :account_id");
        $tmpq->execute(['account_id' => $account_id]);
        $result = $tmpq->fetch();

        if (!$result) {

            $log_message1 = 'Unable to retrieve Username';
            $log_message2 = 'Unable to retrieve Password';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrar($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->error($log_message1, $log_extra);
            $this->log->error($log_message2, $log_extra);
            return array($log_message1, $log_message2);

        } else {

            return array($result->username, $result->password);

        }
    }

    public function getApiRegistrarName($api_registrar_id)
    {
        $tmpq = $this->system->db()->prepare("SELECT `name`
                                              FROM api_registrars
                                              WHERE id = :api_registrar_id");
        $tmpq->execute(['api_registrar_id' => $api_registrar_id]);
        $result = $tmpq->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve API Registrar Name';
            $log_extra = array('API Registrar ID' => $api_registrar_id);
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on
