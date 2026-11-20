<?php
/**
 * /classes/DomainMOD/ListQuery.php
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
 * What the server-side lists (the Domains list, DomainList, and the SSL Certificates list, SslList) share: their page
 * sizes, the search box's words and conditions, the counts and pages of rows, the order, and the data endpoints'
 * request checks. Each list sets its own tables, columns, and filters, and TIE_BREAK, the columns every order ends
 * with.
 *
 * The search box's conditions (searchSql()) use bound parameters, and sorting (orderSql()) only ever uses the fixed
 * column map from the list's columns class (a ListColumns), never text from the request.
 */
abstract class ListQuery
{
    // The largest page anyone can ask for, whatever the page-size preference says
    const MAX_PAGE_SIZE = 500;

    // The search box's limits, checked before any SQL is built
    const MAX_SEARCH_LENGTH = 200;
    const MAX_SEARCH_WORDS = 10;

    const VALID_IS_ACTIVE = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'LIVE', 'ALL');

    // The columns every order ends with, so pages stay stable when values tie (each list sets its own)
    const TIE_BREAK = array();

    /*
     * Export and the Raw List's order, each list's own: the expiry date, the name, and the id, all on the list's first
     * table and all sorted ascending. The list's EXPORT_ORDER_SQL and the keyset (see keysetSql()) are both built from
     * it, so they can't drift apart.
     */
    const EXPORT_ORDER = array();
    const EXPORT_ORDER_SQL = '';

    // The database version whose update adds the index on each list's custom field data (see fixedJoinOrder())
    const JOIN_ORDER_DB_VERSION = '4.26.0';

    // The list's custom field data table, and its column with the asset's id, which needs an index (each list sets
    // its own)
    const FIELD_DATA_TABLE = '';
    const FIELD_DATA_ID = '';

    /*
     * The fixed join order (see fixedJoinOrder()), each list's own: every table with its alias and the index it's read
     * through, in the order they're read. The asset comes first, then its custom field data (through the index on the
     * asset's id), then each lookup by its key. The lookups' keys are named so a small lookup table is never read whole
     * into a join buffer, which would lose the asset's order and sort every row again.
     */
    const JOIN_ORDER = array();

    // Whether each list joins in the written order, by list class and connection, worked out once per request
    protected static $fixed_join_order = array();

    /*
     * Export and the Raw List read their rows in chunks (see exportChunks()): this many keys at a time when the expiry
     * date index can serve the export order, and more without it, where each chunk sorts what's left of the list
     */
    const EXPORT_KEYS = 1000;
    const EXPORT_KEYS_UNINDEXED = 5000;

    // The most bytes of full rows read at once, and the allowance for each row's columns other than its notes and
    // custom fields
    const EXPORT_GROUP_BYTES = 8388608;
    const EXPORT_ROW_BYTES = 4096;

    // Whether each list's first table has the expiry date index, by list class and connection, worked out once per request
    protected static $export_index = array();

    protected $pdo;
    protected $user_id;
    protected $fields;
    protected $field_columns;

    /*
     * $fields is the list's custom fields as they exist now (CustomField::getFilterFields()), read once by the page,
     * from which the SELECT's custom field columns (CustomField::fieldsSql()), the list's columns, and its custom field
     * filters are all built, so they describe the same fields
     */
    public function __construct(\PDO $pdo, $user_id, array $fields)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
        $this->fields = $fields;
        $this->field_columns = CustomField::fieldsSql($fields, static::JOIN_ORDER[1][1]);
    }

    // The custom fields the list was built from (see the constructor)
    public function fields()
    {
        return $this->fields;
    }

    /*
     * The tables the list joins, and the conditions that join them, for the signed-in user's currency conversions. Once
     * fixedJoinOrder() is true, the tables are joined with STRAIGHT_JOIN, so every query built on it (the counts, the
     * rows, the numeric sort check, the Total Cost, Export, and the Raw List) reads them in the written order.
     */
    abstract public function fromSql();

    // The columns every row has, plus the current custom fields
    abstract public function selectSql();

    // The list's columns for a copy of the session's display settings (see ListColumns::sessionCopy())
    abstract protected function listColumns(array $settings);

    /*
     * Whether a database at this version has the index on each list's custom field data (the asset's id). New code can
     * arrive before the database update that adds it, so it's used only once the database itself is at that version or
     * later.
     */
    public static function hasListIndexes($db_version)
    {
        return is_string($db_version) && $db_version !== ''
            && version_compare($db_version, self::JOIN_ORDER_DB_VERSION, '>=');
    }

    /*
     * Whether the list's queries join their tables in the written order (JOIN_ORDER), rather than in the order the
     * database picks. With the list indexes that's much faster on large lists, but without the custom field data index
     * each asset would scan the whole field data table, minutes per query at 30,000 domains. So it needs the database at
     * JOIN_ORDER_DB_VERSION or later and every index JOIN_ORDER uses there (an update can have skipped one), checked once
     * per request for each connection, and a failed check counts as no.
     */
    public function fixedJoinOrder()
    {
        $key = static::class . '#' . spl_object_id($this->pdo);

        if (!isset(self::$fixed_join_order[$key])) {

            try {

                self::$fixed_join_order[$key] = self::hasListIndexes($this->dbVersion()) && $this->listIndexesExist();

            } catch (\Throwable $e) {

                self::$fixed_join_order[$key] = false;

            }

        }

        return self::$fixed_join_order[$key];
    }

    // The database's version (as System::getDbVersion() reads it)
    protected function dbVersion()
    {
        return $this->pdo->query("
            SELECT db_version
            FROM settings")->fetchColumn();
    }

    /*
     * Whether the database has every index the fixed join order uses: one starting with the custom field data's asset
     * id column (under any name), and each lookup key JOIN_ORDER names. An index the database has been told not to use
     * (ignored on MariaDB, invisible on MySQL) doesn't count, since FORCE INDEX can't name it. Every column is read, as
     * the two databases name that column differently and older versions have neither.
     */
    protected function listIndexesExist()
    {
        $tables = array_values(array_unique(array_merge(array(static::FIELD_DATA_TABLE),
            array_column(static::JOIN_ORDER, 0))));
        $names = array();
        foreach ($tables as $n => $table) {
            $names['table' . $n] = $table;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM `INFORMATION_SCHEMA`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` IN (:" . implode(', :', array_keys($names)) . ")
              AND `SEQ_IN_INDEX` = 1");
        foreach ($names as $name => $table) {
            $stmt->bindValue($name, $table, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $indexes = array();
        $field_data_index = false;
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (!self::indexUsable($row)) continue;
            $table = strtolower($row['table_name']);
            $indexes[$table . '.' . strtolower($row['index_name'])] = true;
            if ($table === static::FIELD_DATA_TABLE && strtolower($row['column_name']) === static::FIELD_DATA_ID) {
                $field_data_index = true;
            }
        }
        if (!$field_data_index) return false;

        foreach (static::JOIN_ORDER as $table) {
            if ($table[2] !== '' && !isset($indexes[strtolower($table[0] . '.' . $table[2])])) return false;
        }

        return true;
    }

    // Whether an index (a row from INFORMATION_SCHEMA.STATISTICS, with lowercase keys) can be used: not ignored on
    // MariaDB, and not invisible on MySQL
    protected static function indexUsable(array $row)
    {
        return strtoupper((string) ($row['ignored'] ?? 'NO')) !== 'YES'
            && strtoupper((string) ($row['is_visible'] ?? 'YES')) !== 'NO';
    }

    /*
     * The tables in the fixed join order (JOIN_ORDER), joined with STRAIGHT_JOIN, for the list's fromSql(). An entry
     * with a fourth element, its join condition, is joined with LEFT JOIN ... ON that condition instead, so a row
     * without a match in that table is kept. A LEFT JOIN still reads the tables before it first, so the order holds.
     */
    protected function joinOrderTables()
    {
        $sql = '';
        foreach (static::JOIN_ORDER as $n => $table) {
            $entry = $table[0] . ' AS ' . $table[1] . ($table[2] !== '' ? ' FORCE INDEX (' . $table[2] . ')' : '');
            if ($n === 0) {
                $sql = $entry;
            } elseif (isset($table[3])) {
                $sql .= ' LEFT JOIN ' . $entry . ' ON ' . $table[3];
            } else {
                $sql .= ' STRAIGHT_JOIN ' . $entry;
            }
        }
        return $sql;
    }

    /*
     * The read-only snapshot every query of an export runs in (its counts, its header, and each chunk), so a row that's
     * renewed, added, or deleted part way through can't be skipped, repeated, or counted in the header but missing from
     * the rows. The isolation level is set first, as a server can default to READ COMMITTED, where the snapshot wouldn't
     * hold. Nothing can write inside it, not even a log entry. The page that starts it ends it with endSnapshot().
     */
    public function beginSnapshot()
    {
        $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->pdo->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY");
    }

    // Ends the snapshot beginSnapshot() started, with COMMIT or, after an error, ROLLBACK
    public function endSnapshot($commit)
    {
        $this->pdo->exec($commit ? "COMMIT" : "ROLLBACK");
    }

    /*
     * The list's rows in the export order (EXPORT_ORDER), a group at a time, as a generator of groups, each itself a
     * generator of rows (see fetchEach()), for Export. Each group's rows must be read before the next group. Each chunk is
     * read in two steps, so the memory it takes stays near a fixed size however long the list is. A narrow query reads
     * the next keys (see exportKeyBatches()), each with an estimate of its row's size in bytes. Then the full rows for
     * those keys are read in groups whose estimated sizes add up to at most EXPORT_GROUP_BYTES (see byteGroups()), by
     * the asset's id and its custom field data row's id, with the list's filters. The estimate counts the notes and
     * custom fields, plus EXPORT_ROW_BYTES for the rest, so a group can take somewhat more or less than that in PHP. A
     * single row estimated at more than EXPORT_GROUP_BYTES is a group on its own, which is no worse than the list
     * showing it. $filter_sql is the list's filters (and the table search's conditions), and $params their bound
     * values. It never starts or ends a transaction: the page runs it inside beginSnapshot() and endSnapshot().
     */
    public function exportChunks($filter_sql, array $params, $keys_per_chunk = null)
    {
        $id_column = static::EXPORT_ORDER[2];
        $field_id_column = static::JOIN_ORDER[1][1] . '.id';

        foreach ($this->exportKeyBatches($this->fromSql() . $filter_sql, $params, $keys_per_chunk) as $keys) {

            foreach (self::byteGroups($keys) as $group) {

                $ids = array_values(array_unique(array_column($group, 'ks_id')));
                $field_ids = array_column($group, 'ks_field_id');
                $group_params = array();
                foreach ($ids as $n => $id) {
                    $group_params['ks_id' . $n] = $id;
                }
                foreach ($field_ids as $n => $field_id) {
                    $group_params['ks_fd' . $n] = $field_id;
                }

                $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter_sql
                    . " AND " . $id_column . " IN (:ks_id" . implode(', :ks_id', array_keys($ids)) . ")"
                    . " AND " . $field_id_column . " IN (:ks_fd" . implode(', :ks_fd', array_keys($field_ids)) . ")"
                    . static::EXPORT_ORDER_SQL);
                $stmt->execute($params + $group_params);

                yield $this->fetchEach($stmt);

            }

        }
    }

    // A statement's rows one at a time, as a generator, so only the database driver's copy of a group is held in full
    protected function fetchEach(\PDOStatement $stmt)
    {
        while (($row = $stmt->fetch()) !== false) {
            yield $row;
        }
        $stmt->closeCursor();
    }

    /*
     * The keys of the list's rows in the export order, a batch at a time, as a generator: each row's three export order
     * values (ks_expiry, ks_name, and ks_id), its custom field data row's id (ks_field_id), and its size in bytes
     * (ks_bytes, or 0 when $sizes is false, as the Raw List needs no sizes). $from_sql is the list's fromSql() and
     * filters (the Raw List's saved query, or exportChunks()'s), and $params their bound values. Each batch starts after
     * the last asset of the one before (see keysetSql()).
     *
     * A damaged database can have two custom field data rows for one asset, which share its keys, so a batch could end
     * between them and the next would skip the rest. So when a batch comes back full, the rows of its last asset are put
     * back unread, and the next batch starts after the asset before it. Only a batch with fewer rows than the limit is
     * the last one. A full batch that holds only one asset (as many data rows as the limit, or more) is read on its own,
     * by the data rows' ids (see exportAssetBatches()), in as many batches as that takes. So an asset's rows are only
     * ever split between batches of its own, and every row is read once.
     */
    public function exportKeyBatches($from_sql, array $params, $keys_per_chunk = null, $sizes = true)
    {
        $limit = max(1, (int) ($keys_per_chunk ?? $this->exportKeysPerChunk()));
        $last = null;

        while (true) {

            $keyset = static::keysetSql($last);
            $keys = $this->exportKeys($from_sql . $keyset['sql'], $params + $keyset['params'],
                static::EXPORT_ORDER_SQL, $limit, $sizes);
            $full = count($keys) === $limit;

            if ($full) {

                $last_key = end($keys);
                while ($keys && end($keys)['ks_id'] == $last_key['ks_id']) {
                    array_pop($keys);
                }

                if (!$keys) {

                    yield from $this->exportAssetBatches($from_sql, $params, $last_key['ks_id'], $limit, $sizes);
                    $last = array($last_key['ks_expiry'], $last_key['ks_name'], $last_key['ks_id']);
                    continue;

                }

            }

            yield $keys;

            if (!$full) return;

            $last_key = end($keys);
            $last = array($last_key['ks_expiry'], $last_key['ks_name'], $last_key['ks_id']);

        }
    }

    // One asset's keys, a batch at a time, ordered by its custom field data rows' ids, for an asset with at least as many
    // data rows as a batch holds (see exportKeyBatches())
    protected function exportAssetBatches($from_sql, array $params, $asset_id, $limit, $sizes)
    {
        $field_id_column = static::JOIN_ORDER[1][1] . '.id';
        $after = null;

        do {

            $condition = " AND " . static::EXPORT_ORDER[2] . " = :ks_asset";
            $condition_params = array('ks_asset' => $asset_id);
            if ($after !== null) {
                $condition .= " AND " . $field_id_column . " > :ks_after";
                $condition_params['ks_after'] = $after;
            }

            $keys = $this->exportKeys($from_sql . $condition, $params + $condition_params,
                " ORDER BY " . $field_id_column . " ASC", $limit, $sizes);
            if ($keys) yield $keys;
            $after = $keys ? end($keys)['ks_field_id'] : null;

        } while (count($keys) === $limit);
    }

    // One batch of keys (see exportKeyBatches()), as arrays, with each row's size, or 0 when $sizes is false
    protected function exportKeys($from_sql, array $params, $order_sql, $limit, $sizes)
    {
        list($expiry, $name, $id) = static::EXPORT_ORDER;
        $stmt = $this->pdo->prepare("SELECT " . $expiry . " AS ks_expiry, " . $name . " AS ks_name, " . $id . " AS ks_id, "
            . static::JOIN_ORDER[1][1] . ".id AS ks_field_id, " . ($sizes ? $this->rowBytesSql() : "0") . " AS ks_bytes"
            . $from_sql . $order_sql . " LIMIT " . (int) $limit);
        $stmt->execute($params);
        $keys = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $keys;
    }

    // Each row's size in bytes, as SQL: its notes and custom fields, plus EXPORT_ROW_BYTES for everything else
    protected function rowBytesSql()
    {
        $columns = array(static::JOIN_ORDER[0][1] . '.notes');
        foreach (explode(',', $this->field_columns) as $column) {
            if (trim($column) !== '') $columns[] = trim($column);
        }

        $sql = '';
        foreach ($columns as $column) {
            $sql .= "COALESCE(LENGTH(" . $column . "), 0) + ";
        }
        return "(" . $sql . self::EXPORT_ROW_BYTES . ")";
    }

    /*
     * Keys (see exportKeyBatches()) split into groups whose sizes (ks_bytes) add up to at most $budget, in order. A
     * row bigger than $budget is a group on its own.
     */
    public static function byteGroups(array $keys, $budget = self::EXPORT_GROUP_BYTES)
    {
        $groups = array();
        $group = array();
        $bytes = 0;

        foreach ($keys as $key) {
            $size = (int) $key['ks_bytes'];
            if ($group && $bytes + $size > $budget) {
                $groups[] = $group;
                $group = array();
                $bytes = 0;
            }
            $group[] = $key;
            $bytes += $size;
        }
        if ($group) $groups[] = $group;

        return $groups;
    }

    // How many keys each batch of an export reads (see EXPORT_KEYS)
    public function exportKeysPerChunk()
    {
        return $this->fixedJoinOrder() && $this->exportIndexExists() ? self::EXPORT_KEYS : self::EXPORT_KEYS_UNINDEXED;
    }

    /*
     * Whether the list's first table has its expiry_date index, starting with the expiry date, which can serve the export
     * order. An index the database has been told not to use doesn't count (see indexUsable()), and a failed check
     * counts as no.
     */
    protected function exportIndexExists()
    {
        $key = static::class . '#' . spl_object_id($this->pdo);

        if (!isset(self::$export_index[$key])) {

            try {

                $stmt = $this->pdo->prepare("
                    SELECT *
                    FROM `INFORMATION_SCHEMA`.`STATISTICS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND `TABLE_NAME` = :table
                      AND `INDEX_NAME` = 'expiry_date'
                      AND `SEQ_IN_INDEX` = 1");
                $stmt->bindValue('table', static::JOIN_ORDER[0][0], \PDO::PARAM_STR);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                $exists = false;
                foreach ($rows as $row) {
                    $row = array_change_key_case($row, CASE_LOWER);
                    if (self::indexUsable($row) && strtolower($row['column_name']) === 'expiry_date') $exists = true;
                }
                self::$export_index[$key] = $exists;

            } catch (\Throwable $e) {

                self::$export_index[$key] = false;

            }

        }

        return self::$export_index[$key];
    }

    // How many rows match the filters ($filter_sql from the list's filterSql(), and $filter_params its bound values)
    public function count($filter_sql, array $filter_params = array())
    {
        $stmt = $this->pdo->prepare("SELECT count(*)" . $this->fromSql() . $filter_sql);
        $stmt->execute($filter_params);
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $count;
    }

    /*
     * Export's check that its row query still runs, before the file starts: the full query with LIMIT 0, so a custom
     * field column that's gone (or a table whose definition changed after the snapshot began) fails here, while the
     * export can still go back to the list, rather than in its first chunk
     */
    public function exportPreflight($filter_sql, array $params)
    {
        $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter_sql . " LIMIT 0");
        $stmt->execute($params);
        $stmt->closeCursor();
    }

    /*
     * How many rows match the filters, and how many of those also match the search box ($search from searchSql()), as
     * array(total, filtered), counted in one pass over the rows rather than two
     */
    public function counts($filter_sql, array $search, array $filter_params = array())
    {
        if ($search['sql'] === '') {
            $total = $this->count($filter_sql, $filter_params);
            return array($total, $total);
        }

        $stmt = $this->pdo->prepare("SELECT count(*) AS total, COALESCE(SUM(CASE WHEN 1 = 1" . $search['sql'] . " THEN 1 ELSE 0 END), 0) AS filtered"
            . $this->fromSql() . $filter_sql);
        $stmt->execute($search['params'] + $filter_params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return array((int) $row['total'], (int) $row['filtered']);
    }

    /*
     * One page of rows. $order_sql comes from orderSql(), $start and $length are checked whole numbers, and
     * $filter_params are the filters' bound values.
     */
    public function rows($filter_sql, array $search, $order_sql, $start, $length, array $filter_params = array())
    {
        $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter_sql . $search['sql'] . $order_sql
            . " LIMIT " . max(0, (int) $start) . ", " . max(1, (int) $length));
        $stmt->execute($search['params'] + $filter_params);
        return $stmt->fetchAll();
    }

    /*
     * The page sizes the list offers, and the default one: the user's preference (capped at 500, and 50 when it's not
     * a whole number from 1, as Display Settings accepts any whole number), then 50, 75, 100, 250, and 500. There's no
     * "All", so no request can fetch the whole table.
     */
    public static function pageSizes($preference)
    {
        $preference = filter_var($preference, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($preference === false) $preference = 50;
        $preference = min($preference, self::MAX_PAGE_SIZE);

        $sizes = array($preference);
        foreach (array(50, 75, 100, 250, 500) as $size) {
            if (!in_array($size, $sizes, true)) $sizes[] = $size;
        }

        return array('default' => $preference, 'sizes' => $sizes);
    }

    // A requested page start: a whole number from 0, or 0
    public static function checkedStart($start)
    {
        $start = filter_var(is_scalar($start) ? $start : '', FILTER_VALIDATE_INT,
            array('options' => array('min_range' => 0, 'max_range' => 10000000)));
        return $start === false ? 0 : $start;
    }

    // A requested page size: one of the sizes the menu offers, or the default
    public static function checkedLength($length, array $page_sizes)
    {
        $length = filter_var(is_scalar($length) ? $length : '', FILTER_VALIDATE_INT);
        return in_array($length, $page_sizes['sizes'], true) ? $length : $page_sizes['default'];
    }

    /*
     * A data endpoint's request shape: every key in $single_keys that's there is a single value, the search box's
     * search[value] is too, and order is a list of entries, each with a column and a direction. $get is $_GET.
     */
    public static function requestShapeOk(array $get, array $single_keys)
    {
        foreach ($single_keys as $key) {
            if (isset($get[$key]) && !is_string($get[$key])) return false;
        }

        $search = $get['search'] ?? array();
        if (!is_array($search) || (isset($search['value']) && !is_string($search['value']))) return false;

        $order = $get['order'] ?? array();
        if (!is_array($order) || !array_is_list($order)) return false;
        foreach ($order as $entry) {
            if (!is_array($entry) || !is_string($entry['column'] ?? null) || !is_string($entry['dir'] ?? null)) return false;
        }

        return true;
    }

    /*
     * The search box's text as DataTables' smart search splits it: words, quoted phrases (straight or curly quotes)
     * kept together, and a word starting with "!" (when the rest is longer than one character) as one that mustn't appear.
     * Returns a list of array('text' => ..., 'negate' => bool), or false when it's over the length or word limits.
     */
    public static function searchTokens($text)
    {
        $text = (string) $text;
        if (mb_strlen($text, 'UTF-8') > self::MAX_SEARCH_LENGTH) return false;

        preg_match_all('/(!?)(?:"([^"]*)"|\x{201C}([^\x{201D}]*)\x{201D}|(\S+))/u', $text, $matches, PREG_SET_ORDER);

        $tokens = array();
        foreach ($matches as $match) {
            $negate = $match[1] === '!';
            $word = $match[2] !== '' ? $match[2] : (($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? ''));
            // As in DataTables, an exclusion only counts when the word after "!" is longer than one character, so "!a"
            // (and a lone "!") excludes nothing
            if ($negate && mb_strlen($word, 'UTF-8') <= 1) continue;
            if ($word === '') continue;
            $tokens[] = array('text' => $word, 'negate' => $negate);
        }

        return count($tokens) > self::MAX_SEARCH_WORDS ? false : $tokens;
    }

    // A word for LIKE, with \, %, and _ escaped so they match themselves (also used by CustomFieldFilter)
    public static function likeValue($word)
    {
        return '%' . addcslashes($word, '\\%_') . '%';
    }

    /*
     * The search box's conditions, as " AND ..." SQL and its bound parameters. Each word must appear in at least one
     * of the visible columns' search expressions ($columns from the list's ListColumns::searchExpressions()), an
     * excluded word in none. 'lower' expressions are matched against the word in lowercase (domains and TLDs, stored
     * that way in case-sensitive columns), 'typed' ones against the word as typed (dates and custom fields), 'idn' ones
     * (domains) also against the word's stored IDN form, and 'encoded' ones against the HTML-encoded word (names stored
     * encoded). $tokens comes from searchTokens().
     */
    public function searchSql(array $tokens, array $columns)
    {
        if (!$tokens) return array('sql' => '', 'params' => array());

        $sanitize = new Sanitize();
        $sql = '';
        $params = array();
        $n = 0;

        foreach ($tokens as $token) {
            $word = $token['text'];
            $lower = self::likeValue(mb_strtolower($word, 'UTF-8'));
            $typed = self::likeValue($word);
            $encoded = self::likeValue($sanitize->text($word));
            $alternatives = array();

            foreach ($columns['lower'] as $expression) {
                $name = 'sw' . $n++;
                $alternatives[] = $expression . " LIKE :" . $name . " ESCAPE '\\\\'";
                $params[$name] = $lower;
            }

            foreach ($columns['typed'] as $expression) {
                $name = 'sw' . $n++;
                $alternatives[] = $expression . " LIKE :" . $name . " ESCAPE '\\\\'";
                $params[$name] = $typed;
            }

            // A domain also matches its stored (IDN) form of the word, as the Domains list's keyword filter does
            $idn = ($columns['idn'] ?? array()) ? Domain::normalize($word) : '';
            if ($idn !== '' && $idn !== mb_strtolower($word, 'UTF-8')) {
                foreach ($columns['idn'] as $expression) {
                    $name = 'sw' . $n++;
                    $alternatives[] = $expression . " LIKE :" . $name . " ESCAPE '\\\\'";
                    $params[$name] = self::likeValue($idn);
                }
            }

            foreach ($columns['encoded'] as $expression) {
                $name = 'sw' . $n++;
                $alternatives[] = $expression . " LIKE :" . $name . " ESCAPE '\\\\'";
                $params[$name] = $encoded;
            }

            // A list where nothing at all can be searched matches no word (and every excluded word is absent)
            $group = $alternatives ? '(' . implode(' OR ', $alternatives) . ')' : '(1 = 0)';
            $sql .= $token['negate'] ? " AND NOT " . $group : " AND " . $group;
        }

        return array('sql' => $sql, 'params' => $params);
    }

    /*
     * The search box's conditions for Export and the Raw List, which get the box's text and the table's column layout
     * in their links (dt_search and dt_layout, kept up to date by js/server-list.js), so they hold the rows the table
     * shows. The words are split and matched exactly as the data endpoint does, against the columns the session's
     * display settings show ($settings from the list's ListColumns::sessionCopy()). Text that isn't a single value, or
     * is over the limits, adds no conditions, as the table never shows rows for it. Returns searchSql()'s array, plus
     * 'stale', true (with no conditions) when Display Settings or the custom fields have changed since the table was
     * drawn, so the search would no longer match the same columns.
     */
    public function tableSearchSql($text, $layout, array $settings)
    {
        $tokens = is_string($text) ? self::searchTokens($text) : false;
        if (!$tokens) return array('sql' => '', 'params' => array(), 'stale' => false);

        $columns = $this->listColumns($settings);
        if (!is_string($layout) || $layout !== $columns->layoutKey()) {
            return array('sql' => '', 'params' => array(), 'stale' => true);
        }

        return $this->searchSql($tokens, $columns->searchExpressions()) + array('stale' => false);
    }

    /*
     * The condition that starts a chunk of Export or the Raw List straight after the last row of the one before, as
     * " AND ..." SQL and its bound parameters, in the list's export order (EXPORT_ORDER). $last holds that row's three
     * values in the same order, or is null for the first chunk, which has no condition. It's written out rather than as
     * a row comparison ((a, b, c) > (...)), which older MySQL and MariaDB don't use an index for, and every placeholder
     * has its own name, even where two take the same value, since native prepares can't repeat one. The ks_ names can't
     * clash with the search box's.
     */
    public static function keysetSql($last)
    {
        if ($last === null) return array('sql' => '', 'params' => array());

        list($expiry, $name, $id) = static::EXPORT_ORDER;
        $last = array_values($last);
        // A row without all three values would skip or repeat rows, so it's never used
        if (count($last) !== 3 || in_array(null, $last, true)) {
            throw new \InvalidArgumentException('The keyset needs the last row\'s expiry date, name, and id');
        }

        return array(
            'sql' => " AND (" . $expiry . " > :ks_e OR (" . $expiry . " = :ks_e2 AND (" . $name . " > :ks_d OR ("
                . $name . " = :ks_d2 AND " . $id . " > :ks_i))))",
            'params' => array('ks_e' => $last[0], 'ks_e2' => $last[0], 'ks_d' => $last[1], 'ks_d2' => $last[1],
                'ks_i' => $last[2]));
    }

    /*
     * Whether a Raw List link ($get, raw.php's $_GET) matches the list saved in the session, before anything is read:
     * 'unchecked' for a link that doesn't name its list (dt_list), such as an old bookmark, 'mismatch' when the saved
     * list is of another type ($type, from the link's dt_type), or another list (dt_list against the saved query and its
     * bound values, see savedListKey()), or the link has no custom field filters' key (dt_cfk) to check inside the
     * snapshot, and otherwise 'ok'. $saved_from_sql is null when nothing usable is saved.
     */
    public static function rawLinkStatus(array $get, $type, $saved_type, $saved_from_sql, array $saved_params)
    {
        if (!is_string($get['dt_list'] ?? null)) return 'unchecked';
        if ($saved_type !== $type || $saved_from_sql === null) return 'mismatch';
        if (!hash_equals(self::savedListKey($saved_from_sql, $saved_params), $get['dt_list'])) return 'mismatch';
        if (!is_string($get['dt_cfk'] ?? null)) return 'mismatch';
        return 'ok';
    }

    /*
     * A short key for the Raw List's saved query (its FROM and filters) and the filters' bound values, so the Raw List
     * is never run on a list another tab saved since
     */
    public static function savedListKey($sql, array $params = array())
    {
        return substr(sha1((string) $sql . "\n" . serialize($params)), 0, 12);
    }

    /*
     * DataTables' order requests as ORDER BY, through the columns' fixed sort expressions ($sort_map from the list's
     * ListColumns::sortMap(), by column index). An index or direction that isn't known is skipped, and when nothing is
     * left, $default (a list of array(index, direction)) is used. Every order ends with the list's TIE_BREAK columns,
     * so pages stay stable when values tie.
     */
    public static function orderSql(array $order, array $sort_map, array $default)
    {
        $terms = array();
        $used = array();

        foreach (array($order, $default) as $requests) {
            foreach ($requests as $request) {
                $index = (int) ($request[0] ?? -1);
                $direction = strtolower((string) ($request[1] ?? ''));
                if (!isset($sort_map[$index]) || isset($used[$index])) continue;
                if ($direction !== 'asc' && $direction !== 'desc') continue;
                $used[$index] = true;
                foreach ($sort_map[$index] as $expression) {
                    $terms[] = $expression . ' ' . strtoupper($direction);
                }
            }
            if ($terms) break;
        }

        foreach (static::TIE_BREAK as $term) {
            $terms[] = $term;
        }

        return ' ORDER BY ' . implode(', ', $terms);
    }

} //@formatter:on
