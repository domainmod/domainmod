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
