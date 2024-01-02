<?php
/**
 * /_includes/updates/1.1-2.0022.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

    $pdo->query("
        ALTER TABLE `ssl_certs`
        ADD `ip` VARCHAR(50) NOT NULL AFTER `name`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.2',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.2';

}

// upgrade database from 1.2 to 1.3
if ($current_db_version === '1.2') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ip_addresses` (
        `id` INT(10) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `ip` VARCHAR(255) NOT NULL,
        `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
        `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
        PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.3',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.3';

}

// upgrade database from 1.3 to 1.4
if ($current_db_version === '1.3') {

    $pdo->query("
        ALTER TABLE `ip_addresses`
        ADD `notes` LONGTEXT NOT NULL AFTER `ip`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.4',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.4';

}

// upgrade database from 1.4 to 1.5
if ($current_db_version === '1.4') {

    $pdo->query("
        ALTER TABLE `domains`
        ADD `ip_id` INT(10) NOT NULL DEFAULT '0' AFTER `dns_id`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.5',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.5';

}

// upgrade database from 1.5 to 1.6
if ($current_db_version === '1.5') {

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'");

    $pdo->query("
        UPDATE `domains`
        SET ip_id = '1',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        TRUNCATE `ip_addresses`");

    $pdo->query("
        INSERT INTO `ip_addresses`
        (`id`, `name`, `ip`, `insert_time`)
        VALUES
        ('1', '[no ip address]', '-', '" . $timestamp . "')");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.6',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.6';

}

// upgrade database from 1.6 to 1.7
if ($current_db_version === '1.6') {

    $pdo->query("
        ALTER TABLE `ssl_certs`
        DROP `ip`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.7',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.7';

}

// upgrade database from 1.7 to 1.8
if ($current_db_version === '1.7') {

    $pdo->query("
        ALTER TABLE `ip_addresses`
        ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.8',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.8';

}

// upgrade database from 1.8 to 1.9
if ($current_db_version === '1.8') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.9',
            email_address = 'greg@chetcuti.com',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.9';

}

// upgrade database from 1.9 to 1.91
if ($current_db_version === '1.9') {

    $pdo->query("
        ALTER TABLE `ip_addresses`
         ADD `rdns` VARCHAR(255) NOT NULL DEFAULT '-' AFTER `ip`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.91',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.91';

}

// upgrade database from 1.91 to 1.92
if ($current_db_version === '1.91') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `type` VARCHAR(50) NOT NULL AFTER `id`");

    $pdo->query("
        UPDATE settings
        SET type =  'system',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.92',
            update_time = '" . $timestamp . "'
        WHERE type = 'system'");

    $current_db_version = '1.92';

}

// upgrade database from 1.92 to 1.93
if ($current_db_version === '1.92') {

    $pdo->query("
        ALTER TABLE `settings`
        DROP `type`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.93',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.93';

}

// upgrade database from 1.93 to 1.94
if ($current_db_version === '1.93') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `number_of_domains` INT(5) NOT NULL DEFAULT '50' AFTER `email_address`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_domains`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.94',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.94';

}

// upgrade database from 1.94 to 1.95
if ($current_db_version === '1.94') {

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `default_currency`");

    $pdo->query("
        ALTER TABLE `settings`
        ADD `default_currency` VARCHAR(5) NOT NULL DEFAULT 'USD' AFTER `email_address`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.95',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.95';

}

// upgrade database from 1.95 to 1.96
if ($current_db_version === '1.95') {

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `test_data`");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.96',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.96';

}

// upgrade database from 1.96 to 1.97
if ($current_db_version === '1.96') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `owners` (
            `id` INT(5) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `notes` LONGTEXT NOT NULL,
            `active` INT(1) NOT NULL DEFAULT '1',
            `test_data` INT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`),
            KEY `name` (`name`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO owners
        (id, `name`, notes, active, test_data, insert_time, update_time)
        SELECT id, `name`, notes, active, test_data, insert_time, update_time
        FROM companies ORDER BY id");

    $pdo->query("
        DROP TABLE `companies`");

    $pdo->query("
        ALTER TABLE `domains` CHANGE `company_id` `owner_id` INT(5) NOT NULL");

    $pdo->query("
        ALTER TABLE `registrar_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_certs` CHANGE `company_id` `owner_id` INT(5) NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.97',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.97';

}

// upgrade database from 1.97 to 1.98
if ($current_db_version === '1.97') {

    $pdo->query("
        INSERT INTO `categories`
        (`name`, `owner`, `insert_time`)
        VALUES
        ('[no category]', '[no stakeholder]', '" . $timestamp . "')");

    $result = $pdo->query("
        SELECT id
        FROM categories
        WHERE default_category = '1'")->fetchColumn();

    if (!$result) {

        $pdo->query("
            UPDATE categories
            SET default_category = '1',
                update_time = '" . $timestamp . "'
            WHERE name = '[no category]'");

    }

    $pdo->query("
        ALTER TABLE `dns`
        ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`");

    $pdo->query("
        INSERT INTO `dns`
        (`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`)
        VALUES
        ('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $timestamp . "')");

    $result = $pdo->query("
        SELECT id
        FROM dns
        WHERE default_dns = '1'")->fetchColumn();

    if (!$result) {

        $pdo->query("
            UPDATE dns
            SET default_dns = '1',
                update_time = '" . $timestamp . "'
            WHERE name = '[no dns]'");

    }

    $pdo->query("
        ALTER TABLE `owners`
        ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        INSERT INTO `owners`
        (`name`, `insert_time`)
        VALUES
        ('[no owner]', '" . $timestamp . "')");

    $result = $pdo->query("
        SELECT id
        FROM owners
        WHERE default_owner = '1'")->fetchColumn();

    if (!$result) {

        $pdo->query("
            UPDATE owners
            SET default_owner = '1',
                update_time = '" . $timestamp . "'
            WHERE name = '[no owner]'")->fetchAll();

    }

    $pdo->query("
        ALTER TABLE `ip_addresses`
        ADD `default_ip_address` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $result = $pdo->query("
        SELECT id
        FROM ip_addresses
        WHERE default_ip_address = '1'")->fetchColumn();

    if (!$result) {

        $pdo->query("
            UPDATE ip_addresses
            SET default_ip_address = '1',
                update_time = '" . $timestamp . "'
            WHERE name = '[no ip address]'");

    }

    $pdo->query("
        UPDATE settings
        SET db_version = '1.98',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.98';

}

// upgrade database from 1.98 to 1.99
if ($current_db_version === '1.98') {

    $pdo->query("
        ALTER TABLE `categories`
        CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL");

    $pdo->query("
        UPDATE `categories`
        SET `stakeholder` = '[no stakeholder]',
            `update_time` = '" . $timestamp . "'
        WHERE `stakeholder` = '[no category owner]'");

    $pdo->query("
        UPDATE settings
        SET db_version = '1.99',
            update_time = '" . $timestamp . "'");

    $current_db_version = '1.99';

}

// upgrade database from 1.99 to 2.0001
if ($current_db_version === '1.99') {

    $pdo->query("
        ALTER TABLE `currencies`
        ADD `default_currency` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $default_currency = $pdo->query("
        SELECT default_currency
        FROM settings")->fetchColumn();

    $pdo->query("
        UPDATE currencies
        SET default_currency = '0',
            update_time = '" . $timestamp . "'");

    $pdo->query("
        UPDATE currencies
        SET default_currency = '1',
            update_time = '" . $timestamp . "'
        WHERE currency = '" . $default_currency . "'");

    $pdo->query("
        ALTER TABLE `settings`
        DROP `default_currency`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0001',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0001';

}

// upgrade database from 2.0001 to 2.0002
if ($current_db_version === '2.0001') {

    $pdo->query("
        ALTER TABLE `ssl_cert_functions`
        ADD `default_function` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        ADD `default_type` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        UPDATE ssl_cert_functions
        SET default_function = '1',
            update_time = '" . $timestamp . "'
        WHERE function = 'Web Server SSL/TLS Certificate'");

    $pdo->query("
        UPDATE ssl_cert_types
        SET default_type = '1',
            update_time = '" . $timestamp . "'
        WHERE type = 'Wildcard'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0002',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0002';

}

// upgrade database from 2.0002 to 2.0003
if ($current_db_version === '2.0002') {

    $pdo->query("
        DROP TABLE `ssl_cert_types`");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        DROP `type_id`");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        DROP `type_id`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0003',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0003';

}

// upgrade database from 2.0003 to 2.0004
if ($current_db_version === '2.0003') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_types` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(255) NOT NULL,
            `notes` LONGTEXT NOT NULL,
            `default_type` INT(1) NOT NULL DEFAULT '0',
            `active` INT(1) NOT NULL DEFAULT '1',
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO ssl_cert_types
        (id, type, notes, default_type, active, insert_time, update_time)
        SELECT id, function, notes, default_function, active, insert_time, update_time FROM ssl_cert_functions ORDER BY id");

    $pdo->query("
        DROP TABLE `ssl_cert_functions`");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `function_id` `type_id` INT(10) NOT NULL");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        CHANGE `function_id` `type_id` INT(5) NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0004',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0004';

}

// upgrade database from 2.0004 to 2.0005
if ($current_db_version === '2.0004') {

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0005',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0005';

}

// upgrade database from 2.0005 to 2.0006
if ($current_db_version === '2.0005') {

    $pdo->query("
        ALTER TABLE `ip_addresses`
        ADD `active` INT(1) NOT NULL DEFAULT '1' AFTER `default_ip_address`");

    $pdo->query("
        ALTER TABLE `domains`
        CHANGE `active` `active` INT(2) NOT NULL DEFAULT '1'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0006',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0006';

}

// upgrade database from 2.0006 to 2.0007
if ($current_db_version === '2.0006') {

    $pdo->query("
        ALTER TABLE `registrars`
        ADD `default_registrar` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
            ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        ADD `default_provider` INT(1) NOT NULL DEFAULT '0' AFTER `notes`");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0007',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0007';

}

// upgrade database from 2.0007 to 2.0008
if ($current_db_version === '2.0007') {

    $pdo->query("
        ALTER TABLE `owners`
        CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `registrars`
        CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0008',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0008';

}

// upgrade database from 2.0008 to 2.0009
if ($current_db_version === '2.0008') {

    $pdo->query("
        ALTER TABLE `currencies`
        ADD `test_data` INT(1) NOT NULL DEFAULT '0' AFTER `active`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0009',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0009';

}

// upgrade database from 2.0009 to 2.0010
if ($current_db_version === '2.0009') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `user_settings` (
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
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $result = $pdo->query("
        SELECT id
        FROM users")->fetchAll();

    $stmt = $pdo->prepare("
        INSERT INTO user_settings
        (user_id, insert_time)
        VALUES
        (:bind_user_id, :timestamp)");
    $stmt->bindParam('bind_user_id', $bind_user_id, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);

    foreach ($result as $row) {

        $bind_user_id = $row->id;
        $stmt->execute();

    }

    $pdo->query("
        UPDATE settings
            SET db_version = '2.001',
                update_time = '" . $timestamp . "'");

    $current_db_version = '2.001';

}

// upgrade database from 2.0010 to 2.0011
if ($current_db_version === '2.001') {

    $pdo->query("
        ALTER TABLE `settings`
        DROP `number_of_domains`,
        DROP `number_of_ssl_certs`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0011',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0011';

}

// upgrade database from 2.0011 to 2.0012
if ($current_db_version === '2.0011') {

    $pdo->query("
        ALTER TABLE `user_settings`
        CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0012',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0012';

}

// upgrade database from 2.0012 to 2.0013
if ($current_db_version === '2.0012') {

    $pdo->query("
        ALTER TABLE `categories`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `currencies`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `dns`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `domains`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `fees`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ip_addresses`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `owners`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `registrars`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `registrar_accounts`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `segments`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ssl_accounts`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ssl_certs`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ssl_cert_types`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        DROP `test_data`");

    $pdo->query("
        ALTER TABLE `ssl_providers`
        DROP `test_data`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0013',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0013';

}

// upgrade database from 2.0013 to 2.0014
if ($current_db_version === '2.0013') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `segment_data` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `segment_id` INT(10) NOT NULL,
            `domain` VARCHAR(255) NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0014',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0014';

}

// upgrade database from 2.0014 to 2.0015
if ($current_db_version === '2.0014') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`");

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0015',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0015';

}

// upgrade database from 2.0015 to 2.0016
if ($current_db_version === '2.0015') {

    $pdo->query("
        ALTER TABLE `segment_data`
        ADD `active` INT(1) NOT NULL DEFAULT '0' AFTER `domain`");

    $pdo->query("
        ALTER TABLE `segment_data`
        ADD `inactive` INT(1) NOT NULL DEFAULT '0' AFTER `active`");

    $pdo->query("
        ALTER TABLE `segment_data`
        ADD `missing` INT(1) NOT NULL DEFAULT '0' AFTER `inactive`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0016',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0016';

}

// upgrade database from 2.0016 to 2.0017
if ($current_db_version === '2.0016') {

    $pdo->query("
        ALTER TABLE `segment_data`
        ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0017',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0017';

}

// upgrade database from 2.0017 to 2.0018
if ($current_db_version === '2.0017') {

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0018',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0018';

}

// upgrade database from 2.0018 to 2.0019
if ($current_db_version === '2.0018') {

    $pdo->query("
        ALTER TABLE `ssl_certs`
        CHANGE `domain_id` `domain_id` INT(10) NOT NULL");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0019',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0019';

}

// upgrade database from 2.0019 to 2.0020
if ($current_db_version === '2.0019') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0020',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0020';

}

// upgrade database from 2.0020 to 2.0021
if ($current_db_version === '2.002') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`");

    $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

    $stmt = $pdo->prepare("
        UPDATE settings
        SET full_url = :full_url");
    $stmt->bindValue('full_url', $full_url, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0021',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0021';

}

// upgrade database from 2.0021 to 2.0022
if ($current_db_version === '2.0021') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `email_address`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0022',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0022';

}
//@formatter:on
