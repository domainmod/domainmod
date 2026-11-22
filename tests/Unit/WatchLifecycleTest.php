<?php
/**
 * /tests/Unit/WatchLifecycleTest.php
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

use DomainMOD\WatchLifecycle;
use PHPUnit\Framework\TestCase;

/*
 * The Watch List's stages and drop estimates (WatchLifecycle), with fixed dates: every stage from status sets and dates,
 * every kind of estimate with and without an earlier check, and the TLDs with and without a profile
 */
class WatchLifecycleTest extends TestCase
{
    const TODAY = '2027-03-10';

    public function testEveryStageComesFromTheStatusesAndDates()
    {
        $stage = function (array $codes, $expiry = '2028-01-01', $found = true) {
            return WatchLifecycle::stage($codes, $expiry, $found, self::TODAY);
        };
        $this->assertSame('not_registered', $stage(array(), '1970-01-01', false));
        $this->assertSame('restore_pending', $stage(array('pendingRestore', 'pendingDelete')));
        // A domain in redemption carries pendingDelete too, and stays in redemption
        $this->assertSame('redemption', $stage(array('redemptionPeriod', 'pendingDelete')));
        $this->assertSame('redemption', $stage(array('redemptionPeriod', 'clientHold')));
        $this->assertSame('pending_delete', $stage(array('pendingDelete')));
        // The registry's date can already be a year ahead during the auto-renew grace period: the status wins
        $this->assertSame('grace', $stage(array('autoRenewPeriod', 'clientTransferProhibited'), '2028-03-01'));
        // A ccTLD with no EPP statuses and a past expiry date
        $this->assertSame('expired', $stage(array(), '2027-03-01'));
        $this->assertSame('new', $stage(array('addPeriod')));
        $this->assertSame('registered', $stage(array('clientTransferProhibited')));
        // The placeholder date never gives a stage of its own
        $this->assertSame('registered', $stage(array(), '1970-01-01'));
    }

    public function testTheDisplayedStageCoversTheCheckingStates()
    {
        $row = array('has_baseline' => 0, 'failures' => 0, 'last_attempt' => '1970-01-01 00:00:00', 'pending_stage' => '',
            'stage' => 'unknown');
        $this->assertSame('unknown', WatchLifecycle::displayStage($row)[0]);
        // An attempt that got no answer yet (such as a rate limit) is still not checked, and a first answer waiting for its
        // confirmation is being checked
        $this->assertSame('unknown', WatchLifecycle::displayStage(array('last_attempt' => '2027-03-10 09:00:00') + $row)[0]);
        $this->assertSame('checking', WatchLifecycle::displayStage(array('last_attempt' => '2027-03-10 09:00:00',
            'pending_stage' => 'not_registered') + $row)[0]);
        $this->assertSame('unable', WatchLifecycle::displayStage(array('failures' => 1, 'last_attempt' => '2027-03-10 09:00:00') + $row)[0]);
        $row['has_baseline'] = 1;
        $row['stage'] = 'registered';
        $this->assertSame(array('registered', 'Registered'), WatchLifecycle::displayStage($row));
        $this->assertSame('checking_again', WatchLifecycle::displayStage(array('pending_stage' => 'not_registered') + $row)[0]);
    }

    public function testProfilesGoByTheRegistrysTld()
    {
        $this->assertSame('uk', WatchLifecycle::registryTld('example.co.uk'));
        $this->assertSame('uk', WatchLifecycle::registryTld('example.uk'));
        $this->assertNotNull(WatchLifecycle::profileFor('example.com'));
        $this->assertNotNull(WatchLifecycle::profileFor('example.app'));
        // Sponsored TLDs have their own rules, and a TLD without a checked profile gets none
        foreach (array('example.gov', 'example.edu', 'example.mil', 'example.int', 'example.museum', 'example.de') as $domain) {
            $this->assertNull(WatchLifecycle::profileFor($domain), $domain);
        }
    }

