<?php
/**
 * /classes/DomainMOD/ChangeHistoryListColumns.php
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
 * The Change History list's columns (see ChangeHistoryList), and how a history row reads: field names, values, sources,
 * and notes, translated when shown (the rows hold raw values and codes), for the list, the edit pages' Change History
 * sections, and the export. Stored text is already sanitised (entities such as &amp;), so it's decoded first and
 * escaped exactly once: domain and server names go into Layout::ltr(), which escapes them and isolates them for
 * right-to-left languages, and everything else through htmlspecialchars().
 */
class ChangeHistoryListColumns extends ListColumns
{
    const SESSION_KEYS = array('s_default_timezone');
    const DEFAULT_SORT_KEY = 'when';
    const NAME_KEY = 'item';

    // The most lines a notes change shows in its cell (the whole text is a click away)
    const SUMMARY_LINES = 20;

    // The columns the edit pages' sections show
    const SECTION_KEYS = array('when', 'field', 'from', 'to', 'source', 'by');

    protected $unsanitize;
    // Creation type and user names for a snapshot's facts (see setFactNames()), or null to show their ids
    protected $creation_type_names = null;
    protected $user_names = null;
    // The action line for each operation on the page: operation id => array(changes, items)
    protected $operation_counts = array();

    public function __construct(array $settings, $keys = null)
    {
        $this->settings = $settings;
        $this->layout = new Layout();
        $this->unsanitize = new Unsanitize();

        /*
         * Item, Type, Source, and By sort by what their cells show, and the rows that tie go newest first (see
         * ChangeHistoryList::orderSql()):
         * - Item, by the item's name as recorded. It's stored HTML-encoded and sorted that way, so an index on it can
         *   serve the sort, which can put a name with &, <, >, or a quote slightly out of place.
         * - Type and Source, by the type's and the source's names in the viewer's language. Source sorts by the first
         *   part of its cell only (what the Source filter picks): the action's name is in change_operations, and
         *   sorting by it as well made the sort several times slower.
         * - By, by the username recorded with the action (decoded), DomainMOD for the system, or the user's id.
         */
        $type_names = array();
        foreach (array_keys(ChangeHistory::ITEM_TYPES) as $type) $type_names[$type] = self::typeName($type);
        $source_names = array();
        foreach (ChangeHistory::SOURCES as $source) $source_names[$source] = self::sourceText($source, '', '');
        $by_sort = "CASE WHEN ch.user_id = 0 THEN " . self::sqlText(SOFTWARE_TITLE) . "
            ELSE COALESCE(NULLIF(" . self::decodedSql("(SELECT co.username FROM change_operations AS co WHERE co.id = ch.operation_id)")
            . ", ''), CONCAT('#', ch.user_id)) END";

        $columns = array(
            array('key' => 'when', 'title' => _('When'), 'sort' => array('ch.changed_time', 'ch.operation_id')),
            array('key' => 'item', 'title' => _('Item'), 'sort' => array('ch.item_name')),
            array('key' => 'type', 'title' => _('Type'), 'sort' => array(self::nameSort('ch.item_type', $type_names))),
            array('key' => 'field', 'title' => _('What Changed'), 'sort' => false),
            array('key' => 'from', 'title' => _('From'), 'sort' => false),
            array('key' => 'to', 'title' => _('To'), 'sort' => false),
            array('key' => 'source', 'title' => _('Source'), 'sort' => array(self::nameSort('ch.source', $source_names))),
            array('key' => 'by', 'title' => _('By'), 'sort' => array($by_sort)),
            array('key' => 'note', 'title' => _('Note'), 'sort' => false));
        if ($keys !== null) {
            $columns = array_values(array_filter($columns, function ($column) use ($keys) {
                return in_array($column['key'], $keys, true);
            }));
        }
        $this->columns = $columns;
    }

    /*
     * An edit page's Change History section: the item's last 20 changes in its current life (When, What Changed, From,
     * To, Source, and By), and a link to every change kept for it on the Change History page. It's wrapped in an element
     * that's always on the page (#domainmod-change-history), so an in-place Refresh can replace it with an out-of-band
     * swap ($oob). Before the database update that adds the history, it's an empty wrapper.
     */
    public static function editSection(\PDO $pdo, $type, $id, $oob = false)
    {
        $wrapper = '<div id="domainmod-change-history"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
        if (!ChangeHistory::isAvailable($pdo)) return $wrapper . '</div>';

        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };
        $columns = new self(self::sessionCopy($_SESSION), self::SECTION_KEYS);
        $columns->setFactNames(Export::creationTypeNames($pdo), self::usernames($pdo));
        $list = new ChangeHistoryList($pdo, (int) ($_SESSION['s_user_id'] ?? 0));
        $rows = $list->itemRows($type, (int) $id, 20);
        $columns->setOperationCounts($list->operationCounts(array_map(function ($row) {
            return $row->operation_id;
        }, $rows)));

        if (!$rows) {
            $body = '<p class="domainmod-span-all mb-2">' . $esc(_('No changes recorded yet.')) . '</p>';
        } else {
            // The scroll region's own name, since the section around it is already named Change History
            $body = '<div class="domainmod-span-all domainmod-scroll-table domainmod-history-section mb-2" tabindex="0" role="region" aria-label="'
                . $esc($type === 'ssl' ? _('Recent changes to this SSL certificate') : _('Recent changes to this domain'))
                . '"><table class="table table-sm mb-0"><thead><tr>';
            foreach ($columns->columns() as $column) $body .= '<th scope="col">' . $esc($column['title']) . '</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $body .= '<tr><td>' . implode('</td><td>', $columns->cells($row, '')) . '</td></tr>';
            }
            $body .= '</tbody></table></div>';
        }

        $link_text = $type === 'ssl' ? _('See all changes to this SSL certificate') : _('See all changes to this domain');
        $body .= '<p class="domainmod-span-all mb-2"><a href="' . $esc(WEB_ROOT . '/history/?' . http_build_query(array(
            'type' => $type, 'id' => (int) $id, 'range' => 'all'))) . '">' . $esc($link_text) . '</a></p>';

        return $wrapper . (new Layout())->formSection(_('Change History'), 'history', $body) . '</div>';
    }

