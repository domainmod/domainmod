<?php
/**
 * /classes/DomainMOD/DomainList.php
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
 * Which domains are in the Domains list, and in what order. The list page (domains/index.php) and its server-side
 * data endpoint (domains/list-data.php) both build their filters here, so the first page the server prints, the pages
 * DataTables fetches afterwards, the counts, Export, and the Raw List can't drift apart. What it shares with the SSL
 * Certificates list (page sizes, the search box, counts, rows, and the order) is in ListQuery.
 *
 * The filter conditions are the same SQL fragments the page has always used (whole numbers cast, the TLD, dates, and
 * keyword quoted by PDO, and a segment's stored, already-quoted domain list), so the results don't change.
 */
class DomainList extends ListQuery
{
    // The custom field data table and its domain id column (see ListQuery::fixedJoinOrder())
    const FIELD_DATA_TABLE = 'domain_field_data';
    const FIELD_DATA_ID = 'domain_id';

    // The fixed join order (see ListQuery::JOIN_ORDER)
    const JOIN_ORDER = array(
        array('domains', 'd', ''),
        array('domain_field_data', 'dfd', ''),
        array('registrar_accounts', 'ra', 'PRIMARY'),
        array('registrars', 'r', 'PRIMARY'),
        array('owners', 'o', 'PRIMARY'),
        array('categories', 'cat', 'PRIMARY'),
        array('fees', 'f', 'PRIMARY'),
        array('currencies', 'c', 'PRIMARY'),
        array('currency_conversions', 'cc', 'user_currency'),
        array('dns', 'dns', 'PRIMARY'),
        array('ip_addresses', 'ip', 'PRIMARY'),
        array('hosting', 'h', 'PRIMARY'));

    // Export and the Raw List's order: the expiry date, then the domain (the id keeps domains that tie on both in the
    // same order each time). EXPORT_ORDER_SQL and the keyset (see ListQuery::keysetSql()) are both built from it.
    const EXPORT_ORDER = array('d.expiry_date', 'd.domain', 'd.id');
    const EXPORT_ORDER_SQL = ' ORDER BY ' . self::EXPORT_ORDER[0] . ' ASC, ' . self::EXPORT_ORDER[1] . ' ASC, '
        . self::EXPORT_ORDER[2] . ' ASC';

    // Every order ends with the domain and the id
    const TIE_BREAK = array('d.domain ASC', 'd.id ASC');

    /*
     * The keyword search as an SQL condition on a domain column. The term is quoted by PDO rather than pasted into the
     * SQL, and a term that's also a domain or label in another spelling (a pasted xn--mnchen-3ya.de) matches its stored
     * form too. Domains are stored in lowercase, so the term is lowercased. The two values are each worked out from what
     * was typed, so casing rules can't send both to the wrong spelling.
     */
    public static function keywordCondition(\PDO $pdo, $column, $term)
    {
        $term = htmlspecialchars_decode((string) $term, ENT_QUOTES);
        $lower = mb_strtolower($term, 'UTF-8');
        $conditions = array($column . ' LIKE ' . $pdo->quote('%' . $lower . '%'));
        $idn = Domain::normalize($term);
        if ($idn !== '' && $idn !== $lower) $conditions[] = $column . ' LIKE ' . $pdo->quote('%' . $idn . '%');
        return '(' . implode(' OR ', $conditions) . ')';
    }

    /*
     * The list's filters, checked the way the page has always checked them: the id filters as whole numbers, the TLD
     * checked and turned into its stored form (so xn--p1ai finds domains under рф), the status from a fixed list,
     * falling back to Live, and the Registry Status (see normalizeLock()). $request holds the raw values (a missing key,
     * or one that isn't a single value, counts as no filter).
     */
    public static function normalizeState(array $request)
    {
        $single = function ($key) use ($request) {
            $value = $request[$key] ?? '';
            return is_scalar($value) ? (string) $value : '';
        };

        $state = array();
        foreach (array('pcid', 'oid', 'dnsid', 'ipid', 'whid', 'rid', 'raid', 'segid') as $key) {
            $state[$key] = (int) $single($key);
        }

        $tld = (new Sanitize())->text($single('tld'));
        // As on the page, a TLD of "0" skips the check and stays as it is
        if ($tld && $tld != '') {
            if ((new Validate())->tld($tld) == false || $tld == '0') {
                $tld = '';
            } else {
                $tld = Domain::normalizeTld(htmlspecialchars_decode($tld, ENT_QUOTES));
            }
        }
        $state['tld'] = $tld;

        $is_active = $single('is_active');
        $state['is_active'] = in_array($is_active, self::VALID_IS_ACTIVE, true) ? $is_active : 'LIVE';

        $state['lock'] = self::normalizeLock($request['lock'] ?? '');

        return $state;
    }

