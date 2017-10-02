<?php
/**
 * /classes/DomainMOD/Login.php
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

        return $stmt->fetch();
    }

    public function getSystemSettings()
    {
        return $this->deeb->cnxx->query("
            SELECT full_url, db_version, upgrade_available, email_address, large_mode, default_category_domains,
                default_category_ssl, default_dns, default_host, default_ip_address_domains,
                default_ip_address_ssl, default_owner_domains, default_owner_ssl, default_registrar,
                default_registrar_account, default_ssl_provider_account, default_ssl_type, default_ssl_provider,
                expiration_days, local_php_log
            FROM settings")->fetch();
    }

    public function getUserSettings($user_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT default_currency, default_timezone, default_category_domains, default_category_ssl, default_dns,
                default_host, default_ip_address_domains, default_ip_address_ssl, default_owner_domains,
                default_owner_ssl, default_registrar, default_registrar_account, default_ssl_provider_account,
                default_ssl_type, default_ssl_provider, expiration_emails, number_of_domains,
                number_of_ssl_certs, display_domain_owner, display_domain_registrar, display_domain_account,
                display_domain_expiry_date, display_domain_category, display_domain_dns, display_domain_host,
                display_domain_ip, display_domain_host, display_domain_tld, display_domain_fee,
                display_ssl_owner, display_ssl_provider, display_ssl_account, display_ssl_domain,
                display_ssl_type, display_ssl_ip, display_ssl_category, display_ssl_expiry_date, display_ssl_fee,
                display_inactive_assets, display_dw_intro_page
            FROM user_settings
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getCurrencyInfo($currency)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`, symbol, symbol_order, symbol_space
            FROM currencies
            WHERE currency = :currency");
        $stmt->bindValue('currency', $currency, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch();
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
