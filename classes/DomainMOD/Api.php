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

    public function getKey($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $api_key = $row->api_key;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return $api_key;
    }

    public function getKeySecret($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT api_key, api_secret
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $api_key = $row->api_key;
                $api_secret = $row->api_secret;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($api_key, $api_secret);
    }

    public function getUserKey($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT username, api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $account_username = $row->username;
                $api_key = $row->api_key;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($account_username, $api_key);
    }

    public function getUserAppSecret($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT username, api_app_name, api_secret
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $account_username = $row->username;
                $api_app_name = $row->api_app_name;
                $api_secret = $row->api_secret;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($account_username, $api_app_name, $api_secret);
    }

    public function getUserKeyIp($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT ra.username, ra.api_key, ip.ip
                FROM registrar_accounts AS ra, ip_addresses AS ip
                WHERE ra.api_ip_id = ip.id
                  AND ra.id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $account_username = $row->username;
                $api_key = $row->api_key;
                $api_ip_address = $row->ip;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($account_username, $api_key, $api_ip_address);
    }

    public function getReselleridKey($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT reseller_id, api_key
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $reseller_id = $row->reseller_id;
                $api_key = $row->api_key;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($reseller_id, $api_key);
    }

    public function getUserPass($dbcon, $account_id)
    {
        $error = new Error();
        $sql = "SELECT username, `password`
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $account_username = $row->username;
                $account_password = $row->password;

            }

        } else {

            echo "No API Credentials Found";
            exit;

        }

        return array($account_username, $account_password);
    }

    public function getApiRegistrarName($dbcon, $api_registrar_id)
    {
        $sql = "SELECT `name`
                FROM api_registrars
                WHERE id = '" . $api_registrar_id . "'";
        $result = mysqli_query($dbcon, $sql);

        while ($row = mysqli_fetch_object($result)) {
            
            $api_registrar_name = $row->name;
        
        }
        
        return $api_registrar_name;
    
    }

} //@formatter:on