    /*
     * The Registry Status filter's value (see DomainStatus::FILTERS), or '' for none: anything else, or any value before
     * the 4.27.0 database update adds the columns it filters on, is no filter
     */
    public static function normalizeLock($value)
    {
        $lock = DomainStatus::normalizeFilter($value);
        return ($lock !== '' && DomainStatus::isAvailable()) ? $lock : '';
    }

    /*
     * The filter conditions for a state (see normalizeState()), the keyword, and the date range, as the page's named
     * SQL fragments, each starting with " AND " or empty. $start_date and $end_date are checked dates, or
     * 'YYYY-MM-DD' (or '') when there's no range. $segment is the segment's stored domain list, when the page has
     * already read it (see Segment::storedRow()), so it can't change between the page's check and the filters.
     */
    public function filterParts(array $state, $keyword, $start_date, $end_date, $segment = null)
    {
        $pdo = $this->pdo;
        $parts = array();

        $active_lists = array(
            'LIVE' => "('1', '2', '3', '4', '5', '6', '7', '8', '9')",
            'ALL' => "('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')");
        $is_active = $state['is_active'];
        if (isset($active_lists[$is_active])) {
            $parts['is_active_string'] = " AND d.active IN " . $active_lists[$is_active] . " ";
        } else {
            $parts['is_active_string'] = " AND d.active = '" . (int) $is_active . "' ";
        }

        if ($state['segid'] !== 0) {
            $parts['segid_string'] = " AND d.domain IN (" . ($segment ?? (new Segment())->getSegment($state['segid'])) . ")";
        } else {
            $parts['segid_string'] = '';
        }

        $id_columns = array('pcid' => 'd.cat_id', 'oid' => 'o.id', 'dnsid' => 'dns.id', 'ipid' => 'ip.id',
            'whid' => 'h.id', 'rid' => 'r.id', 'raid' => 'd.account_id');
        foreach ($id_columns as $key => $column) {
            $parts[$key . '_string'] = $state[$key] !== 0 ? " AND " . $column . " = '" . (int) $state[$key] . "' " : '';
        }

        $parts['range_string'] = '';
        if ($start_date != '' && $start_date != 'YYYY-MM-DD') {
            $parts['range_string'] = " AND (d.expiry_date >= " . $pdo->quote($start_date) . " AND d.expiry_date <= "
                . $pdo->quote($end_date) . ")";
        }

        $parts['tld_string'] = $state['tld'] != '' ? " AND d.tld = " . $pdo->quote($state['tld']) . " " : '';

        $parts['search_string'] = $keyword != '' ? " AND " . self::keywordCondition($pdo, 'd.domain', $keyword) . " " : '';

        $parts['lock_string'] = DomainStatus::filterCondition($state['lock'] ?? '');

        return $parts;
    }

    // How many of a segment's domains the segment flags write (see writeSegmentFlags()) reads and writes at a time
    const SEGMENT_FLAG_BATCH = 500;

    // The name of segment_data's index on the segment's id (see segmentIndexName()), by connection, worked out once per
    // request
    protected static $segment_index = array();

    /*
     * Writes a segment's "filtered" flags, which mark its active domains that the list's other filters leave out, for
     * domains/results.php's Matching But Filtered Domains list. $filter_sql is the list's other filters, without the
     * segment's, $segment is the segment's stored domain list the page's filters were built from, and $params the
     * filters' bound values.
     *
     * It's one short transaction at REPEATABLE READ, so other requests see the flags as they were or as they end up,
     * never half done. First the segment's list is read again with a shared lock, and if it's changed since the filters
     * were built (it was edited in the meantime), nothing is written and it returns 'changed'. Otherwise the segment's
     * active rows are read and locked a batch at a time, in id order (the latest data, so a domain made active a moment
     * ago is included). The names the other filters match are found with a plain read, which takes no row locks, and
     * the batch's flags are written by id, cleared for those names and set for the rest, with every value bound, so no
     * SQL grows with the segment. With the index on segment_id alone (added in 4.27.0, see segmentIndexName()), only
     * the segment's own rows are locked, in the same id order as Maintenance::updateSegments() takes them, which keeps
     * the two from deadlocking each other. Without it, the batches can also lock other segments' rows as they're read.
     * A lock wait timeout or a deadlock rolls it back and it's tried once more, and any other error (or a second
     * failure) is thrown, after the rollback. Returns 'written' when the flags are written.
     *
     * With custom field filters on, $fields_key is the page's CustomFieldFilter::fieldsKey(), and the custom fields are
     * read once more just before the commit: when they've changed since the page read them (a filtered field replaced
     * under the same name), the flags were worked out from the old filter, so it rolls back and returns
     * 'fields_changed'.
     */
    public function writeSegmentFlags($segid, $filter_sql, $segment, array $params = array(), $fields_key = null)
    {
        for ($attempt = 1; ; $attempt++) {

            try {

                return $this->writeSegmentFlagsOnce((int) $segid, $filter_sql, (string) $segment, $params, $fields_key);

            } catch (\PDOException $e) {

                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ($attempt >= 2 || !in_array((int) ($e->errorInfo[1] ?? 0), array(1205, 1213), true)) throw $e;

            } catch (\Throwable $e) {

                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;

            }

        }
    }

