<?php
/**
 * /classes/DomainMOD/HostingListColumns.php
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
 * The Hosting Services list's columns and each row's cells, for the page's own first page and for the rows the
 * server-side data endpoint (hosting/list-data.php) sends, so the two can't differ. The columns follow the user's Display
 * Settings and custom fields, given as a copy of the session values ($settings), so the endpoint can let go of the
 * session early. Values from the database are stored sanitized, as on the SSL Certificates list (see SslListColumns),
 * and the list keeps every column and scrolls sideways with the name kept in view (see css/datatables/hosting-main.css),
 * so the name comes first. The Billing Term and Hostname always show. The Fee setting shows two columns: the cost of one
 * term and its yearly figure (cost * 12 / term), so monthly and yearly services can be compared. A service with no fee
 * shows "No fee" in both, and one whose fee's currency has no conversion rate for the user shows "No rate".
 */
class HostingListColumns extends ListColumns
{
    // Each optional column's key, its Display Settings session key (or '' for one that always shows), and its sort
    // expressions (the yearly figure's is built from Hosting::yearlyCostSql() in the constructor), in the order they show,
    // with the hostname straight after the name
    const OPTIONAL = array(
        'hostname' => array('', array('hs.hostname')),
        'expiry' => array('s_display_hosting_expiry_date', array('hs.expiry_date')),
        'term' => array('', array('hs.billing_term')),
        'fee' => array('s_display_hosting_fee', array('hs.total_cost * cc.conversion')),
        'yearly' => array('s_display_hosting_fee', array()),
        'provider' => array('s_display_hosting_provider', array('h.name')),
        'account' => array('s_display_hosting_account', array('h.name', 'o.name', 'hpa.username')),
        'type' => array('s_display_hosting_type', array('ht.type')),
        'ip' => array('s_display_hosting_ip', array('ip.name', 'ip.ip')),
        'category' => array('s_display_hosting_category', array('cat.name')),
        'owner' => array('s_display_hosting_owner', array('o.name')));

    // The session values the columns and cells read, copied by sessionCopy()
    const SESSION_KEYS = array('s_display_hosting_expiry_date', 's_display_hosting_fee', 's_display_hosting_provider',
        's_display_hosting_account', 's_display_hosting_type', 's_display_hosting_ip', 's_display_hosting_category',
        's_display_hosting_owner', 's_chf_data', 's_default_currency', 's_default_currency_symbol',
        's_default_currency_symbol_order', 's_default_currency_symbol_space', 's_read_only');

    const FIELD_ALIAS = 'hsfd';
    const NAME_KEY = 'name';

    // The installation's base path (WEB_ROOT), put in front of the "No fee" cell's link, as FeeStatus puts it in front
    // of its own
    protected $web_root;

    /*
     * $settings is sessionCopy()'s array. $fields is the list's custom fields as they exist now
     * (CustomField::getFilterFields('hosting_service_fields')), which give each custom field column its title and type:
     * the session's list (s_chf_data) only says which ones are shown, and can still list a field that's since been
     * deleted or replaced. $web_root is the installation's base path, WEB_ROOT when it's left out.
     */
    public function __construct(array $settings, array $fields, $web_root = null)
    {
        $this->settings = $settings;
        $this->web_root = $web_root ?? (defined('WEB_ROOT') ? WEB_ROOT : '');
        $this->layout = new Layout();
        $this->currency = new Currency();

        $columns = array(array('key' => 'name', 'title' => _('Name'), 'sort' => array('hs.name')));
        $titles = array('expiry' => _('Renewal Date'), 'term' => _('Billing Term'), 'fee' => _('Fee'),
            'yearly' => _('Yearly Cost'), 'hostname' => _('Hostname'), 'provider' => _('Provider'),
            'account' => _('Account'), 'type' => _('Type'), 'ip' => _('IP Address'), 'category' => _('Category'),
            'owner' => _('Owner'));
        foreach (self::OPTIONAL as $key => $optional) {
            if ($optional[0] === '' || ($settings[$optional[0]] ?? '') == '1') {
                $sort = $key === 'yearly' ? array(Hosting::yearlyCostSql('hs', 'hs.total_cost * cc.conversion')) : $optional[1];
                $columns[] = array('key' => $key, 'title' => $titles[$key], 'sort' => $sort);
            }
        }

        $this->columns = $this->withCustomFields($columns, $settings['s_chf_data'] ?? array(), $fields);
    }

