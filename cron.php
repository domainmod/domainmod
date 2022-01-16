<?php
/**
 * /cron.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

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

if (DEMO_INSTALLATION == false) {

    $pdo->query("UPDATE scheduler SET is_running = '0'");

    $stmt = $pdo->prepare("
        SELECT id, `name`, slug, expression, active
        FROM scheduler
        WHERE active = '1'
          AND is_running = '0'
          AND next_run <= :next_run");
    $bind_timestamp = $time->stamp();
    $stmt->bindValue('next_run', $bind_timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetchAll();

    if (!$result) {

        $log_message = 'No scheduled tasks to run';
        $log->info($log_message);

    } else {

        foreach ($result as $row) {

            $cron = \Cron\CronExpression::factory($row->expression);
            $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

            $log_extra = array('Task ID' => $row->id, 'Name' => $row->name, 'Slug' => $row->slug, 'Expression' => $row->expression, 'Active' => $row->active, 'Next Run' => $next_run);

            if ($row->slug == 'cleanup') {

                $log_message = sprintf('[%s]' . _('Cleanup Tasks'), strtoupper(_('Start')));
                $log->notice($log_message, $log_extra);

                $schedule->isRunning($row->id);
                $maint->performCleanup();
                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('Cleanup Tasks'), strtoupper(_('End')));
                $log->notice($log_message);

            } elseif ($row->slug == 'expiration-email') {

                $log_message = sprintf('[%s]' . _('Send Expiration Email'), strtoupper(_('Start')));
                $log->notice($log_message, $log_extra);

                $email = new DomainMOD\Email();
                $schedule->isRunning($row->id);
                $email->sendExpirations(true);
                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('Send Expiration Email'), strtoupper(_('End')));
                $log->notice($log_message);

            } elseif ($row->slug == 'update-conversion-rates') {

                $log_message = sprintf('[%s]' . _('Update Conversion Rates'), strtoupper(_('Start')));
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

                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('Update Conversion Rates'), strtoupper(_('End')));
                $log->notice($log_message);

            } elseif ($row->slug == 'check-new-version') {

                $log_message = sprintf('[%s]' . _('New Version Check'), strtoupper(_('Start')));
                $log->notice($log_message, $log_extra);

                $schedule->isRunning($row->id);
                $system->checkVersion(SOFTWARE_VERSION);
                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('New Version Check'), strtoupper(_('End')));
                $log->notice($log_message);

            } elseif ($row->slug == 'data-warehouse-build') {

                $log_message = sprintf('[%s]' . _('Build Data Warehouse'), strtoupper(_('Start')));
                $log->notice($log_message, $log_extra);

                $dw = new DomainMOD\DwBuild();
                $schedule->isRunning($row->id);
                $dw->build();
                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('Build Data Warehouse'), strtoupper(_('End')));
                $log->notice($log_message);

            } elseif ($row->slug == 'domain-queue') {

                $log_message = sprintf('[%s]' . _('Process Domain Queue'), strtoupper(_('Start')));
                $log->notice($log_message, $log_extra);

                $queue = new DomainMOD\DomainQueue();
                $schedule->isRunning($row->id);
                $queue->processQueueList();
                $queue->processQueueDomain();
                $schedule->updateTime($row->id, $time->stamp(), $next_run);
                $schedule->isFinished($row->id);

                $log_message = sprintf('[%s]' . _('Process Domain Queue'), strtoupper(_('End')));
                $log->notice($log_message);

            } else {

                $log_message = 'There are results, but no matching slugs';
                $log->critical($log_message, $log_extra);

            }

        }

    }

}
