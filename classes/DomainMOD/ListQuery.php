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

    protected $pdo;
    protected $user_id;
    protected $field_columns;

    // $field_columns is CustomField::getCustomFieldsSql() for the list's custom fields (the ones that exist now)
    public function __construct(\PDO $pdo, $user_id, $field_columns)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
        $this->field_columns = (string) $field_columns;
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
            if (strtoupper((string) ($row['ignored'] ?? 'NO')) === 'YES') continue;
            if (strtoupper((string) ($row['is_visible'] ?? 'YES')) === 'NO') continue;
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

    // The tables in the fixed join order (JOIN_ORDER), joined with STRAIGHT_JOIN, for the list's fromSql()
    protected function joinOrderTables()
    {
        $tables = array();
        foreach (static::JOIN_ORDER as $table) {
            $tables[] = $table[0] . ' AS ' . $table[1] . ($table[2] !== '' ? ' FORCE INDEX (' . $table[2] . ')' : '');
        }
        return implode(' STRAIGHT_JOIN ', $tables);
    }

    // How many rows match the filters ($filter_sql from the list's filterSql())
    public function count($filter_sql)
    {
        return (int) $this->pdo->query("SELECT count(*)" . $this->fromSql() . $filter_sql)->fetchColumn();
    }

    /*
     * How many rows match the filters, and how many of those also match the search box ($search from searchSql()), as
     * array(total, filtered), counted in one pass over the rows rather than two
     */
    public function counts($filter_sql, array $search)
    {
        if ($search['sql'] === '') {
            $total = $this->count($filter_sql);
            return array($total, $total);
        }

        $stmt = $this->pdo->prepare("SELECT count(*) AS total, COALESCE(SUM(CASE WHEN 1 = 1" . $search['sql'] . " THEN 1 ELSE 0 END), 0) AS filtered"
            . $this->fromSql() . $filter_sql);
        $stmt->execute($search['params']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return array((int) $row['total'], (int) $row['filtered']);
    }

    // One page of rows. $order_sql comes from orderSql(), and $start and $length are checked whole numbers.
    public function rows($filter_sql, array $search, $order_sql, $start, $length)
    {
        $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter_sql . $search['sql'] . $order_sql
            . " LIMIT " . max(0, (int) $start) . ", " . max(1, (int) $length));
        $stmt->execute($search['params']);
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

    // A word for LIKE, with \, %, and _ escaped so they match themselves
    protected static function likeValue($word)
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

    // A short key for the Raw List's saved query, so a search from one tab is never run on a list another tab saved since
    public static function savedListKey($sql)
    {
        return substr(sha1((string) $sql), 0, 12);
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
