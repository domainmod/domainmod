<?php
/**
 * /classes/DomainMOD/HostingLinkImport.php
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
//@formatter:off
namespace DomainMOD;

/*
 * The 4.26.0 to 4.27.0 database update's import of each domain's old Web Hosting Provider (domains.hosting_id) into
 * hosting services. For every provider and owner pair the eligible domains use, it adds one hosting account (username
 * Imported) and, under it, one service named after the provider, then links each of those domains to the service for
 * its provider and owner. A domain is eligible when its provider still exists and isn't one of DomainMOD's
 * placeholders (Notice::PLACEHOLDER_PROVIDERS), and its registrar account and that account's owner still exist, the
 * owner the Domains list shows. The upgrade notice's count (Notice::hostingLinkCount()) uses the same joins and
 * placeholder match, so it counts exactly the domains linked here.
 *
 * import() runs inside the update block's transaction, after the hosting tables were created empty and the Other
 * service type was seeded, so the accounts and services it adds are the only ones there, and a stopped run rolls
 * them back. It ends by setting every old link to 0, and the column itself is dropped after the commit
 * (dropLegacyColumn()).
 */
class HostingLinkImport
{
    // The imported accounts' username (the old data has no login, and the column can't be empty)
    const USERNAME = 'Imported';

    // The imported services' billing term, in months, and status (see renewalValues() for the renewal date)
    const BILLING_TERM = 12;
    const ACTIVE = Hosting::ACTIVE;

    const ACCOUNT_NOTES = 'Added by the 4.27.0 upgrade for the domains that used this hosting provider. This account\'s '
        . 'username, Imported, is a placeholder, so change it to its real username and add its email address.';

    const SERVICE_NOTES = 'Added by the 4.27.0 upgrade from the domains\' Web Hosting Provider field. This service\'s '
        . 'renewal date, billing term, and service type are placeholders. Update them to match your hosting plan '
        . 'before adding a fee, since a fee applies to every service with the same provider, service type, and '
        . 'billing term.';

    // The temporary table the link UPDATE looks each service up in (see serviceMap())
    const MAP_TABLE = 'hosting_link_import';
    // Errors that only mean a temporary table can't be made here, leaving the transaction as it was: MySQL and MariaDB's
    // access-denied errors (no CREATE TEMPORARY TABLES privilege), and MySQL's refusal inside a transaction when GTID
    // consistency is enforced with statement-based binary logging (1787 before MySQL 8.0.13, 3748 from it)
    const NO_TEMPORARY_TABLE_ERRORS = array(1044, 1142, 1227, 1787, 3748);
    // Seconds the column drop after the commit waits for another connection's lock on domains before giving up
    const DROP_LOCK_WAIT = 10;

    // The category the upgrade adds when there's none at all, in English, like the upgrade's seed types
    const CATEGORY_NAME = '[no category]';
    const CATEGORY_STAKEHOLDER = '[no stakeholder]';

    /*
     * The joins that make a domain (d) eligible: its provider (h), its registrar account (ra), and that account's owner
     * (o) all still exist. Domains with no provider (hosting_id 0) have no hosting row, so they're left out too.
     */
    const ELIGIBLE_JOINS = // Legacy: domains.hosting_id before 4.27.0
        "INNER JOIN hosting AS h ON h.id = d.hosting_id
        INNER JOIN registrar_accounts AS ra ON ra.id = d.account_id
        INNER JOIN owners AS o ON o.id = ra.owner_id";

    /*
     * The placeholder providers' match on the hosting table's alias $alias, for a WHERE clause: a provider is one of
     * DomainMOD's placeholders when its name is exactly one of Notice::PLACEHOLDER_PROVIDERS' names (compared byte for
     * byte, since the column's collation would treat [No Hosting] as [no hosting]) and its creation type is any
     * creation_types row with that list's name (so a database with two Installation rows still matches). Returns
     * array('sql' => the condition in parentheses, 'params' => its bound values by name), with nothing pasted into the
     * SQL. The upgrade notice's count, the update block's placeholder delete, and import() all use it, so they agree.
     */
    public static function placeholderMatch($alias = 'h')
    {
        $params = array();
        $matches = array();
        foreach (Notice::PLACEHOLDER_PROVIDERS as $type => $names) {
            $type_key = 'type' . count($matches);
            $params[$type_key] = $type;
            $name_keys = array();
            foreach ($names as $name) {
                $key = 'name' . count($params);
                $params[$key] = $name;
                $name_keys[] = ':' . $key;
            }
            $matches[] = "(" . $alias . ".creation_type_id IN (SELECT ct.id FROM creation_types AS ct WHERE ct.name = :"
                . $type_key . ") AND BINARY " . $alias . ".name IN (" . implode(', ', $name_keys) . "))";
        }

        return array('sql' => "(" . implode(' OR ', $matches) . ")", 'params' => $params);
    }

