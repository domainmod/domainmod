<?php
/**
 * /classes/DomainMOD/DomainRefreshSchedule.php
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
 * The Refresh Domains task's schedule rules, with no database: when a domain is due, when it's next checked, how
 * failures back off, and which domain_refresh_status columns each kind of outcome writes (see DomainRefresh::refreshDue()).
 * Times are Unix timestamps and stored times are UTC 'Y-m-d H:i:s' strings, '1970-01-01 00:00:00' when unset.
 *
 * A domain is due SCHEDULE_DAYS after its last successful check, or SCHEDULE_DAYS_NEAR_EXPIRY after it once its expiry
 * date is within NEAR_EXPIRY_DAYS (or past). Since an expiry date can also change by hand, a domain within that window
 * whose last success was more than a day ago is due too, unless it's backing off after failures or held after a busy
 * server. DomainRefresh::candidates() applies the same rules in SQL, and nextCheck() gives the time they make it due.
 */
class DomainRefreshSchedule
{
    const SCHEDULE_DAYS = 7;
    const SCHEDULE_DAYS_NEAR_EXPIRY = 1;
    const NEAR_EXPIRY_DAYS = 60;
    const BACKOFF_MAX_DAYS = 30; // a failure waits 1 day, then 2, 4, 8, and so on, up to this
    const HOLD = 3600;           // seconds a domain waits after a busy server

    const DAY = 86400;
    const UNSET_TIME = '1970-01-01 00:00:00';

    // The kinds of outcome a refresh can have (see row())
    const SUCCESS = 'success';                       // the details were read (and saved, or nothing had changed)
    const FAILURE = 'failure';                       // the lookup failed, or its changes couldn't be saved
    const RATE_LIMITED = 'rate_limited';             // the domain's own lookup was refused for sending too many
    const BUSY = 'busy';                             // skipped, because its server was busy or used up earlier in the run
    const DEFERRED = 'deferred';                     // put off, because the run's time ran out before its request
    const DEFERRED_AFTER_API = 'deferred_after_api'; // the same, after a registrar call that failed left no time
    const NONE = 'none';                             // nothing to write (a stale result, or a domain that's gone)

    // The columns of domain_refresh_status a write sets (apart from domain_id and revision), with their defaults
    const COLUMNS = array('last_attempt' => self::UNSET_TIME, 'last_turn' => self::UNSET_TIME,
        'last_success' => self::UNSET_TIME, 'next_due' => self::UNSET_TIME, 'hold_until' => self::UNSET_TIME,
        'failures' => 0, 'last_source' => '', 'last_result' => '', 'public_next' => 0);

    // A stored time as a timestamp (0 when unset or unreadable)
    public static function time($stamp)
    {
        $stamp = substr((string) $stamp, 0, 19);
        if ($stamp === '' || $stamp === self::UNSET_TIME) return 0;
        $time = strtotime($stamp . ' UTC');
        return $time === false ? 0 : (int) $time;
    }

    public static function stamp($time)
    {
        return gmdate('Y-m-d H:i:s', (int) floor($time));
    }

    // The last expiry date that counts as near expiry at $now (inclusive), as the candidate query compares it
    public static function nearExpiryDate($now)
    {
        return gmdate('Y-m-d', (int) $now + self::NEAR_EXPIRY_DAYS * self::DAY);
    }

    // Whether the expiry date is within NEAR_EXPIRY_DAYS of $now, or past
    public static function isNearExpiry($expiry_date, $now)
    {
        return (string) $expiry_date <= self::nearExpiryDate($now);
    }

