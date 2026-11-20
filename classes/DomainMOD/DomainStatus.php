<?php
/**
 * /classes/DomainMOD/DomainStatus.php
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
 * A domain's registry status codes (the EPP statuses, such as clientTransferProhibited) and what they mean: whether
 * the domain has a transfer lock, and whether the registry has it on hold or is deleting or restoring it. The codes
 * come from RDAP (see Rdap::lookup()) and are kept in domains columns: transfer_lock, epp_status, and epp_status_time
 * for the last successful read, and epp_status_failed_time for a later lookup that couldn't read them. They come with
 * the 4.27.0 database update, so nothing here reads or writes them before isAvailable() says they're there.
 *
 * Only codes on the fixed list below are ever stored or shown, never text from a response.
 */
class DomainStatus
{
    // transfer_lock's values. Not Checked, Couldn't Check, and Unknown share 0, and the two times tell them apart (see
    // state()).
    const LOCK_UNKNOWN = 0;
    const LOCK_OFF = 1;
    const LOCK_ON = 2;

    // The known codes, in the order they're stored: every EPP domain status (RFC 5731) and grace period status
    // (RFC 3915), then RDAP's generic values some country-code registries use instead (RFC 9083)
    const CODES = array('ok', 'inactive', 'addPeriod', 'autoRenewPeriod', 'renewPeriod', 'transferPeriod',
        'redemptionPeriod', 'pendingCreate', 'pendingDelete', 'pendingRenew', 'pendingRestore', 'pendingTransfer',
        'pendingUpdate', 'clientDeleteProhibited', 'clientHold', 'clientRenewProhibited', 'clientTransferProhibited',
        'clientUpdateProhibited', 'serverDeleteProhibited', 'serverHold', 'serverRenewProhibited',
        'serverTransferProhibited', 'serverUpdateProhibited', 'transferProhibited', 'updateProhibited',
        'deleteProhibited', 'renewProhibited', 'locked');

    // The codes that are a transfer lock, and the ones behind the On Hold and Being Deleted problems
    const TRANSFER_CODES = array('clientTransferProhibited', 'serverTransferProhibited', 'transferProhibited');
    const HOLD_CODES = array('clientHold', 'serverHold');
    const DELETION_CODES = array('redemptionPeriod', 'pendingDelete', 'pendingRestore');

    // A status list longer than this isn't read at all, and an entry longer than this (once its URL is removed) makes
    // the read incomplete, since either could hide a transfer lock
    const MAX_ENTRIES = 50;
    const MAX_ENTRY_LENGTH = 100;

    // The Registry Status filter's values (request key lock)
    const FILTERS = array('locked', 'unlocked', 'unknown', 'failed', 'hold', 'deletion');

    // The time stored before any status was read
    const UNSET_TIME = '1970-01-01 00:00:00';

    // The database version the columns come with
    const AVAILABLE_FROM = '4.27.0';

    // Whether the columns are there, by connection, worked out once per request (see isAvailable())
    protected static $available = array();

    /*
     * Reads an RDAP record's status member, decoded with JSON objects kept as objects (json_decode($body, false)), so a
     * JSON object can't pass as a list. Returns array(read, codes): read is 'found' (known codes), 'none' (no status, or
     * a list of strings with no known codes), or 'incomplete' (a status that isn't a list, an entry that isn't a
     * string, more than MAX_ENTRIES entries, or an entry still over MAX_ENTRY_LENGTH characters once its URL is
     * removed), and codes are the known codes in the stored order, without duplicates.
     */
    public static function fromRdap($status, $present = true)
    {
        if (!$present) return array('none', array());
        if (!is_array($status) || !array_is_list($status) || count($status) > self::MAX_ENTRIES) return array('incomplete', array());

        $codes = array();

        foreach ($status as $entry) {

            if (!is_string($entry)) return array('incomplete', array());

            $entry = self::withoutUrl($entry);
            if (strlen($entry) > self::MAX_ENTRY_LENGTH) return array('incomplete', array());

            $code = self::code($entry);
            if ($code !== '') $codes[] = $code;

        }

        $codes = self::ordered($codes);

        return array($codes ? 'found' : 'none', $codes);
    }

    /*
     * The known code an entry stands for, or '': RDAP's spelling ("client transfer prohibited", RFC 8056) or the EPP
     * name, with case and spaces ignored. RDAP's "active" is EPP's ok.
     */
    public static function code($entry)
    {
        if (!is_string($entry)) return '';

        $key = strtolower(str_replace(array(' ', "\t"), '', trim($entry)));
        if ($key === 'active') return 'ok';

        foreach (self::CODES as $code) {
            if (strtolower($code) === $key) return $code;
        }

        return '';
    }

