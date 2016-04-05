<?php
/**
 * /_includes/updates/4.00.000-current.inc.php
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
<?php //@formatter:off

// upgrade database from 4.00.000 to 4.00.001
if ($current_db_version === '4.00.000') {

    $sql = "ALTER TABLE `settings`
            ADD `expiration_days` INT(3) NOT NULL DEFAULT '60' AFTER `expiration_email_days`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `settings`
            SET `expiration_days` = `expiration_email_days`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            DROP `expiration_email_days`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '4.00.001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '4.00.001';

}

// upgrade database from 4.00.001 to 4.00.002
if ($current_db_version === '4.00.001') {

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `api_key` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `password`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '4.00.002',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '4.00.002';

}

// upgrade database from 4.00.002 to 4.01.000
if ($current_db_version === '4.00.002') {

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `api_app_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `password`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `api_secret` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `api_key`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `registrar_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `api_ip_id` INT(10) NOT NULL DEFAULT '0' AFTER `api_secret`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `creation_types` (
                `id` TINYINT(2) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO creation_types
            (`name`, insert_time)
             VALUES
            ('Installation', '" . $time->stamp() . "'),
            ('Manual', '" . $time->stamp() . "'),
            ('Bulk Updater', '" . $time->stamp() . "'),
            ('Manual or Bulk Updater', '" . $time->stamp() . "'),
            ('Queue', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    
    $creation_type_id_installation = $system->getCreationTypeId($connection, 'Installation');
    $creation_type_id_manual = $system->getCreationTypeId($connection, 'Manual');
    $creation_type_id_unknown = $system->getCreationTypeId($connection, 'Manual or Bulk Updater');

    $sql = "ALTER TABLE `domains`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `fee_fixed`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    
    $sql = "UPDATE `domains`
            SET creation_type_id = '" . $creation_type_id_unknown . "'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `dns`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `number_of_servers`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `dns`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `dns`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `registrars`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrars`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `reseller_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_providers`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_providers`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `segments`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `segments`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `ip_addresses`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `dw_servers`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `has_ever_been_built_overall`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `dw_servers`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `categories`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `categories`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `categories`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `hosting`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `hosting`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `hosting`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `owners`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `owners`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `owners`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `domain_fields`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domain_fields`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_certs`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `fee_fixed`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_certs`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_cert_types`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_cert_types`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `ssl_cert_types`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id IN ('1', '2', '3', '4')";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `ssl_cert_fields`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_cert_fields`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `users`
            ADD `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "' AFTER `last_login`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `users`
            ADD `created_by` INT(10) NOT NULL DEFAULT '0' AFTER `creation_type_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `users`
            SET creation_type_id = '" . $creation_type_id_installation . "'
            WHERE id = '1'";
    mysqli_query($connection, $sql);

    $sql = "ALTER TABLE `scheduler`
            CHANGE `interval` `interval` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Daily'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_id` INT(10) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `cat_id` INT(10) NOT NULL DEFAULT '0',
                `dns_id` INT(10) NOT NULL DEFAULT '0',
                `ip_id` INT(10) NOT NULL DEFAULT '0',
                `hosting_id` INT(10) NOT NULL DEFAULT '0',
                `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
                `privacy` TINYINT(1) NOT NULL DEFAULT '0',
                `processing` TINYINT(1) NOT NULL DEFAULT '0',
                `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_history` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_id` INT(10) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `cat_id` INT(10) NOT NULL DEFAULT '0',
                `dns_id` INT(10) NOT NULL DEFAULT '0',
                `ip_id` INT(10) NOT NULL DEFAULT '0',
                `hosting_id` INT(10) NOT NULL DEFAULT '0',
                `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
                `privacy` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_list` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_count` INT(6) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `processing` TINYINT(1) NOT NULL DEFAULT '0',
                `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_list_history` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_count` INT(6) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO scheduler
            (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
             VALUES
            ('Domain Queue Processing', 'Retrieves information for domains in the queue and adds them to DomainMOD.', 'Every 5 Minutes', '*/5 * * * * *', 'domain-queue', '10', '0', '1', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $cron = \Cron\CronExpression::factory('*/5 * * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler
            SET next_run = '" . $next_run . "'
            WHERE `name` = 'Domain Queue Processing'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `api_registrars` (
                `id` TINYINT(3) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `req_account_username` TINYINT(1) NOT NULL DEFAULT '0',
                `req_account_password` TINYINT(1) NOT NULL DEFAULT '0',
                `req_reseller_id` TINYINT(1) NOT NULL DEFAULT '0',
                `req_api_app_name` TINYINT(1) NOT NULL DEFAULT '0',
                `req_api_key` TINYINT(1) NOT NULL DEFAULT '0',
                `req_api_secret` TINYINT(1) NOT NULL DEFAULT '0',
                `req_ip_address` TINYINT(1) NOT NULL DEFAULT '0',
                `lists_domains` TINYINT(1) NOT NULL DEFAULT '0',
                `ret_expiry_date` TINYINT(1) NOT NULL DEFAULT '0',
                `ret_dns_servers` TINYINT(1) NOT NULL DEFAULT '0',
                `ret_privacy_status` TINYINT(1) NOT NULL DEFAULT '0',
                `ret_autorenewal_status` TINYINT(1) NOT NULL DEFAULT '0',
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO api_registrars
            (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
             req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
             ret_autorenewal_status, notes, insert_time)
             VALUES
            ('DNSimple', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Dynadot', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('eNom', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Fabulous', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '0', '0', 'Fabulous does not currently allow the privacy or auto renewal status of a domain to be retrieved using their API, so all domains added to the queue from a Fabulous account will have their privacy and auto renewal status set to No.', '" . $time->stamp() . "'),
            ('GoDaddy', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Internet.bs', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Name.com', '1', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('NameBright', '1', '0', '0', '1', '0', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Namecheap', '1', '0', '0', '0', '1', '0', '1', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('NameSilo', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('OpenSRS', '1', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('ResellerClub', '0', '0', '1', '0', '1', '0', '0', '0', '1', '1', '1', '0', 'ResellerClub does not currently allow the auto renewal status of a domain to be retrieved using their API, so all domains added to the queue from a ResellerClub account will have their auto renewal status set to No.', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrars`
            ADD `api_registrar_id` TINYINT(3) NOT NULL DEFAULT '0' AFTER `url`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `ssl_provider_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ip_addresses`
            CHANGE `rdns` `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `scheduler`
            CHANGE `last_duration` `last_duration` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `reseller_temp` INT(1) NOT NULL DEFAULT '0' AFTER `reseller_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `reseller_id_temp` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `reseller_temp`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `registrar_accounts`
            SET reseller_temp = reseller";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `registrar_accounts`
            SET reseller_id_temp = reseller_id";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            DROP `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            DROP `reseller_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `reseller` INT(1) NOT NULL DEFAULT '0' AFTER `password`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            ADD `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `registrar_accounts`
            SET reseller = reseller_temp";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `registrar_accounts`
            SET reseller_id = reseller_id_temp";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            DROP `reseller_temp`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `registrar_accounts`
            DROP `reseller_id_temp`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `reseller_temp` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `ssl_accounts`
            SET reseller_temp = reseller";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            DROP `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `reseller` INT(1) NOT NULL DEFAULT '0' AFTER `password`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `ssl_accounts`
            SET reseller = reseller_temp";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            DROP `reseller_temp`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_accounts`
            ADD `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `reseller`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '4.01.000',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '4.01.000';

}

//@formatter:on
