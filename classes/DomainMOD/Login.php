<?php
/**
 * /classes/DomainMOD/Login.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

class Login
{
    public $deeb;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->time = new Time();
    }

    public function getUserInfo($user_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT first_name, last_name, username, email_address, new_password, admin, `read_only`, number_of_logins,
                last_login
            FROM users
            WHERE id = :user_id
              AND active = '1'");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function getSystemSettings()
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT full_url, db_version, upgrade_available, email_address, large_mode, default_category_domains,
                default_category_ssl, default_dns, default_host, default_ip_address_domains,
                default_ip_address_ssl, default_owner_domains, default_owner_ssl, default_registrar,
                default_registrar_account, default_ssl_provider_account, default_ssl_type, default_ssl_provider,
                expiration_days, email_signature, currency_converter, local_php_log
            FROM settings");
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function getUserSettings($user_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT us.default_language, us.default_currency, us.default_timezone, us.default_category_domains, us.default_category_ssl,
                us.default_dns, us.default_host, us.default_ip_address_domains, us.default_ip_address_ssl, us.default_owner_domains,
                us.default_owner_ssl, us.default_registrar, us.default_registrar_account, us.default_ssl_provider_account,
                us.default_ssl_type, us.default_ssl_provider, us.expiration_emails, us.number_of_domains,
                us.number_of_ssl_certs, us.display_domain_owner, us.display_domain_registrar, us.display_domain_account,
                us.display_domain_expiry_date, us.display_domain_category, us.display_domain_dns, us.display_domain_host,
                us.display_domain_ip, us.display_domain_host, us.display_domain_tld, us.display_domain_fee,
                us.display_ssl_owner, us.display_ssl_provider, us.display_ssl_account, us.display_ssl_domain,
                us.display_ssl_type, us.display_ssl_ip, us.display_ssl_category, us.display_ssl_expiry_date, us.display_ssl_fee,
                us.display_inactive_assets, us.display_dw_intro_page, us.dark_mode, lang.name AS language_name
            FROM user_settings AS us, languages AS lang
            WHERE us.default_language = lang.language
              AND us.user_id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function setLastLogin($user_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE users
            SET last_login = :last_login,
                number_of_logins = number_of_logins + 1,
                update_time = :update_time
            WHERE id = :user_id");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('last_login', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();

    }

} //@formatter:on
