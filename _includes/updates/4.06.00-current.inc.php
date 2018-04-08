<?php
/**
 * /_includes/updates/4.06.00-current.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
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
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
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
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
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
        $pdo->commit();
        $current_db_version = $new_version;

    } catch (Exception $e) {

        $pdo->rollback();
        $upgrade->logFailedUpgrade($old_version, $new_version, $e);
        throw $e;

    }

}

//@formatter:on
