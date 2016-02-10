<?php
/**
 * /_includes/updates/2.0038-2.0047.inc.php
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
// upgrade database from 2.0038 to 2.0039
if ($current_db_version === '2.0038') {


    $sql = "ALTER TABLE `ssl_certs`
                ADD `ip_id` INT(10) NOT NULL AFTER `type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_certs`
                ADD `cat_id` INT(10) NOT NULL AFTER `ip_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id, cat_id, ip_id
                FROM domains";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE ssl_certs
                           SET cat_id = '" . $row->cat_id . "',
                                  ip_id = '" . $row->ip_id . "',
                               update_time = '" . $time->stamp() . "'
                           WHERE domain_id = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

    }

    $sql = "ALTER TABLE `user_settings`
                ADD `display_ssl_ip` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `display_ssl_category` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_ip`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0039',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0039';

}

// upgrade database from 2.0039 to 2.004
if ($current_db_version === '2.0039') {

    $sql = "ALTER TABLE `user_settings`
                ADD `default_category` INT(10) NOT NULL DEFAULT '1' AFTER `default_currency`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_category` INT(10) NOT NULL DEFAULT '1' AFTER `default_currency`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM categories
                WHERE default_category = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_category = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_category = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_category'] = $row->id;
        $_SESSION['s_system_default_category'] = $row->id;

    }

    $sql = "ALTER TABLE `categories`
                DROP `default_category`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_dns` INT(10) NOT NULL DEFAULT '1' AFTER `default_category`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_dns` INT(10) NOT NULL DEFAULT '1' AFTER `default_category`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM dns
                WHERE default_dns = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_dns = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_dns = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_dns'] = $row->id;
        $_SESSION['s_system_default_dns'] = $row->id;

    }

    $sql = "ALTER TABLE `dns`
                DROP `default_dns`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_host` INT(10) NOT NULL DEFAULT '1' AFTER `default_dns`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_host` INT(10) NOT NULL DEFAULT '1' AFTER `default_dns`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM hosting
                WHERE default_host = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_host = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_host = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_host'] = $row->id;
        $_SESSION['s_system_default_host'] = $row->id;

    }

    $sql = "ALTER TABLE `hosting`
                DROP `default_host`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_ip_address` INT(10) NOT NULL DEFAULT '1' AFTER `default_host`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_ip_address` INT(10) NOT NULL DEFAULT '1' AFTER `default_host`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM ip_addresses
                WHERE default_ip_address = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_ip_address = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_ip_address = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_ip_address'] = $row->id;
        $_SESSION['s_system_default_ip_address'] = $row->id;

    }

    $sql = "ALTER TABLE `ip_addresses`
                DROP `default_ip_address`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_owner` INT(10) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_owner` INT(10) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM owners
                WHERE default_owner = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_owner = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_owner = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_owner'] = $row->id;
        $_SESSION['s_system_default_owner'] = $row->id;

    }

    $sql = "ALTER TABLE `owners`
                DROP `default_owner`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_registrar` INT(10) NOT NULL DEFAULT '1' AFTER `default_owner`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_registrar` INT(10) NOT NULL DEFAULT '1' AFTER `default_owner`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM registrars
                WHERE default_registrar = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_registrar = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_registrar = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_registrar'] = $row->id;
        $_SESSION['s_system_default_registrar'] = $row->id;

    }

    $sql = "ALTER TABLE `registrars`
                DROP `default_registrar`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_registrar_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_registrar_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM registrar_accounts
                WHERE default_account = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_registrar_account = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_registrar_account = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_registrar_account'] = $row->id;
        $_SESSION['s_system_default_registrar_account'] = $row->id;

    }

    $sql = "ALTER TABLE `registrar_accounts`
                DROP `default_account`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar_account`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar_account`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM ssl_accounts
                WHERE default_account = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_ssl_provider_account = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_ssl_provider_account = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_ssl_provider_account'] = $row->id;
        $_SESSION['s_system_default_ssl_provider_account'] = $row->id;

    }

    $sql = "ALTER TABLE `ssl_accounts`
                DROP `default_account`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_ssl_type` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_provider_account`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_ssl_type` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_provider_account`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM ssl_cert_types
                WHERE default_type = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_ssl_type = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_ssl_type = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_ssl_type'] = $row->id;
        $_SESSION['s_system_default_ssl_type'] = $row->id;

    }

    $sql = "ALTER TABLE `ssl_cert_types`
                DROP `default_type`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_ssl_provider` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_type`";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                ADD `default_ssl_provider` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_type`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT id
                FROM ssl_providers
                WHERE default_provider = '1'
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_ssl_provider = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $sql_update = "UPDATE settings
                           SET default_ssl_provider = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update);

        $_SESSION['s_default_ssl_provider'] = $row->id;
        $_SESSION['s_system_default_ssl_provider'] = $row->id;

    }

    $sql = "ALTER TABLE `ssl_providers`
                DROP `default_provider`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET default_timezone = timezone";
    $result = mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `settings`
                DROP `timezone`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`";
    $result = mysqli_query($connection, $sql);

    $sql = "SELECT default_timezone
                FROM settings
                ORDER BY id DESC
                LIMIT 1";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {
        $temp_default_system_timezone = $row->default_timezone;
    }

    $sql = "UPDATE user_settings
                SET default_timezone = '" . $temp_default_system_timezone . "'";
    $result = mysqli_query($connection, $sql);

    $_SESSION['s_default_timezone'] = $temp_default_system_timezone;
    $_SESSION['s_system_default_timezone'] = $temp_default_system_timezone;

    $sql = "ALTER TABLE `settings`
                DROP `default_currency`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                DROP `default_timezone`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.004',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.004';

}

// upgrade database from 2.004 to 2.0041
if ($current_db_version === '2.004') {

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id, default_category_domains, default_ip_address_domains, default_owner_domains
                FROM user_settings";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_update = "UPDATE user_settings
                           SET default_category_ssl = '" . $row->default_category_domains . "',
                                  default_ip_address_ssl = '" . $row->default_ip_address_domains . "',
                               default_owner_ssl = '" . $row->default_owner_domains . "'
                           WHERE id = '" . $row->id . "'";
        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

    }

    $sql = "SELECT default_category_domains, default_ip_address_domains, default_owner_domains
                FROM user_settings
                WHERE user_id = '" . $_SESSION['s_user_id'] . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {

        $default_category_domains = $row->default_category_domains;
        $default_ip_address_domains = $row->default_ip_address_domains;
        $default_owner_domains = $row->default_owner_domains;

    }

    $_SESSION['s_default_category_domains'] = $default_category_domains;
    $_SESSION['s_default_category_ssl'] = $default_category_domains;
    $_SESSION['s_default_ip_address_domains'] = $default_ip_address_domains;
    $_SESSION['s_default_ip_address_ssl'] = $default_ip_address_domains;
    $_SESSION['s_default_owner_domains'] = $default_owner_domains;
    $_SESSION['s_default_owner_ssl'] = $default_owner_domains;

    $sql = "ALTER TABLE `settings`
                CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT default_category_domains, default_ip_address_domains, default_owner_domains
                FROM settings";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $default_category_domains = $row->default_category_domains;
        $default_ip_address_domains = $row->default_ip_address_domains;
        $default_owner_domains = $row->default_owner_domains;

    }

    $sql = "UPDATE settings
                SET default_category_ssl = '" . $default_category_domains . "',
                    default_ip_address_ssl = '" . $default_ip_address_domains . "',
                    default_owner_ssl = '" . $default_owner_domains . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['s_system_default_category_domains'] = $default_category_domains;
    $_SESSION['s_system_default_category_ssl'] = $default_category_domains;
    $_SESSION['s_system_default_ip_address_domains'] = $default_ip_address_domains;
    $_SESSION['s_system_default_ip_address_ssl'] = $default_ip_address_domains;
    $_SESSION['s_system_default_owner_domains'] = $default_owner_domains;
    $_SESSION['s_system_default_owner_ssl'] = $default_owner_domains;

    $sql = "ALTER TABLE `settings`
                CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
                CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0041',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0041';

}

// upgrade database from 2.0041 to 2.0042
if ($current_db_version === '2.0041') {

    // This section was made redundant by DB update v2.005
    /*
    $sql = "CREATE TABLE IF NOT EXISTS `updates` (
            `id` int(10) NOT NULL auto_increment,
            `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `update` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` datetime NOT NULL,
            `update_time` datetime NOT NULL,
            PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO updates
            (name, `update`, insert_time, update_time) VALUES
            ('Domain Manager now contains a Software Updates section!', 'After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-05-04 00:00:00', '2013-05-04 00:00:00'),
            ('Overhaul of Domain Manager Settings Complete!', 'Over the past few months the Domain Manager settings have been undergoing a complete overhaul. The changes include but are not limited to making currency conversions user-based instead of system-based, updating all Domain & SSL default settings to be user-based instead of system-based, separating out Category, IP Address and Owner settings so that Domains & SSLs have thier own options instead of sharing them, adding support for saving passwords for Domain Registrar & SSL Provider accounts, removing the redundant Status and Status Notes fields from the Domains section, and so on.<BR><BR>I\'m constantly trying to improve the software and make it more user-friendly, so if you have any suggestions or feedback feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-05-02 00:00:00', '2013-05-02 00:00:00'),
            ('Currencies have been updated to be user-based instead of system-based', 'Now that Currencies have been re-worked to be user-based, every user in the system can set their own default currency, and this currency will be used for them throughout the system. Every setting, webpage, and report in the Domain Manager system will automatically be converted to display monetary values using the user\'s default currency.', '2013-04-29 00:00:00', '2013-04-29 00:00:00'),
            ('Domain Manager has been converted to UTF-8', 'The entire Domain Manager system has been converted to use the UTF-8 character set in order to allow for support of non-ASCII characters, such as the characters found in some IDNs (Internationalized Domain Names).', '2013-04-27 00:00:01', '2013-04-27 00:00:01'),
            ('Cron job added for automating currency conversions at regular intervals', 'Never worry about having outdated exchange rates again! Domain Manager now includes a cron job that automates currency conversions. This means you can have the cron job set to run overnight, and when you go to use the Domain Manager software in the morning your currency conversions will already be completely up-to-date.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-27 00:00:00', '2013-04-27 00:00:00'),
            ('Reporting section added', 'Domain Manager now includes a handful of reports that can give you valuable insight into your data, and I\'m always on the lookout for more reports that can be added. If you have any new report ideas, or any suggestions for the current reports, feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-04-25 00:00:00', '2013-04-25 00:00:00'),
            ('Added a \'view full notes\' feature to the Domain and SSL Cert edit pages', 'When editing a Domain or SSL certificate, if you want to view the notes but scrolling through the text box just isn\'t your thing, you can now click on a link to view the full notes on a separate page, making them much easier to read.', '2013-04-24 00:00:00', '2013-04-24 00:00:00'),
            ('Remove the (redundant) Domain Status and Status Notes fields', 'Although the Domain Status & Status Notes fields were removed because they were redundant, if you had data stored in either of these fields it would have been appended to the primary Notes field when your Domain Manager database was upgraded. So don\'t worry, dropping these two fields didn\'t cause you to lose any data.', '2013-04-20 00:00:00', '2013-04-20 00:00:00'),
            ('Update the expiration email so that the System Administrator can set the number of days in the future to display in the email', 'Previously when the daily expiration emails were sent out to users they would automatically include the next 60 days of expirations, but this has now been converted to a system setting so that your system administrator can now specify the number of days to include in the email.', '2013-04-19 00:00:01', '2013-04-19 00:00:01'),
            ('A password field has now been added to Registrar & SSL Provider accounts so that passwords can be managed through Domain Manager', '', '2013-04-19 00:00:00', '2013-04-19 00:00:00'),
            ('A new \'Web Hosting\' section has been added to the UI so that you can now keep track of your web hosting providers within Domain Manager', '', '2013-04-17 00:00:00', '2013-04-17 00:00:00'),
            ('Cron job added for sending an email to users about upcoming Domain and SSL Certificate renewals', 'A cron job has now been added to send a daily email to users letting them know about upcoming domain and SSL expirations, and users can subscribe and unsubscribe from this email through their Control Panel.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-15 00:00:00', '2013-04-15 00:00:00'),
            ('A logo has now been added to the Domain Manager software in order to pretty things up a little bit', '', '2013-04-10 00:00:00', '2013-04-10 00:00:00'),
            ('The Domain & SSL search pages have been updated to allow for the exporting of results', '', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
            ('Update the Segments UI to give the user a lot more information and flexibility', 'Now when filtering your domains using a segment, Domain Manager will tell you which domains in the segment are stored in your Domain Manager (indicating whether or not the domain is active or inactive), as well as which domains don\'t match, and lastly it will tell you which domains matched but were filtered out based on your other search criteria. Each of the resulting lists can be easily viewed and exported for your convenience.<BR><BR>It took quite a bit of work to get this feature implemented, but the segment filtering just felt incomplete without it. It was still a very useful feature, but now it\'s incredibly powerful, and I hope to add on the functionality in the future.', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
            ('Test Data System removed, Demo launched', 'In order to focus on the development of the actual Domain Manager software, I\'ve decided to remove the Test Data System entirely. Although this system allowed users to easily generate some test data and get a feel for the software, it complicated the development process and added unecessary overhead to the software as a whole. Most importantly, it took me away from adding other, more useful features to the core software.<BR><BR>Now instead of testing the software by installing it and generating the test data, you can simply visit <a class=\"invisiblelink\" target=\"_blank\" href=\"http://demos.aysmedia.com/domainmanager/\">http://demos.aysmedia.com/domainmanager/</a> to take Domain Manager for a test drive.', '2013-04-06 00:00:00', '2013-04-06 00:00:00'),
            ('A new \'IP Address\' section has been added to the UI so that you can keep track of all your IP Addresses within Domain Manager', '', '2013-03-26 00:00:00', '2013-03-26 00:00:00'),
            ('Support has been added for automatic currency updates!', 'Thanks to Yahoo! Finance\'s free API, I\'m happy to announce that currency conversions have been completely automated. Now instead of having to manually update the conversions one-by-one on a regular basis to ensure proper financial reporting, all you have to do is make sure your default currency is set and your conversion rates will be updated automatically and seemlessly in the background while you use the software.<BR><BR>To say that this feature pleases me would be a huge understatement. I personally use the Domain Manager software on a daily basis, and updating the currency conversions manually was always such a boring, tedious task, and I\'m happy that nobody will ever have to go through that process ever again. If I could give Yahoo! Finance a big hug, I would.', '2013-03-20 00:00:01', '2013-03-20 00:00:01'),
            ('Domain Manager now contains a Software Updates section!', '<em>[This feature was implemented on 2013-05-04, but it seemed appropriate that the very first post in the Software Updates section be information about the new section itself, so the post was duplicated and backdated]</em><BR><BR>After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-03-20 00:00:00', '2013-03-20 00:00:00')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS update_data (
            `id` int(10) NOT NULL auto_increment,
            `user_id` int(10) NOT NULL,
            `update_id` int(10) NOT NULL,
            `insert_time` datetime NOT NULL,
            PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM users";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_updates = "SELECT id
                        FROM `updates`";
        $result_updates = mysqli_query($connection, $sql_updates);

        while ($row_updates = mysqli_fetch_object($result_updates)) {

            $sql_insert = "INSERT INTO
                           update_data
                           (user_id, update_id, insert_time) VALUES
                           ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->stamp() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

    }

    $_SESSION['s_are_there_updates'] = "1";

    $sql = "UPDATE settings
            SET db_version = '2.0042',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    */

    $current_db_version = '2.0042';

}

