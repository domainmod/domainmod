<?php
/**
 * /tests/Unit/WatchCheckTest.php
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
use DomainMOD\RdapAssets;
use DomainMOD\RestoreLock;
use DomainMOD\Sanitize;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\WatchCheck;
use PHPUnit\Framework\TestCase;

/*
 * The Watch List's checks with no database (WatchCheck::decide(), plan(), interval(), backoff(), and the reading of
 * lookup answers): baselines, confirmations, holds, failures and back-off, the grace comparison, what's recorded in the
 * Change History, and the fast slots
 */
class WatchCheckTest extends TestCase
{
    // 2027-03-10 09:00:00 UTC
    const T = 1804669200;

    private function check()
    {
        $check = new WatchCheck(new RecordingPdo());
        $assets = (new \ReflectionClass(RdapAssets::class))->newInstanceWithoutConstructor();
        $assets->sanitize = new Sanitize();
        $check->assets = $assets;
        return $check;
    }

    private function row(array $values = array())
    {
        return $values + array('id' => 7, 'domain' => 'example.com', 'tld' => 'com', 'reason' => 'watch', 'notes' => '',
            'archived' => 0, 'last_status_read' => '', 'has_baseline' => 0, 'moved_domain_id' => 0, 'stage' => 'unknown',
            'pending_stage' => '', 'pending_source' => '', 'pending_since' => '1970-01-01 00:00:00', 'revision' => 1,
            'statuses' => '', 'expiry_date' => '1970-01-01', 'registration_date' => '1970-01-01', 'deletion_date' => '1970-01-01',
            'expiry_source' => '', 'grace_base_expiry' => '1970-01-01', 'grace_base_source' => '', 'grace_open' => 0,
            'registrar_name' => '', 'registrar_iana_id' => '', 'registrar_url' => '', 'stage_since' => '1970-01-01 00:00:00',
            'stage_last_seen' => '1970-01-01 00:00:00', 'prev_check' => '1970-01-01 00:00:00', 'drop_from' => '1970-01-01',
            'drop_to' => '1970-01-01', 'drop_basis' => '', 'last_attempt' => '1970-01-01 00:00:00',
            'last_success' => '1970-01-01 00:00:00', 'next_due' => '1970-01-01 00:00:00', 'hold_until' => '1970-01-01 00:00:00',
            'failures' => 0, 'last_source' => '', 'last_result' => '');
    }

    // A known, registered entry, as after its baseline
    private function known(array $values = array())
    {
        return $this->row($values + array('has_baseline' => 1, 'stage' => 'registered', 'expiry_date' => '2028-01-01',
            'expiry_source' => 'registrar', 'registrar_name' => 'Example Registrar', 'registrar_iana_id' => '1001',
            'statuses' => 'clientTransferProhibited', 'stage_since' => '2027-01-01 00:00:00',
            'stage_last_seen' => '2027-03-09 09:00:00', 'last_attempt' => '2027-03-09 09:00:00',
            'last_success' => '2027-03-09 09:00:00'));
    }

    private function answer($kind, array $values = array(), $offset = 0)
    {
        return $values + array('kind' => $kind, 'source' => 'rdap', 'result' => $kind === 'none' ? 'not_found' : 'ok',
            'request_sent' => true, 'started' => self::T + $offset, 'expiry_date' => '2028-01-01', 'expiry_source' => 'registrar',
            'registration_date' => '2001-01-01', 'deletion_date' => '', 'registrar_name' => 'Example Registrar',
            'registrar_iana_id' => '1001', 'registrar_url' => 'https://registrar.example/', 'statuses' => array('clientTransferProhibited'));
    }

    private function decide(array $row, array $answer, $fast = false)
    {
        return $this->check()->decide($row, $answer, $answer['started'], $fast);
    }

    private function fields(array $decision)
    {
        return array_column($decision['records'], 0);
    }

    public function testTheFirstAnswerIsABaselineAndRecordsNothing()
    {
        $decision = $this->decide($this->row(), $this->answer('record'));
        $this->assertSame('baseline', $decision['outcome']);
        $this->assertSame(array(), $decision['records']);
        $this->assertSame(array('registered', 1, '2027-03-10 09:00:00', '1970-01-01 00:00:00', 'complete', 'Example Registrar'),
            array($decision['update']['stage'], $decision['update']['has_baseline'], $decision['update']['stage_since'],
                $decision['update']['prev_check'], $decision['update']['last_status_read'], $decision['update']['registrar_name']));
    }