    /*
     * The imported services' renewal date and Renews On Day for the hosting date $today (Y-m-d, see
     * Hosting::hostingDate()): one billing term (a year) after it, on its day of the month, which is also the Renews On
     * Day, so 2028-02-29 gives 2029-02-28 with day 29. The date is a placeholder for the user to correct, as the
     * service's notes say.
     */
    public static function renewalValues($today)
    {
        $day = (int) substr((string) $today, 8, 2);
        return array('expiry_date' => (new Date())->addMonths((string) $today, self::BILLING_TERM, $day),
            'renewal_day' => $day);
    }

    // Whether domains still has its old hosting provider column
    public static function hasLegacyColumn(\PDO $pdo)
    {
        return (int) $pdo->query("
            SELECT COUNT(*)
            FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'domains'
              AND `COLUMN_NAME` = 'hosting_id'")->fetchColumn() > 0;
    }

    /*
     * Imports the links (see the class comment), only when domains still has the old column, and returns how many
     * hosting accounts it added. $creation_type_id is the Installation creation type the rows are made with (DomainMOD
     * made them, and there's no Upgrade type), $insert_time their insert time, and $today the hosting date the renewal
     * date is worked out from. Every insert is one INSERT ... SELECT with a fixed ORDER BY, so a large install takes
     * the same few statements, and the ids are always given out in the same order. Nothing is recorded in the Change
     * History, which starts after the upgrade.
     */
    public static function import(\PDO $pdo, $creation_type_id, $insert_time, $today)
    {
        if (!self::hasLegacyColumn($pdo)) return 0;

        $placeholder = self::placeholderMatch('h');
        $eligible = // Legacy: domains.hosting_id before 4.27.0
            "FROM domains AS d
            " . self::ELIGIBLE_JOINS . "
            WHERE d.hosting_id <> 0
              AND NOT " . $placeholder['sql'];

        // One account per provider and owner pair
        $stmt = $pdo->prepare( // Legacy: domains.hosting_id before 4.27.0
            "INSERT INTO hosting_provider_accounts
            (owner_id, hosting_id, email_address, username, reseller, reseller_id, notes, creation_type_id, created_by,
             insert_time)
            SELECT DISTINCT ra.owner_id, d.hosting_id, '', :username, 0, '', :notes, :creation_type_id, 0, :insert_time
            " . $eligible . "
            ORDER BY d.hosting_id, ra.owner_id");
        self::bindAll($stmt, $placeholder['params']);
        $stmt->bindValue('username', self::USERNAME, \PDO::PARAM_STR);
        $stmt->bindValue('notes', self::ACCOUNT_NOTES, \PDO::PARAM_STR);
        $stmt->bindValue('creation_type_id', (int) $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $insert_time, \PDO::PARAM_STR);
        $stmt->execute();
        $accounts = $stmt->rowCount();
        if ($accounts === 0) return self::clearLinks($pdo, 0);

        $type_id = self::otherTypeId($pdo, $creation_type_id);
        $cat_id = self::categoryId($pdo, $creation_type_id, $insert_time);
        $renewal = self::renewalValues($today);

        // One service per account, named after its provider (every account is an imported one, since the table was
        // created empty in this run)
        $stmt = $pdo->prepare("
            INSERT INTO hosting_services
            (owner_id, hosting_id, account_id, type_id, ip_id, cat_id, `name`, hostname, billing_term, renewal_day,
             revision, expiry_date, autorenew, fee_id, total_cost, fee_fixed, notes, active, creation_type_id,
             created_by, insert_time)
            SELECT hpa.owner_id, hpa.hosting_id, hpa.id, :type_id, 0, :cat_id, h.name, '', :billing_term, :renewal_day,
                   0, :expiry_date, 0, 0, 0, 0, :notes, :active, :creation_type_id, 0, :insert_time
            FROM hosting_provider_accounts AS hpa
            INNER JOIN hosting AS h ON h.id = hpa.hosting_id
            ORDER BY hpa.id");
        $stmt->bindValue('type_id', $type_id, \PDO::PARAM_INT);
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('billing_term', self::BILLING_TERM, \PDO::PARAM_INT);
        $stmt->bindValue('renewal_day', $renewal['renewal_day'], \PDO::PARAM_INT);
        $stmt->bindValue('expiry_date', $renewal['expiry_date'], \PDO::PARAM_STR);
        $stmt->bindValue('notes', self::SERVICE_NOTES, \PDO::PARAM_STR);
        $stmt->bindValue('active', self::ACTIVE, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', (int) $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $insert_time, \PDO::PARAM_STR);
        $stmt->execute();

        // Each service's custom field row, since the lists join it (the custom fields table is empty, so it has no
        // field columns yet)
        $stmt = $pdo->prepare("
            INSERT INTO hosting_service_field_data
            (service_id, insert_time)
            SELECT hs.id, :insert_time
            FROM hosting_services AS hs
            ORDER BY hs.id");
        $stmt->bindValue('insert_time', $insert_time, \PDO::PARAM_STR);
        $stmt->execute();

        /*
         * The links, over the same eligible domains, each to the service for its provider and owner. hosting_services
         * has no index on (hosting_id, owner_id), and adding one would end the transaction, so each service is looked
         * up through a temporary table keyed on that pair (creating or dropping a temporary table doesn't end it).
         * Without the privilege to create one, the links join hosting_services directly, which gives the same result,
         * only more slowly on a large install. update_time is left alone, as the update block's other repairs leave it.
         */
        $map = self::serviceMap($pdo);
        $stmt = $pdo->prepare( // Legacy: domains.hosting_id before 4.27.0
            "UPDATE domains AS d
            " . self::ELIGIBLE_JOINS . "
            INNER JOIN " . $map . " AS hs ON hs.hosting_id = d.hosting_id AND hs.owner_id = ra.owner_id
            SET d.service_id = hs." . ($map === self::MAP_TABLE ? 'service_id' : 'id') . "
            WHERE d.hosting_id <> 0
              AND NOT " . $placeholder['sql']);
        self::bindAll($stmt, $placeholder['params']);
        $stmt->execute();
        if ($map === self::MAP_TABLE) $pdo->query("DROP TEMPORARY TABLE IF EXISTS `" . self::MAP_TABLE . "`");

        return self::clearLinks($pdo, $accounts);
    }

    /*
     * The table the link UPDATE finds each service in: MAP_TABLE, a temporary copy of every service's provider, owner,
     * and id keyed on (hosting_id, owner_id), or hosting_services when the database user may not make a temporary table
     * (access denied). Any other error is thrown, since it can have ended the upgrade's transaction (a deadlock rolls it
     * back), and carrying on would clear the old links with no services to link them to.
     */
    protected static function serviceMap(\PDO $pdo)
    {
        try {
            $pdo->query("DROP TEMPORARY TABLE IF EXISTS `" . self::MAP_TABLE . "`");
            $pdo->query("
                CREATE TEMPORARY TABLE `" . self::MAP_TABLE . "` (
                    `hosting_id` INT(10) UNSIGNED NOT NULL,
                    `owner_id` INT(10) UNSIGNED NOT NULL,
                    `service_id` INT(10) UNSIGNED NOT NULL,
                    PRIMARY KEY  (`hosting_id`, `owner_id`)
                ) ENGINE=InnoDB");
            $pdo->query("
                INSERT INTO `" . self::MAP_TABLE . "`
                (hosting_id, owner_id, service_id)
                SELECT hosting_id, owner_id, id
                FROM hosting_services
                ORDER BY id");
            return self::MAP_TABLE;
        } catch (\PDOException $e) {
            if (!in_array((int) ($e->errorInfo[1] ?? 0), self::NO_TEMPORARY_TABLE_ERRORS, true)) throw $e;
            return 'hosting_services';
        }
    }

    /*
     * Drops domains' old hosting provider column, after the update block has committed. Any error is ignored (and not
     * logged, since the debug log writes through the database too), so a finished upgrade is never reported as failed:
     * a column left behind holds only zeros (import() cleared it) and has a default, so nothing reads or needs it.
     */
    public static function dropLegacyColumn(\PDO $pdo)
    {
        $old_wait = null;
        try {
            // A connection holding domains open (a long report, say) would otherwise keep the drop, and the upgrade,
            // waiting for up to a year by default, so the check and the drop wait a few seconds and leave the column
            $old_wait = $pdo->query("SELECT @@SESSION.lock_wait_timeout")->fetchColumn();
            $pdo->query("SET SESSION lock_wait_timeout = " . self::DROP_LOCK_WAIT);
            if (self::hasLegacyColumn($pdo)) $pdo->query("ALTER TABLE `domains` DROP `hosting_id`");
        } catch (\Throwable $e) {
            // Left for a later update to drop
        }
        try {
            if ($old_wait !== null && $old_wait !== false) {
                $pdo->query("SET SESSION lock_wait_timeout = " . (int) $old_wait);
            }
        } catch (\Throwable $e) {
            // The connection ends with the request anyway
        }
    }

    /*
     * The category for the imported services: the system's default domain category when that row exists, otherwise
     * the first category DomainMOD's installer made ($creation_type_id, its [no category]), otherwise the first
     * category. With no category at all, one is added as the installer does, with $insert_time.
     */
    public static function categoryId(\PDO $pdo, $creation_type_id, $insert_time)
    {
        $queries = array(
            array("SELECT c.id
                FROM settings AS s
                INNER JOIN categories AS c ON c.id = s.default_category_domains
                ORDER BY c.id
                LIMIT 1", array()),
            array("SELECT id
                FROM categories
                WHERE creation_type_id = :creation_type_id
                ORDER BY id
                LIMIT 1", array('creation_type_id' => (int) $creation_type_id)),
            array("SELECT id
                FROM categories
                ORDER BY id
                LIMIT 1", array()));

        foreach ($queries as $query) {
            $stmt = $pdo->prepare($query[0]);
            foreach ($query[1] as $key => $value) $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            $stmt->closeCursor();
            if ($id !== false && (int) $id > 0) return (int) $id;
        }

        $stmt = $pdo->prepare("
            INSERT INTO categories
            (`name`, stakeholder, notes, creation_type_id, insert_time)
            VALUES
            (:name, :stakeholder, '', :creation_type_id, :insert_time)");
        $stmt->bindValue('name', self::CATEGORY_NAME, \PDO::PARAM_STR);
        $stmt->bindValue('stakeholder', self::CATEGORY_STAKEHOLDER, \PDO::PARAM_STR);
        $stmt->bindValue('creation_type_id', (int) $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('insert_time', $insert_time, \PDO::PARAM_STR);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    // The Other service type the update block seeded (DomainMOD's own row, the lowest id if there's more than one)
    protected static function otherTypeId(\PDO $pdo, $creation_type_id)
    {
        $stmt = $pdo->prepare("
            SELECT id
            FROM hosting_types
            WHERE `type` = 'Other'
              AND creation_type_id = :creation_type_id
            ORDER BY id
            LIMIT 1");
        $stmt->bindValue('creation_type_id', (int) $creation_type_id, \PDO::PARAM_INT);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($id === false) throw new \RuntimeException('The Other hosting service type is missing');
        return (int) $id;
    }

    /*
     * Sets every old link to 0 (update_time left alone), so the links leave domains in the same commit as the services
     * that replace them, and a column left behind can never be imported again. Returns $accounts.
     */
    protected static function clearLinks(\PDO $pdo, $accounts)
    {
        $pdo->query( // Legacy: domains.hosting_id before 4.27.0
            "UPDATE domains
            SET hosting_id = 0
            WHERE hosting_id <> 0");
        return $accounts;
    }

    // Binds each of $params (by name) to $stmt as a string
    protected static function bindAll(\PDOStatement $stmt, array $params)
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, \PDO::PARAM_STR);
        }
    }

} //@formatter:on
