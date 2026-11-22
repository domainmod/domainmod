<?php
/**
 * /tests/Unit/DomainRefreshScheduleTest.php
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
namespace DomainMOD\Tests\Unit;

use DomainMOD\DomainRefresh;
use DomainMOD\DomainRefreshSchedule as S;
use DomainMOD\LiveDns;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\QuotingPdo;
use PHPUnit\Framework\TestCase;

/*
 * The Refresh Domains task's schedule rules (DomainRefreshSchedule): due dates, the near-expiry cap and window, the
 * back-off, holds, nextCheck() agreeing with the candidate query's rules (isDue()), and the status columns each outcome
 * writes. Also the task's table against install/go.php.
 */
class DomainRefreshScheduleTest extends TestCase
{
    const NOW = 1795000000; // 2026-11-18 08:26:40 UTC

    private function statusRow(array $values = array())
    {
        return $values + array('last_attempt' => S::UNSET_TIME, 'last_turn' => S::UNSET_TIME,
            'last_success' => S::UNSET_TIME, 'next_due' => S::UNSET_TIME, 'hold_until' => S::UNSET_TIME, 'failures' => 0,
            'last_source' => '', 'last_result' => '', 'public_next' => 0, 'revision' => 3);
    }

    private function date($days)
    {
        return gmdate('Y-m-d', self::NOW + $days * S::DAY);
    }

    public function testASuccessIsDueAWeekLaterOrADayLaterNearExpiry()
    {
        // far from expiry: a week
        $this->assertSame(self::NOW + 7 * S::DAY, S::nextDueAfterSuccess($this->date(300), self::NOW));
        // within 60 days, and past expiry: a day
        $this->assertSame(self::NOW + S::DAY, S::nextDueAfterSuccess($this->date(30), self::NOW));
        $this->assertSame(self::NOW + S::DAY, S::nextDueAfterSuccess($this->date(-5), self::NOW));
        // a weekly check is capped at the day the domain enters its window (00:00 UTC, 60 days before expiry)
        $expiry = $this->date(63);
        $this->assertSame(strtotime($expiry . ' 00:00:00 UTC') - 60 * S::DAY, S::nextDueAfterSuccess($expiry, self::NOW));
    }

    public function testTheNearExpiryBoundaryIsInclusiveAtSixtyDays()
    {
        $this->assertTrue(S::isNearExpiry($this->date(60), self::NOW));
        $this->assertFalse(S::isNearExpiry($this->date(61), self::NOW));
        $this->assertTrue(S::isNearExpiry($this->date(-400), self::NOW));
        $this->assertSame($this->date(60), S::nearExpiryDate(self::NOW));
    }

    public function testFailuresBackOffDoublingUpToThirtyDays()
    {
        $days = array();
        for ($failures = 1; $failures <= 8; $failures++) $days[] = S::backoff($failures) / S::DAY;
        $this->assertSame(array(1, 2, 4, 8, 16, 30, 30, 30), $days);
        $this->assertSame(30 * S::DAY, S::backoff(60000));
    }

    public function testDueRules()
    {
        // never checked
        $this->assertTrue(S::isDue(null, $this->date(300), self::NOW));
        // waiting for next_due, far from expiry
        $waiting = $this->statusRow(array('next_due' => S::stamp(self::NOW + 3600), 'last_success' => S::stamp(self::NOW - 3600)));
        $this->assertFalse(S::isDue($waiting, $this->date(300), self::NOW));
        $this->assertTrue(S::isDue($waiting, $this->date(300), self::NOW + 3600));
        // an expiry date edited into the window makes it due once the last success is a day old
        $this->assertFalse(S::isDue($waiting, $this->date(30), self::NOW));
        $this->assertTrue(S::isDue($waiting, $this->date(30), self::NOW + S::DAY - 3600));
        // but not while backing off after a failure, nor while held
        $failing = array('failures' => 2, 'next_due' => S::stamp(self::NOW + 2 * S::DAY)) + $waiting;
        $this->assertFalse(S::isDue($failing, $this->date(30), self::NOW + S::DAY - 3600));
        $held = array('hold_until' => S::stamp(self::NOW + 1800), 'next_due' => S::UNSET_TIME) + $waiting;
        $this->assertFalse(S::isDue($held, $this->date(300), self::NOW));
        $this->assertTrue(S::isDue($held, $this->date(300), self::NOW + 1800));
    }

