<?php
/**
 * /_includes/updates/2.0038-2.0048.inc.php
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
?>
<?php //@formatter:off

// upgrade database from 2.0038 to 2.0039
if ($current_db_version === '2.0038') {

    $pdo->query("
        ALTER TABLE `ssl_certs`
        ADD `ip_id` INT(10) NOT NULL AFTER `type_id`");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        ADD `cat_id` INT(10) NOT NULL AFTER `ip_id`");

    $result = $pdo->query("
        SELECT id, cat_id, ip_id
        FROM domains")->fetchAll();

    $stmt = $pdo->prepare("
        UPDATE ssl_certs
        SET cat_id = :cat_id,
            ip_id = :ip_id,
            update_time = :timestamp
        WHERE domain_id = :domain_id");
    $stmt->bindParam('cat_id', $bind_cat_id, PDO::PARAM_INT);
    $stmt->bindParam('ip_id', $bind_ip_id, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->bindParam('domain_id', $bind_domain_id, PDO::PARAM_INT);

    foreach ($result as $row) {

        $bind_cat_id = $row->cat_id;
        $bind_ip_id = $row->ip_id;
        $bind_domain_id = $row->id;
        $stmt->execute();

    }

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_ssl_ip` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_ssl_category` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_ip`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0039',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0039';

}

// upgrade database from 2.0039 to 2.004
if ($current_db_version === '2.0039') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_category` INT(10) NOT NULL DEFAULT '1' AFTER `default_currency`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_category` INT(10) NOT NULL DEFAULT '1' AFTER `default_currency`");

    $cat_id = $pdo->query("
        SELECT id
        FROM categories
        WHERE default_category = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_category = '" . $cat_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_category = '" . $cat_id . "'");

    $pdo->query("
        ALTER TABLE `categories`
        DROP `default_category`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_dns` INT(10) NOT NULL DEFAULT '1' AFTER `default_category`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_dns` INT(10) NOT NULL DEFAULT '1' AFTER `default_category`");

    $dns_id = $pdo->query("
        SELECT id
        FROM dns
        WHERE default_dns = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_dns = '" . $dns_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_dns = '" . $dns_id . "'");

    $pdo->query("
        ALTER TABLE `dns`
        DROP `default_dns`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_host` INT(10) NOT NULL DEFAULT '1' AFTER `default_dns`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_host` INT(10) NOT NULL DEFAULT '1' AFTER `default_dns`");

    $hosting_id = $pdo->query("
        SELECT id
        FROM hosting
        WHERE default_host = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_host = '" . $hosting_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_host = '" . $hosting_id . "'");

    $pdo->query("
        ALTER TABLE `hosting`
        DROP `default_host`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_ip_address` INT(10) NOT NULL DEFAULT '1' AFTER `default_host`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_ip_address` INT(10) NOT NULL DEFAULT '1' AFTER `default_host`");

    $ip_id = $pdo->query("
        SELECT id
        FROM ip_addresses
        WHERE default_ip_address = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_ip_address = '" . $ip_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_ip_address = '" . $ip_id . "'");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        DROP `default_ip_address`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_owner` INT(10) NOT NULL DEFAULT '1' AFTER `default_ip_address`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_owner` INT(10) NOT NULL DEFAULT '1' AFTER `default_ip_address`");

    $owner_id = $pdo->query("
        SELECT id
        FROM owners
        WHERE default_owner = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_owner = '" . $owner_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_owner = '" . $owner_id . "'");

    $pdo->query("
        ALTER TABLE `owners`
        DROP `default_owner`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_registrar` INT(10) NOT NULL DEFAULT '1' AFTER `default_owner`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_registrar` INT(10) NOT NULL DEFAULT '1' AFTER `default_owner`");

    $registrar_id = $pdo->query("
        SELECT id
        FROM registrars
        WHERE default_registrar = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_registrar = '" . $registrar_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_registrar = '" . $registrar_id . "'");

    $pdo->query("
        ALTER TABLE `registrars`
        DROP `default_registrar`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_registrar_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_registrar_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar`");

    $registrar_account_id = $pdo->query("
        SELECT id
        FROM registrar_accounts
        WHERE default_account = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_registrar_account = '" . $registrar_account_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_registrar_account = '" . $registrar_account_id . "'");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        DROP `default_account`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar_account`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '1' AFTER `default_registrar_account`");

    $ssl_account_id = $pdo->query("
        SELECT id
        FROM ssl_accounts
        WHERE default_account = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_ssl_provider_account = '" . $ssl_account_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_ssl_provider_account = '" . $ssl_account_id . "'");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        DROP `default_account`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_ssl_type` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_provider_account`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_ssl_type` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_provider_account`");

    $ssl_cert_type_id = $pdo->query("
        SELECT id
        FROM ssl_cert_types
        WHERE default_type = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_ssl_type = '" . $ssl_cert_type_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_ssl_type = '" . $ssl_cert_type_id . "'");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        DROP `default_type`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_ssl_provider` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_type`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_ssl_provider` INT(10) NOT NULL DEFAULT '1' AFTER `default_ssl_type`");

    $ssl_provider_id = $pdo->query("
        SELECT id
        FROM ssl_providers
        WHERE default_provider = '1'
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_ssl_provider = '" . $ssl_provider_id . "'");

    $pdo->query("
        UPDATE settings
        SET default_ssl_provider = '" . $ssl_provider_id . "'");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        DROP `default_provider`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`");

    $pdo->query("
        UPDATE settings
        SET default_timezone = timezone");

    $pdo->query("
        ALTER TABLE `settings`
        DROP `timezone`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`");

    $default_timezone = $pdo->query("
        SELECT default_timezone
        FROM settings
        LIMIT 1")->fetchColumn();

    $pdo->query("
        UPDATE user_settings
        SET default_timezone = '" . $default_timezone . "'");

    $pdo->query("
        ALTER TABLE `settings`
        DROP `default_currency`");

    $pdo->query("
        ALTER TABLE `settings`
        DROP `default_timezone`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.004',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.004';

}

// upgrade database from 2.004 to 2.0041
if ($current_db_version === '2.004') {

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`");

    $pdo->query("
        UPDATE user_settings
        SET default_category_ssl = default_category_domains,
            default_ip_address_ssl = default_ip_address_domains,
            default_owner_ssl = default_owner_domains");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`");

    $pdo->query("
        UPDATE settings
        SET default_category_ssl = default_category_domains,
            default_ip_address_ssl = default_ip_address_domains,
            default_owner_ssl = default_owner_domains");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0041',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0041';

}

// upgrade database from 2.0041 to 2.0042
if ($current_db_version === '2.0041') {

    // This section was made redundant by DB update v2.005
    // (redundant code was here)

    $current_db_version = '2.0042';

}

// upgrade database from 2.0042 to 2.0043
if ($current_db_version === '2.0042') {

    $pdo->query("
        ALTER TABLE `segments`
        CHANGE `name` `name` VARCHAR(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0043',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0043';

}

// upgrade database from 2.0043 to 2.0044
if ($current_db_version === '2.0043') {

    $pdo->query("
        ALTER TABLE `owners`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `categories`
        CHANGE `name` `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `categories`
        CHANGE `stakeholder` `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `hosting`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `ip` `ip` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `rdns` `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '-'");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `registrars`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `registrars`
        CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        CHANGE `type` `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `username` `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0044',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0044';

}

// upgrade database from 2.0044 to 2.0045
if ($current_db_version === '2.0044') {

    $pdo->query("
        ALTER TABLE `segments`
        CHANGE `name` `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0045',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0045';

}

// upgrade database from 2.0045 to 2.0046
if ($current_db_version === '2.0045') {

    // This section was made redundant by DB update v2.005
    // (redundant code was here)

    $current_db_version = '2.0046';

}

// upgrade database from 2.0046 to 2.0047
if ($current_db_version === '2.0046') {

    $pdo->query("
        ALTER TABLE `hosting`
        ADD `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER name");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0047',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0047';

}

// upgrade database from 2.0047 to 2.0048
if ($current_db_version === '2.0047') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `custom_field_types` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO custom_field_types
        (id, name, insert_time)
        VALUES
        (1, 'Check Box', '" . $timestamp . "'),
        (2, 'Text', '" . $timestamp . "'),
        (3, 'Text Area', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_fields` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_field_data` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `domain_id` INT(10) NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $result = $pdo->query("
        SELECT id
        FROM domains")->fetchAll();

    foreach ($result as $row) {

        $full_id_string .= "('" . $row->id . "', '" . $timestamp . "'), ";

    }

    $full_id_string_formatted = substr($full_id_string, 0, -2);

    $pdo->query("
        INSERT INTO domain_field_data
        (domain_id, insert_time)
        VALUES
        " . $full_id_string_formatted . "");

    $full_id_string = "";
    $full_id_string_formatted = "";

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_field_data` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `ssl_id` INT(10) NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $result = $pdo->query("
        SELECT id
        FROM ssl_certs")->fetchAll();

    foreach ($result as $row) {

        $full_id_string .= "('" . $row->id . "', '" . $timestamp . "'), ";

    }

    $full_id_string_formatted = substr($full_id_string, 0, -2);

    $pdo->query("
        INSERT INTO ssl_cert_field_data
        (ssl_id, insert_time)
        VALUES
        " . $full_id_string_formatted . "");

    // This section was made redundant by DB update v2.005
    // (redundant code was here)

    $current_db_version = '2.0048';

}
//@formatter:on