    /*
     * The search box's expressions for the visible columns, following what each cell shows, as on the SSL Certificates
     * list: 'typed' ones compared with the word as typed (the renewal date, and text, date, and time stamp custom
     * fields), and 'encoded' ones with the word HTML-encoded, as names, hostnames, and asset names are stored. The name
     * always shows, so it's always searched. A service with no IP address is searched as the "None" its cell shows, so
     * an excluded word (see ListQuery::searchSql()) never turns its condition NULL and hides it. The term and costs
     * aren't searched, as SSL's fee isn't.
     */
    public function searchExpressions()
    {
        $typed = array();
        $encoded = array('hs.name');

        foreach ($this->columns as $column) {
            switch ($column['key']) {
                case 'expiry': $typed[] = 'CAST(hs.expiry_date AS CHAR)'; break;
                case 'hostname': $encoded[] = 'hs.hostname'; break;
                case 'provider': $encoded[] = 'h.name'; break;
                case 'account': $encoded[] = "CONCAT(h.name, ', ', o.name, ' (', hpa.username, ')')"; break;
                case 'type': $encoded[] = 'ht.type'; break;
                case 'ip': $encoded[] = "COALESCE(CONCAT(ip.name, ' (', ip.ip, ')'), " . self::sqlText((new Sanitize())->text(_('None'))) . ")"; break;
                case 'category': $encoded[] = 'cat.name'; break;
                case 'owner': $encoded[] = 'o.name'; break;
                case 'term': case 'fee': case 'yearly': break;
                default:
                    if (isset($column['field'])) $typed = $this->customFieldSearch($column, $typed);
            }
        }

        return array('lower' => array(), 'typed' => $typed, 'idn' => array(), 'encoded' => $encoded);
    }

