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
    // Export and the Raw List's order: the expiry date, then the domain (the id keeps domains that tie on both in the
    // same order each time)
    const EXPORT_ORDER_SQL = ' ORDER BY d.expiry_date ASC, d.domain ASC, d.id ASC';

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
     * checked and turned into its stored form (so xn--p1ai finds domains under рф), and the status from a fixed list,
     * falling back to Live. $request holds the raw values (a missing key, or one that isn't a single value, counts as
     * no filter).
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

        return $state;
    }

    /*
     * The filter conditions for a state (see normalizeState()), the keyword, and the date range, as the page's named
     * SQL fragments, each starting with " AND " or empty. $start_date and $end_date are checked dates, or
     * 'YYYY-MM-DD' (or '') when there's no range.
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
            $parts['is_active_string'] = " AND d.active IN " . $active_lists[$is_active] . " ";
        } else {
            $parts['is_active_string'] = " AND d.active = '" . (int) $is_active . "' ";
        }

        if ($state['segid'] !== 0) {
            $parts['segid_string'] = " AND d.domain IN (" . (new Segment())->getSegment($state['segid']) . ")";
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

        return $parts;
    }

    // Every filter fragment joined, in the page's order
    public static function filterSql(array $parts)
    {
        return $parts['is_active_string'] . $parts['segid_string'] . $parts['pcid_string'] . $parts['oid_string']
            . $parts['dnsid_string'] . $parts['ipid_string'] . $parts['whid_string'] . $parts['rid_string']
            . $parts['raid_string'] . $parts['range_string'] . $parts['tld_string'] . $parts['search_string'];
    }

    // The tables the list joins, and the conditions that join them, for the signed-in user's currency conversions
    public function fromSql()
    {
        return "
            FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h, domain_field_data AS dfd
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

    // The columns every row has, plus the current custom fields
    public function selectSql()
    {
        return "SELECT d.id, d.domain, d.tld, d.expiry_date, d.total_cost, d.function, d.notes, d.autorenew, d.privacy, d.creation_type_id, d.created_by, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.id AS f_id, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name" . $this->field_columns;
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
        return new DomainListColumns($settings, $this->field_columns);
    }

} //@formatter:on
