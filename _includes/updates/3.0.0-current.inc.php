<?php
/**
 * /_includes/updates/3.0.0-current.inc.php
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
// upgrade database from 3.0.1 to 3.0.2
if ($current_db_version === '3.0.1') {

    $sql = "ALTER TABLE `settings`
            ADD `temp_version` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `full_url`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE `settings`
            SET `temp_version` = `db_version`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            DROP `db_version`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "ALTER TABLE `settings`
            CHANGE `temp_version` `db_version` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '3.0.2',
                update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '3.0.2';

}

// upgrade database from 3.0.2 to 3.0.4
if ($current_db_version === '3.0.2') {

    $sql = "CREATE TABLE IF NOT EXISTS `scheduler` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `slug` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `description` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `interval` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Daily',
                `expression` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0 7 * * * *',
                `last_run` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `last_duration` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `next_run` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `sort_order` INT(4) NOT NULL DEFAULT '1',
                `is_running` INT(1) NOT NULL DEFAULT '0',
                `active` INT(1) NOT NULL DEFAULT '1',
                `insert_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `update_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (`id`)
             ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO scheduler
            (`name`, description, slug, sort_order, is_running, active, insert_time)
             VALUES
            ('Send Expiration Email', 'Sends an email out to everyone who\'s subscribed, letting them know of upcoming Domain & SSL Certificate expirations." . "<" . "BR>" . "<" . "BR>Users can subscribe via " . "<" . "a href=\'../../settings/email.php\'>Email Settings" . "<" . "/a>." . "<" . "BR>" . "<" . "BR>Administrators can set the FROM email address and the number of days in the future to display in the email via " . "<" . "a href=\'../system-settings.php\'>System Settings" . "<" . "/a>.', 'expiration-email', '20', '0', '1', '" . $time->time() . "'),
            ('Update Conversion Rates', 'Retrieves the current currency conversion rates and updates the entire system, which keeps all of the financial information in DomainMOD accurate and up-to-date." . "<" . "BR>" . "<" . "BR>Users can set their default currency via " . "<" . "a href=\'../../settings/defaults.php\'>User Defaults" . "<" . "/a>." . "<" . "BR>" . "<" . "BR>Administrators can set the default system currency via " . "<" . "a href=\'../defaults.php\'>System Defaults" . "<" . "/a>.', 'update-conversion-rates', '40', '0', '1', '" . $time->time() . "'),
            ('System Cleanup', '" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running quickly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>TLDs:" . "<" . "/em> Makes sure that the TLD entries recorded in the database are accurate.', 'cleanup', '60', '0', '1', '" . $time->time() . "'),
            ('Check For New Version', 'Checks to see if there is a newer version of DomainMOD available to download." . "<" . "BR>" . "<" . "BR>You can view your current version on the " . "<" . "a href=\'../system-info.php\'>System Information" . "<" . "/a> page.', 'check-new-version', '80', '0', '1', '" . $time->time() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $cron = \Cron\CronExpression::factory('0 7 * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler
            SET next_run = '" . $next_run . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
            SET db_version = '3.0.4',
                update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '3.0.4';

}
