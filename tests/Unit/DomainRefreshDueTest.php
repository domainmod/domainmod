<?php
/**
 * /tests/Unit/DomainRefreshDueTest.php
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

use DomainMOD\ChangeHistory;
use DomainMOD\DomainQueue;
use DomainMOD\DomainRefresh;
use DomainMOD\DomainRefreshSchedule as S;
use DomainMOD\Rdap;
use DomainMOD\Scheduler;
use DomainMOD\Whois;
use PHPUnit\Framework\TestCase;

/*
 * The Refresh Domains task's run (DomainRefresh::refreshDomain() and refreshEach()) with stub lookups and no database:
 * the outcome of each kind of lookup, busy servers, the WHOIS cap, registrar failures and the slow-API fallback, the
 * status-only lookup's rules, stale results and failed saves, the batch cap, the run's budget and turns, and the
 * registrar gap. Also the turn cycle's owner and pointer (Scheduler::turnOwner()).
 */
class DomainRefreshDueTest extends TestCase
{
    // The time limit and the request's start time before each test, put back afterwards (some tests set their own)
    protected $max_execution_time;
    protected $request_time_float;
    protected $had_request_time_float;

    protected function setUp(): void
    {
        $this->max_execution_time = ini_get('max_execution_time');
        $this->had_request_time_float = array_key_exists('REQUEST_TIME_FLOAT', $_SERVER);
        $this->request_time_float = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
    }

    protected function tearDown(): void
    {
        $turn = new \ReflectionProperty(Scheduler::class, 'turn');
        $turn->setValue(null, null);
        ini_set('max_execution_time', $this->max_execution_time);
        if ($this->had_request_time_float) {
            $_SERVER['REQUEST_TIME_FLOAT'] = $this->request_time_float;
        } else {
            unset($_SERVER['REQUEST_TIME_FLOAT']);
        }
    }

    private function refresh(StubRdap $rdap, ?StubWhois $whois = null)
    {
        $refresh = (new \ReflectionClass(StubRefresh::class))->newInstanceWithoutConstructor();
        $refresh->deeb = (object) array('cnxx' => new \stdClass()); // no database: DomainStatus::isAvailable() is false
        $refresh->log = new SilentLog();
        $refresh->stub_rdap = $rdap;
        $refresh->stub_whois = $whois ?? new StubWhois();
        return $refresh;
    }

    private function row(array $values = array())
    {
        return (object) ($values + array('id' => 7, 'domain' => 'example.com', 'tld' => 'com', 'expiry_date' => '2027-06-01',
            'dns_id' => 1, 'privacy' => 0, 'autorenew' => 0, 'notes' => '', 'account_id' => 3, 'active' => '1',
            'registrar_id' => 2, 'api_registrar_id' => 0, 'api_registrar_name' => '', 'username' => '', 'password' => '',
            'api_account_id' => '', 'reseller_id' => '', 'api_app_name' => '', 'api_key' => '', 'api_secret' => '',
            'api_ip_id' => 0, 'api_ip' => ''));
    }

    private function apiRow(array $values = array())
    {
        return $this->row($values + array('api_registrar_name' => 'Porkbun', 'api_key' => 'k', 'api_secret' => 's'));
    }

