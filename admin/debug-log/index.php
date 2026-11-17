<?php
/**
 * /admin/debug-log/index.php
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
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/debug-log/index.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-debug-log-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, user_id, area, `level`, message, extra, url, insert_time
    FROM log
    ORDER BY insert_time DESC, id DESC")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('debug_log'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('ID'),
        _('User ID'),
        _('Area'),
        _('Level'),
        _('Message'),
        _('Extra'),
        _('URL'),
        _('Inserted')
    );
    $export->writeRow($export_file, $row_contents);

    if (!$result) {

        $log_message = 'Unable to retrieve debugging data';
        $log->critical($log_message);

    } else {

        foreach ($result as $row) {

            // Log text can come from anyone who reached a page, so it can't start a spreadsheet formula.
            $row_contents = array(
                $row->id,
                $row->user_id,
                $export->protectFormula($row->area),
                $export->protectFormula($row->level),
                $export->protectFormula($row->message),
                $export->protectFormula($row->extra),
                $export->protectFormula($row->url),
                $time->toUserTimezone($row->insert_time)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php
require_once DIR_INC . '/layout/header.inc.php';

if (!$result) {

    if (DEBUG_MODE == '1') {

        $empty_text = _('New entries appear here while debugging is on.');

    } else {

        $empty_text = sprintf(_('Debugging can be enabled in %sSettings%s.'), '<a href="' . WEB_ROOT . '/admin/settings/">', '</a>');

    }

    echo $layout->emptyState('bi-journal-text', _('No debug log entries'), $empty_text);

} else {

    // The page's actions, drawn in the page header (see Layout::renderPageActions()). Clearing can't be undone, so it
    // asks first, and afterwards comes back to this page rather than going to Maintenance.
    $layout->addPageAction(_('Clear Debug Log'), '../maintenance/clear-log.php?' . (new DomainMOD\Csrf())->query() . '&return=debug-log', 'bi-trash',
        array('confirm' => _("Clear the debug log? This can't be undone.")));
    $layout->addPageAction(_('Export'), 'index.php?export_data=1', 'bi-download'); ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th class="all"><?php echo _('ID'); ?></th>
            <th class="none"><?php echo _('User ID'); ?></th>
            <th><?php echo _('Area'); ?></th>
            <th class="all"><?php echo _('Level'); ?></th>
            <th class="all"><?php echo _('Message'); ?></th>
            <th><?php echo _('Extra'); ?></th>
            <th class="none"><?php echo _('URL'); ?></th>
            <th><?php echo _('Insert Time'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        // Log entries can hold text from anyone who reached a page (such as a sign-in's username or the URL), so it's
        // escaped before it's shown.
        foreach ($result as $row) {

            $level = htmlspecialchars((string) $row->level, ENT_QUOTES, 'UTF-8');
            // The level sorts by its stored word, and searches by that word and the name shown
            $level_search = htmlspecialchars((string) $row->level . ' ' . DomainMOD\Log::levelInfo($row->level)[0], ENT_QUOTES, 'UTF-8');
            $extra_pairs = DomainMOD\Log::extraPairs($row->extra); ?>

            <tr>
            <td></td>
            <td>
                <?php echo (int) $row->id; ?>
            </td>
            <td>
                <?php echo (int) $row->user_id; ?>
            </td>
            <td>
                <div class="text-wrap text-break"><?php echo htmlspecialchars((string) $row->area, ENT_QUOTES, 'UTF-8'); ?></div>
            </td>
            <td data-order="<?php echo $level; ?>" data-search="<?php echo $level_search; ?>">
                <?php echo DomainMOD\Log::levelBadge($row->level); ?>
            </td>
            <td>
                <?php echo htmlspecialchars((string) $row->message, ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td><?php
                // Each detail as a label and its value, or the stored text when it can't be read that way. Like the area
                // and URL, it wraps even with no spaces, including when a phone shows it under the row.
                echo '<div class="text-wrap text-break">';
                if ($extra_pairs !== null) {
                    echo $layout->factList(DomainMOD\Log::extraFacts($extra_pairs), true);
                } else {
                    echo htmlspecialchars((string) $row->extra, ENT_QUOTES, 'UTF-8');
                }
                echo '</div>'; ?>
            </td>
            <td>
                <div class="text-wrap text-break"><?php echo htmlspecialchars((string) $row->url, ENT_QUOTES, 'UTF-8'); ?></div>
            </td>
            <td>
                <?php echo $time->toUserTimezone($row->insert_time); ?>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
