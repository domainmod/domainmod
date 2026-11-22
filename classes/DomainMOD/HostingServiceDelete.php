<?php
/**
 * /classes/DomainMOD/HostingServiceDelete.php
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
 * The hosting service delete's domain side (hosting/edit.php): a service's domains are kept, without a hosting service.
 * The page holds the hosting lock and runs each attempt in its own transaction (see withDeadlockRetry()), after reading
 * the hosting custom field list in share mode and before locking the service row, so the domains are locked before the
 * service, in the Change History's lock order.
 */
class HostingServiceDelete
{
    // How many of a service's domains are locked, recorded, and unlinked at a time
    const PAGE = 1000;

    // MySQL's error number for a deadlock, after which the transaction has been rolled back
    const DEADLOCK = 1213;

    protected $pdo;
    protected $domain;

    // $domain is the Domain whose clearHostingService() unlinks each page (a new one by default)
    public function __construct(\PDO $pdo, $domain = null)
    {
        $this->pdo = $pdo;
        $this->domain = $domain ?? new Domain();
    }

    /*
     * Unlinks every domain on the service ($service_id) and returns how many were changed. Its domains are locked in
     * keyset pages of PAGE in id order, through the domains index whose only column is service_id (named with FORCE
     * INDEX when there is one, see serviceIndexName(), so each page is a seek in id order within the service). Each page
     * is captured for the Change History ($op, a ChangeHistoryOperation) with its service_id alone, set to 0 with
     * Domain::clearHostingService(), and compared, so each domain gets a row from the service to none, marked as a side
     * effect. It starts and ends no transaction.
     */
    public function unlinkDomains($service_id, $op)
    {
        $service_id = (int) $service_id;
        if ($service_id <= 0) return 0;

        $index = self::serviceIndexName($this->pdo);
        $hint = $index !== '' ? " FORCE INDEX (`" . str_replace('`', '``', $index) . "`)" : '';
        $unlinked = 0;
        $last = 0;

        do {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM domains" . $hint . "
                WHERE service_id = :service_id
                  AND id > :last
                ORDER BY id
                LIMIT " . self::PAGE . "
                FOR UPDATE");
            $stmt->bindValue('service_id', $service_id, \PDO::PARAM_INT);
            $stmt->bindValue('last', $last, \PDO::PARAM_INT);
            $stmt->execute();
            $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            $stmt->closeCursor();
            if (!$ids) break;

            $before = $op->capture('domain', $ids, array('service_id'));
            $unlinked += $this->domain->clearHostingService($ids, $service_id);
            $op->compare($before, 'side_effect');
            $last = end($ids);

        } while (count($ids) === self::PAGE);

        return $unlinked;
    }

    /*
     * The domains index whose only column is service_id, whatever its name (the 4.27.0 update adds one named service_id,
     * or keeps one it finds already there), when it's usable (not ignored on MariaDB or invisible on MySQL), or '' when
     * there's none or the check fails. An index that starts with service_id but has more columns doesn't count, since
     * its entries aren't in id order within a service. One named service_id wins over another with the same column.
     */
    public static function serviceIndexName(\PDO $pdo)
    {
        try {
            $rows = $pdo->query("
                SELECT *
                FROM `INFORMATION_SCHEMA`.`STATISTICS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                  AND `TABLE_NAME` = 'domains'")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return '';
        }

        $indexes = array();
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $name = (string) $row['index_name'];
            $indexes[$name]['columns'][(int) $row['seq_in_index']] = strtolower((string) $row['column_name']);
            $indexes[$name]['usable'] = ($indexes[$name]['usable'] ?? true)
                && strtoupper((string) ($row['ignored'] ?? 'NO')) !== 'YES'
                && strtoupper((string) ($row['is_visible'] ?? 'YES')) !== 'NO';
        }

        $found = '';
        foreach ($indexes as $name => $index) {
            if ($index['usable'] && $index['columns'] === array(1 => 'service_id') && ($found === '' || $name === 'service_id')) {
                $found = (string) $name;
            }
        }
        return $found;
    }

    /*
     * Runs $attempt (given the attempt's number, 1 or 2) and returns what it returns. A domain delete takes no hosting
     * lock, so it can meet the service delete on the same domain row, and MySQL can then pick either as a deadlock's
     * victim. When it picks this one (error 1213), the transaction is rolled back and $attempt runs once more from the
     * start, so anything it counts starts again too. A second deadlock, or any other error, is thrown after the
     * rollback.
     */
    public function withDeadlockRetry(callable $attempt)
    {
        for ($number = 1; ; $number++) {

            try {

                return $attempt($number);

            } catch (\PDOException $e) {

                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ($number >= 2 || (int) ($e->errorInfo[1] ?? 0) !== self::DEADLOCK) throw $e;

            } catch (\Throwable $e) {

                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;

            }

        }
    }

} //@formatter:on