    public function testAFirstRdapNotRegisteredIsTheBaselineAtOnce()
    {
        $decision = $this->decide($this->row(array('failures' => 2)), $this->answer('none'));
        $this->assertSame('baseline', $decision['outcome']);
        $this->assertSame(array(), $decision['records']);
        $this->assertSame(array('not_registered', 1, 0, '2027-03-10 09:00:00', '1970-01-01 00:00:00', '2027-03-10 09:00:00'),
            array($decision['update']['stage'], $decision['update']['has_baseline'], $decision['update']['failures'],
                $decision['update']['stage_since'], $decision['update']['prev_check'], $decision['update']['last_success']));
        // Nothing waits for a confirming lookup, and Check Now says the details are up to date
        $this->assertArrayNotHasKey('pending_stage', $decision['update']);
        $this->assertSame('success', WatchCheck::outcomeMessage($decision['outcome'], $this->answer('none'))[0]);

        // A first answer waiting for its WHOIS confirmation is settled the same way once RDAP answers
        $pending = $this->row(array('pending_stage' => 'not_registered', 'pending_source' => 'whois',
            'pending_since' => '2027-03-10 08:00:00', 'last_attempt' => '2027-03-10 08:00:00'));
        $decision = $this->decide($pending, $this->answer('none'));
        $this->assertSame('baseline', $decision['outcome']);
        $this->assertSame(array('not_registered', 1, '', '2027-03-10 09:00:00', '1970-01-01 00:00:00'),
            array($decision['update']['stage'], $decision['update']['has_baseline'], $decision['update']['pending_stage'],
                $decision['update']['stage_since'], $decision['update']['prev_check']));
    }

    public function testAnEstablishedEntrysRdapNotRegisteredStillWaitsForItsConfirmation()
    {
        $decision = $this->decide($this->known(), $this->answer('none'));
        $this->assertSame('provisional', $decision['outcome']);
        $this->assertSame(array('not_registered', 'rdap'), array($decision['update']['pending_stage'], $decision['update']['pending_source']));
        $this->assertArrayNotHasKey('stage', $decision['update']);

        // A WHOIS confirmation it's waiting for isn't replaced by an RDAP answer
        $pending = $this->known(array('pending_stage' => 'not_registered', 'pending_source' => 'whois',
            'pending_since' => '2027-03-10 08:00:00', 'last_attempt' => '2027-03-10 08:00:00'));
        $decision = $this->decide($pending, $this->answer('none'));
        $this->assertSame('confirming', $decision['outcome']);
        $this->assertArrayNotHasKey('pending_stage', $decision['update']);
    }

    public function testABaselineWhoisNotRegisteredWaitsForItsConfirmation()
    {
        $whois = array('source' => 'whois');
        $decision = $this->decide($this->row(), $this->answer('none', $whois));
        $this->assertSame('provisional', $decision['outcome']);
        $this->assertSame(array('not_registered', 'whois', '2027-03-10 09:00:00', '2027-03-10 09:15:00'),
            array($decision['update']['pending_stage'], $decision['update']['pending_source'], $decision['update']['pending_since'],
                $decision['update']['next_due']));
        // The baseline isn't saved yet, and nothing else changes
        $this->assertArrayNotHasKey('has_baseline', $decision['update']);
        $this->assertArrayNotHasKey('stage', $decision['update']);

        $pending = $this->row(array('pending_stage' => 'not_registered', 'pending_source' => 'whois',
            'pending_since' => '2027-03-10 09:00:00', 'last_attempt' => '2027-03-10 09:00:00'));
        $decision = $this->decide($pending, $this->answer('none', $whois, 900));
        $this->assertSame('baseline', $decision['outcome']);
        $this->assertSame(array(), $decision['records']);
        $this->assertSame(array('not_registered', '2027-03-10 09:00:00', '', 1),
            array($decision['update']['stage'], $decision['update']['stage_since'], $decision['update']['pending_stage'],
                $decision['update']['has_baseline']));
    }

    public function testAConfirmedDropRecordsOnlyTheStage()
    {
        $row = $this->known();
        $first = $this->decide(array('failures' => 2) + $row, $this->answer('none'));
        $this->assertSame('provisional', $first['outcome']);
        // An answer ends a back-off, so a confirmation given up later goes back to the normal schedule
        $this->assertSame(0, $first['update']['failures']);
        $this->assertSame(array(), $first['records']);

        $pending = array_merge($row, $first['update']);
        $decision = $this->decide($pending, $this->answer('none', array(), 900));
        $this->assertSame('accepted', $decision['outcome']);
        $this->assertSame(array(array('stage', 'registered', 'not_registered', 'rdap', '', '')), $decision['records']);
        // No record reads no status, so an incomplete read's marker stays
        $this->assertArrayNotHasKey('last_status_read', $decision['update']);
        // The old registration's details are cleared without being recorded
        $this->assertSame(array('1970-01-01', '', '', '', 0), array($decision['update']['expiry_date'], $decision['update']['registrar_name'],
            $decision['update']['statuses'], $decision['update']['expiry_source'], $decision['update']['grace_open']));
        // The stage was first seen when the move was first seen, and the check before it is the old stage's last sighting
        $this->assertSame(array('2027-03-10 09:00:00', '2027-03-09 09:00:00'),
            array($decision['update']['stage_since'], $decision['update']['prev_check']));
    }

