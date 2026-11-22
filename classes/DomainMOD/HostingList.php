<?php
/**
 * /classes/DomainMOD/HostingList.php
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
 * Which hosting services are in the Hosting Services list, and in what order. The list page (hosting/index.php) and its
 * server-side data endpoint (hosting/list-data.php) both build their filters here, so the first page the server prints,
 * the pages DataTables fetches afterwards, the counts, Export, and the Raw List can't drift apart. What it shares with
 * the Domains and SSL Certificates lists (page sizes, the search box, counts, rows, and the order) is in ListQuery.
 *
 * The filter conditions are SQL fragments, with whole numbers cast, and the dates and keyword quoted by PDO. The keyword
 * matches a hosting service's name or hostname. Every condition is written on the hosting service table alone (hs), so
 * the filter menus can use them without joining the lookups.
 */
class HostingList extends ListQuery
{
    // The hosting tables, and the index on their custom field data, arrive with this database version (see
    // ListQuery::hasListIndexes())
    const JOIN_ORDER_DB_VERSION = '4.27.0';

    // The columns every row has (see selectSql(), which adds yearly_cost, worked out from them). The provider's id is
    // also provider_id, the name FeeStatus::addHostingFeeUrl() reads, for the "No fee" cell's link.
    const SELECT_COLUMNS = "hs.id, hs.name, hs.hostname, hs.billing_term, hs.renewal_day, hs.expiry_date, hs.autorenew, hs.total_cost, hs.notes, hs.active, hs.creation_type_id, hs.created_by, hs.insert_time, hs.update_time, hpa.id AS hpa_id, hpa.username, h.id AS h_id, h.id AS provider_id, h.name AS hosting_provider_name, o.id AS o_id, o.name AS owner_name, f.id AS f_id, f.initial_fee, f.renewal_fee, f.misc_fee, c.currency AS fee_currency, cc.conversion, ht.id AS type_id, ht.type, ip.id AS ip_id, ip.name AS ip_name, ip.ip, ip.rdns, cat.id AS cat_id, cat.name AS cat_name";

    // The custom field data table and its hosting service id column (see ListQuery::fixedJoinOrder())
    const FIELD_DATA_TABLE = 'hosting_service_field_data';
    const FIELD_DATA_ID = 'service_id';

    /*
     * The fixed join order (see ListQuery::JOIN_ORDER). The fee, its currency, the user's conversion rate, and the IP
     * address are LEFT JOINs, so a service with no fee (or no rate) stays in the list with NULL fee columns, shown as "No
     * fee" (or "No rate") and left out of the total, and a service with no IP address (ip_id 0, or the id of an IP
     * address that no longer exists) stays with a NULL ip.id. The conversion rate's condition here has no user: it's
     * only ever read through joinOrder(), which adds the signed-in user's id, so one service never joins several users'
     * rates.
     */
    const JOIN_ORDER = array(
        array('hosting_services', 'hs', ''),
        array('hosting_service_field_data', 'hsfd', ''),
        array('hosting_provider_accounts', 'hpa', 'PRIMARY'),
        array('hosting', 'h', 'PRIMARY'),
        array('owners', 'o', 'PRIMARY'),
        array('hosting_fees', 'f', 'PRIMARY', 'f.id = hs.fee_id'),
        array('currencies', 'c', 'PRIMARY', 'c.id = f.currency_id'),
        array('currency_conversions', 'cc', 'user_currency', 'cc.currency_id = f.currency_id'),
        array('hosting_types', 'ht', 'PRIMARY'),
        array('ip_addresses', 'ip', 'PRIMARY', 'ip.id = hs.ip_id'),
        array('categories', 'cat', 'PRIMARY'));

    // Export and the Raw List come out in the list's default order, as the other lists' do, and EXPORT_ORDER_SQL and
    // the keyset (see ListQuery::keysetSql()) are both built from it
    const EXPORT_ORDER = array('hs.expiry_date', 'hs.name', 'hs.id');
    const EXPORT_ORDER_SQL = ' ORDER BY ' . self::EXPORT_ORDER[0] . ' ASC, ' . self::EXPORT_ORDER[1] . ' ASC, '
        . self::EXPORT_ORDER[2] . ' ASC';

    // Every order ends with the hosting service's name and the id
    const TIE_BREAK = array('hs.name ASC', 'hs.id ASC');

    // The IP Address filter's value for hosting services with no IP address (0 already means no filter)
    const NO_IP = -1;

