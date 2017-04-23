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

    public function getAdminId($dbcon)
    {
        $sql = "SELECT id
                FROM users
                WHERE username = 'admin'";
        $result = mysqli_query($dbcon, $sql);
        while ($row = mysqli_fetch_object($result)) {
            $admin_id = $row->id;
        }
        return $admin_id;
    }

    public function getFullName($dbcon, $user_id)
    {
        $sql = "SELECT first_name, last_name
                FROM users
                WHERE id = '" . $user_id . "'";
        $result = mysqli_query($dbcon, $sql);
        while ($row = mysqli_fetch_object($result)) {
            $full_name = $row->first_name . ' ' . $row->last_name;
        }
        return $full_name;
    }

    // leave user_id empty to use the primary admin
    public function getDefaultSetting($dbcon, $default_field, $primary_table, $user_id)
    {
        if ($user_id == '') {
            $user_id = $this->getAdminId($dbcon);
        }

        $sql = "SELECT us." . $default_field . "
                FROM users AS u, user_settings AS us
                WHERE u.id = us.user_id
                  AND u.id = '" . $user_id . "'";
        $result = mysqli_query($dbcon, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_confirm = "SELECT id
                            FROM `" . $primary_table . "`
                            WHERE id = '" . $row->{$default_field} . "'";
            $result_confirm = mysqli_query($dbcon, $sql_confirm);

            if ($row->{$default_field} != '0' && mysqli_num_rows($result_confirm) > 0) {

                return $row->{$default_field};

            } else {

                return '';

            }

        }

    }

} //@formatter:on
