<?php
/**
 * /_includes/updates/4.02.000-4.06.00.inc.php
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

// upgrade database from 4.02.000 to 4.02.001
if ($current_db_version === '4.02.000') {

    $pdo->query("
        UPDATE currencies
        SET symbol = '₺'
        WHERE currency = 'TRY'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `smtp_port` `smtp_port` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '587'");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.02.001',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.02.001';

}

// upgrade database from 4.02.001 to 4.03.000
if ($current_db_version === '4.02.001') {

    $pdo->query("
        UPDATE scheduler
        SET description = '" . "<" . "em>Domains:" . "<" . "/em> Converts all domain entries to lowercase." . "<" . "BR>" . "<" . "BR> " . "<" . "em>TLDs:" . "<" . "/em> Updates all TLD entries to ensure their accuracy." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running smoothly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees.'
        WHERE `name` = 'System Cleanup'");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.03.000',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.03.000';

}

// upgrade database from 4.03.000 to 4.03.001
if ($current_db_version === '4.03.000') {

    $pdo->query("
        UPDATE api_registrars
        SET ret_privacy_status = '1',
            ret_autorenewal_status = '1',
            notes = ''
        WHERE `name` = 'Fabulous'");

    $pdo->query("
        INSERT INTO api_registrars
        (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
         req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
         ret_autorenewal_status, notes, insert_time)
         VALUES
        ('Freenom', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', 'Freenom currently only gives API access to reseller accounts.', '" . $timestamp . "'),
        ('DreamHost', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '0', '1', 'DreamHost does not currently allow the WHOIS privacy status of a domain to be retrieved using their API, so all domains added to the queue from a DreamHost account will have their WHOIS privacy status set to No.', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue_temp` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `account_id` INT(10) NOT NULL,
            `domain` VARCHAR(255) NOT NULL,
            `expiry_date` DATE NOT NULL,
            `ns1` VARCHAR(255) NOT NULL,
            `ns2` VARCHAR(255) NOT NULL,
            `ns3` VARCHAR(255) NOT NULL,
            `ns4` VARCHAR(255) NOT NULL,
            `ns5` VARCHAR(255) NOT NULL,
            `ns6` VARCHAR(255) NOT NULL,
            `ns7` VARCHAR(255) NOT NULL,
            `ns8` VARCHAR(255) NOT NULL,
            `ns9` VARCHAR(255) NOT NULL,
            `ns10` VARCHAR(255) NOT NULL,
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `privacy` TINYINT(1) NOT NULL DEFAULT '0',
            PRIMARY KEY  (`id`),
            KEY `domain` (`domain`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.03.001',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.03.001';

}

// upgrade database from 4.03.001 to 4.03.002
if ($current_db_version === '4.03.001') {

    $pdo->query("
        ALTER TABLE `dw_servers`
        ADD `api_token` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `username`");

    $pdo->query("
        INSERT INTO `api_registrars`
        (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
         req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
         ret_autorenewal_status, notes, insert_time)
         VALUES
        ('Above.com', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "')");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.03.002',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.03.002';

}

// upgrade database from 4.03.002 to 4.04.000
if ($current_db_version === '4.03.002') {

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'");

    $pdo->query("
        ALTER TABLE `domain_queue`
        CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'");

    $pdo->query("
        ALTER TABLE `domain_queue_history`
        CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'");

    $pdo->query("
        ALTER TABLE `domain_queue_temp`
        CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'");

    $pdo->query("
        ALTER TABLE `creation_types`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `creation_types`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `last_login` `last_login` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `users`
        SET `last_login` = '1978-01-23 00:00:00'
        WHERE `last_login` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `users`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `users`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `user_settings`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `user_settings`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `categories`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `categories`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `categories`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `categories`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `hosting`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `hosting`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `hosting`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `hosting`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `owners`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `owners`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `owners`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `owners`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `currencies`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `currencies`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `currencies`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `currencies`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `currency_conversions`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `currency_conversions`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `currency_conversions`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `currency_conversions`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `fees`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `fees`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `fees`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `fees`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_fees`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_fees`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domains`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domains`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_queue`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_queue`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_queue_history`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_queue_history`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_queue_list`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_queue_list`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_queue_list_history`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `custom_field_types`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `custom_field_types`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `custom_field_types`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `custom_field_types`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_fields`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_fields`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_fields`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_fields`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_field_data`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_field_data`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `domain_field_data`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `domain_field_data`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_certs`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_certs`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_types`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_types`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_fields`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_fields`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_fields`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_fields`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_field_data`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_field_data`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_cert_field_data`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_cert_field_data`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dns`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dns`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `registrars`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `registrars`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `registrars`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `registrars`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `registrar_accounts`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `registrar_accounts`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_providers`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_providers`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_accounts`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ssl_accounts`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `segments`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `segments`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `segments`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `segments`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `segment_data`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `segment_data`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ip_addresses`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `ip_addresses`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `timezones`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `timezones`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_start_time` `build_start_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `build_start_time` = '1978-01-23 00:00:00'
        WHERE `build_start_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_end_time` `build_end_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `build_end_time` = '1978-01-23 00:00:00'
        WHERE `build_end_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_start_time_overall` `build_start_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `build_start_time_overall` = '1978-01-23 00:00:00'
        WHERE `build_start_time_overall` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_end_time_overall` `build_end_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `build_end_time_overall` = '1978-01-23 00:00:00'
        WHERE `build_end_time_overall` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `dw_servers`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `last_run` `last_run` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `scheduler`
        SET `last_run` = '1978-01-23 00:00:00'
        WHERE `last_run` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `next_run` `next_run` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `scheduler`
        SET `next_run` = '1978-01-23 00:00:00'
        WHERE `next_run` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `scheduler`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `scheduler`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `api_registrars`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `api_registrars`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `api_registrars`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `api_registrars`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `settings`
        SET `insert_time` = '1978-01-23 00:00:00'
        WHERE `insert_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'");

    $pdo->query("
        UPDATE `settings`
        SET `update_time` = '1978-01-23 00:00:00'
        WHERE `update_time` = '1978-01-23 00:00:01'");

    $pdo->query("
        UPDATE api_registrars
        SET req_ip_address = '1'
        WHERE `name` = 'OpenSRS'");

    $pdo->query("
        INSERT INTO custom_field_types
        (id, `name`, insert_time)
        VALUES
        (4, 'Date', '" . $timestamp . "'),
        (5, 'Time Stamp', '" . $timestamp . "')");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.04.000',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.04.000';

}

// upgrade database from 4.04.000 to 4.04.001
if ($current_db_version === '4.04.000') {

    $pdo->query("
        UPDATE settings
        SET db_version = '4.04.001',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.04.001';

}

// upgrade database from 4.04.001 to 4.05.00
if ($current_db_version === '4.04.001') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `goal_activity` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `type` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `old_version` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `new_version` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `ip` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `agent` longtext COLLATE utf8_unicode_ci NOT NULL,
            `language` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `new_activity` TINYINT(1) NOT NULL DEFAULT '1',
            `insert_time` datetime NOT NULL DEFAULT '1978-01-23 00:00:00',
            `update_time` datetime NOT NULL DEFAULT '1978-01-23 00:00:00',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `debug_mode` TINYINT(1) NOT NULL DEFAULT '0' AFTER `smtp_password`");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `log` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `user_id` INT(10) NOT NULL DEFAULT '0',
            `area` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `level` VARCHAR(9) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `message` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `extra` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `url` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.05.00',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.05.00';

}

// upgrade database from 4.05.00 to 4.05.01
if ($current_db_version === '4.05.00') {

    $pdo->query("
        UPDATE settings
        SET db_version = '4.05.01',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.05.01';

}

// upgrade database from 4.05.01 to 4.05.02
if ($current_db_version === '4.05.01') {

    $pdo->query("
        UPDATE settings
        SET db_version = '4.05.02',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.05.02';

}

// upgrade database from 4.05.02 to 4.05.03
if ($current_db_version === '4.05.02') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `local_php_log` TINYINT(1) NOT NULL DEFAULT '0' AFTER `debug_mode`");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip1` `ip1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip2` `ip2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip3` `ip3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip4` `ip4` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip5` `ip5` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip6` `ip6` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip7` `ip7` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip8` `ip8` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip9` `ip9` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `ip10` `ip10` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `ip` `ip` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        CHANGE `rdns` `rdns` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    $pdo->query("
        ALTER TABLE `goal_activity`
        CHANGE `ip` `ip` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `new_password` `new_password` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `admin` `admin` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `users`
        CHANGE `active` `active` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `expiration_emails` `expiration_emails` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_owner` `display_domain_owner` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_registrar` `display_domain_registrar` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_account` `display_domain_account` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_expiry_date` `display_domain_expiry_date` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_category` `display_domain_category` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_dns` `display_domain_dns` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_host` `display_domain_host` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_ip` `display_domain_ip` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_tld` `display_domain_tld` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_fee` `display_domain_fee` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_owner` `display_ssl_owner` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_provider` `display_ssl_provider` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_account` `display_ssl_account` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_domain` `display_ssl_domain` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_type` `display_ssl_type` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_expiry_date` `display_ssl_expiry_date` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_ip` `display_ssl_ip` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_category` `display_ssl_category` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_ssl_fee` `display_ssl_fee` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_inactive_assets` `display_inactive_assets` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_dw_intro_page` `display_dw_intro_page` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `currencies`
        CHANGE `symbol_order` `symbol_order` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `currencies`
        CHANGE `symbol_space` `symbol_space` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `fees`
        CHANGE `fee_fixed` `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `privacy` `privacy` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `active` `active` TINYINT(2) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `fee_fixed` `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `fee_fixed` `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `active` `active` TINYINT(2) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `fee_fixed` `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        CHANGE `reseller` `reseller` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        CHANGE `reseller` `reseller` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `active` `active` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `inactive` `inactive` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `missing` `missing` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segment_data`
        CHANGE `filtered` `filtered` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_status` `build_status` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `has_ever_been_built` `has_ever_been_built` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `build_status_overall` `build_status_overall` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers`
        CHANGE `has_ever_been_built_overall` `has_ever_been_built_overall` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `is_running` `is_running` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `scheduler`
        CHANGE `active` `active` TINYINT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `settings`
        CHANGE `upgrade_available` `upgrade_available` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dns`
        CHANGE `number_of_servers` `number_of_servers` TINYINT(2) NOT NULL DEFAULT '0'");

    $pdo->query("
        UPDATE api_registrars
        SET ret_privacy_status = '1',
            ret_autorenewal_status = '1',
            notes = ''
        WHERE `name` = 'Fabulous'");

    $pdo->query("
        UPDATE api_registrars
        SET req_ip_address = '1'
        WHERE `name` = 'Dynadot'");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.05.03',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.05.03';

}

// upgrade database from 4.05.03 to 4.06.00
if ($current_db_version === '4.05.03') {

    $pdo->query("
        ALTER TABLE `creation_types` CHANGE `id` `id` TINYINT(2) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `users` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `users` CHANGE `number_of_logins` `number_of_logins` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `users` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `user_id` `user_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_category_domains` `default_category_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_category_ssl` `default_category_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_dns` `default_dns` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_host` `default_host` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_ip_address_domains` `default_ip_address_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_ip_address_ssl` `default_ip_address_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_owner_domains` `default_owner_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_owner_ssl` `default_owner_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_registrar` `default_registrar` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_registrar_account` `default_registrar_account` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_ssl_type` `default_ssl_type` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `user_settings` CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `categories` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `categories` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `hosting` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `hosting` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `owners` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `owners` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `currencies` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `currency_conversions` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `currency_conversions` CHANGE `currency_id` `currency_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `currency_conversions` CHANGE `user_id` `user_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `fees` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `fees` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `fees` CHANGE `currency_id` `currency_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_fees` CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees` CHANGE `type_id` `type_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees` CHANGE `currency_id` `currency_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `cat_id` `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `fee_id` `fee_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `dns_id` `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `ip_id` `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `hosting_id` `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '1'");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `domain_id` `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `cat_id` `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `dns_id` `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `ip_id` `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `hosting_id` `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `domain_id` `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `cat_id` `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `dns_id` `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `ip_id` `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `hosting_id` `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_history` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_queue_list` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_list_history` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_queue_temp` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_queue_temp` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `custom_field_types` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_fields` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_fields` CHANGE `type_id` `type_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `domain_fields` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `domain_field_data` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `domain_field_data` CHANGE `domain_id` `domain_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `account_id` `account_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `domain_id` `domain_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `type_id` `type_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `ip_id` `ip_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `cat_id` `cat_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `fee_id` `fee_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_cert_types` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_cert_types` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_cert_fields` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_cert_fields` CHANGE `type_id` `type_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_cert_fields` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_cert_field_data` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_cert_field_data` CHANGE `ssl_id` `ssl_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `dns` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `dns` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `registrars` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `registrars` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `registrar_id` `registrar_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `api_ip_id` `api_ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_providers` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_providers` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `ssl_accounts` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_accounts` CHANGE `owner_id` `owner_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts` CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segments` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `segments` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `segment_data` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `segment_data` CHANGE `segment_id` `segment_id` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `ip_addresses` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ip_addresses` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `timezones` CHANGE `id` `id` INT(5) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `dw_accounts` `dw_accounts` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `dw_dns_zones` `dw_dns_zones` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `dw_dns_records` `dw_dns_records` INT(10) UNSIGNED NOT NULL");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `build_time` `build_time` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `build_time_overall` `build_time_overall` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `dw_servers` CHANGE `created_by` `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `scheduler` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `api_registrars` CHANGE `id` `id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `goal_activity` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `log` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `log` CHANGE `user_id` `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_category_domains` `default_category_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_category_ssl` `default_category_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_dns` `default_dns` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_host` `default_host` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_ip_address_domains` `default_ip_address_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_ip_address_ssl` `default_ip_address_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_owner_domains` `default_owner_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_owner_ssl` `default_owner_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_registrar` `default_registrar` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_registrar_account` `default_registrar_account` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_ssl_type` `default_ssl_type` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("
        ALTER TABLE `settings` CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) UNSIGNED NOT NULL DEFAULT '0'");

    $pdo->query("ALTER TABLE api_registrars ENGINE=InnoDB");

    $pdo->query("ALTER TABLE categories ENGINE=InnoDB");

    $pdo->query("ALTER TABLE creation_types ENGINE=InnoDB");

    $pdo->query("ALTER TABLE currencies ENGINE=InnoDB");

    $pdo->query("ALTER TABLE currency_conversions ENGINE=InnoDB");

    $pdo->query("ALTER TABLE custom_field_types ENGINE=InnoDB");

    $pdo->query("ALTER TABLE dns ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_field_data ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_fields ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_queue ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_queue_history ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_queue_list ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_queue_list_history ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domain_queue_temp ENGINE=InnoDB");

    $pdo->query("ALTER TABLE domains ENGINE=InnoDB");

    $pdo->query("ALTER TABLE dw_servers ENGINE=InnoDB");

    $pdo->query("ALTER TABLE fees ENGINE=InnoDB");

    $pdo->query("ALTER TABLE goal_activity ENGINE=InnoDB");

    $pdo->query("ALTER TABLE hosting ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ip_addresses ENGINE=InnoDB");

    $pdo->query("ALTER TABLE log ENGINE=InnoDB");

    $pdo->query("ALTER TABLE owners ENGINE=InnoDB");

    $pdo->query("ALTER TABLE registrar_accounts ENGINE=InnoDB");

    $pdo->query("ALTER TABLE registrars ENGINE=InnoDB");

    $pdo->query("ALTER TABLE scheduler ENGINE=InnoDB");

    $pdo->query("ALTER TABLE segment_data ENGINE=InnoDB");

    $pdo->query("ALTER TABLE segments ENGINE=InnoDB");

    $pdo->query("ALTER TABLE settings ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_accounts ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_cert_field_data ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_cert_fields ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_cert_types ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_certs ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_fees ENGINE=InnoDB");

    $pdo->query("ALTER TABLE ssl_providers ENGINE=InnoDB");

    $pdo->query("ALTER TABLE timezones ENGINE=InnoDB");

    $pdo->query("ALTER TABLE user_settings ENGINE=InnoDB");

    $pdo->query("ALTER TABLE users ENGINE=InnoDB");

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `expiration_emails` `expiration_emails` TINYINT(1) NOT NULL DEFAULT '0'");

    $pdo->query("
        UPDATE settings
        SET db_version = '4.06.00',
            update_time = '" . $timestamp . "'");

    $current_db_version = '4.06.00';

}
//@formatter:on
