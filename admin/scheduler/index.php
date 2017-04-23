<?php
/**
 * /admin/scheduler/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$schedule = new DomainMOD\Scheduler();
$form = new DomainMOD\Form();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'config-demo.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/admin-scheduler-main.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$sql = "SELECT id, `name`, description, `interval`, expression, last_run, last_duration, next_run, active
        FROM scheduler
        ORDER BY sort_order ASC";
$result = mysqli_query($dbcon, $sql) or $error->outputOldSqlError($dbcon);
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . 'layout/header.inc.php'); ?>
The Task Scheduler allows you to run various system jobs at specified times, which helps keep your <?php
echo $software_title; ?> installation up-to-date and running smoothly, as well as notifies you of important information,
such as emailing you to let you know about upcoming Domain & SSL Certificate expirations. In order to use the Task
Scheduler you must setup a cron/scheduled job on your web server to execute the file <strong>cron.php</strong>, which is
located in the root folder of your <?php echo $software_title; ?> installation.
This file should be executed <em>every 10 minutes</em>, and once it's setup the Task Scheduler will be live.<BR>
<BR>
Using the Task Scheduler is optional, but <em>highly</em> recommended.<BR>
<BR>
Current Timestamp: <strong><?php echo $time->toUserTimezone($time->stamp()); ?></strong><BR>
<BR>
<table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">

    <thead>
        <tr>
            <th></th>
            <th></th>
        </tr>
    </thead>
    <tbody><?php

    $row = mysqli_fetch_object($schedule->getTask($dbcon, $row->id));
    $hour = explode(" ", $row->expression);

    while ($row = mysqli_fetch_object($result)) { ?>

        <tr>
        <td style="padding: 7px 5px 0px 10px;">
            <h4><?php echo $row->name; ?></h4><?php echo $row->description ?><BR><BR><BR>
        </td>
        <td style="padding: 15px 0px 18px 0px;">
            <strong>Runs:</strong> <?php echo $row->interval; ?><BR>

            <strong>Status:</strong> <?php echo $schedule->createActive($row->active, $row->id); ?><BR><?php

            if ($row->last_run != '0000-00-00 00:00:00') {
                $last_run = $time->toUserTimezone($schedule->getDateOutput($row->last_run));
            } else {
                $last_run = '-';

            } ?>

            <strong>Last Run:</strong> <?php echo $last_run; ?><?php echo $row->last_duration; ?><BR><?php

            if ($row->next_run != '0000-00-00 00:00:00') {
                $next_run = $time->toUserTimezone($schedule->getDateOutput($row->next_run));
                $hour = date('H', strtotime($next_run));
            } else {
                $next_run = '-';

            } ?>

            <strong>Next Run:</strong> <?php echo $next_run; ?><BR><?php

            if ($row->interval == 'Daily' && $row->active == '1') { ?>

                <BR>
                <form name="edit_task_form" method="post" action="update.php">
                    <select name="new_hour">
                        <?php echo $schedule->hourSelect($hour); ?>
                    </select><BR><BR><?php
                    echo $form->showInputHidden('a', 'u');
                    echo $form->showInputHidden('id', $row->id);
                    echo $form->showSubmitButton('Change Time', '', ''); ?>
                </form><?php

            } ?>
        </td>

        </tr><?php

    } ?>

    </tbody>
</table>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