    // One try of writeSegmentFlags()
    protected function writeSegmentFlagsOnce($segid, $filter_sql, $segment, array $params, $fields_key = null)
    {
        $pdo = $this->pdo;
        // Named where it exists, so the batches read only the segment's rows, in id order, even for a segment that holds
        // most of the table
        $index_name = $this->segmentIndexName();
        $index_hint = $index_name !== '' ? " FORCE INDEX (`" . str_replace('`', '``', $index_name) . "`)" : "";

        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT `segment`
            FROM segments
            WHERE id = :segid
            LOCK IN SHARE MODE");
        $stmt->bindValue('segid', $segid, \PDO::PARAM_INT);
        $stmt->execute();
        $current = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($current === false || (string) $current !== $segment) {

            $pdo->rollBack();
            return 'changed';

        }

        $last = 0;

        do {

            // The next batch of the segment's active rows, locked
            $stmt = $pdo->prepare("
                SELECT id, domain
                FROM segment_data" . $index_hint . "
                WHERE segment_id = :segid
                  AND active = '1'
                  AND id > :sg_last
                ORDER BY id ASC
                LIMIT " . self::SEGMENT_FLAG_BATCH . "
                FOR UPDATE");
            $stmt->execute(array('segid' => $segid, 'sg_last' => $last));
            $batch = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $stmt->closeCursor();

            if (!$batch) break;
            $last = max(array_keys($batch));

            $names = array();
            foreach (array_values(array_unique($batch)) as $n => $name) {
                $names['sg' . $n] = $name;
            }

            // The ones the list's other filters match
            $stmt = $pdo->prepare("SELECT DISTINCT d.domain" . $this->fromSql() . $filter_sql . " AND d.domain IN (:"
                . implode(', :', array_keys($names)) . ")");
            $stmt->execute($params + $names);
            $matches = array();
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $n => $match) {
                $matches['sm' . $n] = $match;
            }
            $stmt->closeCursor();

            // The batch's flags: cleared for the matches, set for the rest. The names are compared in SQL, with the
            // column's own collation, as the filters compared them (a name stored with a trailing space still equals
            // the name without it).
            $ids = array();
            foreach (array_keys($batch) as $n => $id) {
                $ids['si' . $n] = $id;
            }
            $stmt = $pdo->prepare("
                UPDATE segment_data
                SET filtered = " . ($matches ? "CASE WHEN domain IN (:" . implode(', :', array_keys($matches))
                    . ") THEN '0' ELSE '1' END" : "'1'") . "
                WHERE id IN (:" . implode(', :', array_keys($ids)) . ")");
            $stmt->execute($ids + $matches);

        } while (count($batch) === self::SEGMENT_FLAG_BATCH);

        if ($fields_key !== null) {

            $fields_now = new CustomFieldFilter(CustomField::readFilterFields($pdo, 'domain_fields'), 'dfd');
            if (!hash_equals((string) $fields_key, $fields_now->fieldsKey())) {

                $pdo->rollBack();
                return 'fields_changed';

            }

        }

        $pdo->commit();
        return 'written';
    }

    /*
     * The name of segment_data's index on the segment's id alone (added in 4.27.0, usually named segment_id), when it's
     * there and usable (see ListQuery::indexUsable()), or '' when it isn't. An index that starts with the segment's id
     * but has more columns doesn't count, since its entries aren't in id order within a segment. A failed check counts
     * as no index.
     */
    protected function segmentIndexName()
    {
        $key = spl_object_id($this->pdo);

        if (!isset(self::$segment_index[$key])) {

            try {

                $rows = $this->pdo->query("
                    SELECT *
                    FROM `INFORMATION_SCHEMA`.`STATISTICS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND `TABLE_NAME` = 'segment_data'")->fetchAll(\PDO::FETCH_ASSOC);

                // Each index's columns, in order, and whether it can be used
                $indexes = array();
                foreach ($rows as $row) {
                    $row = array_change_key_case($row, CASE_LOWER);
                    $name = (string) $row['index_name'];
                    $indexes[$name]['columns'][(int) $row['seq_in_index']] = strtolower((string) $row['column_name']);
                    $indexes[$name]['usable'] = ($indexes[$name]['usable'] ?? true) && self::indexUsable($row);
                }

                $found = '';
                foreach ($indexes as $name => $index) {
                    if ($index['usable'] && $index['columns'] === array(1 => 'segment_id')
                        && ($found === '' || $name === 'segment_id')) {
                        $found = (string) $name;
                    }
                }
                self::$segment_index[$key] = $found;

            } catch (\Throwable $e) {

                self::$segment_index[$key] = '';

            }

        }

        return self::$segment_index[$key];
    }

    /*
     * How many of a segment's active domains the list's filters leave out (its "Matching But Filtered Domains"),
     * worked out from the filters themselves rather than read back from the segment's stored flags. $filter_sql is the
     * list's filters, which include the segment's, and $params their bound values. It's a plain read, which takes no row
     * locks.
     */
    public function segmentFilteredCount($segid, $filter_sql, array $params = array())
    {
        $stmt = $this->pdo->prepare("
            SELECT count(*)
            FROM segment_data AS sd
            WHERE sd.segment_id = :sf_segid
              AND sd.active = '1'
              AND NOT EXISTS (SELECT 1" . $this->fromSql() . $filter_sql . " AND d.domain = sd.domain)");
        $stmt->execute(array('sf_segid' => (int) $segid) + $params);
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();
        return (int) $count;
    }

    // Every filter fragment joined, in the page's order
    public static function filterSql(array $parts)
    {
        return $parts['is_active_string'] . $parts['segid_string'] . $parts['pcid_string'] . $parts['oid_string']
            . $parts['dnsid_string'] . $parts['ipid_string'] . $parts['whid_string'] . $parts['rid_string']
            . $parts['raid_string'] . $parts['range_string'] . $parts['tld_string'] . $parts['search_string']
            . ($parts['lock_string'] ?? '');
    }

    // The tables the list joins, and the conditions that join them, for the signed-in user's currency conversions (see
    // ListQuery::fromSql())
    public function fromSql()
    {
        if ($this->fixedJoinOrder()) {
            $tables = $this->joinOrderTables();
        } else {
            $tables = "domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h, domain_field_data AS dfd";
        }

        return "
            FROM " . $tables . "
            WHERE d.account_id = ra.id
              AND ra.registrar_id = r.id
              AND ra.owner_id = o.id
              AND d.cat_id = cat.id
              AND d.fee_id = f.id
              AND d.dns_id = dns.id
              AND d.ip_id = ip.id
              AND d.hosting_id = h.id
              AND f.currency_id = c.id
              AND c.id = cc.currency_id
              AND d.id = dfd.domain_id
              AND cc.user_id = '" . $this->user_id . "'";
    }

    // Whether the domains table has the registry status columns (see DomainStatus::isAvailable())
    public function hasStatusColumns()
    {
        return DomainStatus::isAvailable($this->pdo);
    }

    // The columns every row has, plus the registry status columns (under their ds1_ aliases, once the 4.27.0 database
    // update adds them) and the current custom fields
    public function selectSql()
    {
        return "SELECT d.id, d.domain, d.tld, d.expiry_date, d.total_cost, d.function, d.notes, d.autorenew, d.privacy, d.creation_type_id, d.created_by, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.id AS f_id, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name" . ($this->hasStatusColumns() ? DomainStatus::selectSql('d') : '') . $this->field_columns;
    }

    // The search box's conditions (see ListQuery::searchSql()), where the domain always shows, so it always also matches
    // a word's stored IDN form, as the keyword filter does
    public function searchSql(array $tokens, array $columns)
    {
        return parent::searchSql($tokens, $columns + array('idn' => array('d.domain')));
    }

    // The Domains list's columns, for ListQuery::tableSearchSql()
    protected function listColumns(array $settings)
    {
        return new DomainListColumns($settings, $this->fields);
    }

} //@formatter:on
