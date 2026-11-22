<?php
/**
 * /cron.php
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
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

// With a key set in config.inc.php, only the command line or a URL with the key can run the Task Scheduler.
if (CRON_KEY !== '' && PHP_SAPI !== 'cli' && !hash_equals(CRON_KEY, is_string($_GET['key'] ?? null) ? $_GET['key'] : '')) {

    http_response_code(403);
    echo _('This script needs its key. See the Task Scheduler section of the User Guide.');
    exit;

}

/*
 * The restore lock (see _includes/restore-gate.inc.php), before the database version check, the due-task query, or any
 * scheduler bookkeeping, held for the whole run, so tasks are always chosen, run, and finished against one database. A
 * restore that died part-way is finished here first, but only from the command line or with a configured key that
 * matched above (so an anonymous request can never start it), and only when this process can clear the flag. Where the
 * lock file can't be used, the database lock stands in for it. While a restore is running, or neither lock can be used
 * safely, the whole run is skipped, and every task is due again next time.
 */
if (domainmod_restore_flag_exists() && (PHP_SAPI === 'cli' || CRON_KEY !== '') && DomainMOD\RestoreRecovery::canRecoverHere()) {
    DomainMOD\RestoreRecovery::fromCron();
}
$cron_lock = domainmod_restore_lock(false);
if ($cron_lock['state'] !== 'held' || $cron_lock['flag']) {
    error_log('DomainMOD: scheduled tasks skipped (' . ($cron_lock['flag'] ? 'a restore is in progress or didn\'t finish'
        : ($cron_lock['state'] === 'busy' ? 'the restore lock is busy' : 'neither temp/restore.lock nor the database lock can be used')) . ')');
    exit;
}

$conversion = new DomainMOD\Conversion();
$deeb = DomainMOD\Database::getInstance();
$log = new DomainMOD\Log('/cron.php');
$maint = new DomainMOD\Maintenance();
$schedule = new DomainMOD\Scheduler();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';

$pdo = $deeb->cnxx;

$system->removeLegacyTempFiles();