    // An entry trimmed, without a URL after it (some servers send "clientTransferProhibited https://icann.org/epp#...")
    protected static function withoutUrl($entry)
    {
        $entry = trim($entry);
        return trim((string) preg_replace('#\s+https?://\S*$#i', '', $entry));
    }

    // Known codes only, in the stored order, without duplicates
    public static function ordered(array $codes)
    {
        return array_values(array_intersect(self::CODES, $codes));
    }

    // The stored form of a set of codes (comma-separated, for FIND_IN_SET()), and back
    public static function toColumn(array $codes)
    {
        return implode(',', self::ordered($codes));
    }

    public static function fromColumn($value)
    {
        $value = trim((string) $value);
        return $value === '' ? array() : self::ordered(explode(',', $value));
    }

    // transfer_lock for a set of known codes (see the plan's "What locked means")
    public static function lockFor(array $codes)
    {
        if (!$codes) return self::LOCK_UNKNOWN;
        if (array_intersect(self::TRANSFER_CODES, $codes)) return self::LOCK_ON;
        if (in_array('locked', $codes, true)) return self::LOCK_UNKNOWN;
        return self::LOCK_OFF;
    }

    /*
     * 'not_checked', 'failed' (never read, and a lookup failed: Couldn't Check), 'unknown', 'unlocked', or 'locked', from
     * the stored columns. A domain read before keeps its state when a later lookup fails (see failedDate()).
     */
    public static function state($transfer_lock, $epp_status_time, $epp_status_failed_time = '')
    {
        if (Time::isUnset($epp_status_time)) return Time::isUnset($epp_status_failed_time) ? 'not_checked' : 'failed';
        if ((int) $transfer_lock === self::LOCK_ON) return 'locked';
        if ((int) $transfer_lock === self::LOCK_OFF) return 'unlocked';
        return 'unknown';
    }

    // state() from a row's ds1_ columns
    public static function rowState($row)
    {
        return self::state($row->ds1_transfer_lock ?? 0, $row->ds1_epp_status_time ?? '', $row->ds1_epp_status_failed_time ?? '');
    }

    // The date (in the user's time zone) of a row's latest lookup when it failed after the last successful read, or ''
    public static function failedDate($row)
    {
        $failed = $row->ds1_epp_status_failed_time ?? '';
        if (Time::isUnset($failed) || !self::isNewer($failed, $row->ds1_epp_status_time ?? '')) return '';
        return (new Time())->toUserTimezone($failed, 'Y-m-d');
    }

    public static function isOnHold(array $codes)
    {
        return (bool) array_intersect(self::HOLD_CODES, $codes);
    }

    public static function isBeingDeleted(array $codes)
    {
        return (bool) array_intersect(self::DELETION_CODES, $codes);
    }

    // The registry problems a set of codes shows: 'hold', 'deletion', 'hold,deletion', or ''
    public static function problem(array $codes)
    {
        $problems = array();
        if (self::isOnHold($codes)) $problems[] = 'hold';
        if (self::isBeingDeleted($codes)) $problems[] = 'deletion';
        return implode(',', $problems);
    }

    // The state's name, as the column, filter, and export show it
    public static function stateLabel($state)
    {
        switch ($state) {
            case 'locked': return _('Locked');
            case 'unlocked': return _('Not Locked');
            case 'unknown': return _('Unknown');
            case 'failed': return _("Couldn't Check");
            default: return _('Not Checked');
        }
    }

    // The edit page's sentence for a state. $can_refresh adds how to check a domain that hasn't been checked.
    public static function stateSentence($state, $can_refresh = false)
    {
        switch ($state) {
            case 'locked':
                return _('The transfer lock is on.');
            case 'unlocked':
                return _('The transfer lock is off.') . ' ' . _("Unless you're transferring this domain, ask your registrar to turn on its transfer lock (also called a registrar lock or domain lock).") . ' ' . _("Some country-code registries don't use transfer locks.");
            case 'unknown':
                return _('The transfer lock status is unknown.') . ' ' . _("The registry didn't report a clear transfer lock status, or RDAP isn't available for this domain's TLD.");
            case 'failed':
                return _("The lock status couldn't be checked.") . ' ' . self::failedCauses();
            default:
                return _("The lock status hasn't been checked yet.") . ($can_refresh ? ' ' . _('Click Refresh to check it.') : '');
        }
    }

