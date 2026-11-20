<?php
/**
 * /classes/DomainMOD/CostReport.php
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
 * The Domain Costs and SSL Costs reports (reporting/domains/costs.php and reporting/ssl/costs.php): what each Group By
 * choice shows, and the queries behind it. Each grouping keeps the tables and order of the report it replaced (such as
 * Domain Cost by Registrar), but groups by id rather than by name, so two records with the same name (such as two
 * owners called Greg) get a row each, each linking to its own record.
 *
 * The SQL is built only from the fixed grouping data below, never from the request: the request's group only picks a
 * grouping, and the user and the dates are bound.
 */
class CostReport
{
    /*
     * Each section's assets: their table and alias, their fee table, the statuses left out (Expired and Sold domains,
     * and Expired SSL certificates), the list their counts link to, and the grouping the report opens at
     */
    const SECTIONS = array(
        'domains' => array('table' => 'domains', 'alias' => 'd', 'fees' => 'fees', 'inactive' => "'0', '10'",
            'list' => '../../domains/index.php', 'default' => 'registrar'),
        'ssl' => array('table' => 'ssl_certs', 'alias' => 'sslc', 'fees' => 'ssl_fees', 'inactive' => "'0'",
            'list' => '../../ssl/index.php', 'default' => 'provider'),
    );

    protected $pdo;
    protected $user_id;

