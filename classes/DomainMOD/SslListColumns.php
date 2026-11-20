<?php
/**
 * /classes/DomainMOD/SslListColumns.php
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
 * The SSL Certificates list's columns and each row's cells, for the page's own first page and for the rows the
 * server-side data endpoint (ssl/list-data.php) sends, so the two can't differ. The columns follow the user's Display
 * Settings and custom fields, given as a copy of the session values ($settings), so the endpoint can let go of the
 * session early. The cells are built exactly as the list has always drawn them, with the same escaping (values from the
 * database are stored sanitized). What it shares with the Domains list's columns is in ListColumns. Like that list,
 * it has no control column for DataTables Responsive: the list keeps every column and scrolls sideways with the name
 * kept in view (see css/datatables/ssl-main.css), so the name comes first.
 */
class SslListColumns extends ListColumns
{
    // Each optional column's key, its Display Settings session key, and its sort expressions
    const OPTIONAL = array(
        'expiry' => array('s_display_ssl_expiry_date', array('sslc.expiry_date')),
        'fee' => array('s_display_ssl_fee', array('sslc.total_cost * cc.conversion')),
        'domain' => array('s_display_ssl_domain', array('d.domain')),
        'provider' => array('s_display_ssl_provider', array('sslp.name')),
        'account' => array('s_display_ssl_account', array('sslp.name', 'o.name', 'sslpa.username')),
        'type' => array('s_display_ssl_type', array('sslcf.type')),
        'ip' => array('s_display_ssl_ip', array('ip.name', 'ip.ip')),
        'category' => array('s_display_ssl_category', array('cat.name')),
        'owner' => array('s_display_ssl_owner', array('o.name')));

    // The session values the columns and cells read, copied by sessionCopy()
    const SESSION_KEYS = array('s_display_ssl_expiry_date', 's_display_ssl_fee', 's_display_ssl_domain',
        's_display_ssl_provider', 's_display_ssl_account', 's_display_ssl_type', 's_display_ssl_ip',
        's_display_ssl_category', 's_display_ssl_owner', 's_csf_data', 's_default_currency', 's_default_currency_symbol',
        's_default_currency_symbol_order', 's_default_currency_symbol_space', 's_read_only');

    const FIELD_ALIAS = 'sslfd';
    const NAME_KEY = 'name';

    /*
     * $settings is sessionCopy()'s array. $current_fields_sql is the custom field columns that exist now
     * (CustomField::getCustomFieldsSql('ssl_cert_fields', 'sslfd')): the session can still list a field that's since
     * been deleted, which would otherwise read a missing column.
     */
    public function __construct(array $settings, $current_fields_sql)
    {
        $this->settings = $settings;
        $this->layout = new Layout();
        $this->currency = new Currency();

        $columns = array(array('key' => 'name', 'title' => _('Name'), 'sort' => array('sslc.name')));
        $titles = array('expiry' => _('Expiry'), 'fee' => _('Fee'), 'domain' => _('Domain'),
            'provider' => _('Provider'), 'account' => _('Account'), 'type' => _('Type'), 'ip' => _('IP Address'),
            'category' => _('Category'), 'owner' => _('Owner'));
        foreach (self::OPTIONAL as $key => $optional) {
            if (($settings[$optional[0]] ?? '') == '1') {
                $columns[] = array('key' => $key, 'title' => $titles[$key], 'sort' => $optional[1]);
            }
        }

        $this->columns = $this->withCustomFields($columns, $settings['s_csf_data'] ?? array(), $current_fields_sql);
    }

    /*
     * The search box's expressions for the visible columns, following what each cell shows: 'lower' ones are compared
     * with the word in lowercase (the domain, stored in lowercase in a case-sensitive column), 'typed' ones with the
     * word as typed (the expiry date, and text, date, and time stamp custom fields, with the empty placeholder dates
     * left out), 'idn' ones (the domain) also with the word's stored IDN form, and 'encoded' ones with the word
     * HTML-encoded, as certificate and asset names are stored. The name always shows, so it's always searched.
     */
    public function searchExpressions()
    {
        $lower = array();
        $typed = array();
        $idn = array();
        $encoded = array('sslc.name');

        foreach ($this->columns as $column) {
            switch ($column['key']) {
                case 'expiry': $typed[] = 'CAST(sslc.expiry_date AS CHAR)'; break;
                case 'domain': $lower[] = 'd.domain'; $idn[] = 'd.domain'; break;
                case 'provider': $encoded[] = 'sslp.name'; break;
                case 'account': $encoded[] = "CONCAT(sslp.name, ', ', o.name, ' (', sslpa.username, ')')"; break;
                case 'type': $encoded[] = 'sslcf.type'; break;
                case 'ip': $encoded[] = "CONCAT(ip.name, ' (', ip.ip, ')')"; break;
                case 'category': $encoded[] = 'cat.name'; break;
                case 'owner': $encoded[] = 'o.name'; break;
                default:
                    if (isset($column['field'])) $typed = $this->customFieldSearch($column, $typed);
            }
        }

        return array('lower' => $lower, 'typed' => $typed, 'idn' => $idn, 'encoded' => $encoded);
    }