    public function testAConfirmationShowingAnotherStageUsesItsOwnTime()
    {
        $pending = $this->known(array('stage' => 'not_registered', 'pending_stage' => 'redemption', 'pending_source' => 'rdap',
            'pending_since' => '2027-03-10 09:00:00', 'last_attempt' => '2027-03-10 09:00:00'));
        $decision = $this->decide($pending, $this->answer('record', array('statuses' => array('pendingDelete')), 1200));
        $this->assertSame('pending_delete', $decision['update']['stage']);
        $this->assertSame(array('2027-03-10 09:20:00', '2027-03-10 09:00:00'),
            array($decision['update']['stage_since'], $decision['update']['prev_check']));
        $this->assertContains('stage', $this->fields($decision));

        // Leaving "Not registered": a first answer of new and a second of registered agree, and the second's stage is saved
        $pending['pending_stage'] = 'new';
        $decision = $this->decide($pending, $this->answer('record', array(), 900));
        $this->assertSame('registered', $decision['update']['stage']);
    }

    public function testAFailedConfirmationRetriesWithoutAHoldOrAFailure()
    {
        $pending = $this->known(array('pending_stage' => 'not_registered', 'pending_source' => 'rdap',
            'pending_since' => '2027-03-10 09:00:00', 'last_attempt' => '2027-03-10 09:00:00'));
        foreach (array($this->answer('failure', array('result' => 'error'), 900), $this->answer('rate_limited', array(), 900),
                     $this->answer('incomplete', array(), 900), $this->answer('record', array('source' => 'whois'), 900),
                     $this->answer('none', array('source' => 'whois'), 900)) as $answer) {
            $decision = $this->decide($pending, $answer);
            $this->assertSame('confirming', $decision['outcome'], $answer['kind'] . ' ' . $answer['source']);
            $this->assertSame('2027-03-10 09:30:00', $decision['update']['next_due']);
            $this->assertArrayNotHasKey('hold_until', $decision['update']);
            $this->assertArrayNotHasKey('failures', $decision['update']);
            $this->assertArrayNotHasKey('pending_stage', $decision['update']);
        }
        // An incomplete read during a confirmation still marks the status read incomplete
        $this->assertSame('incomplete', $this->decide($pending, $this->answer('incomplete', array(), 900))['update']['last_status_read']);
    }

    public function testAConfirmationIsGivenUpOnlyByALookupThatSentItsRequestSixHoursOn()
    {
        $pending = $this->known(array('pending_stage' => 'not_registered', 'pending_source' => 'rdap',
            'pending_since' => '2027-03-10 09:00:00', 'last_attempt' => '2027-03-10 14:55:00'));
        // Started at 5:59 and answering later is judged by its start
        $this->assertSame('confirming', $this->decide($pending, $this->answer('failure', array('result' => 'error'), 21540))['outcome']);
        // A time guard that stopped it before the request (a slow IANA download) never uses it up
        $this->assertSame('confirming', $this->decide($pending, $this->answer('failure', array('request_sent' => false), 21700))['outcome']);
        $decision = $this->decide($pending, $this->answer('failure', array('result' => 'error'), 21700));
        $this->assertSame('given_up', $decision['outcome']);
        $this->assertSame('', $decision['update']['pending_stage']);
        $this->assertSame(array(), $decision['records']);
        // A pending delete entry given up goes back to its fast interval, not a day
        $fast_pending = array('stage' => 'pending_delete', 'drop_from' => '2027-03-01') + $pending;
        $given_up = $this->decide($fast_pending, $this->answer('failure', array('result' => 'error'), 21700));
        $this->assertSame(gmdate('Y-m-d H:i:s', self::T + 21700 + 3600), $given_up['update']['next_due']);
        // A definite "no source" ends it at once, as an ordinary failure
        $decision = $this->decide($pending, $this->answer('no_source', array('result' => 'no_source'), 900));
        $this->assertSame(array('failed', '', 1), array($decision['outcome'], $decision['update']['pending_stage'], $decision['update']['failures']));
    }

