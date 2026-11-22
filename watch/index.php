<?php
/**
 * /watch/index.php
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
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/watch-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

/*
 * The Watch List (see DomainMOD\WatchList): domains you don't own yet, checked in the background. Every signed-in user
 * sees the same list, read-only users included, and only users who can change data get the tick boxes and the bulk
 * actions, which are checked again on the server.
 */
$watch_available = DomainMOD\WatchList::isAvailable();
// Turned off in System Settings (see WatchList::isEnabled()), the page only says so, and nothing is read or changed
$watch_off = $watch_available && !DomainMOD\WatchList::isEnabled();
if ($watch_off) $watch_available = false;
$watch_list = new DomainMOD\WatchList($pdo, (int) $_SESSION['s_user_id']);
$esc = function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
};
$filter_keys = array('stage', 'reason', 'tld', 'drop', 'added', 'archived');
// The list's address with its filters, where a bulk action returns
$return_query = array();
foreach ($filter_keys as $key) {
    if (is_string($_GET[$key] ?? null) && $_GET[$key] !== '') $return_query[$key] = $_GET[$key];
}
$return_url = WEB_ROOT . '/watch/' . ($return_query ? '?' . http_build_query($return_query) : '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $watch_available && isset($_POST['bulk_action'])) {

    $system->readOnlyCheck($return_url);
    $bulk_action = is_string($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
    $wids = array_map('intval', is_array($_POST['wids'] ?? null) ? $_POST['wids'] : array());

    if (DEMO_INSTALLATION == true) {

        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';

    } elseif (!$wids) {

        $_SESSION['s_message_danger'] .= _('Select the domains first.') . '<BR>';

    } elseif ($bulk_action === 'delete' && ($_POST['confirm_delete'] ?? '') !== '1') {

        // Delete asks first: the page is drawn again with a confirmation for the selected entries
        $confirm_delete = $wids;

    } else {

        try {

            if ($bulk_action === 'archive') {
                $count = $watch_list->archive($wids, (int) $_SESSION['s_user_id'], 'watch_bulk_archive');
                $_SESSION['s_message_success'] .= $esc(sprintf(ngettext('%s domain archived.', '%s domains archived.', $count), number_format($count))) . '<BR>';
            } elseif ($bulk_action === 'unarchive') {
                $result = $watch_list->unarchive($wids, (int) $_SESSION['s_user_id'], 'watch_bulk_unarchive');
                $_SESSION['s_message_' . ($result['ok'] ? 'success' : 'danger')] .= $esc(DomainMOD\WatchList::resultMessage($result)) . '<BR>';
            } elseif ($bulk_action === 'delete') {
                $count = $watch_list->delete($wids, (int) $_SESSION['s_user_id'], 'watch_bulk_delete');
                $_SESSION['s_message_success'] .= $esc(sprintf(ngettext('%s domain deleted.', '%s domains deleted.', $count), number_format($count))) . '<BR>';
            } elseif ($bulk_action === 'reason' && in_array($_POST['new_reason'] ?? '', DomainMOD\WatchList::REASONS, true)) {
                $count = $watch_list->setReason($wids, $_POST['new_reason'], (int) $_SESSION['s_user_id']);
                $_SESSION['s_message_success'] .= $esc(sprintf(ngettext('Reason changed for %s domain.', 'Reason changed for %s domains.', $count), number_format($count))) . '<BR>';
            } else {
                $_SESSION['s_message_danger'] .= _('Choose an action.') . '<BR>';
            }

        } catch (DomainMOD\RestoreLockException $e) {

            $_SESSION['s_message_danger'] .= $esc($e->getMessage()) . '<BR>';

        } catch (Throwable $e) {

            // Another request changed the same rows at the same moment: the action was rolled back as a whole (see
            // domainmod_restore_is_conflict())
            if (domainmod_restore_is_conflict($e)) {
                $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            } else {
                (new DomainMOD\Log('/watch/index.php'))->critical('Unable to change the Watch List', array('Error' => $e->getMessage()));
                $_SESSION['s_message_danger'] .= _("That didn't work. Please try again.") . '<BR>';
            }

        }

    }

    if (!isset($confirm_delete)) {
        header('Location: ' . $return_url);
        exit;
    }

} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The filter panel posts its form here, and the page moves to the address of the filters it chose
    $posted = DomainMOD\WatchList::normalizeState($_POST);
    $query = array();
    foreach ($filter_keys as $key) {
        if ($posted[$key] !== '' && !($key === 'archived' && $posted[$key] === 'hide')) $query[$key] = $posted[$key];
    }
    header('Location: ' . WEB_ROOT . '/watch/' . ($query ? '?' . http_build_query($query) : ''));
    exit;

}