    // The id filters, by request key, and the hosting service column each one matches (the IP address has its own
    // condition, see ipCondition())
    const ID_FILTERS = array('oid' => 'hs.owner_id', 'hid' => 'hs.hosting_id', 'hpaid' => 'hs.account_id',
        'htid' => 'hs.type_id', 'hipid' => 'hs.ip_id', 'hcid' => 'hs.cat_id');

    /*
     * The list's filters, checked the way the SSL Certificates list checks them: the id filters as whole numbers, the
     * billing term from the allowed terms (anything else counts as no filter), and the status from a fixed list, falling
     * back to Live. $request holds the raw values (a missing key, or one that isn't a single value, counts as no filter).
     * The IP Address filter also takes NO_IP, and any other negative number counts as no filter.
     */
    public static function normalizeState(array $request)
    {
        $single = function ($key) use ($request) {
            $value = $request[$key] ?? '';
            return is_scalar($value) ? (string) $value : '';
        };

        $state = array();
        foreach (array_keys(self::ID_FILTERS) as $key) {
            $state[$key] = (int) $single($key);
        }
        $state['hipid'] = self::checkedIpId($state['hipid']);
        $state['hterm'] = self::checkedTerm($single('hterm'));

        $is_active = $single('is_active');
        $state['is_active'] = in_array($is_active, self::VALID_IS_ACTIVE, true) ? $is_active : 'LIVE';

        return $state;
    }

    // An IP Address filter value as the list uses it: an IP address's id, NO_IP, or 0 (no filter) for any other number
    public static function checkedIpId($hipid)
    {
        $hipid = (int) $hipid;
        return $hipid < 0 && $hipid !== self::NO_IP ? 0 : $hipid;
    }

    // A Billing Term filter value as the list uses it: one of the allowed terms, or 0 (no filter) for anything else
    public static function checkedTerm($term)
    {
        return Hosting::validTerm(is_scalar($term) ? (string) $term : '') ? (int) $term : 0;
    }

    /*
     * The IP Address filter's condition for a value (checked with checkedIpId()), written from hs alone so it works in a
     * query with or without the ip_addresses table: '' for 0 (no filter), the services using an existing IP address for
     * its id, and for NO_IP the services with no IP address, the ones whose ip_id matches no IP address (ip_id 0, or an
     * IP address that no longer exists). A service pointing at a missing IP address is only ever found under NO_IP,
     * as its IP cell shows "None".
     */
    public static function ipCondition($hipid)
    {
        $hipid = self::checkedIpId($hipid);
        if ($hipid === 0) return '';
        if ($hipid === self::NO_IP) {
            return " AND NOT EXISTS (SELECT 1 FROM ip_addresses AS nip WHERE nip.id = hs.ip_id) ";
        }
        return " AND hs.ip_id = '" . $hipid . "' AND EXISTS (SELECT 1 FROM ip_addresses AS nip WHERE nip.id = hs.ip_id) ";
    }

    /*
     * The filter conditions for a state (see normalizeState()), the keyword, and the date range, as named SQL fragments,
     * each starting with " AND " or empty. $keyword is the keyword as the page keeps it, HTML-encoded by
     * Sanitize::text(). It's matched against the hosting service's name and hostname, which are stored encoded the same
     * way, so neither is decoded. $start_date and $end_date are checked dates, or 'YYYY-MM-DD' (or '') when there's no
     * range.
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
            $parts['is_active_string'] = " AND hs.active IN " . $active_lists[$is_active] . " ";
        } else {
            $parts['is_active_string'] = " AND hs.active = '" . (int) $is_active . "' ";
        }

        foreach (self::ID_FILTERS as $key => $column) {
            $parts[$key . '_string'] = $state[$key] !== 0 ? " AND " . $column . " = '" . (int) $state[$key] . "' " : '';
        }
        $parts['hipid_string'] = self::ipCondition($state['hipid']);
        $term = self::checkedTerm($state['hterm'] ?? 0);
        $parts['hterm_string'] = $term !== 0 ? " AND hs.billing_term = '" . $term . "' " : '';

        $parts['range_string'] = '';
        if ($start_date != '' && $start_date != 'YYYY-MM-DD') {
            $parts['range_string'] = " AND (hs.expiry_date >= " . $pdo->quote($start_date) . " AND hs.expiry_date <= "
                . $pdo->quote($end_date) . ")";
        }

        $parts['search_string'] = '';
        if ($keyword != '') {
            $like = $pdo->quote('%' . $keyword . '%');
            $parts['search_string'] = " AND (hs.name LIKE " . $like . " OR hs.hostname LIKE " . $like . ") ";
        }

        return $parts;
    }

    // Every filter fragment joined, in the page's order
    public static function filterSql(array $parts)
    {
        return $parts['is_active_string'] . $parts['oid_string'] . $parts['hid_string'] . $parts['hpaid_string']
            . $parts['htid_string'] . $parts['hterm_string'] . $parts['hipid_string'] . $parts['hcid_string']
            . $parts['range_string'] . $parts['search_string'];
    }

    /*
     * The filter fragments joined without one filter's (by its key, such as 'hid'), for that filter's own menu, so the
     * menu lists every choice the other filters leave
     */
    public static function filterSqlWithout(array $parts, $key)
    {
        $parts[$key . '_string'] = '';
        return self::filterSql($parts);
    }