    private function call($object, $method, ...$args)
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }

    private function newRunState()
    {
        return $this->call($this->refresh(new StubRdap()), 'newRun');
    }

    private function scheduled(DomainRefresh $refresh, $row, $run, $seconds = 45)
    {
        return $this->call($refresh, 'refreshDomain', $row, 0, microtime(true) + $seconds, $run);
    }

    private function details()
    {
        return array('expiry_date' => '2028-06-01', 'name_servers' => array('ns1.example.net'), 'privacy' => 1,
            'autorenew' => 1, 'notes' => array(), 'status' => null);
    }

    public function testAScheduledRdapSuccessIsRecordedAsScheduledRefreshWithoutANote()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'ok', 'expiry_date' => '2028-06-01')));
        $refresh = $this->refresh($rdap);
        $refresh->apply_result = array(true, array(), array('reasons' => array(), 'changes' => array('x'), 'notes' => array(), 'changed' => true));
        $outcome = $this->scheduled($refresh, $this->row(), $this->newRunState());

        $this->assertSame(S::SUCCESS, $outcome['kind']);
        $this->assertSame('changed', $outcome['result']);
        $this->assertSame('rdap', $outcome['source']);
        $this->assertTrue($outcome['changed']);
        $this->assertSame('2028-06-01', $outcome['expiry_date']);
        $this->assertGreaterThan(0, $outcome['started']);
        list($source, $history_source, $add_note, $require_live, $before_dns) = $refresh->apply_calls[0];
        $this->assertSame(array('rdap', ChangeHistory::SOURCE_SCHEDULED_REFRESH, false, true, array('ns1.example.com')),
            array($source, $history_source, $add_note, $require_live, $before_dns));
    }

    public function testAClickedRefreshKeepsItsSourceAndNote()
    {
        $refresh = $this->refresh(new StubRdap(array('example.com' => array('status' => 'ok'))));
        $outcome = $this->call($refresh, 'refreshDomain', $this->row(), 5, microtime(true) + 45, null);

        $this->assertTrue($outcome['success']);
        $this->assertSame('ok', $outcome['result']);
        $this->assertSame(array('rdap', ChangeHistory::SOURCE_REFRESH, true, false), array_slice($refresh->apply_calls[0], 0, 4));
        $this->assertStringContainsString('refreshed using RDAP', $outcome['lines'][0]);
    }

    public function testARateLimitHoldsTheDomainAndSkipsItsServerForTheRun()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'rate_limited')));
        $refresh = $this->refresh($rdap);
        $run = $this->newRunState();

        $first = $this->scheduled($refresh, $this->row(), $run);
        $this->assertSame(S::RATE_LIMITED, $first['kind']);
        $this->assertSame('rate_limited', $first['result']);
        $this->assertArrayHasKey('rdap:rdap.example', $run->busy);

        $second = $this->scheduled($refresh, $this->row(array('id' => 8, 'domain' => 'other.com')), $run);
        $this->assertSame(S::BUSY, $second['kind']);
        $this->assertSame(array('example.com'), $rdap->lookups); // the second domain asked nothing
        // the rate-limited lookup's status read failed (Couldn't Check, as a clicked Refresh saves it), the skip's didn't
        $this->assertSame(array(array(7, 'example.com', true)), $refresh->failed_saves);
    }

    public function testAnErrorFailsTheDomainAndSkipsItsServerButAMismatchOnlyFailsTheDomain()
    {
        $run = $this->newRunState();
        $error = $this->scheduled($this->refresh(new StubRdap(array('example.com' => array('status' => 'error')))), $this->row(), $run);
        $this->assertSame(array(S::FAILURE, 'rdap_failed'), array($error['kind'], $error['result']));
        $this->assertArrayHasKey('rdap:rdap.example', $run->busy);

        $run = $this->newRunState();
        $mismatch = $this->scheduled($this->refresh(new StubRdap(array('example.com' => array('status' => 'mismatch')))), $this->row(), $run);
        $this->assertSame(array(S::FAILURE, 'mismatch'), array($mismatch['kind'], $mismatch['result']));
        $this->assertSame(array(), $run->busy);

        $not_found = $this->scheduled($this->refresh(new StubRdap(array('example.com' => array('status' => 'not_found')))), $this->row(), $this->newRunState());
        $this->assertSame(array(S::FAILURE, 'not_found'), array($not_found['kind'], $not_found['result']));
    }

    public function testALookupTheDeadlineStoppedIsPutOffNotFailed()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'error')));
        $rdap->stopped = array('example.com' => true);
        $refresh = $this->refresh($rdap);
        $run = $this->newRunState();
        $outcome = $this->scheduled($refresh, $this->row(), $run);

        $this->assertSame(array(S::DEFERRED, 'deferred'), array($outcome['kind'], $outcome['result']));
        $this->assertSame(array(), $run->busy);
        $this->assertSame(array(), $refresh->failed_saves);
    }

    public function testTooLittleTimeOrNoBootstrapAsksNoOne()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'ok')));
        $outcome = $this->scheduled($this->refresh($rdap), $this->row(), $this->newRunState(), 5);
        $this->assertSame(S::DEFERRED, $outcome['kind']);

        $rdap->servers = array();
        $rdap->has_bootstrap = false;
        $outcome = $this->scheduled($this->refresh($rdap), $this->row(), $this->newRunState());
        $this->assertSame(S::DEFERRED, $outcome['kind']);
        $this->assertSame(array(), $rdap->lookups);
    }

    public function testASlowFailingRegistrarCallPutsTheDomainOffForPublicDataNextTime()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'ok')));
        $refresh = $this->refresh($rdap);
        $refresh->registrar_details = null;
        $refresh->registrar_seconds = 40; // the API call uses up the time, leaving under MIN_LOOKUP_TIME
        $run = $this->newRunState();
        $outcome = $this->scheduled($refresh, $this->apiRow(), $run, 45);

        $this->assertSame(array(S::DEFERRED_AFTER_API, 'deferred_after_api'), array($outcome['kind'], $outcome['result']));
        $this->assertArrayHasKey(3, $run->failed_accounts);
        $this->assertSame(1, S::row($outcome['kind'], null, $outcome, '2027-06-01', time())['public_next']);

        // the account's other domains skip its API for the rest of the run
        $refresh->registrar_seconds = 0;
        $next = $this->scheduled($refresh, $this->apiRow(array('id' => 8)), $run);
        $this->assertSame(1, $refresh->registrar_calls);
        $this->assertSame(array(S::SUCCESS, 'rdap'), array($next['kind'], $next['source']));
    }

    public function testAFailedRegistrarCallCountsAsALookupEvenWhenItsFallbackIsSkipped()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'ok')));
        $refresh = $this->refresh($rdap);
        $refresh->registrar_details = null;
        $run = $this->newRunState();
        $run->busy['rdap:rdap.example'] = true;
        $outcome = $this->scheduled($refresh, $this->apiRow(), $run);

        $this->assertSame(S::BUSY, $outcome['kind']);
        $this->assertTrue($outcome['looked_up']);
        $this->assertSame(array(), $rdap->lookups);
    }

    public function testAHostNameThatDoesntResolveLeavesPublicNextSet()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'error')));
        $rdap->unsent = array('example.com' => true);
        $outcome = $this->scheduled($this->refresh($rdap), $this->row(), $this->newRunState());
        $this->assertSame(array(S::FAILURE, 'rdap_failed'), array($outcome['kind'], $outcome['result']));
        $this->assertFalse($outcome['public_done']);
        // and it asked no one, so it doesn't count toward the run's cap
        $this->assertFalse($outcome['looked_up']);
        $this->assertArrayNotHasKey('public_next', S::row($outcome['kind'], null, $outcome, '2027-06-01', time()));
    }

    public function testAPacingWaitThatLeavesTooLittleTimePutsTheDomainOffBeforeTheCall()
    {
        $refresh = $this->refresh(new StubRdap(array('example.com' => array('status' => 'ok'))));
        $refresh->registrar_details = $this->details();
        $run = $this->newRunState();
        $run->api_last['Porkbun'] = microtime(true);
        $outcome = $this->scheduled($refresh, $this->apiRow(), $run, DomainRefresh::MIN_LOOKUP_TIME + 1);

        $this->assertSame(S::DEFERRED, $outcome['kind']);
        $this->assertSame(0, $refresh->registrar_calls);
        $this->assertFalse($outcome['looked_up']);
        $this->assertSame(array(), $run->failed_accounts);
    }

    public function testPublicNextGoesStraightToRdap()
    {
        $refresh = $this->refresh(new StubRdap(array('example.com' => array('status' => 'ok'))));
        $refresh->registrar_details = $this->details();
        $run = $this->newRunState();
        $run->public_next = true;
        $outcome = $this->scheduled($refresh, $this->apiRow(), $run);

        $this->assertSame(0, $refresh->registrar_calls);
        $this->assertSame('rdap', $outcome['source']);
        $this->assertSame(0, S::row($outcome['kind'], null, $outcome, '2027-06-01', time())['public_next']);
    }

    public function testARegistrarAnswerIsUsedAndAFailedCallFallsBackToRdap()
    {
        $refresh = $this->refresh(new StubRdap(array('example.com' => array('status' => 'ok'))));
        $refresh->registrar_details = $this->details();
        $outcome = $this->scheduled($refresh, $this->apiRow(), $this->newRunState());
        $this->assertSame(array(S::SUCCESS, 'registrar'), array($outcome['kind'], $outcome['source']));

        $refresh->registrar_details = null;
        $run = $this->newRunState();
        $fallback = $this->scheduled($refresh, $this->apiRow(array('account_id' => 9)), $run);
        $this->assertSame(array(S::SUCCESS, 'rdap'), array($fallback['kind'], $fallback['source']));
        $this->assertArrayHasKey(9, $run->failed_accounts);
    }

    public function testWhoisIsCappedPerServerAndItsCutOffDiscoveryIsPutOff()
    {
        $rdap = new StubRdap(array(), array());
        $whois = new StubWhois(array('example.de' => array('status' => 'ok', 'expiry_date' => '2027-01-01')));
        $run = $this->newRunState();
        $run->whois_counts['whois.denic.de'] = DomainQueue::WHOIS_PER_SERVER;
        $capped = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $run);
        $this->assertSame(S::BUSY, $capped['kind']);
        $this->assertSame(array(), $whois->lookups);

        $run = $this->newRunState();
        $ok = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $run);
        $this->assertSame(array(S::SUCCESS, 'whois'), array($ok['kind'], $ok['source']));
        $this->assertSame(1, $run->whois_counts['whois.denic.de']);

        $whois->found = false;
        $whois->cut_off = true;
        $cut = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $this->newRunState());
        $this->assertSame(S::DEFERRED, $cut['kind']);

        $whois->cut_off = false;
        $unknown = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $this->newRunState());
        $this->assertSame(array(S::FAILURE, 'whois_server_unknown'), array($unknown['kind'], $unknown['result']));

        $whois->found = true;
        $whois->server = '';
        $none = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $this->newRunState());
        $this->assertSame(array(S::FAILURE, 'no_source'), array($none['kind'], $none['result']));
        $this->assertTrue($none['public_done']);
        // with a stale bootstrap copy, "no source" isn't definite, so public_next stays
        Rdap::$bootstrap_stale = true;
        $stale = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $this->newRunState());
        Rdap::$bootstrap_stale = false;
        $this->assertFalse($stale['public_done']);
    }

    public function testAWhoisRefusalFailsButAWhoisCutOffIsPutOff()
    {
        $rdap = new StubRdap(array(), array());
        $whois = new StubWhois(array('example.de' => array('status' => 'error', 'connection_failed' => true)));
        $run = $this->newRunState();
        $refused = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $run);
        $this->assertSame(array(S::FAILURE, 'whois_unreachable'), array($refused['kind'], $refused['result']));
        $this->assertArrayHasKey('whois:whois.denic.de', $run->busy);

        // a refused connection wrote no query: a failure, but not a finished public attempt or a lookup
        $this->assertFalse($refused['public_done']);
        $this->assertFalse($refused['looked_up']);

        $whois->stopped = true;
        $run = $this->newRunState();
        $stopped = $this->scheduled($this->refresh($rdap, $whois), $this->row(array('domain' => 'example.de', 'tld' => 'de')), $run);
        $this->assertSame(S::DEFERRED, $stopped['kind']);
        $this->assertSame(array(), $run->busy);
        // a query that wasn't sent doesn't use up the server's share, and asked no one
        $this->assertSame(array(), $run->whois_counts);
        $this->assertFalse($stopped['looked_up']);
    }

    public function testAStaleResultWritesNothingAndAFailedSaveIsAFailure()
    {
        $refresh = $this->refresh(new StubRdap(array('example.com' => array('status' => 'ok'))));
        $refresh->apply_result = array(false, array('changed'), array('reasons' => array('changed'), 'changes' => array(), 'notes' => array(), 'failure' => 'stale'));
        $stale = $this->scheduled($refresh, $this->row(), $this->newRunState());
        $this->assertSame(array(S::NONE, 'stale'), array($stale['kind'], $stale['result']));
        $this->assertNull(S::row($stale['kind'], null, $stale, '2027-06-01', time()));
        $this->assertSame(array(), $refresh->failed_saves);

        $refresh->apply_result = array(false, array('x'), array('reasons' => array('x'), 'changes' => array(), 'notes' => array(), 'failure' => 'save_failed'));
        $failed = $this->scheduled($refresh, $this->row(), $this->newRunState());
        $this->assertSame(array(S::FAILURE, 'save_failed'), array($failed['kind'], $failed['result']));

        $refresh->apply_result = array(false, array('x'), array('reasons' => array('x'), 'changes' => array(), 'notes' => array(), 'failure' => 'conflict'));
        $conflict = $this->scheduled($refresh, $this->row(), $this->newRunState());
        $this->assertSame(S::NONE, $conflict['kind']);
        $this->assertTrue($refresh->hadConflict());
    }

    public function testTheStatusOnlyLookupFollowsTheRunsBusyServers()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'rate_limited')));
        $refresh = $this->refresh($rdap);
        $refresh->status_available = true;
        $run = $this->newRunState();

        // a real rate limit marks the server busy, and the read counts as failed (Couldn't Check)
        $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 45, $run));
        $this->assertArrayHasKey('rdap:rdap.example', $run->busy);
        $this->assertNotSame('', $refresh->failed_started);

        // a busy server isn't asked, and that isn't a failed check
        $refresh->failed_started = '';
        $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 45, $run));
        $this->assertSame(array('example.com'), $rdap->lookups);
        $this->assertSame('', $refresh->failed_started);

        // an error doesn't mark the server busy (it also means the time ran out)
        $run = $this->newRunState();
        $rdap->results['example.com'] = array('status' => 'error');
        $this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 45, $run);
        $this->assertSame(array(), $run->busy);

        // a lookup the deadline stopped before it asked the registry isn't a failed check
        $refresh->failed_started = '';
        $rdap->stopped = array('example.com' => true);
        $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 45, $run));
        $this->assertSame('', $refresh->failed_started);
    }

    public function testTheStatusOnlyLookupIsSkippedWhenFindingTheServerUsesUpTheTime()
    {
        $rdap = new StubRdap(array('example.com' => array('status' => 'ok')));
        $rdap->discovery_seconds = 0.6;
        $refresh = $this->refresh($rdap);
        $refresh->status_available = true;
        $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + DomainRefresh::MIN_LOOKUP_TIME + 0.4, $this->newRunState()));
        $this->assertSame(array(), $rdap->lookups);
        $this->assertSame('', $refresh->failed_started);
    }

    public function testTheRunCountsLookupsTowardItsCapAndSkipsDomainsNoLongerDue()
    {
        $refresh = $this->refresh(new StubRdap());
        $refresh->rows = array();
        foreach (range(1, 60) as $id) $refresh->rows[$id] = $this->row(array('id' => $id));
        $refresh->rows[2]->active = '10';                       // marked Sold since the candidates were read
        $refresh->statuses[3] = array('hold_until' => S::stamp(time() + 3600)) + S::COLUMNS + array('revision' => 1);
        unset($refresh->rows[4]);                               // deleted
        $refresh->outcomes = array(5 => S::BUSY, 6 => S::DEFERRED, 7 => S::FAILURE);
        $summary = $this->call($refresh, 'refreshEach', range(1, 60), microtime(true) + 600,
            array('checked' => 0, 'changed' => 0, 'failed' => 0, 'busy' => 0, 'deferred' => 0));

        $this->assertSame(DomainRefresh::REFRESH_BATCH, $summary['checked']);
        $this->assertSame(array(1, 0, 1, 1), array($summary['failed'], $summary['changed'], $summary['busy'], $summary['deferred']));
        $this->assertNotContains(2, $refresh->refreshed);
        $this->assertNotContains(3, $refresh->refreshed);
        $this->assertNotContains(4, $refresh->refreshed);
        // 50 lookups, plus the two that were skipped or put off
        $this->assertCount(52, $refresh->refreshed);
        $this->assertCount(52, $refresh->saved);
    }

    public function testTheRunStopsStartingLookupsWithoutEnoughTime()
    {
        $refresh = $this->refresh(new StubRdap());
        foreach (range(1, 3) as $id) $refresh->rows[$id] = $this->row(array('id' => $id));
        $summary = $this->call($refresh, 'refreshEach', range(1, 3), microtime(true) + DomainRefresh::MIN_LOOKUP_TIME - 1,
            array('checked' => 0, 'changed' => 0, 'failed' => 0, 'busy' => 0, 'deferred' => 0));
        $this->assertSame(0, $summary['checked']);
        $this->assertSame(array(), $refresh->refreshed);
    }

    public function testTheBudgetKeepsAQuarterAndLeavesTheOwnersNeedsOnItsTurn()
    {
        $refresh = $this->refresh(new StubRdap());
        ini_set('max_execution_time', '0');
        $this->assertSame(DomainRefresh::REFRESH_BUDGET, $refresh->budget(microtime(true)));
        $this->assertSame(DomainRefresh::RUN_NOW_BUDGET, $refresh->budget(microtime(true), true));

        ini_set('max_execution_time', '30');
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $budget = $refresh->budget(microtime(true));
        $this->assertEqualsWithDelta(22.5, $budget, 0.1);

        // on the Watch List's turn, Refresh stops with its needs left
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => 'watch-list'));
        $this->assertEqualsWithDelta(30 - 20, $refresh->budget(microtime(true)), 0.1);
        // Run Now keeps nothing for later tasks
        $this->assertEqualsWithDelta(20, $refresh->budget(microtime(true), true), 0.1);
        // on its own turn, or the SSL Queue's (which runs before it), its usual budget
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => 'domain-refresh'));
        $this->assertEqualsWithDelta(22.5, $refresh->budget(microtime(true)), 0.1);
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => 'ssl-queue'));
        $this->assertEqualsWithDelta(22.5, $refresh->budget(microtime(true)), 0.1);
    }

    public function testTheRegistrarGapIsKeptBetweenCallsToOneApi()
    {
        DomainRefresh::$registrar_gaps['TestApi'] = 0.3;
        $refresh = $this->refresh(new StubRdap());
        $run = $this->newRunState();
        $this->call($refresh, 'paceRegistrar', $run, 'TestApi', microtime(true) + 45);
        $began = microtime(true);
        $this->call($refresh, 'paceRegistrar', $run, 'TestApi', microtime(true) + 45);
        $this->assertGreaterThanOrEqual(0.25, microtime(true) - $began);
        // another API doesn't wait
        $began = microtime(true);
        $this->call($refresh, 'paceRegistrar', $run, 'OtherApi', microtime(true) + 45);
        $this->assertLessThan(0.1, microtime(true) - $began);
        unset(DomainRefresh::$registrar_gaps['TestApi']);
    }

    public function testTheTurnGoesToTheFirstEligibleTaskAndAWaitingTaskKeepsIt()
    {
        $all = function ($state) {
            return array_fill_keys(Scheduler::TURN_TASKS, $state);
        };
        // everyone eligible: the stored task owns it
        $this->assertSame(array('domain-refresh', 'domain-refresh'), Scheduler::turnOwner($all('eligible'), 'domain-refresh'));
        // passed tasks are passed over for good
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'passed', 'domain-refresh' => 'passed', 'watch-list' => 'eligible');
        $this->assertSame(array('watch-list', 'watch-list'), Scheduler::turnOwner($states, 'ssl-queue'));
        // a task that's on, with work, but not due in this run keeps the pointer while the next eligible task owns the run
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'eligible', 'domain-refresh' => 'waiting', 'watch-list' => 'passed');
        $this->assertSame(array('domain-queue', 'domain-refresh'), Scheduler::turnOwner($states, 'domain-refresh'));
        // an unreadable pointer is the Domain Queue's
        $this->assertSame(array('domain-queue', 'domain-queue'), Scheduler::turnOwner($all('eligible'), 'nonsense'));
        $this->assertSame('domain-queue', Scheduler::nextTurn('watch-list'));
        $this->assertSame('ssl-queue', Scheduler::nextTurn('domain-queue'));
    }

    public function testOnlyTheTasksBeforeTheOwnerLeaveItsNeeds()
    {
        $turn = new \ReflectionProperty(Scheduler::class, 'turn');
        $turn->setValue(null, array('owner' => 'domain-refresh'));
        $this->assertSame(DomainRefresh::REFRESH_NEEDS, Scheduler::reserveFor('domain-queue'));
        $this->assertSame(DomainRefresh::REFRESH_NEEDS, Scheduler::reserveFor('ssl-queue'));
        $this->assertSame(0, Scheduler::reserveFor('domain-refresh'));
        $this->assertSame(0, Scheduler::reserveFor('watch-list'));
        $turn->setValue(null, array('owner' => 'domain-queue'));
        $this->assertSame(0, Scheduler::reserveFor('domain-queue'));
        $turn->setValue(null, array('owner' => ''));
        $this->assertSame(0, Scheduler::reserveFor('ssl-queue'));
        $turn->setValue(null, null);
        $this->assertSame(0, Scheduler::reserveFor('ssl-queue'));
    }
}

