<?php
/**
 * /classes/DomainMOD/Assets.php
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

class Assets
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->error = new Error();
        $this->log = new Log('assets.class');
    }

    public function getRegistrar($account_id)
    {

        $sql = "SELECT r.name
                FROM registrars AS r, registrar_accounts AS ra
                WHERE r.id = ra.registrar_id
                  AND ra.id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message = '';

            while ($row = mysqli_fetch_object($result)) {

                return $row->name;

            }

        } else {

            $log_message = 'Unable to retrieve Registrar name';
            $log_extra = array('Account ID' => $account_id);
            $this->log->error($log_message, $log_extra);

        }

        return $log_message;

    }

    public function getUsername($account_id)
    {
        $sql = "SELECT username
                FROM registrar_accounts
                WHERE id = '" . $account_id . "'
                LIMIT 1";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');

        if (mysqli_num_rows($result) > 0) {

            $log_message = '';

            while ($row = mysqli_fetch_object($result)) {

                return $row->username;

            }

        } else {

            $log_message = 'Unable to retrieve Registrar Account Username';
            $log_extra = array('Account ID' => $account_id);
            $this->log->error($log_message, $log_extra);

        }

        return $log_message;

    }

} //@formatter:on