    // When the domain enters its near-expiry window (00:00 UTC, NEAR_EXPIRY_DAYS before its expiry date), or 0 for a
    // date that can't be read (which the candidate query's comparison also treats as near)
    public static function windowStart($expiry_date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $expiry_date)) return 0;
        $time = strtotime($expiry_date . ' 00:00:00 UTC');
        return $time === false ? 0 : max(0, (int) $time - self::NEAR_EXPIRY_DAYS * self::DAY);
    }

    // When a domain is due after a success that started at $started: a day later near expiry, otherwise a week later,
    // but never after the day it enters its near-expiry window
    public static function nextDueAfterSuccess($expiry_date, $started)
    {
        $window = self::windowStart($expiry_date);
        if ($window <= $started) return (int) $started + self::SCHEDULE_DAYS_NEAR_EXPIRY * self::DAY;
        return (int) min($started + self::SCHEDULE_DAYS * self::DAY, $window);
    }

    // The wait after the $failures-th failure in a row (seconds): 1 day, doubling each time, up to BACKOFF_MAX_DAYS
    public static function backoff($failures)
    {
        $failures = max(1, (int) $failures);
        $days = $failures > 6 ? self::BACKOFF_MAX_DAYS : min(self::BACKOFF_MAX_DAYS, 2 ** ($failures - 1));
        return $days * self::DAY;
    }

    /*
     * Whether a domain is due at $now, from its status row ($status, null when it has none) and its current expiry date:
     * the candidate query's rules
     */
    public static function isDue($status, $expiry_date, $now)
    {
        if ($status === null) return true;
        if (self::time($status['hold_until']) > $now) return false;
        if (self::time($status['next_due']) <= $now) return true;

        return self::isNearExpiry($expiry_date, $now) && self::time($status['last_success']) + self::DAY <= $now
            && (int) $status['failures'] === 0;
    }

    /*
     * When the task will next find the domain due (a timestamp), from the same rules as isDue(): the earlier of the
     * stored next_due and, when it isn't backing off, the near-expiry date (the later of the day it enters its window
     * and a day after its last success), never before hold_until. Null when it has no status row (never checked).
     */
    public static function nextCheck($status, $expiry_date)
    {
        if ($status === null) return null;

        $next = self::time($status['next_due']);
        if ((int) $status['failures'] === 0) {
            $next = min($next, max(self::windowStart($expiry_date), self::time($status['last_success']) + self::DAY));
        }

        return max($next, self::time($status['hold_until']));
    }

    /*
     * The status columns an outcome writes, as column => value (always last_turn and last_result), or null when it
     * writes nothing. $status is the row read before the lookup (null when there was none), $outcome holds the result
     * code ('result'), the source ('source'), and when the lookup started ('started', a timestamp, 0 for none), and
     * $expiry_date is the domain's expiry date after the refresh. The caller can change next_due (a later step, such as
     * a DNS check) before writing.
     */
    public static function row($kind, $status, array $outcome, $expiry_date, $now)
    {
        $result = (string) ($outcome['result'] ?? '');
        $source = (string) ($outcome['source'] ?? '');
        $started = (int) ($outcome['started'] ?? 0);
        if ($started <= 0) $started = (int) $now;
        $failures = $status === null ? 0 : (int) $status['failures'];

        switch ($kind) {

            case self::SUCCESS:
                return array('last_attempt' => self::stamp($started), 'last_turn' => self::stamp($now),
                    'last_success' => self::stamp($started),
                    'next_due' => self::stamp(self::nextDueAfterSuccess($expiry_date, $started)),
                    'hold_until' => self::UNSET_TIME, 'failures' => 0, 'last_source' => $source, 'last_result' => $result,
                    'public_next' => 0);

            case self::FAILURE:
                // public_next is cleared only when the public attempt ended (its request was sent, or the answer was
                // definite without one, outcome['public_done']), so a host name that didn't resolve leaves it set
                // last_attempt keeps meaning a real lookup, so a failure that asked no one (outcome['looked_up'] false,
                // such as a TLD with neither RDAP nor WHOIS) leaves it as it was
                $failures = min(65535, $failures + 1);
                return array('last_turn' => self::stamp($now),
                    'next_due' => self::stamp($started + self::backoff($failures)), 'failures' => $failures,
                    'last_source' => $source, 'last_result' => $result)
                    + (($outcome['looked_up'] ?? true) ? array('last_attempt' => self::stamp($started)) : array())
                    + (!empty($outcome['public_done']) ? array('public_next' => 0) : array());

            case self::RATE_LIMITED:
                // A busy server, not a failure: the back-off and next_due stay as they were. The request was sent, so
                // the attempt after the next one may try the registrar's API again (see public_next)
                return array('last_attempt' => self::stamp($started), 'last_turn' => self::stamp($now),
                    'hold_until' => self::stamp($now + self::HOLD), 'last_source' => $source, 'last_result' => $result,
                    'public_next' => 0);

            case self::BUSY:
                return array('last_turn' => self::stamp($now), 'hold_until' => self::stamp($now + self::HOLD),
                    'last_result' => self::BUSY);

            case self::DEFERRED:
                return array('last_turn' => self::stamp($now), 'last_result' => self::DEFERRED);

            case self::DEFERRED_AFTER_API:
                return array('last_turn' => self::stamp($now), 'last_result' => self::DEFERRED_AFTER_API, 'public_next' => 1);

        }

        return null;
    }

} //@formatter:on