    // A row's cell for one column. $today is the hosting date (Hosting::hostingDate()), past which a live service's
    // renewal date is marked with its renewal state (Hosting::renewalState()): amber with an hourglass while it waits for
    // its renewal to be confirmed, and red with a warning sign once it's overdue, as on the Renewal Calendar.
    protected function cell(array $column, $row, $today)
    {
        $layout = $this->layout;
        $s = $this->settings;

        switch ($column['key']) {

            case 'name':
                $html = '';
                $badges = array(
                    '0' => array('x', _('Cancelled'), 'secondary', 'bi-x-circle'),
                    '3' => array(_('Pending Renewal Characters'), _('Pending (Renewal)'), 'warning', 'bi-arrow-repeat'),
                    '4' => array(_('Pending Other Characters'), _('Pending (Other)'), 'warning', 'bi-three-dots'),
                    '5' => array(_('Pending Registration Characters'), _('Pending (Setup)'), 'warning', 'bi-plus-circle'));
                if (isset($badges[(string) $row->active])) {
                    $badge = $badges[(string) $row->active];
                    $html .= $layout->statusBadge($badge[0], $badge[1], $badge[2], $badge[3]) . '&nbsp;';
                }
                return $html . '<a href="edit.php?hsid=' . $row->id . '">' . $row->name . '</a>';

            case 'expiry':
                $passed = '';
                $icon = '';
                $state = Hosting::renewalState($row->active, $row->autorenew, $row->expiry_date, $row->billing_term, $today);
                if ($state !== '') {
                    $class = $state === Hosting::STATE_CONFIRM ? 'domainmod-css-confirm' : 'domainmod-css-expired';
                    $label = htmlspecialchars($state === Hosting::STATE_CONFIRM ? _('Confirm renewal') : _('Renewal date passed'), ENT_QUOTES, 'UTF-8');
                    $passed = ' class="' . $class . '" data-bs-toggle="tooltip" data-bs-title="' . $label . '" aria-label="'
                        . $row->expiry_date . ', ' . $label . '"';
                    $icon = '<i class="bi ' . ($state === Hosting::STATE_CONFIRM ? 'bi-hourglass-split' : 'bi-exclamation-triangle')
                        . '" aria-hidden="true"></i>&nbsp;';
                }
                return '<a href="edit.php?hsid=' . $row->id . '"' . $passed . '>' . $icon . $row->expiry_date . '</a>';

            // A term that isn't an allowed one shows as its number
            case 'term':
                return Hosting::validTerm((string) $row->billing_term) ? htmlspecialchars(Hosting::termLabel($row->billing_term), ENT_QUOTES, 'UTF-8')
                    : (string) (int) $row->billing_term;

            // The fee links to the service's fee, except for read-only users, who can't edit it (as on the SSL list)
            case 'fee':
                if ($row->f_id === null || $row->conversion === null) return $this->noCostCell($row);
                $amount = $this->money($row->total_cost * $row->conversion);
                if (($s['s_read_only'] ?? '') == '1') return htmlentities($amount, ENT_QUOTES, 'UTF-8');
                return '<a href="../assets/edit/hosting-fee.php?hosting_id=' . $row->h_id . '&fee_id=' . $row->f_id . '">'
                    . htmlentities($amount, ENT_QUOTES, 'UTF-8') . '</a>';

            // The yearly figure is empty for a term that isn't an allowed one, as it's left out of the totals
            case 'yearly':
                if ($row->f_id === null || $row->conversion === null) return $this->noCostCell($row);
                $yearly = Hosting::yearlyCost($row->total_cost * $row->conversion, (string) $row->billing_term);
                return $yearly === null ? '' : htmlentities($this->money($yearly), ENT_QUOTES, 'UTF-8');

            case 'hostname':
                return (string) $row->hostname;

            case 'provider':
                return '<a href="../assets/edit/host.php?whid=' . $row->h_id . '">' . $row->hosting_provider_name . '</a>';

            case 'account':
                return '<a href="../assets/edit/host.php?whid=' . $row->h_id . '">' . $row->hosting_provider_name . '</a>, '
                    . '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a> '
                    . '(<a href="../assets/edit/hosting-provider-account.php?hpaid=' . $row->hpa_id . '">' . substr($row->username, 0, 15)
                    . (strlen($row->username) >= 16 ? '...' : '') . '</a>)';

            case 'type':
                return '<a href="../assets/edit/hosting-type.php?htid=' . $row->type_id . '">' . $row->type . '</a>';

            // No IP address (ip_id 0, or one that no longer exists) shows as "None"
            case 'ip':
                if ($row->ip_id === null) return htmlspecialchars(_('None'), ENT_QUOTES, 'UTF-8');
                return '<a href="../assets/edit/ip-address.php?ipid=' . $row->ip_id . '">' . $row->ip_name . ' (' . $row->ip . ')</a>';

            case 'category':
                return '<a href="../assets/edit/category.php?pcid=' . $row->cat_id . '">' . $row->cat_name . '</a>';

            case 'owner':
                return '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a>';

            default:
                return $this->customFieldCell($column['field'], $row);

        }
    }

    /*
     * The fee and yearly cost cells of a service that can't be costed: "No fee" when it has no fee, linked to Add
     * Hosting Fee with its provider, type, and billing term chosen (FeeStatus::addHostingFeeUrl()), except for
     * read-only users, whom the fee page would refuse, or "No rate" when the fee's currency has no conversion rate for
     * the user
     */
    protected function noCostCell($row)
    {
        if ($row->f_id !== null) return htmlspecialchars(_('No rate'), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars(_('No fee'), ENT_QUOTES, 'UTF-8');
        if (($this->settings['s_read_only'] ?? '') == '1') return $text;
        return '<a href="' . htmlspecialchars($this->web_root . FeeStatus::addHostingFeeUrl($row), ENT_QUOTES, 'UTF-8') . '">'
            . $text . '</a>';
    }

    // Text as a quoted SQL string, for a fixed expression such as the IP column's "None" (quotes and backslashes doubled)
    protected static function sqlText($text)
    {
        return "'" . str_replace(array('\\', "'"), array('\\\\', "''"), (string) $text) . "'";
    }

    // An amount in the user's currency, formatted as the list's totals are
    protected function money($amount)
    {
        $s = $this->settings;
        return $this->currency->format($amount, $s['s_default_currency_symbol'] ?? '', $s['s_default_currency_symbol_order'] ?? '',
            $s['s_default_currency_symbol_space'] ?? '');
    }

} //@formatter:on