$state = DomainMOD\WatchList::normalizeState($_GET);
$expand = (int) ($_GET['expand'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);
$filter = $watch_available ? $watch_list->filterParts($state) : array('sql' => ' AND 1 = 0', 'params' => array());
$list_columns = new DomainMOD\WatchListColumns(DomainMOD\WatchListColumns::sessionCopy($_SESSION));

if ($export_data === 1 && $watch_available) {

    $back_url = 'index.php?' . http_build_query(array_diff_key($_GET, array_flip(array('export_data', 'dt_search', 'dt_layout'))));

    // A streamed export (see Export::begin()), read and written in chunks, as the Domains export is
    $export = new DomainMOD\Export();
    $export->begin($back_url);

    $table_search = $watch_list->tableSearchSql($_GET['dt_search'] ?? '', $_GET['dt_layout'] ?? '',
        DomainMOD\WatchListColumns::sessionCopy($_SESSION));
    if ($table_search['stale']) $table_search = array('sql' => '', 'params' => array());

    try {

        $export->startSnapshot($watch_list);
        $total_rows = $watch_list->count($filter['sql'] . $table_search['sql'], $table_search['params'] + $filter['params']);
        $export_file = $export->openStream(_('watch_list'), strtotime($time->stamp()));

        $export->writeRow($export_file, array(_('Watch List Export')));
        $export->writeBlankRow($export_file);
        $export->writeRow($export_file, DomainMOD\WatchListColumns::exportHeadings());
        $export->chunkWritten();

        foreach ($watch_list->exportChunks($filter['sql'] . $table_search['sql'], $table_search['params'] + $filter['params']) as $chunk) {
            foreach ($chunk as $row) {
                $export->writeDataRow($export_file, $list_columns->exportCells($export, $row));
            }
            $export->chunkWritten();
        }

        $export->finish($total_rows);

    } catch (Throwable $e) {

        $export->fail($e);

    }

}

$stage_names = DomainMOD\WatchLifecycle::stageLabels();
// The unknown stage covers every entry without an answer kept yet, whatever its badge says
$stage_names['unknown'] = _('Not checked yet, checking, or unable to check');
$reason_names = DomainMOD\WatchList::reasonLabels();
$drop_names = array('7' => _('Next 7 days'), '30' => _('Next 30 days'), 'any' => _('Any estimate'));
$archived_names = array('hide' => _('Hide archived'), 'show' => _('Show archived'), 'only' => _('Only archived'));

$web_root = WEB_ROOT;
$filter_toolbar = new DomainMOD\FilterToolbar('watch-filters', $web_root . '/watch/', $web_root . '/watch/');
if ($state['stage'] !== '') $filter_toolbar->addFilter('stage', $state['stage'], '', $stage_names[$state['stage']], _('Stage'));
if ($state['reason'] !== '') $filter_toolbar->addFilter('reason', $state['reason'], '', $reason_names[$state['reason']], _('Reason'));
if ($state['tld'] !== '') $filter_toolbar->addFilter('tld', $state['tld'], '', '.' . $state['tld'], _('TLD'));
if ($state['drop'] !== '') $filter_toolbar->addFilter('drop', $state['drop'], '', $drop_names[$state['drop']], _('Estimated Drop'));
if ($state['added'] !== '') $filter_toolbar->addFilter('added', 'me', '', _('Me'), _('Added By'));
if ($state['archived'] !== 'hide') $filter_toolbar->addFilter('archived', $state['archived'], 'hide', $archived_names[$state['archived']], _('Archived'));

if ($watch_available) {

    $total_rows = $watch_list->count($filter['sql'], $filter['params']);
    $list_page_sizes = DomainMOD\WatchList::pageSizes($_SESSION['s_number_of_domains'] ?? 50);
    $list_length = DomainMOD\WatchList::checkedLength($_GET['length'] ?? '', $list_page_sizes);
    $list_start = DomainMOD\WatchList::checkedStart($_GET['start'] ?? 0);
    $list_start -= $list_start % $list_length;
    if ($list_start >= $total_rows) $list_start = $total_rows > 0 ? (int) (floor(($total_rows - 1) / $list_length) * $list_length) : 0;
    $watch_check = new DomainMOD\WatchCheck($pdo);
    $list_columns->setFastSlots(DomainMOD\WatchCheck::fastSlots($watch_check->activeRows(), $watch_check->movedCandidates(), time()), time());
    $result = $total_rows > 0 ? $watch_list->rows($filter['sql'], array('sql' => '', 'params' => array()),
        DomainMOD\WatchList::orderSql(array(), $list_columns->sortMap(), $list_columns->defaultOrder()), $list_start,
        $list_length, $filter['params']) : array();
    $has_entries = $watch_list->hasEntries();
    $tld_choices = $watch_list->tlds();
    if (isset($confirm_delete)) {
        $stmt = $pdo->query("SELECT id, domain FROM watch_domains WHERE id IN (" . implode(',', array_map('intval', $confirm_delete)) . ") ORDER BY domain");
        $confirm_rows = $stmt->fetchAll();
        $stmt->closeCursor();
    }

} else {

    $total_rows = 0;
    $result = array();
    $has_entries = false;
    $tld_choices = array();

}
$read_only = ($_SESSION['s_read_only'] ?? '') == '1';
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php
if ($watch_available) {
    // The empty list's own Add button stands in for the header's
    if ($has_entries) $layout->setPageAddAction(_('Add Domains'), $web_root . '/watch/add.php');
    $layout->addPageAction(_('Changes'), $web_root . '/history/?type=watch', 'bi-clock-history');
    if ($total_rows > 0) {
        $layout->addPageAction(_('Export'), $filter_toolbar->exportUrl(), 'bi-download', array('id' => 'watch-list-export'));
    }
}
require_once DIR_INC . '/layout/header.inc.php';

if ($watch_off) {

    echo DomainMOD\WatchList::offState($layout, ($_SESSION['s_is_admin'] ?? 0) === 1);

} elseif (!$watch_available) {

    echo '<p>' . _('The Watch List becomes available after the next database update.') . '</p>';

} elseif (!$has_entries) {

    echo $layout->emptyState('bi-binoculars', _('No domains on your Watch List yet'), sprintf(_("Add domains you don't own yet, and %s checks them in the background with public RDAP and WHOIS data, so you can see when one is close to dropping."), SOFTWARE_TITLE), '/watch/add.php', _('Add Domains'));

} else {

    if (DEMO_INSTALLATION == true) { ?>
        <p class="text-body-secondary small"><?php echo _('The Watch List can\'t be changed on the demo.'); ?></p><?php
    }

    if (isset($confirm_rows) && $confirm_rows) { ?>
        <div class="alert alert-danger" role="alert">
            <p class="mb-2"><strong><?php echo $esc(sprintf(ngettext('Delete %s watched domain?', 'Delete %s watched domains?', count($confirm_rows)), number_format(count($confirm_rows)))); ?></strong>
                <?php echo $esc(ngettext('Its earlier changes stay in the Change History.', 'Their earlier changes stay in the Change History.', count($confirm_rows))); ?></p>
            <ul class="mb-2"><?php
            foreach (array_slice($confirm_rows, 0, 20) as $confirm_row) {
                echo '<li>' . $layout->ltr((string) (new DomainMOD\Unsanitize())->text($confirm_row->domain)) . '</li>';
            }
            if (count($confirm_rows) > 20) echo '<li>' . $esc(sprintf(ngettext('Plus %s other', 'Plus %s others', count($confirm_rows) - 20), number_format(count($confirm_rows) - 20))) . '</li>'; ?>
            </ul>
            <?php echo $form->showFormTop('');
            foreach ($confirm_rows as $confirm_row) echo $form->showInputHidden('wids[]', (int) $confirm_row->id);
            echo $form->showInputHidden('bulk_action', 'delete');
            echo $form->showInputHidden('confirm_delete', '1'); ?>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-danger"><?php echo _('Yes, Delete'); ?></button>
                <a class="btn btn-outline-secondary" href="<?php echo $esc($return_url); ?>"><?php echo _('Cancel'); ?></a>
            </div>
            <?php echo $form->showFormBottom(''); ?>
        </div><?php
    }

    $filter_expanded = ($expand === 1);
    echo $filter_toolbar->toolbar($filter_expanded);
    echo $filter_toolbar->panelTop($filter_expanded);
    echo $form->showFormTop('');

    echo $form->showDropdownTop('stage', _('Stage'), '', '', '');
    echo $form->showDropdownOption('', _('Stage') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['stage']);
    foreach ($stage_names as $stage_code => $stage_name) echo $form->showDropdownOption($stage_code, $esc($stage_name), $state['stage']);
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('reason', _('Reason'), '', '', '');
    echo $form->showDropdownOption('', _('Reason') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['reason']);
    foreach ($reason_names as $reason_code => $reason_name) echo $form->showDropdownOption($reason_code, $esc($reason_name), $state['reason']);
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('tld', _('TLD'), '', '', '');
    echo $form->showDropdownOption('', _('TLD') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['tld']);
    foreach ($tld_choices as $tld_choice) echo $form->showDropdownOption($esc($tld_choice), $esc('.' . $tld_choice), $state['tld']);
    echo $filter_toolbar->keepOption('tld', $tld_choices);
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('drop', _('Estimated Drop'), '', '', '');
    echo $form->showDropdownOption('', _('Estimated Drop') . ' - ' . DomainMOD\Format::toUpper(_('All')), $state['drop']);
    foreach ($drop_names as $drop_code => $drop_name) echo $form->showDropdownOption($drop_code, $esc($drop_name), $state['drop']);
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('added', _('Added By'), '', '', '');
    echo $form->showDropdownOption('', _('Anyone'), $state['added']);
    echo $form->showDropdownOption('me', _('Me'), $state['added']);
    echo $form->showDropdownBottom('');

    echo $form->showDropdownTop('archived', _('Archived'), '', '', '');
    foreach ($archived_names as $archived_code => $archived_name) echo $form->showDropdownOption($archived_code, $esc($archived_name), $state['archived']);
    echo $form->showDropdownBottom(''); ?>

    <div class="d-flex flex-wrap gap-2 domainmod-filter-actions">
        <?php echo $form->showSubmitButton(_('Apply filters'), '', ''); ?>
        <a class="btn btn-outline-secondary" href="<?php echo $esc($web_root . '/watch/'); ?>"><?php echo _('Clear filters'); ?></a>
    </div><?php

    echo $form->showFormBottom('');
    echo $filter_toolbar->panelBottom($filter_expanded);

    if ($total_rows > 0) {

        // The bulk actions: the rows' tick boxes belong to this form (their form attribute), wherever the table puts them
        if (!$read_only && DEMO_INSTALLATION == false) { ?>
            <form method="post" id="watch-bulk-form" class="d-flex flex-wrap align-items-end gap-2 mb-2" action="<?php echo $esc($return_url); ?>">
                <?php echo $form->showCsrfField(); ?>
                <div>
                    <label class="form-label small mb-1" for="watch-bulk-action"><?php echo _('With the selected domains'); ?></label>
                    <select class="form-select form-select-sm" id="watch-bulk-action" name="bulk_action">
                        <option value="archive"><?php echo _('Archive'); ?></option>
                        <option value="unarchive"><?php echo _('Unarchive'); ?></option>
                        <option value="reason"><?php echo _('Change the reason'); ?></option>
                        <option value="delete"><?php echo _('Delete'); ?></option>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1" for="watch-bulk-reason"><?php echo _('New reason'); ?></label>
                    <select class="form-select form-select-sm" id="watch-bulk-reason" name="new_reason"><?php
                    foreach ($reason_names as $reason_code => $reason_name) {
                        echo '<option value="' . $esc($reason_code) . '">' . $esc($reason_name) . '</option>';
                    } ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo _('Apply'); ?></button>
            </form><?php
        }

        $list_state_url = $filter_toolbar->stateUrl();
        $list_query = (string) parse_url($list_state_url, PHP_URL_QUERY);
        $list_data_url = $web_root . '/watch/list-data.php?' . ($list_query !== '' ? $list_query . '&' : '')
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
                'paging' => sprintf(_('Pages of %s'), _('Watched Domains')),
                'scroll' => _('Watch List'),
            ),
            'signinUrl' => $web_root . '/',
            'reloadUrl' => $list_state_url,
            'searchLinks' => array(
                'watch-list-export' => array('dt_layout' => $list_columns->layoutKey()),
            ),
        ); ?>

        <div id="<?php echo $slug; ?>-message"></div>
        <div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<?php echo $esc(_('Watch List')); ?>">
        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>" data-domainmod-list="<?php echo $esc(json_encode($list_config)); ?>">
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
        <?php echo $layout->listPager($total_rows, $list_start, $list_length, $list_state_url, sprintf(_('Pages of %s'), _('Watched Domains'))); ?>
        <BR><?php

    } else {

        echo $layout->emptyState('bi-search', _('No watched domains match these filters.'), _('Try changing or clearing the filters.'), '', '', '/watch/', _('Clear filters'));

    }

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<script src="<?php echo $layout->assetUrl('js/filter-auto-apply.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/server-list.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/watch-list.js'); ?>"></script>
</body>
</html>
