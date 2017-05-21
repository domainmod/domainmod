<?php
/**
 * /cron.php
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
require_once('_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require_once(DIR_ROOT . '/vendor/autoload.php');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$conversion = new DomainMOD\Conversion();
$schedule = new DomainMOD\Scheduler();
$time = new DomainMOD\Time();
$log = new DomainMOD\Log('cron');

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/config-demo.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/database.inc.php');

if (DEMO_INSTALLATION != '1') {

    $tmpq = $system->db()->query("UPDATE scheduler SET is_running = '0'");

    $tmpq = $system->db()->query("SELECT id, `name`, slug, expression, active
                                  FROM scheduler
                                  WHERE active = '1'
                                    AND is_running = '0'
                                    AND next_run <= '" . $time->stamp() . "'");
    $result = $tmpq->fetchAll();

    if (!$result) {

        $log_message = 'No scheduled tasks to run';
        $log->info($log_message);

    } else {

        foreach ($result as $row) {

            $cron = \Cron\CronExpression::factory($row->expression);
            $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

            $log_extra = array('Task ID' => $row->id, 'Name' => $row->name, 'Slug' => $row->slug, 'Expression' => $row->expression, 'Active' => $row->active, 'Next Run' => $next_run);

            if ($row->slug == 'cleanup') {

                $log_message = '[START] Cleanup Tasks';
                $log->info($log_message, $log_extra);

                $schedule->isRunning($dbcon, $row->id);
                $maint->performCleanup($dbcon);
                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] Cleanup Tasks';
                $log->info($log_message);

            } elseif ($row->slug == 'expiration-email') {

                $log_message = '[START] Send Expiration Email';
                $log->info($log_message, $log_extra);

                $email = new DomainMOD\Email();
                $schedule->isRunning($dbcon, $row->id);
                $email->sendExpirations($dbcon, '1');
                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] Send Expiration Email';
                $log->info($log_message);

            } elseif ($row->slug == 'update-conversion-rates') {

                $log_message = '[START] Update Conversion Rates';
                $log->info($log_message, $log_extra);

                $schedule->isRunning($dbcon, $row->id);

                $tmpq = $system->db()->query("SELECT user_id, default_currency
                                              FROM user_settings");
                $result_conversion = $tmpq->fetchAll();

                if (!$result_conversion) {

                    $log_message = 'No user currencies found';
                    $log->error($log_message);

                } else {

                    foreach ($result_conversion as $row_conversion) {

                        $conversion->updateRates($dbcon, $row_conversion->default_currency, $row_conversion->user_id);

                    }

                }

                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] Update Conversion Rates';
                $log->info($log_message);

            } elseif ($row->slug == 'check-new-version') {

                $log_message = '[START] New Version Check';
                $log->info($log_message, $log_extra);

                $schedule->isRunning($dbcon, $row->id);
                $system->checkVersion($dbcon, SOFTWARE_VERSION);
                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] New Version Check';
                $log->info($log_message);

            } elseif ($row->slug == 'data-warehouse-build') {

                $log_message = '[START] Build Data Warehouse';
                $log->info($log_message, $log_extra);

                $dw = new DomainMOD\DwBuild();
                $schedule->isRunning($dbcon, $row->id);
                $dw->build($dbcon);
                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] Build Data Warehouse';
                $log->info($log_message);

            } elseif ($row->slug == 'domain-queue') {

                $log_message = '[START] Process Domain Queue';
                $log->info($log_message, $log_extra);

                $queue = new DomainMOD\DomainQueue($dbcon);
                $schedule->isRunning($dbcon, $row->id);
                $queue->processQueueList($dbcon);
                $queue->processQueueDomain($dbcon);
                $schedule->updateTime($dbcon, $row->id, $time->stamp(), $next_run);
                $schedule->isFinished($dbcon, $row->id);

                $log_message = '[END] Process Domain Queue';
                $log->info($log_message);

            } else {

                $log_message = 'There are results, but no matching slugs';
                $log->error($log_message, $log_extra);

            }

        }

    }

}
