<?php
/**
 * /_includes/update-database.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
$sql = "SELECT db_version
        FROM settings";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

while ($row = mysqli_fetch_object($result)) {
    $current_db_version = $row->db_version;
}

if ($current_db_version < $software_db_version) {

    // upgrade database from 1.1 to 1.2
    if ($current_db_version == 1.1) {

        $sql = "ALTER TABLE `ssl_certs`
                ADD `ip` VARCHAR(50) NOT NULL AFTER `name`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.2',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.2;

    }

    // upgrade database from 1.2 to 1.3
    if ($current_db_version == 1.2) {

        $sql = "CREATE TABLE IF NOT EXISTS `ip_addresses` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `ip` VARCHAR(255) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.3',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.3;

    }

    // upgrade database from 1.3 to 1.4
    if ($current_db_version == 1.3) {

        $sql = "ALTER TABLE `ip_addresses`
                ADD `notes` LONGTEXT NOT NULL AFTER `ip`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.4',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.4;

    }

    // upgrade database from 1.4 to 1.5
    if ($current_db_version == 1.4) {

        $sql = "ALTER TABLE `domains`
                ADD `ip_id` INT(10) NOT NULL DEFAULT '0' AFTER `dns_id`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.5',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.5;

    }

    // upgrade database from 1.5 to 1.6
    if ($current_db_version == 1.5) {

        $sql = "ALTER TABLE `domains`
                CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE `domains`
                SET ip_id = '1',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "TRUNCATE `ip_addresses`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO `ip_addresses`
                (`id`, `name`, `ip`, `insert_time`) VALUES
                ('1', '[no ip address]', '-', '" . $time->time() . "')";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.6',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.6;

    }

    // upgrade database from 1.6 to 1.7
    if ($current_db_version == 1.6) {

        $sql = "ALTER TABLE `ssl_certs`
                DROP `ip`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.7',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.7;

    }

    // upgrade database from 1.7 to 1.8
    if ($current_db_version == 1.7) {

        $sql = "ALTER TABLE `ip_addresses`
                ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.8',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.8;

    }

    // upgrade database from 1.8 to 1.9
    if ($current_db_version == 1.8) {

        $sql = "ALTER TABLE `settings`
                ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.9',
                    email_address = 'greg@chetcuti.com',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.9;

    }

    // upgrade database from 1.9 to 1.91
    if ($current_db_version == 1.9) {

        $sql = "ALTER TABLE `ip_addresses`
                ADD `rdns` VARCHAR(255) NOT NULL DEFAULT '-' AFTER `ip`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.91',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.91;

    }

    // upgrade database from 1.91 to 1.92
    if ($current_db_version == 1.91) {

        $sql = "ALTER TABLE `settings`
                ADD `type` VARCHAR(50) NOT NULL AFTER `id`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET type =  'system',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.92',
                    update_time = '" . $time->time() . "'
                WHERE type = 'system'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.92;

    }

    // upgrade database from 1.92 to 1.93
    if ($current_db_version == 1.92) {

        $sql = "ALTER TABLE `settings`
                DROP `type`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.93',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.93;

    }

    // upgrade database from 1.93 to 1.94
    if ($current_db_version == 1.93) {

        $sql = "ALTER TABLE `settings`
                ADD `number_of_domains` INT(5) NOT NULL DEFAULT '50' AFTER `email_address`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `settings`
                ADD `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_domains`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.94',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.94;

    }

    // upgrade database from 1.94 to 1.95
    if ($current_db_version == 1.94) {

        $sql = "ALTER TABLE `currencies`
                DROP `default_currency`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `settings`
                ADD `default_currency` VARCHAR(5) NOT NULL DEFAULT 'CAD' AFTER `email_address`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.95',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.95;

    }

    // upgrade database from 1.95 to 1.96
    if ($current_db_version == 1.95) {

        $sql = "ALTER TABLE `currencies`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.96',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.96;

    }

    // upgrade database from 1.96 to 1.97
    if ($current_db_version == 1.96) {

        $sql = "CREATE TABLE IF NOT EXISTS `owners` (
                    `id` INT(5) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `notes` LONGTEXT NOT NULL,
                    `active` INT(1) NOT NULL DEFAULT '1',
                    `test_data` INT(1) NOT NULL DEFAULT '0',
                    `insert_time` DATETIME NOT NULL,
                    `update_time` DATETIME NOT NULL,
                    PRIMARY KEY  (`id`),
                    KEY `name` (`name`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO owners
                    (id, name, notes, active, test_data, insert_time, update_time)
                    SELECT id, name, notes, active, test_data, insert_time, update_time FROM companies ORDER BY id;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "DROP TABLE `companies`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `registrar_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_certs` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.97',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.97;

    }

    // upgrade database from 1.97 to 1.98
    if ($current_db_version == 1.97) {

        $sql = "INSERT INTO `categories`
                    (`name`, `owner`, `insert_time`) VALUES
                    ('[no category]', '[no stakeholder]', '" . $time->time() . "');";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM categories
                WHERE default_category = '1';";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) == 0) {
            $sql_update = "UPDATE categories
                           SET default_category = '1',
                                  update_time = '" . $time->time() . "'
                           WHERE name = '[no category]'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
        }

        $sql = "ALTER TABLE `dns`
                    ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO `dns`
                    (`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES
                    ('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $time->time() . "');";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM dns
                WHERE default_dns = '1';";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) == 0) {
            $sql_update = "UPDATE dns
                           SET default_dns = '1',
                                  update_time = '" . $time->time() . "'
                           WHERE name = '[no dns]'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
        }

        $sql = "ALTER TABLE `owners`
                    ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO `owners`
                    (`name`, `insert_time`) VALUES
                    ('[no owner]', '" . $time->time() . "');";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM owners
                WHERE default_owner = '1';";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) == 0) {
            $sql_update = "UPDATE owners
                           SET default_owner = '1',
                                  update_time = '" . $time->time() . "'
                           WHERE name = '[no owner]'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
        }

        $sql = "ALTER TABLE `ip_addresses`
                    ADD `default_ip_address` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM ip_addresses
                WHERE default_ip_address = '1';";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) == 0) {
            $sql_update = "UPDATE ip_addresses
                           SET default_ip_address = '1',
                                  update_time = '" . $time->time() . "'
                           WHERE name = '[no ip address]'";
            $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
        }

        $sql = "UPDATE settings
                SET db_version = '1.98',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.98;

    }

    // upgrade database from 1.98 to 1.99
    if ($current_db_version == 1.98) {

        $sql = "ALTER TABLE `categories`
                    CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE `categories`
                    SET `stakeholder` = '[no stakeholder]',
                        `update_time` = '" . $time->time() . "'
                WHERE `stakeholder` = '[no category owner]';";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '1.99',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 1.99;

    }

    // upgrade database from 1.99 to 2.0001
    if ($current_db_version == 1.99) {

        $sql = "ALTER TABLE `currencies`
                    ADD `default_currency` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT default_currency
                FROM settings";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {
            $default_currency = $row->default_currency;
        }

        $sql = "UPDATE currencies
                SET default_currency = '0',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE currencies
                SET default_currency = '1',
                    update_time = '" . $time->time() . "'
                WHERE currency = '" . $default_currency . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `settings`
                DROP `default_currency`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0001',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0001;

    }

    // upgrade database from 2.0001 to 2.0002
    if ($current_db_version == 2.0001) {

        $sql = "ALTER TABLE `ssl_cert_functions`
                    ADD `default_function` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_cert_types`
                    ADD `default_type` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE ssl_cert_functions
                SET default_function = '1',
                    update_time = '" . $time->time() . "'
                WHERE function = 'Web Server SSL/TLS Certificate'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE ssl_cert_types
                SET default_type = '1',
                    update_time = '" . $time->time() . "'
                WHERE type = 'Wildcard'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0002',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0002;

    }

    // upgrade database from 2.0002 to 2.0003
    if ($current_db_version == 2.0002) {

        $sql = "DROP TABLE `ssl_cert_types`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_certs`
                DROP `type_id`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_fees`
                DROP `type_id`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0003',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0003;

    }

    // upgrade database from 2.0003 to 2.0004
    if ($current_db_version == 2.0003) {

        $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` (
                    `id` INT(10) NOT NULL AUTO_INCREMENT,
                    `type` VARCHAR(255) NOT NULL,
                    `notes` LONGTEXT NOT NULL,
                    `default_type` INT(1) NOT NULL DEFAULT '0',
                    `active` INT(1) NOT NULL DEFAULT '1',
                    `insert_time` DATETIME NOT NULL,
                    `update_time` DATETIME NOT NULL,
                    PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO ssl_cert_types
                    (id, type, notes, default_type, active, insert_time, update_time)
                    SELECT id, function, notes, default_function, active, insert_time, update_time FROM ssl_cert_functions ORDER BY id;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "DROP TABLE `ssl_cert_functions`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_certs`
                    CHANGE `function_id` `type_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_fees`
                    CHANGE `function_id` `type_id` INT(5) NOT NULL";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0004',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0004;

    }

    // upgrade database from 2.0004 to 2.0005
    if ($current_db_version == 2.0004) {

        $sql = "ALTER TABLE `ssl_cert_types`
                    ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0005',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0005;

    }

    // upgrade database from 2.0005 to 2.0006
    if ($current_db_version == 2.0005) {

        $sql = "ALTER TABLE `ip_addresses`
                    ADD `active` INT(1) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    CHANGE `active` `active` INT(2) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0006',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0006;

    }

    // upgrade database from 2.0006 to 2.0007
    if ($current_db_version == 2.0006) {

        $sql = "ALTER TABLE `registrars`
                    ADD `default_registrar` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `registrar_accounts`
                    ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_providers`
                    ADD `default_provider` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_accounts`
                    ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0007',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0007;

    }

    // upgrade database from 2.0007 to 2.0008
    if ($current_db_version == 2.0007) {

        $sql = "ALTER TABLE `owners`
                    CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `registrars`
                    CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_providers`
                    CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0008',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0008;

    }

    // upgrade database from 2.0008 to 2.0009
    if ($current_db_version == 2.0008) {

        $sql = "ALTER TABLE `currencies`
                ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0009',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0009;

    }

    // upgrade database from 2.0009 to 2.0010
    if ($current_db_version == 2.0009) {

        $sql = "CREATE TABLE IF NOT EXISTS `user_settings` (
                    `id` INT(10) NOT NULL AUTO_INCREMENT,
                    `user_id` INT(10) NOT NULL,
                    `number_of_domains` INT(5) NOT NULL DEFAULT '50',
                    `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50',
                    `display_domain_owner` INT(1) NOT NULL DEFAULT '0',
                    `display_domain_registrar` INT(1) NOT NULL DEFAULT '0',
                    `display_domain_account` INT(1) NOT NULL DEFAULT '1',
                    `display_domain_expiry_date` INT(1) NOT NULL DEFAULT '1',
                    `display_domain_category` INT(1) NOT NULL DEFAULT '1',
                    `display_domain_dns` INT(1) NOT NULL DEFAULT '0',
                    `display_domain_ip` INT(1) NOT NULL DEFAULT '0',
                    `display_domain_tld` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_owner` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_provider` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_account` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_domain` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_type` INT(1) NOT NULL DEFAULT '0',
                    `display_ssl_expiry_date` INT(1) NOT NULL DEFAULT '0',
                    `insert_time` DATETIME NOT NULL,
                    `update_time` DATETIME NOT NULL,
                    PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM users";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {
            $sql_temp = "INSERT INTO user_settings
                         (user_id, insert_time) VALUES
                         ('" . $row->id . "', '" . $time->time() . "');";
            $result_temp = mysqli_query($connection, $sql_temp) or $error->outputOldSqlError($connection);
        }

        $sql = "UPDATE settings
                SET db_version = '2.001',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.001;

    }

    // upgrade database from 2.0010 to 2.0011
    if ($current_db_version == 2.001) {

        $sql = "ALTER TABLE `settings`
                    DROP `number_of_domains`,
                    DROP `number_of_ssl_certs`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0011',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0011;

    }

    // upgrade database from 2.0011 to 2.0012
    if ($current_db_version == 2.0011) {

        $sql = "ALTER TABLE `user_settings`
                CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0012',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0012;

    }

    // upgrade database from 2.0012 to 2.0013
    if ($current_db_version == 2.0012) {

        $sql = "ALTER TABLE `categories`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `currencies`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `dns`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `fees`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ip_addresses`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `owners`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `registrars`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `registrar_accounts`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `segments`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_accounts`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_certs`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_cert_types`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_fees`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `ssl_providers`
                DROP `test_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0013',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0013;

    }

    // upgrade database from 2.0013 to 2.0014
    if ($current_db_version == 2.0013) {

        $sql = "CREATE TABLE IF NOT EXISTS `segment_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `segment_id` INT(10) NOT NULL,
                `domain` VARCHAR(255) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0014',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0014;

    }

    // upgrade database from 2.0014 to 2.0015
    if ($current_db_version == 2.0014) {

        $sql = "ALTER TABLE `user_settings`
                    ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `user_settings`
                    ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0015',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0015;

    }

    // upgrade database from 2.0015 to 2.0016
    if ($current_db_version == 2.0015) {

        $sql = "ALTER TABLE `segment_data`
                    ADD `active` INT(1) NOT NULL DEFAULT '0' AFTER `domain`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `segment_data`
                    ADD `inactive` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `segment_data`
                    ADD `missing` INT(1) NOT NULL DEFAULT '0' AFTER `inactive`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0016',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0016;

    }

    // upgrade database from 2.0016 to 2.0017
    if ($current_db_version == 2.0016) {

        $sql = "ALTER TABLE `segment_data`
                    ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0017',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0017;

    }

    // upgrade database from 2.0017 to 2.0018
    if ($current_db_version == 2.0017) {

        $sql = "ALTER TABLE `ssl_certs`
                    CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0018',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0018;

    }

    // upgrade database from 2.0018 to 2.0019
    if ($current_db_version == 2.0018) {

        $sql = "ALTER TABLE `ssl_certs`
                    CHANGE `domain_id` `domain_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0019',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0019;

    }

    // upgrade database from 2.0019 to 2.0020
    if ($current_db_version == 2.0019) {

        $sql = "ALTER TABLE `user_settings`
                    ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0020',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0020;

    }

    // upgrade database from 2.0020 to 2.0021
    if ($current_db_version == 2.002) {

        $sql = "ALTER TABLE `settings`
                    ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

        $sql = "UPDATE settings
                SET full_url = '" . $full_url . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0021',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0021;

    }

    // upgrade database from 2.0021 to 2.0022
    if ($current_db_version == 2.0021) {

        $sql = "ALTER TABLE `settings`
                    ADD `timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `email_address`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0022',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0022;

    }

    // upgrade database from 2.0022 to 2.0023
    if ($current_db_version == 2.0022) {

        $sql = "CREATE TABLE IF NOT EXISTS `timezones` (
                `id` INT(5) NOT NULL AUTO_INCREMENT,
                `timezone` VARCHAR(50) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO `timezones`
                (`timezone`, `insert_time`) VALUES
                ('Africa/Abidjan', '" . $time->time() . "'), ('Africa/Accra', '" . $time->time() . "'), ('Africa/Addis_Ababa', '" . $time->time() . "'), ('Africa/Algiers', '" . $time->time() . "'), ('Africa/Asmara', '" . $time->time() . "'), ('Africa/Asmera', '" . $time->time() . "'), ('Africa/Bamako', '" . $time->time() . "'), ('Africa/Bangui', '" . $time->time() . "'), ('Africa/Banjul', '" . $time->time() . "'), ('Africa/Bissau', '" . $time->time() . "'), ('Africa/Blantyre', '" . $time->time() . "'), ('Africa/Brazzaville', '" . $time->time() . "'), ('Africa/Bujumbura', '" . $time->time() . "'), ('Africa/Cairo', '" . $time->time() . "'), ('Africa/Casablanca', '" . $time->time() . "'), ('Africa/Ceuta', '" . $time->time() . "'), ('Africa/Conakry', '" . $time->time() . "'), ('Africa/Dakar', '" . $time->time() . "'), ('Africa/Dar_es_Salaam', '" . $time->time() . "'), ('Africa/Djibouti', '" . $time->time() . "'), ('Africa/Douala', '" . $time->time() . "'), ('Africa/El_Aaiun', '" . $time->time() . "'), ('Africa/Freetown', '" . $time->time() . "'), ('Africa/Gaborone', '" . $time->time() . "'), ('Africa/Harare', '" . $time->time() . "'), ('Africa/Johannesburg', '" . $time->time() . "'), ('Africa/Juba', '" . $time->time() . "'), ('Africa/Kampala', '" . $time->time() . "'), ('Africa/Khartoum', '" . $time->time() . "'), ('Africa/Kigali', '" . $time->time() . "'), ('Africa/Kinshasa', '" . $time->time() . "'), ('Africa/Lagos', '" . $time->time() . "'), ('Africa/Libreville', '" . $time->time() . "'), ('Africa/Lome', '" . $time->time() . "'), ('Africa/Luanda', '" . $time->time() . "'), ('Africa/Lubumbashi', '" . $time->time() . "'), ('Africa/Lusaka', '" . $time->time() . "'), ('Africa/Malabo', '" . $time->time() . "'), ('Africa/Maputo', '" . $time->time() . "'), ('Africa/Maseru', '" . $time->time() . "'), ('Africa/Mbabane', '" . $time->time() . "'), ('Africa/Mogadishu', '" . $time->time() . "'), ('Africa/Monrovia', '" . $time->time() . "'), ('Africa/Nairobi', '" . $time->time() . "'), ('Africa/Ndjamena', '" . $time->time() . "'), ('Africa/Niamey', '" . $time->time() . "'), ('Africa/Nouakchott', '" . $time->time() . "'), ('Africa/Ouagadougou', '" . $time->time() . "'), ('Africa/Porto-Novo', '" . $time->time() . "'), ('Africa/Sao_Tome', '" . $time->time() . "'), ('Africa/Timbuktu', '" . $time->time() . "'), ('Africa/Tripoli', '" . $time->time() . "'), ('Africa/Tunis', '" . $time->time() . "'), ('Africa/Windhoek', '" . $time->time() . "'), ('America/Adak', '" . $time->time() . "'), ('America/Anchorage', '" . $time->time() . "'), ('America/Anguilla', '" . $time->time() . "'), ('America/Antigua', '" . $time->time() . "'), ('America/Araguaina', '" . $time->time() . "'), ('America/Argentina/Buenos_Aires', '" . $time->time() . "'), ('America/Argentina/Catamarca', '" . $time->time() . "'), ('America/Argentina/ComodRivadavia', '" . $time->time() . "'), ('America/Argentina/Cordoba', '" . $time->time() . "'), ('America/Argentina/Jujuy', '" . $time->time() . "'), ('America/Argentina/La_Rioja', '" . $time->time() . "'), ('America/Argentina/Mendoza', '" . $time->time() . "'), ('America/Argentina/Rio_Gallegos', '" . $time->time() . "'), ('America/Argentina/Salta', '" . $time->time() . "'), ('America/Argentina/San_Juan', '" . $time->time() . "'), ('America/Argentina/San_Luis', '" . $time->time() . "'), ('America/Argentina/Tucuman', '" . $time->time() . "'), ('America/Argentina/Ushuaia', '" . $time->time() . "'), ('America/Aruba', '" . $time->time() . "'), ('America/Asuncion', '" . $time->time() . "'), ('America/Atikokan', '" . $time->time() . "'), ('America/Atka', '" . $time->time() . "'), ('America/Bahia', '" . $time->time() . "'), ('America/Bahia_Banderas', '" . $time->time() . "'), ('America/Barbados', '" . $time->time() . "'), ('America/Belem', '" . $time->time() . "'), ('America/Belize', '" . $time->time() . "'), ('America/Blanc-Sablon', '" . $time->time() . "'), ('America/Boa_Vista', '" . $time->time() . "'), ('America/Bogota', '" . $time->time() . "'), ('America/Boise', '" . $time->time() . "'), ('America/Buenos_Aires', '" . $time->time() . "'), ('America/Cambridge_Bay', '" . $time->time() . "'), ('America/Campo_Grande', '" . $time->time() . "'), ('America/Cancun', '" . $time->time() . "'), ('America/Caracas', '" . $time->time() . "'), ('America/Catamarca', '" . $time->time() . "'), ('America/Cayenne', '" . $time->time() . "'), ('America/Cayman', '" . $time->time() . "'), ('America/Chicago', '" . $time->time() . "'), ('America/Chihuahua', '" . $time->time() . "'), ('America/Coral_Harbour', '" . $time->time() . "'), ('America/Cordoba', '" . $time->time() . "'), ('America/Costa_Rica', '" . $time->time() . "'), ('America/Creston', '" . $time->time() . "'), ('America/Cuiaba', '" . $time->time() . "'), ('America/Curacao', '" . $time->time() . "'), ('America/Danmarkshavn', '" . $time->time() . "'), ('America/Dawson', '" . $time->time() . "'), ('America/Dawson_Creek', '" . $time->time() . "'), ('America/Denver', '" . $time->time() . "'), ('America/Detroit', '" . $time->time() . "'), ('America/Dominica', '" . $time->time() . "'), ('America/Edmonton', '" . $time->time() . "'), ('America/Eirunepe', '" . $time->time() . "'), ('America/El_Salvador', '" . $time->time() . "'), ('America/Ensenada', '" . $time->time() . "'), ('America/Fort_Wayne', '" . $time->time() . "'), ('America/Fortaleza', '" . $time->time() . "'), ('America/Glace_Bay', '" . $time->time() . "'), ('America/Godthab', '" . $time->time() . "'), ('America/Goose_Bay', '" . $time->time() . "'), ('America/Grand_Turk', '" . $time->time() . "'), ('America/Grenada', '" . $time->time() . "'), ('America/Guadeloupe', '" . $time->time() . "'), ('America/Guatemala', '" . $time->time() . "'), ('America/Guayaquil', '" . $time->time() . "'), ('America/Guyana', '" . $time->time() . "'), ('America/Halifax', '" . $time->time() . "'), ('America/Havana', '" . $time->time() . "'), ('America/Hermosillo', '" . $time->time() . "'), ('America/Indiana/Indianapolis', '" . $time->time() . "'), ('America/Indiana/Knox', '" . $time->time() . "'), ('America/Indiana/Marengo', '" . $time->time() . "'), ('America/Indiana/Petersburg', '" . $time->time() . "'), ('America/Indiana/Tell_City', '" . $time->time() . "'), ('America/Indiana/Vevay', '" . $time->time() . "'), ('America/Indiana/Vincennes', '" . $time->time() . "'), ('America/Indiana/Winamac', '" . $time->time() . "'), ('America/Indianapolis', '" . $time->time() . "'), ('America/Inuvik', '" . $time->time() . "'), ('America/Iqaluit', '" . $time->time() . "'), ('America/Jamaica', '" . $time->time() . "'), ('America/Jujuy', '" . $time->time() . "'), ('America/Juneau', '" . $time->time() . "'), ('America/Kentucky/Louisville', '" . $time->time() . "'), ('America/Kentucky/Monticello', '" . $time->time() . "'), ('America/Knox_IN', '" . $time->time() . "'), ('America/Kralendijk', '" . $time->time() . "'), ('America/La_Paz', '" . $time->time() . "'), ('America/Lima', '" . $time->time() . "'), ('America/Los_Angeles', '" . $time->time() . "'), ('America/Louisville', '" . $time->time() . "'), ('America/Lower_Princes', '" . $time->time() . "'), ('America/Maceio', '" . $time->time() . "'), ('America/Managua', '" . $time->time() . "'), ('America/Manaus', '" . $time->time() . "'), ('America/Marigot', '" . $time->time() . "'), ('America/Martinique', '" . $time->time() . "'), ('America/Matamoros', '" . $time->time() . "'), ('America/Mazatlan', '" . $time->time() . "'), ('America/Mendoza', '" . $time->time() . "'), ('America/Menominee', '" . $time->time() . "'), ('America/Merida', '" . $time->time() . "'), ('America/Metlakatla', '" . $time->time() . "'), ('America/Mexico_City', '" . $time->time() . "'), ('America/Miquelon', '" . $time->time() . "'), ('America/Moncton', '" . $time->time() . "'), ('America/Monterrey', '" . $time->time() . "'), ('America/Montevideo', '" . $time->time() . "'), ('America/Montreal', '" . $time->time() . "'), ('America/Montserrat', '" . $time->time() . "'), ('America/Nassau', '" . $time->time() . "'), ('America/New_York', '" . $time->time() . "'), ('America/Nipigon', '" . $time->time() . "'), ('America/Nome', '" . $time->time() . "'), ('America/Noronha', '" . $time->time() . "'), ('America/North_Dakota/Beulah', '" . $time->time() . "'), ('America/North_Dakota/Center', '" . $time->time() . "'), ('America/North_Dakota/New_Salem', '" . $time->time() . "'), ('America/Ojinaga', '" . $time->time() . "'), ('America/Panama', '" . $time->time() . "'), ('America/Pangnirtung', '" . $time->time() . "'), ('America/Paramaribo', '" . $time->time() . "'), ('America/Phoenix', '" . $time->time() . "'), ('America/Port-au-Prince', '" . $time->time() . "'), ('America/Port_of_Spain', '" . $time->time() . "'), ('America/Porto_Acre', '" . $time->time() . "'), ('America/Porto_Velho', '" . $time->time() . "'), ('America/Puerto_Rico', '" . $time->time() . "'), ('America/Rainy_River', '" . $time->time() . "'), ('America/Rankin_Inlet', '" . $time->time() . "'), ('America/Recife', '" . $time->time() . "'), ('America/Regina', '" . $time->time() . "'), ('America/Resolute', '" . $time->time() . "'), ('America/Rio_Branco', '" . $time->time() . "'), ('America/Rosario', '" . $time->time() . "'), ('America/Santa_Isabel', '" . $time->time() . "'), ('America/Santarem', '" . $time->time() . "'), ('America/Santiago', '" . $time->time() . "'), ('America/Santo_Domingo', '" . $time->time() . "'), ('America/Sao_Paulo', '" . $time->time() . "'), ('America/Scoresbysund', '" . $time->time() . "'), ('America/Shiprock', '" . $time->time() . "'), ('America/Sitka', '" . $time->time() . "'), ('America/St_Barthelemy', '" . $time->time() . "'), ('America/St_Johns', '" . $time->time() . "'), ('America/St_Kitts', '" . $time->time() . "'), ('America/St_Lucia', '" . $time->time() . "'), ('America/St_Thomas', '" . $time->time() . "'), ('America/St_Vincent', '" . $time->time() . "'), ('America/Swift_Current', '" . $time->time() . "'), ('America/Tegucigalpa', '" . $time->time() . "'), ('America/Thule', '" . $time->time() . "'), ('America/Thunder_Bay', '" . $time->time() . "'), ('America/Tijuana', '" . $time->time() . "'), ('America/Toronto', '" . $time->time() . "'), ('America/Tortola', '" . $time->time() . "'), ('America/Vancouver', '" . $time->time() . "'), ('America/Virgin', '" . $time->time() . "'), ('America/Whitehorse', '" . $time->time() . "'), ('America/Winnipeg', '" . $time->time() . "'), ('America/Yakutat', '" . $time->time() . "'), ('America/Yellowknife', '" . $time->time() . "'), ('Antarctica/Casey', '" . $time->time() . "'), ('Antarctica/Davis', '" . $time->time() . "'), ('Antarctica/DumontDUrville', '" . $time->time() . "'), ('Antarctica/Macquarie', '" . $time->time() . "'), ('Antarctica/Mawson', '" . $time->time() . "'), ('Antarctica/McMurdo', '" . $time->time() . "'), ('Antarctica/Palmer', '" . $time->time() . "'), ('Antarctica/Rothera', '" . $time->time() . "'), ('Antarctica/South_Pole', '" . $time->time() . "'), ('Antarctica/Syowa', '" . $time->time() . "'), ('Antarctica/Vostok', '" . $time->time() . "'), ('Arctic/Longyearbyen', '" . $time->time() . "'), ('Asia/Aden', '" . $time->time() . "'), ('Asia/Almaty', '" . $time->time() . "'), ('Asia/Amman', '" . $time->time() . "'), ('Asia/Anadyr', '" . $time->time() . "'), ('Asia/Aqtau', '" . $time->time() . "'), ('Asia/Aqtobe', '" . $time->time() . "'), ('Asia/Ashgabat', '" . $time->time() . "'), ('Asia/Ashkhabad', '" . $time->time() . "'), ('Asia/Baghdad', '" . $time->time() . "'), ('Asia/Bahrain', '" . $time->time() . "'), ('Asia/Baku', '" . $time->time() . "'), ('Asia/Bangkok', '" . $time->time() . "'), ('Asia/Beirut', '" . $time->time() . "'), ('Asia/Bishkek', '" . $time->time() . "'), ('Asia/Brunei', '" . $time->time() . "'), ('Asia/Calcutta', '" . $time->time() . "'), ('Asia/Choibalsan', '" . $time->time() . "'), ('Asia/Chongqing', '" . $time->time() . "'), ('Asia/Chungking', '" . $time->time() . "'), ('Asia/Colombo', '" . $time->time() . "'), ('Asia/Dacca', '" . $time->time() . "'), ('Asia/Damascus', '" . $time->time() . "'), ('Asia/Dhaka', '" . $time->time() . "'), ('Asia/Dili', '" . $time->time() . "'), ('Asia/Dubai', '" . $time->time() . "'), ('Asia/Dushanbe', '" . $time->time() . "'), ('Asia/Gaza', '" . $time->time() . "'), ('Asia/Harbin', '" . $time->time() . "'), ('Asia/Hebron', '" . $time->time() . "'), ('Asia/Ho_Chi_Minh', '" . $time->time() . "'), ('Asia/Hong_Kong', '" . $time->time() . "'), ('Asia/Hovd', '" . $time->time() . "'), ('Asia/Irkutsk', '" . $time->time() . "'), ('Asia/Istanbul', '" . $time->time() . "'), ('Asia/Jakarta', '" . $time->time() . "'), ('Asia/Jayapura', '" . $time->time() . "'), ('Asia/Jerusalem', '" . $time->time() . "'), ('Asia/Kabul', '" . $time->time() . "'), ('Asia/Kamchatka', '" . $time->time() . "'), ('Asia/Karachi', '" . $time->time() . "'), ('Asia/Kashgar', '" . $time->time() . "'), ('Asia/Kathmandu', '" . $time->time() . "'), ('Asia/Katmandu', '" . $time->time() . "'), ('Asia/Khandyga', '" . $time->time() . "'), ('Asia/Kolkata', '" . $time->time() . "'), ('Asia/Krasnoyarsk', '" . $time->time() . "'), ('Asia/Kuala_Lumpur', '" . $time->time() . "'), ('Asia/Kuching', '" . $time->time() . "'), ('Asia/Kuwait', '" . $time->time() . "'), ('Asia/Macao', '" . $time->time() . "'), ('Asia/Macau', '" . $time->time() . "'), ('Asia/Magadan', '" . $time->time() . "'), ('Asia/Makassar', '" . $time->time() . "'), ('Asia/Manila', '" . $time->time() . "'), ('Asia/Muscat', '" . $time->time() . "'), ('Asia/Nicosia', '" . $time->time() . "'), ('Asia/Novokuznetsk', '" . $time->time() . "'), ('Asia/Novosibirsk', '" . $time->time() . "'), ('Asia/Omsk', '" . $time->time() . "'), ('Asia/Oral', '" . $time->time() . "'), ('Asia/Phnom_Penh', '" . $time->time() . "'), ('Asia/Pontianak', '" . $time->time() . "'), ('Asia/Pyongyang', '" . $time->time() . "'), ('Asia/Qatar', '" . $time->time() . "'), ('Asia/Qyzylorda', '" . $time->time() . "'), ('Asia/Rangoon', '" . $time->time() . "'), ('Asia/Riyadh', '" . $time->time() . "'), ('Asia/Saigon', '" . $time->time() . "'), ('Asia/Sakhalin', '" . $time->time() . "'), ('Asia/Samarkand', '" . $time->time() . "'), ('Asia/Seoul', '" . $time->time() . "'), ('Asia/Shanghai', '" . $time->time() . "'), ('Asia/Singapore', '" . $time->time() . "'), ('Asia/Taipei', '" . $time->time() . "'), ('Asia/Tashkent', '" . $time->time() . "'), ('Asia/Tbilisi', '" . $time->time() . "'), ('Asia/Tehran', '" . $time->time() . "'), ('Asia/Tel_Aviv', '" . $time->time() . "'), ('Asia/Thimbu', '" . $time->time() . "'), ('Asia/Thimphu', '" . $time->time() . "'), ('Asia/Tokyo', '" . $time->time() . "'), ('Asia/Ujung_Pandang', '" . $time->time() . "'), ('Asia/Ulaanbaatar', '" . $time->time() . "'), ('Asia/Ulan_Bator', '" . $time->time() . "'), ('Asia/Urumqi', '" . $time->time() . "'), ('Asia/Ust-Nera', '" . $time->time() . "'), ('Asia/Vientiane', '" . $time->time() . "'), ('Asia/Vladivostok', '" . $time->time() . "'), ('Asia/Yakutsk', '" . $time->time() . "'), ('Asia/Yekaterinburg', '" . $time->time() . "'), ('Asia/Yerevan', '" . $time->time() . "'), ('Atlantic/Azores', '" . $time->time() . "'), ('Atlantic/Bermuda', '" . $time->time() . "'), ('Atlantic/Canary', '" . $time->time() . "'), ('Atlantic/Cape_Verde', '" . $time->time() . "'), ('Atlantic/Faeroe', '" . $time->time() . "'), ('Atlantic/Faroe', '" . $time->time() . "'), ('Atlantic/Jan_Mayen', '" . $time->time() . "'), ('Atlantic/Madeira', '" . $time->time() . "'), ('Atlantic/Reykjavik', '" . $time->time() . "'), ('Atlantic/South_Georgia', '" . $time->time() . "'), ('Atlantic/St_Helena', '" . $time->time() . "'), ('Atlantic/Stanley', '" . $time->time() . "'), ('Australia/ACT', '" . $time->time() . "'), ('Australia/Adelaide', '" . $time->time() . "'), ('Australia/Brisbane', '" . $time->time() . "'), ('Australia/Broken_Hill', '" . $time->time() . "'), ('Australia/Canberra', '" . $time->time() . "'), ('Australia/Currie', '" . $time->time() . "'), ('Australia/Darwin', '" . $time->time() . "'), ('Australia/Eucla', '" . $time->time() . "'), ('Australia/Hobart', '" . $time->time() . "'), ('Australia/LHI', '" . $time->time() . "'), ('Australia/Lindeman', '" . $time->time() . "'), ('Australia/Lord_Howe', '" . $time->time() . "'), ('Australia/Melbourne', '" . $time->time() . "'), ('Australia/North', '" . $time->time() . "'), ('Australia/NSW', '" . $time->time() . "'), ('Australia/Perth', '" . $time->time() . "'), ('Australia/Queensland', '" . $time->time() . "'), ('Australia/South', '" . $time->time() . "'), ('Australia/Sydney', '" . $time->time() . "'), ('Australia/Tasmania', '" . $time->time() . "'), ('Australia/Victoria', '" . $time->time() . "'), ('Australia/West', '" . $time->time() . "'), ('Australia/Yancowinna', '" . $time->time() . "'), ('Brazil/Acre', '" . $time->time() . "'), ('Brazil/DeNoronha', '" . $time->time() . "'), ('Brazil/East', '" . $time->time() . "'), ('Brazil/West', '" . $time->time() . "'), ('Canada/Atlantic', '" . $time->time() . "'), ('Canada/Central', '" . $time->time() . "'), ('Canada/East-Saskatchewan', '" . $time->time() . "'), ('Canada/Eastern', '" . $time->time() . "'), ('Canada/Mountain', '" . $time->time() . "'), ('Canada/Newfoundland', '" . $time->time() . "'), ('Canada/Pacific', '" . $time->time() . "'), ('Canada/Saskatchewan', '" . $time->time() . "'), ('Canada/Yukon', '" . $time->time() . "'), ('Chile/Continental', '" . $time->time() . "'), ('Chile/EasterIsland', '" . $time->time() . "'), ('Cuba', '" . $time->time() . "'), ('Egypt', '" . $time->time() . "'), ('Eire', '" . $time->time() . "'), ('Europe/Amsterdam', '" . $time->time() . "'), ('Europe/Andorra', '" . $time->time() . "'), ('Europe/Athens', '" . $time->time() . "'), ('Europe/Belfast', '" . $time->time() . "'), ('Europe/Belgrade', '" . $time->time() . "'), ('Europe/Berlin', '" . $time->time() . "'), ('Europe/Bratislava', '" . $time->time() . "'), ('Europe/Brussels', '" . $time->time() . "'), ('Europe/Bucharest', '" . $time->time() . "'), ('Europe/Budapest', '" . $time->time() . "'), ('Europe/Busingen', '" . $time->time() . "'), ('Europe/Chisinau', '" . $time->time() . "'), ('Europe/Copenhagen', '" . $time->time() . "'), ('Europe/Dublin', '" . $time->time() . "'), ('Europe/Gibraltar', '" . $time->time() . "'), ('Europe/Guernsey', '" . $time->time() . "'), ('Europe/Helsinki', '" . $time->time() . "'), ('Europe/Isle_of_Man', '" . $time->time() . "'), ('Europe/Istanbul', '" . $time->time() . "'), ('Europe/Jersey', '" . $time->time() . "'), ('Europe/Kaliningrad', '" . $time->time() . "'), ('Europe/Kiev', '" . $time->time() . "'), ('Europe/Lisbon', '" . $time->time() . "'), ('Europe/Ljubljana', '" . $time->time() . "'), ('Europe/London', '" . $time->time() . "'), ('Europe/Luxembourg', '" . $time->time() . "'), ('Europe/Madrid', '" . $time->time() . "'), ('Europe/Malta', '" . $time->time() . "'), ('Europe/Mariehamn', '" . $time->time() . "'), ('Europe/Minsk', '" . $time->time() . "'), ('Europe/Monaco', '" . $time->time() . "'), ('Europe/Moscow', '" . $time->time() . "'), ('Europe/Nicosia', '" . $time->time() . "'), ('Europe/Oslo', '" . $time->time() . "'), ('Europe/Paris', '" . $time->time() . "'), ('Europe/Podgorica', '" . $time->time() . "'), ('Europe/Prague', '" . $time->time() . "'), ('Europe/Riga', '" . $time->time() . "'), ('Europe/Rome', '" . $time->time() . "'), ('Europe/Samara', '" . $time->time() . "'), ('Europe/San_Marino', '" . $time->time() . "'), ('Europe/Sarajevo', '" . $time->time() . "'), ('Europe/Simferopol', '" . $time->time() . "'), ('Europe/Skopje', '" . $time->time() . "'), ('Europe/Sofia', '" . $time->time() . "'), ('Europe/Stockholm', '" . $time->time() . "'), ('Europe/Tallinn', '" . $time->time() . "'), ('Europe/Tirane', '" . $time->time() . "'), ('Europe/Tiraspol', '" . $time->time() . "'), ('Europe/Uzhgorod', '" . $time->time() . "'), ('Europe/Vaduz', '" . $time->time() . "'), ('Europe/Vatican', '" . $time->time() . "'), ('Europe/Vienna', '" . $time->time() . "'), ('Europe/Vilnius', '" . $time->time() . "'), ('Europe/Volgograd', '" . $time->time() . "'), ('Europe/Warsaw', '" . $time->time() . "'), ('Europe/Zagreb', '" . $time->time() . "'), ('Europe/Zaporozhye', '" . $time->time() . "'), ('Europe/Zurich', '" . $time->time() . "'), ('Greenwich', '" . $time->time() . "'), ('Hongkong', '" . $time->time() . "'), ('Iceland', '" . $time->time() . "'), ('Indian/Antananarivo', '" . $time->time() . "'), ('Indian/Chagos', '" . $time->time() . "'), ('Indian/Christmas', '" . $time->time() . "'), ('Indian/Cocos', '" . $time->time() . "'), ('Indian/Comoro', '" . $time->time() . "'), ('Indian/Kerguelen', '" . $time->time() . "'), ('Indian/Mahe', '" . $time->time() . "'), ('Indian/Maldives', '" . $time->time() . "'), ('Indian/Mauritius', '" . $time->time() . "'), ('Indian/Mayotte', '" . $time->time() . "'), ('Indian/Reunion', '" . $time->time() . "'), ('Iran', '" . $time->time() . "'), ('Israel', '" . $time->time() . "'), ('Jamaica', '" . $time->time() . "'), ('Japan', '" . $time->time() . "'), ('Kwajalein', '" . $time->time() . "'), ('Libya', '" . $time->time() . "'), ('Mexico/BajaNorte', '" . $time->time() . "'), ('Mexico/BajaSur', '" . $time->time() . "'), ('Mexico/General', '" . $time->time() . "'), ('Pacific/Apia', '" . $time->time() . "'), ('Pacific/Auckland', '" . $time->time() . "'), ('Pacific/Chatham', '" . $time->time() . "'), ('Pacific/Chuuk', '" . $time->time() . "'), ('Pacific/Easter', '" . $time->time() . "'), ('Pacific/Efate', '" . $time->time() . "'), ('Pacific/Enderbury', '" . $time->time() . "'), ('Pacific/Fakaofo', '" . $time->time() . "'), ('Pacific/Fiji', '" . $time->time() . "'), ('Pacific/Funafuti', '" . $time->time() . "'), ('Pacific/Galapagos', '" . $time->time() . "'), ('Pacific/Gambier', '" . $time->time() . "'), ('Pacific/Guadalcanal', '" . $time->time() . "'), ('Pacific/Guam', '" . $time->time() . "'), ('Pacific/Honolulu', '" . $time->time() . "'), ('Pacific/Johnston', '" . $time->time() . "'), ('Pacific/Kiritimati', '" . $time->time() . "'), ('Pacific/Kosrae', '" . $time->time() . "'), ('Pacific/Kwajalein', '" . $time->time() . "'), ('Pacific/Majuro', '" . $time->time() . "'), ('Pacific/Marquesas', '" . $time->time() . "'), ('Pacific/Midway', '" . $time->time() . "'), ('Pacific/Nauru', '" . $time->time() . "'), ('Pacific/Niue', '" . $time->time() . "'), ('Pacific/Norfolk', '" . $time->time() . "'), ('Pacific/Noumea', '" . $time->time() . "'), ('Pacific/Pago_Pago', '" . $time->time() . "'), ('Pacific/Palau', '" . $time->time() . "'), ('Pacific/Pitcairn', '" . $time->time() . "'), ('Pacific/Pohnpei', '" . $time->time() . "'), ('Pacific/Ponape', '" . $time->time() . "'), ('Pacific/Port_Moresby', '" . $time->time() . "'), ('Pacific/Rarotonga', '" . $time->time() . "'), ('Pacific/Saipan', '" . $time->time() . "'), ('Pacific/Samoa', '" . $time->time() . "'), ('Pacific/Tahiti', '" . $time->time() . "'), ('Pacific/Tarawa', '" . $time->time() . "'), ('Pacific/Tongatapu', '" . $time->time() . "'), ('Pacific/Truk', '" . $time->time() . "'), ('Pacific/Wake', '" . $time->time() . "'), ('Pacific/Wallis', '" . $time->time() . "'), ('Pacific/Yap', '" . $time->time() . "'), ('Poland', '" . $time->time() . "'), ('Portugal', '" . $time->time() . "'), ('Singapore', '" . $time->time() . "'), ('Turkey', '" . $time->time() . "'), ('US/Alaska', '" . $time->time() . "'), ('US/Aleutian', '" . $time->time() . "'), ('US/Arizona', '" . $time->time() . "'), ('US/Central', '" . $time->time() . "'), ('US/East-Indiana', '" . $time->time() . "'), ('US/Eastern', '" . $time->time() . "'), ('US/Hawaii', '" . $time->time() . "'), ('US/Indiana-Starke', '" . $time->time() . "'), ('US/Michigan', '" . $time->time() . "'), ('US/Mountain', '" . $time->time() . "'), ('US/Pacific', '" . $time->time() . "'), ('US/Pacific-New', '" . $time->time() . "'), ('US/Samoa', '" . $time->time() . "'), ('Zulu', '" . $time->time() . "');";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0023',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0023;

    }

    // upgrade database from 2.0023 to 2.0024
    if ($current_db_version == 2.0023) {

        $sql = "ALTER TABLE `settings`
                    CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0024',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0024;

    }

    // upgrade database from 2.0024 to 2.0025
    if ($current_db_version == 2.0024) {

        $sql = "CREATE TABLE IF NOT EXISTS `hosting` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `notes` LONGTEXT NOT NULL,
                `default_host` INT(1) NOT NULL DEFAULT '0',
                `active` INT(1) NOT NULL DEFAULT '1',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "INSERT INTO `hosting`
                    (`name`, `default_host`, `insert_time`) VALUES
                    ('[no hosting]', 1, '" . $time->time() . "');";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    ADD `hosting_id` INT(10) NOT NULL DEFAULT '1' AFTER `ip_id`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM hosting
                WHERE name = '[no hosting]'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {
            $temp_hosting_id = $row->id;
        }

        $sql = "UPDATE domains
                SET hosting_id = '" . $temp_hosting_id . "',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    CHANGE `owner_id` `owner_id` INT(5) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    CHANGE `registrar_id` `registrar_id` INT(5) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    CHANGE `account_id` `account_id` INT(5) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `domains`
                    CHANGE `dns_id` `dns_id` INT(5) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0025',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0025;

    }

    // upgrade database from 2.0025 to 2.0026
    if ($current_db_version == 2.0025) {

        $sql = "ALTER TABLE `user_settings`
                    ADD `display_domain_host` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_dns`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0026',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0026;

    }

    // upgrade database from 2.0026 to 2.0027
    if ($current_db_version == 2.0026) {

        $sql = "ALTER TABLE `registrar_accounts`
                    ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0027',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0027;

    }

    // upgrade database from 2.0027 to 2.0028
    if ($current_db_version == 2.0027) {

        $sql = "ALTER TABLE `ssl_accounts`
                    ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0028',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0028;

    }

    // upgrade database from 2.0028 to 2.0029
    if ($current_db_version == 2.0028) {

        $sql = "ALTER TABLE `dns`
                    ADD `ip1` VARCHAR(255) NOT NULL AFTER `dns10`,
                    ADD `ip2` VARCHAR(255) NOT NULL AFTER `ip1`,
                    ADD `ip3` VARCHAR(255) NOT NULL AFTER `ip2`,
                    ADD `ip4` VARCHAR(255) NOT NULL AFTER `ip3`,
                    ADD `ip5` VARCHAR(255) NOT NULL AFTER `ip4`,
                    ADD `ip6` VARCHAR(255) NOT NULL AFTER `ip5`,
                    ADD `ip7` VARCHAR(255) NOT NULL AFTER `ip6`,
                    ADD `ip8` VARCHAR(255) NOT NULL AFTER `ip7`,
                    ADD `ip9` VARCHAR(255) NOT NULL AFTER `ip8`,
                    ADD `ip10` VARCHAR(255) NOT NULL AFTER `ip9`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `settings`
                    ADD `expiration_email_days` INT(3) NOT NULL DEFAULT '60' AFTER `timezone`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0029',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0029;

    }

    // upgrade database from 2.0029 to 2.003
    if ($current_db_version == 2.0029) {

        $sql = "ALTER TABLE `domains`
                    ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id, status, status_notes, notes
                FROM domains";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        while ($row = mysqli_fetch_object($result)) {

            if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

                $full_status = "";
                $full_status_notes = "";
                $new_notes = "";

                if ($row->status != "") {

                    $full_status .= "--------------------\r\n";
                    $full_status .= "OLD STATUS - INSERTED " . $time->time() . "\r\n";
                    $full_status .= "The Status field was removed because it was redundant.\r\n";
                    $full_status .= "--------------------\r\n";
                    $full_status .= $row->status . "\r\n";
                    $full_status .= "--------------------";

                } else {

                    $full_status = "";

                }

                if ($row->status_notes != "") {

                    $full_status_notes .= "--------------------\r\n";
                    $full_status_notes .= "OLD STATUS NOTES - INSERTED " . $time->time() . "\r\n";
                    $full_status_notes .= "The Status Notes field was removed because it was redundant.\r\n";
                    $full_status_notes .= "--------------------\r\n";
                    $full_status_notes .= $row->status_notes . "\r\n";
                    $full_status_notes .= "--------------------";

                } else {

                    $full_status_notes = "";

                }

                if ($row->notes != "") {

                    if ($full_status != "" && $full_status_notes != "") {

                        $new_notes = $full_status . "\r\n\r\n" . $full_status_notes . "\r\n\r\n" . $row->notes;

                    } elseif ($full_status != "" && $full_status_notes == "") {

                        $new_notes = $full_status . "\r\n\r\n" . $row->notes;

                    } elseif ($full_status == "" && $full_status_notes != "") {

                        $new_notes = $full_status_notes . "\r\n\r\n" . $row->notes;

                    } elseif ($full_status == "" && $full_status_notes == "") {

                        $new_notes = $row->notes;

                    }

                } elseif ($row->notes == "") {

                    if ($full_status != "" && $full_status_notes != "") {

                        $new_notes = $full_status . "\r\n\r\n" . $full_status_notes;

                    } elseif ($full_status != "" && $full_status_notes == "") {

                        $new_notes = $full_status;

                    } elseif ($full_status == "" && $full_status_notes != "") {

                        $new_notes = $full_status_notes;

                    }

                }

                $sql_update = "UPDATE domains
                               SET notes = '" . trim(mysqli_real_escape_string($connection, $new_notes)) . "',
                                      notes_fixed_temp = '1',
                                   update_time = '" . $time->time() . "'
                               WHERE id = '" . $row->id . "'";
                $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

            } else {

                $sql_update = "UPDATE domains
                               SET notes_fixed_temp = '1',
                                   update_time = '" . $time->time() . "'
                               WHERE id = '" . $row->id . "'";
                $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
            }

        }

        $sql = "SELECT *
                FROM domains
                WHERE notes_fixed_temp = '0'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            echo "DATABASE UPDATE v2.003 FAILED: PLEASE CONTACT YOUR " . strtoupper($software_title) . " ADMINISTRATOR IMMEDIATELY";
            exit;

        } else {

            $sql = "ALTER TABLE `domains`
                        DROP `status`,
                        DROP `status_notes`,
                        DROP `notes_fixed_temp`";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        }

        $sql = "UPDATE settings
                SET db_version = '2.003',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.003;

    }

    // upgrade database from 2.003 to 2.0031
    if ($current_db_version == 2.003) {

        $sql = "ALTER TABLE `categories`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `currencies`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `dns`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `hosting`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ip_addresses`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `owners`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `registrars`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `registrar_accounts`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `segments`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_accounts`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_cert_types`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_providers`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_providers`
                    DROP `active`;";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0031',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0031;

    }

    // upgrade database from 2.0031 to 2.0032
    if ($current_db_version == 2.0031) {

        $sql = "ALTER TABLE `fees`
                ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE fees
                SET transfer_fee = initial_fee,
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql);

        // This section was made redundant by DB update v2.0033
        /*
        $sql = "ALTER TABLE `ssl_fees`
                ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
        $result = mysqli_query($connection, $sql);
        */

        $sql = "UPDATE settings
                SET db_version = '2.0032',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0032;

    }

    // upgrade database from 2.0032 to 2.0033
    if ($current_db_version == 2.0032) {

        $sql = "ALTER TABLE `ssl_fees`
                DROP `transfer_fee`;";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0033',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0033;

    }

    // upgrade database from 2.0033 to 2.0034
    if ($current_db_version == 2.0033) {

        $sql = "ALTER TABLE `domains`
                CHANGE `owner_id` `owner_id` INT(10) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `domains`
                CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `domains`
                CHANGE `account_id` `account_id` INT(10) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `domains`
                CHANGE `dns_id` `dns_id` INT(10) NOT NULL DEFAULT '1'";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `fees`
                CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `registrar_accounts`
                CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_accounts`
                CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_certs`
                CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_certs`
                CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_certs`
                CHANGE `account_id` `account_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_fees`
                CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_fees`
                CHANGE `type_id` `type_id` INT(10) NOT NULL";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0034',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0034;

    }

    // upgrade database from 2.0034 to 2.0035
    if ($current_db_version == 2.0034) {

        $sql = "ALTER DATABASE " . $dbname . "
                CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE categories CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE currencies CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE dns CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE domains CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE hosting CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ip_addresses CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE owners CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE registrars CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE registrar_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE segments CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE segment_data CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_certs CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_cert_types CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_providers CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE timezones CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE users CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE user_settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE categories CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE currencies CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE dns CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE domains CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE hosting CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ip_addresses CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE owners CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE registrars CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE registrar_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE segments CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE segment_data CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_certs CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_cert_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE ssl_providers CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE timezones CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE user_settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0035',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0035;

    }

    // upgrade database from 2.0035 to 2.0036
    if ($current_db_version == 2.0035) {

        $sql = "DROP TABLE `currency_data`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER DATABASE " . $dbname . "
                CHARACTER SET utf8
                DEFAULT CHARACTER SET utf8
                COLLATE utf8_unicode_ci
                DEFAULT COLLATE utf8_unicode_ci;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `currencies`
                ADD `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `conversion`,
                ADD `symbol_order` INT(1) NOT NULL DEFAULT '0' AFTER `symbol`,
                ADD `symbol_space` INT(1) NOT NULL DEFAULT '0' AFTER `symbol_order`,
                ADD `newly_inserted` INT(1) NOT NULL DEFAULT '1' AFTER `symbol_space`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE currencies
                SET newly_inserted = '0',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `settings`
                ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `user_settings`
                ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET default_currency = '" . $_SESSION['default_currency'] . "',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE user_settings
                SET default_currency = '" . $_SESSION['default_currency'] . "',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "INSERT INTO currencies
                (name, currency, symbol, insert_time) VALUES
                ('Albania Lek', 'ALL', 'Lek', '" . $time->time() . "'),
                ('Afghanistan Afghani', 'AFN', '؋', '" . $time->time() . "'),
                ('Argentina Peso', 'ARS', '$', '" . $time->time() . "'),
                ('Aruba Guilder', 'AWG', 'ƒ', '" . $time->time() . "'),
                ('Australia Dollar', 'AUD', '$', '" . $time->time() . "'),
                ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $time->time() . "'),
                ('Bahamas Dollar', 'BSD', '$', '" . $time->time() . "'),
                ('Barbados Dollar', 'BBD', '$', '" . $time->time() . "'),
                ('Belarus Ruble', 'BYR', 'p.', '" . $time->time() . "'),
                ('Belize Dollar', 'BZD', 'BZ$', '" . $time->time() . "'),
                ('Bermuda Dollar', 'BMD', '$', '" . $time->time() . "'),
                ('Bolivia Boliviano', 'BOB', '\$b', '" . $time->time() . "'),
                ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $time->time() . "'),
                ('Botswana Pula', 'BWP', 'P', '" . $time->time() . "'),
                ('Bulgaria Lev', 'BGN', 'лв', '" . $time->time() . "'),
                ('Brazil Real', 'BRL', 'R$', '" . $time->time() . "'),
                ('Brunei Darussalam Dollar', 'BND', '$', '" . $time->time() . "'),
                ('Cambodia Riel', 'KHR', '៛', '" . $time->time() . "'),
                ('Canada Dollar', 'CAD', '$', '" . $time->time() . "'),
                ('Cayman Islands Dollar', 'KYD', '$', '" . $time->time() . "'),
                ('Chile Peso', 'CLP', '$', '" . $time->time() . "'),
                ('China Yuan Renminbi', 'CNY', '¥', '" . $time->time() . "'),
                ('Colombia Peso', 'COP', '$', '" . $time->time() . "'),
                ('Costa Rica Colon', 'CRC', '₡', '" . $time->time() . "'),
                ('Croatia Kuna', 'HRK', 'kn', '" . $time->time() . "'),
                ('Cuba Peso', 'CUP', '₱', '" . $time->time() . "'),
                ('Czech Republic Koruna', 'CZK', 'Kč', '" . $time->time() . "'),
                ('Denmark Krone', 'DKK', 'kr', '" . $time->time() . "'),
                ('Dominican Republic Peso', 'DOP', 'RD$', '" . $time->time() . "'),
                ('East Caribbean Dollar', 'XCD', '$', '" . $time->time() . "'),
                ('Egypt Pound', 'EGP', '£', '" . $time->time() . "'),
                ('El Salvador Colon', 'SVC', '$', '" . $time->time() . "'),
                ('Estonia Kroon', 'EEK', 'kr', '" . $time->time() . "'),
                ('Euro Member Countries', 'EUR', '€', '" . $time->time() . "'),
                ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $time->time() . "'),
                ('Fiji Dollar', 'FJD', '$', '" . $time->time() . "'),
                ('Ghana Cedis', 'GHC', '¢', '" . $time->time() . "'),
                ('Gibraltar Pound', 'GIP', '£', '" . $time->time() . "'),
                ('Guatemala Quetzal', 'GTQ', 'Q', '" . $time->time() . "'),
                ('Guernsey Pound', 'GGP', '£', '" . $time->time() . "'),
                ('Guyana Dollar', 'GYD', '$', '" . $time->time() . "'),
                ('Honduras Lempira', 'HNL', 'L', '" . $time->time() . "'),
                ('Hong Kong Dollar', 'HKD', '$', '" . $time->time() . "'),
                ('Hungary Forint', 'HUF', 'Ft', '" . $time->time() . "'),
                ('Iceland Krona', 'ISK', 'kr', '" . $time->time() . "'),
                ('India Rupee', 'INR', 'Rs', '" . $time->time() . "'),
                ('Indonesia Rupiah', 'IDR', 'Rp', '" . $time->time() . "'),
                ('Iran Rial', 'IRR', '﷼', '" . $time->time() . "'),
                ('Isle of Man Pound', 'IMP', '£', '" . $time->time() . "'),
                ('Israel Shekel', 'ILS', '₪', '" . $time->time() . "'),
                ('Jamaica Dollar', 'JMD', 'J$', '" . $time->time() . "'),
                ('Japan Yen', 'JPY', '¥', '" . $time->time() . "'),
                ('Jersey Pound', 'JEP', '£', '" . $time->time() . "'),
                ('Kazakhstan Tenge', 'KZT', 'лв', '" . $time->time() . "'),
                ('Korea (North) Won', 'KPW', '₩', '" . $time->time() . "'),
                ('Korea (South) Won', 'KRW', '₩', '" . $time->time() . "'),
                ('Kyrgyzstan Som', 'KGS', 'лв', '" . $time->time() . "'),
                ('Laos Kip', 'LAK', '₭', '" . $time->time() . "'),
                ('Latvia Lat', 'LVL', 'Ls', '" . $time->time() . "'),
                ('Lebanon Pound', 'LBP', '£', '" . $time->time() . "'),
                ('Liberia Dollar', 'LRD', '$', '" . $time->time() . "'),
                ('Lithuania Litas', 'LTL', 'Lt', '" . $time->time() . "'),
                ('Macedonia Denar', 'MKD', 'ден', '" . $time->time() . "'),
                ('Malaysia Ringgit', 'RM', 'RM', '" . $time->time() . "'),
                ('Mauritius Rupee', 'MUR', '₨', '" . $time->time() . "'),
                ('Mexico Peso', 'MXN', '$', '" . $time->time() . "'),
                ('Mongolia Tughrik', 'MNT', '₮', '" . $time->time() . "'),
                ('Mozambique Metical', 'MZN', 'MT', '" . $time->time() . "'),
                ('Namibia Dollar', 'NAD', '$', '" . $time->time() . "'),
                ('Nepal Rupee', 'NPR', '₨', '" . $time->time() . "'),
                ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $time->time() . "'),
                ('New Zealand Dollar', 'NZD', '$', '" . $time->time() . "'),
                ('Nicaragua Cordoba', 'NIO', 'C$', '" . $time->time() . "'),
                ('Nigeria Naira', 'NGN', '₦', '" . $time->time() . "'),
                ('Norway Krone', 'NOK', 'kr', '" . $time->time() . "'),
                ('Oman Rial', 'OMR', '﷼', '" . $time->time() . "'),
                ('Pakistan Rupee', 'PKR', '₨', '" . $time->time() . "'),
                ('Panama Balboa', 'PAB', 'B/.', '" . $time->time() . "'),
                ('Paraguay Guarani', 'PYG', 'Gs', '" . $time->time() . "'),
                ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $time->time() . "'),
                ('Philippines Peso', 'PHP', '₱', '" . $time->time() . "'),
                ('Poland Zloty', 'PLN', 'zł', '" . $time->time() . "'),
                ('Qatar Riyal', 'QAR', '﷼', '" . $time->time() . "'),
                ('Romania New Leu', 'RON', 'lei', '" . $time->time() . "'),
                ('Russia Ruble', 'RUB', 'руб', '" . $time->time() . "'),
                ('Saint Helena Pound', 'SHP', '£', '" . $time->time() . "'),
                ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $time->time() . "'),
                ('Serbia Dinar', 'RSD', 'Дин.', '" . $time->time() . "'),
                ('Seychelles Rupee', 'SCR', '₨', '" . $time->time() . "'),
                ('Singapore Dollar', 'SGD', '$', '" . $time->time() . "'),
                ('Solomon Islands Dollar', 'SBD', '$', '" . $time->time() . "'),
                ('Somalia Shilling', 'SOS', 'S', '" . $time->time() . "'),
                ('South Africa Rand', 'ZAR', 'R', '" . $time->time() . "'),
                ('Sri Lanka Rupee', 'LKR', '₨', '" . $time->time() . "'),
                ('Sweden Krona', 'SEK', 'kr', '" . $time->time() . "'),
                ('Switzerland Franc', 'CHF', 'CHF', '" . $time->time() . "'),
                ('Suriname Dollar', 'SRD', '$', '" . $time->time() . "'),
                ('Syria Pound', 'SYP', '£', '" . $time->time() . "'),
                ('Taiwan New Dollar', 'TWD', 'NT$', '" . $time->time() . "'),
                ('Thailand Baht', 'THB', '฿', '" . $time->time() . "'),
                ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $time->time() . "'),
                ('Turkey Lira', 'TRY', '₤', '" . $time->time() . "'),
                ('Tuvalu Dollar', 'TVD', '$', '" . $time->time() . "'),
                ('Ukraine Hryvna', 'UAH', '₴', '" . $time->time() . "'),
                ('United Kingdom Pound', 'GBP', '£', '" . $time->time() . "'),
                ('United States Dollar', 'USD', '$', '" . $time->time() . "'),
                ('Uruguay Peso', 'UYU', '\$U', '" . $time->time() . "'),
                ('Uzbekistan Som', 'UZS', 'лв', '" . $time->time() . "'),
                ('Venezuela Bolivar', 'VEF', 'Bs', '" . $time->time() . "'),
                ('Viet Nam Dong', 'VND', '₫', '" . $time->time() . "'),
                ('Yemen Rial', 'YER', '﷼', '" . $time->time() . "'),
                ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $time->time() . "'),
                ('Emirati Dirham', 'AED', 'د.إ', '" . $time->time() . "'),
                ('Malaysian Ringgit', 'MYR', 'RM', '" . $time->time() . "'),
                ('Kuwaiti Dinar', 'KWD', 'ك', '" . $time->time() . "'),
                ('Moroccan Dirham', 'MAD', 'م.', '" . $time->time() . "'),
                ('Iraqi Dinar', 'IQD', 'د.ع', '" . $time->time() . "'),
                ('Bangladeshi Taka', 'BDT', 'Tk', '" . $time->time() . "'),
                ('Bahraini Dinar', 'BHD', 'BD', '" . $time->time() . "'),
                ('Kenyan Shilling', 'KES', 'KSh', '" . $time->time() . "'),
                ('CFA Franc', 'XOF', 'CFA', '" . $time->time() . "'),
                ('Jordanian Dinar', 'JOD', 'JD', '" . $time->time() . "'),
                ('Tunisian Dinar', 'TND', 'د.ت', '" . $time->time() . "'),
                ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $time->time() . "'),
                ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $time->time() . "'),
                ('Algerian Dinar', 'DZD', 'دج', '" . $time->time() . "'),
                ('CFP Franc', 'XPF', 'F', '" . $time->time() . "'),
                ('Ugandan Shilling', 'UGX', 'USh', '" . $time->time() . "'),
                ('Tanzanian Shilling', 'TZS', 'TZS', '" . $time->time() . "'),
                ('Ethiopian Birr', 'ETB', 'Br', '" . $time->time() . "'),
                ('Georgian Lari', 'GEL', 'GEL', '" . $time->time() . "'),
                ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $time->time() . "'),
                ('Burmese Kyat', 'MMK', 'K', '" . $time->time() . "'),
                ('Libyan Dinar', 'LYD', 'LD', '" . $time->time() . "'),
                ('Zambian Kwacha', 'ZMK', 'ZK', '" . $time->time() . "'),
                ('Zambian Kwacha', 'ZMW', 'ZK', '" . $time->time() . "'),
                ('Macau Pataca', 'MOP', 'MOP$', '" . $time->time() . "'),
                ('Armenian Dram', 'AMD', 'AMD', '" . $time->time() . "'),
                ('Angolan Kwanza', 'AOA', 'Kz', '" . $time->time() . "'),
                ('Papua New Guinean Kina', 'PGK', 'K', '" . $time->time() . "'),
                ('Malagasy Ariary', 'MGA', 'Ar', '" . $time->time() . "'),
                ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $time->time() . "'),
                ('Sudanese Pound', 'SDG', 'SDG', '" . $time->time() . "'),
                ('Malawian Kwacha', 'MWK', 'MK', '" . $time->time() . "'),
                ('Rwandan Franc', 'RWF', 'FRw', '" . $time->time() . "'),
                ('Gambian Dalasi', 'GMD', 'D', '" . $time->time() . "'),
                ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $time->time() . "'),
                ('Congolese Franc', 'CDF', 'FC', '" . $time->time() . "'),
                ('Djiboutian Franc', 'DJF', 'Fdj', '" . $time->time() . "'),
                ('Haitian Gourde', 'HTG', 'G', '" . $time->time() . "'),
                ('Samoan Tala', 'WST', '$', '" . $time->time() . "'),
                ('Guinean Franc', 'GNF', 'FG', '" . $time->time() . "'),
                ('Cape Verdean Escudo', 'CVE', '$', '" . $time->time() . "'),
                ('Tongan Pa\'anga', 'TOP', 'T$', '" . $time->time() . "'),
                ('Moldovan Leu', 'MDL', 'MDL', '" . $time->time() . "'),
                ('Sierra Leonean Leone', 'SLL', 'Le', '" . $time->time() . "'),
                ('Burundian Franc', 'BIF', 'FBu', '" . $time->time() . "'),
                ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $time->time() . "'),
                ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $time->time() . "'),
                ('Swazi Lilangeni', 'SZL', 'SZL', '" . $time->time() . "'),
                ('Tajikistani Somoni', 'TJS', 'TJS', '" . $time->time() . "'),
                ('Turkmenistani Manat', 'TMT', 'm', '" . $time->time() . "'),
                ('Basotho Loti', 'LSL', 'LSL', '" . $time->time() . "'),
                ('Comoran Franc', 'KMF', 'CF', '" . $time->time() . "'),
                ('Sao Tomean Dobra', 'STD', 'STD', '" . $time->time() . "'),
                ('Seborgan Luigino', 'SPL', 'SPL', '" . $time->time() . "')";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT id, currency
                FROM currencies
                WHERE newly_inserted = '0'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        while ($row = mysqli_fetch_object($result)) {

            $sql_find_new = "SELECT id, symbol
                             FROM currencies
                             WHERE newly_inserted = '1'
                               AND currency = '" . $row->currency . "'";
            $result_find_new = mysqli_query($connection, $sql_find_new);
            $total_results = mysqli_num_rows($result_find_new);

            while ($row_find_new = mysqli_fetch_object($result_find_new)) {

                if ($total_results > 0) {

                    $sql_update_old = "UPDATE currencies
                                       SET symbol = '" . $row_find_new->symbol . "'
                                       WHERE id = '" . $row->id . "'";
                    $result_update_old = mysqli_query($connection, $sql_update_old);

                    $sql_delete_new = "DELETE FROM currencies
                                       WHERE id = '" . $row_find_new->id . "'";
                    $result_delete_new = mysqli_query($connection, $sql_delete_new);

                }

            }

        }

        $sql = "ALTER TABLE `currencies`
                DROP `newly_inserted`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0036',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0036;

    }

    // upgrade database from 2.0036 to 2.0037
    if ($current_db_version == 2.0036) {

        $sql = "SELECT currency
                FROM currencies
                WHERE default_currency = '1'";
        $result = mysqli_query($connection, $sql);
        while ($row = mysqli_fetch_object($result)) {
            $temp_currency = $row->currency;
        }

        $sql = "UPDATE settings
                SET default_currency = '" . $temp_currency . "'";
        $result = mysqli_query($connection, $sql);

        $_SESSION['default_currency'] = $temp_currency;

        $sql = "SELECT name, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = '" . $_SESSION['default_currency'] . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $_SESSION['default_currency_name'] = $row->name;
            $_SESSION['default_currency_symbol'] = $row->symbol;
            $_SESSION['default_currency_symbol_order'] = $row->symbol_order;
            $_SESSION['default_currency_symbol_space'] = $row->symbol_space;
        }

        $sql = "ALTER TABLE `currencies`
                DROP `default_currency`;";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `user_settings`
                DROP `default_currency`;";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0037',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0037;

    }

    // upgrade database from 2.0037 to 2.0038
    if ($current_db_version == 2.0037) {

        $sql = "ALTER TABLE `user_settings`
                ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER user_id";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT default_currency
                FROM settings";
        $result = mysqli_query($connection, $sql);
        while ($row = mysqli_fetch_object($result)) {
            $temp_default_currency = $row->default_currency;
            $_SESSION['default_currency'] = $row->default_currency;
        }

        $sql = "SELECT name, symbol, symbol_order, symbol_space
                FROM currencies
                WHERE currency = '" . $_SESSION['default_currency'] . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $_SESSION['default_currency_name'] = $row->name;
            $_SESSION['default_currency_symbol'] = $row->symbol;
            $_SESSION['default_currency_symbol_order'] = $row->symbol_order;
            $_SESSION['default_currency_symbol_space'] = $row->symbol_space;

        }

        $sql = "UPDATE user_settings
                SET default_currency = '" . $temp_default_currency . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `currency_id` INT(10) NOT NULL,
                `user_id` INT(10) NOT NULL,
                `conversion` FLOAT NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT id
                FROM users";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_conversion = "SELECT id, conversion
                               FROM currencies
                               WHERE conversion != '0'";
            $result_conversion = mysqli_query($connection, $sql_conversion);

            while ($row_conversion = mysqli_fetch_object($result_conversion)) {

                $sql_insert = "INSERT INTO currency_conversions
                               (currency_id, user_id, conversion, insert_time, update_time) VALUES
                               ('" . $row_conversion->id . "', '" . $row->id . "', '" . $row_conversion->conversion . "', '" . $time->time() . "', '" . $time->time() . "')";
                $result_insert = mysqli_query($connection, $sql_insert);

            }

        }

        $sql = "ALTER TABLE `currencies`
                DROP `conversion`;";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0038',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0038;

    }

    // upgrade database from 2.0038 to 2.0039
    if ($current_db_version == 2.0038) {


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
                               update_time = '" . $time->time() . "'
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
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0039;

    }

    // upgrade database from 2.0039 to 2.004
    if ($current_db_version == 2.0039) {

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

            $_SESSION['default_category'] = $row->id;
            $_SESSION['system_default_category'] = $row->id;

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

            $_SESSION['default_dns'] = $row->id;
            $_SESSION['system_default_dns'] = $row->id;

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

            $_SESSION['default_host'] = $row->id;
            $_SESSION['system_default_host'] = $row->id;

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

            $_SESSION['default_ip_address'] = $row->id;
            $_SESSION['system_default_ip_address'] = $row->id;

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

            $_SESSION['default_owner'] = $row->id;
            $_SESSION['system_default_owner'] = $row->id;

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

            $_SESSION['default_registrar'] = $row->id;
            $_SESSION['system_default_registrar'] = $row->id;

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

            $_SESSION['default_registrar_account'] = $row->id;
            $_SESSION['system_default_registrar_account'] = $row->id;

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

            $_SESSION['default_ssl_provider_account'] = $row->id;
            $_SESSION['system_default_ssl_provider_account'] = $row->id;

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

            $_SESSION['default_ssl_type'] = $row->id;
            $_SESSION['system_default_ssl_type'] = $row->id;

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

            $_SESSION['default_ssl_provider'] = $row->id;
            $_SESSION['system_default_ssl_provider'] = $row->id;

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

        $_SESSION['default_timezone'] = $temp_default_system_timezone;
        $_SESSION['system_default_timezone'] = $temp_default_system_timezone;

        $sql = "ALTER TABLE `settings`
                DROP `default_currency`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "ALTER TABLE `settings`
                DROP `default_timezone`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.004',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.004;

    }

    // upgrade database from 2.004 to 2.0041
    if ($current_db_version == 2.004) {

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
                WHERE user_id = '" . $_SESSION['user_id'] . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {

            $default_category_domains = $row->default_category_domains;
            $default_ip_address_domains = $row->default_ip_address_domains;
            $default_owner_domains = $row->default_owner_domains;

        }

        $_SESSION['default_category_domains'] = $default_category_domains;
        $_SESSION['default_category_ssl'] = $default_category_domains;
        $_SESSION['default_ip_address_domains'] = $default_ip_address_domains;
        $_SESSION['default_ip_address_ssl'] = $default_ip_address_domains;
        $_SESSION['default_owner_domains'] = $default_owner_domains;
        $_SESSION['default_owner_ssl'] = $default_owner_domains;

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

        $_SESSION['system_default_category_domains'] = $default_category_domains;
        $_SESSION['system_default_category_ssl'] = $default_category_domains;
        $_SESSION['system_default_ip_address_domains'] = $default_ip_address_domains;
        $_SESSION['system_default_ip_address_ssl'] = $default_ip_address_domains;
        $_SESSION['system_default_owner_domains'] = $default_owner_domains;
        $_SESSION['system_default_owner_ssl'] = $default_owner_domains;

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
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0041;

    }

    // upgrade database from 2.0041 to 2.0042
    if ($current_db_version == 2.0041) {

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
                               ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->time() . "')";
                $result_insert = mysqli_query($connection, $sql_insert);

            }

        }

        $_SESSION['are_there_updates'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.0042',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        */

        $current_db_version = 2.0042;

    }

    // upgrade database from 2.0042 to 2.0043
    if ($current_db_version == 2.0042) {

        $sql = "ALTER TABLE `segments`
                CHANGE `name` `name` VARCHAR(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0043',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0043;

    }

    // upgrade database from 2.0043 to 2.0044
    if ($current_db_version == 2.0043) {

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
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0044;

    }

    // upgrade database from 2.0044 to 2.0045
    if ($current_db_version == 2.0044) {

        $sql = "ALTER TABLE `segments`
                CHANGE `name` `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0045',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0045;

    }

    // upgrade database from 2.0045 to 2.0046
    if ($current_db_version == 2.0045) {

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
                               ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->time() . "')";
                $result_insert = mysqli_query($connection, $sql_insert);

            }

        }

        $_SESSION['are_there_updates'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.0046',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        */

        $current_db_version = 2.0046;

    }

    // upgrade database from 2.0046 to 2.0047
    if ($current_db_version == 2.0046) {

        $sql = "ALTER TABLE `hosting`
                ADD `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER name";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0047',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0047;

    }

    // upgrade database from 2.0047 to 2.0048
    if ($current_db_version == 2.0047) {

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
                (1, 'Check Box', '" . $time->time() . "'),
                (2, 'Text', '" . $time->time() . "'),
                (3, 'Text Area', '" . $time->time() . "')";
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

            $full_id_string .= "('" . $row->id . "', '" . $time->time() . "'), ";

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

                $full_id_string .= "('" . $row->id . "', '" . $time->time() . "'), ";

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
                           ('" . $row->id . "', '" . $temp_update_id . "', '" . $time->time() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

        $_SESSION['are_there_updates'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.0048',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        */

        $current_db_version = 2.0048;

    }

    // upgrade database from 2.0048 to 2.0049
    if ($current_db_version == 2.0048) {

        $sql = "CREATE TABLE IF NOT EXISTS `dw_servers` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `host` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `protocol` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `port` INT(5) NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `hash` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `dw_accounts` INT(10) NOT NULL,
                `dw_dns_zones` INT(10) NOT NULL,
                `dw_dns_records` INT(10) NOT NULL,
                `build_status` INT(1) NOT NULL DEFAULT '0',
                `build_start_time` DATETIME NOT NULL,
                `build_end_time` DATETIME NOT NULL,
                `build_time` INT(10) NOT NULL DEFAULT '0',
                `has_ever_been_built` INT(1) NOT NULL DEFAULT '0',
                `build_status_overall` INT(1) NOT NULL DEFAULT '0',
                `build_start_time_overall` DATETIME NOT NULL,
                `build_end_time_overall` DATETIME NOT NULL,
                `build_time_overall` INT(10) NOT NULL DEFAULT '0',
                `has_ever_been_built_overall` INT(1) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        // This section was made redundant by DB update v2.005
        /*
        $sql = "INSERT INTO updates
                (name, `update`, insert_time, update_time) VALUES
                ('Domain Manager now includes a Data Warehouse for importing data', 'Domain Manager now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout Domain Manager where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><font class=\"default_highlight\">NOTE:</font> Importing your server into the data warehouse will not modify any of your Domain Manager data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "SELECT id
                FROM `updates`
                WHERE name = 'Domain Manager now includes a Data Warehouse for importing data'
                  AND insert_time = '2013-06-01 1:00:00'";
        $result = mysqli_query($connection, $sql);
        while ($row = mysqli_fetch_object($result)) { $temp_update_id = $row->id; }

        $sql = "SELECT id
                FROM users";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_insert = "INSERT INTO
                           update_data
                           (user_id, update_id, insert_time) VALUES
                           ('" . $row->id . "', '" . $temp_update_id . "', '" . $time->time() . "')";
            $result_insert = mysqli_query($connection, $sql_insert);

        }

        $_SESSION['are_there_updates'] = "1";
        */

        $sql = "UPDATE settings
                SET db_version = '2.0049',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0049;

    }

    // upgrade database from 2.0049 to 2.005
    if ($current_db_version == 2.0049) {

        // This section was made redundant by DB update v2.0051
        /*
        $sql = "DROP TABLE IF EXISTS `updates`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "DROP TABLE IF EXISTS `update_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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
                ('" . $software_title . " now contains a Software Updates section!', '<em>[This feature was implemented on 2013-05-04, but it seemed appropriate that the very first post in the Software Updates section be information about the new section itself, so the post was duplicated and backdated]</em><BR><BR>After upgrading " . $software_title . " I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-03-20 00:00:00', '2013-03-20 00:00:00'),
                ('Support has been added for automatic currency updates!', 'Thanks to Yahoo! Finance\'s free API, I\'m happy to announce that currency conversions have been completely automated. Now instead of having to manually update the conversions one-by-one on a regular basis to ensure proper financial reporting, all you have to do is make sure your default currency is set and your conversion rates will be updated automatically and seemlessly in the background while you use the software.<BR><BR>To say that this feature pleases me would be a huge understatement. I personally use " . $software_title . " on a daily basis, and updating the currency conversions manually was always such a boring, tedious task, and I\'m happy that nobody will ever have to go through that process ever again. If I could give Yahoo! Finance a big hug, I would.', '2013-03-20 00:00:01', '2013-03-20 00:00:01'),
                ('A new \'IP Address\' section has been added to the UI so that you can keep track of all your IP Addresses within " . $software_title . "', '', '2013-03-26 00:00:00', '2013-03-26 00:00:00'),
                ('Test Data System removed, Demo launched', 'In order to focus on the development of the actual " . $software_title . " software, I\'ve decided to remove the Test Data System entirely. Although this system allowed users to easily generate some test data and get a feel for the software, it complicated the development process and added unecessary overhead to the software as a whole. Most importantly, it took me away from adding other, more useful features to the core software.<BR><BR>Now instead of testing the software by installing it and generating the test data, you can simply visit <a class=\"invisiblelink\" target=\"_blank\" href=\"http://demo.domainmod.org\">http://demo.domainmod.org</a> to take " . $software_title . " for a test drive.', '2013-04-06 00:00:00', '2013-04-06 00:00:00'),
                ('Update the Segments UI to give the user a lot more information and flexibility', 'Now when filtering your domains using a segment, " . $software_title . " will tell you which domains in the segment are stored in " . $software_title . " (indicating whether or not the domain is active or inactive), as well as which domains don\'t match, and lastly it will tell you which domains matched but were filtered out based on your other search criteria. Each of the resulting lists can be easily viewed and exported for your convenience.<BR><BR>It took quite a bit of work to get this feature implemented, but the segment filtering just felt incomplete without it. It was still a very useful feature, but now it\'s incredibly powerful, and I hope to add on the functionality in the future.', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
                ('The Domain & SSL search pages have been updated to allow for the exporting of results', '', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
                ('A logo has now been added to " . $software_title . " in order to pretty things up a little bit', '', '2013-04-10 00:00:00', '2013-04-10 00:00:00'),
                ('Cron job added for sending an email to users about upcoming Domain and SSL Certificate renewals', 'A cron job has now been added to send a daily email to users letting them know about upcoming domain and SSL expirations, and users can subscribe and unsubscribe from this email through their Control Panel.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-15 00:00:00', '2013-04-15 00:00:00'),
                ('A new \'Web Hosting\' section has been added to the UI so that you can now keep track of your web hosting providers within " . $software_title . "', '', '2013-04-17 00:00:00', '2013-04-17 00:00:00'),
                ('A password field has now been added to Registrar & SSL Provider accounts so that passwords can be managed through " . $software_title . "', '', '2013-04-19 00:00:00', '2013-04-19 00:00:00'),
                ('Update the expiration email so that the System Adminstrator can set the number of days in the future to display in the email', 'Previously when the daily expiration emails were sent out to users they would automatically include the next 60 days of expirations, but this has now been converted to a system setting so that your system administrator can now specify the number of days to include in the email.', '2013-04-19 00:00:01', '2013-04-19 00:00:01'),
                ('Remove the (redundant) Domain Status and Status Notes fields', 'Although the Domain Status & Status Notes fields were removed because they were redundant, if you had data stored in either of these fields it would have been appended to the primary Notes field when your database was upgraded. So don\'t worry, dropping these two fields didn\'t cause you to lose any data.', '2013-04-20 00:00:00', '2013-04-20 00:00:00'),
                ('Added a \'view full notes\' feature to the Domain and SSL Cert edit pages', 'When editing a Domain or SSL certificate, if you want to view the notes but scrolling through the text box just isn\'t your thing, you can now click on a link to view the full notes on a separate page, making them much easier to read.', '2013-04-24 00:00:00', '2013-04-24 00:00:00'),
                ('Reporting section added', '" . $software_title . " now includes a handful of reports that can give you valuable insight into your data, and I\'m always on the lookout for more reports that can be added. If you have any new report ideas, or any suggestions for the current reports, feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-04-25 00:00:00', '2013-04-25 00:00:00'),
                ('Cron job added for automating currency conversions at regular intervals', 'Never worry about having outdated exchange rates again! " . $software_title . " now includes a cron job that automates currency conversions. This means you can have the cron job set to run overnight, and when you go to use " . $software_title . " in the morning your currency conversions will already be completely up-to-date.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-27 00:00:00', '2013-04-27 00:00:00'),
                ('" . $software_title . " has been converted to UTF-8', 'The entire " . $software_title . " system has been converted to use the UTF-8 character set in order to allow for support of non-ASCII characters, such as the characters found in some IDNs (Internationalized Domain Names).', '2013-04-27 00:00:01', '2013-04-27 00:00:01'),
                ('Currencies have been updated to be user-based instead of system-based', 'Now that Currencies have been re-worked to be user-based, every user in the system can set their own default currency, and this currency will be used for them throughout the system. Every setting, webpage, and report in the " . $software_title . " system will automatically be converted to display monetary values using the user\'s default currency.', '2013-04-29 00:00:00', '2013-04-29 00:00:00'),
                ('Overhaul of " . $software_title . " Settings Complete!', 'Over the past few months the " . $software_title . " settings have been undergoing a complete overhaul. The changes include but are not limited to making currency conversions user-based instead of system-based, updating all Domain & SSL default settings to be user-based instead of system-based, separating out Category, IP Address and Owner settings so that Domains & SSLs have thier own options instead of sharing them, adding support for saving passwords for Domain Registrar & SSL Provider accounts, removing the redundant Status and Status Notes fields from the Domains section, and so on.<BR><BR>I\'m constantly trying to improve the software and make it more user-friendly, so if you have any suggestions or feedback feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-05-02 00:00:00', '2013-05-02 00:00:00'),
                ('" . $software_title . " now contains a Software Updates section!', 'After upgrading " . $software_title . " I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-05-04 00:00:00', '2013-05-04 00:00:00'),
                ('An Export option has been added to all Asset pages', '', '2013-05-06 00:00:00', '2013-05-06 00:00:00'),
                ('You can now create Custom Domain & SSL Fields!', 'In an effort to allow users more flexibility, as well as track as much data as possible, I\'ve implemented Custom Domain & SSL Fields. Now if there\'s information you want to track for a domain or SSL certificate but the field doesn\'t exist in " . $software_title . ", you can just add it yourself!<BR><BR>For example, if you wanted to keep track of which domains are currenty setup in Google Analytics, you could create a new Google Analytics check box field and start tracking this information for each of your domains. Or if you were working in a corporate environment and wanted to keep a record of who purchased each of your SSL certificates, you could create a Purchaser Name text field and keep track of this information for every one of your SSL certificates. Combine custom fields with the ability to update them with the Bulk Updater, and the sky\'s the limit in regards to what data you can easily track! (the Bulk Updater currently only supports domains, not SSL certificates)<BR><BR>And when you export your domain & SSL data, the information contained in your custom fields will automatically be included in the exported data.', '2013-05-25 17:00:00', '2013-05-25 17:00:00'),
                ('" . $software_title . " now includes a Data Warehouse for importing data', '" . $software_title . " now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout " . $software_title . " where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><font class=\"default_highlight\">NOTE:</font> Importing your server into the data warehouse will not modify any of your " . $software_title . " data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
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
                               ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->time() . "')";
                $result_insert = mysqli_query($connection, $sql_insert);

            }

        }

        $_SESSION['are_there_updates'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.005',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        */

        $current_db_version = 2.005;

    }

    // upgrade database from 2.005 to 2.0051
    if ($current_db_version == 2.005) {

        $sql = "DROP TABLE IF EXISTS `updates`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "DROP TABLE IF EXISTS `update_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
                SET db_version = '2.0051',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0051;

    }

    // upgrade database from 2.0051 to 2.0052
    if ($current_db_version == 2.0051) {

        $sql = "ALTER TABLE `fees`
                ADD `privacy_fee` FLOAT NOT NULL AFTER `transfer_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0052',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0052;

    }

    // upgrade database from 2.0052 to 2.0053
    if ($current_db_version == 2.0052) {

        $sql = "ALTER TABLE `fees`
                ADD `misc_fee` FLOAT NOT NULL AFTER `privacy_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_fees`
                ADD `misc_fee` FLOAT NOT NULL AFTER `renewal_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
                SET db_version = '2.0053',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0053;

    }

    // upgrade database from 2.0053 to 2.0054
    if ($current_db_version == 2.0053) {

        $sql = "ALTER TABLE `domains`
                ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT d.id, d.fee_id, f.renewal_fee
                FROM domains AS d, fees AS f
                WHERE d.fee_id = f.id
                ORDER BY domain ASC";

        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE domains
                           SET total_cost = '" . $row->renewal_fee . "'
                           WHERE id = '" . $row->id . "'
                             AND fee_id = '" . $row->fee_id . "'";
            $result_update = mysqli_query($connection, $sql_update);

        }

        $sql = "ALTER TABLE `ssl_certs`
                ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT s.id, s.fee_id, sf.renewal_fee
                FROM ssl_certs AS s, ssl_fees AS sf
                WHERE s.fee_id = sf.id";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE ssl_certs
                           SET total_cost = '" . $row->renewal_fee . "'
                           WHERE id = '" . $row->id . "'
                             AND fee_id = '" . $row->fee_id . "'";
            $result_update = mysqli_query($connection, $sql_update);

        }

        $sql = "UPDATE settings
                SET db_version = '2.0054',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0054;

    }

    // upgrade database from 2.0054 to 2.0055
    if ($current_db_version == 2.0054) {

        $sql = "ALTER TABLE `user_settings`
                    ADD `display_inactive_assets` INT(1) NOT NULL DEFAULT '1' AFTER `display_ssl_fee`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['display_inactive_assets'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.0055',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0055;

    }

    // upgrade database from 2.0055 to 2.0056
    if ($current_db_version == 2.0055) {

        $sql = "ALTER TABLE `user_settings`
                    ADD `display_dw_intro_page` INT(1) NOT NULL DEFAULT '1' AFTER `display_inactive_assets`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['display_dw_intro_page'] = "1";

        $sql = "UPDATE settings
                SET db_version = '2.0056',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0056;

    }

    // upgrade database from 2.0056 to 2.0057
    if ($current_db_version == 2.0056) {

        $sql = "ALTER TABLE `settings`
                ADD `upgrade_available` INT(1) NOT NULL DEFAULT '0' AFTER `db_version`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['system_upgrade_available'] = "0";

        $sql = "UPDATE settings
                SET db_version = '2.0057',
                    update_time = '" . $time->time() . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0057;

    }

    $_SESSION['system_upgrade_available'] = "0";

    $sql = "UPDATE settings
            SET upgrade_available = '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['result_message'] .= "Your Database Has Been Updated<BR>";

} else {

    $_SESSION['result_message'] .= "Your Database is already up-to-date<BR>";

}
