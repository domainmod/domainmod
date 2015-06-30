<?php
/**
 * /_includes/updates/2.0048-2.0057.inc.php
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
// upgrade database from 2.0048 to 2.0049
if ($current_db_version === '2.0048') {

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
            ('Domain Manager now includes a Data Warehouse for importing data', 'Domain Manager now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout Domain Manager where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><div class=\"default_highlight\">NOTE:</div> Importing your server into the data warehouse will not modify any of your Domain Manager data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
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

    $current_db_version = '2.0049';

}

// upgrade database from 2.0049 to 2.005
if ($current_db_version === '2.0049') {

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
            ('" . $software_title . " now includes a Data Warehouse for importing data', '" . $software_title . " now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout " . $software_title . " where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><div class=\"default_highlight\">NOTE:</div> Importing your server into the data warehouse will not modify any of your " . $software_title . " data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
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

    $current_db_version = '2.005';

}

// upgrade database from 2.005 to 2.0051
if ($current_db_version === '2.005') {

    $sql = "DROP TABLE IF EXISTS `updates`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "DROP TABLE IF EXISTS `update_data`;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0051',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0051';

}

// upgrade database from 2.0051 to 2.0052
if ($current_db_version === '2.0051') {

    $sql = "ALTER TABLE `fees`
                ADD `privacy_fee` FLOAT NOT NULL AFTER `transfer_fee`";
    $result = mysqli_query($connection, $sql);

    $sql = "UPDATE settings
                SET db_version = '2.0052',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0052';

}

// upgrade database from 2.0052 to 2.0053
if ($current_db_version === '2.0052') {

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

    $current_db_version = '2.0053';

}

// upgrade database from 2.0053 to 2.0054
if ($current_db_version === '2.0053') {

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

    $current_db_version = '2.0054';

}

// upgrade database from 2.0054 to 2.0055
if ($current_db_version === '2.0054') {

    $sql = "ALTER TABLE `user_settings`
                    ADD `display_inactive_assets` INT(1) NOT NULL DEFAULT '1' AFTER `display_ssl_fee`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['display_inactive_assets'] = "1";

    $sql = "UPDATE settings
                SET db_version = '2.0055',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0055';

}

// upgrade database from 2.0055 to 2.0056
if ($current_db_version === '2.0055') {

    $sql = "ALTER TABLE `user_settings`
                    ADD `display_dw_intro_page` INT(1) NOT NULL DEFAULT '1' AFTER `display_inactive_assets`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['display_dw_intro_page'] = "1";

    $sql = "UPDATE settings
                SET db_version = '2.0056',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0056';

}

// upgrade database from 2.0056 to 2.0057
if ($current_db_version === '2.0056') {

    $sql = "ALTER TABLE `settings`
                ADD `upgrade_available` INT(1) NOT NULL DEFAULT '0' AFTER `db_version`";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "UPDATE settings
                SET db_version = '2.0057',
                    update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '2.0057';

}

// upgrade database from 2.0057 to 3.0.1
if ($current_db_version === '2.0057') {

    $sql = "UPDATE settings
            SET db_version = '3.0.1',
                update_time = '" . $time->time() . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $current_db_version = '3.0.1';

}
