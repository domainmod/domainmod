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
 * DataTables fetches afterwards, the counts, Export, and the Raw List can't drift apart.
 *
 * The filter conditions are the same SQL fragments the page has always used (whole numbers cast, the TLD, dates, and
 * keyword quoted by PDO, and a segment's stored, already-quoted domain list), so the results don't change. The search
 * box's conditions (searchSql()) use bound parameters, and sorting (orderSql()) only ever uses the fixed column map in
 * DomainListColumns, never text from the request.
 */
class DomainList
{
    // The largest page anyone can ask for, whatever the page-size preference says
    const MAX_PAGE_SIZE = 500;

    // The search box's limits, checked before any SQL is built
    const MAX_SEARCH_LENGTH = 200;
    const MAX_SEARCH_WORDS = 10;

    const VALID_IS_ACTIVE = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'LIVE', 'ALL');

    private $pdo;
    private $user_id;
    private $dfd_columns;

    // $dfd_columns is CustomField::getCustomFieldsSql('domain_fields', 'dfd'), the current custom field columns
    public function __construct(\PDO $pdo, $user_id, $dfd_columns)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
        $this->dfd_columns = (string) $dfd_columns;
    }

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
        return "SELECT d.id, d.domain, d.tld, d.expiry_date, d.total_cost, d.function, d.notes, d.autorenew, d.privacy, d.creation_type_id, d.created_by, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.id AS f_id, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name" . $this->dfd_columns;
    }

    // How many domains match the filters ($filter_sql from filterSql())
    public function count($filter_sql)
    {
        return (int) $this->pdo->query("SELECT count(*)" . $this->fromSql() . $filter_sql)->fetchColumn();
    }

    /*
     * How many domains match the filters, and how many of those also match the search box ($search from searchSql()),
     * as array(total, filtered), counted in one pass over the rows rather than two
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
     * The page sizes the list offers, and the default one: the user's preference (only when it's from 1 to 500, as
     * Display Settings accepts any whole number), then 50, 75, 100, 250, and 500. There's no "All", so no request can
     * fetch the whole table.
     */
    public static function pageSizes($preference)
    {
        $preference = filter_var($preference, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1,
            'max_range' => self::MAX_PAGE_SIZE)));
        if ($preference === false) $preference = 50;

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
    private static function likeValue($word)
    {
        return '%' . addcslashes($word, '\\%_') . '%';
    }

    /*
     * The search box's conditions, as " AND ..." SQL and its bound parameters. Each word must appear in at least one
     * of the visible columns' search expressions ($columns from DomainListColumns::searchExpressions()), an excluded
     * word in none. The domain and TLD are matched against the word in lowercase (they're stored that way, in
     * case-sensitive columns), asset names against the HTML-encoded word (they're stored encoded), and the dates and
     * custom fields against the word as typed. $tokens comes from searchTokens().
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

            // The domain also matches its stored (IDN) form of the word, as the keyword filter does
            $idn = Domain::normalize($word);
            if ($idn !== '' && $idn !== mb_strtolower($word, 'UTF-8')) {
                $name = 'sw' . $n++;
                $alternatives[] = "d.domain LIKE :" . $name . " ESCAPE '\\\\'";
                $params[$name] = self::likeValue($idn);
            }

            foreach ($columns['encoded'] as $expression) {
                $name = 'sw' . $n++;
                $alternatives[] = $expression . " LIKE :" . $name . " ESCAPE '\\\\'";
                $params[$name] = $encoded;
            }

            $group = '(' . implode(' OR ', $alternatives) . ')';
            $sql .= $token['negate'] ? " AND NOT " . $group : " AND " . $group;
        }

        return array('sql' => $sql, 'params' => $params);
    }

    /*
     * DataTables' order requests as ORDER BY, through the columns' fixed sort expressions ($sort_map from
     * DomainListColumns::sortMap(), by column index). An index or direction that isn't known is skipped, and when
     * nothing is left, $default (a list of array(index, direction)) is used. Every order ends with the domain and the
     * id, so pages stay stable when values tie.
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

        $terms[] = 'd.domain ASC';
        $terms[] = 'd.id ASC';

        return ' ORDER BY ' . implode(', ', $terms);
    }

} //@formatter:on
