<?php
/**
 * /admin/scheduler/run.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require DIR_ROOT . 'vendor/autoload.php';

$conversion = new DomainMOD\Conversion();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$schedule = new DomainMOD\Scheduler();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['is_admin'], $web_root);

$id = $_GET['id'];

$sql = "SELECT id, slug, expression, next_run, active
        FROM scheduler
        WHERE id = '" . $id . "'";
$result = mysqli_query($connection, $sql);

while ($row = mysqli_fetch_object($result)) {

    if ($row->active == '1') {

        $cron = \Cron\CronExpression::factory($row->expression);
        $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    } else {

        $next_run = '0000-00-00 00:00:00';

    }

    if ($row->slug == 'cleanup') {

        $schedule->isRunning($connection, $row->id);
        $maint->performMaintenance($connection);
        $schedule->updateTime($connection, $row->id, $timestamp, $next_run, $row->active);
        $schedule->isFinished($connection, $row->id);

        $_SESSION['result_message'] .= "System Cleanup Performed";

    } elseif ($row->slug == 'update-conversion-rates') {

        $schedule->isRunning($connection, $row->id);
        $sql_currency = "SELECT user_id, default_currency
                         FROM user_settings";
        $result_currency = mysqli_query($connection, $sql_currency);

        while ($row_currency = mysqli_fetch_object($result_currency)) {
            $conversion->updateRates($connection, $row_currency->default_currency, $row_currency->user_id);
        }
        $schedule->updateTime($connection, $row->id, $timestamp, $next_run, $row->active);
        $schedule->isFinished($connection, $row->id);

        $_SESSION['result_message'] .= "Conversion Rates Updated";

    } elseif ($row->slug == 'check-new-version') {

        $schedule->isRunning($connection, $row->id);
        $system->checkVersion($connection, $software_version);
        $schedule->updateTime($connection, $row->id, $timestamp, $next_run, $row->active);
        $schedule->isFinished($connection, $row->id);

        $_SESSION['result_message'] .= $system->getUpgradeMessage();

    }

}

header("Location: index.php");
exit;
