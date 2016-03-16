<?php
/**
 * /_includes/updates/1.1-2.0021.inc.php
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

// upgrade database from 1.1 to 1.2
if ($current_db_version === '1.1') {

    $sql = "ALTER TABLE `ssl_certs`
            ADD `ip` VARCHAR(50) NOT NULL AFTER `name`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.2',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.2';

}

// upgrade database from 1.2 to 1.3
if ($current_db_version === '1.2') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.3';

}

// upgrade database from 1.3 to 1.4
if ($current_db_version === '1.3') {

    $sql = "ALTER TABLE `ip_addresses`
            ADD `notes` LONGTEXT NOT NULL AFTER `ip`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.4',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.4';

}

// upgrade database from 1.4 to 1.5
if ($current_db_version === '1.4') {

    $sql = "ALTER TABLE `domains`
            ADD `ip_id` INT(10) NOT NULL DEFAULT '0' AFTER `dns_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.5',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.5';

}

// upgrade database from 1.5 to 1.6
if ($current_db_version === '1.5') {

    $sql = "ALTER TABLE `domains`
            CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `domains`
            SET ip_id = '1',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "TRUNCATE `ip_addresses`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `ip_addresses`
            (`id`, `name`, `ip`, `insert_time`) VALUES
            ('1', '[no ip address]', '-', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.6',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.6';

}

// upgrade database from 1.6 to 1.7
if ($current_db_version === '1.6') {

    $sql = "ALTER TABLE `ssl_certs`
            DROP `ip`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.7',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.7';

}

// upgrade database from 1.7 to 1.8
if ($current_db_version === '1.7') {

    $sql = "ALTER TABLE `ip_addresses`
            ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.8',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.8';

}

// upgrade database from 1.8 to 1.9
if ($current_db_version === '1.8') {

    $sql = "ALTER TABLE `settings`
            ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.9',
                email_address = 'greg@chetcuti.com',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.9';

}

// upgrade database from 1.9 to 1.91
if ($current_db_version === '1.9') {

    $sql = "ALTER TABLE `ip_addresses`
            ADD `rdns` VARCHAR(255) NOT NULL DEFAULT '-' AFTER `ip`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.91',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.91';

}

// upgrade database from 1.91 to 1.92
if ($current_db_version === '1.91') {

    $sql = "ALTER TABLE `settings`
            ADD `type` VARCHAR(50) NOT NULL AFTER `id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET type =  'system',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.92',
                update_time = '" . $time->stamp() . "'
            WHERE type = 'system'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.92';

}

// upgrade database from 1.92 to 1.93
if ($current_db_version === '1.92') {

    $sql = "ALTER TABLE `settings`
            DROP `type`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.93',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.93';

}

// upgrade database from 1.93 to 1.94
if ($current_db_version === '1.93') {

    $sql = "ALTER TABLE `settings`
            ADD `number_of_domains` INT(5) NOT NULL DEFAULT '50' AFTER `email_address`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            ADD `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_domains`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.94',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.94';

}

// upgrade database from 1.94 to 1.95
if ($current_db_version === '1.94') {

    $sql = "ALTER TABLE `currencies`
            DROP `default_currency`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            ADD `default_currency` VARCHAR(5) NOT NULL DEFAULT 'USD' AFTER `email_address`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.95',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.95';

}

// upgrade database from 1.95 to 1.96
if ($current_db_version === '1.95') {

    $sql = "ALTER TABLE `currencies`
            DROP `test_data`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.96',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.96';

}

// upgrade database from 1.96 to 1.97
if ($current_db_version === '1.96') {

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
                (id, `name`, notes, active, test_data, insert_time, update_time)
                SELECT id, `name`, notes, active, test_data, insert_time, update_time
                FROM companies ORDER BY id;";
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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.97';

}

// upgrade database from 1.97 to 1.98
if ($current_db_version === '1.97') {

    $sql = "INSERT INTO `categories`
            (`name`, `owner`, `insert_time`) VALUES
            ('[no category]', '[no stakeholder]', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM categories
            WHERE default_category = '1';";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) == 0) {
        $sql_update = "UPDATE categories
                       SET default_category = '1',
                              update_time = '" . $time->stamp() . "'
                       WHERE name = '[no category]'";
        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
    }

    $sql = "ALTER TABLE `dns`
            ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `dns`
            (`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES
            ('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM dns
            WHERE default_dns = '1';";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) == 0) {
        $sql_update = "UPDATE dns
                       SET default_dns = '1',
                              update_time = '" . $time->stamp() . "'
                       WHERE name = '[no dns]'";
        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
    }

    $sql = "ALTER TABLE `owners`
            ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `owners`
            (`name`, `insert_time`) VALUES
            ('[no owner]', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM owners
            WHERE default_owner = '1';";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) == 0) {
        $sql_update = "UPDATE owners
                       SET default_owner = '1',
                              update_time = '" . $time->stamp() . "'
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
                              update_time = '" . $time->stamp() . "'
                       WHERE name = '[no ip address]'";
        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
    }

    $sql = "UPDATE settings
            SET db_version = '1.98',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.98';

}

// upgrade database from 1.98 to 1.99
if ($current_db_version === '1.98') {

    $sql = "ALTER TABLE `categories`
            CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `categories`
            SET `stakeholder` = '[no stakeholder]',
                `update_time` = '" . $time->stamp() . "'
            WHERE `stakeholder` = '[no category owner]';";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '1.99',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '1.99';

}

// upgrade database from 1.99 to 2.0001
if ($current_db_version === '1.99') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE currencies
            SET default_currency = '1',
                update_time = '" . $time->stamp() . "'
            WHERE currency = '" . $default_currency . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            DROP `default_currency`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0001';

}

// upgrade database from 2.0001 to 2.0002
if ($current_db_version === '2.0001') {

    $sql = "ALTER TABLE `ssl_cert_functions`
            ADD `default_function` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `ssl_cert_types`
            ADD `default_type` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE ssl_cert_functions
            SET default_function = '1',
                update_time = '" . $time->stamp() . "'
            WHERE function = 'Web Server SSL/TLS Certificate'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE ssl_cert_types
            SET default_type = '1',
                update_time = '" . $time->stamp() . "'
            WHERE type = 'Wildcard'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0002',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0002';

}

// upgrade database from 2.0002 to 2.0003
if ($current_db_version === '2.0002') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0003';

}

// upgrade database from 2.0003 to 2.0004
if ($current_db_version === '2.0003') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0004';

}

// upgrade database from 2.0004 to 2.0005
if ($current_db_version === '2.0004') {

    $sql = "ALTER TABLE `ssl_cert_types`
            ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0005',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0005';

}

// upgrade database from 2.0005 to 2.0006
if ($current_db_version === '2.0005') {

    $sql = "ALTER TABLE `ip_addresses`
            ADD `active` INT(1) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `domains`
            CHANGE `active` `active` INT(2) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0006',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0006';

}

// upgrade database from 2.0006 to 2.0007
if ($current_db_version === '2.0006') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0007';

}

// upgrade database from 2.0007 to 2.0008
if ($current_db_version === '2.0007') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0008';

}

// upgrade database from 2.0008 to 2.0009
if ($current_db_version === '2.0008') {

    $sql = "ALTER TABLE `currencies`
            ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0009',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0009';

}

// upgrade database from 2.0009 to 2.0010
if ($current_db_version === '2.0009') {

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
                     ('" . $row->id . "', '" . $time->stamp() . "');";
        $result_temp = mysqli_query($connection, $sql_temp) or $error->outputOldSqlError($connection);
    }

    $sql = "UPDATE settings
            SET db_version = '2.001',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.001';

}

// upgrade database from 2.0010 to 2.0011
if ($current_db_version === '2.001') {

    $sql = "ALTER TABLE `settings`
            DROP `number_of_domains`,
            DROP `number_of_ssl_certs`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0011',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0011';

}

// upgrade database from 2.0011 to 2.0012
if ($current_db_version === '2.0011') {

    $sql = "ALTER TABLE `user_settings`
            CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0012',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0012';

}

// upgrade database from 2.0012 to 2.0013
if ($current_db_version === '2.0012') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0013';

}

// upgrade database from 2.0013 to 2.0014
if ($current_db_version === '2.0013') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0014';

}

// upgrade database from 2.0014 to 2.0015
if ($current_db_version === '2.0014') {

    $sql = "ALTER TABLE `user_settings`
            ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `user_settings`
            ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0015',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0015';

}

// upgrade database from 2.0015 to 2.0016
if ($current_db_version === '2.0015') {

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
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0016';

}

// upgrade database from 2.0016 to 2.0017
if ($current_db_version === '2.0016') {

    $sql = "ALTER TABLE `segment_data`
            ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0017',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0017';

}

// upgrade database from 2.0017 to 2.0018
if ($current_db_version === '2.0017') {

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0018',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0018';

}

// upgrade database from 2.0018 to 2.0019
if ($current_db_version === '2.0018') {

    $sql = "ALTER TABLE `ssl_certs`
            CHANGE `domain_id` `domain_id` INT(10) NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0019',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0019';

}

// upgrade database from 2.0019 to 2.0020
if ($current_db_version === '2.0019') {

    $sql = "ALTER TABLE `user_settings`
            ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0020',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0020';

}

// upgrade database from 2.0020 to 2.0021
if ($current_db_version === '2.002') {

    $sql = "ALTER TABLE `settings`
            ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

    $sql = "UPDATE settings
            SET full_url = '" . $full_url . "'";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
            SET db_version = '2.0021',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0021';

}

// upgrade database from 2.0021 to 2.0022
if ($current_db_version === '2.0021') {

    $sql = "ALTER TABLE `settings`
            ADD `timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `email_address`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '2.0022',
                update_time = '" . $time->stamp() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0022';

}

//@formatter:on
