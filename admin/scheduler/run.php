<?php
/**
 * /admin/scheduler/run.php
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

$conversion = new DomainMOD\Conversion();
$deeb = DomainMOD\Database::getInstance();
$log = new DomainMOD\Log('/admin/scheduler/run.php');
$maint = new DomainMOD\Maintenance();
$schedule = new DomainMOD\Scheduler();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$id = (int) $_GET['id'] ?? 0;

if (DEMO_INSTALLATION == false) {

    $stmt = $pdo->prepare("
        SELECT `name`, slug, expression, active
        FROM scheduler
        WHERE id = :id");
    $stmt->bindValue('id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if (!$result) {

        $log_message = 'Unable to get scheduled task';
        $log_extra = array('Task ID' => $id);
        $log->critical($log_message, $log_extra);

    } else {

        if ($result->active == '1') {

            $cron = new Cron\CronExpression($result->expression);
            $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

        } else {

            $next_run = '1970-01-01 00:00:00';

        }

        $log_extra = array(_('Task ID') => $id, _('Name') => $result->name, _('Slug') => $result->slug, _('Expression') => $result->expression, _('Active') => $result->active, _('Next Run') => $next_run);

        if ($result->slug == 'cleanup') {

            $log_message = sprintf('[%s]' . _('Cleanup Tasks'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $schedule->isRunning($id);
            $maint->performCleanup();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('Cleanup Tasks'), strtoupper(_('End')));
            $log->notice($log_message);

            $_SESSION['s_message_success'] .= _('System Cleanup Performed');

        } elseif ($result->slug == 'expiration-email') {

            $log_message = sprintf('[%s]' . _('Send Expiration Email'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $email = new DomainMOD\Email();
            $schedule->isRunning($id);
            $email->sendExpirations();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('Send Expiration Email'), strtoupper(_('End')));
            $log->notice($log_message);

        } elseif ($result->slug == 'update-conversion-rates') {

            $log_message = sprintf('[%s]' . _('Update Conversion Rates'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $schedule->isRunning($id);

            $result_conversion = $pdo->query("
                SELECT user_id, default_currency
                FROM user_settings")->fetchAll();

            if (!$result_conversion) {

                $log_message = 'No user currencies found';
                $log->critical($log_message);

            } else {

                foreach ($result_conversion as $row_conversion) {

                    $conversion->updateRates($row_conversion->default_currency, $row_conversion->user_id);

                }

            }

            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('Update Conversion Rates'), strtoupper(_('End')));
            $log->notice($log_message);

            $_SESSION['s_message_success'] .= _('Conversion Rates Updated');

        } elseif ($result->slug == 'check-new-version') {

            $log_message = sprintf('[%s]' . _('New Version Check'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $schedule->isRunning($id);
            $system->checkVersion(SOFTWARE_VERSION);
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('New Version Check'), strtoupper(_('End')));
            $log->notice($log_message);

            $_SESSION['s_message_success'] .= _('No Upgrade Available');

        } elseif ($result->slug == 'data-warehouse-build') {

            $log_message = sprintf('[%s]' . _('Build Data Warehouse'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $dw = new DomainMOD\DwBuild();
            $schedule->isRunning($id);
            $dw->build();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('Build Data Warehouse'), strtoupper(_('End')));
            $log->notice($log_message);

            $_SESSION['s_message_success'] .= _('Data Warehouse Rebuilt');

        } elseif ($result->slug == 'domain-queue') {

            $log_message = sprintf('[%s]' . _('Process Domain Queue'), strtoupper(_('Start')));
            $log->notice($log_message, $log_extra);

            $queue = new DomainMOD\DomainQueue();
            $schedule->isRunning($id);
            $queue->processQueueList();
            $queue->processQueueDomain();
            $schedule->updateTime($id, $time->stamp(), $next_run);
            $schedule->isFinished($id);

            $log_message = sprintf('[%s]' . _('Process Domain Queue'), strtoupper(_('End')));
            $log->notice($log_message);

            $_SESSION['s_message_success'] .= _('Domain Queue Processed');

        }

    }

} else {

    if (DEMO_INSTALLATION == true) {

        $_SESSION['s_message_danger'] .= _('Tasks Disabled in Demo Mode');

    } else {

        $_SESSION['s_message_success'] .= _('Task Run');

    }

}

header("Location: index.php");
exit;