    // Why a lookup can fail, for a domain that couldn't be checked
    public static function failedCauses()
    {
        return _("This can happen when the domain isn't registered, when the registry's server doesn't answer, or when its TLD has neither RDAP nor WHOIS.");
    }

    // A short sentence for the saved state, for Refresh's "Lock status checked." note
    public static function shortSentence($state)
    {
        switch ($state) {
            case 'locked': return _('The transfer lock is on.');
            case 'unlocked': return _('The transfer lock is off.');
            default: return _('The transfer lock status is unknown.');
        }
    }

    // What a code means, translated (the code itself is a protocol name and isn't)
    public static function meaning($code)
    {
        $meanings = array(
            'ok' => _('the registry reports no restrictions or pending operations'),
            'inactive' => _('the domain has no name servers at the registry'),
            'addPeriod' => _("recently registered, within the registry's grace period"),
            'autoRenewPeriod' => _("recently renewed automatically by the registry, within its grace period"),
            'renewPeriod' => _("recently renewed, within the registry's grace period"),
            'transferPeriod' => _("recently transferred, within the registry's grace period"),
            'redemptionPeriod' => _('deleted, and can only be restored during the redemption period'),
            'pendingCreate' => _('a registration is being processed'),
            'pendingDelete' => _('the registry is deleting the domain'),
            'pendingRenew' => _('a renewal is being processed'),
            'pendingRestore' => _('a restore is being processed'),
            'pendingTransfer' => _('a transfer is being processed'),
            'pendingUpdate' => _('an update is being processed'),
            'clientDeleteProhibited' => _("your registrar won't allow the domain to be deleted"),
            'clientHold' => _("your registrar has put the domain on hold, so it doesn't resolve"),
            'clientRenewProhibited' => _("your registrar won't allow a renewal"),
            'clientTransferProhibited' => _("your registrar won't allow a transfer"),
            'clientUpdateProhibited' => _("your registrar won't allow changes"),
            'serverDeleteProhibited' => _("the registry won't allow the domain to be deleted"),
            'serverHold' => _("the registry has put the domain on hold, so it doesn't resolve"),
            'serverRenewProhibited' => _("the registry won't allow a renewal"),
            'serverTransferProhibited' => _("the registry won't allow a transfer"),
            'serverUpdateProhibited' => _("the registry won't allow changes"),
            'transferProhibited' => _("transfers aren't allowed"),
            'updateProhibited' => _("changes aren't allowed"),
            'deleteProhibited' => _("deletion isn't allowed"),
            'renewProhibited' => _("renewal isn't allowed"),
            'locked' => _("changes to the domain aren't allowed"));

        return $meanings[$code] ?? '';
    }

