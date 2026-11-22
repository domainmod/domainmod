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
 * database are stored sanitized). What it shares with the SSL Certificates list's columns is in ListColumns.
 * Like that list, it has no control column for DataTables Responsive: the Domains list keeps every column and
 * scrolls sideways with the domain kept in view (see css/datatables/domains-main.css), so the domain comes first.
 */
class DomainListColumns extends ListColumns
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
        'service' => array('s_display_domain_host', array('hs.name')),
        'category' => array('s_display_domain_category', array('cat.name')),
        'owner' => array('s_display_domain_owner', array('o.name')),
        'lock' => array('s_display_domain_lock', array('d.transfer_lock', 'd.epp_status_time')),
        'live_dns' => array('s_display_domain_live_dns', array(LiveDns::LIST_SORT)));

    // The session values the columns and cells read, copied by sessionCopy()
    const SESSION_KEYS = array('s_display_domain_expiry_date', 's_display_domain_fee', 's_display_domain_tld',
        's_display_domain_registrar', 's_display_domain_account', 's_display_domain_dns', 's_display_domain_ip',
        's_display_domain_host', 's_display_domain_category', 's_display_domain_owner', 's_display_domain_lock',
        's_display_domain_live_dns', 's_cdf_data',
        's_default_currency', 's_default_currency_symbol', 's_default_currency_symbol_order',
        's_default_currency_symbol_space', 's_read_only');

    const FIELD_ALIAS = 'dfd';
    const NAME_KEY = 'domain';

    /*
     * $settings is sessionCopy()'s array. $fields is the list's custom fields as they exist now
     * (CustomField::getFilterFields('domain_fields')), which give each custom field column its title and type: the
     * session's list (s_cdf_data) only says which ones are shown, and can still list a field that's since been deleted
     * or replaced. $hosting_available says whether the database has hosting services (see Hosting::isAvailable()), or
     * is null to ask, which happens only when the Hosting Service column is switched on: before the update the column
     * isn't offered, whatever the session says, since its values come from a join the list doesn't have yet.
     */
    public function __construct(array $settings, array $fields, $hosting_available = null)
    {
        $this->settings = $settings;
        $this->layout = new Layout();
        $this->currency = new Currency();

        $columns = array(array('key' => 'domain', 'title' => _('Domain'), 'sort' => array('d.domain')));
        $titles = array('expiry' => _('Expiry'),
            'fee' => _('Fee') . ' (' . ($settings['s_default_currency'] ?? '') . ')',
            'tld' => _('TLD'), 'registrar' => _('Registrar'), 'account' => _('Account'), 'dns' => _('DNS'),
            // TRANSLATORS: A Domains list column heading: the hosting service each domain uses
            'ip' => _('IP'), 'service' => _('Hosting Service'), 'category' => _('Category'), 'owner' => _('Owner'),
            'lock' => _('Transfer Lock'), 'live_dns' => _('Live DNS'));
        foreach (self::OPTIONAL as $key => $optional) {
            if (($settings[$optional[0]] ?? '') == '1') {
                // The Transfer Lock column's columns come with the 4.27.0 database update, whatever the session says
                if ($key === 'lock' && !DomainStatus::isAvailable()) continue;
                // So does the Hosting Service column's
                if ($key === 'service' && !($hosting_available ?? self::hostingAvailable())) continue;
                // So does the Live DNS column's table
                if ($key === 'live_dns' && !LiveDns::isAvailable()) continue;
                $columns[] = array('key' => $key, 'title' => $titles[$key], 'sort' => $optional[1]);
            }
        }

        $this->columns = $this->withCustomFields($columns, $settings['s_cdf_data'] ?? array(), $fields);
    }

    // Whether the shared database has hosting services (a database that can't be asked counts as no)
    protected static function hostingAvailable()
    {
        try {
            return Hosting::isAvailable();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /*
     * The search box's expressions for the visible columns, following what each cell shows: 'lower' ones are compared
     * with the word in lowercase (the domain and TLD, which are stored in lowercase in case-sensitive columns), 'typed'
     * ones with the word as typed (the expiry date, and text, date, and time stamp custom fields, with the empty
     * placeholder dates left out), and 'encoded' ones with the word HTML-encoded, as asset names are stored. The domain
     * also matches the word's stored IDN form (see DomainList::searchSql()).
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
                // A domain with no service shows a blank cell, so an excluded word (NOT LIKE) mustn't hide it by
                // turning the condition NULL
                case 'service': $encoded[] = "COALESCE(hs.name, '')"; break;
                case 'category': $encoded[] = 'cat.name'; break;
                case 'owner': $encoded[] = 'o.name'; break;
                default:
                    if (isset($column['field'])) $typed = $this->customFieldSearch($column, $typed);
            }
        }

        return array('lower' => $lower, 'typed' => $typed, 'encoded' => $encoded);
    }

    // A row's cell for one column. $today is the date past which a live domain's expiry is marked.
    protected function cell(array $column, $row, $today)
    {
        $layout = $this->layout;
        $s = $this->settings;

        switch ($column['key']) {

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
                // On Hold, Being Deleted, or Being Restored at the registry, whether or not the Transfer Lock column is on
                if (isset($row->ds1_epp_status)) $html .= DomainStatus::problemBadges($row);
                return $html;

            case 'expiry':
                $expired = '';
                if (!in_array($row->active, array('0', '10')) && $row->expiry_date < $today) {
                    $expired = ' class="domainmod-css-expired" data-bs-toggle="tooltip" data-bs-title="'
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . $row->expiry_date . ', '
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '"';
                }
                return '<a href="edit.php?did=' . $row->id . '"' . $expired . '>' . $row->expiry_date . '</a>';

            // The fee and TLD link to the domain's fee, except for read-only users, who can't edit it (as on the
            // Domain Fees report)
            case 'fee':
                $amount = $this->currency->format($row->total_cost * $row->conversion, $s['s_default_currency_symbol'],
                    $s['s_default_currency_symbol_order'], $s['s_default_currency_symbol_space']);
                if (($s['s_read_only'] ?? '') == '1') return htmlentities($amount, ENT_QUOTES, 'UTF-8');
                return '<a href="../assets/edit/registrar-fee.php?rid=' . $row->r_id . '&fee_id=' . $row->f_id . '">'
                    . htmlentities($amount, ENT_QUOTES, 'UTF-8') . '</a>';

            case 'tld':
                if (($s['s_read_only'] ?? '') == '1') return '<bdi dir="ltr">.' . $row->tld . '</bdi>';
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

            // Blank for a domain with no service (service_id 0, or a service that no longer exists)
            case 'service':
                if (($row->service_id ?? null) === null) return '';
                return '<a href="../hosting/edit.php?hsid=' . (int) $row->service_id . '">' . $row->service_name . '</a>';

            case 'category':
                return '<a href="../assets/edit/category.php?pcid=' . $row->pcid . '">' . $row->category_name . '</a>';

            case 'owner':
                return '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a>';

            case 'lock':
                return DomainStatus::listCell($row);

            case 'live_dns':
                return LiveDns::listCell($row);

            default:
                return $this->customFieldCell($column['field'], $row);

        }
    }

} //@formatter:on
