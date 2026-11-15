<?php
/**
 * /classes/DomainMOD/Scheduler.php
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
//@formatter:off
namespace DomainMOD;

class Scheduler
{
    /*
     * How long cron.php can go without running before administrators are warned (see healthState()). It should run every
     * 10 minutes, but some hosts only allow hourly cron jobs, so two hours keeps the warning from coming and going.
     */
    const CRON_STALE_SECONDS = 7200;

    public $deeb;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->time = new Time();
    }

    public function isRunning($task_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE scheduler
            SET is_running = '1'
            WHERE id = :task_id");
        $stmt->bindValue('task_id', $task_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function isFinished($task_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE scheduler
            SET is_running = '0'
            WHERE id = :task_id");
        $stmt->bindValue('task_id', $task_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /*
     * Takes the task's database lock without waiting, and returns whether it got it. The database releases a named lock
     * by itself when the connection that holds it ends, so a run that crashes or is killed frees its task straight away.
     */
    public function getTaskLock($task_id)
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT GET_LOCK(:lock_name, 0)");
        $stmt->bindValue('lock_name', $this->getTaskLockName($task_id), \PDO::PARAM_STR);
        $stmt->execute();

        return (string) $stmt->fetchColumn() === '1';
    }

    public function releaseTaskLock($task_id)
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT RELEASE_LOCK(:lock_name)");
        $stmt->bindValue('lock_name', $this->getTaskLockName($task_id), \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * Named locks are shared by every database on the server, so the name includes a short hash of this database's name,
     * and two installations on one server don't block each other (it stays well under MySQL's 64-character limit).
     */
    protected function getTaskLockName($task_id)
    {
        return 'domainmod_task_' . substr(md5(DB_NAME), 0, 12) . '_' . (int) $task_id;
    }

    // Clears the running flag of any task that isn't really running (its lock is free), such as after a crash.
    public function clearStoppedTasks()
    {
        $result = $this->deeb->cnxx->query("
            SELECT id
            FROM scheduler
            WHERE is_running = '1'")->fetchAll();

        foreach ($result as $row) {

            if ($this->getTaskLock($row->id)) {

                $this->isFinished($row->id);
                $this->releaseTaskLock($row->id);

            }

        }
    }

    /*
     * The next time a task is due, worked out from now in the task's time zone, as a UTC timestamp. A daily task (a fixed
     * minute and hour) is worked out with PHP's own dates rather than the cron library, which can skip a whole day or
     * return a repeated time twice when the clocks change. It runs once every day: just after the change when its time
     * doesn't exist that day, and at the first occurrence when its time happens twice. $now (a Unix timestamp) is for
     * testing.
     */
    public function getNextRun($expression, $timezone = 'UTC', $now = null)
    {
        $zone = $this->getZone($timezone);
        $daily_time = $this->getDailyTime($expression);

        if ($daily_time === null) {

            $cron = new \Cron\CronExpression($expression);
            $current_time = ($now === null) ? 'now' : (new \DateTimeImmutable('@' . (int) $now))->setTimezone($zone);
            return $cron->getNextRunDate($current_time, 0, false, $zone->getName())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        }

        list($hour, $minute) = $daily_time;
        $now = ($now === null) ? time() : (int) $now;
        // Today's date in the task's zone, then the days around it as plain calendar dates (worked out in UTC, so a clock
        // change can't move them).
        $today = new \DateTimeImmutable((new \DateTimeImmutable('@' . $now))->setTimezone($zone)->format('Y-m-d'), new \DateTimeZone('UTC'));
        $next = null;

        // Yesterday's time too, since a time just before midnight can be moved forward into today by a clock change.
        foreach (array('-1 day', '+0 days', '+1 day') as $day) {

            $candidate = $this->resolveLocalTime($today->modify($day)->format('Y-m-d'), $hour, $minute, $zone);
            if ($candidate > $now && ($next === null || $candidate < $next)) $next = $candidate;

        }

        return gmdate('Y-m-d H:i:s', $next);
    }

    // The hour and minute of a daily task's expression (such as '0 7 * * *'), or null for any other expression.
    public function getDailyTime($expression)
    {
        if (!preg_match('/^(\d{1,2}) (\d{1,2}) \* \* \*$/', trim((string) $expression), $matches)) return null;
        if ((int) $matches[1] > 59 || (int) $matches[2] > 23) return null;

        return array((int) $matches[2], (int) $matches[1]);
    }

    // Whether a time zone can be used, including older names such as Canada/Pacific (see Time::zone()).
    public function isValidZone($timezone)
    {
        return Time::isKnownZone($timezone);
    }

    // The time zone (an older name becomes the zone it points to), or UTC if it isn't one.
    public function getZone($timezone)
    {
        if (Time::isKnownZone($timezone)) {

            return Time::zone($timezone);

        } else {

            $log = new Log('class.scheduler');
            $log_message = 'Invalid time zone for a scheduled task, so UTC was used';
            $log_extra = array('Time Zone' => (string) $timezone);
            $log->warning($log_message, $log_extra);

            return new \DateTimeZone('UTC');

        }
    }

    /*
     * A local date and time in a zone, as a Unix timestamp. PHP moves a time that doesn't exist (the clocks went forward)
     * forward by the size of the gap. For a time that happens twice (the clocks went back), PHP doesn't always pick the
     * same occurrence, so if the same local time also existed at an earlier offset, the earlier moment is used.
     */
    protected function resolveLocalTime($date, $hour, $minute, $zone)
    {
        $local = new \DateTimeImmutable(sprintf('%s %02d:%02d:00', $date, $hour, $minute), $zone);
        $timestamp = $local->getTimestamp();
        $wall_time = $local->format('Y-m-d H:i');

        // Up to and including a change at exactly this moment (PHP can return the moment of the change itself).
        $transitions = $zone->getTransitions($timestamp - 86400, $timestamp + 1);
        if (!is_array($transitions)) return $timestamp;

        for ($i = 1; $i < count($transitions); $i++) {

            $shift = $transitions[$i - 1]['offset'] - $transitions[$i]['offset'];
            if ($shift <= 0) continue;

            $earlier = $timestamp - $shift;
            $earlier_time = (new \DateTimeImmutable('@' . $earlier))->setTimezone($zone);

            if ($earlier_time->format('Y-m-d H:i') === $wall_time) return $earlier;

        }

        return $timestamp;
    }

    /*
     * Records a finished task: when it ran, how long it took, and when it's next due, worked out now that it has
     * finished, from the task's current settings (so a task that ran past its next time isn't due again straight away,
     * and a task disabled while it ran stays disabled). Then clears its running flag.
     */
    public function finishTask($task_id, $started)
    {
        $task = $this->getTask($task_id);

        if ($task && $task->active == '1') {

            $next_run = $this->getNextRun($task->expression, $task->timezone);

        } else {

            $next_run = $task ? $task->next_run : '1970-01-01 00:00:00';

        }

        $this->updateTime($task_id, $started, $next_run);
        $this->isFinished($task_id);
    }

    public function updateTime($task_id, $timestamp, $next_run)
    {
        $current_time = $this->time->stamp();
        $duration = $this->getTimeDifference($timestamp, $current_time);

        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            UPDATE scheduler
            SET last_run = :last_run,
                last_duration = :last_duration,
                next_run = :next_run
            WHERE id = :task_id");
        $stmt->bindValue('last_run', $current_time, \PDO::PARAM_STR);
        $stmt->bindValue('last_duration', $duration, \PDO::PARAM_STR);
        $stmt->bindValue('next_run', $next_run, \PDO::PARAM_STR);
        $stmt->bindValue('task_id', $task_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getTimeDifference($start_time, $end_time)
    {
        $difference = (strtotime($end_time) - strtotime($start_time));
        $minutes = intval($difference / 60);
        $seconds = $difference - ($minutes * 60);
        if ($minutes != '0') {
            $result = " (<em>" . $minutes . "m " . $seconds . "s</em>)";
        } else {
            $result = " (<em>" . $seconds . "s</em>)";
        }
        return $result;
    }

    /*
     * Whether the scheduled task (cron.php) is set up and running, for the Dashboard's Getting Started checklist: true
     * only when cron.php itself has run recently (see healthState()), so a Run Now on the Task Scheduler page doesn't
     * count. An install whose tasks are all disabled leaves the step unticked, since the step is about setting it up.
     */
    public function hasRunRecently()
    {
        return $this->healthState() === 'ok';
    }

    /*
     * Records a run of cron.php (once per run, whether or not a task is due), so the Task Scheduler's health can be
     * checked (see healthState()). Run Now doesn't call it. A failed write never stops the tasks: it's logged instead.
     */
    public function recordCronRun()
    {
        try {

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE settings
                SET cron_last_run = :cron_last_run");
            $stmt->bindValue('cron_last_run', $this->time->stamp(), \PDO::PARAM_STR);
            $stmt->execute();

        } catch (\Throwable $e) {

            $this->logCronProblem('The run of cron.php could not be recorded', $e);

        }
    }

    /*
     * When cron.php last ran (UTC, 1970-01-01 if it never has), or null when it can't be read: while the database upgrade
     * is waiting (new code can arrive before the column it reads), or when the read fails. Nothing else reads the column.
     */
    public function getCronLastRun()
    {
        try {

            if ($this->getDbVersion() !== SOFTWARE_VERSION) return null;

            $last_run = $this->deeb->cnxx->query("
                SELECT cron_last_run
                FROM settings")->fetchColumn();

            return ($last_run === false || $last_run === null) ? null : (string) $last_run;

        } catch (\Throwable $e) {

            $this->logCronProblem('The last run of cron.php could not be read', $e);
            return null;

        }
    }

    /*
     * The Task Scheduler's health, for the warnings on the Dashboard and the Task Scheduler page: 'ok', 'never' (cron.php
     * has never run), 'stale' (it hasn't run for more than CRON_STALE_SECONDS), or 'off' (no task is active, a demo
     * install, the database upgrade is waiting, or the time couldn't be read), when there's nothing to warn about.
     */
    public function healthState()
    {
        return $this->healthDetails()[0];
    }

    // The health state and cron.php's last run (UTC), read once for both
    protected function healthDetails()
    {
        if (defined('DEMO_INSTALLATION') && DEMO_INSTALLATION == true) return array('off', null);

        $last_run = $this->getCronLastRun();
        if ($last_run === null) return array('off', null);

        try {

            $any_active = (int) $this->deeb->cnxx->query("
                SELECT COUNT(*)
                FROM scheduler
                WHERE active = '1'")->fetchColumn() > 0;

        } catch (\Throwable $e) {

            $this->logCronProblem('The Task Scheduler\'s tasks could not be read', $e);
            return array('off', null);

        }

        return array(self::cronHealth($last_run, strtotime($this->time->stamp() . ' UTC'), $any_active), $last_run);
    }

    /*
     * The health state from cron.php's last run ($last_run, UTC), the time now ($now, a Unix timestamp), and whether any
     * task is active. A run exactly CRON_STALE_SECONDS ago still counts as ok.
     */
    public static function cronHealth($last_run, $now, $any_active)
    {
        if (!$any_active || $last_run === null) return 'off';
        if (Time::isUnset($last_run)) return 'never';

        $last_run_time = strtotime($last_run . ' UTC');
        if ($last_run_time === false) return 'never';

        return ($now - $last_run_time > self::CRON_STALE_SECONDS) ? 'stale' : 'ok';
    }

    /*
     * The warning for administrators when cron.php isn't running, as a slim alert, or '' when there's nothing to show.
     * $page is 'dashboard' or 'scheduler'. On the Dashboard, a never-run Task Scheduler is left to the Getting Started
     * checklist while it's showing ($getting_started), since its own step says the same. On the Task Scheduler page,
     * where it's set up, a never-run one gets an info alert rather than a warning.
     */
    public function healthAlert($page, $getting_started = false)
    {
        if (($_SESSION['s_is_admin'] ?? 0) !== 1) return '';

        list($state, $last_run) = $this->healthDetails();
        $system = new System();
        $guide_link = '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">';

        if ($state === 'stale') {

            $message = sprintf(_("The Task Scheduler hasn't run since %s, so expiry emails aren't being sent and the Domain Queue isn't being processed. Check that the cron job for cron.php is still running every 10 minutes, and that its address includes the key if you've given cron.php one. See the %sUser Guide%s for how to set it up."),
                $this->time->toUserTimezone($last_run, 'Y-m-d H:i'), $guide_link, '</a>');

            if ($page === 'dashboard') {

                $message .= ' ' . sprintf(_('To stop using the Task Scheduler, disable its tasks on the %sTask Scheduler%s page.'),
                    '<a href="' . WEB_ROOT . '/admin/scheduler/">', '</a>');

            }

            return $system->showMaintenanceTable($message);

        } elseif ($state === 'never' && !($page === 'dashboard' && $getting_started)) {

            $message = sprintf(_("The Task Scheduler hasn't run yet. Set up a cron job that runs cron.php every 10 minutes, so %s can send expiry emails and work through the Domain Queue. See the %sUser Guide%s for how to set it up."),
                SOFTWARE_TITLE, $guide_link, '</a>');

            return ($page === 'scheduler') ? $system->showMessageInfo($message) : $system->showMaintenanceTable($message);

        }

        return '';
    }

    // The database's version, so the column isn't read before the upgrade that adds it (System::getDbVersion())
    protected function getDbVersion()
    {
        return (new System())->getDbVersion();
    }

    /*
     * Logs a problem with the Task Scheduler's health record. Logging writes to the same database, which may be what
     * failed, so a failed log write goes to PHP's own error log instead, and nothing here can stop a page or cron.php.
     */
    protected function logCronProblem($message, $e)
    {
        try {

            $this->getLog()->error($message, array('Error' => $e->getMessage()));

        } catch (\Throwable $log_e) {

            $this->fallbackLog('DomainMOD: ' . $message . ': ' . $e->getMessage());

        }
    }

    protected function getLog()
    {
        return new Log('class.scheduler');
    }

    protected function fallbackLog($message)
    {
        error_log($message);
    }

    public function getTask($task_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT id, `name`, description, `interval`, expression, `timezone`, last_run, last_duration, next_run, active
            FROM scheduler
            WHERE id = :task_id
            ORDER BY sort_order ASC");
        $stmt->bindValue('task_id', $task_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function createActive($active, $task_id)
    {
        // These links change data, so they carry the session's security token (see Csrf). Run Now runs the task in place
        // with htmx and replaces the task's details with the result (see taskCard()).
        $token = (new Csrf())->query();
        $layout = new Layout();
        $run_now = $layout->htmxTaskLink('run.php?id=' . $task_id . '&' . $token, Format::toLower(_('Run Now')), '#domainmod-task-' . $task_id, 'outerHTML', 'domainmod-task-' . $task_id . '-working', ' data-domainmod-refocus');
        $spinner = $layout->htmxSpinner('domainmod-task-' . $task_id . '-working');
        $result = '<strong><span class=\'domainmod-css-text-highlight-green\'>' . _('Active') . '</span></strong> [<a href=\'update.php?a=d&id=' . $task_id . '&' . $token .
            '\'>' . Format::toLower(_('Disable')) . '</a>] [' . $run_now . ']' . $spinner;
        if ($active == '0') {
            $result = '<strong><span class=\'domainmod-css-text-highlight-red\'>' . _('Inactive') . '</span></strong> [<a href=\'update.php?a=e&id=' . $task_id . '&' . $token .
                '\'>' . Format::toLower(_('Enable')) . '</a>] [' . $run_now . ']' . $spinner;
        }
        return $result;
    }

    /*
     * A task's details on the Task Scheduler page, inside its card: the description, when it runs, its status and links,
     * its last and next run, and the run time form for daily tasks. Run Now (run.php) sends this back after an htmx run,
     * with the run's result messages in $messages, which take the keyboard focus so they're announced.
     */
    public function taskCard($task, $messages = '')
    {
        $form = new Form();
        ob_start(); ?>
        <div id="domainmod-task-<?php echo $task->id; ?>"><?php

            if ($messages != '') { ?>
                <div data-domainmod-focus><?php echo $messages; ?></div><?php
            } ?>

            <div>
                <?php echo $task->description ?>
            </div>
            <div>
                <BR><strong><?php echo _('Runs'); ?>:</strong> <?php echo $task->interval; ?><BR><?php

                // A daily task's time is shown in its own time zone, which may not be the viewer's.
                $daily_time = $this->getDailyTime($task->expression);
                if ($daily_time !== null) { ?>
                    <strong><?php echo _('Run Time'); ?>:</strong> <?php echo sprintf('%02d:%02d', $daily_time[0], $daily_time[1]) . ' (' . htmlentities($this->getZone($task->timezone)->getName(), ENT_QUOTES, 'UTF-8') . ')'; ?><BR><?php
                } ?>

                <strong><?php echo _('Status'); ?>:</strong> <?php echo $this->createActive($task->active, $task->id); ?><BR><?php

                $last_run = $this->time->toUserTimezone($task->last_run, 'Y-m-d H:i:s', '-'); ?>

                <strong><?php echo _('Last Run'); ?>:</strong> <?php echo $last_run; ?><?php echo $task->last_duration; ?><BR><?php

                $hour = ($daily_time !== null) ? sprintf('%02d', $daily_time[0]) : '';
                $next_run = $this->time->toUserTimezone($task->next_run, 'Y-m-d H:i:s', '-'); ?>

                <strong><?php echo _('Next Run'); ?>:</strong> <?php echo $next_run; ?><BR><?php

                if ($task->interval == 'Daily' && $task->active == '1') { ?>

                    <BR>
                    <form name="edit_task_form" method="post" action="update.php">
                        <?php echo $form->showCsrfField(); ?>
                        <label for="domainmod-task-hour-<?php echo (int) $task->id; ?>"><?php echo _('Set Run Time'); ?>:</label> <select name="new_hour" id="domainmod-task-hour-<?php echo (int) $task->id; ?>">
                            <?php echo $this->hourSelect($hour); ?>
                        </select><BR>
                        <em><?php echo _('The time is saved in your own time zone, which you can change in your user settings.'); ?></em><BR><BR><?php
                        echo $form->showInputHidden('a', 'u');
                        echo $form->showInputHidden('id', $task->id);
                        echo $form->showSubmitButton(_('Change Time'), '', ''); ?>
                    </form><?php
                } ?>

            </div>
        </div><?php
        return ob_get_clean();
    }

    public function getDateOutput($next_run)
    {
        if ($next_run == '1970-01-01 00:00:00') {
            return 'n/a';
        } else {
            return $next_run;
        }
    }

    public function hourSelect($hour)
    {
        $hours = array('00' => '00:00', '01' => '01:00', '02' => '02:00', '03' => '03:00', '04' => '04:00',
                       '05' => '05:00', '06' => '06:00', '07' => '07:00', '08' => '08:00', '09' => '09:00',
                       '10' => '10:00', '11' => '11:00', '12' => '12:00', '13' => '13:00', '14' => '14:00',
                       '15' => '15:00', '16' => '16:00', '17' => '17:00', '18' => '18:00', '19' => '19:00',
                       '20' => '20:00', '21' => '21:00', '22' => '22:00', '23' => '23:00');
        ob_start();
        foreach ($hours as $key => $value) { ?>
            <option value="<?php echo $key; ?>"<?php if ($hour == $key) echo ' selected'; ?>><?php echo $value; ?></option><?php
        }
        return ob_get_clean();
    }

} //@formatter:on