// upgrade database from 2.0042 to 2.0043
if ($current_db_version === '2.0042') {

    $sql = "ALTER TABLE `segments`
                CHANGE `name` `name` VARCHAR(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0043',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0043';

}

// upgrade database from 2.0043 to 2.0044
if ($current_db_version === '2.0043') {

    $sql = "ALTER TABLE `owners`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `categories`
                CHANGE `name` `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `categories`
                CHANGE `stakeholder` `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `hosting`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
                CHANGE `ip` `ip` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
                CHANGE `rdns` `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '-'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
                CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
                CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrars`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrars`
                CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
                CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
                CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_providers`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_providers`
                CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_cert_types`
                CHANGE `type` `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `users`
                CHANGE `username` `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `users`
                CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
                CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_certs`
                CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0044',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0044';

}

// upgrade database from 2.0044 to 2.0045
if ($current_db_version === '2.0044') {

    $sql = "ALTER TABLE `segments`
                CHANGE `name` `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0045',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0045';

}

// upgrade database from 2.0045 to 2.0046
if ($current_db_version === '2.0045') {

    // This section was made redundant by DB update v2.005
    /*
    $sql = "INSERT INTO updates
            (name, `update`, insert_time, update_time) VALUES
            ('An Export option has been added to all Asset pages', '', '2013-05-06 00:00:00', '2013-05-06 00:00:00')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM users";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_updates = "SELECT id
                        FROM `updates`
                        WHERE name = 'An Export option has been added to all Asset pages'
                          AND insert_time = '2013-05-06 00:00:00'";
        $result_updates = mysqli_query($connection, $sql_updates);

        while ($row_updates = mysqli_fetch_object($result_updates)) {

            $sql_insert = "INSERT INTO
                           update_data
                           (user_id, update_id, insert_time) VALUES
                           ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->stamp() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

    }

    $_SESSION['s_are_there_updates'] = "1";

    $sql = "UPDATE settings
            SET db_version = '2.0046',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    */

    $current_db_version = '2.0046';

}

// upgrade database from 2.0046 to 2.0047
if ($current_db_version === '2.0046') {

    $sql = "ALTER TABLE `hosting`
                ADD `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER name";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0047',
                    update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0047';

}

// upgrade database from 2.0047 to 2.0048
if ($current_db_version === '2.0047') {

    $sql = "CREATE TABLE IF NOT EXISTS `custom_field_types` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO custom_field_types
                (id, name, insert_time) VALUES
                (1, 'Check Box', '" . $time->stamp() . "'),
                (2, 'Text', '" . $time->stamp() . "'),
                (3, 'Text Area', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_fields` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `type_id` INT(10) NOT NULL,
                `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_field_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `domain_id` INT(10) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
                FROM domains";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {

        $full_id_string .= "('" . $row->id . "', '" . $time->stamp() . "'), ";

    }

    $full_id_string_formatted = substr($full_id_string, 0, -2);

    $sql = "INSERT INTO domain_field_data
                (domain_id, insert_time) VALUES
                " . $full_id_string_formatted . "";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $full_id_string = "";
    $full_id_string_formatted = "";

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `type_id` INT(10) NOT NULL,
                `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_field_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `ssl_id` INT(10) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
                FROM ssl_certs";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_object($result)) {

            $full_id_string .= "('" . $row->id . "', '" . $time->stamp() . "'), ";

        }

        $full_id_string_formatted = substr($full_id_string, 0, -2);

        $sql = "INSERT INTO ssl_cert_field_data
                    (ssl_id, insert_time) VALUES
                    " . $full_id_string_formatted . "";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    }

    // This section was made redundant by DB update v2.005
    /*
    $sql = "INSERT INTO updates
            (name, `update`, insert_time, update_time) VALUES
            ('You can now create Custom Domain & SSL Fields!', 'In an effort to allow users more flexibility, as well as track as much data as possible, I\'ve implemented Custom Domain & SSL Fields. Now if there\'s information you want to track for a domain or SSL certificate but the field doesn\'t exist in Domain Manager, you can just add it yourself!<BR><BR>For example, if you wanted to keep track of which domains are currenty setup in Google Analytics, you could create a new Google Analytics check box field and start tracking this information for each of your domains. Or if you were working in a corporate environment and wanted to keep a record of who purchased each of your SSL certificates, you could create a Purchaser Name text field and keep track of this information for every one of your SSL certificates. Combine custom fields with the ability to update them with the Bulk Updater, and the sky\'s the limit in regards to what data you can easily track! (the Bulk Updater currently only supports domains, not SSL certificates)<BR><BR>And when you export your domain & SSL data, the information contained in your custom fields will automatically be included in the exported data.', '2013-05-25 17:00:00', '2013-05-25 17:00:00')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM `updates`
            WHERE name = 'You can now create Custom Domain & SSL Fields!'
              AND insert_time = '2013-05-25 17:00:00'";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) { $temp_update_id = $row->id; }

    $sql = "SELECT id
            FROM users";
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {

        $sql_insert = "INSERT INTO
                       update_data
                       (user_id, update_id, insert_time) VALUES
                       ('" . $row->id . "', '" . $temp_update_id . "', '" . $time->stamp() . "')";
        $result_insert = mysqli_query($connection, $sql_insert);

    }

    $_SESSION['s_are_there_updates'] = "1";

    $sql = "UPDATE settings
            SET db_version = '2.0048',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    */

    $current_db_version = '2.0048';

}