    /*
     * What a completed status read changes, by one set of rules for Refresh's result lines, the domain's notes, and the
     * history. $before is the saved row's array('transfer_lock', 'codes', 'time'), and $after the read's
     * array('read' => 'found', 'none', or 'whois', 'codes'). Returns array('lines' => translated sentences for the
     * result, 'notes' => English sentences for the domain's notes, 'info' => a translated note for the result when the
     * read changes nothing visible, 'history' => field => array(old, new)):
     * - the first read (never read before: Not Checked or Couldn't Check) isn't a change: it's saved quietly, with "Lock
     *   status checked." in 'info',
     * - a move between Locked and Not Locked is a lock change, while a move to or from Unknown isn't,
     * - On Hold or Being Deleted appearing or clearing is a change, but only between two reads that found codes,
     * - other codes coming and going are quiet, but count for the epp_status history.
     */
    public static function changes(array $before, array $after)
    {
        $result = array('lines' => array(), 'notes' => array(), 'info' => array(), 'history' => array());

        $after_codes = $after['read'] === 'found' ? self::ordered($after['codes']) : array();
        $after_lock = self::lockFor($after_codes);
        $after_state = self::state($after_lock, '2000-01-01 00:00:00');

        if (Time::isUnset($before['time'] ?? '')) {

            $result['info'][] = _('Lock status checked.') . ' ' . self::shortSentence($after_state);
            return $result;

        }

        $before_codes = self::ordered($before['codes'] ?? array());
        $before_lock = (int) ($before['transfer_lock'] ?? self::LOCK_UNKNOWN);
        $lock_values = array(self::LOCK_OFF, self::LOCK_ON);

        if ($before_lock !== $after_lock && in_array($before_lock, $lock_values, true) && in_array($after_lock, $lock_values, true)) {

            if ($after_lock === self::LOCK_OFF) {
                $result['lines'][] = _('Transfer lock turned off.');
                $result['notes'][] = 'Transfer lock turned off, according to RDAP.';
            } else {
                $result['lines'][] = _('Transfer lock turned on.');
                $result['notes'][] = 'Transfer lock turned on, according to RDAP.';
            }
            $result['history']['transfer_lock'] = array((string) $before_lock, (string) $after_lock);

        }

        // A read without codes (no usable codes, or WHOIS) isn't evidence the registry cleared anything
        if ($before_codes && $after_codes) {

            $problem_lines = array(
                'hold' => array(
                    array(_('The registry put this domain on hold.'), 'The registry put this domain on hold, according to RDAP.'),
                    array(_('The registry no longer lists this domain as on hold.'), 'The registry no longer lists this domain as on hold, according to RDAP.')),
                'deletion' => array(
                    array(_('The registry lists this domain as being deleted or restored.'), 'The registry lists this domain as being deleted or restored, according to RDAP.'),
                    array(_('The registry no longer lists this domain as being deleted or restored.'), 'The registry no longer lists this domain as being deleted or restored, according to RDAP.')));
            $was = array('hold' => self::isOnHold($before_codes), 'deletion' => self::isBeingDeleted($before_codes));
            $now = array('hold' => self::isOnHold($after_codes), 'deletion' => self::isBeingDeleted($after_codes));

            foreach ($problem_lines as $problem => $lines) {
                if ($was[$problem] === $now[$problem]) continue;
                $line = $now[$problem] ? $lines[0] : $lines[1];
                $result['lines'][] = $line[0];
                $result['notes'][] = $line[1];
            }

            if (self::problem($before_codes) !== self::problem($after_codes)) {
                $result['history']['registry_problem'] = array(self::problem($before_codes), self::problem($after_codes));
            }

            if ($before_codes !== $after_codes) {
                $result['history']['epp_status'] = array(self::toColumn($before_codes), self::toColumn($after_codes));
            }

        }

        return $result;
    }

    /*
     * Whether a status read that started at $started (UTC, Y-m-d H:i:s) may be saved over the stored epp_status_time:
     * only when it started later, so whichever of two overlapping lookups started later wins, whatever order they're
     * saved in. Two that start in the same second keep the one saved first.
     */
    public static function isNewer($started, $stored)
    {
        if (Time::isUnset($stored)) return true;
        return strcmp((string) $started, (string) $stored) > 0;
    }

    // The Registry Status filter's value from a request (anything else, or an array, is no filter)
    public static function normalizeFilter($value, $available = true)
    {
        if (!$available || !is_string($value)) return '';
        return in_array($value, self::FILTERS, true) ? $value : '';
    }

    /*
     * The filter's SQL condition, starting with " AND ", or '' for no filter. $alias is the domains table's alias with
     * its dot ('d.') or '' where a query names the table without one.
     */
    public static function filterCondition($filter, $alias = 'd.')
    {
        $alias = $alias === '' ? '' : 'd.';

        switch ($filter) {
            case 'locked': return " AND " . $alias . "transfer_lock = '2' ";
            case 'unlocked': return " AND " . $alias . "transfer_lock = '1' ";
            case 'unknown': return " AND " . $alias . "transfer_lock = '0' AND NOT (" . self::failedCondition($alias) . ") ";
            case 'failed': return " AND " . self::failedCondition($alias) . " ";
            case 'hold': return " AND (" . self::findInSet(self::HOLD_CODES, $alias . 'epp_status') . ") ";
            case 'deletion': return " AND (" . self::findInSet(self::DELETION_CODES, $alias . 'epp_status') . ") ";
            default: return '';
        }
    }

    // Never read, and a lookup failed (Couldn't Check)
    protected static function failedCondition($alias)
    {
        return "(" . $alias . "epp_status_time = '" . self::UNSET_TIME . "' AND " . $alias . "epp_status_failed_time > '"
            . self::UNSET_TIME . "')";
    }

    // FIND_IN_SET() for each code (fixed names from the list above, so nothing from a request) on a column, OR'd
    public static function findInSet(array $codes, $column)
    {
        return implode(' OR ', array_map(function ($code) use ($column) {
            return "FIND_IN_SET('" . $code . "', " . $column . ")";
        }, $codes));
    }