    public function testABusySkipDoesntStopANearExpiryDomainOnceItsHoldEnds()
    {
        $now = self::NOW;
        $after_skip = S::row(S::BUSY, $this->statusRow(array('last_success' => S::stamp($now - 3 * S::DAY),
            'next_due' => S::stamp($now + 5 * S::DAY))), array(), $this->date(20), $now) + $this->statusRow(array(
            'last_success' => S::stamp($now - 3 * S::DAY), 'next_due' => S::stamp($now + 5 * S::DAY)));
        $this->assertFalse(S::isDue($after_skip, $this->date(20), $now + 600));
        $this->assertTrue(S::isDue($after_skip, $this->date(20), $now + S::HOLD));
    }

    public function testNextCheckAgreesWithTheDueRules()
    {
        $cases = array();
        foreach (array(S::UNSET_TIME, S::stamp(self::NOW - 2 * S::DAY), S::stamp(self::NOW - 3600)) as $last_success) {
            foreach (array(S::UNSET_TIME, S::stamp(self::NOW + 6 * S::DAY), S::stamp(self::NOW - 600)) as $next_due) {
                foreach (array(S::UNSET_TIME, S::stamp(self::NOW + 3600)) as $hold) {
                    foreach (array(0, 3) as $failures) {
                        $cases[] = $this->statusRow(array('last_success' => $last_success, 'next_due' => $next_due,
                            'hold_until' => $hold, 'failures' => $failures));
                    }
                }
            }
        }
        foreach ($cases as $status) {
            foreach (array(-10, 0, 45, 59, 60, 61, 62, 70, 400) as $expiry_days) {
                $expiry = $this->date($expiry_days);
                $next = S::nextCheck($status, $expiry);
                foreach (array(0, 1800, 3600, S::DAY, 2 * S::DAY, 3 * S::DAY, 7 * S::DAY) as $later) {
                    $at = self::NOW + $later;
                    $this->assertSame(S::isDue($status, $expiry, $at), $next <= $at,
                        json_encode(array($status, $expiry, $later)));
                }
            }
        }
        $this->assertNull(S::nextCheck(null, $this->date(10)));
    }

    public function testAHoldNeverShortensABackOff()
    {
        $status = $this->statusRow(array('failures' => 2, 'next_due' => S::stamp(self::NOW + 2 * S::DAY),
            'hold_until' => S::stamp(self::NOW + 3600)));
        $this->assertSame(self::NOW + 2 * S::DAY, S::nextCheck($status, $this->date(10)));
    }

    public function testAnEditFromAHundredToSixtyTwoDaysShowsTheWindowNotAWeek()
    {
        $status = $this->statusRow(array('last_success' => S::stamp(self::NOW - 3600), 'next_due' => S::stamp(self::NOW + 7 * S::DAY - 3600)));
        $expiry = $this->date(62);
        $this->assertSame(strtotime($expiry . ' 00:00:00 UTC') - 60 * S::DAY, S::nextCheck($status, $expiry));
    }

    public function testEachOutcomesColumns()
    {
        $now = self::NOW;
        $before = $this->statusRow(array('failures' => 2, 'next_due' => S::stamp($now - 60), 'public_next' => 1));
        $outcome = array('result' => 'changed', 'source' => 'rdap', 'started' => $now - 5);

        $success = S::row(S::SUCCESS, $before, $outcome, $this->date(300), $now);
        $this->assertSame(array('last_attempt' => S::stamp($now - 5), 'last_turn' => S::stamp($now),
            'last_success' => S::stamp($now - 5), 'next_due' => S::stamp($now - 5 + 7 * S::DAY), 'hold_until' => S::UNSET_TIME,
            'failures' => 0, 'last_source' => 'rdap', 'last_result' => 'changed', 'public_next' => 0), $success);

        $failure = S::row(S::FAILURE, $before, array('result' => 'not_found', 'public_done' => true) + $outcome, $this->date(300), $now);
        $this->assertSame(3, $failure['failures']);
        $this->assertSame(S::stamp($now - 5 + 4 * S::DAY), $failure['next_due']);
        $this->assertSame(0, $failure['public_next']);
        $this->assertArrayNotHasKey('hold_until', $failure);
        $this->assertArrayNotHasKey('last_success', $failure);

        $limited = S::row(S::RATE_LIMITED, $before, array('result' => 'rate_limited') + $outcome, $this->date(300), $now);
        $this->assertSame(S::stamp($now + S::HOLD), $limited['hold_until']);
        $this->assertArrayNotHasKey('failures', $limited);
        $this->assertArrayNotHasKey('next_due', $limited);

        // a skip changes only the hold, the turn, and the result (so the back-off and public_next stay)
        $this->assertSame(array('last_turn' => S::stamp($now), 'hold_until' => S::stamp($now + S::HOLD), 'last_result' => 'busy'),
            S::row(S::BUSY, $before, array(), $this->date(300), $now));
        // put off: only the turn and the result, and after a failed registrar call, public_next
        $this->assertSame(array('last_turn' => S::stamp($now), 'last_result' => 'deferred'),
            S::row(S::DEFERRED, $before, array(), $this->date(300), $now));
        $this->assertSame(array('last_turn' => S::stamp($now), 'last_result' => 'deferred_after_api', 'public_next' => 1),
            S::row(S::DEFERRED_AFTER_API, $before, array(), $this->date(300), $now));
        // a stale result writes nothing
        $this->assertNull(S::row(S::NONE, $before, array('result' => 'stale'), $this->date(300), $now));

        // a domain's first row, whatever writes it, is complete with the defaults
        $first = array_merge(S::COLUMNS, S::row(S::BUSY, null, array(), $this->date(300), $now));
        $this->assertSame(array_keys(S::COLUMNS), array_keys($first));
        $this->assertTrue(S::isDue(array('revision' => 1) + $first, $this->date(300), $now + S::HOLD));
    }