    public function testADisagreeingConfirmationIsAnOrdinaryAnswer()
    {
        $pending = $this->known(array('pending_stage' => 'not_registered', 'pending_source' => 'rdap',
            'pending_since' => '2027-03-10 09:00:00'));
        $decision = $this->decide($pending, $this->answer('record', array('expiry_date' => '2029-01-01'), 900));
        $this->assertSame('accepted', $decision['outcome']);
        $this->assertSame('', $decision['update']['pending_stage']);
        $this->assertSame(array('expiry_date'), $this->fields($decision));
    }

    public function testHoldsAndFailures()
    {
        $row = $this->known();
        $decision = $this->decide($row, $this->answer('rate_limited'));
        $this->assertSame(array('held', '2027-03-10 10:00:00'), array($decision['outcome'], $decision['update']['hold_until']));
        $this->assertArrayNotHasKey('failures', $decision['update']);

        $decision = $this->decide($row, $this->answer('incomplete'));
        $this->assertSame(array('held', 'incomplete'), array($decision['outcome'], $decision['update']['last_status_read']));
        // Nothing from an incomplete answer is saved
        $this->assertArrayNotHasKey('expiry_date', $decision['update']);
        $this->assertArrayNotHasKey('stage', $decision['update']);

        // A timeout leaves the status read as it was
        $decision = $this->decide($row + array(), $this->answer('failure', array('result' => 'error')));
        $this->assertArrayNotHasKey('last_status_read', $decision['update']);
        $this->assertSame(1, $decision['update']['failures']);

        // A new name with no source: "Unable to check", backing off a day
        $decision = $this->decide($this->row(), $this->answer('no_source', array('result' => 'no_source')));
        $this->assertSame(array(1, '2027-03-11 09:00:00'), array($decision['update']['failures'], $decision['update']['next_due']));
    }

    public function testBackOffDoublesUpToItsCap()
    {
        $registered = $this->known(array('expiry_date' => '2029-01-01'));
        $this->assertSame(604800, WatchCheck::backoff($registered, 1, self::T, false));
        $this->assertSame(1209600, WatchCheck::backoff($registered, 2, self::T, false));
        $this->assertSame(2592000, WatchCheck::backoff($registered, 9, self::T, false));
        // Pending delete stops at a day, so a flaky server can't make it miss a drop by weeks
        $pending_delete = $this->known(array('stage' => 'pending_delete', 'drop_from' => '2027-03-12'));
        $this->assertSame(7200, WatchCheck::backoff($pending_delete, 1, self::T, true));
        $this->assertSame(86400, WatchCheck::backoff($pending_delete, 8, self::T, true));
    }

    public function testRoutineIntervals()
    {
        $t = self::T;
        // Weekly far from expiry, capped at the day the 60-day window starts, then daily
        $this->assertSame(604800, WatchCheck::interval($this->known(array('expiry_date' => '2028-01-01')), $t, false));
        $this->assertSame(strtotime('2027-03-13 00:00:00 UTC') - $t, WatchCheck::interval($this->known(array('expiry_date' => '2027-05-12')), $t, false));
        $this->assertSame(86400, WatchCheck::interval($this->known(array('expiry_date' => '2027-04-01')), $t, false));
        // No expiry date published: weekly
        $this->assertSame(604800, WatchCheck::interval($this->known(array('expiry_date' => '1970-01-01')), $t, false));
        // Pending delete with a fast slot: two-hourly, then hourly from the start of its window, even past its end
        $pending_delete = $this->known(array('stage' => 'pending_delete', 'drop_from' => '2027-03-12', 'drop_to' => '2027-03-14'));
        $this->assertSame(7200, WatchCheck::interval($pending_delete, $t, true));
        $this->assertSame(3600, WatchCheck::interval($pending_delete, strtotime('2027-03-12 00:00:00 UTC'), true));
        $this->assertSame(3600, WatchCheck::interval($pending_delete, strtotime('2027-03-20 00:00:00 UTC'), true));
        $this->assertSame(86400, WatchCheck::interval($pending_delete, $t, false));
        // With no window (a TLD without a profile): hourly from the first sighting
        $this->assertSame(3600, WatchCheck::interval($this->known(array('stage' => 'pending_delete')), $t, true));
        // Redemption: six-hourly from two days before its window, with a slot
        $redemption = $this->known(array('stage' => 'redemption', 'drop_from' => '2027-03-12', 'drop_to' => '2027-04-10'));
        $this->assertSame(21600, WatchCheck::interval($redemption, $t, true));
        $this->assertSame(86400, WatchCheck::interval(array('drop_from' => '2027-03-20') + $redemption, $t, true));
        foreach (array('grace', 'expired', 'restore_pending', 'not_registered', 'new', 'unknown') as $stage) {
            $this->assertSame(86400, WatchCheck::interval($this->known(array('stage' => $stage)), $t, true), $stage);
        }
    }

