<?php
/**
 * /classes/DomainMOD/WatchListColumns.php
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
 * The Watch List's columns and each row's cells (see WatchList), for the page's first page and the data endpoint's rows
 * (watch/list-data.php), so the two can't differ, and the export's cells. Stored text is sanitised (entities such as
 * &amp;), so it's decoded once and escaped exactly once: domain names go into Layout::ltr(), which escapes them and
 * isolates them for right-to-left languages, and everything else through htmlspecialchars(). Nothing from a lookup is
 * ever printed as HTML.
 */
class WatchListColumns extends ListColumns
{
    const SESSION_KEYS = array('s_default_timezone', 's_read_only');
    const DEFAULT_SORT_KEY = 'stage';
    const NAME_KEY = 'domain';

    // The stages' badge colours, from Bootstrap's own classes so they follow light and dark mode (text with colour, never
    // colour alone): red for pending delete, a lighter red for redemption, amber for grace, green for not registered
    const BADGES = array('pending_delete' => 'text-bg-danger',
        'redemption' => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle', 'grace' => 'text-bg-warning',
        'expired' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'not_registered' => 'text-bg-success',
        'restore_pending' => 'text-bg-info', 'new' => 'text-bg-info', 'registered' => 'text-bg-secondary');

    protected $unsanitize;
    // The entries holding fast slots now (see WatchCheck::fastSlots()), for the Next Check column
    protected $slots = array();
    protected $now;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->layout = new Layout();
        $this->unsanitize = new Unsanitize();
        $this->now = time();

        $urgency = "CASE w.stage WHEN 'pending_delete' THEN 1 WHEN 'redemption' THEN 2 WHEN 'grace' THEN 3
            WHEN 'expired' THEN 4 WHEN 'not_registered' THEN 5 ELSE 6 END";
        $soonest = "CASE WHEN w.drop_basis <> '' THEN w.drop_from WHEN w.expiry_date <> '1970-01-01' THEN w.expiry_date
            ELSE '9999-12-31' END";

