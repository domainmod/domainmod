<?php
/**
 * /classes/DomainMOD/DomainListColumns.php
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
 * The Domains list's columns and each row's cells, for the page's own first page and for the rows the server-side
 * data endpoint (domains/list-data.php) sends, so the two can't differ. The columns follow the user's Display Settings
 * and custom fields, given as a copy of the session values ($settings), so the endpoint can let go of the session
 * early. The cells are built exactly as the list has always drawn them, with the same escaping (values from the
 * database are stored sanitized).
 */
class DomainListColumns
{
    // Each optional column's key, its Display Settings session key, and its sort expressions
    const OPTIONAL = array(
        'expiry' => array('s_display_domain_expiry_date', array('d.expiry_date')),
        'fee' => array('s_display_domain_fee', array('d.total_cost * cc.conversion')),
        'tld' => array('s_display_domain_tld', array('d.tld')),
        'registrar' => array('s_display_domain_registrar', array('r.name')),
        'account' => array('s_display_domain_account', array('r.name', 'o.name', 'ra.username')),
        'dns' => array('s_display_domain_dns', array('dns.name')),
        'ip' => array('s_display_domain_ip', array('ip.name', 'ip.ip')),
        'host' => array('s_display_domain_host', array('h.name')),
        'category' => array('s_display_domain_category', array('cat.name')),
        'owner' => array('s_display_domain_owner', array('o.name')));

    // The session values the columns and cells read, copied by sessionCopy()
    const SESSION_KEYS = array('s_display_domain_expiry_date', 's_display_domain_fee', 's_display_domain_tld',
        's_display_domain_registrar', 's_display_domain_account', 's_display_domain_dns', 's_display_domain_ip',
        's_display_domain_host', 's_display_domain_category', 's_display_domain_owner', 's_cdf_data',
        's_default_currency', 's_default_currency_symbol', 's_default_currency_symbol_order',
        's_default_currency_symbol_space');

    private $settings;
    private $layout;
    private $currency;
    private $columns;

    /*
     * $settings is sessionCopy()'s array. $current_fields is the custom field columns that exist now
     * (CustomField::getCustomFieldsSql('domain_fields', 'dfd')): the session can still list a field that's since been
     * deleted, which would otherwise read a missing column.
     */
    public function __construct(array $settings, $current_fields_sql)
    {
        $this->settings = $settings;
        $this->layout = new Layout();
        $this->currency = new Currency();

        preg_match_all('/dfd\.([A-Za-z0-9_]+)/', (string) $current_fields_sql, $matches);
        $current_fields = $matches[1];

        $columns = array(array('key' => 'control', 'title' => '', 'sort' => array()));
        $columns[] = array('key' => 'domain', 'title' => _('Domain'), 'sort' => array('d.domain'));
        $titles = array('expiry' => _('Expiry'),
            'fee' => _('Fee') . ' (' . ($settings['s_default_currency'] ?? '') . ')',
            'tld' => _('TLD'), 'registrar' => _('Registrar'), 'account' => _('Account'), 'dns' => _('DNS'),
            'ip' => _('IP'), 'host' => _('Host'), 'category' => _('Category'), 'owner' => _('Owner'));
        foreach (self::OPTIONAL as $key => $optional) {
            if (($settings[$optional[0]] ?? '') == '1') {
                $columns[] = array('key' => $key, 'title' => $titles[$key], 'sort' => $optional[1]);
            }
        }

        foreach ((array) ($settings['s_cdf_data'] ?? array()) as $field) {
            // Text Areas get no column, and neither does a field that no longer exists
            if (($field['value'] ?? '') != '1' || ($field['type_id'] ?? '') == '3') continue;
            if (!in_array($field['field'] ?? '', $current_fields, true)) continue;
            $columns[] = array('key' => 'cf_' . $field['field'], 'title' => $field['name'], 'sort' => array('dfd.' . $field['field']),
                'field' => $field);
        }

        $this->columns = $columns;
    }