    public function testAnExpiryDateMovesOnlyBetweenDatesFromOneSource()
    {
        $row = $this->known();
        $this->assertSame(array(array('expiry_date', '2028-01-01', '2029-01-01', 'rdap', '', '')),
            $this->decide($row, $this->answer('record', array('expiry_date' => '2029-01-01')))['records']);
        // The registrar timed out and the registry's date came instead: saved, never recorded
        $decision = $this->decide($row, $this->answer('record', array('expiry_date' => '2029-01-01', 'expiry_source' => 'registry')));
        $this->assertSame(array(), $decision['records']);
        $this->assertSame('registry', $decision['update']['expiry_source']);
        // A date appearing where there was none isn't a change
        $this->assertSame(array(), $this->decide($this->known(array('expiry_date' => '1970-01-01', 'expiry_source' => '')),
            $this->answer('record'))['records']);
    }

    public function testTheGracePeriodRecordsOneChangeWhenItEnds()
    {
        $row = $this->known(array('expiry_date' => '2027-03-01'));
        $grace = $this->decide($row, $this->answer('record', array('statuses' => array('autoRenewPeriod'), 'expiry_date' => '2028-03-01')));
        $this->assertSame(array('statuses', 'stage'), $this->fields($grace));
        $this->assertSame(array(1, '2027-03-01', 'registrar', 'rough'), array($grace['update']['grace_open'],
            $grace['update']['grace_base_expiry'], $grace['update']['grace_base_source'], $grace['update']['drop_basis']));

        $in_grace = array_merge($row, $grace['update']);
        // A later date during grace isn't recorded, and grace starting again keeps the same baseline
        $again = $this->decide($in_grace, $this->answer('record', array('statuses' => array('autoRenewPeriod'), 'expiry_date' => '2028-03-02')));
        $this->assertSame(array(), $again['records']);
        $this->assertArrayNotHasKey('grace_base_expiry', $again['update']);
        // A WHOIS answer neither moves the stage nor closes the comparison
        $whois = $this->decide($in_grace, $this->answer('record', array('source' => 'whois', 'statuses' => array(), 'expiry_date' => '2028-03-01')));
        $this->assertSame(array(), $whois['records']);
        $this->assertArrayNotHasKey('stage', $whois['update']);
        $this->assertArrayNotHasKey('grace_open', $whois['update']);
        // A date from the other source keeps it open
        $other = $this->decide($in_grace, $this->answer('record', array('expiry_date' => '2028-03-01', 'expiry_source' => 'registry')));
        $this->assertNotContains('expiry_date', $this->fields($other));
        $this->assertArrayNotHasKey('grace_open', $other['update']);
        // The first complete read without the status, with a date from the same source, records the one change
        $done = $this->decide($in_grace, $this->answer('record', array('expiry_date' => '2028-03-01')));
        $this->assertContains(array('expiry_date', '2027-03-01', '2028-03-01', 'rdap', '', ''), $done['records']);
        $this->assertSame(0, $done['update']['grace_open']);
    }

    public function testNoDateIsComparedWhileTheDomainIsBeingDeleted()
    {
        // Grace and redemption in one response close the comparison, so a later read never compares the old date
        $in_grace = $this->known(array('stage' => 'grace', 'expiry_date' => '2028-03-01', 'grace_open' => 1,
            'grace_base_expiry' => '2027-03-01', 'grace_base_source' => 'registrar', 'statuses' => 'autoRenewPeriod'));
        $both = $this->decide($in_grace, $this->answer('record', array('statuses' => array('autoRenewPeriod', 'redemptionPeriod'), 'expiry_date' => '2028-03-01')));
        $this->assertSame(0, $both['update']['grace_open']);
        $this->assertNotContains('expiry_date', $this->fields($both));
        // A registered entry going straight to redemption with a later date records no expiry change
        $straight = $this->decide($this->known(), $this->answer('record', array('statuses' => array('redemptionPeriod'), 'expiry_date' => '2029-01-01')));
        $this->assertNotContains('expiry_date', $this->fields($straight));
    }

    public function testGraceFirstSeenOnABaselineClosesWithoutARecord()
    {
        $first = $this->decide($this->row(), $this->answer('record', array('statuses' => array('autoRenewPeriod'), 'expiry_date' => '2028-03-01')));
        $this->assertSame(array(1, '1970-01-01', ''), array($first['update']['grace_open'], $first['update']['grace_base_expiry'], $first['update']['drop_basis']));
        $after = $this->decide(array_merge($this->row(), $first['update']), $this->answer('record', array('expiry_date' => '2028-03-01')));
        $this->assertNotContains('expiry_date', $this->fields($after));
        $this->assertSame(0, $after['update']['grace_open']);
    }

