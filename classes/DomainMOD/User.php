<?php
/**
 * /classes/DomainMOD/User.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
    public $deeb;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->time = new Time();
    }

    public function getAdminId()
    {
        return $this->deeb->cnxx->query("
            SELECT id
            FROM users
            WHERE username = 'admin'")->fetchColumn();
    }

    public function getUserId($username)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM users
            WHERE username = :username");
        $stmt->bindValue('username', $username, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getDefaultCurrency($user_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT default_currency
            FROM user_settings
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result->default_currency;
    }

    public function getFullName($user_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result->first_name . ' ' . $result->last_name;
    }

    public function generatePassword($password_length = 72)
    {
        $character_pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $max_index = strlen($character_pool) - 1;
        $password = '';
        for ($i = 0; $i < $password_length; $i++) {
            $password .= $character_pool[random_int(0, $max_index)];
        }
        return $password;
    }

    /*
     * The token for a password reset link. It's keyed with the user's stored password hash, so it stops working as
     * soon as the password changes, which makes each link single-use.
     */
    public function getResetToken($user_id, $expires, $stored_hash)
    {
        return hash_hmac('sha256', 'password-reset|' . $user_id . '|' . $expires, $stored_hash);
    }

    public function generateHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function setDarkMode()
    {
        $stmt = $this->deeb->cnxx->prepare("
            UPDATE user_settings
            SET dark_mode = :dark_mode,
                update_time = :update_time
            WHERE user_id = :user_id");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('dark_mode', $_SESSION['s_dark_mode'], \PDO::PARAM_INT);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], \PDO::PARAM_INT);
        $stmt->execute();
        return;
    }

} //@formatter:on
