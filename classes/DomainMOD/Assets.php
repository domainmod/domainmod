<?php
/**
 * /classes/DomainMOD/Assets.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
    public $deeb;
    public $error;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.assets');
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

    public function getHost($host_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM hosting
            WHERE id = :host_id");
        $stmt->bindValue('host_id', $host_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Hosting name';
            $log_extra = array('Hosting ID' => $host_id);
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

} //@formatter:on
