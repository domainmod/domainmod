<?php
/**
 * /admin/scheduler/run.php
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

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require_once(DIR_ROOT . '/vendor/autoload.php');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$conversion = new DomainMOD\Conversion();
$schedule = new DomainMOD\Scheduler();
$time = new DomainMOD\Time();
$log = new DomainMOD\Log('scheduler.run');

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/config-demo.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);

$id = $_GET['id'];

if (DEMO_INSTALLATION != '1') {

    $tmpq = $system->db()->prepare("
        SELECT `name`, slug, expression, active
        FROM scheduler
        WHERE id = :id");
    $tmpq->execute(array('id' => $id));
    $result = $tmpq->fetch();

    if (!$result) {

        $log_message = 'Unable to get scheduled task';
        $log_extra = array('Task ID' => $id);
        $log->info($log_message, $log_extra);

    } else {

        if ($result->active == '1') {

            $cron = \Cron\CronExpression::factory($result->expression);
            $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

        } else {

            $next_run = '1978-01-23 00:00:00';

        }

        $log_extra = array('Task ID' => $id, 'Name' => $result->name, 'Slug' => $result->slug, 'Expression' => $result->expression, 'Active' => $result->active, 'Next Run' => $next_run);

        if ($result->slug == 'cleanup') {

            $log_message = '[START] Cleanup Tasks';
            $log->info($log_message, $log_extra);

            $schedule->isRunning($id);
            $maint->performCleanup();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] Cleanup Tasks';
            $log->info($log_message);

            $_SESSION['s_message_success'] .= "System Cleanup Performed";

        } elseif ($result->slug == 'expiration-email') {

            $log_message = '[START] Send Expiration Email';
            $log->info($log_message, $log_extra);

            $email = new DomainMOD\Email();
            $schedule->isRunning($id);
            $email->sendExpirations('0');
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] Send Expiration Email';
            $log->info($log_message);

        } elseif ($result->slug == 'update-conversion-rates') {

            $log_message = '[START] Update Conversion Rates';
            $log->info($log_message, $log_extra);

            $schedule->isRunning($id);

            $tmpq = $system->db()->query("
                SELECT user_id, default_currency
                FROM user_settings");
            $result_conversion = $tmpq->fetchAll();

            if (!$result_conversion) {

                $log_message = 'No user currencies found';
                $log->error($log_message);

            } else {

                foreach ($result_conversion as $row_conversion) {

                    $conversion->updateRates($row_conversion->default_currency, $row_conversion->user_id);

                }

            }

            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] Update Conversion Rates';
            $log->info($log_message);

            $_SESSION['s_message_success'] .= "Conversion Rates Updated";

        } elseif ($result->slug == 'check-new-version') {

            $log_message = '[START] New Version Check';
            $log->info($log_message, $log_extra);

            $schedule->isRunning($id);
            $system->checkVersion(SOFTWARE_VERSION);
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] New Version Check';
            $log->info($log_message);

            $_SESSION['s_message_success'] .= "No Upgrade Available";

        } elseif ($result->slug == 'data-warehouse-build') {

            $log_message = '[START] Build Data Warehouse';
            $log->info($log_message, $log_extra);

            $dw = new DomainMOD\DwBuild();
            $schedule->isRunning($id);
            $dw->build();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] Build Data Warehouse';
            $log->info($log_message);

            $_SESSION['s_message_success'] .= "Data Warehouse Rebuilt";

        } elseif ($result->slug == 'domain-queue') {

            $log_message = '[START] Process Domain Queue';
            $log->info($log_message, $log_extra);

            $queue = new DomainMOD\DomainQueue();
            $schedule->isRunning($id);
            $queue->processQueueList();
            $queue->processQueueDomain();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = '[END] Process Domain Queue';
            $log->info($log_message);

            $_SESSION['s_message_success'] .= "Domain Queue Processed";

        }

    }

} else {

    if (DEMO_INSTALLATION == '1') {

        $_SESSION['s_message_danger'] .= "Tasks Disabled in Demo Mode";

    } else {

        $_SESSION['s_message_success'] .= "Task Run";

    }

}

header("Location: index.php");
exit;