    public function testOnlyAFinishedPublicAttemptClearsPublicNext()
    {
        $before = $this->statusRow(array('public_next' => 1));
        // a host name that didn't resolve: the public attempt didn't end, so the next one still skips the registrar API
        $row = S::row(S::FAILURE, $before, array('result' => 'rdap_failed', 'started' => self::NOW), $this->date(300), self::NOW);
        $this->assertArrayNotHasKey('public_next', $row);
        // a request that was sent, or a definite answer without one, ends it
        $row = S::row(S::FAILURE, $before, array('result' => 'no_source', 'started' => self::NOW, 'public_done' => true), $this->date(300), self::NOW);
        $this->assertSame(0, $row['public_next']);
    }

    public function testAFailureThatAskedNoOneKeepsLastAttempt()
    {
        $row = S::row(S::FAILURE, null, array('result' => 'no_source', 'started' => self::NOW, 'looked_up' => false, 'public_done' => true), $this->date(300), self::NOW);
        $this->assertArrayNotHasKey('last_attempt', $row);
        $this->assertSame(1, $row['failures']);
    }

    public function testNotFoundBacksOffOneTwoThenFourDays()
    {
        $status = null;
        $now = self::NOW;
        $waits = array();
        for ($i = 0; $i < 3; $i++) {
            $row = S::row(S::FAILURE, $status, array('result' => 'not_found', 'source' => 'rdap', 'started' => $now), $this->date(300), $now);
            $status = array_merge(S::COLUMNS, $status ?? array(), $row);
            $waits[] = (S::time($row['next_due']) - $now) / S::DAY;
            $now = S::time($row['next_due']);
        }
        $this->assertSame(array(1, 2, 4), $waits);
    }

    // A live DNS check that needs another try brings a success's next check to a day after its lookup started, never
    // later than the usual date, so a near-expiry check isn't pushed back
    public function testALiveDnsRetryIsDueWithinADay()
    {
        $week = S::nextDueAfterSuccess($this->date(300), self::NOW);
        $this->assertSame(self::NOW + S::DAY, S::withDnsRetry($week, self::NOW));
        // A refresh at 18:00 whose domain enters its window at the next midnight keeps the earlier date
        $evening = strtotime(gmdate('Y-m-d', self::NOW) . ' 18:00:00 UTC');
        $expiry = gmdate('Y-m-d', $evening + 61 * S::DAY);
        $window = S::nextDueAfterSuccess($expiry, $evening);
        $this->assertSame(strtotime($expiry . ' 00:00:00 UTC') - 60 * S::DAY, $window);
        $this->assertLessThan($evening + S::DAY, $window);
        $this->assertSame($window, S::withDnsRetry($window, $evening));
    }