    /*
     * A sort expression that puts a column's stored codes in the order of their names ($names, code => name, in the
     * viewer's language), compared as the database compares any text. A code without a name sorts as itself.
     */
    public static function nameSort($column, array $names)
    {
        $sql = 'CASE ' . $column;
        foreach ($names as $code => $name) $sql .= ' WHEN ' . self::sqlText($code) . ' THEN ' . self::sqlText($name);
        return $sql . ' ELSE ' . $column . ' END';
    }

    // Stored text in SQL as the cells show it: the HTML entities the Sanitize class writes, decoded (&amp; last)
    public static function decodedSql($column)
    {
        foreach (array('&lt;' => '<', '&gt;' => '>', '&quot;' => '"', '&#039;' => "'", '&amp;' => '&') as $entity => $character) {
            $column = 'REPLACE(' . $column . ', ' . self::sqlText($entity) . ', ' . self::sqlText($character) . ')';
        }
        return $column;
    }

    /*
     * Text as a SQL string literal, for the names a sort compares (see nameSort()). A quote is doubled and a backslash
     * is doubled too, so a translation can't end the literal whether or not the server treats backslashes as escapes.
     */
    public static function sqlText($text)
    {
        return "'" . str_replace(array('\\', "'"), array('\\\\', "''"), (string) $text) . "'";
    }

    // Newest first
    public function defaultOrder()
    {
        return array(array(0, 'desc'));
    }

    // The search box looks in the item names and the values, which are stored HTML-encoded
    public function searchExpressions()
    {
        return array('lower' => array(), 'typed' => array(), 'idn' => array('ch.item_name'),
            'encoded' => array('ch.item_name', 'ch.old_value', 'ch.new_value', 'ch.old_display', 'ch.new_display'));
    }

