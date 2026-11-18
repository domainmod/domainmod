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

        /*
         * This needs to be MOVED from the last version to the newest version with every release
         */
        $goal->upgrade($previous_version);

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on