// DomainRefresh with its lookups, registrar call, save, and database reads replaced
class StubRefresh extends DomainRefresh
{
    public $stub_rdap;
    public $stub_whois;
    public $apply_result = null;
    public $apply_calls = array();
    public $failed_saves = array();
    public $registrar_details = null;
    public $registrar_seconds = 0;
    public $registrar_calls = 0;
    public $rows = array();
    public $statuses = array();
    public $outcomes = array();
    public $refreshed = array();
    public $saved = array();

    protected function rdap()
    {
        return $this->stub_rdap;
    }

    protected function whois()
    {
        return $this->stub_whois;
    }

    protected function dnsServers($dns_id)
    {
        return array('ns1.example.com');
    }

    protected function fetchFromRegistrar($row, $api_name, $deadline)
    {
        $this->registrar_calls++;
        // a slow call: it starts with just over MIN_LOOKUP_TIME left (see refreshDomain() below) and uses half a second
        if ($this->registrar_seconds > 0) usleep(500000);
        return $this->registrar_details;
    }

    protected function paceRegistrar($run, $api_name, $deadline)
    {
        if ($this->registrar_seconds > 0) {
            // the call "takes" this long: the time it leaves is what counts, so the deadline is moved instead of waiting
            $run->api_last[$api_name] = microtime(true);
            return;
        }
        parent::paceRegistrar($run, $api_name, $deadline);
    }

