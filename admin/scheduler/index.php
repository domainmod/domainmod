<?php
/**
 * /admin/scheduler/index.php
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
$layout = new DomainMOD\Layout();
$schedule = new DomainMOD\Scheduler();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-scheduler-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo $schedule->healthAlert('scheduler'); ?>
<div class="domainmod-intro">
<p><?php echo sprintf(_('The Task Scheduler allows you to run various system jobs at specified times, which helps keep your %s installation up-to-date and running smoothly, as well as notifies you of important information, such as emailing you to let you know about upcoming Domain & SSL Certificate expirations.'), SOFTWARE_TITLE); ?></p>
<p><?php echo sprintf(_('In order to use the Task Scheduler you must set up a cron/scheduled job on your web server to execute the file %scron.php%s, which is located in the root folder of your %s installation.'), '<strong>', '</strong>', SOFTWARE_TITLE); ?>
<?php
$emphasized_text = _("every 10 minutes");
$full_text = sprintf(_("This file should be executed %s, and once it's set up the Task Scheduler will be live."), $emphasized_text);
echo ' ' . str_replace($emphasized_text, "<em>$emphasized_text</em>", $full_text);
?></p>
<p><?php echo sprintf(_('Using the Task Scheduler is optional, but %shighly%s recommended.'), '<em>', '</em>'); ?>
<?php echo ' ' . sprintf(_('To stop anyone else from running cron.php, you can give it a key in config.inc.php. See the %sUser Guide%s for details.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">', '</a>'); ?></p>
</div>
<?php
// The time now, and cron.php's last run, which is left out when the time can't be read (such as while the database
// upgrade is waiting), rather than showing "Never"
$facts = array(array('label' => _('Current Timestamp'), 'value' => $time->toUserTimezone($time->stamp()), 'ltr' => true));
$cron_last_run = $schedule->getCronLastRun();
if ($cron_last_run !== null) {
    $facts[] = array('label' => _('Last run of cron.php'), 'value' => $time->toUserTimezone($cron_last_run, 'Y-m-d H:i', _('Never')), 'ltr' => !DomainMOD\Time::isUnset($cron_last_run));
}
echo $layout->factList($facts);

$result = $pdo->query("
        SELECT id, `name`, description, `interval`, expression, `timezone`, last_run, last_duration, next_run, active, slug
        FROM scheduler
        ORDER BY sort_order ASC")->fetchAll(); ?>
<div class="row g-3"><?php
    foreach ($result as $task) {
        echo $schedule->taskCard($task);
    } ?>
</div>
<p class="text-body-secondary small mt-3 mb-0"><?php
// TRANSLATORS: The two %s wrap the link to the Task Scheduler section of the User Guide and must stay.
echo sprintf(_('See %sTask Scheduler%s in the User Guide for what each task does.'), '<a target="_blank" href="https://domainmod.org/docs/userguide/administration/#task-scheduler">', '</a>'); ?></p>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