        $columns = array();
        // Read-only users can't change anything, so they get no tick boxes
        if (($settings['s_read_only'] ?? '') != '1') {
            $columns[] = array('key' => 'select', 'title' => '', 'sort' => false);
        }
        $columns[] = array('key' => 'domain', 'title' => _('Domain'), 'sort' => array('w.domain'));
        $columns[] = array('key' => 'stage', 'title' => _('Stage'), 'sort' => array($urgency, $soonest));
        $columns[] = array('key' => 'expiry', 'title' => _('Expiry'), 'sort' => array("w.expiry_date = '1970-01-01'", 'w.expiry_date'));
        // TRANSLATORS: a Watch List column heading, short for the domain's registration date, beside "Expiry"
        $columns[] = array('key' => 'registered', 'title' => _('Registered'), 'sort' => array("w.registration_date = '1970-01-01'", 'w.registration_date'));
        $columns[] = array('key' => 'drop', 'title' => _('Estimated Drop'), 'sort' => array("w.drop_basis = ''", 'w.drop_from'));
        $columns[] = array('key' => 'registrar', 'title' => _('Registrar'), 'sort' => array('w.registrar_name'));
        $columns[] = array('key' => 'reason', 'title' => _('Reason'), 'sort' => array('w.reason'));
        $columns[] = array('key' => 'added_by', 'title' => _('Added By'), 'sort' => array('u.first_name', 'u.last_name'));
        // TRANSLATORS: a Watch List column heading, the date the domain was added to the list, beside "Added By"
        $columns[] = array('key' => 'added', 'title' => _('Added'), 'sort' => array('w.insert_time', 'w.id'));
        $columns[] = array('key' => 'checked', 'title' => _('Last Checked'), 'sort' => array('w.last_attempt'));
        $columns[] = array('key' => 'next', 'title' => _('Next Check'), 'sort' => array('w.archived', 'GREATEST(w.next_due, w.hold_until)'));
        $this->columns = $columns;
    }

    // The entries holding fast slots now, and the time the Next Check column compares with
    public function setFastSlots(array $slots, $now)
    {
        $this->slots = $slots;
        $this->now = (int) $now;
    }

    // The search box looks in the domain, the registrar, and the notes
    public function searchExpressions()
    {
        return array('lower' => array('w.domain'), 'typed' => array(), 'idn' => array('w.domain'),
            'encoded' => array('w.registrar_name', 'w.notes'));
    }

    // The column heading cells: the tick box column's heading selects every row on the page
    public function headerCells()
    {
        $cells = array();
        foreach ($this->columns as $column) {
            if ($column['key'] === 'select') {
                $cells[] = '<th class="' . $this->columnClass($column) . '"><input class="form-check-input" type="checkbox" data-domainmod-select-all aria-label="'
                    . $this->esc(_('Select all on this page')) . '"></th>';
            } else {
                $cells[] = '<th class="' . $this->columnClass($column) . '">' . $this->esc($column['title']) . '</th>';
            }
        }
        return $cells;
    }

    protected function esc($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    // Stored text as plain text (decoded once, see the class comment)
    public function plain($text)
    {
        return (string) $this->unsanitize->text((string) $text);
    }

    // A stored UTC time in the viewer's time zone, with the time of day or ($format 'Y-m-d') the date only
    public function when($time, $format = 'Y-m-d H:i')
    {
        if (Time::isUnset($time)) return '';
        $date = new \DateTime((string) $time, new \DateTimeZone('UTC'));
        $date->setTimezone(Time::zone($this->settings['s_default_timezone'] ?? 'UTC'));
        return $date->format($format);
    }

    // A stage badge for a row (see WatchLifecycle::displayStage())
    public function stageBadge($row)
    {
        list($code, $label) = WatchLifecycle::displayStage($row);
        // The states that aren't a stage (not checked yet, checking, unable to check) are neutral
        $class = self::BADGES[$code] ?? 'bg-body-tertiary text-body border';
        return '<span class="badge domainmod-watch-badge ' . $class . '">' . $this->esc($label) . '</span>';
    }

    // The estimated drop window as text, such as "2027-03-01 to 2027-03-06", or ''
    public function dropText($row)
    {
        if ((string) $row->drop_basis === '' || Time::isUnset($row->drop_from)) return '';
        if ((string) $row->drop_from === (string) $row->drop_to) return (string) $row->drop_from;
        return sprintf(_('%1$s to %2$s'), $row->drop_from, $row->drop_to);
    }

    // The same as HTML, each date isolated left to right (for right-to-left languages)
    public function dropHtml($row)
    {
        if ((string) $row->drop_basis === '' || Time::isUnset($row->drop_from)) return '';
        if ((string) $row->drop_from === (string) $row->drop_to) return $this->layout->ltr((string) $row->drop_from);
        return sprintf($this->esc(_('%1$s to %2$s')), $this->layout->ltr((string) $row->drop_from), $this->layout->ltr((string) $row->drop_to));
    }

    // Who added an entry: the user's name, or "Unknown user" once they're gone
    public function addedBy($row)
    {
        $name = trim($this->plain($row->added_by ?? ''));
        return $name !== '' ? $name : _('Unknown user');
    }

    /*
     * Whether the latest attempt didn't update the entry's details (a failure, a hold, or a change still waiting for its
     * confirmation), so the details shown are from an earlier answer
     */
    public static function lastAttemptUnanswered($row)
    {
        $row = (object) $row;
        return (int) ($row->has_baseline ?? 0) === 1 && (string) ($row->last_attempt ?? '') > (string) ($row->last_success ?? '');
    }

    // When an entry's next check can be made: when it's due, or later while it's held (a rate limit or a busy server)
    public static function nextCheck($row)
    {
        $row = (object) $row;
        $hold = (string) ($row->hold_until ?? '');
        return $hold > (string) $row->next_due ? $hold : (string) $row->next_due;
    }

    // Whether an entry is waiting for a fast slot (its routine checks stay daily meanwhile)
    public function waitingForSlot($row)
    {
        if ((int) $row->archived === 1 || (string) $row->pending_stage !== '' || isset($this->slots[(int) $row->id])) return false;
        return WatchCheck::wantsFast((array) $row, $this->now);
    }

    /*
     * The edit page's note for an entry waiting for its confirming lookup (the Checking and Checking again stages):
     * when that lookup is due (the last attempt plus WatchCheck::CONFIRM_GAP, as Check Now works it out), or, once
     * that time has passed, that it's waiting for the Check Watch List task. $now is a Unix time, and $can_check adds
     * that Check Now can make it, for users who can press it. Returns '' when the entry isn't waiting, or is archived
     * (it isn't checked). The text is escaped, and the time isolated left to right (for right-to-left languages).
     */
    public function confirmNoteHtml($row, $now, $can_check)
    {
        list($code) = WatchLifecycle::displayStage($row);
        if (!in_array($code, array('checking', 'checking_again'), true) || (int) $row->archived === 1) return '';
        if (Time::isUnset($row->last_attempt)) return '';
        try {
            $due = (new \DateTimeImmutable(substr((string) $row->last_attempt, 0, 19) . ' UTC'))
                ->modify('+' . WatchCheck::CONFIRM_GAP . ' seconds')->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return '';
        }
        // (the stored times are UTC, in the same format, so they compare as text)
        if ($due > gmdate('Y-m-d H:i:s', (int) $now)) {
            // TRANSLATORS: %s is a date and time in the viewer's time zone, such as "2027-03-10 18:15"
            return sprintf($this->esc(_("A new result will be checked again around %s, and it's recorded only if the second lookup agrees.")),
                $this->layout->ltr($this->when($due)));
        }
        $note = _("A new result is waiting for a second lookup by the Check Watch List task, and it's recorded only if that lookup agrees.");
        return $this->esc($can_check ? $note . ' ' . _('You can also press Check Now.') : $note);
    }

    protected function cell(array $column, $row, $today)
    {
        switch ($column['key']) {
            case 'select':
                return '<input class="form-check-input" type="checkbox" name="wids[]" value="' . (int) $row->id
                    . '" form="watch-bulk-form" aria-label="' . $this->esc(sprintf(_('Select %s'), $this->plain($row->domain))) . '">';
            case 'domain':
                $text = '<a href="' . $this->esc(WEB_ROOT . '/watch/edit.php?wid=' . (int) $row->id) . '">'
                    . $this->layout->ltr($this->plain($row->domain)) . '</a>';
                if ((int) $row->archived === 1) $text .= ' <span class="text-body-secondary small">(' . $this->esc(_('Archived')) . ')</span>';
                return $text;
            case 'stage':
                return $this->stageBadge($row);
            case 'expiry':
                return (int) $row->has_baseline === 1 && !Time::isUnset($row->expiry_date) ? $this->esc($row->expiry_date) : '';
            case 'registered':
                return (int) $row->has_baseline === 1 && !Time::isUnset($row->registration_date) ? $this->esc($row->registration_date) : '';
            case 'drop':
                $drop = $this->dropHtml($row);
                if ($drop === '') return '';
                return $drop . ' <span class="text-body-secondary small">(' . $this->esc(WatchLifecycle::basisLabel($row->drop_basis)) . ')</span>';
            case 'registrar':
                return (int) $row->has_baseline === 1 ? $this->esc($this->plain($row->registrar_name)) : '';
            case 'reason':
                return $this->esc(WatchList::reasonLabel($row->reason));
            case 'added_by':
                return $this->esc($this->addedBy($row));
            case 'added':
                // The day it was added in the viewer's time zone, left to right in a right-to-left language
                $added = $this->when($row->insert_time, 'Y-m-d');
                return $added !== '' ? $this->layout->ltr($added) : '';
            case 'checked':
                $when = $this->when($row->last_attempt);
                if ($when === '') return '';
                // The latest attempt didn't update the row's details, so they're from an earlier check
                return $this->layout->ltr($when) . (self::lastAttemptUnanswered($row)
                    ? ' <span class="text-body-secondary small">(' . $this->esc(_('not updated')) . ')</span>' : '');
            case 'next':
                if ((int) $row->archived === 1) return '';
                $when = $this->when(self::nextCheck($row));
                $text = $when !== '' ? $this->layout->ltr($when) : '';
                if ($this->waitingForSlot($row)) {
                    $text .= ' <span class="text-body-secondary small">(' . $this->esc(_('Daily: fast checks full')) . ')</span>';
                }
                return $text;
        }
        return '';
    }

    // An export cell anyone could have supplied: the isolation marks removed first and then formula protection
    public static function exportCell(Export $export, $value)
    {
        return $export->protectFormula(str_replace(array("\u{2066}", "\u{2069}"), '', (string) $value));
    }

    // The export's headings
    public static function exportHeadings()
    {
        return array(_('Domain'), _('Stage'), _('Statuses'), _('Expiry Date'), _('Registration Date'),
            _('Estimated Drop From'), _('Estimated Drop To'), _('Estimate Basis'), _('Registrar'), _('Reason'), _('Notes'),
            _('Added By'), _('Added'), _('Last Checked'), _('Archived'));
    }

    // A row's export cells, in exportHeadings()' order
    public function exportCells(Export $export, $row)
    {
        $date = function ($value) {
            return Time::isUnset($value) ? '' : (string) $value;
        };
        list(, $stage) = WatchLifecycle::displayStage($row);
        $known = (int) $row->has_baseline === 1;
        return array(
            self::exportCell($export, $this->plain($row->domain)),
            $stage,
            $known ? implode(', ', DomainStatus::fromColumn($row->statuses)) : '',
            $known ? $date($row->expiry_date) : '',
            $known ? $date($row->registration_date) : '',
            (string) $row->drop_basis !== '' ? $date($row->drop_from) : '',
            (string) $row->drop_basis !== '' ? $date($row->drop_to) : '',
            WatchLifecycle::basisLabel($row->drop_basis),
            self::exportCell($export, $known ? $this->plain($row->registrar_name) : ''),
            WatchList::reasonLabel($row->reason),
            self::exportCell($export, $this->plain($row->notes)),
            self::exportCell($export, $this->addedBy($row)),
            $this->when($row->insert_time),
            $this->when($row->last_attempt),
            (int) $row->archived === 1 ? _('Yes') : _('No'));
    }

} //@formatter:on
