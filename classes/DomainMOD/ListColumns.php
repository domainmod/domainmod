<?php
/**
 * /classes/DomainMOD/ListColumns.php
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
 * What the server-side lists' columns share (the Domains list's DomainListColumns and the SSL Certificates list's
 * SslListColumns): the column list and its layout key, sorting, the default order, the custom field columns and their
 * cells, and the copy of the session values the columns read. Each list sets its own columns, titles, cells, and
 * search expressions, and these constants:
 * - SESSION_KEYS, the session values its columns and cells read, copied by sessionCopy(),
 * - FIELD_ALIAS, the SQL alias of its custom field data table (dfd or sslfd),
 * - DEFAULT_SORT_KEY, the column the list sorts by when it shows (otherwise the first column that isn't the control
 *   column),
 * - NAME_KEY, the column that names each row (the domain or the certificate), which stays shown on narrow screens.
 */
abstract class ListColumns
{
    const SESSION_KEYS = array();
    const FIELD_ALIAS = '';
    const DEFAULT_SORT_KEY = 'expiry';
    const NAME_KEY = '';

    protected $settings;
    protected $layout;
    protected $currency;
    protected $columns;

    // A row's cell for one column, as HTML
    abstract protected function cell(array $column, $row, $today);

    // The search box's expressions for the visible columns (see ListQuery::searchSql())
    abstract public function searchExpressions();

    // The session values the columns need, so the endpoint can close the session before running its queries
    public static function sessionCopy(array $session)
    {
        $copy = array();
        foreach (static::SESSION_KEYS as $key) {
            $copy[$key] = $session[$key] ?? '';
        }
        return $copy;
    }

    /*
     * The custom field columns for $fields (the session's custom field list, s_cdf_data or s_csf_data), added to
     * $columns. $current_fields_sql is the custom field columns that exist now (CustomField::getCustomFieldsSql()): the
     * session can still list a field that's since been deleted, which would otherwise read a missing column.
     */
    protected function withCustomFields(array $columns, $fields, $current_fields_sql)
    {
        $alias = static::FIELD_ALIAS;
        preg_match_all('/' . $alias . '\.([A-Za-z0-9_]+)/', (string) $current_fields_sql, $matches);
        $current_fields = $matches[1];

        foreach ((array) $fields as $field) {
            // Text Areas get no column, and neither does a field that no longer exists
            if (($field['value'] ?? '') != '1' || ($field['type_id'] ?? '') == '3') continue;
            if (!in_array($field['field'] ?? '', $current_fields, true)) continue;
            $columns[] = array('key' => 'cf_' . $field['field'], 'title' => $field['name'], 'sort' => array($alias . '.' . $field['field']),
                'field' => $field);
        }

        return $columns;
    }

    // The visible columns in order, each with its key, its heading (as the page has always printed it), and whether it sorts
    public function columns()
    {
        return $this->columns;
    }

    // A short hash of the visible columns in order, so a change made in another tab is noticed
    public function layoutKey()
    {
        return substr(sha1(implode(',', array_column($this->columns, 'key'))), 0, 12);
    }