    // The action lines' counts for the operations on this page (see ChangeHistoryList::operationCounts())
    public function setOperationCounts(array $counts)
    {
        $this->operation_counts = $counts;
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

    protected function cell(array $column, $row, $today)
    {
        switch ($column['key']) {
            case 'when':
                // Left to right in a right-to-left language (the date before the time), and on one line, so a narrow
                // table scrolls rather than squeezing it
                return '<bdi dir="ltr" class="text-nowrap">' . $this->esc($this->when($row->changed_time)) . '</bdi>';
            case 'item':
                return $this->itemCell($row);
            case 'type':
                return $this->esc(self::typeName($row->item_type));
            case 'field':
                return $this->fieldCell($row);
            case 'from':
                return $this->valueCell($row, 'old');
            case 'to':
                return $this->valueCell($row, 'new');
            case 'source':
                return $this->sourceCell($row);
            case 'by':
                return $this->esc($this->byText($row));
            case 'note':
                return $this->esc($this->noteText($row));
        }
        return '';
    }

    // A stored UTC time in the viewer's time zone (the zone the endpoint copied from the session)
    public function when($time)
    {
        if (Time::isUnset($time)) return '';
        $date = new \DateTime((string) $time, new \DateTimeZone('UTC'));
        $date->setTimezone(Time::zone($this->settings['s_default_timezone'] ?? 'UTC'));
        return $date->format('Y-m-d H:i');
    }

    public static function typeName($type)
    {
        return $type === 'ssl' ? _('SSL Certificate') : _('Domain');
    }

    // The item's edit page, while the row's item still exists (no delete row for its type and id after it)
    public static function itemUrl($row)
    {
        if ($row->change_type === 'delete' || (int) ($row->last_delete_id ?? 0) > (int) $row->id) return '';
        return WEB_ROOT . ($row->item_type === 'ssl' ? '/ssl/edit.php?sslcid=' : '/domains/edit.php?did=') . (int) $row->item_id;
    }

    protected function itemCell($row)
    {
        $name = $this->plain($row->item_name);
        $text = $row->item_type === 'domain' ? $this->layout->ltr($name) : $this->esc($name);
        $url = self::itemUrl($row);
        if ($url !== '') return '<a href="' . $this->esc($url) . '">' . $text . '</a>';
        $text .= ' <span class="text-body-secondary">(' . $this->esc(_('Deleted')) . ')</span>';
        // A delete row ends its item's life, which it links to, so an id used by several items in turn never mixes them
        if ($row->change_type === 'delete') {
            $text .= '<br><a class="small" href="' . $this->esc(WEB_ROOT . '/history/?' . http_build_query(array(
                'type' => $row->item_type, 'id' => (int) $row->item_id, 'life' => (int) $row->id, 'range' => 'all'))) . '">'
                . $this->esc(_("Show this item's changes")) . '</a>';
        }
        return $text;
    }

    /*
     * A field's name: the edit form's label for a column, the custom field's name at the time for a custom field, and
     * "Added" or "Deleted" for a whole item
     */
    public static function fieldName($row)
    {
        if ($row->change_type === 'create') return _('Added');
        if ($row->change_type === 'delete') return _('Deleted');

        $field = (string) $row->field;
        if (strpos($field, 'cf:') === 0) {
            $name = (string) $row->old_display;
            return $name !== '' ? (string) (new Unsanitize())->text($name) : substr($field, 3);
        }

        $names = self::fieldNames($row->item_type);
        return $names[$field] ?? $field;
    }

    // The names a snapshot's creation facts are shown with, read once for the page (Export::creationTypeNames() and
    // usernames())
    public function setFactNames(array $creation_type_names, array $user_names)
    {
        $this->creation_type_names = $creation_type_names;
        $this->user_names = $user_names;
    }

    // Every user's username by id, for a snapshot's Created By (see setFactNames())
    public static function usernames(\PDO $pdo)
    {
        $names = array();
        foreach ($pdo->query("SELECT id, username FROM users")->fetchAll(\PDO::FETCH_NUM) as $row) {
            $names[(int) $row[0]] = (string) $row[1];
        }
        return $names;
    }

    // An export cell anyone could have supplied: the isolation marks removed first and then formula protection, so a
    // value that starts with a mark and then = is still protected
    public static function exportCell(Export $export, $value)
    {
        return $export->protectFormula(str_replace(array("\u{2066}", "\u{2069}"), '', (string) $value));
    }

    // The fields' names by code, as their edit forms label them
    public static function fieldNames($type)
    {
        $shared = array('account_id' => $type === 'ssl' ? _('SSL Provider Account') : _('Registrar Account'),
            'owner_id' => _('Owner'), 'expiry_date' => _('Expiry Date'), 'cat_id' => _('Category'),
            'ip_id' => _('IP Address'), 'notes' => _('Notes'),
            'active' => $type === 'ssl' ? _('Certificate Status') : _('Domain Status'), 'total_cost' => _('Cost'));
        if ($type === 'ssl') {
            return $shared + array('name' => _('Host') . ' / ' . _('Label'), 'domain_id' => _('Domain'),
                'ssl_provider_id' => _('SSL Provider'), 'type_id' => _('Certificate Type'), 'issuer' => _('Issuer'));
        }
        return $shared + array('domain' => _('Domain'), 'registrar_id' => _('Registrar'), 'dns_id' => _('DNS Profile'),
            'hosting_id' => _('Web Hosting Provider'), 'function' => _('Function'), 'autorenew' => _('Auto-Renewal'),
            'privacy' => _('Privacy Enabled'), 'dns_servers' => _('DNS Servers'), 'transfer_lock' => _('Transfer Lock'),
            'registry_problem' => _('Registry Problem'), 'epp_status' => _('Registry Status'), 'live_ns' => _('Live DNS Servers'),
            'live_ips' => _('Live IP Addresses'));
    }

    protected function fieldCell($row)
    {
        $text = $this->esc(self::fieldName($row));
        if ($row->change_type !== 'create' && $row->change_type !== 'delete') return $text;

        // The snapshot, a click away
        $lines = array();
        foreach ($this->snapshotLines($row) as $line) {
            // Line breaks are kept by the stylesheet (domainmod-history-text), not one <br> per line
            // A domain name is isolated left to right, as the Item column and the edit pages show it
            $lines[] = '<dt>' . $this->esc($line[0]) . '</dt><dd class="domainmod-history-text">'
                . (!empty($line[2]) ? $this->layout->ltr($line[1]) : $this->esc($line[1])) . '</dd>';
        }
        if (!$lines) return $text;
        // The scrolling region is a div around the list, so the list keeps its own meaning, and its name is the row's own
        // (each region on the page has a different one)
        // TRANSLATORS: a snapshot's region, such as "Details of example.com, deleted (change 1234)"
        $region = sprintf(_('Details of %1$s, %2$s (change %3$s)'), $this->plain($row->item_name),
            $row->change_type === 'create' ? _('added') : _('deleted'), (int) $row->id);
        return $text . ' <details class="domainmod-history-details"><summary>' . $this->esc(_('Show details'))
            . '</summary><div tabindex="0" role="region" aria-label="' . $this->esc($region) . '"><dl class="mb-0">'
            . implode('', $lines) . '</dl></div></details>';
    }

    /*
     * A create or delete row's snapshot as (label, value) pairs, in plain text: every recorded field, then the custom
     * fields and the issuer
     */
    public function snapshotLines($row)
    {
        $snapshot = json_decode((string) $row->snapshot, true);
        if (!is_array($snapshot)) return array();

        $names = self::fieldNames($row->item_type) + array('fact:insert_time' => _('Added On'),
            'fact:creation_type_id' => _('Creation Type'), 'fact:created_by' => _('Created By'), 'fact:tld' => _('TLD'));
        $lines = array();
        foreach ($snapshot as $key => $entry) {
            if (!is_array($entry)) continue;
            if (strpos($key, 'fact:') === 0) {
                $value = (string) ($entry['v'] ?? '');
                if ($key === 'fact:insert_time') $value = $this->when($value);
                if ($key === 'fact:creation_type_id') {
                    $value = $this->creation_type_names !== null
                        ? (string) $this->unsanitize->text(Export::creationTypeName($this->creation_type_names, (int) $value)) : '#' . (int) $value;
                }
                if ($key === 'fact:created_by') {
                    // By username, as the By column shows who made a change (a zero creator is Unknown, and a user who's
                    // been deleted is shown by number)
                    $value = (int) $value === 0 ? _('Unknown') : ($this->user_names !== null && isset($this->user_names[(int) $value])
                        ? trim((string) $this->unsanitize->text($this->user_names[(int) $value])) : '#' . (int) $value);
                    if ($value === '') $value = '#' . (int) ($entry['v'] ?? 0);
                }
                // A TLD as it's written, with its dot
                if ($key === 'fact:tld' && $value !== '' && $value[0] !== '.') $value = '.' . $value;
                $lines[] = array($names[$key], $value);
                continue;
            }
            if (strpos($key, 'cf:') === 0) {
                $label = $this->plain($entry['name'] ?? substr($key, 3));
            } else {
                $label = $names[$key] ?? $key;
            }
            $value = $this->valueText($row->item_type, $key, (string) ($entry['v'] ?? ''), (string) ($entry['d'] ?? ''),
                !empty($entry['cb']));
            if (!empty($entry['cut'])) $value .= ' (' . _('Shortened') . ')';
            // (a third element marks a domain name, a domain's own or an SSL certificate's linked one)
            $lines[] = array($label, $value, $key === 'domain' || ($row->item_type === 'ssl' && $key === 'domain_id'));
        }
        // A snapshot too large for its limit left some custom fields out (see ChangeHistory::snapshotJson())
        if (!empty($snapshot['_cut'])) {
            $lines[] = array(_('Custom Fields'), _('Some custom fields were left out to keep this snapshot within its size limit.'));
        }
        return $lines;
    }

    /*
     * A value as plain text: a status by name, a flag as Yes or No, an id by the asset's name at the time (an SSL
     * certificate's domain 0, or one whose domain is gone, as None), a cost with its currency, and text decoded
     */
    public function valueText($type, $field, $value, $display, $checkbox = false)
    {
        // A custom check box recorded as one (see ChangeHistoryOperation) shows Yes or No, as the built-in flags do. Rows
        // from before that was recorded keep the stored value, since a field's name may since have been reused.
        if (strpos($field, 'cf:') === 0) {
            if ($checkbox && ($value === '0' || $value === '1')) return $value === '1' ? _('Yes') : _('No');
            return $this->plain($value);
        }
        $kind = ChangeHistory::FIELDS[$type][$field] ?? 'text';

        if ($field === 'active') return self::statusName($type, (int) $value);
        // The lock and registry problems by the names the Domains list and the edit page use (DomainMOD\DomainStatus)
        if ($field === 'transfer_lock') {
            return DomainStatus::stateLabel((int) $value === DomainStatus::LOCK_ON ? 'locked'
                : ((int) $value === DomainStatus::LOCK_OFF ? 'unlocked' : 'unknown'));
        }
        if ($field === 'registry_problem') {
            $problems = array_filter(explode(',', $value));
            if (!$problems) return _('None');
            $choices = DomainStatus::filterChoices();
            return implode(', ', array_map(function ($problem) use ($choices) {
                return $choices[$problem] ?? $problem;
            }, $problems));
        }
        if ($field === 'epp_status' && $value === '') return _('None');
        if ($field === 'autorenew' || $field === 'privacy') return (int) $value === 1 ? _('Yes') : _('No');
        if ($kind === 'id') {
            if ($display !== '') return $this->plain($display);
            if ($type === 'ssl' && $field === 'domain_id') return _('None');
            return (int) $value === 0 ? _('None') : '#' . (int) $value;
        }
        if ($kind === 'cost') {
            return $display !== '' ? $value . ' ' . $this->plain($display) : sprintf(_('%s (no currency)'), $value);
        }
        if ($kind === 'date') return Time::isUnset($value) ? '' : $value;
        return $this->plain($value);
    }

    public static function statusName($type, $code)
    {
        $names = array(0 => _('Expired'), 1 => _('Active'), 2 => _('Pending (Transfer)'), 3 => _('Pending (Renewal)'),
            4 => _('Pending (Other)'), 5 => _('Pending (Registration)'), 10 => _('Sold'));
        return $names[$code] ?? (string) $code;
    }

    // Whether the row's value on one side was cut to fit (see ChangeHistoryOperation)
    public static function shortened($row, $side)
    {
        return in_array($side . '_truncated', explode(',', (string) $row->detail), true);
    }

    protected function valueCell($row, $side)
    {
        if ($row->change_type !== 'update') return '';

        $field = (string) $row->field;
        $value = (string) $row->{$side . '_value'};
        // A custom field's display column holds its name, not a value's
        $display = strpos($field, 'cf:') === 0 ? '' : (string) $row->{$side . '_display'};
        $shortened = self::shortened($row, $side)
            ? ' <span class="badge text-bg-secondary">' . $this->esc(_('Shortened')) . '</span>' : '';

        // Notes: the lines added (To) or removed (From), with the whole stored text a click away
        if ($field === 'notes' || $this->isLongCustomField($row)) {
            // A shortened side's last line may be cut part-way, so it's left out of the comparison (it's still in the
            // retained text a click away)
            $old_lines = $this->plain($row->old_value);
            $new_lines = $this->plain($row->new_value);
            if (self::shortened($row, 'old')) $old_lines = preg_replace('/(^|\r\n?|\n)[^\r\n]*\z/', '', $old_lines);
            if (self::shortened($row, 'new')) $new_lines = preg_replace('/(^|\r\n?|\n)[^\r\n]*\z/', '', $new_lines);
            list($removed, $added) = self::lineChanges($old_lines, $new_lines);
            // A shortened side may have lost lines past its end, so lines missing from it can't be called removed (or,
            // for the old side, added), and kept texts that differ only in a line left out can't be compared at all
            // (identical retained texts mean the change was past what's kept, which the message below says)
            $cut = self::shortened($row, 'old') || self::shortened($row, 'new');
            $same_kept = (string) $row->old_value === (string) $row->new_value;
            $unknown = !$same_kept && ((self::shortened($row, 'new') && $removed) || (self::shortened($row, 'old') && $added)
                || ($cut && !$removed && !$added));
            if (self::shortened($row, 'new')) $removed = array();
            if (self::shortened($row, 'old')) $added = array();
            $lines = $side === 'old' ? $removed : $added;
            if (!$removed && !$added && $unknown) {
                $summary = $side === 'new' ? $this->esc(_("This change can't be shown from the text kept in history.")) : '';
            } elseif (!$removed && !$added) {
                // The same lines: the change was past the text kept (a side was shortened and the kept text is the same),
                // only the line breaks changed, or only moved lines or blank lines
                $old_kept = (string) $row->old_value;
                $new_kept = (string) $row->new_value;
                if ($old_kept === $new_kept && (self::shortened($row, 'old') || self::shortened($row, 'new'))) {
                    $same_lines = _('The change is outside the text kept in history.');
                } elseif (preg_replace('/\r\n?/', "\n", $old_kept) === preg_replace('/\r\n?/', "\n", $new_kept)) {
                    $same_lines = _('Only the line breaks changed.');
                } else {
                    $same_lines = _('Only the order of the lines or the blank lines changed.');
                }
                $summary = $side === 'new' ? $this->esc($same_lines) : '';
                // Only blank lines added: From says nothing went
                if ($side === 'old' && !$cut && self::onlyAdded($old_lines, $new_lines)
                    && self::lineCount($new_lines) > self::lineCount($old_lines)) {
                    $summary = '<span class="text-body-secondary">' . $this->esc(_('Nothing removed')) . '</span>';
                }
            } elseif ($side === 'old' && !$removed && !self::shortened($row, 'new') && self::onlyAdded($old_lines, $new_lines)) {
                // Lines were only added (no line went, a blank one included), which From says rather than staying blank
                $summary = '<span class="text-body-secondary">' . $this->esc(_('Nothing removed')) . '</span>';
            } else {
                // At most SUMMARY_LINES lines, so a note of thousands of short lines stays a short cell
                $more = count($lines) > self::SUMMARY_LINES;
                $summary = '<span class="domainmod-history-text domainmod-history-preview">' . $this->esc(implode("\n", array_slice($lines, 0, self::SUMMARY_LINES))
                    . ($more ? "\n…" : '')) . '</span>';
            }
            $full = $this->plain($value);
            // A shortened value shows the text kept, never the whole note it came from
            $label = $shortened !== '' ? _('Show retained text') : _('Show full text');
            // The region's name is the row's own (each region on the page has a different one)
            // TRANSLATORS: a long value's region, such as "example.com, Notes, From (change 1234)"
            $region = sprintf(_('%1$s, %2$s, %3$s (change %4$s)'), $this->plain($row->item_name), self::fieldName($row),
                $side === 'old' ? _('From') : _('To'), (int) $row->id);
            $details = $full !== '' ? ' <details class="domainmod-history-details"><summary>' . $this->esc($label)
                . '</summary><div class="domainmod-history-text" tabindex="0" role="region" aria-label="' . $this->esc($region) . '">'
                . $this->esc($full) . '</div></details>' : '';
            return $summary . $shortened . $details;
        }

        if ($field === 'dns_servers' || $field === 'live_ns') {
            $servers = array_filter(explode("\n", $this->plain($value)), 'strlen');
            return implode('<br>', array_map(array($this->layout, 'ltr'), $servers)) . $shortened;
        }

        $text = $this->valueText($row->item_type, $field, $value, $display, self::isCheckbox($row));
        if ($row->item_type === 'ssl' && $field === 'domain_id' && $display !== '') return $this->layout->ltr($text) . $shortened;
        if ($row->item_type === 'domain' && $field === 'domain') return $this->layout->ltr($text) . $shortened;
        return $this->esc($text) . $shortened;
    }

    // Whether every line of $old (blank ones too) is still in $new, so the change only added lines
    public static function onlyAdded($old, $new)
    {
        $split = function ($text) {
            return $text === '' ? array() : (array) preg_split('/\r\n|\r|\n/', $text);
        };
        $new_counts = array_count_values($split($new));
        foreach (array_count_values($split($old)) as $line => $count) {
            if (($new_counts[$line] ?? 0) < $count) return false;
        }
        return true;
    }

    // How many lines $text has, counting an empty text as none, as onlyAdded() does
    public static function lineCount($text)
    {
        return $text === '' ? 0 : count((array) preg_split('/\r\n|\r|\n/', $text));
    }

    // Whether a row is a custom check box's change, as it was when recorded (its cf_checkbox detail code)
    public static function isCheckbox($row)
    {
        return in_array('cf_checkbox', explode(',', (string) $row->detail), true);
    }

    // Whether a custom field row is long text: its value has line breaks, or a side was shortened to fit
    protected function isLongCustomField($row)
    {
        // A long value on one line (such as pasted JSON) counts too, so it gets the bounded preview and its disclosure
        return strpos((string) $row->field, 'cf:') === 0
            && (strpos((string) $row->old_value, "\n") !== false || strpos((string) $row->new_value, "\n") !== false
                || strlen((string) $row->old_value) > 500 || strlen((string) $row->new_value) > 500
                || self::shortened($row, 'old') || self::shortened($row, 'new'));
    }

    /*
     * The lines removed from $old and added in $new, as array(removed, added), compared as whole lines with their counts,
     * so a note that gained a line shows only that line
     */
    public static function lineChanges($old, $new)
    {
        $split = function ($text) {
            return $text === '' ? array() : preg_split('/\r\n|\r|\n/', $text);
        };
        $old_lines = $split($old);
        $new_lines = $split($new);
        $old_counts = array_count_values($old_lines);
        $new_counts = array_count_values($new_lines);

        $removed = array();
        foreach ($old_lines as $line) {
            if (($new_counts[$line] ?? 0) > 0) {
                $new_counts[$line]--;
            } else {
                $removed[] = $line;
            }
        }
        $added = array();
        foreach ($new_lines as $line) {
            if (($old_counts[$line] ?? 0) > 0) {
                $old_counts[$line]--;
            } else {
                $added[] = $line;
            }
        }
        $blank = function ($line) {
            return trim($line) !== '';
        };
        return array(array_values(array_filter($removed, $blank)), array_values(array_filter($added, $blank)));
    }

    // Where a change came from, such as "Bulk Updater: Change DNS Profile" or "Refresh, RDAP"
    public static function sourceText($source, $action, $via)
    {
        $sources = array('user' => _('Add or Edit Page'), 'bulk' => _('Bulk Updater'), 'csv_import' => _('CSV Importer'),
            'queue' => _('Domain Queue'), 'ssl_queue' => _('SSL Queue'), 'refresh' => _('Refresh'),
            'scheduled_refresh' => _('Scheduled Refresh'), 'asset' => _('Asset Change'), 'maintenance' => _('Maintenance'),
            'custom_field' => _('Custom Field Deleted'), 'watch_list' => _('Watch List'), 'api' => _('API'),
            'upgrade' => _('Database Upgrade'), 'restore' => _('Restore'), 'system' => SOFTWARE_TITLE);
        $text = $sources[$source] ?? $source;

        $actions = self::actionNames();
        if (($actions[$action] ?? '') !== '') $text .= ': ' . $actions[$action];

        $vias = array('registrar' => _('Registrar API'), 'rdap' => 'RDAP', 'whois' => 'WHOIS', 'dns' => 'DNS',
            'tls' => 'TLS');
        if (isset($vias[$via])) $text .= ', ' . $vias[$via];
        return $text;
    }

    // The finer actions' names (change_operations.action), such as a Bulk Updater action or an asset page
    public static function actionNames()
    {
        return array(
            'bulk_AD' => _('Add Domains'),
            'bulk_RENEW' => _('Renew Domains (Update Expiry Date Only)'),
            'bulk_FR' => _('Renew Domains (Update Expiry Date, Mark Active, Add Note)'),
            'bulk_E' => _('Mark as Expired'),
            'bulk_S' => _('Mark as Sold'),
            'bulk_A' => _('Mark as Active'),
            'bulk_T' => _('Mark as Pending Transfer'),
            'bulk_PRg' => _('Mark as Pending Registration'),
            'bulk_PRn' => _('Mark as Pending Renewal'),
            'bulk_PO' => _('Mark as Pending (Other)'),
            'bulk_AURNE' => _('Mark as Auto-Renewal'),
            'bulk_AURND' => _('Mark as Manual Renewal'),
            'bulk_PRVE' => _('Mark as Private WHOIS'),
            'bulk_PRVD' => _('Mark as Public WHOIS'),
            'bulk_CED' => _('Change Expiry Date'),
            'bulk_CPC' => _('Change Category'),
            'bulk_CDNS' => _('Change DNS Profile'),
            'bulk_CIP' => _('Change IP Address'),
            'bulk_CRA' => _('Change Registrar Account'),
            'bulk_CWH' => _('Change Web Hosting Provider'),
            'bulk_DD' => _('Delete Domains'),
            'bulk_AN' => _('Add a Note'),
            'bulk_CF' => _('Update Custom Domain Field'),
            'queue_edit' => _('Fix and Import'),
            'domain_refresh' => '',
            'ssl_refresh' => '',
            'registrar_fee_add' => _('Fee Added'),
            'registrar_fee_edit' => _('Fee Changed'),
            'ssl_fee_add' => _('Fee Added'),
            'ssl_fee_edit' => _('Fee Changed'),
            'zero_out_domain_fees' => _('Missing Fees Set to Zero'),
            'zero_out_ssl_fees' => _('Missing Fees Set to Zero'),
            'registrar_account_edit' => _('Registrar Account Changed'),
            'ssl_account_edit' => _('SSL Provider Account Changed'),
            'registrar_delete' => _('Registrar Deleted'),
            'ssl_provider_delete' => _('SSL Provider Deleted'),
            'ssl_type_delete' => _('SSL Type Deleted'),
            'cleanup' => _('System Cleanup'),
            'update_domain_fees' => _('Update Domain Fees'),
            'update_ssl_fees' => _('Update SSL Fees'));
    }

    protected function sourceCell($row)
    {
        $text = $this->esc(self::sourceText($row->source, (string) ($row->op_action ?? ''), $row->via));
        $operation_id = (int) $row->operation_id;
        $counts = $this->operation_counts[$operation_id] ?? null;
        if ($counts === null || $counts[0] < 2) return $text;

        // TRANSLATORS: one action's counts, such as "1,204 changes to 1,180 items" (%1$s changes, %2$s items)
        $line = sprintf(ngettext('%1$s change to %2$s items', '%1$s changes to %2$s items', $counts[0]),
            number_format($counts[0]), number_format($counts[1]));
        if ($counts[1] === 1) {
            $line = sprintf(ngettext('%s change to 1 item', '%s changes to 1 item', $counts[0]), number_format($counts[0]));
        }
        return $text . '<br><a class="small text-body-secondary" href="' . $this->esc(WEB_ROOT . '/history/?op=' . $operation_id
            . '&range=all') . '">' . $this->esc($line) . '</a>';
    }

    // Who made the change: the username at the time, or DomainMOD for the system
    public function byText($row)
    {
        if ((int) $row->user_id === 0) return SOFTWARE_TITLE;
        $name = $this->plain($row->op_username ?? '');
        return $name !== '' ? $name : '#' . (int) $row->user_id;
    }

    // Why: the Bulk Updater's note, a fee change's summary, or "Also changed by this action" for a side effect
    public function noteText($row)
    {
        $parts = array();
        if (in_array('side_effect', explode(',', (string) $row->detail), true)) $parts[] = _('Also changed by this action.');
        $code = (string) ($row->op_note_code ?? '');
        $note = $this->plain($row->op_note ?? '');
        if ($code === 'fee') {
            $added = substr((string) ($row->op_action ?? ''), -4) === '_add';
            $parts[] = sprintf($added ? _('The fee for %s was added.') : _('The fee for %s was changed.'), $note);
        } elseif ($note !== '') {
            $parts[] = $note;
        }
        return implode(' ', $parts);
    }

} //@formatter:on
