<?php
/**
 * /_includes/updates/2.0048-3.0.1.inc.php
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

// upgrade database from 2.0048 to 2.0049
if ($current_db_version === '2.0048') {

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `dw_servers` (
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
            `build_start_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `build_end_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `build_time` INT(10) NOT NULL DEFAULT '0',
            `has_ever_been_built` INT(1) NOT NULL DEFAULT '0',
            `build_status_overall` INT(1) NOT NULL DEFAULT '0',
            `build_start_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `build_end_time_overall` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `build_time_overall` INT(10) NOT NULL DEFAULT '0',
            `has_ever_been_built_overall` INT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            `update_time` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:01',
            PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    // This section was made redundant by DB update v2.005
    // (redundant code was here)

    $current_db_version = '2.0049';

}

// upgrade database from 2.0049 to 2.005
if ($current_db_version === '2.0049') {

    // This section was made redundant by DB update v2.0051
    // (redundant code was here)

    $current_db_version = '2.005';

}

// upgrade database from 2.005 to 2.0051
if ($current_db_version === '2.005') {

    $pdo->query("
        DROP TABLE IF EXISTS `updates`");

    $pdo->query("
        DROP TABLE IF EXISTS `update_data`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0051',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0051';

}

// upgrade database from 2.0051 to 2.0052
if ($current_db_version === '2.0051') {

    $pdo->query("
        ALTER TABLE `fees`
        ADD `privacy_fee` FLOAT NOT NULL AFTER `transfer_fee`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0052',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0052';

}

// upgrade database from 2.0052 to 2.0053
if ($current_db_version === '2.0052') {

    $pdo->query("
        ALTER TABLE `fees`
        ADD `misc_fee` FLOAT NOT NULL AFTER `privacy_fee`");

    $pdo->query("
        ALTER TABLE `ssl_fees`
        ADD `misc_fee` FLOAT NOT NULL AFTER `renewal_fee`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0053',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0053';

}

// upgrade database from 2.0053 to 2.0054
if ($current_db_version === '2.0053') {

    $pdo->query("
        ALTER TABLE `domains`
        ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`");

    $result = $pdo->query("
        SELECT d.id, d.fee_id, f.renewal_fee
        FROM domains AS d, fees AS f
        WHERE d.fee_id = f.id
        ORDER BY domain ASC")->fetchAll();

    $stmt = $pdo->prepare("
        UPDATE domains
        SET total_cost = :renewal_fee
        WHERE id = :domain_id
          AND fee_id = :fee_id");
    $stmt->bindParam('renewal_fee', $bind_renewal_fee, PDO::PARAM_STR);
    $stmt->bindParam('domain_id', $bind_domain_id, PDO::PARAM_INT);
    $stmt->bindParam('fee_id', $bind_fee_id, PDO::PARAM_INT);

    foreach ($result as $row) {

        $bind_renewal_fee = strval($row->renewal_fee);
        $bind_domain_id = $row->id;
        $bind_fee_id = $row->fee_id;
        $stmt->execute();

    }

    $pdo->query("
        ALTER TABLE `ssl_certs`
        ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`");

    $result = $pdo->query("
        SELECT s.id, s.fee_id, sf.renewal_fee
        FROM ssl_certs AS s, ssl_fees AS sf
        WHERE s.fee_id = sf.id")->fetchAll();

    $stmt = $pdo->prepare("
        UPDATE ssl_certs
        SET total_cost = :total_cost
        WHERE id = :ssl_id
          AND fee_id = :fee_id");
    $stmt->bindParam('total_cost', $bind_renewal_fee, PDO::PARAM_STR);
    $stmt->bindParam('ssl_id', $bind_ssl_id, PDO::PARAM_INT);
    $stmt->bindParam('fee_id', $bind_fee_id, PDO::PARAM_INT);

    foreach ($result as $row) {

        $bind_renewal_fee = strval($row->renewal_fee);
        $bind_ssl_id = $row->id;
        $bind_fee_id = $row->fee_id;
        $stmt->execute();

    }

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0054',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0054';

}

// upgrade database from 2.0054 to 2.0055
if ($current_db_version === '2.0054') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_inactive_assets` INT(1) NOT NULL DEFAULT '1' AFTER `display_ssl_fee`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0055',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0055';

}

// upgrade database from 2.0055 to 2.0056
if ($current_db_version === '2.0055') {

    $pdo->query("
        ALTER TABLE `user_settings`
        ADD `display_dw_intro_page` INT(1) NOT NULL DEFAULT '1' AFTER `display_inactive_assets`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0056',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0056';

}

// upgrade database from 2.0056 to 2.0057
if ($current_db_version === '2.0056') {

    $pdo->query("
        ALTER TABLE `settings`
        ADD `upgrade_available` INT(1) NOT NULL DEFAULT '0' AFTER `db_version`");

    $pdo->query("
        UPDATE settings
        SET db_version = '2.0057',
            update_time = '" . $timestamp . "'");

    $current_db_version = '2.0057';

}

// upgrade database from 2.0057 to 3.0.1
if ($current_db_version === '2.0057') {

    $pdo->query("
        UPDATE settings
        SET db_version = '3.0.1',
            update_time = '" . $timestamp . "'");

    $current_db_version = '3.0.1';

}
//@formatter:on
