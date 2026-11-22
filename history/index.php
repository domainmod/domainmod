<?php
/**
 * /history/index.php
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
?>
<?php //@formatter:off
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$date = new DomainMOD\Date();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/history-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Each type's custom field table. Hosting Services are a type once the database update adds the hosting tables
// (DomainMOD\Hosting).
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$custom_field_tables = array('domain' => 'domain_fields', 'ssl' => 'ssl_cert_fields');
if ($hosting_available) $custom_field_tables['hosting'] = 'hosting_service_fields';

/*
 * The Change History (see DomainMOD\ChangeHistory): every change to a domain, SSL certificate, hosting service, or
 * watched domain, whoever or whatever made it, newest first. Every signed-in user sees it, read-only users included, as
 * they see every item. The bootstrap's restore gate holds the shared restore lock until the request ends, so the page
 * and its export never read history part-way into a restore.
 */

// The filter panel posts its form here, and the page moves to the address of the filters it chose, so the filters
// always live in the address (as the data endpoint and the export read them)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Dates are a custom range when Custom Range is chosen, or when they're new (typed or picked under a preset), and
    // are dropped when another preset is chosen
    $post = $_POST;
    $posted_dates = is_string($post['daterange'] ?? null) ? trim($post['daterange']) : '';
    $previous_dates = is_string($post['daterange_was'] ?? null) ? trim($post['daterange_was']) : '';
    if (($post['range'] ?? '') === 'custom' || ($posted_dates !== '' && $posted_dates !== $previous_dates)) {
        $post['range'] = 'custom';
    } else {
        $post['daterange'] = '';
    }
    // An item picked by id belongs to its type, so a new type drops it (as removing the type's chip does)
    if (($post['id'] ?? '') !== '' && ($post['type'] ?? '') !== ($post['type_was'] ?? '')) {
        unset($post['id'], $post['life']);
    }
    $posted = DomainMOD\ChangeHistoryList::normalizeState($post);
    if ($posted['range_invalid']) {
        $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The date range you entered is invalid.') . '<BR>';
    }
    // When the type changes, a What Changed choice the new type doesn't have is dropped, so it can't hide every row while
    // the menu shows All (with the type unchanged, a deleted custom field's choice stays, as keepOption() keeps it)
    if ($posted['type'] !== '' && $posted['field'] !== '' && $posted['type'] !== ($post['type_was'] ?? '')) {
        if (strpos($posted['field'], 'cf:') === 0) {
            $posted_fields = isset($custom_field_tables[$posted['type']])
                ? DomainMOD\CustomField::readFilterFields($pdo, $custom_field_tables[$posted['type']]) : array();
            $posted_names = array_map('strtolower', array_column($posted_fields, 'field_name'));
            if (!in_array(strtolower(substr($posted['field'], 3)), $posted_names, true)) $posted['field'] = '';
        } elseif (!isset(DomainMOD\ChangeHistoryListColumns::fieldNames($posted['type'])[$posted['field']])) {
            $posted['field'] = '';
        }
    }
    $query = array();
    foreach (array('type', 'id', 'life', 'name', 'source', 'user', 'field', 'op', 'daterange') as $key) {
        if ($posted[$key] !== '' && $posted[$key] !== 0) $query[$key] = $posted[$key];
    }
    // DomainMOD itself is chosen as 'system', as the menu and the chip name it
    if ($posted['user'] === -1) $query['user'] = 'system';
    if ($posted['range'] !== DomainMOD\ChangeHistoryList::DEFAULT_RANGE) $query['range'] = $posted['range'];
    // Dates mean a custom range (Custom Range without dates yet shows everything until they're picked)
    if (($query['daterange'] ?? '') !== '') $query['range'] = 'custom';
    header('Location: ' . WEB_ROOT . '/history/' . ($query ? '?' . http_build_query($query) : ''));
    exit;

}

