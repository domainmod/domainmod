<?php
/**
 * /admin/scheduler/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
$layout = new DomainMOD\Layout();
$schedule = new DomainMOD\Scheduler();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-scheduler-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_('The Task Scheduler allows you to run various system jobs at specified times, which helps keep your %s installation up-to-date and running smoothly, as well as notifies you of important information, such as emailing you to let you know about upcoming Domain & SSL Certificate expirations.'), SOFTWARE_TITLE); ?>&nbsp;
<?php echo sprintf(_('In order to use the Task Scheduler you must setup a cron/scheduled job on your web server to execute the file %scron.php%s, which is located in the root folder of your %s installation.'), '<strong>', '</strong>', SOFTWARE_TITLE); ?>&nbsp;
<?php echo sprintf(_("This file should be executed %severy 10 minutes%s, and once it's setup the Task Scheduler will be live."), '<em>', '</em>'); ?><BR>
<BR>
<?php echo sprintf(_('Using the Task Scheduler is optional, but %shighly%s recommended.'), '<em>', '</em>'); ?><BR>
<BR>
<?php echo _('Current Timestamp'); ?>: <strong><?php echo $time->toUserTimezone($time->stamp()); ?></strong><BR>
<BR>
<?php
$result = $pdo->query("
        SELECT id, `name`, description, `interval`, expression, last_run, last_duration, next_run, active
        FROM scheduler
        ORDER BY sort_order ASC")->fetchAll();


$count = 1;

foreach ($result as $box) {

    if ($count == 1 || $count == 4) { ?>

        <div class="row"> <?php

    } ?>

    <?php echo $layout->contentBoxTop($box->name, '4'); ?>
    <div>
        <?php echo $box->description ?>
    </div>
    <div>
        <BR><strong><?php echo _('Runs'); ?>:</strong> <?php echo $box->interval; ?><BR>

        <strong><?php echo _('Status'); ?>:</strong> <?php echo $schedule->createActive($box->active, $box->id); ?><BR><?php

        if ($box->last_run != '1970-01-01 00:00:00') {
            $last_run = $time->toUserTimezone($schedule->getDateOutput($box->last_run));
        } else {
            $last_run = '-';

        } ?>

        <strong><?php echo _('Last Run'); ?>:</strong> <?php echo $last_run; ?><?php echo $box->last_duration; ?><BR><?php

        if ($box->next_run != '1970-01-01 00:00:00') {
            $next_run = $time->toUserTimezone($schedule->getDateOutput($box->next_run));
            $hour = date('H', strtotime($next_run));
        } else {
            $next_run = '-';

        } ?>

        <strong><?php echo _('Next Run'); ?>:</strong> <?php echo $next_run; ?><BR><?php

        if ($box->interval == 'Daily' && $box->active == '1') { ?>

            <BR>
            <form name="edit_task_form" method="post" action="update.php">
                <?php echo _('Set Run Time'); ?>: <select name="new_hour" title="Task Time">
                    <?php echo $schedule->hourSelect($hour); ?>
                </select><BR><BR><?php
                echo $form->showInputHidden('a', 'u');
                echo $form->showInputHidden('id', $box->id);
                echo $form->showSubmitButton(_('Change Time'), '', ''); ?>
            </form><?php
        } ?>

    </div><?php
    echo $layout->contentBoxBottom();

    if ($count == 3 || $count == 6) { ?>

            </div><?php // end the row div
    }

    $count++;

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
