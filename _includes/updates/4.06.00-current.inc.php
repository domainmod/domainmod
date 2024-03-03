<?php
/**
 * /_includes/updates/4.06.00-current.inc.php
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

// upgrade database from 4.06.00 to 4.06.01
if ($current_db_version === '4.06.00') {

    $old_version = '4.06.00';
    $new_version = '4.06.01';

    try {

        $pdo->beginTransaction();
        $upgrade->database($new_version);
        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.06.01 to 4.07.00
if ($current_db_version === '4.06.01') {

    $old_version = '4.06.01';
    $new_version = '4.07.00';

    try {

        $pdo->beginTransaction();
        $upgrade->database($new_version);
        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.07.00 to 4.08.00
if ($current_db_version === '4.07.00') {

    $old_version = '4.07.00';
    $new_version = '4.08.00';

    try {

        $pdo->beginTransaction();
        $upgrade->database($new_version);
        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.08.00 to 4.09.00
if ($current_db_version === '4.08.00') {

    $old_version = '4.08.00';
    $new_version = '4.09.00';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            ALTER TABLE `domain_queue`
            ADD `invalid_domain` TINYINT(1) NOT NULL DEFAULT '0' AFTER `already_in_queue`");

        $pdo->query("
            UPDATE api_registrars
            SET notes = 'DreamHost does not currently allow the WHOIS privacy status of a domain to be retrieved via their API, so all domains added to the Domain Queue from a DreamHost account will have their WHOIS privacy status set to No by default.'
            WHERE name = 'DreamHost'");

        $pdo->query("
            UPDATE api_registrars
            SET notes = 'When retrieving your list of domains from GoDaddy, the current limit is 1,000 domains. If you have more than this you should export the full list of domains from GoDaddy and paste it into the <strong>Domains to add</strong> field when adding domains via the Domain Queue.'
            WHERE name = 'GoDaddy'");

        $pdo->query("
            UPDATE api_registrars
            SET notes = 'NameSilo\'s domains have 6 possible statuses: Active, Expired (grace period), Expired (restore period), Expired (pending delete), Inactive, and Pending Outbound Transfer<BR><BR>When retrieving your list of domains via the API, <STRONG>Inactive</STRONG> domains are not returned.<BR><BR>When retrieving the details of a specific domain via the API, <STRONG>Inactive</STRONG> and <STRONG>Expired (pending delete)</STRONG> domains will not return any data.'
            WHERE name = 'NameSilo'");

        $pdo->query("
            UPDATE api_registrars
            SET notes = 'ResellerClub does not allow users to retrieve a list of their domains via the API, nor do they return the Auto Renewal status when retrieving the details of a domain. All domains imported via the API will have their Auto Renewal status set to No by default.'
            WHERE name = 'ResellerClub'");

        $upgrade->database($new_version);
        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.09.00 to 4.09.01
if ($current_db_version === '4.09.00') {

    $old_version = '4.09.00';
    $new_version = '4.09.01';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.09.01 to 4.09.02
if ($current_db_version === '4.09.01') {

    $old_version = '4.09.01';
    $new_version = '4.09.02';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.09.02 to 4.09.03
if ($current_db_version === '4.09.02') {

    $old_version = '4.09.02';
    $new_version = '4.09.03';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.09.03 to 4.10.0
if ($current_db_version === '4.09.03') {

    $old_version = '4.09.03';
    $new_version = '4.10.0';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.10.0 to 4.11.0
if ($current_db_version === '4.10.0') {

    $old_version = '4.10.0';
    $new_version = '4.11.0';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.11.0 to 4.11.01
if ($current_db_version === '4.11.0') {

    $old_version = '4.11.0';
    $new_version = '4.11.01';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.11.01 to 4.12.0
if ($current_db_version === '4.11.01') {

    $old_version = '4.11.01';
    $new_version = '4.12.0';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.12.0 to 4.13.0
if ($current_db_version === '4.12.0') {

    $old_version = '4.12.0';
    $new_version = '4.13.0';

    try {

        $pdo->beginTransaction();

        $result = $pdo->query("SELECT `field_name` FROM domain_fields")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `user_settings`
                    ADD `dispcdf_" . $row->field_name . "` TINYINT(1) NOT NULL DEFAULT '0'");

            }

        }

        $result = $pdo->query("SELECT `field_name` FROM ssl_cert_fields")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `user_settings`
                    ADD `dispcsf_" . $row->field_name . "` TINYINT(1) NOT NULL DEFAULT '0'");

            }

        }

        $pdo->query("
            ALTER TABLE `creation_types`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `creation_types`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `users`
            ALTER `last_login` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `users`
            SET `last_login` = '1970-01-01 00:00:00'
            WHERE `last_login` = '1978-01-23 00:00:00'
              OR `last_login` = '0000-00-00 00:00:00'
              OR `last_login` IS NULL");

        $pdo->query("
            UPDATE `users`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `users`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `user_settings`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `user_settings`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `user_settings`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `categories`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `categories`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `categories`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `hosting`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `hosting`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `hosting`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `owners`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `owners`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `owners`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `currencies`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `currencies`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `currencies`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `currency_conversions`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `currency_conversions`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `currency_conversions`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `fees`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `fees`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `fees`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_fees`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_fees`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_fees`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domains`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domains`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `domains`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_queue`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue_history`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_queue_history`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue_list`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_queue_list`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue_list_history`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_queue_list_history`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `custom_field_types`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `custom_field_types`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `custom_field_types`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_fields`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_fields`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `domain_fields`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_field_data`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `domain_field_data`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `domain_field_data`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_certs`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_certs`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_certs`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_cert_types`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_cert_types`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_cert_types`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_cert_fields`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_cert_fields`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_cert_fields`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_cert_field_data`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_cert_field_data`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_cert_field_data`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `dns`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `dns`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `dns`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `registrars`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `registrars`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `registrars`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `registrar_accounts`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `registrar_accounts`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `registrar_accounts`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_providers`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_providers`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_providers`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_accounts`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ssl_accounts`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ssl_accounts`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `segments`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `segments`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `segments`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `segment_data`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `segment_data`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `segment_data`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `ip_addresses`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `ip_addresses`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `ip_addresses`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `timezones`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `timezones`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `dw_servers`
            ALTER `build_start_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `build_end_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `build_start_time_overall` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `build_end_time_overall` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `dw_servers`
            SET `build_start_time` = '1970-01-01 00:00:00'
            WHERE `build_start_time` = '1978-01-23 00:00:00'
              OR `build_start_time` = '0000-00-00 00:00:00'
              OR `build_start_time` IS NULL");

        $pdo->query("
            UPDATE `dw_servers`
            SET `build_end_time` = '1970-01-01 00:00:00'
            WHERE `build_end_time` = '1978-01-23 00:00:00'
              OR `build_end_time` = '0000-00-00 00:00:00'
              OR `build_end_time` IS NULL");

        $pdo->query("
            UPDATE `dw_servers`
            SET `build_start_time_overall` = '1970-01-01 00:00:00'
            WHERE `build_start_time_overall` = '1978-01-23 00:00:00'
              OR `build_start_time_overall` = '0000-00-00 00:00:00'
              OR `build_start_time_overall` IS NULL");

        $pdo->query("
            UPDATE `dw_servers`
            SET `build_end_time_overall` = '1970-01-01 00:00:00'
            WHERE `build_end_time_overall` = '1978-01-23 00:00:00'
              OR `build_end_time_overall` = '0000-00-00 00:00:00'
              OR `build_end_time_overall` IS NULL");

        $pdo->query("
            UPDATE `dw_servers`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `dw_servers`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `scheduler`
            ALTER `last_run` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `next_run` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `scheduler`
            SET `last_run` = '1970-01-01 00:00:00'
            WHERE `last_run` = '1978-01-23 00:00:00'
              OR `last_run` = '0000-00-00 00:00:00'
              OR `last_run` IS NULL");

        $pdo->query("
            UPDATE `scheduler`
            SET `next_run` = '1970-01-01 00:00:00'
            WHERE `next_run` = '1978-01-23 00:00:00'
              OR `next_run` = '0000-00-00 00:00:00'
              OR `next_run` IS NULL");

        $pdo->query("
            UPDATE `scheduler`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `scheduler`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `api_registrars`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `api_registrars`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `api_registrars`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `goal_activity`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `goal_activity`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `goal_activity`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `log`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `log`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            ALTER TABLE `settings`
            ALTER `insert_time` SET DEFAULT '1970-01-01 00:00:00',
            ALTER `update_time` SET DEFAULT '1970-01-01 00:00:00'");

        $pdo->query("
            UPDATE `settings`
            SET `insert_time` = '1970-01-01 00:00:00'
            WHERE `insert_time` = '1978-01-23 00:00:00'
              OR `insert_time` = '0000-00-00 00:00:00'
              OR `insert_time` IS NULL");

        $pdo->query("
            UPDATE `settings`
            SET `update_time` = '1970-01-01 00:00:00'
            WHERE `update_time` = '1978-01-23 00:00:00'
              OR `update_time` = '0000-00-00 00:00:00'
              OR `update_time` IS NULL");

        $pdo->query("
            ALTER TABLE `domains`
            ALTER `expiry_date` SET DEFAULT '1970-01-01'");

        $pdo->query("
            UPDATE `domains`
            SET `expiry_date` = '1970-01-01'
            WHERE `expiry_date` = '1978-01-23'
              OR `expiry_date` = '0000-00-00'
              OR `expiry_date` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue`
            ALTER `expiry_date` SET DEFAULT '1970-01-01'");

        $pdo->query("
            UPDATE `domain_queue`
            SET `expiry_date` = '1970-01-01'
            WHERE `expiry_date` = '1978-01-23'
              OR `expiry_date` = '0000-00-00'
              OR `expiry_date` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue_history`
            ALTER `expiry_date` SET DEFAULT '1970-01-01'");

        $pdo->query("
            UPDATE `domain_queue_history`
            SET `expiry_date` = '1970-01-01'
            WHERE `expiry_date` = '1978-01-23'
              OR `expiry_date` = '0000-00-00'
              OR `expiry_date` IS NULL");

        $pdo->query("
            ALTER TABLE `domain_queue_temp`
            ALTER `expiry_date` SET DEFAULT '1970-01-01'");

        $pdo->query("
            UPDATE `domain_queue_temp`
            SET `expiry_date` = '1970-01-01'
            WHERE `expiry_date` = '1978-01-23'
              OR `expiry_date` = '0000-00-00'
              OR `expiry_date` IS NULL");

        $pdo->query("
            ALTER TABLE `ssl_certs`
            ALTER `expiry_date` SET DEFAULT '1970-01-01'");

        $pdo->query("
            UPDATE `ssl_certs`
            SET `expiry_date` = '1970-01-01'
            WHERE `expiry_date` = '1978-01-23'
              OR `expiry_date` = '0000-00-00'
              OR `expiry_date` IS NULL");

        $result = $pdo->query("
            SELECT `COLUMN_NAME` AS field_name
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = '" . $dbname . "'
              AND `TABLE_NAME` = 'domain_field_data'
              AND `COLUMN_TYPE` = 'datetime'")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `domain_field_data`
                    ALTER `" . $row->field_name . "` SET DEFAULT '1970-01-01 00:00:00'");

                $pdo->query("
                    UPDATE `domain_field_data`
                    SET `" . $row->field_name . "` = '1970-01-01 00:00:00'
                    WHERE `" . $row->field_name . "` = '1978-01-23 00:00:00'
                      OR `" . $row->field_name . "` = '0000-00-00 00:00:00'
                      OR `" . $row->field_name . "` IS NULL");

            }

        }

        $result = $pdo->query("
            SELECT `COLUMN_NAME` AS field_name
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = '" . $dbname . "'
              AND `TABLE_NAME` = 'ssl_cert_field_data'
              AND `COLUMN_TYPE` = 'datetime'")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `ssl_cert_field_data`
                    ALTER `" . $row->field_name . "` SET DEFAULT '1970-01-01 00:00:00'");

                $pdo->query("
                    UPDATE `ssl_cert_field_data`
                    SET `" . $row->field_name . "` = '1970-01-01 00:00:00'
                    WHERE `" . $row->field_name . "` = '1978-01-23 00:00:00'
                      OR `" . $row->field_name . "` = '0000-00-00 00:00:00'
                      OR `" . $row->field_name . "` IS NULL");

            }

        }

        $result = $pdo->query("
            SELECT `COLUMN_NAME` AS field_name
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = '" . $dbname . "'
              AND `TABLE_NAME` = 'domain_field_data'
              AND `COLUMN_TYPE` = 'date'")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `domain_field_data`
                    ALTER `" . $row->field_name . "` SET DEFAULT '1970-01-01'");

                $pdo->query("
                    UPDATE `domain_field_data`
                    SET `" . $row->field_name . "` = '1970-01-01'
                    WHERE `" . $row->field_name . "` = '1978-01-23'
                      OR `" . $row->field_name . "` = '0000-00-00'
                      OR `" . $row->field_name . "` IS NULL");

            }

        }

        $result = $pdo->query("
            SELECT `COLUMN_NAME` AS field_name
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = '" . $dbname . "'
              AND `TABLE_NAME` = 'ssl_cert_field_data'
              AND `COLUMN_TYPE` = 'date'")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                $pdo->query("
                    ALTER TABLE `ssl_cert_field_data`
                    ALTER `" . $row->field_name . "` SET DEFAULT '1970-01-01'");

                $pdo->query("
                    UPDATE `ssl_cert_field_data`
                    SET `" . $row->field_name . "` = '1970-01-01'
                    WHERE `" . $row->field_name . "` = '1978-01-23'
                      OR `" . $row->field_name . "` = '0000-00-00'
                      OR `" . $row->field_name . "` IS NULL");

            }

        }

        $pdo->query("
            ALTER TABLE `settings`
            ADD `currency_converter` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'fcca' AFTER `expiration_days`");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.13.0 to 4.14.0
if ($current_db_version === '4.13.0') {

    $old_version = '4.13.0';
    $new_version = '4.14.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            ALTER TABLE `settings`
            CHANGE `currency_converter` `currency_converter` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'era'");

        $pdo->query("
            UPDATE `settings`
            SET currency_converter = 'era'");

        $pdo->query("
            ALTER TABLE `settings`
            ADD `email_signature` INT(10) UNSIGNED NOT NULL DEFAULT '1' AFTER `expiration_days`");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.14.0 to 4.15.0
if ($current_db_version === '4.14.0') {

    $old_version = '4.14.0';
    $new_version = '4.15.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("UPDATE registrars SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE registrar_accounts SET username = 'n/a' WHERE username = '' OR username IS NULL");
        $pdo->query("UPDATE dns SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE dns SET dns1 = 'n/a' WHERE dns1 = '' OR dns1 IS NULL");
        $pdo->query("UPDATE dns SET dns2 = 'n/a' WHERE dns2 = '' OR dns2 IS NULL");
        $pdo->query("UPDATE hosting SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE ssl_providers SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE ssl_accounts SET username = 'n/a' WHERE username = '' OR username IS NULL");
        $pdo->query("UPDATE ssl_cert_types SET type = 'n/a' WHERE type = '' OR type IS NULL");
        $pdo->query("UPDATE owners SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE categories SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE ip_addresses SET name = 'n/a' WHERE name = '' OR name IS NULL");
        $pdo->query("UPDATE ip_addresses SET ip = 'n/a' WHERE ip = '' OR ip IS NULL");

        $pdo->query("
            INSERT INTO `api_registrars`
            (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
             req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
             ret_autorenewal_status, notes, insert_time)
             VALUES
            ('Gandi', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '0', '1', 'Gandi does not currently allow the WHOIS privacy status of a domain to be retrieved via their API, so all domains added to the Domain Queue from a Gandi account will have their WHOIS privacy status set to No by default.', '" . $timestamp . "')");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

// upgrade database from 4.15.0 to 4.16.0
if ($current_db_version === '4.15.0') {

    $old_version = '4.15.0';
    $new_version = '4.16.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            CREATE TABLE IF NOT EXISTS `languages` (
                `id` INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `language` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

        $pdo->query("
            INSERT INTO `languages`
            (`name`, language, insert_time)
             VALUES
            ('English (Canada)', 'en_CA.UTF-8', '" . $timestamp . "'),
            ('English (United States)', 'en_US.UTF-8', '" . $timestamp . "'),
            ('German', 'de_DE.UTF-8', '" . $timestamp . "'),
            ('Spanish', 'es_ES.UTF-8', '" . $timestamp . "'),
            ('French', 'fr_FR.UTF-8', '" . $timestamp . "'),
            ('Italian', 'it_IT.UTF-8', '" . $timestamp . "'),
            ('Dutch', 'nl_NL.UTF-8', '" . $timestamp . "'),
            ('Polish', 'pl_PL.UTF-8', '" . $timestamp . "'),
            ('Portuguese', 'pt_PT.UTF-8', '" . $timestamp . "'),
            ('Russian', 'ru_RU.UTF-8', '" . $timestamp . "')");

        $pdo->query("
            ALTER TABLE `user_settings`
            ADD `default_language` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'en_US.UTF-8' AFTER `user_id`");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.16.0 to 4.17.0
if ($current_db_version === '4.16.0') {

    $old_version = '4.16.0';
    $new_version = '4.17.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            INSERT INTO custom_field_types
            (id, `name`, insert_time)
            VALUES
            (6, 'URL', '" . $timestamp . "')");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.17.0 to 4.18.0
if ($current_db_version === '4.17.0') {

    $old_version = '4.17.0';
    $new_version = '4.18.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            ALTER TABLE `user_settings`
            ADD `dark_mode` TINYINT(1) NOT NULL DEFAULT '0' AFTER `display_dw_intro_page`");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.18.0 to 4.18.01
if ($current_db_version === '4.18.0') {

    $old_version = '4.18.0';
    $new_version = '4.18.01';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.18.01 to 4.19.0
if ($current_db_version === '4.18.01') {

    $old_version = '4.18.01';
    $new_version = '4.19.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            INSERT INTO creation_types
            (`name`, insert_time)
             VALUES
            ('CSV Importer', '" . $timestamp . "')");

        $pdo->query("
            ALTER TABLE `registrar_accounts`
            ADD `account_id` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `password`");

        $pdo->query("
            ALTER TABLE `api_registrars`
            ADD `req_account_id` TINYINT(1) NOT NULL DEFAULT '0' AFTER `req_account_password`");

        $pdo->query("
            INSERT INTO `api_registrars`
            (`name`, req_account_username, req_account_password, req_account_id, req_reseller_id, req_api_app_name,
             req_api_key, req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers,
             ret_privacy_status, ret_autorenewal_status, notes, insert_time)
             VALUES
            ('Cloudflare', '1', '0', '1', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "')");

        $pdo->query("
            ALTER TABLE `settings`
            CHANGE `currency_converter` `currency_converter` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'erh'");

        $pdo->query("
            UPDATE `settings`
            SET currency_converter = 'erh'");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.19.0 to 4.20.0
if ($current_db_version === '4.19.0') {

    $old_version = '4.19.0';
    $new_version = '4.20.0';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.0 to 4.20.01
if ($current_db_version === '4.20.0') {

    $old_version = '4.20.0';
    $new_version = '4.20.01';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.01 to 4.20.02
if ($current_db_version === '4.20.01') {

    $old_version = '4.20.01';
    $new_version = '4.20.02';

    try {

        $pdo->beginTransaction();

        $result = $pdo->query("SELECT id, expression FROM scheduler")->fetchAll();

        if ($result) {

            foreach ($result as $row) {

                if (substr_count($row->expression, " ") == 5) {

                    $new_expression = substr($row->expression, 0, -2);

                    $pdo->query("
                    UPDATE `scheduler`
                    SET `expression` = '" . $new_expression . "'
                    WHERE `id` = '" . $row->id . "'");

                }

            }

        }

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

// upgrade database from 4.20.02 to 4.20.03
if ($current_db_version === '4.20.02') {

    $old_version = '4.20.02';
    $new_version = '4.20.03';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.03 to 4.20.04
if ($current_db_version === '4.20.03') {

    $old_version = '4.20.03';
    $new_version = '4.20.04';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.04 to 4.20.05
if ($current_db_version === '4.20.04') {

    $old_version = '4.20.04';
    $new_version = '4.20.05';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.05 to 4.20.06
if ($current_db_version === '4.20.05') {

    $old_version = '4.20.05';
    $new_version = '4.20.06';

    try {

        $pdo->beginTransaction();

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.06 to 4.20.07
if ($current_db_version === '4.20.06') {

    $old_version = '4.20.06';
    $new_version = '4.20.07';

    try {

        $pdo->beginTransaction();

        $pdo->query("
            UPDATE settings
            SET currency_converter = 'fcra',
                update_time = '" . $timestamp . "'");

        $upgrade->database($new_version);

        if ($pdo->InTransaction()) $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

} //@formatter:on

// upgrade database from 4.20.07 to 4.21.0
if ($current_db_version === '4.20.07') {

    $old_version = '4.20.07';
    $new_version = '4.21.0';

    try {

        $pdo->beginTransaction();

        $pdo->query("
        INSERT INTO api_registrars
        (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
         req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
         ret_autorenewal_status, notes, insert_time)
         VALUES
        ('Porkbun', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', 'When retrieving your list of domains from Porkbun, the current limit is 1,000 domains. If you have more than this you should export the full list of domains from Porkbun and paste it into the <strong>Domains to add</strong> field when adding domains via the Domain Queue.', '" . $timestamp . "')");

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