    // $pdo is the database connection (Database::getInstance()->cnxx), and $user_id the user whose conversion rates
    // turn every cost into their default currency
    public function __construct($pdo, $user_id)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
    }

    /*
     * The section's groupings, keyed and ordered as the Group By menu lists them (the Reporting page's order). Each
     * one has:
     *   key, label    the URL value (the old report's file name) and the menu's text
     *   title, name   the old report's page title (the h1 and the export's first row) and its short name (the chart's
     *                 and the summary's title)
     *   export        the export's file name, with the _all form for every date
     *   select, from, where, group_by, order_by
     *                 the grouped query's own columns, extra tables, their join conditions, grouping, and order
     *   left_join     (optional) a table joined with LEFT JOIN instead, as its table, alias, and ON condition, so assets
     *                 without a match are kept, as one row whose columns from that table are NULL
     *   columns       the table's columns after the control column, in order. Each has its heading, its export heading
     *                 ('csv', when it differs), and its 'value': 'field' (the row's 'field', with 'tld' for a TLD),
     *                 'cost', 'count', or 'per' (the row's cost, number, and cost per asset), 'group_cost',
     *                 'group_count', or 'group_per' (the same for the row's registrar or provider), 'account' (owner and
     *                 username), or 'year', 'month', 'month_cost', and 'year_cost' (Month). 'link' is a count's link to
     *                 the list, as its parameter and the row's id field. 'group_only' cells show only on a group's
     *                 first row in the table (every row in the export). With a left_join, a field's 'null_label' is
     *                 its text and a count's 'null_link' its link's value on the NULL row (see fieldText() and
     *                 linkValue()).
     *   chart         'doughnut' or 'bar', and its labels: 'field' (a row's field), 'tld', 'ip' (name and IP address),
     *                 'month', or the chart query's field (Registrar and Provider, which have their own chart query),
     *                 with 'null_label' for a NULL field, as for the columns
     *   subtotal      'year' (Month), or the registrar's or provider's id column and name field, with 'blank', the
     *                 text of a group cell on a group's later rows
     *   ordering      whether the table's columns sort, and 'page_length', the user setting a paged table opens at
     */
    public static function groupings($section)
    {
        $cost = array('heading' => _('Cost'), 'value' => 'cost');
        $per_domain = array('heading' => _('Per Domain'), 'value' => 'per');
        $per_cert = array('heading' => _('Per Cert'), 'value' => 'per');
        $domains = function ($param) {
            return array('heading' => _('Domains'), 'value' => 'count', 'link' => array($param, 'id'));
        };
        $ssl_certs = function ($param) {
            return array('heading' => _('SSL Certs'), 'value' => 'count', 'link' => array($param, 'id'));
        };

        if ($section === 'ssl') {

            return array(
                'category' => array('key' => 'category', 'label' => _('Category'),
                    'title' => _('SSL Cost by Category Report'), 'name' => _('SSL Cost by Category'),
                    'export' => _('ssl_cost_by_category_report'), 'export_all' => _('ssl_cost_by_category_report_all'),
                    'select' => 'cat.id, cat.name', 'from' => array('categories AS cat'),
                    'where' => array('sslc.cat_id = cat.id'), 'group_by' => 'cat.id, cat.name', 'order_by' => 'cat.name, cat.id',
                    'columns' => array(array('heading' => _('Category'), 'value' => 'field', 'field' => 'name'), $cost,
                        $ssl_certs('sslpcid'), $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
                'domain' => array('key' => 'domain', 'label' => _('Domain'),
                    'title' => _('SSL Cost by Domain Report'), 'name' => _('SSL Cost by Domain'),
                    'export' => _('ssl_cost_by_domain_report'), 'export_all' => _('ssl_cost_by_domain_report_all'),
                    // A certificate doesn't need a domain, so the certificates with no domain are one NULL row, None
                    'select' => 'd.id, d.domain', 'from' => array(), 'where' => array(),
                    'left_join' => array('table' => 'domains', 'alias' => 'd', 'on' => 'd.id = sslc.domain_id'),
                    'group_by' => 'd.id, d.domain', 'order_by' => 'd.domain, d.id',
                    'columns' => array(
                        array('heading' => _('Domain'), 'value' => 'field', 'field' => 'domain', 'null_label' => _('None')),
                        $cost, $ssl_certs('did') + array('null_link' => SslList::NO_DOMAIN), $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'domain',
                        'null_label' => _('None')), 'ordering' => true,
                    'page_length' => 's_number_of_domains'),
                'ip-address' => array('key' => 'ip-address', 'label' => _('IP Address'),
                    'title' => _('SSL Cost by IP Address Report'), 'name' => _('SSL Cost by IP Address'),
                    'export' => _('ssl_cost_by_ip_address_report'),
                    'export_all' => _('ssl_cost_by_ip_address_report_all'),
                    'select' => 'ip.id, ip.name, ip.ip, ip.rdns', 'from' => array('ip_addresses AS ip'),
                    'where' => array('sslc.ip_id = ip.id'), 'group_by' => 'ip.id, ip.name, ip.ip, ip.rdns',
                    'order_by' => 'ip.name, ip.id',
                    'columns' => array(
                        array('heading' => _('Name'), 'csv' => _('IP Address Name'), 'value' => 'field', 'field' => 'name'),
                        array('heading' => _('IP Address'), 'value' => 'field', 'field' => 'ip'),
                        array('heading' => _('rDNS'), 'value' => 'field', 'field' => 'rdns'),
                        $cost, $ssl_certs('sslipid'), $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'ip'), 'ordering' => true),
                'month' => self::month('ssl'),
                'owner' => array('key' => 'owner', 'label' => _('Owner'),
                    'title' => _('SSL Cost by Owner Report'), 'name' => _('SSL Cost by Owner'),
                    'export' => _('ssl_cost_by_owner_report'), 'export_all' => _('ssl_cost_by_owner_report_all'),
                    'select' => 'o.id, o.name', 'from' => array('owners AS o'),
                    'where' => array('sslc.owner_id = o.id'), 'group_by' => 'o.id, o.name', 'order_by' => 'o.name, o.id',
                    'columns' => array(array('heading' => _('Owner'), 'value' => 'field', 'field' => 'name'), $cost,
                        $ssl_certs('oid'), $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
                'provider' => array('key' => 'provider', 'label' => _('Provider'),
                    'title' => _('SSL Cost by Provider Report'), 'name' => _('SSL Cost by Provider'),
                    'export' => _('ssl_cost_by_provider_report'), 'export_all' => _('ssl_cost_by_provider_report_all'),
                    'select' => 'sslp.id, sslp.name AS provider_name, o.name AS owner_name, sslpa.id AS account_id, sslpa.username',
                    'from' => array('ssl_providers AS sslp', 'ssl_accounts AS sslpa', 'owners AS o'),
                    'where' => array('sslc.ssl_provider_id = sslp.id', 'sslc.account_id = sslpa.id', 'sslc.owner_id = o.id'),
                    'group_by' => 'sslp.id, sslp.name, o.id, o.name, sslpa.id, sslpa.username',
                    'order_by' => 'sslp.name, sslp.id, o.name, o.id, sslpa.username, sslpa.id',
                    'columns' => array(
                        array('heading' => _('Provider'), 'value' => 'field', 'field' => 'provider_name', 'group_only' => true),
                        array('heading' => _('Cost'), 'value' => 'group_cost', 'group_only' => true),
                        array('heading' => _('Certs'), 'value' => 'group_count', 'link' => array('sslpid', 'id'), 'group_only' => true),
                        array('heading' => _('Per Cert'), 'value' => 'group_per', 'group_only' => true),
                        array('heading' => _('Account'), 'csv' => _('Provider Account'), 'value' => 'account'),
                        $cost,
                        array('heading' => _('Certs'), 'value' => 'count', 'link' => array('sslpaid', 'account_id')),
                        $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'provider_name',
                        'select' => 'sslp.name AS provider_name', 'group_by' => 'sslp.id, sslp.name', 'order_by' => 'sslp.name, sslp.id'),
                    'subtotal' => array('id' => 'sslp.id', 'name' => 'provider_name', 'blank' => ''), 'ordering' => false),
                'type' => array('key' => 'type', 'label' => _('Type'),
                    'title' => _('SSL Cost by Type Report'), 'name' => _('SSL Cost by Type'),
                    'export' => _('ssl_cost_by_type_report'), 'export_all' => _('ssl_cost_by_type_report_all'),
                    'select' => 'sslt.id, sslt.type', 'from' => array('ssl_cert_types AS sslt'),
                    'where' => array('sslc.type_id = sslt.id'), 'group_by' => 'sslt.id, sslt.type', 'order_by' => 'sslt.type, sslt.id',
                    'columns' => array(array('heading' => _('Type'), 'value' => 'field', 'field' => 'type'), $cost,
                        array('heading' => _('Certs'), 'value' => 'count', 'link' => array('ssltid', 'id')), $per_cert),
                    'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'type'), 'ordering' => true),
            );

        }

        return array(
            'category' => array('key' => 'category', 'label' => _('Category'),
                'title' => _('Domain Cost by Category Report'), 'name' => _('Domain Cost by Category'),
                'export' => _('domain_cost_by_category_report'), 'export_all' => _('domain_cost_by_category_report_all'),
                'select' => 'cat.id, cat.name', 'from' => array('categories AS cat'),
                'where' => array('d.cat_id = cat.id'), 'group_by' => 'cat.id, cat.name', 'order_by' => 'cat.name, cat.id',
                'columns' => array(array('heading' => _('Category'), 'value' => 'field', 'field' => 'name'), $cost,
                    $domains('pcid'), $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
            'dns' => array('key' => 'dns', 'label' => _('DNS Profile'),
                'title' => _('Domain Cost by DNS Profile Report'), 'name' => _('Domain Cost by DNS Profile'),
                'export' => _('domain_cost_by_dns_report'), 'export_all' => _('domain_cost_by_dns_report_all'),
                'select' => 'dns.id, dns.name', 'from' => array('dns AS dns'),
                'where' => array('d.dns_id = dns.id'), 'group_by' => 'dns.id, dns.name', 'order_by' => 'dns.name, dns.id',
                'columns' => array(array('heading' => _('DNS Profile'), 'value' => 'field', 'field' => 'name'), $cost,
                    $domains('dnsid'), $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
            'ip-address' => array('key' => 'ip-address', 'label' => _('IP Address'),
                'title' => _('Domain Cost by IP Address Report'), 'name' => _('Domain Cost by IP Address'),
                'export' => _('domain_cost_by_ip_address_report'),
                'export_all' => _('domain_cost_by_ip_address_report_all'),
                'select' => 'ip.id, ip.name, ip.ip, ip.rdns', 'from' => array('ip_addresses AS ip'),
                'where' => array('d.ip_id = ip.id'), 'group_by' => 'ip.id, ip.name, ip.ip, ip.rdns',
                    'order_by' => 'ip.name, ip.id',
                'columns' => array(
                    array('heading' => _('Name'), 'csv' => _('IP Address Name'), 'value' => 'field', 'field' => 'name'),
                    array('heading' => _('IP Address'), 'value' => 'field', 'field' => 'ip'),
                    array('heading' => _('rDNS'), 'value' => 'field', 'field' => 'rdns'),
                    $cost, $domains('ipid'), $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'ip'), 'ordering' => true),
            'month' => self::month('domains'),
            'owner' => array('key' => 'owner', 'label' => _('Owner'),
                'title' => _('Domain Cost by Owner Report'), 'name' => _('Domain Cost by Owner'),
                'export' => _('domain_cost_by_owner_report'), 'export_all' => _('domain_cost_by_owner_report_all'),
                'select' => 'o.id, o.name', 'from' => array('owners AS o'),
                'where' => array('d.owner_id = o.id'), 'group_by' => 'o.id, o.name', 'order_by' => 'o.name, o.id',
                'columns' => array(array('heading' => _('Owner'), 'value' => 'field', 'field' => 'name'), $cost,
                    $domains('oid'), $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
            'registrar' => array('key' => 'registrar', 'label' => _('Registrar'),
                'title' => _('Domain Cost by Registrar Report'), 'name' => _('Domain Cost by Registrar'),
                'export' => _('domain_cost_by_registrar_report'), 'export_all' => _('domain_cost_by_registrar_report_all'),
                'select' => 'r.id, r.name AS registrar_name, o.name AS owner_name, ra.id AS account_id, ra.username',
                'from' => array('registrars AS r', 'registrar_accounts AS ra', 'owners AS o'),
                'where' => array('d.registrar_id = r.id', 'd.account_id = ra.id', 'd.owner_id = o.id'),
                'group_by' => 'r.id, r.name, o.id, o.name, ra.id, ra.username',
                'order_by' => 'r.name, r.id, o.name, o.id, ra.username, ra.id',
                'columns' => array(
                    array('heading' => _('Registrar'), 'value' => 'field', 'field' => 'registrar_name', 'group_only' => true),
                    array('heading' => _('Cost'), 'value' => 'group_cost', 'group_only' => true),
                    array('heading' => _('Domains'), 'value' => 'group_count', 'link' => array('rid', 'id'), 'group_only' => true),
                    array('heading' => _('Per Domain'), 'value' => 'group_per', 'group_only' => true),
                    array('heading' => _('Account'), 'csv' => _('Registrar Account'), 'value' => 'account'),
                    $cost,
                    array('heading' => _('Domains'), 'value' => 'count', 'link' => array('raid', 'account_id')),
                    $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'registrar_name',
                    'select' => 'r.name AS registrar_name', 'group_by' => 'r.id, r.name', 'order_by' => 'r.name, r.id'),
                'subtotal' => array('id' => 'r.id', 'name' => 'registrar_name', 'blank' => ''), 'ordering' => false),
            'tld' => array('key' => 'tld', 'label' => _('TLD'),
                'title' => _('Domain Cost by TLD Report'), 'name' => _('Domain Cost by TLD'),
                'export' => _('domain_cost_by_tld_report'), 'export_all' => _('domain_cost_by_tld_report_all'),
                'select' => 'd.tld', 'from' => array(), 'where' => array(), 'group_by' => 'd.tld', 'order_by' => 'd.tld',
                'columns' => array(array('heading' => _('TLD'), 'value' => 'field', 'field' => 'tld', 'tld' => true),
                    $cost, array('heading' => _('Domains'), 'value' => 'count', 'link' => array('tld', 'tld')),
                    $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'tld'), 'ordering' => true,
                // The export's Date Range row has the start and end dates in cells of their own
                'range_cells' => true),
            'host' => array('key' => 'host', 'label' => _('Web Host'),
                'title' => _('Domain Cost by Web Host Report'), 'name' => _('Domain Cost by Web Host'),
                'export' => _('domain_cost_by_web_host_report'), 'export_all' => _('domain_cost_by_web_host_report_all'),
                'select' => 'wh.id, wh.name', 'from' => array('hosting AS wh'),
                'where' => array('d.hosting_id = wh.id'), 'group_by' => 'wh.id, wh.name', 'order_by' => 'wh.name, wh.id',
                // The number of domains comes before the cost here
                'columns' => array(
                    array('heading' => _('Host'), 'csv' => _('Web Hosting Provider'), 'value' => 'field', 'field' => 'name'),
                    $domains('whid'), $cost, $per_domain),
                'chart' => array('type' => 'doughnut', 'label' => 'field', 'field' => 'name'), 'ordering' => true),
        );
    }

    // The Month grouping, the same for both sections apart from its names. The year and its cost show only on a year's
    // first row in the table.
    protected static function month($section)
    {
        return array('key' => 'month', 'label' => _('Month'),
            'title' => $section === 'ssl' ? _('SSL Cost by Month Report') : _('Domain Cost by Month Report'),
            'name' => $section === 'ssl' ? _('SSL Cost by Month') : _('Domain Cost by Month'),
            'export' => $section === 'ssl' ? _('ssl_cost_by_month_report') : _('domain_cost_by_month_report'),
            'export_all' => $section === 'ssl' ? _('ssl_cost_by_month_report_all') : _('domain_cost_by_month_report_all'),
            'from' => array(), 'where' => array(),
            'columns' => array(
                array('heading' => _('Year'), 'value' => 'year', 'group_only' => true),
                array('heading' => _('Month'), 'value' => 'month'),
                array('heading' => _('Cost'), 'value' => 'month_cost'),
                array('heading' => _('By Year'), 'value' => 'year_cost', 'group_only' => true)),
            'chart' => array('type' => 'bar', 'label' => 'month'),
            'subtotal' => array('year' => true, 'name' => 'year', 'blank' => '&nbsp;'), 'ordering' => false);
    }

    /*
     * What marks a row's group in the table, whose cells show only on the group's first row: the registrar's or
     * provider's id (so two with the same name stay apart), or the year (Month). Groupings without a subtotal have none.
     */
    public static function groupKey($grouping, $row)
    {
        if (!isset($grouping['subtotal'])) return null;
        if (isset($grouping['subtotal']['id'])) return $row->id;
        return $row->{$grouping['subtotal']['name']};
    }

    // One grouping of the section, or the section's default when $key isn't one of its groupings (or isn't text)
    public static function grouping($section, $key)
    {
        $section = isset(self::SECTIONS[$section]) ? $section : 'domains';
        $groupings = self::groupings($section);
        if (is_string($key) && isset($groupings[$key])) return $groupings[$key];
        return $groupings[self::SECTIONS[$section]['default']];
    }

    /*
     * Where an old report's address (such as reporting/domains/cost-by-registrar.php) now leads: its grouping on the
     * section's costs page, relative to the old page's folder. Only the date range and an export request are carried
     * over, as the old page read them: the range from the form's POST first and otherwise from the address (as
     * $_REQUEST does), and the export only when export_data is exactly 1 in the address. $post and $get default to
     * the request's.
     */
    public static function redirectUrl($section, $key, $post = null, $get = null)
    {
        $post = is_array($post) ? $post : $_POST;
        $get = is_array($get) ? $get : $_GET;

        $params = array('group' => self::grouping($section, $key)['key']);
        $daterange = $post['daterange'] ?? $get['daterange'] ?? null;
        if (is_string($daterange) && $daterange !== '') $params['daterange'] = $daterange;
        if (self::exportRequested($get)) $params['export_data'] = 1;

        return 'costs.php?' . http_build_query($params);
    }

    // Whether the address asks for the export: export_data is text whose number is exactly 1
    public static function exportRequested($get)
    {
        return isset($get['export_data']) && is_string($get['export_data']) && (int) $get['export_data'] === 1;
    }

    /*
     * The grouped rows. Month lists every month with an asset, without the conversion rates (as the old report did),
     * and its costs come from subtotals().
     */
    public function rows($section, $grouping, $start_date, $end_date)
    {
        if ($grouping['key'] === 'month') {
            $expiry = self::SECTIONS[$section]['alias'] . '.expiry_date';
            return $this->run($section, $grouping, 'YEAR(' . $expiry . ') AS year, MONTH(' . $expiry . ') AS month',
                'year, month', 'year, month', $start_date, $end_date, false);
        }

        return $this->run($section, $grouping, $grouping['select'] . ', ' . $this->costColumns($section),
            $grouping['group_by'], $grouping['order_by'], $start_date, $end_date);
    }

    // The report's total cost (grand_total) and number of assets (number_total), with the grouping's own tables
    public function totals($section, $grouping, $start_date, $end_date)
    {
        $rows = $this->run($section, $grouping,
            'SUM(' . self::SECTIONS[$section]['alias'] . '.total_cost * cc.conversion) AS grand_total, count(*) AS number_total',
            '', '', $start_date, $end_date);

        return $rows[0] ?? (object) array('grand_total' => null, 'number_total' => 0);
    }

    // The chart's own rows, for a grouping with its own chart query (Registrar and Provider, by id), or none
    public function chartRows($section, $grouping, $start_date, $end_date)
    {
        if (!isset($grouping['chart']['select'])) return array();

        return $this->run($section, $grouping, $grouping['chart']['select'] . ', SUM(' .
            self::SECTIONS[$section]['alias'] . '.total_cost * cc.conversion) AS total_cost',
            $grouping['chart']['group_by'], $grouping['chart']['order_by'], $start_date, $end_date);
    }

    /*
     * The costs a row looks up rather than carries. Month: 'month' (keyed year-month) and 'year' (keyed year), each a
     * cost. Registrar and Provider: 'group', keyed by the registrar's or provider's id, each with total_cost and
     * number_of_assets. Other groupings have none.
     */
    public function subtotals($section, $grouping, $start_date, $end_date)
    {
        if (!isset($grouping['subtotal'])) return array();

        $alias = self::SECTIONS[$section]['alias'];
        $cost = 'SUM(' . $alias . '.total_cost * cc.conversion)';

        if (!empty($grouping['subtotal']['year'])) {
            $expiry = $alias . '.expiry_date';
            $subtotals = array('month' => array(), 'year' => array());
            foreach ($this->run($section, $grouping, 'YEAR(' . $expiry . ') AS year, MONTH(' . $expiry .
                ') AS month, ' . $cost . ' AS cost', 'year, month', '', $start_date, $end_date) as $row) {
                $subtotals['month'][$row->year . '-' . $row->month] = $row->cost;
            }
            foreach ($this->run($section, $grouping, 'YEAR(' . $expiry . ') AS year, ' . $cost . ' AS cost', 'year',
                '', $start_date, $end_date) as $row) {
                $subtotals['year'][$row->year] = $row->cost;
            }
            return $subtotals;
        }

        $subtotals = array('group' => array());
        foreach ($this->run($section, $grouping, $grouping['subtotal']['id'] . ' AS id, ' .
            $this->costColumns($section), $grouping['subtotal']['id'], '', $start_date, $end_date) as $row) {
            $subtotals['group'][$row->id] = $row;
        }
        return $subtotals;
    }

    // A 'field' column's text for a row: the row's field, or the column's null_label when the field is NULL
    public static function fieldText($column, $row)
    {
        $text = $row->{$column['field']};
        if ($text === null && isset($column['null_label'])) return $column['null_label'];
        return $text;
    }

    // The value a count's link gives the list: the row's id field, or the column's null_link when that's NULL
    public static function linkValue($column, $row)
    {
        $value = $row->{$column['link'][1]};
        if ($value === null && isset($column['null_link'])) return $column['null_link'];
        return $value;
    }

    /*
     * The chart's labels and values: the grouped rows' (or Registrar's and Provider's chart rows'), or for Month, each
     * listed month with its cost (0 when it has none)
     */
    public function chartData($grouping, $rows, $chart_rows, $subtotals)
    {
        $reporting = new Reporting();
        $chart = $grouping['chart'];
        $source = isset($chart['select']) ? $chart_rows : $rows;

        $labels = array();
        $values = array();
        foreach ($source as $row) {
            if ($chart['label'] === 'month') {
                $labels[] = $reporting->monthName($row->month) . ' ' . $row->year;
                $values[] = $subtotals['month'][$row->year . '-' . $row->month] ?? 0;
                continue;
            }
            if ($chart['label'] === 'tld') {
                $labels[] = '.' . $row->tld;
            } elseif ($chart['label'] === 'ip') {
                $labels[] = $row->name . ' (' . $row->ip . ')';
            } else {
                $labels[] = self::fieldText($chart, $row);
            }
            $values[] = $row->total_cost;
        }

        return array($labels, $values);
    }

    // The cost and the number of assets of each group
    protected function costColumns($section)
    {
        return 'SUM(' . self::SECTIONS[$section]['alias'] . '.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_assets';
    }

    /*
     * The query's SQL and its parameters: the section's live assets with a fee, joined to the grouping's own tables
     * (and its LEFT JOIN, if it has one), and (unless $with_rates is false) to the user's conversion rates, between the
     * dates when both are set
     */
    public function sql($section, $grouping, $select, $group_by, $order_by, $start_date, $end_date, $with_rates = true)
    {
        $assets = self::SECTIONS[$section];
        $a = $assets['alias'];

        // A grouping's LEFT JOIN is written straight after the asset's table, as its ON can only name that table
        $asset_table = $assets['table'] . ' AS ' . $a;
        if (isset($grouping['left_join'])) {
            $join = $grouping['left_join'];
            $asset_table .= ' LEFT JOIN ' . $join['table'] . ' AS ' . $join['alias'] . ' ON ' . $join['on'];
        }
        $from = array($asset_table, $assets['fees'] . ' AS f', 'currencies AS c');
        $where = array($a . '.fee_id = f.id', 'f.currency_id = c.id');
        $params = array();
        if ($with_rates) {
            $from[] = 'currency_conversions AS cc';
            $where[] = 'c.id = cc.currency_id';
        }
        $from = array_merge($from, $grouping['from']);
        $where = array_merge($where, $grouping['where']);
        $where[] = $a . '.active NOT IN (' . $assets['inactive'] . ')';
        if ($with_rates) {
            $where[] = 'cc.user_id = :user_id';
            $params['user_id'] = $this->user_id;
        }
        if ($start_date != '' && $end_date != '') {
            $where[] = $a . '.expiry_date BETWEEN :start_date AND :end_date';
            $params['start_date'] = (string) $start_date;
            $params['end_date'] = (string) $end_date;
        }

        $sql = 'SELECT ' . $select . '
            FROM ' . implode(', ', $from) . '
            WHERE ' . implode('
              AND ', $where);
        if ($group_by !== '') $sql .= '
            GROUP BY ' . $group_by;
        if ($order_by !== '') $sql .= '
            ORDER BY ' . $order_by;

        return array($sql, $params);
    }

    protected function run($section, $grouping, $select, $group_by, $order_by, $start_date, $end_date, $with_rates = true)
    {
        list($sql, $params) = $this->sql($section, $grouping, $select, $group_by, $order_by, $start_date, $end_date,
            $with_rates);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, $name === 'user_id' ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

} //@formatter:on