    // The filter's choices, value => label, in the order the toolbar lists them
    public static function filterChoices()
    {
        return array('locked' => _('Locked'), 'unlocked' => _('Not Locked'), 'unknown' => _('Unknown or Not Checked'),
            'failed' => _("Couldn't Check"), 'hold' => _('On Hold'), 'deletion' => _('Being Deleted or Restored'));
    }

    /*
     * Whether the database has the columns: version 4.27.0 or later, and the four domains columns and
     * user_settings.display_domain_lock all there (a restore that stopped part-way can leave an older domains table
     * under a 4.27.0 version). Worked out once per request for each connection, and false if it can't be read.
     */
    public static function isAvailable($pdo = null)
    {
        // Without a connection given, the shared one (a connection that fails isn't available)
        try {
            $pdo = $pdo ?? Database::getInstance()->cnxx;
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_object($pdo)) return false;

        $key = spl_object_id($pdo);
        if (isset(self::$available[$key])) return self::$available[$key];

        try {

            $version = (string) $pdo->query("
                SELECT db_version
                FROM settings")->fetchColumn();

            $available = $version !== '' && version_compare($version, self::AVAILABLE_FROM, '>=');

            if ($available) {

                $columns = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM `INFORMATION_SCHEMA`.`COLUMNS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND ((`TABLE_NAME` = 'domains'
                            AND `COLUMN_NAME` IN ('transfer_lock', 'epp_status', 'epp_status_time', 'epp_status_failed_time'))
                        OR (`TABLE_NAME` = 'user_settings' AND `COLUMN_NAME` = 'display_domain_lock'))")->fetchColumn();
                $available = $columns === 5;

            }

        } catch (\Throwable $e) {

            $available = false;

        }

        return self::$available[$key] = $available;
    }

    // Forgets what isAvailable() worked out (after the database update, and for tests)
    public static function resetAvailable()
    {
        self::$available = array();
    }

    /*
     * The edit page's Registry Status section, from the saved row's ds1_ columns: a warning when the registry has the
     * domain on hold or is deleting or restoring it, the lock state in a sentence, when it was last checked (and when a
     * later check failed), and the codes with what each means. $can_refresh is whether the
     * user can change data (and so can click Refresh).
     */
    public static function editSection($row, $can_refresh)
    {
        $codes = self::fromColumn($row->ds1_epp_status ?? '');
        $state = self::rowState($row);
        $html = '';

        $warnings = array();
        if (self::isOnHold($codes)) $warnings[] = _("The registry has this domain on hold, so it doesn't resolve.");
        if (in_array('pendingRestore', $codes, true)) {
            $warnings[] = _('The registry is restoring this domain.');
        } elseif (self::isBeingDeleted($codes)) {
            $warnings[] = _('The registry is deleting this domain. Ask your registrar right away if you want to keep it.');
        }
        if ($warnings) $html .= '<div class="callout callout-warning">' . self::escape(implode(' ', $warnings)) . '</div>';

        $layout = new Layout();
        $failed_date = self::failedDate($row);

        if ($state === 'failed' && $failed_date !== '') {
            $html .= '<p>' . sprintf(self::escape(_("The lock status couldn't be checked on %s.")), $layout->ltr($failed_date))
                . ' ' . self::escape(self::failedCauses()) . '</p>';
        } else {
            $html .= '<p>' . self::escape(self::stateSentence($state, $can_refresh)) . '</p>';
        }

        if (!in_array($state, array('not_checked', 'failed'), true)) {
            $date = (new Time())->toUserTimezone($row->ds1_epp_status_time, 'Y-m-d');
            if ($date !== '') $html .= '<p>' . sprintf(self::escape(_('Lock status last checked on %s.')), $layout->ltr($date)) . '</p>';
        }

        // A lookup that failed after the last successful read
        if ($state !== 'failed' && $failed_date !== '') {
            $html .= '<p>' . sprintf(self::escape(_('The latest check, on %s, failed, so this is the last status that could be read.')),
                $layout->ltr($failed_date)) . '</p>';
        }

        if ($codes) {
            $items = '';
            foreach ($codes as $code) {
                $items .= '<li>' . sprintf(self::escape(_('%1$s: %2$s')), '<code dir="ltr">' . self::escape($code) . '</code>', self::escape(self::meaning($code))) . '</li>';
            }
            $html .= '<ul class="mb-3">' . $items . '</ul>';
        }

        return '<div class="domainmod-span-all">' . $html . '</div>';
    }

    // A domain's saved status columns, under the ds1_ aliases editSection() reads, or false if the domain is gone
    public static function savedRow($pdo, $domain_id)
    {
        $stmt = $pdo->prepare("
            SELECT id" . self::selectSql('domains') . "
            FROM domains
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }

    /*
     * The edit page's Registry Status card (heading id domainmod-section-registry-status) in its wrapper, which the
     * in-place Refresh replaces with an out-of-band swap ($oob), since formSection() puts its id on the heading
     */
    public static function editCard($row, $can_refresh, $oob = false)
    {
        return '<div id="domainmod-registry-status"' . ($oob ? ' hx-swap-oob="true"' : '') . '>'
            . (new Layout())->formSection(_('Registry Status'), 'registry-status', self::editSection($row, $can_refresh))
            . '</div>';
    }

    /*
     * The Domains list's Transfer Lock cell from a row's ds1_ columns: an icon and the state, with when it was checked
     * on hover or focus, linking to the edit page's Registry Status section.
     */
    public static function listCell($row)
    {
        $state = self::rowState($row);
        $icons = array('locked' => array('bi-lock-fill', 'text-body-secondary'),
            'unlocked' => array('bi-unlock', 'text-warning-emphasis'),
            'unknown' => array('bi-question-circle', 'text-body-secondary'),
            'failed' => array('bi-exclamation-circle', 'text-body-secondary'),
            'not_checked' => array('bi-dash-circle', 'text-body-secondary'));
        list($icon, $class) = $icons[$state];

        $title = '';
        $failed_date = self::failedDate($row);
        if ($state === 'failed') {
            if ($failed_date !== '') $title = sprintf(_("Couldn't check on %s"), $failed_date);
        } elseif ($state !== 'not_checked') {
            $date = (new Time())->toUserTimezone($row->ds1_epp_status_time, 'Y-m-d');
            if ($date !== '') {
                $title = $failed_date !== '' ? sprintf(_('Last checked on %1$s. The latest check failed on %2$s.'), $date, $failed_date)
                    : sprintf(_('Checked %s'), $date);
            }
        }
        $tooltip = $title !== '' ? ' data-bs-toggle="tooltip" data-bs-title="' . self::escape($title) . '"' : '';

        return '<a href="edit.php?did=' . (int) $row->id . '#domainmod-section-registry-status" class="' . $class . '"'
            . $tooltip . '><i class="bi ' . $icon . '" aria-hidden="true"></i> ' . self::escape(self::stateLabel($state)) . '</a>';
    }

    // The domain cell's danger badges for On Hold, and Being Deleted or Being Restored, from a row's ds1_epp_status
    public static function problemBadges($row)
    {
        $codes = self::fromColumn($row->ds1_epp_status ?? '');
        $layout = new Layout();
        $html = '';

        if (self::isOnHold($codes)) {
            $html .= '&nbsp;' . $layout->statusBadge(_('On Hold'), _('On hold at the registry'), 'danger', 'bi-slash-circle');
        }
        if (in_array('pendingRestore', $codes, true)) {
            $html .= '&nbsp;' . $layout->statusBadge(_('Being Restored'), _('Being restored by the registry'), 'danger', 'bi-arrow-counterclockwise');
        } elseif (self::isBeingDeleted($codes)) {
            $html .= '&nbsp;' . $layout->statusBadge(_('Being Deleted'), _('Being deleted by the registry'), 'danger', 'bi-trash');
        }

        return $html;
    }

    // The three export columns' values from a row's ds1_ columns: the state's name, the codes, and the date checked
    public static function exportValues($row)
    {
        $state = self::rowState($row);
        $date = in_array($state, array('not_checked', 'failed'), true) ? '' : (new Time())->toUserTimezone($row->ds1_epp_status_time, 'Y-m-d');
        return array(self::stateLabel($state), self::toColumn(self::fromColumn($row->ds1_epp_status ?? '')), $date);
    }

    // The three export headings, in the order of exportValues()
    public static function exportHeadings()
    {
        return array(_('Transfer Lock'), _('Registry Status Codes'), _('Registry Status Checked'));
    }

    // The columns the lists and exports read, under aliases a custom field can't have (custom field names are letters
    // and underscores only), for $alias's domains table
    public static function selectSql($alias = 'd')
    {
        return ', ' . $alias . '.transfer_lock AS ds1_transfer_lock, ' . $alias . '.epp_status AS ds1_epp_status, '
            . $alias . '.epp_status_time AS ds1_epp_status_time, ' . $alias . '.epp_status_failed_time AS ds1_epp_status_failed_time';
    }

    protected static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

} //@formatter:on
