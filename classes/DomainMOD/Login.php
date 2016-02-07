<?php
/**
 * /classes/DomainMOD/Login.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class Login
{

    public function getUserInfo($connection, $username, $password)
    {

        $sql = "SELECT id, first_name, last_name, username, email_address, new_password, admin, number_of_logins,
                       last_login
                FROM users
                WHERE username = '" . $username . "'
                  AND password = password('" . $password . "')
                  AND active = '1'";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getSystemSettings($connection)
    {

        $sql = "SELECT full_url, db_version, upgrade_available, email_address, default_category_domains,
                       default_category_ssl, default_dns, default_host, default_ip_address_domains,
                       default_ip_address_ssl, default_owner_domains, default_owner_ssl, default_registrar,
                       default_registrar_account, default_ssl_provider_account, default_ssl_type, default_ssl_provider,
                       expiration_email_days
                FROM settings";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getUserSettings($connection, $user_id)
    {

        $sql = "SELECT default_currency, default_timezone, default_category_domains, default_category_ssl, default_dns,
                       default_host, default_ip_address_domains, default_ip_address_ssl, default_owner_domains,
                       default_owner_ssl, default_registrar, default_registrar_account, default_ssl_provider_account,
                       default_ssl_type, default_ssl_provider, number_of_domains, number_of_ssl_certs,
                       display_domain_owner, display_domain_registrar, display_domain_account,
                       display_domain_expiry_date, display_domain_category, display_domain_dns, display_domain_host,
                       display_domain_ip, display_domain_host, display_domain_tld, display_domain_fee,
                       display_ssl_owner, display_ssl_provider, display_ssl_account, display_ssl_domain,
                       display_ssl_type, display_ssl_ip, display_ssl_category, display_ssl_expiry_date, display_ssl_fee,
                       display_inactive_assets, display_dw_intro_page
                FROM user_settings
                WHERE user_id = '" . $user_id . "'";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function getCurrencyInfo($connection, $currency)
    {

        $sql = "SELECT `name`, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = '" . $currency . "'";
        $result = mysqli_query($connection, $sql);

        return $result;

    }

    public function setLastLogin($connection, $user_id, $email_address)
    {

        $time = new Time();
        $timestamp = $time->time();

        $sql = "UPDATE users
                SET last_login = '" . $timestamp . "',
                    number_of_logins = number_of_logins + 1,
                    update_time = '" . $timestamp . "'
                WHERE id = '" . $user_id . "'
                  AND email_address = '" . $email_address . "'";
        mysqli_query($connection, $sql);

    }

}
