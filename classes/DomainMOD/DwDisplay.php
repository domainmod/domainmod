<?php
/**
 * /classes/DomainMOD/DwDisplay.php
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

class DwDisplay
{
    public $deeb;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
    }

    /*
     * The columns of a Data Warehouse accounts table, in order, after Responsive's control column: each an array with
     * its 'label' (translated text), 'details' (true for a column shown only in Responsive's details row, DataTables'
     * "none" class), and 'end' (true for a right-aligned number or date, as DataTables aligns them). The accounts list
     * shows the Domain column, and Edit Domain leaves it out ($with_domain false), since that page is about the domain.
     * accountCells() draws a row's cells in the same order.
     */
    public static function accountColumns($with_domain = true)
    {
        $columns = array();
        if ($with_domain) $columns[] = array('label' => _('Domain'), 'details' => false, 'end' => false);
        $columns[] = array('label' => _('Server'), 'details' => false, 'end' => false);
        $columns[] = array('label' => _('Created'), 'details' => false, 'end' => true);
        $columns[] = array('label' => _('IP Address'), 'details' => false, 'end' => false);
        $columns[] = array('label' => _('Plan'), 'details' => false, 'end' => false);
        $columns[] = array('label' => _('Disk Used'), 'details' => false, 'end' => true);
        $columns[] = array('label' => _('Suspended'), 'details' => false, 'end' => false);
        foreach (array(_('Contact'), _('Owner'), _('User'), _('Theme'), _('Shell'), _('Home'), _('Disk Quota'), _('POP'),
                     _('Lists'), _('Addons'), _('Subdomains'), _('SQL'), _('FTP'), _('Parked'), _('Suspended On'),
                     _('Suspend Reason')) as $label) {
            $columns[] = array('label' => $label, 'details' => true, 'end' => false);
        }
        return $columns;
    }

    // The header cells of accountColumns(), after the control column's
    public static function accountHeaderCells($with_domain = true)
    {
        $cells = array();
        foreach (self::accountColumns($with_domain) as $column) {
            $classes = array();
            if ($column['details']) $classes[] = 'none';
            if ($column['end']) $classes[] = 'text-end';
            $cells[] = '<th' . ($classes ? ' class="' . implode(' ', $classes) . '"' : '') . '>' . self::escape($column['label']) . '</th>';
        }
        return $cells;
    }

    /*
     * A Data Warehouse account's cells (a row of dw_accounts with its server's name as dw_server_name), in the order of
     * accountColumns(), each a whole <td>. The domain's DNS Zone link is underlined, since it sits in a line of text.
     * Everything is escaped, and left-to-right values (the domain, IP address, email address, and home folder) are
     * isolated, so they keep their order on right-to-left pages.
     */
    public static function accountCells($row, $with_domain = true)
    {
        $row = (array) $row;
        $value = function ($key) use ($row) {
            return (string) ($row[$key] ?? '');
        };
        $layout = new Layout();
        $ltr = function ($text) use ($layout) {
            return $text === '' ? '' : $layout->ltr($text);
        };
        $cell = function ($html, $attributes = '') {
            return '<td' . $attributes . '>' . $html . '</td>';
        };

        $cells = array();
        if ($with_domain) {
            $domain = $value('domain');
            $cells[] = $cell($ltr($domain) . ' <a class="text-decoration-underline" href="list-zones.php?domain=' . self::escape(rawurlencode($domain)) . '">'
                . self::escape(_('DNS Zone')) . '</a>', ' data-order="' . self::escape($domain) . '"');
        }
        // server names are stored HTML-escaped (they're typed in DomainMOD), so they're decoded before escaping
        $cells[] = $cell(self::escape(htmlspecialchars_decode($value('dw_server_name'), ENT_QUOTES)));
        $cells[] = $cell(self::escape(self::timestampDate($value('unix_startdate'))), ' class="text-end text-nowrap" data-order="' . (int) $value('unix_startdate') . '"');
        $cells[] = $cell($ltr($value('ip')));
        $cells[] = $cell(self::escape($value('plan')));
        $cells[] = $cell(self::escape(number_format((float) $value('diskused')) . ' MB'), ' class="text-end" data-order="' . (float) $value('diskused') . '"');
        $cells[] = $cell((int) $value('suspended') === 1 ? '<span class="badge text-bg-danger">' . self::escape(_('Yes')) . '</span>' : self::escape(_('No')));
        $cells[] = $cell($ltr($value('email')));
        $cells[] = $cell(self::escape($value('owner')));
        $cells[] = $cell(self::escape($value('user')));
        $cells[] = $cell(self::escape($value('theme')));
        $cells[] = $cell(self::escape($value('shell')));
        $cells[] = $cell($value('user') === '' ? '' : $ltr('/' . $value('partition') . '/' . $value('user')));
        $disk_quota = $value('disklimit');
        if ($disk_quota !== '' && $disk_quota !== 'unlimited') $disk_quota .= ' MB';
        $cells[] = $cell(self::escape($disk_quota));
        foreach (array('maxpop', 'maxlst', 'maxaddons', 'maxsub', 'maxsql', 'maxftp', 'maxparked') as $key) {
            $cells[] = $cell(self::escape($value($key)));
        }
        // WHM sends the suspension time as a Unix timestamp, and 0 when the account isn't suspended ("-")
        $cells[] = $cell(self::escape(self::timestampDate($value('suspendtime'))));
        $cells[] = $cell(self::escape($value('suspendreason')));
        return $cells;
    }

    public function getAccount($server_id, $domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT a.domain, a.unix_startdate, a.email, a.ip, a.plan, a.theme, a.`user`, a.`owner`, a.shell, a.`partition`,
                a.disklimit, a.diskused, a.maxpop, a.maxlst, a.maxaddons, a.maxsub, a.maxsql, a.maxftp, a.maxparked,
                a.suspended, a.suspendtime, a.suspendreason, s.name AS dw_server_name
            FROM dw_accounts AS a, dw_servers AS s
            WHERE a.server_id = s.id
              AND a.server_id = :server_id
              AND a.domain = :domain
            ORDER BY a.unix_startdate DESC");
        $stmt->bindValue('server_id', $server_id, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /*
     * A Unix timestamp from WHM (an account's creation or suspension time) as a date (YYYY-MM-DD) in the user's time
     * zone, or "-" for 0, an empty value, or anything that isn't a positive whole number
     */
    public static function timestampDate($timestamp)
    {
        $timestamp = trim((string) $timestamp);
        if (!preg_match('/^[0-9]+$/', $timestamp) || (int) $timestamp <= 0) return '-';

        return (new Time())->toUserTimezone('@' . (int) $timestamp, 'Y-m-d', '-');
    }

    /*
     * The view the Data Warehouse page's Servers table links to (?server=<id>, or all): a known server's id views that
     * server's accounts or DNS zones, "all" views every server's, and anything else clears the view, so the list shows
     * its "Choose what to view" message rather than a server chosen earlier. The view is kept in the session, as the
     * lists' Export and domain links expect.
     */
    public function chooseServer($server)
    {
        $server = (string) $server;

        if ($server === 'all') {

            $_SESSION['s_dw_view_all'] = 1;
            return;

        }

        $result = false;

        if (preg_match('/^[0-9]+$/', $server)) {

            $stmt = $this->deeb->cnxx->prepare("
                SELECT `name`, `host`
                FROM dw_servers
                WHERE id = :id");
            $stmt->bindValue('id', (int) $server, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

        }

        if ($result) {

            $_SESSION['s_dw_view_all'] = '';
            $_SESSION['s_dw_server_id'] = (int) $server;
            $_SESSION['s_dw_server_name'] = $result->name;
            $_SESSION['s_dw_server_host'] = $result->host;

        } else {

            unset($_SESSION['s_dw_view_all'], $_SESSION['s_dw_server_id']);

        }
    }

    /*
     * A DNS record's parts, built from its own fields (not the formatted_* columns, which hold HTML in the language of
     * whoever ran the build), as plain text: 'type', 'name', 'value', 'ttl' (a number, or '' when there's none to
     * show), 'line' (its line in the zone file, or ''), and 'facts' (the SOA record's timers, as label => value). The
     * text comes from the WHM server, so it's escaped only when it's drawn (recordList()).
     */
    public static function recordParts($record)
    {
        $record = (array) $record;
        $field = function ($key) use ($record) {
            return trim((string) ($record[$key] ?? ''));
        };
        $type = $field('type');
        $name = $field('name');
        $value = '';
        $facts = array();

        switch ($type) {
            case 'A':
            case 'AAAA':
                $value = $field('address');
                break;
            case 'CNAME':
                $value = $field('cname');
                break;
            case 'MX':
                $value = trim($field('preference') . ' ' . $field('exchange'));
                break;
            case 'NS':
                $value = $field('nsdname');
                break;
            case 'TXT':
                $value = $field('txtdata');
                break;
            case 'SRV':
                $value = $field('raw');
                break;
            case 'COMMENT':
                $name = '';
                $value = $field('raw');
                break;
            case 'ZONE TTL':
                $name = '';
                break;
            case 'SOA':
                $value = trim($field('mname') . ' ' . $field('rname'));
                $facts = array(_('Serial') => $field('serial'), _('Refresh') => $field('refresh'),
                    _('Retry') => $field('retry'), _('Expire') => $field('expire'),
                    _('Minimum TTL') => $field('minimum'));
                break;
            default:
                foreach (array('address', 'cname', 'txtdata', 'raw') as $key) {
                    if ($field($key) !== '') {
                        $value = $field($key);
                        break;
                    }
                }
        }

        // A comment has no TTL, and a record WHM sent without one is stored as 0
        $ttl = $type === 'COMMENT' || (int) $field('ttl') <= 0 ? '' : (string) (int) $field('ttl');
        $line = (int) $field('line') > 0 ? (string) (int) $field('line') : '';

        return array('type' => $type, 'name' => $name, 'value' => $value, 'ttl' => $ttl, 'line' => $line,
            'facts' => $facts);
    }

    // A DNS zone's records as a list (see recordList())
    public function records($zone_id)
    {
        return self::recordList($this->getRecords($zone_id));
    }

    /*
     * DNS records as a list, one item per record: its type as a grey badge, then its name (in bold) and value (each
     * isolated, so they keep their order on right-to-left pages), its TTL, the SOA record's timers, and its line in the zone file.
     * Everything is escaped. Long values wrap in the browser (CSS) rather than at a set width.
     */
    public static function recordList($records)
    {
        $layout = new Layout();
        $items = '';

        foreach ($records as $record) {

            $parts = self::recordParts($record);
            $item = '<span class="badge text-bg-secondary">' . self::escape($parts['type']) . '</span>';
            if ($parts['name'] !== '') $item .= ' <span class="domainmod-dw-record-name">' . $layout->ltr($parts['name']) . '</span>';
            if ($parts['value'] !== '') $item .= ' ' . $layout->ltr($parts['value']);
            // TRANSLATORS: %s is a DNS record's time to live, a number of seconds
            if ($parts['ttl'] !== '') $item .= ' <span class="text-body-secondary">' . self::escape(sprintf(_('TTL %s'), $parts['ttl'])) . '</span>';
            // TRANSLATORS: %s is the DNS record's line number in the zone file
            if ($parts['line'] !== '') $item .= ' <span class="text-body-secondary">' . self::escape(sprintf(_('Line %s'), $parts['line'])) . '</span>';
            if ($parts['facts']) {
                $facts = array();
                foreach ($parts['facts'] as $label => $value) {
                    $facts[] = array('label' => $label, 'value' => $value, 'ltr' => true);
                }
                $item .= $layout->factList($facts, true);
            }
            $items .= '<li>' . $item . '</li>';

        }

        if ($items === '') return '';
        return '<ul class="list-unstyled mb-0 domainmod-dw-records">' . $items . '</ul>';
    }

    // Text from the WHM server (or a server's name), safe to put in the page
    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Wrapped at $width characters, then escaped, with <BR> line breaks (escaping first could split an entity)
    public static function wrapEscaped($value, $width)
    {
        return implode('<BR>', array_map(array(self::class, 'escape'), self::wrapLines($value, $width)));
    }

    /*
     * Text wrapped at $width characters (not bytes, so a multi-byte character such as ü is never cut in half): at spaces
     * where it can, and inside words longer than $width, like wordwrap() with cut_long_words. Bytes that aren't valid
     * UTF-8 are replaced first
     */
    public static function wrapLines($value, $width)
    {
        $value = (string) $value;
        if (!mb_check_encoding($value, 'UTF-8')) $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        $lines = array();
        foreach (preg_split('/\R/u', $value) as $paragraph) {
            // null until the line has started, so leading and repeated spaces are kept, as wordwrap() keeps them
            $line = null;
            foreach (explode(' ', $paragraph) as $word) {
                while (mb_strlen($word, 'UTF-8') > $width) {
                    if ($line !== null) { $lines[] = $line; $line = null; }
                    $lines[] = mb_substr($word, 0, $width, 'UTF-8');
                    $word = mb_substr($word, $width, null, 'UTF-8');
                }
                $candidate = $line === null ? $word : $line . ' ' . $word;
                if ($line === null || mb_strlen($candidate, 'UTF-8') <= $width) {
                    $line = $candidate;
                } else {
                    $lines[] = $line;
                    $line = $word;
                }
            }
            // explode() always returns at least one piece, so the first one has always started the line here
            $lines[] = $line;
        }
        return $lines;
    }

    public function getRecords($zone_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT address, cname, `exchange`, `expire`, line, minimum, mname, `name`, nsdname, preference, raw,
                refresh, retry, rname, `serial`, ttl, txtdata, type
            FROM dw_dns_records
            WHERE dns_zone_id = :zone_id
            ORDER BY new_order, line, id");
        $stmt->bindValue('zone_id', $zone_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

} //@formatter:on