if (DEMO_INSTALLATION == false && $system->getDbVersion() !== SOFTWARE_VERSION) {

    // New code can expect database changes that only the upgrade makes, so tasks wait until it has run.
    $log_message = 'Scheduled tasks are waiting for the database upgrade';
    $log_extra = array('Database Version' => $system->getDbVersion(), 'Software Version' => SOFTWARE_VERSION);
    $log->notice($log_message, $log_extra);

} elseif (DEMO_INSTALLATION == false) {

    // Every run counts, even one with nothing due, so administrators are warned when cron.php stops running.
    $schedule->recordCronRun();

    // A task's running flag is only cleared when it isn't really running (see Scheduler::clearStoppedTasks()).
    $schedule->clearStoppedTasks();

    $stmt = $pdo->prepare("
        SELECT id, `name`, slug, expression, active
        FROM scheduler
        WHERE active = '1'
          AND next_run <= :next_run
        ORDER BY sort_order, id");
    $bind_timestamp = $time->stamp();
    $stmt->bindValue('next_run', $bind_timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetchAll();

    if (!$result) {

        $log_message = 'No scheduled tasks to run';
        $log->info($log_message);

    } else {

        // With a time limit, the tasks that look domains up take turns when more than one has work (see
        // DomainMOD\Scheduler::turn())
        $schedule->turn(array_map(function ($task_row) {
            return $task_row->slug;
        }, $result));

        foreach ($result as $row) {

            // Another contested run has claimed this task, so it's left to that run (see
            // DomainMOD\Scheduler::leftToOtherRun()).
            if ($schedule->leftToOtherRun($row->slug)) continue;

            // Another run has this task, so it's left to that run.
            if (!$schedule->getTaskLock($row->id)) {
                $schedule->releaseClaim($row->slug);
                continue;
            }

            try {

                // Read the task again now that it's locked, since another run may have just finished it.
                $task = $schedule->getTask($row->id);
                if (!$task || $task->active != '1' || $task->next_run > $time->stamp()) continue;
                DomainMOD\Scheduler::noteRan($row->slug);

                $started = $time->stamp();
                $log_extra = array('Task ID' => $row->id, 'Name' => $row->name, 'Slug' => $row->slug, 'Expression' => $row->expression, 'Active' => $row->active, 'Next Run' => $task->next_run);

                if ($row->slug == 'cleanup') {

                    $log_message = sprintf('[%s]' . _('Cleanup Tasks'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $schedule->isRunning($row->id);
                    try {
                        $cleanup_result = $maint->performCleanup();
                        $schedule->finishTask($row->id, $started);
                        // A skipped hosting fee step (the hosting lock stayed busy) is logged, and the next run catches up
                        $cleanup_hosting_warning = is_array($cleanup_result) ? (string) ($cleanup_result['hosting_warning'] ?? '') : '';
                        if ($cleanup_hosting_warning !== '') $log->warning($cleanup_hosting_warning);
                        $log_message = sprintf('[%s]' . _('Cleanup Tasks'), DomainMOD\Format::toUpper(_('End')));
                        $log->notice($log_message);
                    } catch (DomainMOD\RestoreLockException $e) {
                        // The fee-change lock stayed busy (such as during a long CSV import): the clean-up stays due for
                        // the next run, and this run goes on to the other tasks
                        $schedule->isFinished($row->id);
                        $log->warning('Cleanup Tasks skipped, since DomainMOD was busy', array('Error' => $e->getMessage()));
                    }

                } elseif ($row->slug == 'expiration-email') {

                    $log_message = sprintf('[%s]' . _('Send Expiration Email'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $email = new DomainMOD\Email();
                    $schedule->isRunning($row->id);
                    $email->sendExpirations(true);
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Send Expiration Email'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'update-conversion-rates') {

                    $log_message = sprintf('[%s]' . _('Update Conversion Rates'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $schedule->isRunning($row->id);

                    $result_conversion = $pdo->query("
                        SELECT user_id, default_currency
                        FROM user_settings")->fetchAll();

                    if (!$result_conversion) {

                        $log_message = 'No user currencies found';
                        $log->critical($log_message);

                    } else {

                        foreach ($result_conversion as $row_conversion) {

                            $conversion->updateRates($row_conversion->default_currency, $row_conversion->user_id, true);

                        }

                    }

                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Update Conversion Rates'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'check-new-version') {

                    $log_message = sprintf('[%s]' . _('New Version Check'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $schedule->isRunning($row->id);
                    $system->checkVersion(SOFTWARE_VERSION);
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('New Version Check'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'data-warehouse-build') {

                    $log_message = sprintf('[%s]' . _('Build Data Warehouse'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $dw = new DomainMOD\DwBuild();
                    $schedule->isRunning($row->id);
                    $dw->build();
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Build Data Warehouse'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'domain-queue') {

                    $log_message = sprintf('[%s]' . _('Process Domain Queue'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $queue = new DomainMOD\DomainQueue();
                    $schedule->isRunning($row->id);
                    $queue->processQueueList();
                    $queue->processQueueDomain();
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Process Domain Queue'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'ssl-queue') {

                    $log_message = sprintf('[%s]' . _('Process SSL Queue'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $ssl_queue = new DomainMOD\SslQueue();
                    $schedule->isRunning($row->id);
                    $ssl_queue->process();
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Process SSL Queue'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message);

                } elseif ($row->slug == 'domain-refresh') {

                    $log_message = sprintf('[%s]' . _('Refresh Domains'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $refresh = new DomainMOD\DomainRefresh();
                    $schedule->isRunning($row->id);
                    $refresh_summary = $refresh->refreshDue();
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Refresh Domains'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message, $refresh_summary);

                } elseif ($row->slug == 'watch-list') {

                    $log_message = sprintf('[%s]' . _('Check Watch List'), DomainMOD\Format::toUpper(_('Start')));
                    $log->notice($log_message, $log_extra);

                    $watch_check = new DomainMOD\WatchCheck($pdo);
                    $schedule->isRunning($row->id);
                    $watch_summary = $watch_check->run();
                    $schedule->finishTask($row->id, $started);

                    $log_message = sprintf('[%s]' . _('Check Watch List'), DomainMOD\Format::toUpper(_('End')));
                    $log->notice($log_message, $watch_summary);

                } else {

                    $log_message = 'There are results, but no matching slugs';
                    $log->critical($log_message, $log_extra);

                }

            } finally {

                $schedule->releaseTaskLock($row->id);
                // Another contested run can now try this task, if it's still due.
                $schedule->releaseClaim($row->slug);

            }

        }

        $schedule->finishTurn();

    }

}
