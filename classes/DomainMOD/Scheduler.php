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

    // The database version whose update adds settings.cron_last_run, the record of cron.php's last run
    const CRON_LAST_RUN_DB_VERSION = '4.26.0';

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
     * Whether a run holds the task's lock now. It only looks at the lock, never takes it, so a page asking (such as the
     * SSL Queue's processing notice) can't turn away a run starting at the same moment.
     */
    public function isTaskLocked($task_id)
    {
        $stmt = $this->deeb->cnxx->prepare("SELECT IS_USED_LOCK(:lock_name)");
        $stmt->bindValue('lock_name', $this->getTaskLockName($task_id), \PDO::PARAM_STR);
        $stmt->execute();
        $holder = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $holder !== null && $holder !== false;
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

    /*
     * A daily task's run time as the clock shows it in another time zone (array(hour, minute)), at its next run, or null
     * for any other expression. The run time menu saves in the user's zone, so it shows the task's time in that zone.
     * $now (a Unix timestamp) is for testing.
     */
    public function getDailyTimeIn($expression, $task_timezone, $user_timezone, $now = null)
    {
        $daily_time = $this->getDailyTime($expression);
        if ($daily_time === null) return null;

        $task_zone = $this->getZone($task_timezone);
        $user_zone = $this->getZone($user_timezone);
        if ($task_zone->getName() === $user_zone->getName()) return $daily_time;

        $next_run = new \DateTimeImmutable($this->getNextRun($expression, $task_zone->getName(), $now), new \DateTimeZone('UTC'));
        $local_time = $next_run->setTimezone($user_zone);

        return array((int) $local_time->format('G'), (int) $local_time->format('i'));
    }

    /*
     * Whether a chosen hour is the time the run time menu showed (see getDailyTimeIn()), so saving it changes nothing.
     * update.php then keeps the task as it is, since saving the same time again in another zone can skip a run (when
     * the clocks go back, the hour shown can be the second of two with the same name, and a saved time runs at the first).
     */
    public function isShownHour($expression, $task_timezone, $user_timezone, $hour, $now = null)
    {
        return $this->getDailyTimeIn($expression, $task_timezone, $user_timezone, $now) === array((int) $hour, 0);
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

            if (!$this->canUseCronLastRun()) return;

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
     * When cron.php last ran (UTC, 1970-01-01 if it never has), or null when it can't be read: before the database update
     * that adds the column (see canUseCronLastRun()), or when the read fails. Nothing else reads the column.
     */
    public function getCronLastRun()
    {
        try {

            if (!$this->canUseCronLastRun()) return null;

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

    /*
     * Whether a database at this version has settings.cron_last_run. New code can arrive before the database update that
     * adds the column (and, before the version bump, SOFTWARE_VERSION is still the older version too), so the column is
     * used only once the database itself is at that version or later.
     */
    public static function hasCronLastRun($db_version)
    {
        return is_string($db_version) && $db_version !== ''
            && version_compare($db_version, self::CRON_LAST_RUN_DB_VERSION, '>=');
    }

    // Whether the column can be read and written now: the database has it, and no database upgrade is waiting
    protected function canUseCronLastRun()
    {
        $db_version = $this->getDbVersion();
        return $db_version === SOFTWARE_VERSION && self::hasCronLastRun($db_version);
    }

    // The database's version, so the column isn't used before the update that adds it (System::getDbVersion())
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
            SELECT id, `name`, description, `interval`, expression, `timezone`, last_run, last_duration, next_run, active, slug
            FROM scheduler
            WHERE id = :task_id
            ORDER BY sort_order ASC");
        $stmt->bindValue('task_id', $task_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    /*
     * A task's name or description ($field is 'name' or 'description') in the viewer's language, found by its slug. The
     * stored name and description are in English (or the installer's language), so they're only shown for a task this
     * list doesn't know, as plain text. They stay in the database as they are.
     */
    public static function taskText($slug, $field, $stored)
    {
        $texts = array(
            'domain-queue' => array(
                'name' => _('Domain Queue Processing'),
                /* TRANSLATORS: %s is the software's name, DomainMOD */
                'description' => sprintf(_('Retrieves information for the domains in the Domain Queue and adds them to %s.'), SOFTWARE_TITLE)),
            'ssl-queue' => array(
                'name' => _('SSL Queue Processing'),
                /* TRANSLATORS: %s is the software's name, DomainMOD */
                'description' => sprintf(_('Reads the certificates of the sites in the SSL Queue and adds them to %s.'), SOFTWARE_TITLE)),
            'expiration-email' => array(
                'name' => _('Send Expiration Email'),
                'description' => _("Emails everyone who's subscribed about upcoming domain, SSL certificate, and hosting service renewals. Users can subscribe in their User Profile, and administrators can set the From address and the number of days the email covers in System Settings.")),
            'update-conversion-rates' => array(
                'name' => _('Update Conversion Rates'),
                /* TRANSLATORS: %s is the software's name, DomainMOD */
                'description' => sprintf(_("Retrieves the current currency conversion rates and updates every user's costs, which keeps the financial information in %s accurate. Users can set their currency in their User Profile."), SOFTWARE_TITLE)),
            'cleanup' => array(
                'name' => _('System Cleanup'),
                'description' => _('Converts domains to lowercase, checks that every TLD is correct, and records the status of each domain in your segments, which keeps segment filtering up to date. It also checks the domain, SSL certificate, and fee data against each other, and deletes unused fees. Finally, it removes change history older than the retention period set in System Settings.')),
            'check-new-version' => array(
                'name' => _('Check For New Version'),
                /* TRANSLATORS: %s is the software's name, DomainMOD */
                'description' => sprintf(_('Checks whether a newer version of %s is available to download.'), SOFTWARE_TITLE)),
            'data-warehouse-build' => array(
                'name' => _('Data Warehouse Build'),
                'description' => _('Rebuilds the Data Warehouse, so it has the latest information from your servers.')),
            'watch-list' => array(
                'name' => _('Check Watch List'),
                'description' => _('Checks the domains on the Watch List with public RDAP and WHOIS servers, a few at a time: weekly while a domain is far from expiry, daily near and after expiry, and more often once deletion is pending.')),
        );

        if (isset($texts[$slug][$field])) return $texts[$slug][$field];

        // The stored description can hold line breaks and emphasis, which become plain text here
        $text = preg_replace('/<br\s*\/?>/i', ' ', (string) $stored);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    // How often a task runs (the stored interval, in English) in the viewer's language, or as stored if it's unknown
    public static function intervalText($stored)
    {
        $intervals = array(
            'Every 5 Minutes' => _('Every 5 Minutes'),
            'Every 10 Minutes' => _('Every 10 Minutes'),
            'Daily' => _('Daily'),
        );

        return $intervals[$stored] ?? (string) $stored;
    }

    /*
     * A stored duration as plain text: getTimeDifference() saves it as HTML for the page it was first shown on (such as
     * ' (<em>2m 3s</em>)'), so this gives '2m 3s', or '' when there's none. Stored rows aren't rewritten.
     */
    public static function durationText($stored)
    {
        $text = trim(html_entity_decode(strip_tags((string) $stored), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (substr($text, 0, 1) === '(' && substr($text, -1) === ')') $text = trim(substr($text, 1, -1));
        return $text;
    }

    /*
     * A task's buttons, for the foot of its card: Disable (or Enable), and Run Now, which runs the task in place with htmx
     * and replaces the whole card with the result (see taskCard()), with the spinner that shows while it runs. These
     * links change data, so they carry the session's security token (see Csrf).
     */
    public function createActive($active, $task_id)
    {
        $task_id = (int) $task_id;
        $token = (new Csrf())->query();
        $layout = new Layout();

        if ($active == '0') {
            $toggle = $layout->linkButton('update.php?a=e&id=' . $task_id . '&' . $token, _('Enable'), 'secondary', 'bi-toggle-off');
        } else {
            $toggle = $layout->linkButton('update.php?a=d&id=' . $task_id . '&' . $token, _('Disable'), 'secondary', 'bi-toggle-on');
        }

        // The same look as linkButton(), with htmx's attributes, which linkButton() doesn't take
        $run_now = $layout->htmxTaskLink(htmlspecialchars('run.php?id=' . $task_id . '&' . $token, ENT_QUOTES, 'UTF-8'),
            '<i class="bi bi-play-fill me-1" aria-hidden="true"></i>' . htmlspecialchars(_('Run Now'), ENT_QUOTES, 'UTF-8'),
            '#domainmod-task-' . $task_id, 'outerHTML', 'domainmod-task-' . $task_id . '-working',
            ' class="btn btn-outline-secondary domainmod-css-button" data-domainmod-refocus');
        $spinner = $layout->htmxSpinner('domainmod-task-' . $task_id . '-working');

        // btn-group-sm makes the buttons Bootstrap's small size
        return '<div class="d-flex flex-wrap align-items-center gap-2 btn-group-sm">' . $toggle . $run_now . $spinner . '</div>';
    }

    /*
     * A task's card on the Task Scheduler page, in its column of the page's grid: its name and status, the description,
     * when it runs, its last and next run as facts, the run time form for an active daily task, and its buttons. Run Now
     * (run.php) sends the whole card back after an htmx run, with the run's result messages in $messages, which take
     * the keyboard focus so they're announced.
     */
    public function taskCard($task, $messages = '')
    {
        $layout = new Layout();
        $form = new Form();
        $task_id = (int) $task->id;
        $slug = $task->slug ?? '';
        $name = self::taskText($slug, 'name', $task->name);

        if ($task->active == '1') {
            $badge = $layout->statusBadge(htmlspecialchars(_('Active'), ENT_QUOTES, 'UTF-8'), _('This task runs on its schedule.'), 'success');
        } else {
            $badge = $layout->statusBadge(htmlspecialchars(_('Inactive'), ENT_QUOTES, 'UTF-8'), _("This task doesn't run on its schedule, but Run Now still runs it."), 'secondary');
        }

        // A daily task's time is shown in its own time zone, which may not be the viewer's.
        $daily_time = $this->getDailyTime($task->expression);
        $facts = array(array('label' => _('Runs'), 'value' => self::intervalText($task->interval)));
        if ($daily_time !== null) {
            $facts[] = array('label' => _('Run Time'), 'value' => sprintf('%02d:%02d', $daily_time[0], $daily_time[1]) . ' (' . $this->getZone($task->timezone)->getName() . ')', 'ltr' => true);
        }
        $facts[] = array('label' => _('Last Run'), 'value' => $this->time->toUserTimezone($task->last_run, 'Y-m-d H:i:s', _('Never')), 'ltr' => !Time::isUnset($task->last_run));
        if (!Time::isUnset($task->last_run)) {
            $facts[] = array('label' => _('Duration'), 'value' => self::durationText($task->last_duration), 'ltr' => true);
        }
        $facts[] = array('label' => _('Next Run'), 'value' => $this->time->toUserTimezone($task->next_run, 'Y-m-d H:i:s', '-'), 'ltr' => true);

        ob_start(); ?>
        <div class="col-12 col-md-6 col-xxl-4" id="domainmod-task-<?php echo $task_id; ?>">
            <div class="card card-outline card-danger h-100 mb-0">
                <div class="card-header"><?php // The card header's clearfix would be a flex item, so the flex box is inside it ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="card-title m-0"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php echo $badge; ?>
                    </div>
                </div>
                <div class="card-body"><?php

                    if ($messages != '') { ?>
                        <div data-domainmod-focus><?php echo $messages; ?></div><?php
                    } ?>

                    <p><?php echo htmlspecialchars(self::taskText($slug, 'description', $task->description), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php echo $layout->factList($facts); ?><?php

                    if ($task->interval == 'Daily' && $task->active == '1') {
                        // The menu shows the time in the zone update.php saves in (the user's), so an unchanged choice
                        // saves the same time. A time that isn't on the hour there is shown as its own choice.
                        $user_time = $this->getDailyTimeIn($task->expression, $task->timezone, $_SESSION['s_default_timezone'] ?? 'UTC');
                        $hour = ($user_time !== null && $user_time[1] === 0) ? sprintf('%02d', $user_time[0]) : '';
                        $other_time = ($user_time !== null && $user_time[1] !== 0) ? sprintf('%02d:%02d', $user_time[0], $user_time[1]) : '';
                        $field_id = 'domainmod-task-hour-' . $task_id; ?>
                        <form name="edit_task_form" method="post" action="update.php">
                            <?php echo $form->showCsrfField(); ?>
                            <label class="form-label" for="<?php echo $field_id; ?>"><?php echo _('Set Run Time'); ?></label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <select class="form-select w-auto" name="new_hour" id="<?php echo $field_id; ?>" aria-describedby="<?php echo $field_id; ?>-help">
                                    <?php echo $this->hourSelect($hour, $other_time); ?>
                                </select><?php
                                echo $form->showInputHidden('a', 'u');
                                echo $form->showInputHidden('id', $task_id);
                                echo $form->showSubmitButton(_('Change Time'), '', ''); ?>
                            </div>
                            <div class="form-text" id="<?php echo $field_id; ?>-help"><?php echo _('The time is saved in your own time zone, which you can change in your User Profile.'); ?></div>
                        </form><?php
                    } ?>

                </div>
                <div class="card-footer">
                    <?php echo $this->createActive($task->active, $task_id); ?>
                </div>
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

    // The run time menu's hours, with the task's current hour chosen. A current time that isn't on the hour ($other_time)
    // comes first and is chosen, with no value, so saving it unchanged changes nothing (update.php ignores it).
    public function hourSelect($hour, $other_time = '')
    {
        $form = new Form();
        $options = ($other_time !== '') ? $form->showDropdownOption('', htmlspecialchars($other_time, ENT_QUOTES, 'UTF-8'), '') : '';
        for ($i = 0; $i < 24; $i++) {
            $key = sprintf('%02d', $i);
            $options .= $form->showDropdownOption($key, $key . ':00', $hour);
        }
        return $options;
    }

} //@formatter:on
