<?php
/**
 * /classes/DomainMOD/SslList.php
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
 * Which certificates are in the SSL Certificates list, and in what order. The list page (ssl/index.php) and its
 * server-side data endpoint (ssl/list-data.php) both build their filters here, so the first page the server prints, the
 * pages DataTables fetches afterwards, the counts, Export, and the Raw List can't drift apart. What it shares with the
 * Domains list (page sizes, the search box, counts, rows, and the order) is in ListQuery.
 *
 * The filter conditions are SQL fragments, with whole numbers cast, and the dates and keyword quoted by PDO. The keyword
 * matches a certificate's name or its domain, as the page's filter menus match it.
 */
class SslList extends ListQuery
{
    // The custom field data table and its certificate id column (see ListQuery::fixedJoinOrder())
    const FIELD_DATA_TABLE = 'ssl_cert_field_data';
    const FIELD_DATA_ID = 'ssl_id';

    // The fixed join order (see ListQuery::JOIN_ORDER)
    const JOIN_ORDER = array(
        array('ssl_certs', 'sslc', ''),
        array('ssl_cert_field_data', 'sslfd', ''),
        array('ssl_accounts', 'sslpa', 'PRIMARY'),
        array('ssl_providers', 'sslp', 'PRIMARY'),
        array('owners', 'o', 'PRIMARY'),
        array('ssl_fees', 'f', 'PRIMARY'),
        array('currencies', 'c', 'PRIMARY'),
        array('currency_conversions', 'cc', 'user_currency'),
        array('domains', 'd', 'PRIMARY'),
        array('ssl_cert_types', 'sslcf', 'PRIMARY'),
        array('ip_addresses', 'ip', 'PRIMARY'),
        array('categories', 'cat', 'PRIMARY'));

    // Export and the Raw List come out in the list's default order, as the Domains list's do
    const EXPORT_ORDER_SQL = ' ORDER BY sslc.expiry_date ASC, sslc.name ASC, sslc.id ASC';

    // Every order ends with the certificate's name and the id
    const TIE_BREAK = array('sslc.name ASC', 'sslc.id ASC');

    /*
     * The list's filters, checked the way the page has always checked them: the id filters as whole numbers, and the
     * status from a fixed list, falling back to Live. $request holds the raw values (a missing key, or one that isn't a
     * single value, counts as no filter).
     */
    public static function normalizeState(array $request)
    {
        $single = function ($key) use ($request) {
            $value = $request[$key] ?? '';
            return is_scalar($value) ? (string) $value : '';
        };

        $state = array();
        foreach (array('oid', 'did', 'sslpid', 'sslpaid', 'ssltid', 'sslipid', 'sslpcid') as $key) {
            $state[$key] = (int) $single($key);
        }

        $is_active = $single('is_active');
        $state['is_active'] = in_array($is_active, self::VALID_IS_ACTIVE, true) ? $is_active : 'LIVE';

        return $state;
    }

    /*
     * The filter conditions for a state (see normalizeState()), the keyword, and the date range, as the page's named
     * SQL fragments, each starting with " AND " or empty. $keyword is the keyword as the page keeps it, HTML-encoded by
     * Sanitize::text(). It's matched against the certificate's name, which is stored encoded the same way, so neither
     * is decoded, or against its domain, as the page's filter menus match it (see DomainList::keywordCondition()). The
     * domain is matched in a subquery on the certificate's domain_id, so the condition can be checked on each
     * certificate before the other tables are joined, which keeps a keyword search fast on a long list. $start_date and
     * $end_date are checked dates, or 'YYYY-MM-DD' (or '') when there's no range.
     */
    public function filterParts(array $state, $keyword, $start_date, $end_date)
    {
        $pdo = $this->pdo;
        $parts = array();

        $active_lists = array(
            'LIVE' => "('1', '2', '3', '4', '5', '6', '7', '8', '9')",
            'ALL' => "('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')");
        $is_active = $state['is_active'];
        if (isset($active_lists[$is_active])) {
            $parts['is_active_string'] = " AND sslc.active IN " . $active_lists[$is_active] . " ";
        } else {
            $parts['is_active_string'] = " AND sslc.active = '" . (int) $is_active . "' ";
        }

        $id_columns = array('oid' => 'o.id', 'did' => 'd.id', 'sslpid' => 'sslp.id', 'sslpaid' => 'sslc.account_id',
            'ssltid' => 'sslc.type_id', 'sslipid' => 'sslc.ip_id', 'sslpcid' => 'sslc.cat_id');
        foreach ($id_columns as $key => $column) {
            $parts[$key . '_string'] = $state[$key] !== 0 ? " AND " . $column . " = '" . (int) $state[$key] . "' " : '';
        }

        $parts['range_string'] = '';
        if ($start_date != '' && $start_date != 'YYYY-MM-DD') {
            $parts['range_string'] = " AND (sslc.expiry_date >= " . $pdo->quote($start_date) . " AND sslc.expiry_date <= "
                . $pdo->quote($end_date) . ")";
        }

        $parts['search_string'] = $keyword != '' ? " AND (sslc.name LIKE " . $pdo->quote('%' . $keyword . '%')
            . " OR sslc.domain_id IN (SELECT kd.id FROM domains AS kd WHERE "
            . DomainList::keywordCondition($pdo, 'kd.domain', $keyword) . ")) " : '';

        return $parts;
    }

    // Every filter fragment joined, in the page's order
    public static function filterSql(array $parts)
    {
        return $parts['is_active_string'] . $parts['oid_string'] . $parts['did_string'] . $parts['sslpid_string']
            . $parts['sslpaid_string'] . $parts['ssltid_string'] . $parts['sslipid_string'] . $parts['sslpcid_string']
            . $parts['range_string'] . $parts['search_string'];
    }

    // The tables the list joins, and the conditions that join them, for the signed-in user's currency conversions (see
    // ListQuery::fromSql())
    public function fromSql()
    {
        if ($this->fixedJoinOrder()) {
            $tables = $this->joinOrderTables();
        } else {
            $tables = "ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f,
                 currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip,
                 categories AS cat, ssl_cert_field_data AS sslfd";
        }

        return "
            FROM " . $tables . "
            WHERE sslc.account_id = sslpa.id
              AND sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslc.fee_id = f.id
              AND f.currency_id = c.id
              AND c.id = cc.currency_id
              AND sslc.domain_id = d.id
              AND sslc.type_id = sslcf.id
              AND sslc.ip_id = ip.id
              AND sslc.cat_id = cat.id
              AND sslc.id = sslfd.ssl_id
              AND cc.user_id = '" . $this->user_id . "'";
    }

    // The columns every row has, plus the current custom fields
    public function selectSql()
    {
        return "SELECT sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.total_cost, sslc.notes, sslc.active, sslc.creation_type_id, sslc.created_by, sslc.insert_time, sslc.update_time, sslpa.id AS sslpa_id, sslpa.username, sslp.id AS sslp_id, sslp.name AS ssl_provider_name, o.id AS o_id, o.name AS owner_name, f.id AS f_id, f.initial_fee, f.renewal_fee, f.misc_fee, cc.conversion, d.domain, sslcf.id as type_id, sslcf.type, ip.id AS ip_id, ip.name as ip_name, ip.ip, ip.rdns, cat.id AS cat_id, cat.name AS cat_name" . $this->field_columns;
    }

    // The SSL Certificates list's columns, for ListQuery::tableSearchSql()
    protected function listColumns(array $settings)
    {
        return new SslListColumns($settings, $this->field_columns);
    }

} //@formatter:on
