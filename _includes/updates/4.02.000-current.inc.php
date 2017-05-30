<?php
/**
 * /_includes/updates/4.02.000-current.inc.php
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
?>
<?php //@formatter:off

// upgrade database from 4.02.000 to 4.02.001
if ($current_db_version === '4.02.000') {

    $sql = "UPDATE currencies
            SET symbol = '₺'
            WHERE currency = 'TRY'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `settings`
            CHANGE `smtp_port` `smtp_port` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '587'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.02.001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.02.001';

}

// upgrade database from 4.02.001 to 4.03.000
if ($current_db_version === '4.02.001') {

    $sql = "UPDATE scheduler
            SET description = '" . "<" . "em>Domains:" . "<" . "/em> Converts all domain entries to lowercase." . "<" . "BR>" . "<" . "BR> " . "<" . "em>TLDs:" . "<" . "/em> Updates all TLD entries to ensure their accuracy." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running smoothly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees.'
            WHERE `name` = 'System Cleanup'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.03.000',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.03.000';

}

// upgrade database from 4.03.000 to 4.03.001
if ($current_db_version === '4.03.000') {

    $sql = "UPDATE api_registrars
            SET ret_privacy_status = '1',
                ret_autorenewal_status = '1',
                notes = ''
            WHERE `name` = 'Fabulous'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "INSERT INTO api_registrars
            (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
             req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
             ret_autorenewal_status, notes, insert_time)
             VALUES
            ('Freenom', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', 'Freenom currently only gives API access to reseller accounts.', '" . $time->stamp() . "'),
            ('DreamHost', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '0', '1', 'DreamHost does not currently allow the WHOIS privacy status of a domain to be retrieved using their API, so all domains added to the queue from a DreamHost account will have their WHOIS privacy status set to No.', '" . $time->stamp() . "')";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_temp` (
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
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.03.001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.03.001';

}

// upgrade database from 4.03.001 to 4.03.002
if ($current_db_version === '4.03.001') {

    $sql = "ALTER TABLE `dw_servers`
            ADD `api_token` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `username`";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "INSERT INTO `api_registrars`
            (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
             req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
             ret_autorenewal_status, notes, insert_time)
             VALUES
            ('Above.com', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "')";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.03.002',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.03.002';

}

// upgrade database from 4.03.002 to 4.04.000
if ($current_db_version === '4.03.002') {

    $sql = "ALTER TABLE `domains`
            CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue`
            CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue_history`
            CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue_temp`
            CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `expiry_date` `expiry_date` DATE NOT NULL DEFAULT '1978-01-23'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `creation_types`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `creation_types`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `users`
            CHANGE `last_login` `last_login` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `users`
            SET `last_login` = '1978-01-23 00:00:00'
            WHERE `last_login` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `users`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `users`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `users`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `users`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `user_settings`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `user_settings`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `user_settings`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `user_settings`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `categories`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `categories`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `categories`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `categories`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `hosting`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `hosting`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `hosting`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `hosting`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `owners`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `owners`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `owners`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `owners`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `currencies`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `currencies`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `currencies`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `currencies`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `currency_conversions`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `currency_conversions`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `currency_conversions`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `currency_conversions`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `fees`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `fees`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `fees`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `fees`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_fees`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_fees`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_fees`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_fees`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domains`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domains`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domains`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domains`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_queue`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue_history`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_queue_history`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue_list`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_queue_list`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_queue_list_history`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_queue_list_history`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `custom_field_types`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `custom_field_types`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `custom_field_types`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `custom_field_types`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_fields`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_fields`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_fields`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_fields`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_field_data`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_field_data`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `domain_field_data`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `domain_field_data`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_certs`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_certs`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_types`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_types`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_types`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_types`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_fields`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_fields`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_fields`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_fields`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_field_data`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_field_data`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_cert_field_data`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_cert_field_data`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dns`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dns`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dns`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dns`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `registrars`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `registrars`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `registrars`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `registrars`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `registrar_accounts`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `registrar_accounts`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `registrar_accounts`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `registrar_accounts`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_providers`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_providers`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_providers`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_providers`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_accounts`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_accounts`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ssl_accounts`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ssl_accounts`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `segments`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `segments`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `segments`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `segments`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `segment_data`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `segment_data`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `segment_data`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `segment_data`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ip_addresses`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ip_addresses`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `ip_addresses`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `ip_addresses`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `timezones`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `timezones`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `build_start_time` `build_start_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `build_start_time` = '1978-01-23 00:00:00'
            WHERE `build_start_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `build_end_time` `build_end_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `build_end_time` = '1978-01-23 00:00:00'
            WHERE `build_end_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `build_start_time_overall` `build_start_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `build_start_time_overall` = '1978-01-23 00:00:00'
            WHERE `build_start_time_overall` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `build_end_time_overall` `build_end_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `build_end_time_overall` = '1978-01-23 00:00:00'
            WHERE `build_end_time_overall` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `dw_servers`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `dw_servers`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `scheduler`
            CHANGE `last_run` `last_run` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `scheduler`
            SET `last_run` = '1978-01-23 00:00:00'
            WHERE `last_run` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `scheduler`
            CHANGE `next_run` `next_run` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `scheduler`
            SET `next_run` = '1978-01-23 00:00:00'
            WHERE `next_run` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `scheduler`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `scheduler`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `scheduler`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `scheduler`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `api_registrars`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `api_registrars`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `api_registrars`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `api_registrars`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `settings`
            CHANGE `insert_time` `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `settings`
            SET `insert_time` = '1978-01-23 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "ALTER TABLE `settings`
            CHANGE `update_time` `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE `settings`
            SET `update_time` = '1978-01-23 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:01'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE api_registrars
            SET req_ip_address = '1'
            WHERE `name` = 'OpenSRS'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "INSERT INTO custom_field_types
            (id, `name`, insert_time)
            VALUES
            (4, 'Date', '" . $time->stamp() . "'),
            (5, 'Time Stamp', '" . $time->stamp() . "')";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.04.000',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.04.000';

}

// upgrade database from 4.04.000 to 4.04.001
if ($current_db_version === '4.04.000') {

    $sql = "UPDATE settings
            SET db_version = '4.04.001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.04.001';

}

// upgrade database from 4.04.001 to 4.05.00
if ($current_db_version === '4.04.001') {

    $sql = "CREATE TABLE IF NOT EXISTS `goal_activity` (
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
            ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $goal->upgrade($previous_version);

    $sql = "ALTER TABLE `settings`
            ADD `debug_mode` TINYINT(1) NOT NULL DEFAULT '0' AFTER `smtp_password`";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "CREATE TABLE IF NOT EXISTS `log` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) NOT NULL DEFAULT '0',
                `area` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `level` VARCHAR(9) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `message` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `extra` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00',
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $sql = "UPDATE settings
            SET db_version = '4.05.00',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.05.00';

}

// upgrade database from 4.05.00 to 4.05.01
if ($current_db_version === '4.05.00') {

    $sql = "UPDATE settings
            SET db_version = '4.05.01',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $current_db_version = '4.05.01';

}

//@formatter:on