    protected function refreshDomain($row, $user_id, $deadline, $run)
    {
        if ($this->rows) {
            $this->refreshed[] = (int) $row->id;
            $kind = $this->outcomes[(int) $row->id] ?? S::SUCCESS;
            return array('success' => $kind === S::SUCCESS, 'kind' => $kind, 'result' => $kind === S::SUCCESS ? 'ok' : $kind,
                'source' => 'rdap', 'started' => time(), 'changed' => false, 'expiry_date' => $row->expiry_date,
                'looked_up' => !in_array($kind, array(S::BUSY, S::DEFERRED), true),
                'lines' => array(), 'parts' => array());
        }
        if ($this->registrar_seconds > 0) $deadline = microtime(true) + DomainRefresh::MIN_LOOKUP_TIME + 0.3;
        return parent::refreshDomain($row, $user_id, $deadline, $run);
    }

    protected function apply($before, $user_id, $source, $details, $history_source = ChangeHistory::SOURCE_REFRESH,
                             $add_note = true, $require_live = false, $before_dns = false)
    {
        $this->apply_calls[] = array($source, $history_source, $add_note, $require_live, $before_dns);
        return $this->apply_result ?? array(true, array(), array('reasons' => array(), 'changes' => array(), 'notes' => array(), 'changed' => false));
    }