    /*
     * The fixed join order with the signed-in user's id in the conversion rate's condition (see JOIN_ORDER). It's
     * written into the SQL as a whole number, as fromSql() writes it, since the counts bind only the filters' values.
     */
    protected function joinOrder()
    {
        $tables = static::JOIN_ORDER;
        foreach ($tables as $n => $table) {
            if ($table[1] === 'cc') $tables[$n][3] = $table[3] . " AND cc.user_id = '" . (int) $this->user_id . "'";
        }
        return $tables;
    }

    /*
     * The tables the list joins, and the conditions that join them (see ListQuery::fromSql()). Without the fixed join
     * order, the LEFT JOINs (the fee, its currency, the user's conversion rate, and the IP address, see JOIN_ORDER) are
     * written next to hosting_services, since a LEFT JOIN's ON can only name the tables in its own operand. Neither
     * order has a WHERE condition on a LEFT JOINed table, which would drop the services without a match.
     */
    public function fromSql()
    {
        if ($this->fixedJoinOrder()) {
            $tables = $this->joinOrderTables();
        } else {
            $tables = "hosting_services AS hs
                 LEFT JOIN hosting_fees AS f ON f.id = hs.fee_id
                 LEFT JOIN currencies AS c ON c.id = f.currency_id
                 LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = '" . (int) $this->user_id . "'
                 LEFT JOIN ip_addresses AS ip ON ip.id = hs.ip_id,
                 hosting_provider_accounts AS hpa, hosting AS h, owners AS o, hosting_types AS ht, categories AS cat,
                 hosting_service_field_data AS hsfd";
        }

        return "
            FROM " . $tables . "
            WHERE hs.account_id = hpa.id
              AND hpa.hosting_id = h.id
              AND hpa.owner_id = o.id
              AND hs.type_id = ht.id
              AND hs.cat_id = cat.id
              AND hs.id = hsfd.service_id";
    }

    // The columns every row has, plus the current custom fields. yearly_cost is the yearly figure of one term's cost in
    // the fee's own currency (NULL for a term that isn't an allowed one).
    public function selectSql()
    {
        return "SELECT " . self::SELECT_COLUMNS . ", " . Hosting::yearlyCostSql('hs') . " AS yearly_cost" . $this->field_columns;
    }

    // The names of the list's own columns in its rows, yearly_cost included (see ListQuery::builtInColumnNames())
    public static function builtInColumnNames()
    {
        return array_merge(parent::builtInColumnNames(), array('yearly_cost'));
    }

    /*
     * The SQL for the list's total yearly cost in the user's currency (add fromSql() and the filters). A service with
     * no fee, or no conversion rate, has a NULL cost, which SUM() leaves out.
     */
    public static function yearlyTotalSql()
    {
        return "SELECT SUM(" . Hosting::yearlyCostSql('hs', 'hs.total_cost * cc.conversion') . ")";
    }

    /*
     * The SQL for the list's total yearly cost (grand_total, see yearlyTotalSql()) and how many of its services have no
     * fee (no_fee_count), the ones the total leaves out, read in one pass (add fromSql() and the filters)
     */
    public static function totalsSql()
    {
        return "SELECT SUM(" . Hosting::yearlyCostSql('hs', 'hs.total_cost * cc.conversion') . ") AS grand_total,"
            . " COALESCE(SUM(CASE WHEN f.id IS NULL THEN 1 ELSE 0 END), 0) AS no_fee_count";
    }

    // The Hosting Services list's columns, for ListQuery::tableSearchSql()
    protected function listColumns(array $settings)
    {
        return new HostingListColumns($settings, $this->fields);
    }

} //@formatter:on