    public function testRegistrarChangesAreRecordedByIanaIdOrName()
    {
        $row = $this->known();
        $this->assertSame(array(array('registrar', 'Example Registrar', 'Example Registrar', 'rdap', '1001', '2002')),
            $this->decide($row, $this->answer('record', array('registrar_iana_id' => '2002')))['records']);
        $this->assertSame(array(), $this->decide($row, $this->answer('record', array('registrar_name' => 'EXAMPLE registrar ')))['records']);
        $this->assertSame(array('registrar'), $this->fields($this->decide($this->known(array('registrar_iana_id' => '')),
            $this->answer('record', array('registrar_name' => 'Other Registrar', 'registrar_iana_id' => '')))));
        // Values that don't fit are stored by RdapAssets' rules: shortened, without an emoji, an http:// URL left out
        $decision = $this->decide($row, $this->answer('record', array('registrar_name' => str_repeat('a', 110) . "\u{1F600}",
            'registrar_url' => 'http://registrar.example/', 'registrar_iana_id' => '12x')));
        $this->assertSame(array(100, '', ''), array(mb_strlen($decision['update']['registrar_name']), $decision['update']['registrar_url'],
            $decision['update']['registrar_iana_id']));
    }

    public function testStatusesAreRecordedOnlyFromCompleteRdapReads()
    {
        $row = $this->known();
        $this->assertSame(array('statuses'), $this->fields($this->decide($row, $this->answer('record',
            array('statuses' => array('clientTransferProhibited', 'clientHold'))))));
        $whois = $this->decide($row, $this->answer('record', array('source' => 'whois', 'statuses' => array())));
        $this->assertArrayNotHasKey('statuses', $whois['update']);
        // A WHOIS answer for a newly registered entry keeps its stage and its last status sighting
        $new = $this->decide($this->known(array('stage' => 'new')), $this->answer('record', array('source' => 'whois', 'statuses' => array())));
        $this->assertArrayNotHasKey('stage', $new['update']);
        $this->assertArrayNotHasKey('stage_last_seen', $new['update']);
    }

    public function testATransferRecordsTheRegistrarAndExpiryInOneAction()
    {
        $decision = $this->decide($this->known(), $this->answer('record', array('registrar_iana_id' => '3003',
            'registrar_name' => 'New Registrar', 'expiry_date' => '2029-01-01')));
        $this->assertSame(array('registrar', 'expiry_date'), $this->fields($decision));
        foreach ($decision['records'] as $record) $this->assertSame('rdap', $record[3]);
    }

    public function testRdapAnswersAreReadByTheirStatusFirst()
    {
        $check = $this->check();
        $state = array('failed' => array());
        $base = array('kind' => 'skipped', 'source' => '', 'result' => '', 'request_sent' => true, 'started' => self::T);
        $lookup = array('status' => 'not_found', 'status_read' => 'incomplete', 'redirected' => false);
        $this->assertSame('none', $check->fromRdap($lookup, $base, $state)['kind']);
        $this->assertSame('failure', $check->fromRdap(array('redirected' => true) + $lookup, $base, $state)['kind']);
        $ok = array('status' => 'ok', 'status_read' => 'incomplete', 'expiry_date' => '2028-01-01', 'registrar_name' => '',
            'registrar_iana_id' => '', 'registrar_url' => '', 'statuses' => array());
        $this->assertSame('incomplete', $check->fromRdap($ok, $base, $state)['kind']);
        $this->assertSame('record', $check->fromRdap(array('status_read' => 'none') + $ok, $base, $state)['kind']);
        $this->assertSame('failure', $check->fromRdap(array('status' => 'mismatch'), $base, $state)['kind']);
        $this->assertSame(array(), $state['failed']);
        $check->fromRdap(array('status' => 'rate_limited'), $base, $state, 'rdap:registry.example');
        $check->fromRdap(array('status' => 'error'), $base, $state, 'rdap:other.example');
        $check->fromRdap(array('status' => 'error'), array('request_sent' => false) + $base, $state, 'rdap:third.example');
        $check->fromRdap(array('status' => 'error', 'redirected' => true), $base, $state, 'rdap:fourth.example');
        $this->assertSame(array('rdap:registry.example', 'rdap:other.example', 'rdap:fourth.example'), array_keys($state['failed']));
    }

    public function testAWhoisBannerIsntARecord()
    {
        $check = $this->check();
        $state = array();
        $base = array('kind' => 'skipped', 'source' => '', 'result' => '', 'request_sent' => true, 'started' => self::T);
        $banner = array('status' => 'ok', 'expiry_date' => '', 'registration_date' => '', 'registrar_name' => '', 'name_servers' => array());
        $this->assertSame(array('failure', 'unreadable'), array_values(array_intersect_key($check->fromWhois($banner, $base, $state),
            array('kind' => 1, 'result' => 1))));
        $record = $check->fromWhois(array('registrar_name' => 'Example') + $banner, $base, $state);
        $this->assertSame(array('record', ''), array($record['kind'], $record['expiry_source']));
        $this->assertSame('none', $check->fromWhois(array('status' => 'not_found'), $base, $state)['kind']);
    }

