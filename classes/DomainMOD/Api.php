<?php
/**
 * /classes/DomainMOD/Api.php
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

class Api
{
    public $assets;
    public $deeb;
    public $error;
    public $log;

    public function __construct()
    {
        $this->assets = new Assets();
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.api');
    }

    public function getKey($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT api_key
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getKeySecret($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT api_key, api_secret
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve API Key & API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message);

        } else {

            return array($result->api_key, $result->api_secret);

        }
    }

    public function getUserKey($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT username, api_key
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Username & API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message);

        } else {

            return array($result->username, $result->api_key);

        }
    }

    public function getUserAppSecret($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT username, api_app_name, api_secret
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Username, API App Name, & API Secret';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message, $log_message);

        } else {

            return array($result->username, $result->api_app_name, $result->api_secret);

        }
    }

    public function getUserKeyIp($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT ra.username, ra.api_key, ip.ip
            FROM registrar_accounts AS ra, ip_addresses AS ip
            WHERE ra.api_ip_id = ip.id
              AND ra.id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Username, API Key, & IP Address';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message, $log_message);

        } else {

            return array($result->username, $result->api_key, $result->ip);

        }
    }

    public function getResellerIdKey($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT reseller_id, api_key
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Reseller ID & API Key';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message);

        } else {

            return array($result->reseller_id, $result->api_key);

        }
    }

    public function getAccountId($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT account_id
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Registrar Account ID';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message);

        } else {

            return $result->account_id;

        }
    }

    public function getUserPass($account_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT username, `password`
            FROM registrar_accounts
            WHERE id = :account_id");
        $stmt->bindValue('account_id', $account_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if (!$result) {

            $log_message = 'Unable to retrieve Username & Password';
            $log_extra = array('Account ID' => $account_id, 'Registrar' => $this->assets->getRegistrarByAcc($account_id),
                               'Account Username' => $this->assets->getUsername($account_id));
            $this->log->critical($log_message, $log_extra);
            return array($log_message, $log_message);

        } else {

            return array($result->username, $result->password);

        }
    }

    public function getApiRegistrarName($api_registrar_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM api_registrars
            WHERE id = :api_registrar_id");
        $stmt->bindValue('api_registrar_id', $api_registrar_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve API Registrar Name';
            $log_extra = array('API Registrar ID' => $api_registrar_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on
