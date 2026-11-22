<?php
/**
 * /admin/scheduler/run.php
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
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

$id = (int) $_GET['id'] ?? 0;

if (DEMO_INSTALLATION == false) {

    $stmt = $pdo->prepare("
        SELECT `name`, slug, expression, active, next_run
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

    } elseif (!$schedule->getTaskLock($id)) {

        $_SESSION['s_message_danger'] .= _('That task is already running. Please try again once it finishes.') . '<BR>';

    } else {

        // Run Now runs only this task, so the queues leave no time for the Watch List (see WatchCheck::reserveFor())
        DomainMOD\WatchCheck::$single_task = true;

        try {

            $started = $time->stamp();
            $log_extra = array(_('Task ID') => $id, _('Name') => $result->name, _('Slug') => $result->slug, _('Expression') => $result->expression, _('Active') => $result->active, _('Next Run') => $result->next_run);

            if ($result->slug == 'cleanup') {

                $log_message = sprintf('[%s]' . _('Cleanup Tasks'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $schedule->isRunning($id);
                $cleanup_busy = false;
                try {
                    // Recorded in the Change History as this administrator's
                    $maint->performCleanup((int) ($_SESSION['s_user_id'] ?? 0));
                } catch (DomainMOD\RestoreLockException $e) {
                    // The fee-change lock stayed busy (such as during a long CSV import): the task isn't left marked as
                    // running, it stays due (as cron.php leaves it), and the busy message is shown with the result
                    $schedule->isFinished($id);
                    $_SESSION['s_message_danger'] .= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '<BR>';
                    $cleanup_busy = true;
                }

                if (!$cleanup_busy) {

                    $schedule->finishTask($id, $started);

                    $log_message = sprintf('[%s]' . _('Cleanup Tasks'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                    $_SESSION['s_message_success'] .= _('System Cleanup Performed') . '<BR>';

                }

            } elseif ($result->slug == 'expiration-email') {

                $log_message = sprintf('[%s]' . _('Send Expiration Email'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $email = new DomainMOD\Email();
                $schedule->isRunning($id);
                $email->sendExpirations();
                $schedule->finishTask($id, $started);

                $log_message = sprintf('[%s]' . _('Send Expiration Email'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

            } elseif ($result->slug == 'update-conversion-rates') {

                $log_message = sprintf('[%s]' . _('Update Conversion Rates'), DomainMOD\Format::toUpper(_('Start')));
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

                $schedule->finishTask($id, $started);

                $log_message = sprintf('[%s]' . _('Update Conversion Rates'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

                $_SESSION['s_message_success'] .= _('Conversion Rates Updated') . '<BR>';

            } elseif ($result->slug == 'check-new-version') {

                $log_message = sprintf('[%s]' . _('New Version Check'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $schedule->isRunning($id);
                $system->checkVersion(SOFTWARE_VERSION);
                $schedule->finishTask($id, $started);

                $log_message = sprintf('[%s]' . _('New Version Check'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

                $_SESSION['s_message_success'] .= _('No Upgrade Available') . '<BR>';

            } elseif ($result->slug == 'data-warehouse-build') {

                $log_message = sprintf('[%s]' . _('Build Data Warehouse'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $dw = new DomainMOD\DwBuild();
                $schedule->isRunning($id);
                $dw->build();
                $schedule->finishTask($id, $started);

                $log_message = sprintf('[%s]' . _('Build Data Warehouse'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

                $_SESSION['s_message_success'] .= _('Data Warehouse Rebuilt') . '<BR>';

            } elseif ($result->slug == 'domain-queue') {

                $log_message = sprintf('[%s]' . _('Process Domain Queue'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $queue = new DomainMOD\DomainQueue();
                $schedule->isRunning($id);
                $queue->processQueueList();
                $queue->processQueueDomain();
                $schedule->finishTask($id, $started);
                if ($queue->hadConflict()) {
                    $_SESSION['s_message_danger'] .= _("Someone else changed a domain at the same time, so the Domain Queue couldn't finish part of its work. The Domain Queue shows what's left to do.") . '<BR>';
                }

                $log_message = sprintf('[%s]' . _('Process Domain Queue'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

                $_SESSION['s_message_success'] .= _('Domain Queue Processed') . '<BR>';

            } elseif ($result->slug == 'ssl-queue') {

                $log_message = sprintf('[%s]' . _('Process SSL Queue'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $ssl_queue = new DomainMOD\SslQueue();
                $schedule->isRunning($id);
                $ssl_queue->process();
                $schedule->finishTask($id, $started);
                if ($ssl_queue->hadConflict()) {
                    $_SESSION['s_message_danger'] .= _("Someone else changed a certificate or domain at the same time, so the SSL Queue couldn't finish part of its work. The SSL Queue shows what's left to do.") . '<BR>';
                }

                $log_message = sprintf('[%s]' . _('Process SSL Queue'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message);

                $_SESSION['s_message_success'] .= _('SSL Queue Processed') . '<BR>';

            } elseif ($result->slug == 'watch-list') {

                $log_message = sprintf('[%s]' . _('Check Watch List'), DomainMOD\Format::toUpper(_('Start')));
                $log->notice($log_message, $log_extra);

                $watch_check = new DomainMOD\WatchCheck($pdo);
                $schedule->isRunning($id);
                $watch_summary = $watch_check->run(true);
                $schedule->finishTask($id, $started);
                if ($watch_check->hadConflict()) {
                    $_SESSION['s_message_danger'] .= _("Someone else changed a watched domain at the same time, so the Watch List check couldn't finish part of its work.") . '<BR>';
                }

                $log_message = sprintf('[%s]' . _('Check Watch List'), DomainMOD\Format::toUpper(_('End')));
                $log->notice($log_message, $watch_summary);

                // Turned off in System Settings, the task does nothing (see WatchCheck::run()), and says so as a warning,
                // which the htmx Run Now's result shows (System::htmxTaskResult())
                if (empty($watch_summary['off'])) {
                    $_SESSION['s_message_success'] .= _('Watch List Checked') . '<BR>';
                } else {
                    $_SESSION['s_message_warning'] .= _('The Watch List is turned off in System Settings, so nothing was checked.') . '<BR>';
                }

            }

        } finally {

            $schedule->releaseTaskLock($id);

        }

    }

} else {

    if (DEMO_INSTALLATION == true) {

        $_SESSION['s_message_danger'] .= _('Tasks Disabled in Demo Mode') . '<BR>';

    } else {

        $_SESSION['s_message_success'] .= _('Task Run') . '<BR>';

    }

}

// An htmx Run Now gets the task's details back with the result in them, instead of a redirect
$task = $schedule->getTask($id);
if ($system->isHtmxRequest() && $task) {
    $messages = $system->htmxTaskResult();
    echo $schedule->taskCard($task, $messages);
    exit;
}

header("Location: index.php");
exit;
