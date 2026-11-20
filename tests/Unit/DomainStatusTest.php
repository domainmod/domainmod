<?php
/**
 * /tests/Unit/DomainStatusTest.php
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

use DomainMOD\DomainStatus;
use DomainMOD\Tests\Support\FakeDatabase;
use PHPUnit\Framework\TestCase;

/*
 * DomainStatus: the known codes and how RDAP's spellings map to them, the lock rule, the problem statuses, the change
 * rules Refresh uses for its result lines, notes, and history, the filter's values and SQL, and the edit page's and
 * list's HTML.
 */
class DomainStatusTest extends TestCase
{

    protected function tearDown(): void
    {
        DomainStatus::resetAvailable();
    }

    private function row(array $values)
    {
        return (object) ($values + array('id' => 7, 'ds1_transfer_lock' => 0, 'ds1_epp_status' => '',
            'ds1_epp_status_time' => '1970-01-01 00:00:00'));
    }

    public function testEveryRdapSpellingAndEppNameMapsToItsCode()
    {
        $this->assertSame('ok', DomainStatus::code('active'));
        $this->assertSame('ok', DomainStatus::code('ok'));
        $this->assertSame('clientTransferProhibited', DomainStatus::code('client transfer prohibited'));
        $this->assertSame('clientTransferProhibited', DomainStatus::code('clientTransferProhibited'));
        $this->assertSame('clientTransferProhibited', DomainStatus::code('  Client Transfer  PROHIBITED '));
        $this->assertSame('autoRenewPeriod', DomainStatus::code('auto renew period'));
        $this->assertSame('redemptionPeriod', DomainStatus::code('redemption period'));
        $this->assertSame('transferProhibited', DomainStatus::code('transfer prohibited'));
        $this->assertSame('locked', DomainStatus::code('locked'));
        $this->assertSame('', DomainStatus::code('obscured'));
        $this->assertSame('', DomainStatus::code(array('active')));

        foreach (DomainStatus::CODES as $code) {
            $this->assertSame($code, DomainStatus::code($code));
            $spelled = strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $code));
            $this->assertSame($code, DomainStatus::code($spelled), $spelled);
            $this->assertStringNotContainsString(',', $code);
            $this->assertNotSame('', DomainStatus::meaning($code), $code);
        }
    }

    public function testAReadKeepsKnownCodesInTheStoredOrder()
    {
        $this->assertSame(array('found', array('ok', 'clientTransferProhibited')),
            DomainStatus::fromRdap(array('client transfer prohibited', 'active', 'obscured', 'active')));

        // a trailing URL is dropped
        $this->assertSame(array('found', array('clientTransferProhibited')),
            DomainStatus::fromRdap(array('clientTransferProhibited https://icann.org/epp#clientTransferProhibited')));
    }

    public function testNoStatusOrNoKnownCodesIsNone()
    {
        $this->assertSame(array('none', array()), DomainStatus::fromRdap(null, false));
        $this->assertSame(array('none', array()), DomainStatus::fromRdap(array()));
        $this->assertSame(array('none', array()), DomainStatus::fromRdap(array('obscured', 'proxy')));
    }

    public function testAMalformedStatusIsIncomplete()
    {
        $this->assertSame('incomplete', DomainStatus::fromRdap(null)[0]);
        $this->assertSame('incomplete', DomainStatus::fromRdap('active')[0]);
        $this->assertSame('incomplete', DomainStatus::fromRdap(array('active', array('client transfer prohibited')))[0]);
        $this->assertSame('incomplete', DomainStatus::fromRdap(array(1 => 'active'))[0]);
        $this->assertSame('incomplete', DomainStatus::fromRdap((object) array('0' => 'active'))[0]);

        // more than 50 entries, with the lock last
        $this->assertSame('incomplete', DomainStatus::fromRdap(array_merge(array_fill(0, 50, 'active'), array('client transfer prohibited')))[0]);
        $this->assertSame('found', DomainStatus::fromRdap(array_fill(0, 50, 'active'))[0]);

        // an entry over 100 characters once its URL is removed, and one that's only long because of its URL
        $this->assertSame('incomplete', DomainStatus::fromRdap(array(str_repeat('x', 101)))[0]);
        $this->assertSame('found', DomainStatus::fromRdap(array('clientTransferProhibited https://example.com/' . str_repeat('x', 200)))[0]);
    }

    public function testTheLockRule()
    {
        foreach (array('clientTransferProhibited', 'serverTransferProhibited', 'transferProhibited') as $code) {
            $this->assertSame(DomainStatus::LOCK_ON, DomainStatus::lockFor(array('ok', $code)));
        }
        $this->assertSame(DomainStatus::LOCK_OFF, DomainStatus::lockFor(array('ok')));
        $this->assertSame(DomainStatus::LOCK_OFF, DomainStatus::lockFor(array('clientUpdateProhibited')));
        $this->assertSame(DomainStatus::LOCK_UNKNOWN, DomainStatus::lockFor(array()));
        $this->assertSame(DomainStatus::LOCK_UNKNOWN, DomainStatus::lockFor(array('locked')));
        $this->assertSame(DomainStatus::LOCK_UNKNOWN, DomainStatus::lockFor(array('locked', 'clientUpdateProhibited')));
        $this->assertSame(DomainStatus::LOCK_ON, DomainStatus::lockFor(array('locked', 'transferProhibited')));
    }

    public function testTheStates()
    {
        $this->assertSame('not_checked', DomainStatus::state(0, '1970-01-01 00:00:00'));
        $this->assertSame('unknown', DomainStatus::state(0, '2026-11-19 10:00:00'));
        $this->assertSame('unlocked', DomainStatus::state(1, '2026-11-19 10:00:00'));
        $this->assertSame('locked', DomainStatus::state('2', '2026-11-19 10:00:00'));

        // Couldn't Check: never read, and a lookup failed. A domain read before keeps its state.
        $this->assertSame('failed', DomainStatus::state(0, '1970-01-01 00:00:00', '2026-11-20 10:00:00'));
        $this->assertSame('not_checked', DomainStatus::state(0, '1970-01-01 00:00:00', '1970-01-01 00:00:00'));
        $this->assertSame('locked', DomainStatus::state(2, '2026-11-19 10:00:00', '2026-11-20 10:00:00'));
    }

    public function testALaterFailedLookupIsNoted()
    {
        $this->assertSame('2026-11-20', DomainStatus::failedDate($this->row(array('ds1_epp_status_time' => '2026-11-19 10:00:00',
            'ds1_epp_status_failed_time' => '2026-11-20 10:00:00'))));
        // a failure before the last successful read, or none, isn't
        $this->assertSame('', DomainStatus::failedDate($this->row(array('ds1_epp_status_time' => '2026-11-21 10:00:00',
            'ds1_epp_status_failed_time' => '2026-11-20 10:00:00'))));
        $this->assertSame('', DomainStatus::failedDate($this->row(array('ds1_epp_status_time' => '2026-11-21 10:00:00'))));
        $this->assertSame('2026-11-20', DomainStatus::failedDate($this->row(array('ds1_epp_status_failed_time' => '2026-11-20 10:00:00'))));

        $locked = $this->row(array('ds1_transfer_lock' => 2, 'ds1_epp_status' => 'clientTransferProhibited',
            'ds1_epp_status_time' => '2026-11-19 10:00:00', 'ds1_epp_status_failed_time' => '2026-11-20 10:00:00'));
        $html = DomainStatus::editSection($locked, true);
        $this->assertStringContainsString('The transfer lock is on.', $html);
        $this->assertStringContainsString('Lock status last checked on <bdi dir="ltr">2026-11-19</bdi>.', $html);
        $this->assertStringContainsString('The latest check, on <bdi dir="ltr">2026-11-20</bdi>, failed, so this is the last status that could be read.', $html);
        $this->assertStringContainsString('Last checked on 2026-11-19. The latest check failed on 2026-11-20.', DomainStatus::listCell($locked));
        $this->assertSame(array('Locked', 'clientTransferProhibited', '2026-11-19'), DomainStatus::exportValues($locked));

        $never = $this->row(array('ds1_epp_status_failed_time' => '2026-11-20 10:00:00'));
        $html = DomainStatus::editSection($never, true);
        $this->assertStringContainsString('The lock status couldn&#039;t be checked on <bdi dir="ltr">2026-11-20</bdi>. This can happen', $html);
        $this->assertStringNotContainsString('last checked', $html);
        $cell = DomainStatus::listCell($never);
        $this->assertStringContainsString("Couldn&#039;t Check", $cell);
        $this->assertStringContainsString('Couldn&#039;t check on 2026-11-20', $cell);
        $this->assertSame(array("Couldn't Check", '', ''), DomainStatus::exportValues($never));
    }

    public function testTheProblemStatuses()
    {
        $this->assertTrue(DomainStatus::isOnHold(array('serverHold')));
        $this->assertTrue(DomainStatus::isOnHold(array('clientHold')));
        $this->assertTrue(DomainStatus::isBeingDeleted(array('redemptionPeriod')));
        $this->assertTrue(DomainStatus::isBeingDeleted(array('pendingDelete')));
        $this->assertTrue(DomainStatus::isBeingDeleted(array('pendingRestore')));
        $this->assertSame('hold,deletion', DomainStatus::problem(array('serverHold', 'pendingDelete')));
        $this->assertSame('', DomainStatus::problem(array('ok', 'clientTransferProhibited')));
    }

    public function testTheStoredColumnRoundTrips()
    {
        $this->assertSame('ok,clientTransferProhibited', DomainStatus::toColumn(array('clientTransferProhibited', 'ok', 'ok', 'bogus')));
        $this->assertSame(array('ok', 'clientTransferProhibited'), DomainStatus::fromColumn('clientTransferProhibited,ok,bogus'));
        $this->assertSame(array(), DomainStatus::fromColumn(''));
        $this->assertLessThan(1000, strlen(DomainStatus::toColumn(DomainStatus::CODES)));
    }

    public function testTheFirstReadIsNotAChange()
    {
        $changes = DomainStatus::changes(array('transfer_lock' => 0, 'codes' => array(), 'time' => '1970-01-01 00:00:00'),
            array('read' => 'found', 'codes' => array('ok')));

        $this->assertSame(array(), $changes['lines']);
        $this->assertSame(array(), $changes['notes']);
        $this->assertSame(array(), $changes['history']);
        $this->assertSame(array('Lock status checked. The transfer lock is off.'), $changes['info']);
    }

    public function testLockedAndNotLockedMovesAreChanges()
    {
        $off = DomainStatus::changes(array('transfer_lock' => 2, 'codes' => array('clientTransferProhibited'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('ok')));
        $this->assertSame(array('Transfer lock turned off.'), $off['lines']);
        $this->assertSame(array('Transfer lock turned off, according to RDAP.'), $off['notes']);
        $this->assertSame(array('2', '1'), $off['history']['transfer_lock']);
        $this->assertSame(array('clientTransferProhibited', 'ok'), $off['history']['epp_status']);
        $this->assertArrayNotHasKey('registry_problem', $off['history']);

        $on = DomainStatus::changes(array('transfer_lock' => 1, 'codes' => array('ok'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('clientTransferProhibited')));
        $this->assertSame(array('Transfer lock turned on.'), $on['lines']);
        $this->assertSame(array('1', '2'), $on['history']['transfer_lock']);
    }

    public function testMovesToOrFromUnknownAreQuiet()
    {
        $to_unknown = DomainStatus::changes(array('transfer_lock' => 2, 'codes' => array('clientTransferProhibited'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'none', 'codes' => array()));
        $this->assertSame(array(), $to_unknown['lines']);
        $this->assertSame(array(), $to_unknown['history']);

        $from_unknown = DomainStatus::changes(array('transfer_lock' => 0, 'codes' => array(), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('ok')));
        $this->assertSame(array(), $from_unknown['lines']);
        $this->assertSame(array(), $from_unknown['history']);
    }

    public function testAProblemChangeCountsOnlyBetweenTwoFoundReads()
    {
        $cleared = DomainStatus::changes(array('transfer_lock' => 1, 'codes' => array('serverHold'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('ok')));
        $this->assertSame(array('The registry no longer lists this domain as on hold.'), $cleared['lines']);
        $this->assertSame(array('hold', ''), $cleared['history']['registry_problem']);

        $appeared = DomainStatus::changes(array('transfer_lock' => 2, 'codes' => array('clientTransferProhibited'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('clientTransferProhibited', 'redemptionPeriod')));
        $this->assertSame(array('The registry lists this domain as being deleted or restored.'), $appeared['lines']);
        $this->assertSame(array('', 'deletion'), $appeared['history']['registry_problem']);

        // serverHold followed by no usable codes (or WHOIS) records nothing
        foreach (array('none', 'whois') as $read) {
            $nothing = DomainStatus::changes(array('transfer_lock' => 1, 'codes' => array('serverHold'), 'time' => '2026-11-01 00:00:00'),
                array('read' => $read, 'codes' => array()));
            $this->assertSame(array(), $nothing['lines'], $read);
            $this->assertSame(array(), $nothing['history'], $read);
        }
    }

    public function testOtherCodeChangesAreQuietButCountForTheHistory()
    {
        $changes = DomainStatus::changes(array('transfer_lock' => 2, 'codes' => array('clientTransferProhibited'), 'time' => '2026-11-01 00:00:00'),
            array('read' => 'found', 'codes' => array('autoRenewPeriod', 'clientTransferProhibited')));

        $this->assertSame(array(), $changes['lines']);
        $this->assertSame(array(), $changes['notes']);
        $this->assertSame(array('epp_status'), array_keys($changes['history']));
    }

    public function testALaterStartWins()
    {
        $this->assertTrue(DomainStatus::isNewer('2026-11-19 10:00:01', '2026-11-19 10:00:00'));
        $this->assertFalse(DomainStatus::isNewer('2026-11-19 10:00:00', '2026-11-19 10:00:00'));
        $this->assertFalse(DomainStatus::isNewer('2026-11-19 09:59:59', '2026-11-19 10:00:00'));
        $this->assertTrue(DomainStatus::isNewer('2026-11-19 09:59:59', '1970-01-01 00:00:00'));
    }

    public function testTheFilterValuesAndSql()
    {
        $this->assertSame('unlocked', DomainStatus::normalizeFilter('unlocked'));
        $this->assertSame('', DomainStatus::normalizeFilter('Unlocked'));
        $this->assertSame('', DomainStatus::normalizeFilter(array('unlocked')));
        $this->assertSame('', DomainStatus::normalizeFilter('unlocked', false));

        $this->assertSame(" AND d.transfer_lock = '2' ", DomainStatus::filterCondition('locked'));
        $this->assertSame(" AND d.transfer_lock = '1' ", DomainStatus::filterCondition('unlocked', 'd.'));
        $this->assertSame(" AND transfer_lock = '0' AND NOT ((epp_status_time = '1970-01-01 00:00:00' AND epp_status_failed_time > '1970-01-01 00:00:00')) ",
            DomainStatus::filterCondition('unknown', ''));
        $this->assertSame(" AND (d.epp_status_time = '1970-01-01 00:00:00' AND d.epp_status_failed_time > '1970-01-01 00:00:00') ",
            DomainStatus::filterCondition('failed'));
        $this->assertSame(" AND (FIND_IN_SET('clientHold', d.epp_status) OR FIND_IN_SET('serverHold', d.epp_status)) ",
            DomainStatus::filterCondition('hold'));
        $this->assertStringContainsString("FIND_IN_SET('pendingRestore', epp_status)", DomainStatus::filterCondition('deletion', ''));
        $this->assertSame('', DomainStatus::filterCondition(''));
        $this->assertSame('', DomainStatus::filterCondition("x' OR 1=1"));
        $this->assertSame(DomainStatus::FILTERS, array_keys(DomainStatus::filterChoices()));
    }

    public function testAvailabilityNeedsTheVersionAndEveryColumn()
    {
        $this->assertTrue(DomainStatus::isAvailable(new FakeDatabase(array('db_version' => '4.27.0', 'COLUMNS' => '5'))));
        DomainStatus::resetAvailable();
        $this->assertFalse(DomainStatus::isAvailable(new FakeDatabase(array('db_version' => '4.26.0', 'COLUMNS' => '5'))));
        DomainStatus::resetAvailable();
        // a database upgraded before epp_status_failed_time was added has only four
        $this->assertFalse(DomainStatus::isAvailable(new FakeDatabase(array('db_version' => '4.27.0', 'COLUMNS' => '4'))));
        DomainStatus::resetAvailable();
        $this->assertFalse(DomainStatus::isAvailable(new FakeDatabase(array('db_version' => new \RuntimeException('no')))));
    }

    public function testTheEditSection()
    {
        $html = DomainStatus::editSection($this->row(array('ds1_transfer_lock' => 1, 'ds1_epp_status' => 'ok,serverHold',
            'ds1_epp_status_time' => '2026-11-19 10:00:00')), true);

        $this->assertStringContainsString('callout-warning', $html);
        $this->assertStringContainsString('The transfer lock is off.', $html);
        $this->assertStringContainsString('Lock status last checked on <bdi dir="ltr">2026-11-19</bdi>.', $html);
        $this->assertStringContainsString('<code dir="ltr">serverHold</code>: the registry has put the domain on hold', $html);

        $not_checked = DomainStatus::editSection($this->row(array()), true);
        $this->assertStringContainsString("The lock status hasn&#039;t been checked yet. Click Refresh to check it.", $not_checked);
        $this->assertStringNotContainsString('last checked', $not_checked);
        $this->assertStringNotContainsString('Click Refresh', DomainStatus::editSection($this->row(array()), false));

        $restoring = DomainStatus::editSection($this->row(array('ds1_epp_status' => 'pendingDelete,pendingRestore',
            'ds1_epp_status_time' => '2026-11-19 10:00:00')), false);
        $this->assertStringContainsString('The registry is restoring this domain.', $restoring);
        $this->assertStringNotContainsString('The registry is deleting this domain', $restoring);
    }

    public function testTheListCellAndBadges()
    {
        $cell = DomainStatus::listCell($this->row(array('ds1_transfer_lock' => 1, 'ds1_epp_status_time' => '2026-11-19 10:00:00')));
        $this->assertStringContainsString('edit.php?did=7#domainmod-section-registry-status', $cell);
        $this->assertStringContainsString('bi-unlock', $cell);
        $this->assertStringContainsString('Not Locked', $cell);
        $this->assertStringContainsString('Checked 2026-11-19', $cell);

        $not_checked = DomainStatus::listCell($this->row(array()));
        $this->assertStringContainsString('Not Checked', $not_checked);
        $this->assertStringNotContainsString('tooltip', $not_checked);

        $this->assertSame('', DomainStatus::problemBadges($this->row(array('ds1_epp_status' => 'ok'))));
        $badges = DomainStatus::problemBadges($this->row(array('ds1_epp_status' => 'serverHold,pendingRestore,pendingDelete')));
        $this->assertStringContainsString('bi-slash-circle', $badges);
        $this->assertStringContainsString('bi-arrow-counterclockwise', $badges);
        $this->assertStringNotContainsString('bi-trash', $badges);
    }

    public function testTheExportValues()
    {
        $this->assertSame(array('Not Checked', '', ''), DomainStatus::exportValues($this->row(array())));
        $this->assertSame(array('Locked', 'ok,clientTransferProhibited', '2026-11-19'),
            DomainStatus::exportValues($this->row(array('ds1_transfer_lock' => 2, 'ds1_epp_status' => 'ok,clientTransferProhibited',
                'ds1_epp_status_time' => '2026-11-19 10:00:00'))));
        $this->assertCount(3, DomainStatus::exportHeadings());
    }

} //@formatter:on