    // saveOutcome()'s Live DNS retry: a success gets the one-day retry ('due'), forced when the DNS step had no time or
    // couldn't save, and otherwise left to the stored observation (read by the write); a failure, or a success while Live
    // DNS isn't available, gets none
    public function testASuccessCarriesTheLiveDnsRetryToTheWrite()
    {
        $pdo = new QuotingPdo();
        $refresh = Instance::withoutConstructor(CapturingRefresh::class, array('deeb' => (object) array('cnxx' => $pdo)));
        $known = function ($class, $live_dns) use ($pdo) {
            $property = new \ReflectionProperty($class, $class === DomainRefresh::class ? 'schedule_available' : 'available');
            $values = $property->getValue();
            $values[spl_object_id($pdo)] = $live_dns;
            $property->setValue(null, $values);
        };
        $known(DomainRefresh::class, true);
        $known(LiveDns::class, true);
        $save = new \ReflectionMethod(DomainRefresh::class, 'saveOutcome');
        $row = (object) array('id' => 3, 'domain' => 'example.com');
        $outcome = function ($kind, $dns) {
            return array('kind' => $kind, 'result' => 'ok', 'source' => 'rdap', 'started' => self::NOW,
                'expiry_date' => gmdate('Y-m-d', self::NOW + 300 * S::DAY), 'looked_up' => true, 'public_done' => true,
                'dns' => array('state' => $dns));
        };

        try {
            foreach (array('no_time' => true, 'save_failed' => true, 'complete' => false, 'incomplete' => false,
                         'unavailable' => false, 'not_run' => false) as $dns => $force) {
                $save->invoke($refresh, $row, $outcome(S::SUCCESS, $dns), null, false);
                $retry = end($refresh->writes)[7];
                $this->assertSame(array('due' => S::stamp(self::NOW + S::DAY), 'force' => $force), $retry, $dns);
            }
            $save->invoke($refresh, $row, $outcome(S::FAILURE, 'no_time'), null, false);
            $this->assertNull(end($refresh->writes)[7]);

            LiveDns::resetAvailable();
            $known(LiveDns::class, false);
            $writes = count($refresh->writes);
            $save->invoke($refresh, $row, $outcome(S::SUCCESS, 'no_time'), null, false);
            $this->assertCount($writes + 1, $refresh->writes);
            $this->assertNull(end($refresh->writes)[7]);
        } finally {
            LiveDns::resetAvailable();
            DomainRefresh::resetScheduleAvailable();
        }
    }

    // A scheduled write refused because another refresh wrote first still lets a stored Live DNS observation pull the
    // next check forward, while a refused success, or an outcome whose DNS step stored nothing, doesn't
    public function testARefusedScheduledWriteStillPullsTheLiveDnsRetry()
    {
        $pdo = new QuotingPdo();
        $refresh = Instance::withoutConstructor(CapturingRefresh::class, array('deeb' => (object) array('cnxx' => $pdo)));
        $property = new \ReflectionProperty(DomainRefresh::class, 'schedule_available');
        $property->setValue(null, array(spl_object_id($pdo) => true));
        $save = new \ReflectionMethod(DomainRefresh::class, 'saveOutcome');
        $row = (object) array('id' => 3, 'domain' => 'example.com');
        $outcome = function ($kind, $dns) {
            return array('kind' => $kind, 'result' => 'rdap_failed', 'source' => 'rdap', 'started' => self::NOW,
                'expiry_date' => '2028-01-01', 'looked_up' => true, 'public_done' => true, 'dns' => array('state' => $dns));
        };

        try {
            $refresh->written = false;
            $save->invoke($refresh, $row, $outcome(S::FAILURE, 'incomplete'), $this->statusRow(), true);
            // only while the domain is still live, the task's rule
            $this->assertSame(array(array(3, 'example.com', true)), $refresh->pulls);
            $save->invoke($refresh, $row, $outcome(S::FAILURE, 'not_run'), $this->statusRow(), true);
            $save->invoke($refresh, $row, $outcome(S::SUCCESS, 'incomplete'), null, true);
            $refresh->written = true;
            $save->invoke($refresh, $row, $outcome(S::FAILURE, 'complete'), $this->statusRow(), true);
            $this->assertCount(1, $refresh->pulls);
        } finally {
            DomainRefresh::resetScheduleAvailable();
        }
    }

    public function testTheTableMatchesTheInstaller()
    {
        $source = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/install/go.php'));
        $this->assertStringContainsString(preg_replace('/\s+/', ' ', trim(DomainRefresh::tableSql())), $source);
        foreach (DomainRefresh::STATUS_COLUMNS as $column) {
            $this->assertStringContainsString('`' . $column . '`', DomainRefresh::tableSql());
        }
        $this->assertSame(array_merge(array('domain_id'), array_keys(S::COLUMNS), array('revision')), DomainRefresh::STATUS_COLUMNS);
    }
}

// DomainRefresh with its status write captured instead of run
class CapturingRefresh extends DomainRefresh
{
    public $writes = array();
    public $written = true;
    public $pulls = array();

    protected function pullDnsRetry($domain_id, $domain, $require_live = false)
    {
        $this->pulls[] = array($domain_id, $domain, $require_live);
    }

    protected function writeStatus($domain_id, $domain, array $columns, $success, $revision, $started, $require_live,
                                   $dns_retry = null)
    {
        $this->writes[] = func_get_args() + array(7 => null);
        return $this->written;
    }
}