    public function testFastSlotsPromotionsAndDemotions()
    {
        $now = self::T;
        $rows = array();
        // 51 pending delete entries with current windows, and one whose window ended
        for ($i = 1; $i <= 51; $i++) {
            $rows[] = $this->known(array('id' => $i, 'stage' => 'pending_delete', 'drop_from' => sprintf('2027-03-%02d', min(28, 9 + $i % 10)),
                'drop_to' => '2027-03-30', 'last_attempt' => '2027-03-10 06:00:00', 'next_due' => '2027-03-11 06:00:00'));
        }
        $rows[] = $this->known(array('id' => 99, 'stage' => 'pending_delete', 'drop_from' => '2027-03-01', 'drop_to' => '2027-03-06',
            'last_attempt' => '2027-03-10 06:00:00', 'next_due' => '2027-03-10 08:00:00'));
        $plan = $this->check()->plan($rows, array(), $now);
        $this->assertCount(WatchCheck::FAST_ENTRIES, $plan['slots']);
        // A window that has ended ranks after the current ones, so it loses its slot and goes back to daily
        $this->assertArrayNotHasKey(99, $plan['slots']);
        $this->assertSame('2027-03-11 06:00:00', $plan['changes'][99][0]);
        // A slotted entry checked at 06:00 is brought forward to its fast interval
        $slotted = array_key_first($plan['slots']);
        $this->assertSame('2027-03-10 07:00:00', $plan['changes'][$slotted][0]);
        $this->assertNotEmpty($plan['due']);

        // A back-off and a confirmation are never changed, and a held entry isn't due
        $rows = array(
            $this->known(array('id' => 1, 'stage' => 'pending_delete', 'failures' => 2, 'next_due' => '2027-03-11 00:00:00')),
            $this->known(array('id' => 2, 'stage' => 'pending_delete', 'pending_stage' => 'not_registered', 'next_due' => '2027-03-10 09:15:00')),
            $this->known(array('id' => 3, 'next_due' => '2027-03-10 08:00:00', 'hold_until' => '2027-03-10 10:00:00')));
        $plan = $this->check()->plan($rows, array(), $now);
        $this->assertSame(array(), $plan['changes']);
        $this->assertSame(array(), $plan['due']);

        // An entry live in Domains is left out of the ranking and the selection
        $plan = $this->check()->plan(array($this->known(array('id' => 5, 'next_due' => '2027-03-10 08:00:00'))), array(5 => 42), $now);
        $this->assertSame(array(), $plan['due']);

        // The queue is one list, oldest due first
        $rows = array($this->known(array('id' => 1, 'next_due' => '2027-03-10 08:00:00')),
            $this->known(array('id' => 2, 'next_due' => '2027-03-09 08:00:00')));
        $this->assertSame(array(2, 1), array_column($this->check()->plan($rows, array(), $now)['due'], 'id'));
    }

    public function testAConfirmationNeverHoldsAFastSlot()
    {
        $pending = $this->known(array('id' => 1, 'stage' => 'pending_delete', 'pending_stage' => 'not_registered',
            'drop_from' => '2027-03-01', 'drop_to' => '2027-03-30'));
        $waiting = $this->known(array('id' => 2, 'stage' => 'pending_delete', 'drop_from' => '2027-03-05', 'drop_to' => '2027-03-30'));
        $this->assertSame(array(2 => true), WatchCheck::fastSlots(array($pending, $waiting), array(), self::T));
    }

    // A save writes the entry, records its history rows, and finishes the action before its commit
    public function testASaveRecordsAndFinishesItsAction()
    {
        $row = $this->known();
        $pdo = new class(array('FROM watch_domains WHERE id' => array(array($row)))) extends RecordingPdo {
            public function lastInsertId(?string $name = null): string|false
            {
                return '9';
            }
        };
        $cache = new \ReflectionProperty(ChangeHistory::class, 'available');
        $cache->setValue(null, array(spl_object_id($pdo) => true));
        RestoreLock::$test_state = array('state' => 'held', 'flag' => false);
        try {
            $check = new WatchCheck($pdo);
            $decision = array('update' => array('stage' => 'grace'), 'records' => array(array('stage', 'registered', 'grace', 'rdap', '', '')),
                'outcome' => 'accepted');
            $this->assertSame('saved', $check->save($row, $decision, 0, 'watch_check'));
        } finally {
            RestoreLock::$test_state = null;
            ChangeHistory::forgetAvailability();
        }
        $sql = array_column($pdo->log, 'sql');
        $at = function ($text) use ($sql) {
            foreach ($sql as $index => $statement) {
                if (strpos($statement, $text) !== false) return $index;
            }
            return -1;
        };
        $this->assertGreaterThan(-1, $at('UPDATE watch_domains'));
        $this->assertGreaterThan($at('UPDATE watch_domains'), $at('INSERT INTO change_history'));
        // finish() counts the action's items, before the commit
        $this->assertGreaterThan($at('INSERT INTO change_history'), $at('SELECT DISTINCT item_type, item_id'));
        $this->assertGreaterThan($at('SELECT DISTINCT item_type, item_id'), $at('COMMIT'));
        $this->assertStringContainsString('REPEATABLE READ', $sql[0]);
    }