$state = DomainMOD\ChangeHistoryList::normalizeState($_GET);
$expand = (int) ($_GET['expand'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);
list($start_date, $end_date) = $date->splitAndCheckRange($sanitize->text($state['daterange']));
// Dates that were given but aren't a valid range are said so, rather than quietly showing every date
if ($state['range_invalid']) {
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The date range you entered is invalid.') . '<BR>';
}
$start_date = $start_date === 'YYYY-MM-DD' ? '' : $start_date;
$end_date = $end_date === 'YYYY-MM-DD' ? '' : $end_date;
$zone = $_SESSION['s_default_timezone'] ?? 'UTC';
list($from_time, $to_time) = DomainMOD\ChangeHistoryList::rangeTimes($state['range'], $zone, $start_date, $end_date);

$history_available = DomainMOD\ChangeHistory::isAvailable($pdo);
$history_list = new DomainMOD\ChangeHistoryList($pdo, (int) $_SESSION['s_user_id']);
$filter = $history_available ? $history_list->filterParts($state, $from_time, $to_time)
    : array('sql' => ' AND 1 = 0', 'params' => array(), 'life_ok' => true);
$list_columns = new DomainMOD\ChangeHistoryListColumns(DomainMOD\ChangeHistoryListColumns::sessionCopy($_SESSION));
$list_columns->setFactNames(DomainMOD\Export::creationTypeNames($pdo), DomainMOD\ChangeHistoryListColumns::usernames($pdo));

// The range presets' names, as the range menu and its chip show them
$range_names = array('today' => _('Today'), '7' => _('Last 7 Days'), '30' => _('Last 30 Days'), '90' => _('Last 90 Days'),
    'all' => _('All Time'), 'custom' => _('Custom Range'));
$source_names = array();
foreach (DomainMOD\ChangeHistory::SOURCES as $source_code) {
    if (in_array($source_code, array('restore', 'system', 'api', 'scheduled_refresh'), true)) continue;
    $source_names[$source_code] = DomainMOD\ChangeHistoryListColumns::sourceText($source_code, '', '');
}

if ($export_data === 1 && $history_available) {

    // Where an export that can't be made sends the user back: the list, with the export's filters
    $back_url = 'index.php?' . http_build_query(array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout'))));

    // A streamed export (see Export::begin()), read and written in chunks, as the Domains export is
    $export = new DomainMOD\Export();
    $export->begin($back_url);

    // The table's search box, when the link carries it (js/server-list.js adds it), so the export holds the rows the
    // table shows
    $table_search = $history_list->tableSearchSql($_GET['dt_search'] ?? '', $_GET['dt_layout'] ?? '',
        DomainMOD\ChangeHistoryListColumns::sessionCopy($_SESSION));
    if ($table_search['stale']) $table_search = array('sql' => '', 'params' => array());

    try {

        $export->startSnapshot($history_list);
        $total_rows = $history_list->count($filter['sql'] . $table_search['sql'], $table_search['params'] + $filter['params']);
        $export_file = $export->openStream(_('change_history'), strtotime($time->stamp()));

        // Every cell anyone could have supplied (see DomainMOD\ChangeHistoryListColumns::exportCell())
        $cell = function ($value) use ($export) {
            return DomainMOD\ChangeHistoryListColumns::exportCell($export, $value);
        };

        $export->writeRow($export_file, array(_('Change History Export')));
        $export->writeBlankRow($export_file);
        $export->writeRow($export_file, array(_('When'), _('Item'), _('Item ID'), _('Type'), _('Change Type'), _('What Changed'),
            _('From'), _('To'), _('From (Stored)'), _('To (Stored)'), _('Source'), _('By'), _('Note'), _('Detail'),
            _('Snapshot')));
        $export->chunkWritten();

        foreach ($history_list->exportChunks($filter['sql'] . $table_search['sql'], $table_search['params'] + $filter['params']) as $chunk) {

            foreach ($chunk as $row) {

                $change_names = array('create' => _('Added'), 'update' => _('Changed'), 'delete' => _('Deleted'));
                $field_name = $row->change_type === 'update' ? DomainMOD\ChangeHistoryListColumns::fieldName($row) : '';
                $is_custom = strpos((string) $row->field, 'cf:') === 0;
                $checkbox = DomainMOD\ChangeHistoryListColumns::isCheckbox($row);
                $from = $row->change_type === 'update' ? $list_columns->valueText($row->item_type, $row->field, $row->old_value,
                    $is_custom ? '' : $row->old_display, $checkbox) : '';
                $to = $row->change_type === 'update' ? $list_columns->valueText($row->item_type, $row->field, $row->new_value,
                    $is_custom ? '' : $row->new_display, $checkbox) : '';
                $details = array();
                if (DomainMOD\ChangeHistoryListColumns::shortened($row, 'old')) $details[] = _('From: Shortened');
                if (DomainMOD\ChangeHistoryListColumns::shortened($row, 'new')) $details[] = _('To: Shortened');
                // A change made as a side effect is said in the Note column (see noteText()), not repeated here

                // A create or delete row's snapshot as JSON, with its text decoded, so a deleted item's values can be
                // recovered from the export
                $snapshot = '';
                $decoded = json_decode((string) $row->snapshot, true);
                if (is_array($decoded)) {
                    array_walk_recursive($decoded, function (&$value) use ($unsanitize) {
                        if (is_string($value)) $value = $unsanitize->text($value);
                    });
                    $snapshot = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    // Directional isolates as JSON escapes, since the CSV cell helper strips them from a cell's text
                    $snapshot = str_replace(array("\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}"), array('\u2066', '\u2067', '\u2068', '\u2069'), $snapshot);
                }

                $export->writeDataRow($export_file, array(
                    $list_columns->when($row->changed_time),
                    $cell($list_columns->plain($row->item_name)),
                    (int) $row->item_id,
                    DomainMOD\ChangeHistoryListColumns::typeName($row->item_type),
                    $change_names[$row->change_type] ?? $row->change_type,
                    $cell($field_name),
                    $cell($from),
                    $cell($to),
                    $cell($list_columns->plain($row->old_value)),
                    $cell($list_columns->plain($row->new_value)),
                    DomainMOD\ChangeHistoryListColumns::sourceText($row->source, (string) $row->op_action, $row->via),
                    $cell($list_columns->byText($row)),
                    $cell($list_columns->noteText($row)),
                    implode(', ', $details),
                    $cell($snapshot)));

            }

            $export->chunkWritten();

        }

        $export->finish($total_rows);

    } catch (Throwable $e) {

        $export->fail($e);

    }

}

// What Changed: the recorded fields of the chosen type, by their edit forms' labels. With every type, a field that
// reads differently for each lists each label, and an SSL certificate's, hosting service's, or watched domain's own
// field is marked as one
$field_types = $state['type'] !== '' ? array($state['type']) : array_merge(array_keys($custom_field_tables), array('watch'));
$field_labels = array();
foreach ($field_types as $field_type) {
    foreach (DomainMOD\ChangeHistoryListColumns::fieldNames($field_type) as $field_code => $field_label) {
        if (isset(DomainMOD\ChangeHistory::FIELDS[$field_type][$field_code]) || in_array($field_code, array('dns_servers', 'issuer', 'transfer_lock', 'registry_problem', 'epp_status', 'stage', 'expiry_date', 'registrar', 'statuses', 'moved_domain_id'), true)) {
            $field_labels[$field_code][$field_type] = $field_label;
        }
    }
}
$field_choices = array();
foreach ($field_labels as $field_code => $labels) {
    if (count($field_types) === 1 || count(array_unique($labels)) === 1 && count($labels) > 1) {
        $field_choices[$field_code] = reset($labels);
    } elseif (count($labels) > 1) {
        $field_choices[$field_code] = implode(' / ', array_unique($labels));
    } elseif (isset($labels['domain'])) {
        $field_choices[$field_code] = $labels['domain'];
    } else {
        $field_choices[$field_code] = sprintf('%1$s (%2$s)', reset($labels),
            DomainMOD\ChangeHistoryListColumns::typeName((string) key($labels)));
    }
}
asort($field_choices);
// The chosen type's custom fields too, by their names now (a deleted field's choice is kept with keepOption() below).
// Watched domains have no custom fields.
$custom_field_choices = array();
foreach ($field_types as $field_type) {
    if (!isset($custom_field_tables[$field_type])) continue;
    foreach (DomainMOD\CustomField::readFilterFields($pdo, $custom_field_tables[$field_type]) as $custom_field_row) {
        $custom_label = DomainMOD\FilterToolbar::decode($custom_field_row['name']);
        if (count($field_types) > 1) {
            $custom_label = sprintf('%1$s (%2$s)', $custom_label, DomainMOD\ChangeHistoryListColumns::typeName($field_type));
        }
        // By the field name in lowercase, as the filter compares it (Region and region are one choice)
        $custom_field_choices['cf:' . strtolower($custom_field_row['field_name'])][] = $custom_label;
    }
}
foreach ($custom_field_choices as $field_code => $labels) $field_choices[$field_code] = implode(' / ', $labels);
asort($field_choices);

/*
 * The filter toolbar (DomainMOD\FilterToolbar): an item picked by id is named by its type, id, and life together, so
 * removing either its chip or the type's drops all three. The name search doesn't depend on the type, so it stays when
 * the type's chip is removed.
 */
$web_root = WEB_ROOT;
$filter_toolbar = new DomainMOD\FilterToolbar('history-filters', $web_root . '/history/', $web_root . '/history/');
if ($state['type'] !== '') $filter_toolbar->addFilter('type', $state['type'], '', DomainMOD\ChangeHistoryListColumns::typeName($state['type']), _('Type'));
// An item's newest name in any of its lives (its newest row, a delete row included), or false when it has no rows
$history_item_name = function ($type, $id) use ($pdo) {
    $stmt = $pdo->prepare("
        SELECT item_name
        FROM change_history
        WHERE item_type = :item_type
          AND item_id = :item_id
        ORDER BY id DESC
        LIMIT 1");
    $stmt->bindValue('item_type', $type, PDO::PARAM_STR);
    $stmt->bindValue('item_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $name = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $name;
};
if ($state['id'] !== 0 && $history_available && $state['life'] >= 0) {
    // The item's name as its own life last had it: an earlier life's delete row (life), or the newest row for the id
    $stmt = $pdo->prepare("
        SELECT item_name
        FROM change_history
        WHERE item_type = :item_type
          AND item_id = :item_id" . ($state['life'] > 0 ? "
          AND id <= " . (int) $state['life'] : "
          AND id > COALESCE((SELECT MAX(d.id) FROM change_history AS d WHERE d.item_type = :life_type AND d.item_id = :life_id
                             AND d.change_type = 'delete'), 0)") . "
        ORDER BY id DESC
        LIMIT 1");
    $stmt->bindValue('item_type', $state['type'], PDO::PARAM_STR);
    $stmt->bindValue('item_id', $state['id'], PDO::PARAM_INT);
    if ($state['life'] <= 0) {
        $stmt->bindValue('life_type', $state['type'], PDO::PARAM_STR);
        $stmt->bindValue('life_id', $state['id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $item_name = $stmt->fetchColumn();
    $stmt->closeCursor();
    // An item with no changes in this life (one deleted since, say) takes the name its newest row has, and only an id
    // with no rows at all is shown as its number
    if ($item_name === false || !$filter['life_ok']) $item_name = $history_item_name($state['type'], $state['id']);
    $filter_toolbar->addFilter('id', $state['id'], 0, $item_name === false ? '#' . $state['id']
        : DomainMOD\FilterToolbar::decode($item_name), _('Item'));
    if ($state['life'] > 0) $filter_toolbar->carry('life', $state['life']);
    $filter_toolbar->removeTogether('id', array('type', 'life'));
    $filter_toolbar->removeTogether('type', array('id', 'life'));
}
if ($state['id'] !== 0 && $state['life'] < 0) {
    // A life that names no kept delete row is shown with the item's newest name, where it has rows at all
    $item_name = $history_available ? $history_item_name($state['type'], $state['id']) : false;
    $filter_toolbar->addFilter('id', $state['id'], 0, $item_name === false ? '#' . $state['id']
        : DomainMOD\FilterToolbar::decode($item_name), _('Item'));
    $filter_toolbar->carry('life', $state['life']);
    $filter_toolbar->removeTogether('id', array('type', 'life'));
    $filter_toolbar->removeTogether('type', array('id', 'life'));
}
if ($state['name'] !== '') $filter_toolbar->addFilter('name', $state['name'], '', '"' . $state['name'] . '"', _('Item Name'));
if ($state['source'] !== '') $filter_toolbar->addFilter('source', $state['source'], '', $source_names[$state['source']] ?? $state['source'], _('Source'));
if ($state['user'] === -1) {
    $filter_toolbar->addFilter('user', 'system', '', SOFTWARE_TITLE, _('By'));
} elseif ($state['user'] !== 0) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $stmt->bindValue('id', $state['user'], PDO::PARAM_INT);
    $stmt->execute();
    $user_name = $stmt->fetchColumn();
    $stmt->closeCursor();
    $filter_toolbar->addFilter('user', $state['user'], 0, $user_name === false ? DomainMOD\FilterToolbar::notFound()
        : DomainMOD\FilterToolbar::decode($user_name), _('By'));
}
if ($state['field'] !== '') {
    // The same label as the menu's option
    $filter_toolbar->addFilter('field', $state['field'], '', $field_choices[$state['field']] ?? $state['field'], _('What Changed'));
}
if ($state['op'] !== 0) {
    // The action is shown by where it came from, as the Source column shows it (such as "Bulk Updater: Change DNS
    // Profile"), or by its number where it's no longer kept
    $op_label = '#' . $state['op'];
    if ($history_available) {
        $stmt = $pdo->prepare("
            SELECT co.source, co.action,
                   (SELECT MIN(ch.via) FROM change_history AS ch WHERE ch.operation_id = co.id) AS min_via,
                   (SELECT MAX(ch.via) FROM change_history AS ch WHERE ch.operation_id = co.id) AS max_via
            FROM change_operations AS co
            WHERE co.id = :id");
        $stmt->bindValue('id', $state['op'], PDO::PARAM_INT);
        $stmt->execute();
        $op_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        // Where every row says where its values came from in the same way (such as TLS for an SSL Refresh), so does the chip
        if ($op_row) {
            $op_via = (string) $op_row['min_via'] === (string) $op_row['max_via'] ? (string) $op_row['min_via'] : '';
            $op_label = DomainMOD\ChangeHistoryListColumns::sourceText((string) $op_row['source'], (string) $op_row['action'], $op_via);
        }
    }
    $filter_toolbar->addFilter('op', $state['op'], 0, $op_label, _('Action'));
}
if ($state['range'] === 'custom') {
    $filter_toolbar->addFilter('range', 'custom', DomainMOD\ChangeHistoryList::DEFAULT_RANGE,
        $start_date !== '' ? sprintf(_('%1$s to %2$s'), $start_date, $end_date) : $range_names['custom'], _('Date'));
    if ($start_date !== '') $filter_toolbar->carry('daterange', $start_date . ' - ' . $end_date);
    $filter_toolbar->removeTogether('range', array('daterange'));
} else {
    $filter_toolbar->addFilter('range', $state['range'], DomainMOD\ChangeHistoryList::DEFAULT_RANGE, $range_names[$state['range']], _('Date'));
}
$date_range_fields = array('daterange');

if ($history_available) {

    $total_rows = $history_list->count($filter['sql'], $filter['params']);
    $list_page_sizes = DomainMOD\ChangeHistoryList::pageSizes($_SESSION['s_number_of_domains'] ?? 50);
    $list_length = DomainMOD\ChangeHistoryList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
    $list_start = DomainMOD\ChangeHistoryList::checkedStart($_GET['start'] ?? 0);
    $list_start -= $list_start % $list_length;
    if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;
    $result = $total_rows > 0 ? $history_list->rows($filter['sql'], array('sql' => '', 'params' => array()),
        DomainMOD\ChangeHistoryList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
        $list_length, $filter['params']) : array();
    $list_columns->setOperationCounts($history_list->operationCounts(array_map(function ($row) {
        return $row->operation_id;
    }, $result)));
    $last_restore = $history_list->lastRestore();
    $has_any_changes = $history_list->hasAnyChanges();

} else {

    $total_rows = 0;
    $result = array();
    $last_restore = null;
    $has_any_changes = false;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
if (!$history_available) {

    // A restore of a backup from before the Change History says what happened to the history recorded before it
    try {
        $last_restore = $history_list->lastRestore();
    } catch (Throwable $e) {
        $last_restore = null;
    }
    if ($last_restore !== null) { ?>
        <p class="text-body-secondary small mb-2"><?php echo htmlspecialchars(sprintf(DomainMOD\RestoreRecovery::markerMessage((string) $last_restore['action']), $time->toUserTimezone($last_restore['insert_time'], 'Y-m-d')), ENT_QUOTES, 'UTF-8'); ?></p><?php
    }

    echo $layout->emptyState('bi-clock-history', _('No changes recorded yet.'), sprintf(_('The Change History starts once an administrator runs Upgrade Database for this version of %s.'), SOFTWARE_TITLE));

} else {

    // What the last restore did to the history
    if ($last_restore !== null) { ?>
        <p class="text-body-secondary small mb-2"><?php echo htmlspecialchars(sprintf(DomainMOD\RestoreRecovery::markerMessage((string) $last_restore['action']), $time->toUserTimezone($last_restore['insert_time'], 'Y-m-d')), ENT_QUOTES, 'UTF-8'); ?></p><?php
    }

    // The no-JavaScript page (expand=1) draws the form as a card under the toolbar instead of in the side panel
    $filter_expanded = ($expand === 1);
    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);
    echo $form->showFormTop('');

    // The item picked by id (from an edit page) stays picked while the other filters change
    if ($state['id'] !== 0) {
        echo $form->showInputHidden('id', $state['id']);
        if ($state['life'] !== 0) echo $form->showInputHidden('life', $state['life']);
    }
    // One action stays picked too, and the type the filters had, so a new type can drop what belongs to the old one
    if ($state['op'] !== 0) echo $form->showInputHidden('op', $state['op']);
    echo $form->showInputHidden('type_was', $state['type']);

    echo $form->showDropdownTop('type', _('Type'), '', '', '');
    echo $form->showDropdownOption('', _('Type') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['type']);
    echo $form->showDropdownOption('domain', _('Domain'), $state['type']);
    echo $form->showDropdownOption('ssl', _('SSL Certificate'), $state['type']);
    if ($hosting_available) echo $form->showDropdownOption('hosting', _('Hosting Service'), $state['type']);
    echo $form->showDropdownOption('watch', _('Watched Domain'), $state['type']);
    echo $form->showDropdownBottom('');

    echo $form->showInputText('name', _('Item Name'), '', $state['name'], '200', '', '', '', '');

    echo $form->showDropdownTop('source', _('Source'), '', '', '');
    echo $form->showDropdownOption('', _('Source') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['source']);
    foreach ($source_names as $source_code => $source_name) {
        echo $form->showDropdownOption($source_code, $source_name, $state['source']);
    }
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('user', _('By'), '', '', '');
    $user_choice = $state['user'] === 0 ? '' : ($state['user'] === -1 ? 'system' : $state['user']);
    echo $form->showDropdownOption('', _('By') . ' - ' . DomainMOD\Format::toUpper(_('All')), $user_choice);
    echo $form->showDropdownOption('system', SOFTWARE_TITLE, $user_choice);
    // Usernames are stored HTML-escaped, which is how the menu's options print them (as admin/settings/index.php does)
    $user_rows = $pdo->query("SELECT id, username FROM users ORDER BY username, id")->fetchAll();
    foreach ($user_rows as $user_row) {
        echo $form->showDropdownOption($user_row->id, $user_row->username, $user_choice);
    }
    // A deleted user's changes can still be filtered, so their choice stays when the filters are applied again
    echo $filter_toolbar->keepOption('user', array_merge(array('system'), array_map(function ($user_row) {
        return $user_row->id;
    }, $user_rows)));
    echo $form->showDropdownBottom('');


    echo $form->showDropdownTop('field', _('What Changed'), '', '', '');
    echo $form->showDropdownOption('', _('What Changed') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['field']);
    foreach ($field_choices as $field_code => $field_label) {
        echo $form->showDropdownOption($field_code, htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8'), $state['field']);
    }
    echo $filter_toolbar->keepOption('field', array_keys($field_choices));
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('range', _('Date'), '', '', '');
    foreach ($range_names as $range_code => $range_name) {
        echo $form->showDropdownOption($range_code, $range_name, $state['range']);
    }
    echo $form->showDropdownBottom('');
    $custom_dates = $state['range'] === 'custom' && $start_date !== '' ? $start_date . ' - ' . $end_date : '';
    echo $form->showInputText('daterange', _('Custom Range'), '', $custom_dates, '23', '', '', '', '');
    echo $form->showInputHidden('daterange_was', $custom_dates); ?>

    <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
        <?php echo $form->showSubmitButton(_('Apply filters'), '', ''); ?>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($web_root . '/history/', ENT_QUOTES, 'UTF-8'); ?>"><?php echo _('Clear filters'); ?></a>
    </div><?php

    echo $form->showFormBottom('');
    echo $filter_toolbar->panelBottom($filter_expanded);

    if (!$filter['life_ok']) { ?>
        <p><?php echo _('These changes are no longer kept.'); ?></p><?php
    }

    if ($total_rows > 0) {

        // The range preset is the range key (a custom range's dates are carried with it), so the dates aren't blanked
        $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl('name', 'range'), 'bi-download', array('id' => 'history-list-export'));

        // Server-side processing (see js/server-list.js), as on the Domains list
        $list_state_url = $filter_toolbar->stateUrl('name', 'range');
        $list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);
        $list_data_url = $web_root . '/history/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
            . http_build_query(array('layout' => $list_columns->layoutKey()));
        $list_config = array(
            'url' => $list_data_url,
            'columns' => array_map(function ($column) use ($list_columns) {
                return array('orderable' => (bool) $column['sort'], 'control' => false,
                    'className' => $list_columns->columnClass($column));
            }, $list_columns->columns()),
            'cellTitles' => true,
            'responsive' => false,
            'order' => $list_columns->defaultOrder(),
            'start' => $list_start,
            'length' => $list_length,
            'sizes' => $list_page_sizes['sizes'],
            'total' => $total_rows,
            'messages' => array(
                'error' => _("That didn't work. Please reload the page and try again."),
                'signin' => _('Your session has ended. Please sign in again.'),
                'signinLink' => _('Sign In'),
                'reload' => _('Your display settings have changed. Reload the page to see them.'),
                'reloadLink' => _('Reload'),
                'close' => _('Close'),
                'paging' => sprintf(_('Pages of %s'), _('Changes')),
                'scroll' => _('Change History'),
            ),
            'signinUrl' => $web_root . '/',
            'reloadUrl' => $list_state_url,
            'searchLinks' => array(
                'history-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        ); ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<?php echo htmlspecialchars(_('Change History'), ENT_QUOTES, 'UTF-8'); ?>">
        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>" data-domainmod-list="<?php echo htmlspecialchars(json_encode($list_config), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
            <tr>
                <?php foreach ($list_columns->headerCells() as $header_cell) {
                    echo $header_cell;
                } ?>
            </tr>
            </thead>
            <tbody>
            <?php
            $list_cell_classes = array_map(array($list_columns, 'columnClass'), $list_columns->columns());
            foreach ($result as $row) { ?>
                <tr>
                    <?php foreach ($list_columns->cells($row, '') as $index => $cell) { ?>
                        <td class="<?php echo $list_cell_classes[$index]; ?>"><?php echo $cell; ?></td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('Changes'))); ?>
        <BR><?php

    } elseif ($filter['life_ok']) {

        if (!$has_any_changes) {
            echo $layout->emptyState('bi-clock-history', _('No changes recorded yet.'), ($hosting_available ? _('Changes to your domains, hosting services, and SSL certificates will be listed here.') : _('Changes to your domains and SSL certificates will be listed here.')));
        } else {
            echo $layout->emptyState('bi-search', _('No changes match these filters.'), _('Try changing or clearing the filters.'), '', '', '/history/', _('Clear filters'));
        }

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/server-list.js'); ?>"></script>
</body>
</html>
