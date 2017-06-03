<?php
/**
 * /classes/DomainMOD/User.php
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

class User
{
    public $system;

    public function __construct()
    {
        $this->system = new System();
    }

    public function getAdminId()
    {
        $pdo = $this->system->db();

        $stmt = $pdo->query("
            SELECT id
            FROM users
            WHERE username = 'admin'");
        return $stmt->fetchColumn();
    }

    public function getFullName($user_id)
    {
        $pdo = $this->system->db();

        $stmt = $pdo->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        return $result->first_name . ' ' . $result->last_name;
    }

} //@formatter:on
