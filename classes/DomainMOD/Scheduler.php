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

    // Whether PHP accepts a time zone, including older names such as Canada/Pacific.
    public function isValidZone($timezone)
    {
        try {

            new \DateTimeZone((string) $timezone);
            return (string) $timezone !== '';

        } catch (\Exception $e) {

            return false;

        }
    }

    // A time zone PHP accepts, or UTC if it isn't one.
    public function getZone($timezone)
    {
        try {

            if ((string) $timezone === '') throw new \Exception('No time zone');
            return new \DateTimeZone((string) $timezone);

        } catch (\Exception $e) {

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
        // These links change data, so they carry the session's security token (see Csrf).
        $token = (new Csrf())->query();
        $result = '<strong><font color=\'green\'>' . _('Active') . '</font></strong> [<a href=\'update.php?a=d&id=' . $task_id . '&' . $token .
            '\'>' . Format::toLower(_('Disable')) . '</a>] [<a href=\'run.php?id=' . $task_id . '&' . $token . '\'>' . Format::toLower(_('Run Now')) . '</a>]';
        if ($active == '0') {
            $result = '<strong><font color=\'red\'>' . _('Inactive') . '</font></strong> [<a href=\'update.php?a=e&id=' . $task_id . '&' . $token .
                '\'>' . Format::toLower(_('Enable')) . '</a>] [<a href=\'run.php?id=' . $task_id . '&' . $token . '\'>' . Format::toLower(_('Run Now')) . '</a>]';
        }
        return $result;
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