    protected function saveFailedStatus($domain_id, $domain = null, $require_live = false)
    {
        if ($this->failed_started !== '') $this->failed_saves[] = array($domain_id, $domain, $require_live);
    }

    protected function getDomainRow($domain_id, $lock)
    {
        return $this->rows[$domain_id] ?? false;
    }

    public function readStatus($domain_id)
    {
        return $this->statuses[$domain_id] ?? null;
    }

    protected function saveOutcome($row, array $outcome, $status, $require_live)
    {
        $this->saved[] = array((int) $row->id, $outcome['kind'], $require_live);
    }
}

// Rdap answering from a table: each domain's lookup result, its TLD's server, and whether the deadline stopped it
class StubRdap extends Rdap
{
    public $results;
    public $servers;
    public $has_bootstrap = true;
    public $stopped = array();
    public $unsent = array();
    public $lookups = array();
    public $discovery_seconds = 0;

    public function __construct(array $results = array(), ?array $servers = null)
    {
        $this->results = $results;
        $this->servers = $servers ?? array('com' => 'https://rdap.example/');
    }

    public function lookup($domain, $deadline = null, $status_only = false)
    {
        $this->lookups[] = $domain;
        $this->deadline_stopped = !empty($this->stopped[$domain]);
        $tld = substr(strrchr($domain, '.'), 1);
        $this->domain_request_sent = !$this->deadline_stopped && empty($this->unsent[$domain]) && isset($this->servers[$tld]);
        $this->domain_request_delivered = $this->domain_request_sent;
        if (!isset($this->servers[$tld])) return array('status' => 'no_server', 'name_servers' => array());
        return ($this->results[$domain] ?? array('status' => 'ok')) + array('expiry_date' => '', 'name_servers' => array(),
            'status_read' => 'found', 'statuses' => array('ok'));
    }