    /*
     * Text custom fields whose stored values are all numbers (or empty) sort as numbers, as DataTables sorted a column
     * of numbers before, so 2 comes before 10. A number can have a sign, a leading decimal point, an exponent, and the
     * characters DataTables ignored (thousands separators, currency signs, percent signs, and a few letters). Empty
     * values come first when ascending and last when descending, as DataTables placed them. Only the rows the list's
     * filters leave are checked, as DataTables only saw those: $from_sql is the list's ListQuery::fromSql() followed by
     * the filter conditions. Call it before sortMap().
     */
    public function useNumericSort(\PDO $pdo, $from_sql)
    {
        foreach ($this->columns as $index => $column) {
            if (!isset($column['field']) || $column['field']['type_id'] != '2') continue;
            $name = static::FIELD_ALIAS . '.' . $column['field']['field'];
            $plain = $name;
            // The characters DataTables 3.1.5's number check ignores, in either case
            foreach (array("\\'", "\u{00A0}", ',', '$', '£', '€', '¥', '%', "\u{2009}", "\u{202F}", "\u{20BD}", "\u{20A9}",
                         "\u{20BA}", 'r', 'R', 'f', 'F', 'k', 'K', 'Ƀ', 'ƀ', 'Ξ', 'ξ') as $character) {
                $plain = "REPLACE(" . $plain . ", '" . $character . "', '')";
            }
            $counts = $pdo->query("
                SELECT SUM(" . $name . " != '') AS filled,
                       SUM(" . $name . " != '' AND " . $plain . " NOT REGEXP '^[[:space:]]*[-+]?([0-9]+([.][0-9]*)?|[.][0-9]+)([eE][-+]?[0-9]+)?[[:space:]]*$') AS other
                " . $from_sql)->fetch(\PDO::FETCH_ASSOC);
            if ((int) $counts['filled'] > 0 && (int) $counts['other'] === 0) {
                $this->columns[$index]['sort'] = array($name . " != ''", '(' . $plain . ') + 0');
            }
        }
    }

    // The sort expressions by column index, for ListQuery::orderSql(). The control column doesn't sort.
    public function sortMap()
    {
        $map = array();
        foreach ($this->columns as $index => $column) {
            if ($column['sort']) $map[$index] = $column['sort'];
        }
        return $map;
    }

    // The default order: the DEFAULT_SORT_KEY column (the expiry date) when it shows, otherwise the first column that
    // isn't the control column (a list without Responsive has none), both ascending
    public function defaultOrder()
    {
        foreach ($this->columns as $index => $column) {
            if ($column['key'] === static::DEFAULT_SORT_KEY) return array(array($index, 'asc'));
        }
        foreach ($this->columns as $index => $column) {
            if ($column['key'] !== 'control') return array(array($index, 'asc'));
        }
        return array(array(0, 'asc'));
    }

    /*
     * The search expressions for a custom field column, added to $typed (matched against the word as typed): text,
     * date, and time stamp fields, with the empty placeholder dates left out, as their cells show them blank. Check
     * boxes and URLs aren't searched.
     */
    protected function customFieldSearch(array $column, array $typed)
    {
        $name = static::FIELD_ALIAS . '.' . $column['field']['field'];
        $type = $column['field']['type_id'];
        if ($type == '2') {
            $typed[] = $name;
        } elseif ($type == '4') {
            $typed[] = "CASE WHEN " . $name . " IN ('1970-01-01', '0000-00-00') THEN '' ELSE CAST(" . $name . " AS CHAR) END";
        } elseif ($type == '5') {
            $typed[] = "CASE WHEN " . $name . " IN ('1970-01-01 00:00:00', '0000-00-00 00:00:00') THEN '' ELSE CAST(" . $name . " AS CHAR) END";
        }
        return $typed;
    }

    /*
     * The table's heading cells as HTML, in the columns' order, each with its column's class (see columnClass()). The
     * control column (Responsive's details button) has no visible heading, so screen readers get "Details", as on the
     * Domain Queue. Titles are printed as the page has always printed them.
     */
    public function headerCells()
    {
        $cells = array();
        foreach ($this->columns as $column) {
            if ($column['key'] === 'control') {
                $cells[] = '<th width="20px"><span class="visually-hidden">' . _('Details') . '</span></th>';
            } else {
                $cells[] = '<th class="' . $this->columnClass($column) . '">' . $column['title'] . '</th>';
            }
        }
        return $cells;
    }

    // A column's class for the list's styles (domainmod-list-domain, domainmod-list-fee, and so on, with
    // domainmod-list-custom for the custom fields), on its heading and every cell, with JavaScript on or off
    public function columnClass(array $column)
    {
        return 'domainmod-list-' . (strpos($column['key'], 'cf_') === 0 ? 'custom' : $column['key']);
    }

    // A row's cells as HTML, in the columns' order. $today is the date past which a live row's expiry is marked.
    public function cells($row, $today)
    {
        $cells = array();
        foreach ($this->columns as $column) {
            $cells[] = $this->cell($column, $row, $today);
        }
        return $cells;
    }

    // A custom field column's cell, as each list has always drawn it
    protected function customFieldCell(array $field, $row)
    {
        $layout = $this->layout;
        $value = $row->{$field['field']} ?? '';
        if ($field['type_id'] == '1') { // Check Box
            return $value == '1' ? 'Yes' : 'No';
        } elseif ($field['type_id'] == '2') { // Text
            return $layout->customFieldText($value);
        } elseif ($field['type_id'] == '4') { // Date
            return Time::isUnset($value) ? '' : $layout->customFieldText($value);
        } elseif ($field['type_id'] == '5') { // Time Stamp
            return Time::isUnset($value) ? '' : $layout->customFieldText($value);
        } elseif ($field['type_id'] == '6') { // URL
            return $value != '' ? $layout->customFieldLink($value) : '';
        }
        return '';
    }

} //@formatter:on
