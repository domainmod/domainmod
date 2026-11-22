<?php
/**
 * /classes/DomainMOD/WatchCheck.php
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

/*
 * The Watch List's checks (the Check Watch List task and the edit page's Check Now): which entries are due, the lookups
 * (public RDAP, or WHOIS for a TLD without RDAP), what each answer means for the entry, and the Change History rows for
 * what changed. Each answer goes through decide(), which has no database, so the rules can be tested on their own, and
 * then save(), which writes it in its own transaction with its history.
 *
 * - The first answer of a watching period (after an add or an unarchive) is its baseline, which records nothing.
 * - A move into or out of "Not registered" is recorded only once a second lookup from the same source agrees (the
 *   confirming lookup, CONFIRM_GAP later), except a first answer from RDAP that the domain isn't registered, which is
 *   taken as the baseline at once. A first WHOIS answer that it isn't registered still waits for its confirming
 *   lookup. A confirmation is limited by time, never by a count of attempts.
 * - A lookup runs outside any transaction, so its save checks the entry's revision and throws the result away when the
 *   entry changed meanwhile, and archives the entry instead when its name is now live in Domains.
 * - Entries are checked weekly far from expiry, daily near and after it, and more often once deletion is pending, for at
 *   most FAST_ENTRIES at once. Failures back off.
 */
class WatchCheck
{
    // Each scheduled run: the most time it takes, and the most lookups it makes (Run Now takes less time)
    const WATCH_BUDGET = 60;
    const WATCH_BATCH = 50;
    const RUN_NOW_BUDGET = 20;
    const CHECK_NOW_BUDGET = 30;
    // The time it needs left in the request when it starts on its turn in a cron run with a time limit (see
    // Scheduler::turn()): a lookup needs MIN_LOOKUP_TIME, and the run keeps a quarter of what's left
    const WATCH_NEEDS = 20;
    // A lookup is only started with at least this many seconds left, as Refresh does
    const MIN_LOOKUP_TIME = 10;
    // Lookups to one TLD server per run (WHOIS uses DomainQueue::WHOIS_PER_SERVER)
    const RDAP_PER_SERVER = 30;
    // Entries that get routine checks more often than daily at once
    const FAST_ENTRIES = 50;
    // A confirming lookup comes this long after the answer it confirms, and the change is given up by the first failed
    // confirming lookup that sent its request this long after the change was first seen (in seconds)
    const CONFIRM_GAP = 900;
    const CONFIRM_TIMEOUT = 21600;
    // Check Now shows the stored answer instead of asking again within this long of the last answer (in seconds)
    const CHECK_NOW_GAP = 600;
    // Check Now's session limits, as Refresh's (DomainRefresh::SAME_DOMAIN_GAP and ANY_DOMAIN_GAP)
    const SAME_ENTRY_GAP = 30;
    const ANY_ENTRY_GAP = 5;
    // The archive of entries now live in Domains: entries per run, and the time it may take beyond its first archive
    const SWEEP_BATCH = 50;
    const SWEEP_BUDGET = 10;
    // A save's wait for a row another writer holds (seconds), and the time the run's schedule changes may take in all
    const LOCK_WAIT = 2;
    const SCHEDULE_BUDGET = 5;
    // How long an entry is held after a rate limit, a busy server, or an incomplete status read
    const HOLD = 3600;
    // Back-off limits for slow and fast stages
    const SLOW_BACKOFF_CAP = 2592000;
    const FAST_BACKOFF_CAP = 86400;
    // An entry is checked daily from this many days before its expiry date
    const NEAR_EXPIRY_DAYS = 60;

    const HOUR = 3600;
    const DAY = 86400;
    const WEEK = 604800;

    const UNSET_DATE = '1970-01-01';
    const UNSET_TIME = '1970-01-01 00:00:00';

    // The columns a run reads for every active entry, to rank and pick
    const PLAN_COLUMNS = 'id, domain, stage, expiry_date, drop_from, drop_to, stage_since, next_due, last_attempt, failures,
        pending_stage, hold_until, revision';

    protected $pdo;
    protected $log;
    protected $rdap = null;
    protected $whois = null;

    // Whether a save in this run met another request's write (errors 1020 and 1213, see hadConflict())
    protected $had_conflict = false;