    public function serverFor($ascii_domain, $deadline = null)
    {
        if ($this->discovery_seconds > 0) usleep((int) ($this->discovery_seconds * 1000000));
        return $this->servers[substr(strrchr($ascii_domain, '.'), 1)] ?? '';
    }

    public function hasBootstrap($deadline = null)
    {
        return $this->has_bootstrap;
    }
}

// Whois answering from a table, with a fixed server for every TLD
class StubWhois extends Whois
{
    public $results;
    public $found = true;
    public $server = 'whois.denic.de';
    public $cut_off = false;
    public $stopped = false;
    public $lookups = array();

    public function __construct(array $results = array())
    {
        $this->results = $results;
    }

    public function serverFor($ascii_domain, $deadline = null)
    {
        $this->discovery_cut_off = !$this->found && $this->cut_off;
        return array($this->found, $this->found ? $this->server : '');
    }

    public function lookup($domain, $deadline = null)
    {
        $this->lookups[] = $domain;
        $this->deadline_stopped = $this->stopped;
        $this->domain_request_sent = !$this->stopped;
        $result = ($this->results[$domain] ?? array('status' => 'ok')) + array('expiry_date' => '2027-01-01',
            'name_servers' => array(), 'connection_failed' => false);
        $this->query_written = !$this->stopped && !$result['connection_failed'];
        return $result;
    }
}

class SilentLog
{
    public function __call($name, $arguments)
    {
    }
}