    // The session values the columns need, so the endpoint can close the session before running its queries
    public static function sessionCopy(array $session)
    {
        $copy = array();
        foreach (self::SESSION_KEYS as $key) {
            $copy[$key] = $session[$key] ?? '';
        }
        return $copy;
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
     * values come first when ascending and last when descending, as DataTables placed them. Only the domains the list's
     * filters leave are checked, as DataTables only saw those: $from_sql is DomainList::fromSql() followed by the
     * filter conditions. Call it before sortMap().
     */
    public function useNumericSort(\PDO $pdo, $from_sql)
    {
        foreach ($this->columns as $index => $column) {
            if (!isset($column['field']) || $column['field']['type_id'] != '2') continue;
            $name = 'dfd.' . $column['field']['field'];
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

    // The sort expressions by column index, for DomainList::orderSql(). The control column doesn't sort.
    public function sortMap()
    {
        $map = array();
        foreach ($this->columns as $index => $column) {
            if ($column['sort']) $map[$index] = $column['sort'];
        }
        return $map;
    }

    // The default order: the expiry date when it shows, otherwise the domain, both ascending
    public function defaultOrder()
    {
        foreach ($this->columns as $index => $column) {
            if ($column['key'] === 'expiry') return array(array($index, 'asc'));
        }
        return array(array(1, 'asc'));
    }

    /*
     * The search box's expressions for the visible columns, following what each cell shows: 'lower' ones are compared
     * with the word in lowercase (the domain and TLD, which are stored in lowercase in case-sensitive columns), 'typed'
     * ones with the word as typed (the expiry date, and text, date, and time stamp custom fields, with the empty
     * placeholder dates left out), and 'encoded' ones with the word HTML-encoded, as asset names are stored.
     */
    public function searchExpressions()
    {
        $lower = array('d.domain');
        $typed = array();
        $encoded = array();

        foreach ($this->columns as $column) {
            switch ($column['key']) {
                case 'expiry': $typed[] = 'CAST(d.expiry_date AS CHAR)'; break;
                case 'tld': $lower[] = 'd.tld'; break;
                case 'registrar': $encoded[] = 'r.name'; break;
                case 'account': $encoded[] = "CONCAT(r.name, ', ', o.name, ' (', ra.username, ')')"; break;
                case 'dns': $encoded[] = 'dns.name'; break;
                case 'ip': $encoded[] = "CONCAT(ip.name, ' (', ip.ip, ')')"; break;
                case 'host': $encoded[] = 'h.name'; break;
                case 'category': $encoded[] = 'cat.name'; break;
                case 'owner': $encoded[] = 'o.name'; break;
                default:
                    if (!isset($column['field'])) break;
                    $name = 'dfd.' . $column['field']['field'];
                    $type = $column['field']['type_id'];
                    if ($type == '2') {
                        $typed[] = $name;
                    } elseif ($type == '4') {
                        $typed[] = "CASE WHEN " . $name . " IN ('1970-01-01', '0000-00-00') THEN '' ELSE CAST(" . $name . " AS CHAR) END";
                    } elseif ($type == '5') {
                        $typed[] = "CASE WHEN " . $name . " IN ('1970-01-01 00:00:00', '0000-00-00 00:00:00') THEN '' ELSE CAST(" . $name . " AS CHAR) END";
                    }
            }
        }

        return array('lower' => $lower, 'typed' => $typed, 'encoded' => $encoded);
    }

    // A row's cells as HTML, in the columns' order. $today is the date past which a live domain's expiry is marked.
    public function cells($row, $today)
    {
        $cells = array();
        foreach ($this->columns as $column) {
            $cells[] = $this->cell($column, $row, $today);
        }
        return $cells;
    }

    private function cell(array $column, $row, $today)
    {
        $layout = $this->layout;
        $s = $this->settings;

        switch ($column['key']) {

            case 'control':
                return '';

            case 'domain':
                $html = '';
                $badges = array(
                    '0' => array('x', _('Inactive Domain'), 'secondary', 'bi-pause-circle'),
                    '2' => array(_('Pending Transfer Characters'), _('Pending (Transfer)'), 'warning', 'bi-arrow-left-right'),
                    '3' => array(_('Pending Renewal Characters'), _('Pending (Renewal)'), 'warning', 'bi-arrow-repeat'),
                    '4' => array(_('Pending Other Characters'), _('Pending (Other)'), 'warning', 'bi-three-dots'),
                    '5' => array(_('Pending Registration Characters'), _('Pending (Registration)'), 'warning', 'bi-plus-circle'),
                    '10' => array(_('Pending Sold Characters'), _('Sold'), 'success', 'bi-tag'));
                if (isset($badges[(string) $row->active])) {
                    $badge = $badges[(string) $row->active];
                    $html .= $layout->statusBadge($badge[0], $badge[1], $badge[2], $badge[3]) . '&nbsp;';
                }
                $html .= '<a href="edit.php?did=' . $row->id . '">' . $row->domain . '</a>';
                if ($row->privacy == '1') {
                    $html .= '&nbsp;' . $layout->statusBadge(_('Private WHOIS Characters'), _('Private WHOIS Registration'), 'muted', 'bi-shield-lock-fill');
                }
                return $html;

            case 'expiry':
                $expired = '';
                if (!in_array($row->active, array('0', '10')) && $row->expiry_date < $today) {
                    $expired = ' class="domainmod-css-expired" data-bs-toggle="tooltip" data-bs-title="'
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . $row->expiry_date . ', '
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '"';
                }
                return '<a href="edit.php?did=' . $row->id . '"' . $expired . '>' . $row->expiry_date . '</a>';

            case 'fee':
                $amount = $this->currency->format($row->total_cost * $row->conversion, $s['s_default_currency_symbol'],
                    $s['s_default_currency_symbol_order'], $s['s_default_currency_symbol_space']);
                return '<a href="../assets/edit/registrar-fee.php?rid=' . $row->r_id . '&fee_id=' . $row->f_id . '">'
                    . htmlentities($amount, ENT_QUOTES, 'UTF-8') . '</a>';

            case 'tld':
                return '<a href="../assets/edit/registrar-fee.php?rid=' . $row->r_id . '&fee_id=' . $row->f_id
                    . '"><bdi dir="ltr">.' . $row->tld . '</bdi></a>';

            case 'registrar':
                return '<a href="../assets/edit/registrar.php?rid=' . $row->r_id . '">' . $row->registrar_name . '</a>';

            case 'account':
                return '<a href="../assets/edit/registrar.php?rid=' . $row->r_id . '">' . $row->registrar_name . '</a>, '
                    . '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a> '
                    . '(<a href="../assets/edit/registrar-account.php?raid=' . $row->ra_id . '">' . substr($row->username, 0, 15)
                    . (strlen($row->username) >= 16 ? '...' : '') . '</a>)';

            case 'dns':
                return '<a href="../assets/edit/dns.php?dnsid=' . $row->dnsid . '">' . $row->dns_name . '</a>';

            case 'ip':
                return '<a href="../assets/edit/ip-address.php?ipid=' . $row->ipid . '">' . $row->ip_name . ' (' . $row->ip . ')</a>';

            case 'host':
                return '<a href="../assets/edit/host.php?whid=' . $row->whid . '">' . $row->wh_name . '</a>';

            case 'category':
                return '<a href="../assets/edit/category.php?pcid=' . $row->pcid . '">' . $row->category_name . '</a>';

            case 'owner':
                return '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a>';

            default:
                $field = $column['field'];
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
    }

} //@formatter:on