    // A schedule change that meets another request's write (a deadlock) waits for the next run, as a held row does, and
    // the run notes it for Run Now. Any other error still ends the run.
    public function testAScheduleChangeThatMeetsAnotherWriteWaitsForTheNextRun()
    {
        $deadlock = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
        $deadlock->errorInfo = array('40001', 1213, 'Deadlock found when trying to get lock');
        // Only the first change meets it, and the run goes on to the next
        $pdo = new RecordingPdo(array('SET next_due' => function ($sql, $params) use ($deadlock) {
            if ((int) $params['id'] === 7) throw $deadlock;
            return array();
        }));
        $check = new WatchCheck($pdo);
        $this->assertFalse($check->hadConflict());
        $check->saveSchedule(array(7 => array('2027-03-10 10:00:00', 1), 8 => array('2027-03-10 11:00:00', 1)));
        $this->assertTrue($check->hadConflict());
        $this->assertSame(array(7, 8), array_map(function ($entry) {
            return (int) $entry['params']['id'];
        }, $pdo->statementsWith('SET next_due')));

        $held = new \PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        $held->errorInfo = array('HY000', 1205, 'Lock wait timeout exceeded');
        $check = new WatchCheck(new RecordingPdo(array('SET next_due' => $held)));
        $this->assertSame(0, $check->saveSchedule(array(7 => array('2027-03-10 10:00:00', 1))));
        $this->assertFalse($check->hadConflict());

        $other = new \PDOException('SQLSTATE[42S02]: Base table or view not found: 1146');
        $other->errorInfo = array('42S02', 1146, "Table 'watch_domains' doesn't exist");
        $check = new WatchCheck(new RecordingPdo(array('SET next_due' => $other)));
        $this->expectException(\PDOException::class);
        $check->saveSchedule(array(7 => array('2027-03-10 10:00:00', 1)));
    }

    // A registrar whose name stays the same while its IANA ID changes is still recorded (record() can change the display
    // alone), and a change with neither is never written
    public function testARegistrarsNewIanaIdAloneIsRecorded()
    {
        $row = $this->known();
        $inserts = array();
        foreach (array(array('1001', '2002'), array('1001', '1001')) as $ids) {
            $pdo = new class(array('FROM watch_domains WHERE id' => array(array($row)))) extends RecordingPdo {
                public function lastInsertId(?string $name = null): string|false
                {
                    return '9';
                }
            };
            $cache = new \ReflectionProperty(ChangeHistory::class, 'available');
            $cache->setValue(null, array(spl_object_id($pdo) => true));
            RestoreLock::$test_state = array('state' => 'held', 'flag' => false);
            try {
                $decision = array('update' => array('registrar_iana_id' => $ids[1]),
                    'records' => array(array('registrar', 'Example Registrar', 'Example Registrar', 'rdap', $ids[0], $ids[1])),
                    'outcome' => 'accepted');
                $this->assertSame('saved', (new WatchCheck($pdo))->save($row, $decision, 0, 'watch_check'));
            } finally {
                RestoreLock::$test_state = null;
                ChangeHistory::forgetAvailability();
            }
            $inserts[] = count($pdo->statementsWith('INSERT INTO change_history'));
        }
        $this->assertSame(array(1, 0), $inserts);
    }

    public function testAPromotedLateRedemptionKeepsItsSixHourInterval()
    {
        // Checked at 08:00, promoted at 09:00: next due at 14:00
        $row = $this->known(array('id' => 1, 'stage' => 'redemption', 'drop_from' => '2027-03-11', 'drop_to' => '2027-04-10',
            'last_attempt' => '2027-03-10 08:00:00', 'next_due' => '2027-03-11 08:00:00'));
        $plan = $this->check()->plan(array($row), array(), self::T);
        $this->assertSame('2027-03-10 14:00:00', $plan['changes'][1][0]);
    }

} //@formatter:on