    // Test hook: when set, a callable returning the current time (a Unix timestamp, possibly fractional)
    public $clock = null;
    // The registrar name and URL rules (RdapAssets::storedName() and storedUrl()), made when first needed
    public $assets = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->log = new Log('class.watchcheck');
    }

    public function now()
    {
        return $this->clock !== null ? (float) call_user_func($this->clock) : microtime(true);
    }

    protected function rdap()
    {
        return $this->rdap = $this->rdap ?? new Rdap();
    }

    protected function whois()
    {
        return $this->whois = $this->whois ?? new Whois();
    }

    protected static function stamp($time)
    {
        return gmdate('Y-m-d H:i:s', (int) floor($time));
    }

    /*
     * A stored time or date as a timestamp (0 when unset or unreadable). It's read with DateTimeImmutable, since
     * strtotime() gives false on 32-bit PHP (the official Docker image's armhf build) for a time past 2038, such as an
     * expiry date years away. A time past PHP's int range comes back as a float, which is exact for these times.
     */
    protected static function time($stamp)
    {
        if (WatchLifecycle::isUnset($stamp)) return 0;
        try {
            $unix = (new \DateTimeImmutable(substr((string) $stamp, 0, 19) . ' UTC'))->format('U');
        } catch (\Exception $e) {
            return 0;
        }
        return (string) (int) $unix === $unix ? (int) $unix : (float) $unix;
    }

    /*
     * Whether a scheduled run would have work: an entry now live in Domains to archive, or an entry its selection would
     * pick (worked out by the same code the run uses), for the turns the tasks take in a cron run with a time limit (see
     * Scheduler::turn(), which reads whether the task is on and due from the run's own task list). False, without a
     * query of the list, before the database update that adds the Watch List, and while it's turned off in System
     * Settings (WatchList::isEnabled()).
     */
    public static function reserveNeeded(\PDO $pdo)
    {
        if (!WatchList::isEnabled()) return false;
        $check = new self($pdo);
        $moved = $check->movedCandidates();
        if ($moved) return true;
        $plan = $check->plan($check->activeRows(), $moved, $check->now());
        return (bool) $plan['due'];
    }

    // Every active entry's planning columns
    public function activeRows()
    {
        return $this->pdo->query("
            SELECT " . self::PLAN_COLUMNS . "
            FROM watch_domains
            WHERE archived = 0
            ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Active entries whose name is live in Domains (not Expired or Sold): entry id => the domain's id
    public function movedCandidates()
    {
        $moved = array();
        $stmt = $this->pdo->query("
            SELECT w.id, MIN(d.id) AS domain_id
            FROM watch_domains AS w
            JOIN domains AS d ON d.domain = w.domain AND d.active NOT IN ('0', '10')
            WHERE w.archived = 0
            GROUP BY w.id
            ORDER BY w.id");
        foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $moved[(int) $row[0]] = (int) $row[1];
        $stmt->closeCursor();
        return $moved;
    }

    // Whether an entry would use a fast slot now (pending delete, or redemption from two days before its window)
    public static function wantsFast(array $row, $now)
    {
        if ($row['stage'] === 'pending_delete') return true;
        if ($row['stage'] !== 'redemption' || WatchLifecycle::isUnset($row['drop_from'])) return false;
        return $now >= self::time($row['drop_from'] . ' 00:00:00') - 2 * self::DAY;
    }

    /*
     * The entries that hold the FAST_ENTRIES fast slots now (id => true): those whose window hasn't ended first, by the
     * start of their window (a pending delete entry with no window by when that stage was first seen plus 4 days), then
     * those whose window has ended, oldest window first
     */
    public static function fastSlots(array $rows, array $moved, $now)
    {
        $today = gmdate('Y-m-d', (int) $now);
        $ranked = array();
        foreach ($rows as $row) {
            // A confirming lookup isn't a routine check, so an entry waiting for one never holds a slot
            if (isset($moved[(int) $row['id']]) || $row['pending_stage'] !== '' || !self::wantsFast($row, $now)) continue;
            $start = !WatchLifecycle::isUnset($row['drop_from']) ? (string) $row['drop_from']
                : gmdate('Y-m-d', self::time($row['stage_since']) + 4 * self::DAY);
            $ended = !WatchLifecycle::isUnset($row['drop_to']) && (string) $row['drop_to'] < $today ? 1 : 0;
            $ranked[] = array($ended, $start, (int) $row['id']);
        }
        sort($ranked);
        $slots = array();
        foreach (array_slice($ranked, 0, self::FAST_ENTRIES) as $entry) $slots[$entry[2]] = true;
        return $slots;
    }

    /*
     * The routine time to the next check after a check at $time (seconds), from the entry's stage, expiry date, and drop
     * window, and whether it holds a fast slot ($fast)
     */
    public static function interval(array $row, $time, $fast)
    {
        switch ($row['stage']) {
            case 'registered':
                if (WatchLifecycle::isUnset($row['expiry_date'])) return self::WEEK;
                $window = self::time($row['expiry_date'] . ' 00:00:00') - self::NEAR_EXPIRY_DAYS * self::DAY;
                if ($window - $time <= 0) return self::DAY;
                // A weekly check never passes the day the entry enters its 60-day window
                return (int) max(self::HOUR, min(self::WEEK, $window - $time));
            case 'redemption':
                return $fast && self::wantsFast($row, $time) ? 6 * self::HOUR : self::DAY;
            case 'pending_delete':
                if (!$fast) return self::DAY;
                if (WatchLifecycle::isUnset($row['drop_from'])) return self::HOUR;
                return $time >= self::time($row['drop_from'] . ' 00:00:00') ? self::HOUR : 2 * self::HOUR;
        }
        return self::DAY;
    }

    // The delay after the $failures-th failure in a row: the routine interval, doubling, up to 30 days (1 day for fast
    // stages)
    public static function backoff(array $row, $failures, $time, $fast)
    {
        $base = self::interval($row, $time, $fast);
        $cap = $row['stage'] === 'pending_delete' || ($row['stage'] === 'redemption' && self::wantsFast($row, $time))
            ? self::FAST_BACKOFF_CAP : self::SLOW_BACKOFF_CAP;
        $delay = $base * (2 ** min(30, max(0, (int) $failures - 1)));
        return (int) min($cap, max($base, $delay));
    }

    /*
     * A run's plan, worked out in memory with nothing written: the fast slots, each entry's effective next check (a slot
     * brings a routine check forward to its fast interval after the last attempt, and losing one pushes it to a day after
     * the last attempt, but a confirmation or a back-off is never changed), the schedule changes to save (id =>
     * array(next_due, revision)), and the entries due now, oldest first. Entries live in Domains ($moved) are left out.
     */
    public function plan(array $rows, array $moved, $now)
    {
        $slots = self::fastSlots($rows, $moved, $now);
        $changes = array();
        $due = array();
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($moved[$id])) continue;
            $next = self::time($row['next_due']);
            $effective = $next;
            if ($row['pending_stage'] === '' && (int) $row['failures'] === 0 && self::wantsFast($row, $now)) {
                $last = self::time($row['last_attempt']);
                if (isset($slots[$id])) {
                    $fast_due = $last > 0 ? $last + self::interval($row, $now, true) : (int) $now;
                    $effective = min($next, $fast_due);
                } elseif ($last > 0) {
                    $effective = max($next, $last + self::DAY);
                }
            }
            if ($effective !== $next) $changes[$id] = array(self::stamp($effective), (int) $row['revision']);
            if ($effective <= $now && self::time($row['hold_until']) <= $now) $due[] = array($effective, $id, $row);
        }
        usort($due, function ($a, $b) {
            return $a[0] <=> $b[0] ?: $a[1] <=> $b[1];
        });
        return array('slots' => $slots, 'changes' => $changes, 'due' => array_map(function ($entry) {
            return $entry[2];
        }, $due));
    }

    /*
     * The Check Watch List task: archives the entries now live in Domains and checks the due entries, within its time and
     * lookup limits. $run_now is Run Now on the Task Scheduler page, which takes at most RUN_NOW_BUDGET seconds. Returns
     * a summary (array of counts) for the log. It does nothing while the Watch List is turned off in System Settings
     * (WatchList::isEnabled()), so the entries wait unchanged until it's turned back on, and its summary then says so
     * ('off' => true).
     */
    public function run($run_now = false)
    {
        $summary = array('lookups' => 0, 'archived' => 0, 'schedule' => 0);
        if (!WatchList::isEnabled(true)) return $summary + array('off' => true);

        $started = microtime(true);
        $budget = $run_now ? self::RUN_NOW_BUDGET : self::WATCH_BUDGET;
        $max_execution_time = (int) ini_get('max_execution_time');
        if ($max_execution_time > 0) {
            // keep a quarter of what's left (at most a minute) for the rest of the run, as the Domain Queue does
            $remaining = $max_execution_time - ($started - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $started));
            $budget = min($budget, max(0, $remaining - min(60, $remaining / 4)));
        }
        $deadline = $started + $budget;
        $left = function () use ($deadline) {
            return $deadline - microtime(true);
        };

        // Every lock wait in the run is short, so a row an edit holds can't take the run's time (a save that times out is
        // logged and its entry is checked again next run)
        $previous_wait = $this->setLockWait(self::LOCK_WAIT);
        try {
            return $this->runPlanned($summary, $deadline, $left);
        } finally {
            $this->setLockWait($previous_wait);
        }
    }

    /*
     * Whether a write in this run met another request's and was rolled back: a check's save or an archive (the entry is
     * checked or archived again on a later run), or a schedule change (worked out again next run). The rest of the run
     * goes on, so this is reported after it rather than ending it.
     */
    public function hadConflict()
    {
        return $this->had_conflict;
    }

    /*
     * Whether $e is a write that met another request's (see domainmod_restore_is_conflict()), noted for hadConflict().
     * Without the restore gate loaded (the unit tests), the same two errors are read here: a deadlock (1213) and a row
     * changed since it was read (1020).
     */
    protected function noteConflict(\Throwable $e)
    {
        $conflict = function_exists('domainmod_restore_is_conflict') ? domainmod_restore_is_conflict($e)
            : $e instanceof \PDOException && in_array((int) ($e->errorInfo[1] ?? 0), array(1020, 1213), true);
        if ($conflict) $this->had_conflict = true;
        return $conflict;
    }

    // The run itself, once its time is worked out (see run())
    protected function runPlanned(array $summary, $deadline, callable $left)
    {
        $moved = $this->movedCandidates();
        $plan = $this->plan($this->activeRows(), $moved, $this->now());
        $sweep_first = $this->readTurn();
        $state = array('servers' => array(), 'failed' => array());
        $schedule_saved = false;
        $sweep = array('done' => 0, 'time' => 0.0, 'ids' => array_keys($moved));
        $due_left = count($plan['due']);

        // On a sweep turn the first archive always goes first, then more while the first lookup keeps its time
        if ($sweep_first && $moved) $this->sweep($moved, $sweep, $left, true, $due_left > 0, $summary);

        foreach ($plan['due'] as $planned) {
            if ($summary['lookups'] >= self::WATCH_BATCH || $left() < self::MIN_LOOKUP_TIME) break;
            $due_left--;
            // The whole entry, read now (its save checks it hasn't changed since)
            $row = $this->entry((int) $planned['id']);
            // An entry saved since the plan (such as by Check Now) is worked out again on the next run
            if (!$row || (int) $row['archived'] === 1 || (int) $row['revision'] !== (int) $planned['revision']) continue;
            $answer = $this->lookup($row, $deadline, $state);
            if ($answer['kind'] === 'skipped') {
                // A server that failed earlier in the run holds the entry for an hour, except a confirmation, which is
                // simply left due for the next run, and a server's per-run limit leaves every entry due
                if ($answer['result'] === 'server_failed' && $row['pending_stage'] === '') {
                    $this->saveLogged($row, array('update' => array('hold_until' => self::stamp($this->now() + self::HOLD)),
                        'records' => array(), 'outcome' => 'held'));
                }
                continue;
            }
            $summary['lookups']++;
            $this->saveLogged($row, $this->decide($row, $answer, $this->now(), isset($plan['slots'][(int) $row['id']])));
            if (!$schedule_saved) {
                $summary['schedule'] = $this->saveSchedule($plan['changes'], min(self::SCHEDULE_BUDGET, $left()));
                $schedule_saved = true;
            }
        }

        if (!$schedule_saved) $summary['schedule'] = $this->saveSchedule($plan['changes'], min(self::SCHEDULE_BUDGET, $left()));

        // Then the archives use only the time left over (the first one goes whatever is left only when no lookup was due,
        // so nothing competes with it)
        if ($moved) $this->sweep($moved, $sweep, $left, !$plan['due'], false, $summary);

        if ($moved && $plan['due']) $this->writeTurn(!$sweep_first);

        return $summary;
    }

    // A scheduled save (user 0, action watch_check), whose failure is logged so the run goes on to the next entry
    protected function saveLogged(array $row, array $decision)
    {
        try {
            return $this->save($row, $decision, 0, 'watch_check');
        } catch (\Throwable $e) {
            $this->noteConflict($e);
            $this->log->warning('Unable to save a Watch List check', array('Entry' => (int) $row['id'], 'Error' => $e->getMessage()));
            return 'failed';
        }
    }

    /*
     * Archives entries now live in Domains, each in its own transaction, up to SWEEP_BATCH in the run and within
     * SWEEP_BUDGET seconds beyond the first. With $first_forced, the first archive is made however little time is left. With
     * $keep_lookup, it stops once the time left would fall below MIN_LOOKUP_TIME plus 10 seconds (the lock waits and the
     * archive's own save), so the first lookup always keeps its time.
     */
    protected function sweep(array $moved, array &$sweep, callable $left, $first_forced, $keep_lookup, array &$summary)
    {
        while ($sweep['ids'] && $sweep['done'] < self::SWEEP_BATCH) {
            $forced = $first_forced && $sweep['done'] === 0;
            if (!$forced) {
                if ($sweep['time'] >= self::SWEEP_BUDGET) return;
                if ($left() < ($keep_lookup ? self::MIN_LOOKUP_TIME + 10 : 4 * self::LOCK_WAIT + 2)) return;
            }
            $id = array_shift($sweep['ids']);
            $began = microtime(true);
            try {
                if ($this->archiveMoved($id, $moved[$id], 0, true) === 'moved') $summary['archived']++;
            } catch (\Throwable $e) {
                $this->noteConflict($e);
                $this->log->warning('Unable to archive a watched domain that is now in Domains', array('Entry' => $id,
                    'Error' => $e->getMessage()));
            }
            $sweep['done']++;
            if (!$forced) $sweep['time'] += microtime(true) - $began;
            $first_forced = false;
        }
    }

    // Whether this run's sweep goes first (temp/watch-turn holds '1' when the lookups go first, and a missing file means
    // the sweep goes first)
    protected function readTurn()
    {
        $value = @file_get_contents(DIR_TEMP . '/watch-turn');
        return $value !== '1';
    }

    protected function writeTurn($lookups_first)
    {
        @file_put_contents(DIR_TEMP . '/watch-turn', $lookups_first ? '0' : '1');
    }

    /*
     * Saves the run's schedule changes (see plan()), one entry at a time, each only while the entry still has the
     * revision it was worked out from (so a newer save, such as a Check Now, is never overwritten), with a short lock
     * wait (an entry an edit holds is skipped and worked out again next run), within $budget seconds (SCHEDULE_BUDGET at
     * most, and never past the run's own time). Returns how many it saved.
     */
    public function saveSchedule(array $changes, $budget = self::SCHEDULE_BUDGET)
    {
        if (!$changes || $budget <= 0) return 0;
        $saved = 0;
        $began = microtime(true);
        $previous = $this->setLockWait(self::LOCK_WAIT);
        try {
            $stmt = $this->pdo->prepare("
                UPDATE watch_domains
                SET next_due = :next_due
                WHERE id = :id
                  AND revision = :revision
                  AND archived = 0");
            foreach ($changes as $id => $change) {
                // Each update's lock wait fits in what's left, so a held row can't carry the writes past the budget
                $remaining = $budget - (microtime(true) - $began);
                if ($remaining < 1) break;
                $this->pdo->exec("SET SESSION innodb_lock_wait_timeout = " . (int) min(self::LOCK_WAIT, floor($remaining)));
                try {
                    $stmt->bindValue('next_due', $change[0], \PDO::PARAM_STR);
                    $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
                    $stmt->bindValue('revision', (int) $change[1], \PDO::PARAM_INT);
                    $stmt->execute();
                    $saved += $stmt->rowCount();
                } catch (\PDOException $e) {
                    // A row another writer holds (error 1205), or a write that met another request's (a deadlock, noted
                    // for hadConflict()), waits for the next run
                    if ((int) ($e->errorInfo[1] ?? 0) !== 1205 && !$this->noteConflict($e)) throw $e;
                }
            }
        } finally {
            $this->setLockWait($previous);
        }
        return $saved;
    }

    // Sets the session's lock wait (seconds) and returns the one it had
    protected function setLockWait($seconds)
    {
        $previous = (int) $this->pdo->query("SELECT @@SESSION.innodb_lock_wait_timeout")->fetchColumn();
        $this->pdo->exec("SET SESSION innodb_lock_wait_timeout = " . (int) $seconds);
        return $previous > 0 ? $previous : 50;
    }

    /*
     * One lookup, as an answer for decide(): kind (record, none, incomplete, rate_limited, failure, no_source, or
     * skipped), source (rdap or whois), result (a short code for the entry's last_result), request_sent (whether the
     * request for the domain itself started), started (when the lookup started, a Unix time), and for a record the
     * details. RDAP comes first, then WHOIS only when the TLD has no RDAP server. $state holds the run's per-server
     * counts and the servers that failed or limited requests (skipped for the rest of the run).
     */
    public function lookup(array $row, $deadline, array &$state)
    {
        $answer = array('kind' => 'skipped', 'source' => '', 'result' => 'no_time', 'request_sent' => false,
            'started' => $this->now(), 'expiry_date' => '', 'expiry_source' => '', 'registration_date' => '',
            'deletion_date' => '', 'registrar_name' => '', 'registrar_iana_id' => '', 'registrar_url' => '',
            'statuses' => array());
        $state += array('servers' => array(), 'failed' => array());
        // Each watched domain is its own attempt, so a bootstrap download another's deadline cut off is tried again
        Rdap::newAttempt();
        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return $answer;

        $ascii = Domain::forApi($row['domain']);
        $rdap = $this->rdap();

        // Without IANA's bootstrap file nothing can be looked up yet: a failure, but never a definite "no source". A
        // download the deadline cut off failed nothing, so the entry is simply left for a later run
        if (!$rdap->hasBootstrap($deadline)) {
            return Rdap::bootstrapCutOff() ? $answer : array('kind' => 'failure', 'source' => 'rdap', 'result' => 'no_bootstrap') + $answer;
        }

        $server = $rdap->serverFor($ascii, $deadline);
        // An expired copy that doesn't name the TLD, after a cut-off download, can't say the TLD has no RDAP server
        if ($server === '' && Rdap::downloadCutOff()) return $answer;
        if ($server !== '') {
            $key = 'rdap:' . strtolower((string) parse_url($server, PHP_URL_HOST));
            if (isset($state['failed'][$key])) return array('result' => 'server_failed') + $answer;
            if (($state['servers'][$key] ?? 0) >= self::RDAP_PER_SERVER) return array('result' => 'server_limit') + $answer;
            if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return $answer;
            $state['servers'][$key] = ($state['servers'][$key] ?? 0) + 1;

            $answer['started'] = $this->now();
            $answer['source'] = 'rdap';
            $lookup = $rdap->lookup($row['domain'], $deadline);
            // Stopped by the deadline before the registry was asked: nothing failed
            if ($rdap->deadline_stopped) {
                // nothing was sent, so it doesn't use up the server's share
                $state['servers'][$key]--;
                return $answer;
            }
            $answer['request_sent'] = $rdap->domain_request_sent;
            return $this->fromRdap($lookup, $answer, $state, $key);
        }

        // No RDAP server for the TLD, so WHOIS. "No source" is definite only when the RDAP bootstrap file is fresh and
        // IANA was asked for the WHOIS server: a stale file (kept after a failed refresh) may be missing a new server
        $stale = Rdap::$bootstrap_stale;
        $whois = $this->whois();
        list($found, $whois_server) = $whois->serverFor($ascii, $deadline);
        if (!$found) return $whois->discovery_cut_off ? $answer : array('kind' => 'failure', 'source' => 'whois', 'result' => 'no_whois_info') + $answer;
        if ($whois_server === '') {
            return array('kind' => $stale ? 'failure' : 'no_source', 'source' => 'whois',
                'result' => $stale ? 'stale_bootstrap' : 'no_source') + $answer;
        }

        $key = 'whois:' . $whois_server;
        if (isset($state['failed'][$key])) return array('result' => 'server_failed') + $answer;
        if (($state['servers'][$key] ?? 0) >= DomainQueue::WHOIS_PER_SERVER) return array('result' => 'server_limit') + $answer;
        if ($deadline - microtime(true) < self::MIN_LOOKUP_TIME) return $answer;
        $state['servers'][$key] = ($state['servers'][$key] ?? 0) + 1;

        $answer['started'] = $this->now();
        $answer['source'] = 'whois';
        $lookup = $whois->lookup($row['domain'], $deadline);
        if ($whois->deadline_stopped) {
            $state['servers'][$key]--;
            return $answer;
        }
        $answer['request_sent'] = $whois->domain_request_sent;
        return $this->fromWhois($lookup, $answer, $state, $key);
    }

    // An RDAP lookup's result as an answer (see lookup()), read by its status first and its status read only after that
    public function fromRdap(array $lookup, array $answer, array &$state, $key = '')
    {
        $answer['source'] = 'rdap';
        switch ($lookup['status']) {
            case 'ok':
                if (!in_array($lookup['status_read'], array('found', 'none'), true)) {
                    return array('kind' => 'incomplete', 'result' => 'incomplete') + $answer;
                }
                return array('kind' => 'record', 'result' => 'ok',
                    'expiry_date' => (string) $lookup['expiry_date'], 'expiry_source' => (string) ($lookup['expiry_source'] ?? ''),
                    'registration_date' => (string) ($lookup['registration_date'] ?? ''),
                    'deletion_date' => (string) ($lookup['deletion_date'] ?? ''),
                    'registrar_name' => (string) $lookup['registrar_name'], 'registrar_iana_id' => (string) $lookup['registrar_iana_id'],
                    'registrar_url' => (string) $lookup['registrar_url'],
                    'statuses' => $lookup['status_read'] === 'found' ? DomainStatus::ordered($lookup['statuses']) : array()) + $answer;
            case 'not_found':
                // A 404 reached through a redirect says nothing about the name, and the redirect is broken, so the TLD's
                // server is paused for the run
                if (!empty($lookup['redirected'])) {
                    if ($key !== '') $state['failed'][$key] = true;
                    return array('kind' => 'failure', 'result' => 'redirect_404') + $answer;
                }
                return array('kind' => 'none', 'result' => 'not_found') + $answer;
            case 'rate_limited':
                // A rate limit pauses the TLD's server for the run, even through a redirect (it may be the same host)
                if ($key !== '') $state['failed'][$key] = true;
                return array('kind' => 'rate_limited', 'result' => 'rate_limited') + $answer;
            case 'mismatch':
                return array('kind' => 'failure', 'result' => 'mismatch') + $answer;
            case 'no_server':
                return array('kind' => 'failure', 'result' => 'no_server') + $answer;
        }
        // An error after the request started pauses the TLD's server for the run (a redirect's host can't be told apart
        // from the registry's, so that counts too), but a time guard before it doesn't
        if ($key !== '' && $answer['request_sent']) $state['failed'][$key] = true;
        return array('kind' => 'failure', 'result' => 'error') + $answer;
    }

    /*
     * A WHOIS lookup's result as an answer. An ok counts as a record only when it holds at least one of the expiry date,
     * the registration date, the registrar's name, or a name server (anything else, such as a banner, is unreadable)
     */
    public function fromWhois(array $lookup, array $answer, array &$state, $key = '')
    {
        $answer['source'] = 'whois';
        if ($lookup['status'] === 'ok') {
            if (($lookup['expiry_date'] ?? '') === '' && ($lookup['registration_date'] ?? '') === ''
                && ($lookup['registrar_name'] ?? '') === '' && empty($lookup['name_servers'])) {
                return array('kind' => 'failure', 'result' => 'unreadable') + $answer;
            }
            return array('kind' => 'record', 'result' => 'ok', 'expiry_date' => (string) $lookup['expiry_date'],
                'expiry_source' => $lookup['expiry_date'] !== '' ? 'whois' : '',
                'registration_date' => (string) ($lookup['registration_date'] ?? ''),
                'registrar_name' => (string) $lookup['registrar_name'],
                'registrar_iana_id' => (string) ($lookup['registrar_iana_id'] ?? ''), 'statuses' => array()) + $answer;
        }
        if ($lookup['status'] === 'not_found') return array('kind' => 'none', 'result' => 'not_found') + $answer;
        if ($lookup['status'] === 'rate_limited') {
            if ($key !== '') $state['failed'][$key] = true;
            return array('kind' => 'rate_limited', 'result' => 'rate_limited') + $answer;
        }
        if ($lookup['status'] === 'no_server') return array('kind' => 'no_source', 'result' => 'no_source') + $answer;
        if ($key !== '' && $answer['request_sent']) $state['failed'][$key] = true;
        return array('kind' => 'failure', 'result' => 'error') + $answer;
    }

    /*
     * What an answer means for an entry ($row, every column of watch_domains), with no database: array('update' =>
     * columns to set, 'records' => the Change History rows (each array(field, old, new, via, old display, new display)),
     * 'outcome' => accepted, baseline, provisional, confirming, given_up, held, failed). $now is the current time (Unix),
     * and $fast whether the entry holds a fast slot. See the class comment and the plan's rules.
     */
    public function decide(array $row, array $answer, $now, $fast = false)
    {
        $started = (float) ($answer['started'] ?? $now);
        $kind = $answer['kind'];
        $source = (string) $answer['source'];
        $u = array('last_attempt' => self::stamp($started), 'last_source' => $source,
            'last_result' => substr((string) $answer['result'], 0, 30));
        $clear_pending = array('pending_stage' => '', 'pending_source' => '', 'pending_since' => self::UNSET_TIME);
        $confirmed = false;

        if ($row['pending_stage'] !== '') {
            $same_source = $source === $row['pending_source'];
            if (in_array($kind, array('record', 'none'), true) && $same_source) {
                // Agreeing is about the move itself (a record found, or no record), not the exact stage
                $confirmed = ($row['pending_stage'] === 'not_registered') === ($kind === 'none');
                $u += $clear_pending;
            } elseif ($kind === 'no_source') {
                // A definite answer that the TLD has no source ends the pending change at once
                $u += $clear_pending;
            } elseif ($kind === 'none' && $source === 'rdap' && (int) $row['has_baseline'] === 0) {
                // A first answer still waiting for its WHOIS confirmation, now answered by RDAP (the TLD has an RDAP
                // server now): it ends the pending change and becomes the baseline at once, as below
                $u += $clear_pending;
            } else {
                // A confirming lookup that failed, was rate limited or incomplete, or came from the other source: the
                // change stays pending (never a hold or a counted failure), until a lookup that actually sent its
                // request CONFIRM_TIMEOUT after the change was first seen
                if ($kind === 'incomplete') $u['last_status_read'] = 'incomplete';
                if (!empty($answer['request_sent']) && $started >= self::time($row['pending_since']) + self::CONFIRM_TIMEOUT) {
                    $u += $clear_pending;
                    // An entry waiting for a confirmation holds no fast slot, so one that would want it gets its fast
                    // interval (the next run's plan pushes it to daily when it doesn't win a slot)
                    $u['next_due'] = self::stamp($started + self::interval($row, $started, $fast || self::wantsFast($row, $started)));
                    return array('update' => $u, 'records' => array(), 'outcome' => 'given_up');
                }
                $u['next_due'] = self::stamp($started + self::CONFIRM_GAP);
                return array('update' => $u, 'records' => array(), 'outcome' => 'confirming');
            }
        }

        if ($kind === 'rate_limited') {
            $u['hold_until'] = self::stamp($now + self::HOLD);
            return array('update' => $u, 'records' => array(), 'outcome' => 'held');
        }
        if ($kind === 'incomplete') {
            $u['hold_until'] = self::stamp($now + self::HOLD);
            $u['last_status_read'] = 'incomplete';
            return array('update' => $u, 'records' => array(), 'outcome' => 'held');
        }
        if ($kind === 'failure' || $kind === 'no_source') {
            $failures = (int) $row['failures'] + 1;
            $u['failures'] = $failures;
            $u['next_due'] = self::stamp($started + self::backoff($row, $failures, $started, $fast));
            return array('update' => $u, 'records' => array(), 'outcome' => 'failed');
        }

        // A record found, or no record
        $today = gmdate('Y-m-d', (int) $started);
        $whois = $source === 'whois';
        $old_stage = (string) $row['stage'];
        $baseline = (int) $row['has_baseline'] === 0;
        $expiry = $kind === 'record' && $answer['expiry_date'] !== '' ? (string) $answer['expiry_date'] : self::UNSET_DATE;
        if ($kind === 'none') {
            $new_stage = 'not_registered';
        } else {
            $new_stage = WatchLifecycle::stage($whois ? array() : $answer['statuses'], $expiry, true, $today);
            // A WHOIS answer has no status codes, so it never moves an entry out of a stage that came from them
            if ($whois && in_array($old_stage, WatchLifecycle::STATUS_STAGES, true)) $new_stage = $old_stage;
        }

        // A move into or out of "Not registered" waits for a confirming lookup from the same source, except a first
        // answer of the watching period that RDAP has no record, which is the registry's own answer and becomes the
        // baseline at once (a first WHOIS "no record" still waits, since it comes from reading the reply's free text)
        $crossing = ($old_stage === 'not_registered') !== ($new_stage === 'not_registered');
        $rdap_baseline_none = $baseline && $kind === 'none' && $source === 'rdap';
        if ($crossing && !$confirmed && !$rdap_baseline_none) {
            $u['pending_stage'] = $new_stage;
            $u['pending_source'] = $source;
            $u['pending_since'] = self::stamp($started);
            // It's an answer, so a back-off from earlier failures ends
            $u['failures'] = 0;
            $u['next_due'] = self::stamp($started + self::CONFIRM_GAP);
            return array('update' => $u, 'records' => array(), 'outcome' => 'provisional');
        }

        $records = array();
        $u += array('has_baseline' => 1, 'failures' => 0, 'last_success' => self::stamp($started));
        // A record's answer is a complete status read (a WHOIS one has no codes to read), while "no record" reads no
        // status at all, so it leaves the marker as it was
        if ($kind === 'record') $u['last_status_read'] = 'complete';

        if ($kind === 'none') {
            // A confirmed drop clears what the old registration had (not recorded: the stage change says what happened)
            $u += array('expiry_date' => self::UNSET_DATE, 'registration_date' => self::UNSET_DATE,
                'deletion_date' => self::UNSET_DATE, 'expiry_source' => '', 'registrar_name' => '', 'registrar_iana_id' => '',
                'registrar_url' => '', 'statuses' => '', 'grace_open' => 0, 'grace_base_expiry' => self::UNSET_DATE,
                'grace_base_source' => '');
        } else {
            $this->decideRegistrar($row, $answer, $baseline, $source, $u, $records);
            $this->decideDates($row, $answer, $baseline, $source, $expiry, $u, $records);
            if (!$whois) {
                $codes = DomainStatus::toColumn($answer['statuses']);
                $old_codes = DomainStatus::fromColumn($row['statuses']);
                $new_codes = DomainStatus::fromColumn($codes);
                sort($old_codes);
                sort($new_codes);
                if (!$baseline && $old_codes !== $new_codes) $records[] = array('statuses', (string) $row['statuses'], $codes, $source, '', '');
                $u['statuses'] = $codes;
            }
        }

        // The stage, when it was first seen, and the check before it (for the drop estimate)
        if ($new_stage !== $old_stage || $baseline) {
            if ($confirmed && $new_stage === $row['pending_stage']) {
                $since = (string) $row['pending_since'];
                $prev = (string) $row['stage_last_seen'];
            } elseif ($confirmed) {
                $since = self::stamp($started);
                $prev = (string) $row['pending_since'];
            } else {
                $since = self::stamp($started);
                $prev = $baseline ? self::UNSET_TIME : (string) $row['stage_last_seen'];
            }
            $u['stage'] = $new_stage;
            $u['stage_since'] = $since;
            $u['prev_check'] = $prev;
            if (!$baseline && $new_stage !== $old_stage) $records[] = array('stage', $old_stage, $new_stage, $source, '', '');
        }
        // A WHOIS answer can't show that a status stage still holds
        if (!$whois || !in_array($new_stage, WatchLifecycle::STATUS_STAGES, true)) $u['stage_last_seen'] = self::stamp($started);

        $merged = array_merge($row, $u);
        list($u['drop_from'], $u['drop_to'], $u['drop_basis']) = WatchLifecycle::dropWindow($new_stage, $row['domain'],
            (int) $merged['grace_open'] === 1 ? $merged['grace_base_expiry'] : self::UNSET_DATE, $merged['stage_since'],
            $merged['prev_check']);
        $merged = array_merge($merged, $u);
        $u['next_due'] = self::stamp($started + self::interval($merged, $started, $fast));

        return array('update' => $u, 'records' => $records, 'outcome' => $baseline ? 'baseline' : 'accepted');
    }

    /*
     * The registrar from a record: stored as RdapAssets stores it (a name that doesn't fit is shortened, an http:// or
     * too-long URL and a non-digit IANA ID are left out), recorded as a change only when both sides are known and the
     * IANA ID differs (or, when either side has no IANA ID, the name differs once trimmed and compared without case). An
     * answer without a registrar's name leaves the stored one.
     */
    protected function decideRegistrar(array $row, array $answer, $baseline, $source, array &$u, array &$records)
    {
        $assets = $this->assets = $this->assets ?? new RdapAssets();
        $name = $answer['registrar_name'] !== '' ? $assets->storedName($answer['registrar_name']) : '';
        if ($name === '') return;
        $iana_id = (string) $answer['registrar_iana_id'];
        $iana_id = ctype_digit($iana_id) && strlen($iana_id) <= 20 ? $iana_id : '';
        $url = $assets->storedUrl((string) ($answer['registrar_url'] ?? ''));

        $old_name = (string) $row['registrar_name'];
        $old_id = (string) $row['registrar_iana_id'];
        if (!$baseline && $old_name !== '') {
            $unsanitize = new Unsanitize();
            $differs = ($old_id !== '' && $iana_id !== '') ? $old_id !== $iana_id
                : mb_strtolower(trim((string) $unsanitize->text($old_name)), 'UTF-8') !== mb_strtolower(trim((string) $unsanitize->text($name)), 'UTF-8');
            if ($differs) $records[] = array('registrar', $old_name, $name, $source, $old_id, $iana_id);
        }
        $u['registrar_name'] = $name;
        $u['registrar_iana_id'] = $iana_id;
        $u['registrar_url'] = $url;
    }

    /*
     * The dates from a record, and an expiry_date change when a known date moves. An expiry date is compared only with
     * one from the same source (registrar, registry, or whois). During the auto-renew grace period a later date isn't
     * recorded: the date held when autoRenewPeriod first appeared is kept, and on the first complete RDAP read without it
     * that has a date from the same source, a later date is recorded as one change from it, unless that read shows the
     * domain being deleted (then the comparison closes with nothing recorded). A date is never compared while the domain
     * is being deleted. A WHOIS answer never opens or closes that comparison.
     */
    protected function decideDates(array $row, array $answer, $baseline, $source, $expiry, array &$u, array &$records)
    {
        $whois = $source === 'whois';
        $new_source = WatchLifecycle::isUnset($expiry) ? '' : ($whois ? 'whois' : (string) $answer['expiry_source']);
        $old_expiry = (string) $row['expiry_date'];
        $old_source = (string) $row['expiry_source'];
        $grace_now = !$whois && in_array('autoRenewPeriod', $answer['statuses'], true);
        $grace_open = (int) $row['grace_open'] === 1;
        // A domain being deleted (redemption, restore pending, or pending delete) wasn't renewed, whatever date the
        // registry shows, so its date isn't compared, and a grace period that ends in deletion closes its comparison
        $deleting = !$whois && array_intersect(DomainStatus::DELETION_CODES, $answer['statuses']);

        if ($deleting) {
            if ($grace_open) $u += array('grace_open' => 0, 'grace_base_expiry' => self::UNSET_DATE, 'grace_base_source' => '');
        } elseif ($grace_now) {
            // A grace period opens a comparison with the date known before it (unknown on a baseline), and one already open
            // keeps its date
            if (!$grace_open) {
                $u['grace_open'] = 1;
                $u['grace_base_expiry'] = $baseline ? self::UNSET_DATE : $old_expiry;
                $u['grace_base_source'] = $baseline || WatchLifecycle::isUnset($old_expiry) ? '' : $old_source;
            }
        } elseif ($grace_open && !$whois) {
            $base = (string) $row['grace_base_expiry'];
            $base_source = (string) $row['grace_base_source'];
            if (!WatchLifecycle::isUnset($expiry)) {
                if (WatchLifecycle::isUnset($base) || $base_source === 'whois') {
                    // A comparison that can never be made closes on the first complete read with a date
                    $u += array('grace_open' => 0, 'grace_base_expiry' => self::UNSET_DATE, 'grace_base_source' => '');
                } elseif ($new_source === $base_source) {
                    if ($expiry > $base) $records[] = array('expiry_date', $base, $expiry, $source, '', '');
                    $u += array('grace_open' => 0, 'grace_base_expiry' => self::UNSET_DATE, 'grace_base_source' => '');
                }
                // A date from the other source waits for one from the same source
            }
        } elseif (!$grace_open && !$baseline && !WatchLifecycle::isUnset($old_expiry) && !WatchLifecycle::isUnset($expiry)
            && $old_source === $new_source && $old_expiry !== $expiry) {
            $records[] = array('expiry_date', $old_expiry, $expiry, $source, '', '');
        }

        $u['expiry_date'] = $expiry;
        $u['expiry_source'] = $new_source;
        $u['registration_date'] = $answer['registration_date'] !== '' ? (string) $answer['registration_date'] : self::UNSET_DATE;
        $u['deletion_date'] = !$whois && ($answer['deletion_date'] ?? '') !== '' ? (string) $answer['deletion_date'] : self::UNSET_DATE;
    }

    /*
     * Saves a decision for an entry, in its own transaction: the entry is locked and read again, and the decision is
     * thrown away when the entry was changed (its revision or name), archived, or deleted since $row was read. When its
     * name is now live in Domains (read with a shared lock through the domain index, after the entry's lock), the entry
     * is archived as moved instead. Otherwise the decision's columns are written with the revision raised, and its
     * history rows recorded (source watch_list, the action given). Returns the outcome: saved, stale, or moved.
     */
    public function save(array $row, array $decision, $user_id, $action)
    {
        $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->pdo->beginTransaction();
        try {
            $current = $this->lockEntry((int) $row['id']);
            if (!$current || (int) $current['archived'] === 1 || (int) $current['revision'] !== (int) $row['revision']
                || (string) $current['domain'] !== (string) $row['domain']) {
                $this->pdo->rollBack();
                return 'stale';
            }

            $live_id = WatchList::liveDomainId($this->pdo, $current['domain'], true);
            if ($live_id > 0) {
                $this->archiveLocked($current, $live_id, $user_id);
                $this->pdo->commit();
                return 'moved';
            }

            $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, $action);
            $update = $decision['update'];
            if ($update) {
                $columns = array();
                foreach (array_keys($update) as $column) {
                    if (!preg_match('/^[a-z_]+$/', $column)) throw new \LogicException('Unexpected watch_domains column');
                    $columns[] = '`' . $column . '` = :' . $column;
                }
                $stmt = $this->pdo->prepare("
                    UPDATE watch_domains
                    SET " . implode(', ', $columns) . ",
                        revision = revision + 1
                    WHERE id = :id");
                foreach ($update as $column => $value) {
                    $stmt->bindValue($column, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
                }
                $stmt->bindValue('id', (int) $row['id'], \PDO::PARAM_INT);
                $stmt->execute();
            }
            foreach ($decision['records'] as $record) {
                list($field, $old, $new, $via, $old_display, $new_display) = $record;
                $op->record('watch', (int) $row['id'], $field, $old, $new, $via, $old_display, $new_display);
            }
            $op->finish();
            $this->pdo->commit();
            return 'saved';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    // An entry's every column, locked (FOR UPDATE), or null
    protected function lockEntry($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM watch_domains WHERE id = :id FOR UPDATE");
        $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $row ?: null;
    }

    // An entry's every column, read without a lock, or null
    public function entry($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM watch_domains WHERE id = :id");
        $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $row ?: null;
    }

    /*
     * Archives an entry whose name is live in Domains, in its own transaction (the sweep and Check Now), with the
     * session's lock wait at LOCK_WAIT seconds when $short_waits, so a row an edit holds is skipped rather than waited
     * on. Returns moved, or skipped when it's no longer active or no longer live.
     */
    public function archiveMoved($id, $domain_id, $user_id, $short_waits = false)
    {
        $previous = $short_waits ? $this->setLockWait(self::LOCK_WAIT) : null;
        try {
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();
            try {
                $current = $this->lockEntry((int) $id);
                $live_id = $current && (int) $current['archived'] === 0
                    ? WatchList::liveDomainId($this->pdo, $current['domain'], true) : 0;
                if ($live_id === 0) {
                    $this->pdo->rollBack();
                    return 'skipped';
                }
                $this->archiveLocked($current, $live_id, $user_id);
                $this->pdo->commit();
                return 'moved';
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1205) return 'skipped';
                throw $e;
            }
        } finally {
            if ($previous !== null) $this->setLockWait($previous);
        }
    }

    /*
     * The archive itself, inside the caller's transaction on the locked entry: archived with moved_domain_id set (raising
     * the revision, so a lookup in flight is thrown away), recorded as an archived change and a moved_domain_id change
     * (action watch_moved), with any pending confirmation and grace comparison cleared
     */
    protected function archiveLocked(array $current, $live_id, $user_id)
    {
        $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, 'watch_moved');
        $before = $op->capture('watch', array((int) $current['id']), array('archived'));
        $stmt = $this->pdo->prepare("
            UPDATE watch_domains
            SET archived = 1,
                moved_domain_id = :moved_domain_id,
                pending_stage = '',
                pending_source = '',
                pending_since = '1970-01-01 00:00:00',
                grace_open = 0,
                grace_base_expiry = '1970-01-01',
                grace_base_source = '',
                revision = revision + 1,
                update_time = :update_time
            WHERE id = :id");
        $stmt->bindValue('moved_domain_id', (int) $live_id, \PDO::PARAM_INT);
        $stmt->bindValue('update_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue('id', (int) $current['id'], \PDO::PARAM_INT);
        $stmt->execute();
        $op->compare($before);
        $op->record('watch', (int) $current['id'], 'moved_domain_id', (string) (int) $current['moved_domain_id'], (string) (int) $live_id);
        $op->finish();
    }

    // The named lock Check Now holds while it looks up, built from a hash of the database name as the task locks are
    public static function checkLockName()
    {
        return 'domainmod_watch_check_' . substr(md5(DB_NAME), 0, 12);
    }

    /*
     * Check Now on the edit page: archives the entry first when its name is live in Domains, shows the stored answer
     * while it's held, within CHECK_NOW_GAP of its last answer (once its watching period has a baseline), or before a
     * pending change's confirming lookup is due, and otherwise looks it up once (within the session's limits, and only
     * one Check Now at a time on the install). Returns array(message type: success, info, warning, or danger, message).
     */
    public function checkNow($id, $user_id)
    {
        $row = $this->entry($id);
        if (!$row) return array('danger', _('That watched domain no longer exists.'));
        if ((int) $row['archived'] === 1) return array('info', _('This watched domain is archived, so it isn\'t checked. Unarchive it to check it again.'));

        $time = new Time();
        $live_id = WatchList::liveDomainId($this->pdo, $row['domain']);
        // (the archive checks again under its locks, and goes on to the check when the domain is no longer live)
        if ($live_id > 0) {
            if ($this->archiveMoved((int) $row['id'], $live_id, $user_id, true) === 'moved') {
                return array('info', sprintf(_('This domain is in %s now, so watching it has stopped.'), SOFTWARE_TITLE));
            }
            // Still live: another request held the entry (or changed it) during the archive, so the domain isn't looked up
            if (WatchList::liveDomainId($this->pdo, $row['domain']) > 0) {
                return array('warning', _('This watched domain is being changed. Please try again in a moment.'));
            }
        }

        $now = $this->now();
        if ($row['pending_stage'] !== '') {
            $confirm_at = self::time($row['last_attempt']) + self::CONFIRM_GAP;
            if ($now < $confirm_at) {
                return array('info', sprintf(_('A change was found, and it will be checked again at %s before it\'s recorded.'),
                    $time->toUserTimezone(self::stamp($confirm_at), 'Y-m-d H:i')));
            }
        } else {
            if (self::time($row['hold_until']) > $now) {
                return array('info', sprintf(_("This domain can't be checked again until %s."),
                    $time->toUserTimezone($row['hold_until'], 'Y-m-d H:i')));
            }
            if ((int) $row['has_baseline'] === 1 && $now - self::time($row['last_success']) < self::CHECK_NOW_GAP) {
                return array('info', sprintf(_('This domain was checked at %s, so these details are up to date.'),
                    $time->toUserTimezone($row['last_success'], 'Y-m-d H:i')));
            }
        }

        $wait = $this->throttle((int) $row['id']);
        if ($wait !== '') return array('warning', $wait);

        $stmt = $this->pdo->prepare("SELECT GET_LOCK(:lock_name, 0)");
        $stmt->bindValue('lock_name', self::checkLockName(), \PDO::PARAM_STR);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        if (!$got) return array('warning', _('Another check is running. Please try again in a moment.'));

        try {
            $state = array();
            $answer = $this->lookup($row, microtime(true) + self::CHECK_NOW_BUDGET, $state);
            if ($answer['kind'] === 'skipped') return array('danger', _('The lookup ran out of time. Please try again later.'));
            // Whether it holds a fast slot, so its next check (or retry) follows its fast interval
            $fast = isset(self::fastSlots($this->activeRows(), $this->movedCandidates(), $now)[(int) $row['id']]);
            $decision = $this->decide($row, $answer, $this->now(), $fast);
            // The save's lock waits are short, as the run's are, so a row another request holds isn't waited on while
            // this request holds the session and its locks: the answer is dropped, and the user tries again
            $previous_wait = $this->setLockWait(self::LOCK_WAIT);
            try {
                $saved = $this->save($row, $decision, $user_id, 'watch_check_now');
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1205) throw $e;
                $saved = 'busy';
            } finally {
                $this->setLockWait($previous_wait);
            }
        } finally {
            $stmt = $this->pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', self::checkLockName(), \PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
        }

        if ($saved === 'busy') return array('warning', _('This watched domain is being changed. Please try again in a moment.'));
        if ($saved === 'stale') return array('warning', _('This entry changed while it was being checked. Please try again.'));
        if ($saved === 'moved') return array('info', sprintf(_('This domain is in %s now, so watching it has stopped.'), SOFTWARE_TITLE));
        return self::outcomeMessage($decision['outcome'], $answer);
    }

    // What Check Now says about a saved decision
    public static function outcomeMessage($outcome, array $answer)
    {
        switch ($outcome) {
            case 'accepted':
            case 'baseline':
                return array('success', _('Checked. The details below are up to date.'));
            case 'provisional':
                return array('info', _('A change was found. It will be checked again in 15 minutes before it\'s recorded.'));
            case 'confirming':
                return array('warning', _("The confirming check didn't get an answer. It will be tried again."));
            case 'given_up':
                return array('warning', _("The confirming check didn't get an answer for six hours, so the change wasn't recorded. Checks go on as before."));
            case 'held':
                return $answer['kind'] === 'incomplete'
                    ? array('warning', _("The registry statuses couldn't be read in full. Please try again in an hour."))
                    : array('warning', _('The registry is limiting requests. Please try again in an hour.'));
        }
        if ($answer['kind'] === 'no_source') return array('danger', _('Neither RDAP nor WHOIS is available for this TLD.'));
        return array('danger', _('The lookup failed. Please try again later.'));
    }

    /*
     * Per session, Check Now of the same entry waits SAME_ENTRY_GAP seconds and any Check Now waits ANY_ENTRY_GAP
     * seconds, as Refresh's limits do (with their own session keys, since an entry's id and a domain's can be the same).
     * Returns '' when the check can go ahead (and records it), or the message saying how long to wait.
     */
    protected function throttle($id)
    {
        $now = time();
        $times = $_SESSION['s_watch_check_times'] ?? array();
        if (!is_array($times)) $times = array();
        foreach ($times as $entry => $when) {
            if ($now - (int) $when >= self::SAME_ENTRY_GAP) unset($times[$entry]);
        }
        if (isset($times[$id])) {
            $seconds = self::SAME_ENTRY_GAP - ($now - (int) $times[$id]);
            return sprintf(ngettext('Please wait %d second before checking this domain again.',
                'Please wait %d seconds before checking this domain again.', $seconds), $seconds);
        }
        $last = (int) ($_SESSION['s_watch_check_last'] ?? 0);
        if ($now - $last < self::ANY_ENTRY_GAP) {
            $seconds = self::ANY_ENTRY_GAP - ($now - $last);
            return sprintf(ngettext('Please wait %d second before checking another domain.',
                'Please wait %d seconds before checking another domain.', $seconds), $seconds);
        }
        $times[$id] = $now;
        $_SESSION['s_watch_check_times'] = $times;
        $_SESSION['s_watch_check_last'] = $now;
        return '';
    }

} //@formatter:on
