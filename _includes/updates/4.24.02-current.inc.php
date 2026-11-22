<?php
/**
 * /_includes/updates/4.24.02-current.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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

// upgrade database from 4.24.02 to 4.25.0
if ($current_db_version === '4.24.02') {

    $old_version = '4.24.02';
    $new_version = '4.25.0';

    try {

        $maint = new DomainMOD\Maintenance();

        /*
         * IDN support: store every domain and TLD in its normalised Unicode form, switch the domain and TLD columns to
         * utf8_bin, then recalculate fees and segments. ALTER TABLE commits straight away, so these steps can't share
         * one transaction; each one is safe to run again, so an upgrade that stops part-way finishes on the next run.
         */
        $maint->normalizeIdnData();
        $maint->changeIdnCollations();
        $maint->finishIdnUpgrade();

        // Domain Queue: the reason a domain failed or is waiting (RDAP imports), and when RDAP last tried it.
        $new_columns = array(
            array('domain_queue', 'message', "ADD `message` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `invalid_domain`"),
            array('domain_queue', 'last_attempt', "ADD `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `message`"),
            array('domain_queue_history', 'invalid_domain', "ADD `invalid_domain` TINYINT(1) NOT NULL DEFAULT '0' AFTER `already_in_queue`"),
            array('domain_queue_history', 'message', "ADD `message` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `invalid_domain`"),
            array('scheduler', 'timezone', "ADD `timezone` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'UTC' AFTER `expression`"));

        foreach ($new_columns as $new_column) {

            // ALTER TABLE commits straight away, so skip a column that an earlier attempt already added.
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM `INFORMATION_SCHEMA`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = :dbname
                  AND `TABLE_NAME` = :table_name
                  AND `COLUMN_NAME` = :column_name");
            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $new_column[0], PDO::PARAM_STR);
            $stmt->bindValue('column_name', $new_column[1], PDO::PARAM_STR);
            $stmt->execute();

            if ((int) $stmt->fetchColumn() === 0) {

                $pdo->query("
                    ALTER TABLE `" . $new_column[0] . "`
                    " . $new_column[2]);

            }

        }

        $pdo->beginTransaction();

        $new_api_registrars = array(
            array('OVH', '0', '0', '1', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', 'API Key: your OVH Application Key. API Secret: your Application Secret. Account ID: your Consumer Key. Create all three on the key page for your OVH region (Europe: https://eu.api.ovh.com/createToken/, Canada: https://ca.api.ovh.com/createToken/, US: https://api.us.ovhcloud.com/createToken/) with two read-only rights, GET /domain and GET /domain/*.'),
            array('RDAP', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1', '1', '0', '0', "Domain details are looked up using RDAP, the public replacement for WHOIS, or WHOIS itself for domains RDAP doesn't cover. Neither one includes a domain's WHOIS privacy or auto renewal status, so both will be set to No. You can update them afterwards using the Bulk Updater."),
            array('SiteHost', '0', '0', '1', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', 'Account ID: your SiteHost Account ID, shown on your SiteHost invoices. API Key: a key from the SiteHost Control Panel (API module) with access to Domains (SRS). SiteHost prices are in NZD.'));

        foreach ($new_api_registrars as $new_api_registrar) {

            // skip a row an earlier attempt already added.
            $stmt = $pdo->prepare("
                SELECT id
                FROM api_registrars
                WHERE `name` = :name");
            $stmt->bindValue('name', $new_api_registrar[0], PDO::PARAM_STR);
            $stmt->execute();
            $existing_id = $stmt->fetchColumn();
            $stmt->closeCursor();

            if (!$existing_id) {

                $stmt = $pdo->prepare("
                    INSERT INTO api_registrars
                    (`name`, req_account_username, req_account_password, req_account_id, req_reseller_id, req_api_app_name,
                     req_api_key, req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers,
                     ret_privacy_status, ret_autorenewal_status, notes, insert_time)
                    VALUES
                    (:name, :req_account_username, :req_account_password, :req_account_id, :req_reseller_id,
                     :req_api_app_name, :req_api_key, :req_api_secret, :req_ip_address, :lists_domains, :ret_expiry_date,
                     :ret_dns_servers, :ret_privacy_status, :ret_autorenewal_status, :notes, :insert_time)");
                $stmt->bindValue('name', $new_api_registrar[0], PDO::PARAM_STR);
                $stmt->bindValue('req_account_username', $new_api_registrar[1], PDO::PARAM_INT);
                $stmt->bindValue('req_account_password', $new_api_registrar[2], PDO::PARAM_INT);
                $stmt->bindValue('req_account_id', $new_api_registrar[3], PDO::PARAM_INT);
                $stmt->bindValue('req_reseller_id', $new_api_registrar[4], PDO::PARAM_INT);
                $stmt->bindValue('req_api_app_name', $new_api_registrar[5], PDO::PARAM_INT);
                $stmt->bindValue('req_api_key', $new_api_registrar[6], PDO::PARAM_INT);
                $stmt->bindValue('req_api_secret', $new_api_registrar[7], PDO::PARAM_INT);
                $stmt->bindValue('req_ip_address', $new_api_registrar[8], PDO::PARAM_INT);
                $stmt->bindValue('lists_domains', $new_api_registrar[9], PDO::PARAM_INT);
                $stmt->bindValue('ret_expiry_date', $new_api_registrar[10], PDO::PARAM_INT);
                $stmt->bindValue('ret_dns_servers', $new_api_registrar[11], PDO::PARAM_INT);
                $stmt->bindValue('ret_privacy_status', $new_api_registrar[12], PDO::PARAM_INT);
                $stmt->bindValue('ret_autorenewal_status', $new_api_registrar[13], PDO::PARAM_INT);
                $stmt->bindValue('notes', $new_api_registrar[14], PDO::PARAM_STR);
                $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
                $stmt->execute();

            }

        }

        // Domains that failed in the Domain Queue and were then fixed and imported by hand (queue/edit/).
        $stmt = $pdo->prepare("
            SELECT id
            FROM creation_types
            WHERE `name` = 'Queue (Edited Manually)'");
        $stmt->execute();
        $existing_id = $stmt->fetchColumn();
        $stmt->closeCursor();

        if (!$existing_id) {

            $stmt = $pdo->prepare("
                INSERT INTO creation_types
                (`name`, insert_time)
                VALUES
                ('Queue (Edited Manually)', :insert_time)");
            $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
            $stmt->execute();

        }

        /*
         * Scheduled tasks now follow a time zone, with its clock changes. Each daily task is converted to the time zone of
         * user 1 (normally whoever installed DomainMOD), or of the first administrator, keeping the moment it runs today.
         * Its next_run is left as it is, so the run that's already pending happens as planned. Tasks that run every few
         * minutes stay in UTC.
         */
        $schedule = new DomainMOD\Scheduler();

        $scheduler_timezone = 'UTC';

        $result = $pdo->query("
            SELECT us.default_timezone
            FROM user_settings AS us, users AS u
            WHERE us.user_id = u.id
              AND (u.id = '1' OR u.admin = '1')
            ORDER BY (u.id = '1') DESC, u.id ASC")->fetchAll();

        foreach ($result as $row) {

            if ($schedule->isValidZone($row->default_timezone)) {

                $scheduler_timezone = $row->default_timezone;
                break;

            }

        }

        $zone = $schedule->getZone($scheduler_timezone);

        $result = $pdo->query("
            SELECT id, expression
            FROM scheduler
            WHERE `timezone` = 'UTC'")->fetchAll();

        foreach ($result as $row) {

            $daily_time = $schedule->getDailyTime($row->expression);
            if ($daily_time === null) continue;

            $runs_today = new DateTimeImmutable(gmdate('Y-m-d') . sprintf(' %02d:%02d:00', $daily_time[0], $daily_time[1]), new DateTimeZone('UTC'));
            $local_time = $runs_today->setTimezone($zone);

            $stmt = $pdo->prepare("
                UPDATE scheduler
                SET expression = :expression,
                    `timezone` = :timezone
                WHERE id = :id");
            $stmt->bindValue('expression', (int) $local_time->format('i') . ' ' . (int) $local_time->format('G') . ' * * *', PDO::PARAM_STR);
            $stmt->bindValue('timezone', $zone->getName(), PDO::PARAM_STR);
            $stmt->bindValue('id', $row->id, PDO::PARAM_INT);
            $stmt->execute();

        }

        // GoDaddy accepts a Personal Access Token in the API Key field with the API Secret left blank.
        $stmt = $pdo->prepare("
            UPDATE api_registrars
            SET req_api_secret = '0',
                notes = :notes,
                update_time = :update_time
            WHERE `name` = 'GoDaddy'");
        $stmt->bindValue('notes', 'API Key: a Personal Access Token from developer.godaddy.com/personal-access-token with the domains.domain:read permission, and leave the API Secret blank. A classic API key and secret also still work until GoDaddy retires them. When retrieving your list of domains from GoDaddy, the current limit is 1,000 domains. If you have more than this you should export the full list of domains from GoDaddy and paste it into the <strong>Domains to add</strong> field when adding domains via the Domain Queue. GoDaddy limits API access to accounts it considers eligible (at times only accounts with at least 10 domains or a Discount Domain Club membership), and if it refuses the requests, domains added to the Domain Queue stay at Pending.', PDO::PARAM_STR);
        $stmt->bindValue('update_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.25.0 to 4.26.0
if ($current_db_version === '4.25.0') {

    $old_version = '4.25.0';
    $new_version = '4.26.0';

    try {

        /*
         * ALTER TABLE commits straight away, so the schema steps come first, outside the transaction, and each one is
         * safe to run again: an upgrade that stops part-way finishes on the next run.
         */

        // New users start on Auto (the colour mode that follows the system setting). Setting the same default again
        // is harmless.
        $pdo->query("
            ALTER TABLE `user_settings`
            ALTER `dark_mode` SET DEFAULT '2'");

        // The Dashboard's Getting Started checklist can be hidden by each user (shown until they hide it).
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'user_settings'
              AND `COLUMN_NAME` = 'display_getting_started'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {

            $pdo->query("
                ALTER TABLE `user_settings`
                ADD `display_getting_started` TINYINT(1) NOT NULL DEFAULT '1' AFTER `display_dw_intro_page`");

        }

        // Line up with fresh installs: scheduled tasks use five-field expressions, and older upgrades
        // left the column's default as a six-field one, which the cron library rejects.
        $pdo->query("
            ALTER TABLE `scheduler`
            ALTER `expression` SET DEFAULT '0 7 * * *'");

        // Line up with fresh installs: 4.20.07 moved every install to the Free Currency Rates API
        // (fcra), the only converter Admin Settings offers, but left the column's default as an older one.
        $pdo->query("
            ALTER TABLE `settings`
            ALTER `currency_converter` SET DEFAULT 'fcra'");

        // Line up with upgraded installs: currency codes are three letters (ISO 4217), as in every
        // default_currency column, but fresh installs before 4.26.0 made this column four characters long. It's only
        // narrowed when it's wider and every saved code fits.
        $stmt = $pdo->prepare("
            SELECT `CHARACTER_MAXIMUM_LENGTH`
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'currencies'
              AND `COLUMN_NAME` = 'currency'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $currency_length = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        $longest_currency = (int) $pdo->query("
            SELECT COALESCE(MAX(CHAR_LENGTH(`currency`)), 0)
            FROM `currencies`")->fetchColumn();

        if ($currency_length > 3 && $longest_currency <= 3) {

            $pdo->query("
                ALTER TABLE `currencies`
                MODIFY `currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

        }

        // Users added later take the time zone column's default, which older upgrades left as Canada/Pacific, one of
        // the older names (see Time::LEGACY_ZONES). Point it at the zone that name stands for.
        $stmt = $pdo->prepare("
            SELECT `COLUMN_DEFAULT`
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'user_settings'
              AND `COLUMN_NAME` = 'default_timezone'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        // MariaDB quotes a text default here and MySQL doesn't.
        $timezone_default = trim((string) $stmt->fetchColumn(), "'");
        $stmt->closeCursor();

        if (isset(DomainMOD\Time::LEGACY_ZONES[$timezone_default])) {

            $pdo->query("
                ALTER TABLE `user_settings`
                ALTER `default_timezone` SET DEFAULT " . $pdo->quote(DomainMOD\Time::LEGACY_ZONES[$timezone_default]));

        }

        // When cron.php last ran, so administrators are warned when the Task Scheduler stops (Scheduler::healthState()).
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'settings'
              AND `COLUMN_NAME` = 'cron_last_run'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {

            $pdo->query("
                ALTER TABLE `settings`
                ADD `cron_last_run` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `upgrade_available`");

        }

        /*
         * Indexes for the server-side Domains and SSL Certificates lists: each list joins its custom field rows by the
         * asset's id, and its default order is the expiry date, then the name. The lists join their tables in a fixed
         * order only once the database is at this version and the indexes the query needs (these and earlier lookup keys)
         * are usable (ListQuery::fixedJoinOrder()). An index that's already there (by name) is left
         * alone.
         */
        $list_indexes = array(
            array('domain_field_data', 'domain_id', '`domain_id`'),
            array('ssl_cert_field_data', 'ssl_id', '`ssl_id`'),
            array('domains', 'expiry_date', '`expiry_date`, `domain`'),
            array('ssl_certs', 'expiry_date', '`expiry_date`, `name`'));

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name
              AND `INDEX_NAME` = :index_name");

        foreach ($list_indexes as $list_index) {

            list($table_name, $index_name, $index_columns) = $list_index;

            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $table_name, PDO::PARAM_STR);
            $stmt->bindValue('index_name', $index_name, PDO::PARAM_STR);
            $stmt->execute();
            $index_exists = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($index_exists === 0) {

                $pdo->query("
                    ALTER TABLE `" . $table_name . "`
                    ADD INDEX `" . $index_name . "` (" . $index_columns . ")");

            }

        }

        // Large Mode is gone: the Domains list pages, sorts, and searches on the server for everyone.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'settings'
              AND `COLUMN_NAME` = 'large_mode'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 1) {

            $pdo->query("
                ALTER TABLE `settings`
                DROP `large_mode`");

        }

        $pdo->beginTransaction();

        /*
         * Older time zone names (US/Pacific, Asia/Calcutta, and so on) can be refused by PHP builds that use a system's
         * time zone list without the legacy names. Saved settings and scheduled tasks move to the zone each name stands
         * for, and the time zone menus offer only the current names, each once. Time::LEGACY_ZONES stays, for sessions
         * and imports.
         */
        $stmt_user = $pdo->prepare("
            UPDATE user_settings
            SET default_timezone = :new_timezone
            WHERE default_timezone = :old_timezone");
        $stmt_task = $pdo->prepare("
            UPDATE scheduler
            SET `timezone` = :new_timezone
            WHERE `timezone` = :old_timezone");
        $stmt_delete = $pdo->prepare("
            DELETE FROM timezones
            WHERE `timezone` = :old_timezone");

        foreach (DomainMOD\Time::LEGACY_ZONES as $old_timezone => $new_timezone) {

            foreach (array($stmt_user, $stmt_task) as $stmt) {

                $stmt->bindValue('new_timezone', $new_timezone, PDO::PARAM_STR);
                $stmt->bindValue('old_timezone', $old_timezone, PDO::PARAM_STR);
                $stmt->execute();

            }

            $stmt_delete->bindValue('old_timezone', $old_timezone, PDO::PARAM_STR);
            $stmt_delete->execute();

        }

        $stmt_check = $pdo->prepare("
            SELECT COUNT(*)
            FROM timezones
            WHERE `timezone` = :timezone");
        $stmt_insert = $pdo->prepare("
            INSERT INTO timezones
            (`timezone`, insert_time)
            VALUES
            (:timezone, :insert_time)");

        foreach (array_unique(DomainMOD\Time::LEGACY_ZONES) as $new_timezone) {

            $stmt_check->bindValue('timezone', $new_timezone, PDO::PARAM_STR);
            $stmt_check->execute();
            $timezone_exists = (int) $stmt_check->fetchColumn();
            $stmt_check->closeCursor();

            if ($timezone_exists === 0) {

                $stmt_insert->bindValue('timezone', $new_timezone, PDO::PARAM_STR);
                $stmt_insert->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
                $stmt_insert->execute();

            }

        }

        /*
         * The Malaysian ringgit was seeded twice: as RM (its symbol, so its exchange rate can't be looked up) and as MYR
         * (its ISO 4217 code). Fees, exchange rates, and users' default currencies on RM move to MYR, and the RM row
         * goes. Without an MYR row, RM is renamed instead. A user with exchange rates for both keeps MYR's.
         */
        $stmt = $pdo->prepare("
            SELECT id
            FROM currencies
            WHERE currency = :currency
            ORDER BY id ASC");
        $stmt->bindValue('currency', 'RM', PDO::PARAM_STR);
        $stmt->execute();
        $rm_currency_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $stmt->bindValue('currency', 'MYR', PDO::PARAM_STR);
        $stmt->execute();
        $myr_currency_id = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($rm_currency_ids !== array()) {

            if ($myr_currency_id === 0) {

                $myr_currency_id = array_shift($rm_currency_ids);

                $stmt = $pdo->prepare("
                    UPDATE currencies
                    SET currency = 'MYR',
                        `name` = 'Malaysian Ringgit',
                        update_time = :update_time
                    WHERE id = :currency_id");
                $stmt->bindValue('update_time', $time->stamp(), PDO::PARAM_STR);
                $stmt->bindValue('currency_id', $myr_currency_id, PDO::PARAM_INT);
                $stmt->execute();

            }

            $stmt_fees = $pdo->prepare("
                UPDATE fees
                SET currency_id = :new_currency_id
                WHERE currency_id = :old_currency_id");
            $stmt_ssl_fees = $pdo->prepare("
                UPDATE ssl_fees
                SET currency_id = :new_currency_id
                WHERE currency_id = :old_currency_id");
            $stmt_conversions = $pdo->prepare("
                UPDATE currency_conversions
                SET currency_id = :new_currency_id
                WHERE currency_id = :old_currency_id");
            // The users who already have a MYR rate are read first, rather than in a subquery on the table being
            // deleted from, which MySQL 5.7.9 and 5.7.10 can refuse (error 1093)
            $stmt_myr_users = $pdo->prepare("
                SELECT user_id
                FROM currency_conversions
                WHERE currency_id = :new_currency_id");
            $stmt_delete_conversion = $pdo->prepare("
                DELETE FROM currency_conversions
                WHERE currency_id = :old_currency_id
                  AND user_id = :user_id");
            $stmt_delete_currency = $pdo->prepare("
                DELETE FROM currencies
                WHERE id = :old_currency_id");

            foreach ($rm_currency_ids as $rm_currency_id) {

                // A user's exchange rate for RM goes only where they have none for MYR (one per user and currency)
                $stmt_myr_users->bindValue('new_currency_id', $myr_currency_id, PDO::PARAM_INT);
                $stmt_myr_users->execute();
                foreach ($stmt_myr_users->fetchAll(PDO::FETCH_COLUMN) as $myr_user_id) {

                    $stmt_delete_conversion->bindValue('old_currency_id', $rm_currency_id, PDO::PARAM_INT);
                    $stmt_delete_conversion->bindValue('user_id', (int) $myr_user_id, PDO::PARAM_INT);
                    $stmt_delete_conversion->execute();

                }

                foreach (array($stmt_fees, $stmt_ssl_fees, $stmt_conversions) as $stmt) {

                    $stmt->bindValue('new_currency_id', $myr_currency_id, PDO::PARAM_INT);
                    $stmt->bindValue('old_currency_id', $rm_currency_id, PDO::PARAM_INT);
                    $stmt->execute();

                }

                $stmt_delete_currency->bindValue('old_currency_id', $rm_currency_id, PDO::PARAM_INT);
                $stmt_delete_currency->execute();

            }

            $stmt = $pdo->prepare("
                UPDATE user_settings
                SET default_currency = 'MYR'
                WHERE default_currency = 'RM'");
            $stmt->execute();


        }

        // A user left on RM after its currency row was already gone (an import or a manual clean-up) moves to MYR too,
        // since a default currency that has no row can't be looked up at sign-in.
        if ($myr_currency_id !== 0) {

            $stmt = $pdo->prepare("
                UPDATE user_settings
                SET default_currency = 'MYR'
                WHERE default_currency = 'RM'");
            $stmt->execute();

            // A user whose default currency is now MYR converts MYR at 1, as for any default currency, so a failed
            // 0.0000 rate kept from the merge can't zero their ringgit costs until the next conversion run.
            $stmt = $pdo->prepare("
                UPDATE currency_conversions cc
                JOIN user_settings us ON us.user_id = cc.user_id
                SET cc.conversion = 1
                WHERE cc.currency_id = :myr_currency_id
                  AND us.default_currency = 'MYR'");
            $stmt->bindValue('myr_currency_id', $myr_currency_id, PDO::PARAM_INT);
            $stmt->execute();

        }

        /*
         * Every domain and SSL certificate gets its custom field row, which some older imports and edits left out.
         * Without one, the lists left the asset out while the totals still counted it. NOT IN is used rather than a
         * LEFT JOIN, since it needs no index on the asset's id (an install can already have its own index under the name
         * the step above uses, and then the step adds none): the database looks the ids up once, so 40,000 domains take
         * a fraction of a second instead of half a minute. The subquery leaves out NULL ids, which would otherwise make
         * NOT IN match nothing.
         */
        $stmt = $pdo->prepare("
            INSERT INTO domain_field_data
            (domain_id, insert_time)
            SELECT d.id, :insert_time
            FROM domains AS d
            WHERE d.id NOT IN (SELECT domain_id FROM domain_field_data WHERE domain_id IS NOT NULL)");
        $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdo->prepare("
            INSERT INTO ssl_cert_field_data
            (ssl_id, insert_time)
            SELECT sc.id, :insert_time
            FROM ssl_certs AS sc
            WHERE sc.id NOT IN (SELECT ssl_id FROM ssl_cert_field_data WHERE ssl_id IS NOT NULL)");
        $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        /*
         * An install whose tasks have run before starts with the upgrade's time as cron.php's last run, so a working cron
         * job isn't reported as stopped before its next run (with the Domain Queue disabled, the other tasks run only
         * daily, so their last run can be hours old). A broken one is reported two hours after the upgrade. An install
         * whose tasks have never run keeps 1970-01-01 (never run), and a time already recorded is kept.
         */
        $stmt = $pdo->prepare("
            UPDATE settings
            SET cron_last_run = :cron_last_run
            WHERE cron_last_run = '1970-01-01 00:00:00'
              AND (SELECT COUNT(*)
                   FROM scheduler
                   WHERE last_run > '1970-01-01 00:00:00') > 0");
        $stmt->bindValue('cron_last_run', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.26.0 to 4.27.0
if ($current_db_version === '4.26.0') {

    $old_version = '4.26.0';
    $new_version = '4.27.0';

    try {

        /*
         * ALTER TABLE commits straight away, so the schema steps come first, outside the transaction, and each one is
         * safe to run again: an upgrade that stops part-way finishes on the next run. A column that's already there (by
         * name) is left alone.
         *
         * - settings.latest_version and latest_version_checked: the newest release the last successful version check
         *   found, and when, for the Upgrade page (System::checkLatestVersion()).
         * - goal_activity.install_type and files_writable: the kind of install (InstallType::detect()) and whether the
         *   web server can write to its files (InstallType::filesWritable()), sent with the install and upgrade
         *   activity (Goal).
         * - users.two_factor_* and settings.two_factor_required: two-factor sign-in (TwoFactor). Every column defaults
         *   to "off".
         */
        $new_columns = array(
            array('settings', 'latest_version', "VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `upgrade_available`"),
            array('settings', 'latest_version_checked', "DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `latest_version`"),
            array('goal_activity', 'install_type', "VARCHAR(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown' AFTER `language`"),
            array('goal_activity', 'files_writable', "VARCHAR(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown' AFTER `install_type`"),
            array('users', 'two_factor_secret', "VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `last_login`"),
            array('users', 'two_factor_last_step', "BIGINT(20) UNSIGNED NOT NULL DEFAULT '0' AFTER `two_factor_secret`"),
            array('users', 'two_factor_failures', "SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0' AFTER `two_factor_last_step`"),
            array('users', 'two_factor_locked_until', "INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `two_factor_failures`"),
            array('users', 'two_factor_stamp', "CHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `two_factor_locked_until`"),
            array('users', 'two_factor_codes_confirmed', "TINYINT(1) NOT NULL DEFAULT '0' AFTER `two_factor_stamp`"),
            array('users', 'two_factor_enabled_time', "DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `two_factor_codes_confirmed`"),
            array('settings', 'two_factor_required', "TINYINT(1) NOT NULL DEFAULT '0' AFTER `local_php_log`"));

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name
              AND `COLUMN_NAME` = :column_name");

        foreach ($new_columns as $new_column) {

            list($table_name, $column_name, $column_definition) = $new_column;

            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $table_name, PDO::PARAM_STR);
            $stmt->bindValue('column_name', $column_name, PDO::PARAM_STR);
            $stmt->execute();
            $column_exists = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($column_exists === 0) {

                $pdo->query("
                    ALTER TABLE `" . $table_name . "`
                    ADD `" . $column_name . "` " . $column_definition);

            }

        }

        /*
         * The SSL Queue's tables. A database at 4.26.0 can't hold valid rows in them, but restoring a 4.26.0 backup over
         * a 4.27.0 install leaves them behind (the restore only runs the backup's own statements), and old status rows
         * could then attach to new certificates that reuse their ids. So they're dropped and created empty, which also
         * lets an upgrade that stopped part-way finish cleanly.
         *
         * - ssl_queue: one row per hostname waiting for, or finished with, its certificate lookup.
         * - ssl_queue_history: rows moved out of ssl_queue by Clear Completed.
         * - ssl_cert_status: the latest lookup of each SSL certificate (by the SSL Queue or Refresh).
         */
        $pdo->query("DROP TABLE IF EXISTS `ssl_queue`");
        $pdo->query("DROP TABLE IF EXISTS `ssl_queue_history`");
        $pdo->query("DROP TABLE IF EXISTS `ssl_cert_status`");

        $pdo->query("
            CREATE TABLE IF NOT EXISTS `ssl_queue` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `host` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
                `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `domain_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `provider_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `type_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `wait_for_domain` TINYINT(1) NOT NULL DEFAULT '0',
                `wait_domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `wait_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `exact_domain` TINYINT(1) NOT NULL DEFAULT '0',
                `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
                `issuer` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `fingerprint` CHAR(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `processing` TINYINT(1) NOT NULL DEFAULT '0',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_ssl` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `failed` TINYINT(1) NOT NULL DEFAULT '0',
                `message` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `new_ssl_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`),
                KEY `host` (`host`),
                KEY `finished` (`finished`, `processing`, `last_attempt`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
            CREATE TABLE IF NOT EXISTS `ssl_queue_history` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `host` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
                `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `domain_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `provider_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `type_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `wait_for_domain` TINYINT(1) NOT NULL DEFAULT '0',
                `wait_domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `wait_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `exact_domain` TINYINT(1) NOT NULL DEFAULT '0',
                `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
                `issuer` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `fingerprint` CHAR(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_ssl` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `failed` TINYINT(1) NOT NULL DEFAULT '0',
                `message` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
                `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `new_ssl_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
            CREATE TABLE IF NOT EXISTS `ssl_cert_status` (
                `ssl_id` INT(10) UNSIGNED NOT NULL,
                `host` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `fingerprint` CHAR(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
                `issuer` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `refreshed_issuer` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
                `names` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `valid_from` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `valid_to` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `trusted` TINYINT(1) NOT NULL DEFAULT '0',
                `last_check` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `last_result` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `revision` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY  (`ssl_id`),
                KEY `fingerprint` (`fingerprint`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        /*
         * An index on ssl_certs.domain_id, so the SSL Queue's duplicate checks and the bulk delete's locking read of a
         * domain's certificates only read that domain's rows. It's added only when no index starts with that column (an
         * index where it comes second can't be used for it), named domain_id, or domain_id_ssl_queue if an index on other
         * columns already has that name (then domain_id_ssl_queue_2, and so on).
         */
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'ssl_certs'
              AND `COLUMN_NAME` = 'domain_id'
              AND `SEQ_IN_INDEX` = 1");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $domain_id_indexed = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($domain_id_indexed === 0) {

            $stmt = $pdo->prepare("
                SELECT DISTINCT `INDEX_NAME`
                FROM `INFORMATION_SCHEMA`.`STATISTICS`
                WHERE `TABLE_SCHEMA` = :dbname
                  AND `TABLE_NAME` = 'ssl_certs'");
            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->execute();
            $index_names = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $stmt->closeCursor();

            $index_name = 'domain_id';

            for ($suffix = 1; in_array($index_name, $index_names, true); $suffix++) {

                $index_name = ($suffix === 1) ? 'domain_id_ssl_queue' : 'domain_id_ssl_queue_' . $suffix;

            }

            $pdo->query("ALTER TABLE `ssl_certs` ADD INDEX `" . $index_name . "` (`domain_id`)");

        }

        /*
         * ssl_certs.domain_id defaults to 0, "no domain". Only the default changes, so the table isn't rebuilt, and
         * running it again changes nothing. No code relies on it: every insert writes domain_id itself.
         */
        $pdo->query("ALTER TABLE `ssl_certs` ALTER `domain_id` SET DEFAULT '0'");

        /*
         * An index on segment_data.segment_id alone, so the Domains list's segment flags are read and written for that
         * segment alone, in id order, and a write locks only its rows rather than every segment's (see
         * DomainList::writeSegmentFlags()). It's added only when no usable index has segment_id as its only column (one
         * with more columns isn't in id order within a segment, and the flags don't use one that's ignored on MariaDB or
         * invisible on MySQL), named segment_id, or segment_id_flags if another index already has that name (then
         * segment_id_flags_2, and so on).
         */
        $stmt = $pdo->prepare("
            SELECT *
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'segment_data'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $segment_data_indexes = array();
        $segment_index_usable = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index_row) {
            $index_row = array_change_key_case($index_row, CASE_LOWER);
            $index_key = strtolower($index_row['index_name']);
            $segment_data_indexes[$index_key][(int) $index_row['seq_in_index']] = strtolower($index_row['column_name']);
            $segment_index_usable[$index_key] = ($segment_index_usable[$index_key] ?? true)
                && strtoupper((string) ($index_row['ignored'] ?? 'NO')) !== 'YES'
                && strtoupper((string) ($index_row['is_visible'] ?? 'YES')) !== 'NO';
        }
        $stmt->closeCursor();

        $segment_index_found = false;
        foreach ($segment_data_indexes as $index_key => $index_columns) {
            if ($index_columns === array(1 => 'segment_id') && $segment_index_usable[$index_key]) $segment_index_found = true;
        }

        if (!$segment_index_found) {

            $segment_index_name = 'segment_id';

            for ($suffix = 1; isset($segment_data_indexes[$segment_index_name]); $suffix++) {

                $segment_index_name = ($suffix === 1) ? 'segment_id_flags' : 'segment_id_flags_' . $suffix;

            }

            $pdo->query("ALTER TABLE `segment_data` ADD INDEX `" . $segment_index_name . "` (`segment_id`)");

        }

        // Two-factor sign-in's recovery codes (stored as SHA-256 hashes) and remembered devices (token hashes)
        $pdo->query("
            CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `code_hash` CHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `used_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`),
                UNIQUE KEY `user_code` (`user_id`, `code_hash`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
            CREATE TABLE IF NOT EXISTS `user_remembered_devices` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) UNSIGNED NOT NULL,
                `token_hash` CHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expires` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `stamp` CHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`),
                UNIQUE KEY `token_hash` (`token_hash`),
                KEY `user_expires` (`user_id`, `expires`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        /*
         * An older version's Domain Queue run could leave a row marked ready to import without finishing it (the run was
         * killed, or the import couldn't be confirmed). Once Clear Queue Processing cleared the row's processing flag, the
         * queue never picked the row up again. A row whose domain is in the main domain table (the import itself may have
         * gone through) is finished as already there, as DomainQueue::markAlreadyInDomains() does, rather than waiting for
         * a registrar lookup that may no longer work. It keeps the domain id the row already has when that domain has the
         * row's name (the same name can be listed more than once), and otherwise takes the first domain with that name.
         * The others without a domain id lose their ready mark, as DomainQueue::releaseDomainProcessing() does for rows
         * still flagged as processing, so the queue looks them up again. The remaining rows with a domain id are left as
         * they are, since their domain was imported but is no longer in the main domain table under the row's name (it was
         * deleted or renamed since), and the queue mustn't add it again. Finished rows are left alone. These updates run
         * before the transaction, each committing on its own, so the queue's rows are locked only while each one runs, and
         * each is safe to run again.
         */
        $pdo->query("
            UPDATE domain_queue AS dq
            SET dq.domain_id = (SELECT d.id
                                FROM domains AS d
                                WHERE d.domain = dq.domain
                                ORDER BY d.id = dq.domain_id DESC, d.id
                                LIMIT 1),
                dq.ready_to_import = '1',
                dq.finished = '1',
                dq.already_in_domains = '1'
            WHERE dq.processing = '0'
              AND dq.ready_to_import = '1'
              AND dq.finished = '0'
              AND EXISTS (SELECT d.id FROM domains AS d WHERE d.domain = dq.domain)");

        /*
         * An unfinished row still flagged as processing (an older version's run was killed or stopped early without
         * clearing it, and nobody cleared the flag since) is finished the same way when its domain is in the main domain
         * table, so it doesn't wait for a registrar lookup that may no longer work. Other rows still flagged as processing
         * are left for the queue's next run, which releases them (DomainQueue::releaseDomainProcessing()).
         */
        $pdo->query("
            UPDATE domain_queue AS dq
            SET dq.domain_id = (SELECT d.id
                                FROM domains AS d
                                WHERE d.domain = dq.domain
                                ORDER BY d.id = dq.domain_id DESC, d.id
                                LIMIT 1),
                dq.processing = '0',
                dq.ready_to_import = '1',
                dq.finished = '1',
                dq.already_in_domains = '1'
            WHERE dq.processing = '1'
              AND dq.finished = '0'
              AND EXISTS (SELECT d.id FROM domains AS d WHERE d.domain = dq.domain)");

        $pdo->query("
            UPDATE domain_queue
            SET ready_to_import = '0'
            WHERE processing = '0'
              AND ready_to_import = '1'
              AND finished = '0'
              AND domain_id = '0'");

        // ===== BEGIN: RDAP lock status (RDAP-LOCK-STATUS-PLAN.local.md, rdap-lock-status build) =====
        /*
         * The registry status codes and transfer lock (DomainStatus): domains.transfer_lock (0 Not Checked, Couldn't
         * Check, or Unknown, 1 Not Locked, 2 Locked), epp_status (the known codes, comma-separated), epp_status_time
         * (when they were last read, UTC), and epp_status_failed_time (when a later lookup couldn't read them, UTC).
         * The two times tell the three states that share 0 apart: neither set is Not Checked, only the failure set is
         * Couldn't Check, and a read time set (even with a later failure) is Unknown. Also the Transfer Lock column's
         * Display Setting, user_settings.display_domain_lock (off). Each column is added only when it's missing, so an
         * upgrade that stops part-way finishes on the next run, and the missing domains columns go in one ALTER TABLE,
         * so a server that copies the table to add columns copies it once. Existing domains start Not Checked from the
         * defaults.
         */
        $status_columns = array(
            'transfer_lock' => "ADD `transfer_lock` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `privacy`",
            'epp_status' => "ADD `epp_status` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `transfer_lock`",
            'epp_status_time' => "ADD `epp_status_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `epp_status`",
            'epp_status_failed_time' => "ADD `epp_status_failed_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `epp_status_time`");

        $status_column_check = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name
              AND `COLUMN_NAME` = :column_name");

        $missing_status_columns = array();

        foreach ($status_columns as $column_name => $column_change) {

            $status_column_check->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $status_column_check->bindValue('table_name', 'domains', PDO::PARAM_STR);
            $status_column_check->bindValue('column_name', $column_name, PDO::PARAM_STR);
            $status_column_check->execute();
            if ((int) $status_column_check->fetchColumn() === 0) $missing_status_columns[] = $column_change;
            $status_column_check->closeCursor();

        }

        if ($missing_status_columns) {

            $pdo->query("
                ALTER TABLE `domains`
                " . implode(",
                ", $missing_status_columns));

        }

        $status_column_check->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $status_column_check->bindValue('table_name', 'user_settings', PDO::PARAM_STR);
        $status_column_check->bindValue('column_name', 'display_domain_lock', PDO::PARAM_STR);
        $status_column_check->execute();
        $display_lock_exists = (int) $status_column_check->fetchColumn();
        $status_column_check->closeCursor();

        if ($display_lock_exists === 0) {

            $pdo->query("
                ALTER TABLE `user_settings`
                ADD `display_domain_lock` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_domain_fee`");

        }
        // ===== END: RDAP lock status =====

        // ===== BEGIN: Dynadot REST, schema (DYNADOT-REST-PLAN.local.md, dynadot-rest build) =====
        // When a Domain Queue list was last tried, so a list that keeps failing waits behind lists not tried yet, added
        // only when it's missing, as domain_queue.last_attempt was in 4.25.0
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'domain_queue_list'
              AND `COLUMN_NAME` = 'last_attempt'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $list_last_attempt_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($list_last_attempt_exists === 0) {

            $pdo->query("
                ALTER TABLE `domain_queue_list`
                ADD `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' AFTER `finished`");

        }
        // ===== END: Dynadot REST, schema =====

        // ===== BEGIN: Change History, schema (CHANGE-HISTORY-PLAN.local.md, change-history build) =====
        /*
         * The Change History's tables (DomainMOD\ChangeHistory): change_operations, one row per action, and
         * change_history, one row per changed field. Created only when they're missing, never dropped, since a restored
         * 4.27.0 backup's history belongs to it (the restore's cleanup deals with a table of the wrong shape). Then
         * settings.history_retention_months (36, three years, with 0 meaning Forever), added only when it's missing.
         */
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `change_operations` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `source` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `action` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `note` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `note_code` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `item_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `insert_time` (`insert_time`),
            KEY `source_action` (`source`, `action`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
        CREATE TABLE IF NOT EXISTS `change_history` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `operation_id` INT(10) UNSIGNED NOT NULL,
            `item_type` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `item_id` INT(10) UNSIGNED NOT NULL,
            `item_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `change_type` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `old_value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `new_value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `old_display` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `new_display` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `old_hash` CHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `old_length` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `snapshot` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `via` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `detail` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `source` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `changed_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            UNIQUE KEY `operation_item` (`operation_id`, `item_type`, `item_id`, `field`, `change_type`),
            KEY `item` (`item_type`, `item_id`, `id`),
            KEY `item_life` (`item_type`, `item_id`, `change_type`, `id`),
            KEY `changed_time` (`changed_time`, `id`),
            KEY `source` (`source`, `changed_time`),
            KEY `user_id` (`user_id`, `changed_time`),
            KEY `field` (`field`, `changed_time`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        /*
         * Two indexes for the Change History's Item sort, one for each direction (ties newest first, see
         * ChangeHistoryList::orderSql()), only where the server honours descending index parts (MariaDB 10.8 and MySQL
         * 8.0 on), since an older one would keep two plain indexes that never serve the sort. They're optional: a
         * server set up with 4 KB pages can't hold a key this long, and the list sorts without them, so a failure here
         * is ignored.
         */
        $history_db = DomainMOD\Database::getInstance();
        if (version_compare($history_db->getDbVersion(), $history_db->getDbType() === 'MariaDB' ? '10.8' : '8.0', '>=')) {
            foreach (array("ADD KEY `item_name` (`item_name`, `changed_time` DESC, `operation_id` DESC)",
                         "ADD KEY `item_name_desc` (`item_name` DESC, `changed_time` DESC, `operation_id` DESC)") as $item_key) {
                try {
                    $pdo->query("ALTER TABLE `change_history` " . $item_key);
                } catch (PDOException $e) {
                }
            }
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'settings'
              AND `COLUMN_NAME` = 'history_retention_months'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $retention_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($retention_exists === 0) {

            $pdo->query("
                ALTER TABLE `settings`
                ADD `history_retention_months` INT(10) UNSIGNED NOT NULL DEFAULT '36' AFTER `two_factor_required`");

        }

        /*
         * The indexes the history's locking reads use, so a fee page, an account page, or a domain delete locks only
         * the matching rows rather than scanning the table: domains (registrar_id, tld), (fee_id), and (account_id),
         * and ssl_certs (ssl_provider_id, type_id), (type_id), (fee_id), and (account_id). Each is added only when no
         * usable index already starts with the same columns in the same order (one that's ignored on MariaDB or
         * invisible on MySQL doesn't count), named after its first column, or with a _history suffix (then _history_2,
         * and so on) when that name is taken. Then ssl_certs (domain_id) on its own, for a domain delete's keyset pages
         * of its certificates, which need an index in id order within a domain: added unless a usable index has
         * domain_id as its only column (one with more columns, such as (domain_id, expiry_date), isn't in id order
         * within a domain).
         */
        $history_indexes = array(
            array('domains', array('registrar_id', 'tld'), false),
            array('domains', array('fee_id'), false),
            array('domains', array('account_id'), false),
            array('ssl_certs', array('ssl_provider_id', 'type_id'), false),
            array('ssl_certs', array('type_id'), false),
            array('ssl_certs', array('fee_id'), false),
            array('ssl_certs', array('account_id'), false),
            array('ssl_certs', array('domain_id'), true));

        $stmt = $pdo->prepare("
            SELECT *
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name");

        foreach ($history_indexes as $history_index) {

            list($table_name, $index_columns, $only_column) = $history_index;

            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $table_name, PDO::PARAM_STR);
            $stmt->execute();
            $table_indexes = array();
            $table_index_usable = array();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index_row) {
                $index_row = array_change_key_case($index_row, CASE_LOWER);
                $index_key = strtolower($index_row['index_name']);
                $table_indexes[$index_key][(int) $index_row['seq_in_index']] = strtolower($index_row['column_name']);
                $table_index_usable[$index_key] = ($table_index_usable[$index_key] ?? true)
                    && strtoupper((string) ($index_row['ignored'] ?? 'NO')) !== 'YES'
                    && strtoupper((string) ($index_row['is_visible'] ?? 'YES')) !== 'NO';
            }
            $stmt->closeCursor();

            $wanted = array_combine(range(1, count($index_columns)), $index_columns);
            $index_found = false;
            foreach ($table_indexes as $index_key => $columns_in_index) {
                ksort($columns_in_index);
                $matches = $only_column ? $columns_in_index === $wanted
                    : array_slice($columns_in_index, 0, count($wanted), true) === $wanted;
                if ($matches && $table_index_usable[$index_key]) $index_found = true;
            }

            if (!$index_found) {
                $index_name = $index_columns[0];
                for ($suffix = 1; isset($table_indexes[$index_name]); $suffix++) {
                    $index_name = ($suffix === 1) ? $index_columns[0] . '_history' : $index_columns[0] . '_history_' . $suffix;
                }
                $pdo->query("ALTER TABLE `" . $table_name . "` ADD INDEX `" . $index_name . "` (`"
                    . implode("`, `", $index_columns) . "`)");
            }

        }
        // ===== END: Change History, schema =====

        // ===== BEGIN: Watch list, schema (DOMAIN-WATCH-LIST-PLAN.local.md, watch-list build) =====
        /*
         * The Watch List's table (DomainMOD\WatchList). This block only runs on a database at 4.26.0, which never has a
         * real watch list, so a watch_domains already there can only be a later install's table that a restored older dump
         * didn't drop: it's emptied (dropped and created again), so an overwritten database's watched domains don't come
         * back and start being checked.
         */
        $pdo->query("DROP TABLE IF EXISTS `watch_domains`");
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `watch_domains` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
            `reason` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'watch',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `archived` TINYINT(1) NOT NULL DEFAULT '0',
            `last_status_read` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `has_baseline` TINYINT(1) NOT NULL DEFAULT '0',
            `moved_domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `stage` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `pending_stage` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `pending_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `pending_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `revision` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `statuses` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `registration_date` DATE NOT NULL DEFAULT '1970-01-01',
            `deletion_date` DATE NOT NULL DEFAULT '1970-01-01',
            `expiry_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `grace_base_expiry` DATE NOT NULL DEFAULT '1970-01-01',
            `grace_base_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `grace_open` TINYINT(1) NOT NULL DEFAULT '0',
            `registrar_name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `registrar_iana_id` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `registrar_url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `stage_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `stage_last_seen` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `prev_check` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `drop_from` DATE NOT NULL DEFAULT '1970-01-01',
            `drop_to` DATE NOT NULL DEFAULT '1970-01-01',
            `drop_basis` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_success` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `next_due` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `hold_until` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `failures` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
            `last_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_result` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            UNIQUE KEY `domain` (`domain`),
            KEY `next_due` (`next_due`),
            KEY `archived_stage` (`archived`, `stage`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        // settings.use_watch_list ("Use the Watch List" in System Settings, on by default), added only when it's missing
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'settings'
              AND `COLUMN_NAME` = 'use_watch_list'");
        $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt->execute();
        $use_watch_list_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($use_watch_list_exists === 0) {

            $pdo->query("
                ALTER TABLE `settings`
                ADD `use_watch_list` TINYINT(1) NOT NULL DEFAULT '1' AFTER `history_retention_months`");

        }
        // ===== END: Watch list, schema =====

        // ===== BEGIN: Hosting, schema (WEB-HOSTING-SECTION-PLAN.local.md and HOSTING-RESTRUCTURE-PLAN.local.md, web-hosting build) =====
        /*
         * Hosting's tables (DomainMOD\Hosting): hosting services, the hosting accounts they belong to, service types,
         * fees, and custom fields. This block only runs on a database at 4.26.0, which never has real hosting data (every
         * hosting page and task waits for the version), so any of these tables already there are leftovers, from a
         * stopped upgrade, a pre-release test build, or a later install's tables that a restored older dump didn't drop.
         * They're dropped and created empty, so those rows don't come back pointing by id at the restored domains,
         * providers, and owners. The list also has the names pre-release builds used before the services were renamed
         * (hosting_accounts, hosting_account_fields, and hosting_account_field_data), so a test database's leftovers
         * under those names go too.
         */
        $creation_type_id_manual = $system->getCreationTypeId('Manual');

        foreach (array('hosting_service_field_data', 'hosting_service_fields', 'hosting_account_field_data',
                     'hosting_account_fields', 'hosting_fees', 'hosting_types', 'hosting_provider_accounts',
                     'hosting_services', 'hosting_accounts') as $hosting_table) {

            $pdo->query("DROP TABLE IF EXISTS `" . $hosting_table . "`");

        }

        // A service belongs to one hosting account (account_id), and its owner and provider follow the account. Its IP
        // address is optional (0 is "None").
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_services` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL,
            `hosting_id` INT(10) UNSIGNED NOT NULL,
            `account_id` INT(10) UNSIGNED NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `cat_id` INT(10) UNSIGNED NOT NULL,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `hostname` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `billing_term` TINYINT(3) UNSIGNED NOT NULL DEFAULT '12',
            `renewal_day` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1',
            `revision` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `fee_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `total_cost` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `active` TINYINT(2) NOT NULL DEFAULT '1',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `fee_group` (`hosting_id`, `type_id`, `billing_term`),
            KEY `fee_id` (`fee_id`),
            KEY `account_id` (`account_id`),
            KEY `autorenew_due` (`autorenew`, `active`, `expiry_date`),
            KEY `expiry_date` (`expiry_date`, `name`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        // Like ssl_accounts, without a password column (people keep those in a password manager)
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_provider_accounts` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL,
            `hosting_id` INT(10) UNSIGNED NOT NULL,
            `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `reseller` TINYINT(1) NOT NULL DEFAULT '0',
            `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `hosting_id` (`hosting_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_types` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        // A fee is per provider, service type, and billing term (in months), and the unique key keeps it that way
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_fees` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `hosting_id` INT(10) UNSIGNED NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `billing_term` TINYINT(3) UNSIGNED NOT NULL DEFAULT '12',
            `initial_fee` DECIMAL(10,2) NOT NULL,
            `renewal_fee` DECIMAL(10,2) NOT NULL,
            `misc_fee` DECIMAL(10,2) NOT NULL,
            `currency_id` INT(10) UNSIGNED NOT NULL,
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            UNIQUE KEY `fee_group` (`hosting_id`, `type_id`, `billing_term`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_service_fields` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting_service_field_data` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `service_id` INT(10) UNSIGNED NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `service_id` (`service_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        // The users' and the system's hosting settings, mirroring the SSL ones. Each column is added only if it isn't
        // there yet (ALTER TABLE commits straight away, so a stopped upgrade carries on).
        $hosting_columns = array(
            array('user_settings', 'default_category_hosting', "ADD `default_category_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_ssl_provider`"),
            array('user_settings', 'default_ip_address_hosting', "ADD `default_ip_address_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_category_hosting`"),
            array('user_settings', 'default_owner_hosting', "ADD `default_owner_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_ip_address_hosting`"),
            array('user_settings', 'default_hosting_provider_account', "ADD `default_hosting_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_owner_hosting`"),
            array('user_settings', 'default_hosting_type', "ADD `default_hosting_type` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_hosting_provider_account`"),
            array('user_settings', 'number_of_hosting_services', "ADD `number_of_hosting_services` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_ssl_certs`"),
            array('user_settings', 'display_hosting_owner', "ADD `display_hosting_owner` TINYINT(1) NOT NULL DEFAULT '1' AFTER `display_ssl_fee`"),
            array('user_settings', 'display_hosting_provider', "ADD `display_hosting_provider` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_hosting_owner`"),
            array('user_settings', 'display_hosting_account', "ADD `display_hosting_account` TINYINT(1) NOT NULL DEFAULT '1' AFTER `display_hosting_provider`"),
            array('user_settings', 'display_hosting_type', "ADD `display_hosting_type` TINYINT(1) NOT NULL DEFAULT '1' AFTER `display_hosting_account`"),
            array('user_settings', 'display_hosting_expiry_date', "ADD `display_hosting_expiry_date` TINYINT(1) NOT NULL DEFAULT '1' AFTER `display_hosting_type`"),
            array('user_settings', 'display_hosting_ip', "ADD `display_hosting_ip` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_hosting_expiry_date`"),
            array('user_settings', 'display_hosting_category', "ADD `display_hosting_category` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_hosting_ip`"),
            array('user_settings', 'display_hosting_fee', "ADD `display_hosting_fee` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_hosting_category`"),
            array('settings', 'default_category_hosting', "ADD `default_category_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_ssl_provider`"),
            array('settings', 'default_ip_address_hosting', "ADD `default_ip_address_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_category_hosting`"),
            array('settings', 'default_owner_hosting', "ADD `default_owner_hosting` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_ip_address_hosting`"),
            array('settings', 'default_hosting_provider_account', "ADD `default_hosting_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_owner_hosting`"),
            array('settings', 'default_hosting_type', "ADD `default_hosting_type` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `default_hosting_provider_account`"));

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name
              AND `COLUMN_NAME` = :column_name");

        foreach ($hosting_columns as $hosting_column) {

            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $hosting_column[0], PDO::PARAM_STR);
            $stmt->bindValue('column_name', $hosting_column[1], PDO::PARAM_STR);
            $stmt->execute();
            $hosting_column_exists = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($hosting_column_exists === 0) $pdo->query("ALTER TABLE `" . $hosting_column[0] . "` " . $hosting_column[2]);

        }

        // Pre-release builds had two user_settings columns this release doesn't keep (the page size under the services'
        // old name, and a Main Domain column the list no longer has). A stopped upgrade of such a build can have left
        // them, so they're dropped when they're there.
        foreach (array('number_of_hosting_accounts', 'display_hosting_domain') as $retired_column) {

            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', 'user_settings', PDO::PARAM_STR);
            $stmt->bindValue('column_name', $retired_column, PDO::PARAM_STR);
            $stmt->execute();
            $retired_column_exists = (int) $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($retired_column_exists !== 0) $pdo->query("ALTER TABLE `user_settings` DROP `" . $retired_column . "`");

        }
        // ===== END: Hosting, schema =====

        // ===== BEGIN: Hosting, domains, schema (HOSTING-RESTRUCTURE-PLAN.local.md, hosting-domains build) =====
        /*
         * Each domain can now use one hosting service (domains.service_id, 0 for none), which replaces the domain's old
         * hosting provider (domains.hosting_id, dropped with its links, as the release notes and the upgrade notice say).
         * Each step is checked in INFORMATION_SCHEMA first, so a stopped upgrade carries on, and the parts still needed
         * go into one ALTER TABLE, so a large table is rebuilt once. The index on service_id is added unless a usable
         * index (one that isn't ignored on MariaDB or invisible on MySQL) has service_id as its only column, since a
         * service delete's keyset pages need a service's domains in id order (one with more columns, such as
         * (service_id, expiry_date), doesn't give that). It's named service_id, or service_id_hosting (then
         * service_id_hosting_2, and so on) when an index on other columns already has that name.
         */
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = :table_name
              AND `COLUMN_NAME` = :column_name");
        $domain_column_exists = function ($table_name, $column_name) use ($stmt, $dbname) {
            $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
            $stmt->bindValue('table_name', $table_name, PDO::PARAM_STR);
            $stmt->bindValue('column_name', $column_name, PDO::PARAM_STR);
            $stmt->execute();
            $exists = (int) $stmt->fetchColumn() !== 0;
            $stmt->closeCursor();
            return $exists;
        };

        $domain_changes = array();
        if (!$domain_column_exists('domains', 'service_id')) {
            $domain_changes[] = "ADD `service_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `ip_id`";
        }

        $stmt_indexes = $pdo->prepare("
            SELECT *
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'domains'");
        $stmt_indexes->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $stmt_indexes->execute();
        $domain_indexes = array();
        $domain_index_usable = array();
        foreach ($stmt_indexes->fetchAll(PDO::FETCH_ASSOC) as $index_row) {
            $index_row = array_change_key_case($index_row, CASE_LOWER);
            $index_key = strtolower($index_row['index_name']);
            $domain_indexes[$index_key][(int) $index_row['seq_in_index']] = strtolower($index_row['column_name']);
            $domain_index_usable[$index_key] = ($domain_index_usable[$index_key] ?? true)
                && strtoupper((string) ($index_row['ignored'] ?? 'NO')) !== 'YES'
                && strtoupper((string) ($index_row['is_visible'] ?? 'YES')) !== 'NO';
        }
        $stmt_indexes->closeCursor();

        $service_index_found = false;
        foreach ($domain_indexes as $index_key => $columns_in_index) {
            ksort($columns_in_index);
            if ($columns_in_index === array(1 => 'service_id') && $domain_index_usable[$index_key]) $service_index_found = true;
        }
        if (!$service_index_found) {
            $index_name = 'service_id';
            for ($suffix = 1; isset($domain_indexes[$index_name]); $suffix++) {
                $index_name = ($suffix === 1) ? 'service_id_hosting' : 'service_id_hosting_' . $suffix;
            }
            $domain_changes[] = "ADD INDEX `" . $index_name . "` (`service_id`)";
        }

        if ($domain_column_exists('domains', 'hosting_id')) $domain_changes[] = "DROP `hosting_id`";

        if ($domain_changes) $pdo->query("ALTER TABLE `domains` " . implode(", ", $domain_changes));

        foreach (array('domain_queue', 'domain_queue_history') as $queue_table) {

            if ($domain_column_exists($queue_table, 'hosting_id')) {
                $pdo->query("ALTER TABLE `" . $queue_table . "` DROP `hosting_id`");
            }

        }
        // ===== END: Hosting, domains, schema =====

        // ===== BEGIN: Scheduled refresh, schema (SCHEDULED-REFRESH-HISTORY-PLAN.local.md, scheduled-refresh build) =====
        /*
         * The Refresh Domains task's schedule, one row per domain (DomainMOD\DomainRefresh::tableSql()). This block only
         * runs on a database at 4.26.0, which can hold this table only from a failed earlier attempt or a restore over a
         * newer install, and the table holds only schedule state, so it's dropped and created empty.
         */
        $pdo->query("DROP TABLE IF EXISTS `domain_refresh_status`");
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_refresh_status` (
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_turn` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_success` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `next_due` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `hold_until` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `failures` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
            `last_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_result` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `public_next` TINYINT(1) NOT NULL DEFAULT '0',
            `revision` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            PRIMARY KEY  (`domain_id`),
            KEY `next_due` (`next_due`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
        // ===== END: Scheduled refresh, schema =====

        // ===== BEGIN: Live DNS, schema (LIVE-DNS-IP-PLAN.local.md, live-dns build) =====
        /*
         * Live DNS: each domain's last live name servers, addresses, and mail servers (DomainMOD\LiveDns::tableSql()).
         * This block only runs on a database at 4.26.0, which can hold this table only from a failed earlier attempt or a
         * restore over a newer install, and the table holds only observations the next Refresh rebuilds, so it's dropped
         * and created empty: every domain starts Not checked. Then the Live DNS column's Display Setting,
         * user_settings.display_domain_live_dns (off), added only when it's missing, so a retried upgrade finishes.
         */
        $pdo->query("DROP TABLE IF EXISTS `domain_dns_check`");
        $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_dns_check` (
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `checked_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `started_us` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
            `result` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `ns_state` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `ip_state` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `list_state` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'failed',
            `name_servers` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `a` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `aaaa` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `www_a` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `www_aaaa` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `mx` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `seen_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `stale_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `truncated_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `retry_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_ok_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `first_set` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `ip_basis` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `history_ns` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `history_ips` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `history_seen` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            PRIMARY KEY  (`domain_id`),
            KEY `list_state` (`list_state`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

        $live_dns_column_check = $pdo->prepare("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = :dbname
              AND `TABLE_NAME` = 'user_settings'
              AND `COLUMN_NAME` = 'display_domain_live_dns'");
        $live_dns_column_check->bindValue('dbname', $dbname, PDO::PARAM_STR);
        $live_dns_column_check->execute();
        $display_live_dns_exists = (int) $live_dns_column_check->fetchColumn();
        $live_dns_column_check->closeCursor();

        if ($display_live_dns_exists === 0) {

            $pdo->query("
                ALTER TABLE `user_settings`
                ADD `display_domain_live_dns` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_domain_lock`");

        }
        // ===== END: Live DNS, schema =====

        $pdo->beginTransaction();

        // The SSL Queue's scheduled task, unless a row with its slug is already there
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM scheduler
            WHERE slug = 'ssl-queue'");
        $stmt->execute();
        $ssl_queue_task_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($ssl_queue_task_exists === 0) {

            $schedule = new DomainMOD\Scheduler();

            $stmt = $pdo->prepare("
                INSERT INTO scheduler
                (`name`, description, `interval`, expression, slug, sort_order, is_running, active, `timezone`, next_run,
                 insert_time)
                 VALUES
                ('SSL Queue Processing', 'Reads the certificates of the sites in the SSL Queue and adds them to DomainMOD.',
                 'Every 5 Minutes', '*/5 * * * *', 'ssl-queue', '15', '0', '1', 'UTC', :next_run, :insert_time)");
            $stmt->bindValue('next_run', $schedule->getNextRun('*/5 * * * *', 'UTC'), PDO::PARAM_STR);
            $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
            $stmt->execute();

        }

        /*
         * Domains and SSL certificates without a custom field row get one, as in the 4.26.0 update, since older versions
         * could still leave it out (a Domain Queue run or an add page that stopped between the two inserts), and then the
         * asset's custom field edits aren't saved. Assets that already have one are left alone. NOT IN is used for the same reason as there: it needs no index on the
         * asset's id, and the subquery leaves out NULL ids, which would otherwise make NOT IN match nothing.
         */
        $stmt = $pdo->prepare("
            INSERT INTO domain_field_data
            (domain_id, insert_time)
            SELECT d.id, :insert_time
            FROM domains AS d
            WHERE d.id NOT IN (SELECT domain_id FROM domain_field_data WHERE domain_id IS NOT NULL)");
        $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdo->prepare("
            INSERT INTO ssl_cert_field_data
            (ssl_id, insert_time)
            SELECT sc.id, :insert_time
            FROM ssl_certs AS sc
            WHERE sc.id NOT IN (SELECT ssl_id FROM ssl_cert_field_data WHERE ssl_id IS NOT NULL)");
        $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();

        /*
         * A random two-factor stamp for every user, so every session from before the upgrade (which has none) ends,
         * and every session after it has one (see System::authCheckResult()). This runs in the upgrading admin's own
         * checks.php request, so their session gets the exact stamp written for them, and they stay signed in.
         */
        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            FOR UPDATE");
        $stmt->execute();
        $stamp_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        $stmt = $pdo->prepare("
            UPDATE users
            SET two_factor_stamp = :stamp
            WHERE id = :user_id");

        foreach ($stamp_user_ids as $stamp_user_id) {

            $stamp = bin2hex(random_bytes(16));
            $stmt->bindValue('stamp', $stamp, PDO::PARAM_STR);
            $stmt->bindValue('user_id', $stamp_user_id, PDO::PARAM_INT);
            $stmt->execute();
            if ((int) $stamp_user_id === (int) ($_SESSION['s_user_id'] ?? 0)) $upgrading_user_stamp = $stamp;

        }

        /*
         * Certificates whose domain an older version deleted (or that point at a missing domain for any other reason)
         * now simply have no domain, so they're set to 0. Their update_time is left alone, since nothing a user set
         * changed.
         */
        $pdo->query("
            UPDATE ssl_certs AS sslc
            LEFT JOIN domains AS d ON d.id = sslc.domain_id
            SET sslc.domain_id = 0
            WHERE sslc.domain_id <> 0
              AND d.id IS NULL");

        // ===== BEGIN: Dynadot REST, notes (DYNADOT-REST-PLAN.local.md, dynadot-rest build) =====
        // Dynadot's RESTful API needs the API Secret too, and accounts without one stay on the legacy API until it's
        // retired, so the secret stays optional for now
        $stmt = $pdo->prepare("
            UPDATE api_registrars
            SET notes = :notes,
                update_time = :update_time
            WHERE `name` = 'Dynadot'");
        $stmt->bindValue('notes', 'API Key and API Secret: both are under Tools > API in your Dynadot account. The same API Key works for both of Dynadot\'s APIs, so keep a saved API Key as it is and add the API Secret to this registrar account. With an API Secret saved, DomainMOD tries Dynadot\'s current API first. Without one, it uses Dynadot\'s legacy API, which Dynadot plans to retire, so add the secret soon. If you restrict API access by IP address at Dynadot, add this server\'s IP address to the allowed list. If a large list of domains doesn\'t finish importing, set up your cron job to run cron.php with PHP\'s command-line version, or paste your domains into the <strong>Domains to add</strong> field.', PDO::PARAM_STR);
        $stmt->bindValue('update_time', $time->stamp(), PDO::PARAM_STR);
        $stmt->execute();
        // ===== END: Dynadot REST, notes =====

        // ===== BEGIN: Watch list, task (DOMAIN-WATCH-LIST-PLAN.local.md, watch-list build) =====
        // The Check Watch List task, unless a row with its slug is already there
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM scheduler
            WHERE slug = 'watch-list'");
        $stmt->execute();
        $watch_list_task_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($watch_list_task_exists === 0) {

            $schedule = new DomainMOD\Scheduler();

            $stmt = $pdo->prepare("
                INSERT INTO scheduler
                (`name`, description, `interval`, expression, slug, sort_order, is_running, active, `timezone`, next_run,
                 insert_time)
                 VALUES
                ('Check Watch List', 'Checks the domains on the Watch List with public RDAP and WHOIS servers, a few at a time: weekly while a domain is far from expiry, daily near and after expiry, and more often once deletion is pending.',
                 'Every 10 Minutes', '*/10 * * * *', 'watch-list', '130', '0', '1', 'UTC', :next_run, :insert_time)");
            $stmt->bindValue('next_run', $schedule->getNextRun('*/10 * * * *', 'UTC'), PDO::PARAM_STR);
            $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
            $stmt->execute();

        }
        // ===== END: Watch list, task =====

        // ===== BEGIN: Hosting, seeds (WEB-HOSTING-SECTION-PLAN.local.md and HOSTING-RESTRUCTURE-PLAN.local.md, web-hosting build) =====
        // The seed service types (the tables were created empty above, so these are the only rows)
        $creation_type_id_installation = $system->getCreationTypeId('Installation');

        $stmt = $pdo->prepare("
            INSERT INTO hosting_types
            (`type`, notes, creation_type_id, insert_time)
            VALUES
            (:type, '', :creation_type_id, :insert_time)");

        foreach (array('Shared', 'Reseller', 'VPS', 'Dedicated', 'Cloud', 'DNS', 'Email', 'Other') as $hosting_type) {

            $stmt->bindValue('type', $hosting_type, PDO::PARAM_STR);
            $stmt->bindValue('creation_type_id', $creation_type_id_installation, PDO::PARAM_INT);
            $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
            $stmt->execute();

        }

        /*
         * An earlier draft of this block added an hourly Advance Hosting Renewal Dates task, which 4.27.0 doesn't have (a
         * service whose renewal date passes now waits for the user to confirm it, see DomainMOD\Hosting::renewalState()).
         * Only a pre-release test database or a stopped run of that draft can have its row, and it's deleted, as the
         * other pre-release leftovers are. On a real 4.26.0 database there's nothing to delete.
         */
        $pdo->query("
            DELETE FROM scheduler
            WHERE slug = 'hosting-auto-renew'");
        // ===== END: Hosting, seeds =====

        // ===== BEGIN: Scheduled refresh, task (SCHEDULED-REFRESH-HISTORY-PLAN.local.md, scheduled-refresh build) =====
        // The Refresh Domains task, off (an administrator turns it on), unless a row with its slug is already there. A
        // row that's there can only have come from a restore of an older backup over a newer install, so it's turned
        // off, and an administrator turns it on for the restored portfolio.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM scheduler
            WHERE slug = 'domain-refresh'");
        $stmt->execute();
        $domain_refresh_task_exists = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($domain_refresh_task_exists === 0) {

            $schedule = new DomainMOD\Scheduler();

            $stmt = $pdo->prepare("
                INSERT INTO scheduler
                (`name`, description, `interval`, expression, slug, sort_order, is_running, active, `timezone`, next_run,
                 insert_time)
                 VALUES
                ('Refresh Domains', :description, 'Every 10 Minutes', '*/10 * * * *', 'domain-refresh', '120', '0', '0',
                 'UTC', :next_run, :insert_time)");
            $stmt->bindValue('description', DomainMOD\DomainRefresh::TASK_DESCRIPTION, PDO::PARAM_STR);
            $stmt->bindValue('next_run', $schedule->getNextRun('*/10 * * * *', 'UTC'), PDO::PARAM_STR);
            $stmt->bindValue('insert_time', $time->stamp(), PDO::PARAM_STR);
            $stmt->execute();

        } else {

            $pdo->query("UPDATE scheduler SET active = '0' WHERE slug = 'domain-refresh'");

        }
        // ===== END: Scheduled refresh, task =====

        // ===== BEGIN: Hosting, domains, data (HOSTING-RESTRUCTURE-PLAN.local.md, hosting-domains build) =====
        /*
         * The services table was created empty above, so any domain already linked to a service is a leftover (from a
         * pre-release test database or a stopped run), and keeping it would attach the domain to whichever new service
         * later gets the same id. The same goes for the hosting defaults, since the types and accounts tables were just
         * recreated. On a real 4.26.0 database these columns are new and already 0, so nothing changes.
         */
        $pdo->query("UPDATE domains SET service_id = 0 WHERE service_id <> 0");
        foreach (array('user_settings', 'settings') as $defaults_table) {
            $pdo->query("
                UPDATE `" . $defaults_table . "`
                SET default_hosting_type = 0,
                    default_hosting_provider_account = 0
                WHERE default_hosting_type <> 0
                   OR default_hosting_provider_account <> 0");
        }

        /*
         * The placeholder providers DomainMOD made itself (DomainMOD\Notice::PLACEHOLDER_PROVIDERS, the same list the
         * upgrade notice's count uses): [no hosting], which the installer added in its language (so every translation
         * DomainMOD ships is listed), and [created by queue], which the Domain Queue always added in English. A row matches only with both its exact name (compared byte for byte, so a provider renamed to
         * [No Hosting] is kept) and DomainMOD's own creation type, so a provider someone added or renamed by hand is
         * never touched. A matching row is kept while any hosting account or service uses it, so nothing is orphaned.
         * Then any default hosting provider that pointed at a deleted row is set to 0. Running this again finds nothing.
         */
        $placeholder_names = array();
        foreach (DomainMOD\Notice::PLACEHOLDER_PROVIDERS as $placeholder_type_name => $names) {
            $placeholder_names[$system->getCreationTypeId($placeholder_type_name)] = $names;
        }

        $stmt_find = $pdo->prepare("
            SELECT h.id
            FROM hosting AS h
            WHERE BINARY h.name = :name
              AND h.creation_type_id = :creation_type_id
              AND NOT EXISTS (SELECT 1 FROM hosting_provider_accounts AS hpa WHERE hpa.hosting_id = h.id)
              AND NOT EXISTS (SELECT 1 FROM hosting_services AS hs WHERE hs.hosting_id = h.id)");
        $placeholder_ids = array();
        foreach ($placeholder_names as $placeholder_type => $names) {
            foreach ($names as $placeholder_name) {
                $stmt_find->bindValue('name', $placeholder_name, PDO::PARAM_STR);
                $stmt_find->bindValue('creation_type_id', (int) $placeholder_type, PDO::PARAM_INT);
                $stmt_find->execute();
                foreach ($stmt_find->fetchAll(PDO::FETCH_COLUMN) as $placeholder_id) {
                    $placeholder_ids[(int) $placeholder_id] = (int) $placeholder_id;
                }
            }
        }

        if ($placeholder_ids) {
            $placeholder_list = implode(', ', $placeholder_ids);
            $pdo->query("
                DELETE FROM hosting
                WHERE id IN (" . $placeholder_list . ")
                  AND NOT EXISTS (SELECT 1 FROM hosting_provider_accounts AS hpa WHERE hpa.hosting_id = hosting.id)
                  AND NOT EXISTS (SELECT 1 FROM hosting_services AS hs WHERE hs.hosting_id = hosting.id)");
            $pdo->query("UPDATE user_settings SET default_host = 0 WHERE default_host IN (" . $placeholder_list . ")
                           AND default_host NOT IN (SELECT id FROM hosting)");
            $pdo->query("UPDATE settings SET default_host = 0 WHERE default_host IN (" . $placeholder_list . ")
                           AND default_host NOT IN (SELECT id FROM hosting)");
        }
        // ===== END: Hosting, domains, data =====

        // ===== BEGIN: Change History, start marker (CHANGE-HISTORY-PLAN.local.md, change-history build) =====
        // The Change History's start marker, after the data repairs above (so they aren't recorded) and in the same
        // transaction as the new database version, so the history turns on only once the upgrade has finished
        DomainMOD\ChangeHistory::writeStartMarker($pdo);
        // ===== END: Change History, start marker =====

        /*
         * This needs to be MOVED from the last version to the newest version with every release
         */
        $goal->upgrade($previous_version);

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

        if (isset($upgrading_user_stamp)) {

            $_SESSION['s_two_factor_stamp'] = $upgrading_user_stamp;
            $_SESSION['s_two_factor_verified'] = 0;

        }

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on