    // A row's cell for one column. $today is the date past which an unexpired certificate's expiry is marked.
    protected function cell(array $column, $row, $today)
    {
        $layout = $this->layout;
        $s = $this->settings;

        switch ($column['key']) {

            case 'name':
                $html = '';
                $badges = array(
                    '0' => array('x', _('Expired'), 'secondary', 'bi-x-circle'),
                    '3' => array(_('Pending Renewal Characters'), _('Pending (Renewal)'), 'warning', 'bi-arrow-repeat'),
                    '4' => array(_('Pending Other Characters'), _('Pending (Other)'), 'warning', 'bi-three-dots'),
                    '5' => array(_('Pending Registration Characters'), _('Pending (Registration)'), 'warning', 'bi-plus-circle'));
                if (isset($badges[(string) $row->active])) {
                    $badge = $badges[(string) $row->active];
                    $html .= $layout->statusBadge($badge[0], $badge[1], $badge[2], $badge[3]) . '&nbsp;';
                }
                return $html . '<a href="edit.php?sslcid=' . $row->id . '">' . $row->name . '</a>';

            case 'expiry':
                $expired = '';
                if ($row->active != '0' && $row->expiry_date < $today) {
                    $expired = ' class="domainmod-css-expired" data-bs-toggle="tooltip" data-bs-title="'
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . $row->expiry_date . ', '
                        . htmlspecialchars(_('Expired'), ENT_QUOTES, 'UTF-8') . '"';
                }
                return '<a href="edit.php?sslcid=' . $row->id . '"' . $expired . '>' . $row->expiry_date . '</a>';

            // The fee links to the certificate's fee, except for read-only users, who can't edit it (as on the SSL Fees
            // report)
            case 'fee':
                $amount = $this->currency->format($row->total_cost * $row->conversion, $s['s_default_currency_symbol'],
                    $s['s_default_currency_symbol_order'], $s['s_default_currency_symbol_space']);
                if (($s['s_read_only'] ?? '') == '1') return htmlentities($amount, ENT_QUOTES, 'UTF-8');
                return '<a href="../assets/edit/ssl-provider-fee.php?sslpid=' . $row->sslp_id . '&fee_id=' . $row->f_id . '">'
                    . htmlentities($amount, ENT_QUOTES, 'UTF-8') . '</a>';

            case 'domain':
                return '<a href="../domains/edit.php?did=' . $row->domain_id . '">' . $row->domain . '</a>';

            case 'provider':
                return '<a href="../assets/edit/ssl-provider.php?sslpid=' . $row->sslp_id . '">' . $row->ssl_provider_name . '</a>';

            case 'account':
                return '<a href="../assets/edit/ssl-provider.php?sslpid=' . $row->sslp_id . '">' . $row->ssl_provider_name . '</a>, '
                    . '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a> '
                    . '(<a href="../assets/edit/ssl-provider-account.php?sslpaid=' . $row->sslpa_id . '">' . substr($row->username, 0, 15)
                    . (strlen($row->username) >= 16 ? '...' : '') . '</a>)';

            case 'type':
                return '<a href="../assets/edit/ssl-type.php?ssltid=' . $row->type_id . '">' . $row->type . '</a>';

            case 'ip':
                return '<a href="../assets/edit/ip-address.php?ipid=' . $row->ip_id . '">' . $row->ip_name . ' (' . $row->ip . ')</a>';

            case 'category':
                return '<a href="../assets/edit/category.php?pcid=' . $row->cat_id . '">' . $row->cat_name . '</a>';

            case 'owner':
                return '<a href="../assets/edit/account-owner.php?oid=' . $row->o_id . '">' . $row->owner_name . '</a>';

            default:
                return $this->customFieldCell($column['field'], $row);

        }
    }

} //@formatter:on