    public function testTheRoughEstimateNeedsAKnownDateFromBeforeGrace()
    {
        $this->assertSame(array('2027-03-08', '2027-04-22', 'rough'),
            WatchLifecycle::dropWindow('grace', 'example.com', '2027-02-01', '2027-02-03 10:00:00', '1970-01-01 00:00:00'));
        // First seen already in grace: no rough estimate
        $this->assertSame(array('1970-01-01', '1970-01-01', ''),
            WatchLifecycle::dropWindow('grace', 'example.com', '1970-01-01', '2027-02-03 10:00:00', '1970-01-01 00:00:00'));
        // A date only (no status) never has one
        $this->assertSame('', WatchLifecycle::dropWindow('expired', 'example.com', '2027-02-01', '2027-02-03 10:00:00', '2027-01-01 00:00:00')[2]);
    }

    public function testTheRedemptionEstimate()
    {
        // Previous check (C) on 2027-03-01 showed an earlier stage, redemption first seen (R) on 2027-03-10: from the
        // later of C + 35 and R + 5, to R + 35
        $this->assertSame(array('2027-04-05', '2027-04-14', 'redemption'),
            WatchLifecycle::dropWindow('redemption', 'example.com', '1970-01-01', '2027-03-10 08:00:00', '2027-03-01 08:00:00'));
        // An old earlier check never pulls the start before R + 5
        $this->assertSame(array('2027-03-15', '2027-04-14', 'redemption'),
            WatchLifecycle::dropWindow('redemption', 'example.com', '1970-01-01', '2027-03-10 08:00:00', '2026-12-01 08:00:00'));
        // No earlier check: it may be at any point of its 30 days
        $this->assertSame(array('2027-03-15', '2027-04-14', 'redemption'),
            WatchLifecycle::dropWindow('redemption', 'example.com', '1970-01-01', '2027-03-10 08:00:00', '1970-01-01 00:00:00'));
    }

    public function testThePendingDeleteEstimate()
    {
        $this->assertSame(array('2027-03-10', '2027-03-15', 'pending_delete'),
            WatchLifecycle::dropWindow('pending_delete', 'example.com', '1970-01-01', '2027-03-10 08:00:00', '1970-01-01 00:00:00'));
        // Seen the day after the earlier check: from C + 5
        $this->assertSame(array('2027-03-14', '2027-03-15', 'pending_delete'),
            WatchLifecycle::dropWindow('pending_delete', 'example.com', '1970-01-01', '2027-03-10 08:00:00', '2027-03-09 08:00:00'));
        // An entry added on day 4 of pending delete has no earlier check, so its window starts at once
        $this->assertSame('2027-03-10', WatchLifecycle::dropWindow('pending_delete', 'example.net', '1970-01-01',
            '2027-03-10 08:00:00', '1970-01-01 00:00:00')[0]);
    }

    public function testNoProfileNoEstimate()
    {
        foreach (array('redemption', 'pending_delete', 'grace') as $stage) {
            $this->assertSame('', WatchLifecycle::dropWindow($stage, 'example.de', '2027-02-01', '2027-03-10 08:00:00', '2027-03-01 08:00:00')[2]);
            $this->assertSame('', WatchLifecycle::dropWindow($stage, 'example.gov', '2027-02-01', '2027-03-10 08:00:00', '2027-03-01 08:00:00')[2]);
        }
        foreach (array('registered', 'new', 'restore_pending', 'not_registered', 'unknown') as $stage) {
            $this->assertSame('', WatchLifecycle::dropWindow($stage, 'example.com', '2027-02-01', '2027-03-10 08:00:00', '2027-03-01 08:00:00')[2], $stage);
        }
    }

    public function testEveryStageAndBasisHasAName()
    {
        foreach (WatchLifecycle::STAGES as $stage) $this->assertNotSame($stage, WatchLifecycle::stageLabel($stage));
        foreach (WatchLifecycle::BASES as $basis) $this->assertNotSame('', WatchLifecycle::basisLabel($basis));
    }

} //@formatter:on
