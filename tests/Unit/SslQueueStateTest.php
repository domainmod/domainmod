<?php
/**
 * /tests/Unit/SslQueueStateTest.php
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

use DomainMOD\SslQueue;
use PHPUnit\Framework\TestCase;

/*
 * The SSL Queue's row state rules, as pure functions: afterResult() (the columns written after a lookup result that
 * isn't a success), afterRetry() (Retry Failed), rowState() (the state the status page shows), and the rules for a
 * row's domain: pastedAction() and claimAction() (link, go ahead without a domain, wait, or read domains again),
 * releaseValues() (a waiting row's release), and waitDone() (a row that has already waited never waits again).
 */
class SslQueueStateTest extends TestCase
{

    private function row(array $values = array())
    {
        return (object) ($values + array('processing' => 1, 'finished' => 0, 'failed' => 0, 'wait_for_domain' => 0,
            'already_in_queue' => 0, 'already_in_ssl' => 0, 'attempts' => 0, 'message' => ''));
    }

    public function testATimeoutAndASaveFailureEachCountAnAttempt()
    {
        $this->assertSame(array('processing' => 0, 'attempts' => 1, 'message' => 'timeout'), SslQueue::afterResult($this->row(), 'timeout'));
        $this->assertSame(array('processing' => 0, 'attempts' => 2, 'message' => 'save_failed'), SslQueue::afterResult($this->row(array('attempts' => 1)), 'save_failed'));
        $this->assertSame(array('processing' => 0, 'attempts' => 1, 'message' => 'local_error'), SslQueue::afterResult($this->row(), 'local_error'));
    }

    public function testTheThirdAttemptFailsTheRow()
    {
        $values = SslQueue::afterResult($this->row(array('attempts' => 2, 'message' => 'timeout')), 'timeout');

        $this->assertSame(3, $values['attempts']);
        $this->assertSame(1, $values['finished']);
        $this->assertSame(1, $values['failed']);
        $this->assertSame(0, $values['processing']);
        $this->assertSame(0, $values['wait_for_domain']);
        $this->assertSame('failed', SslQueue::rowState((object) ($values + (array) $this->row())));
    }

    public function testADeferralPutsTheRowBackAsItWas()
    {
        // only the claim is cleared: attempts, message, and the claim's last_attempt are left as they were
        $this->assertSame(array('processing' => 0), SslQueue::afterResult($this->row(array('attempts' => 1, 'message' => 'timeout')), 'deferred'));

        $row = $this->row(array('attempts' => 1, 'message' => 'timeout'));
        $after = (object) (SslQueue::afterResult($row, 'deferred') + (array) $row);
        $this->assertSame('retrying', SslQueue::rowState($after));
        $this->assertSame('pending', SslQueue::rowState((object) (SslQueue::afterResult($this->row(), 'deferred') + (array) $this->row())));
    }

    public function testACrashResetIsATimeout()
    {
        $this->assertSame(array('processing' => 0, 'attempts' => 1, 'message' => 'timeout'), SslQueue::afterCrash($this->row()));

        $third = SslQueue::afterCrash($this->row(array('attempts' => 2, 'message' => 'timeout')));
        $this->assertSame(1, $third['failed']);
        $this->assertSame('timeout', $third['message']);
    }

    public function testOtherFailuresFailStraightAway()
    {
        foreach (SslQueue::$failure_codes as $code) {

            $this->assertSame(array('processing' => 0, 'message' => $code, 'finished' => 1, 'failed' => 1, 'wait_for_domain' => 0),
                SslQueue::afterResult($this->row(), $code), $code);

        }
    }

    public function testRetryFailedResetsTheCount()
    {
        $values = SslQueue::afterRetry($this->row(array('finished' => 1, 'failed' => 1, 'attempts' => 3, 'message' => 'timeout')));

        $this->assertSame(0, $values['attempts']);
        $this->assertSame('', $values['message']);
        $this->assertSame(0, $values['finished']);
        $this->assertSame(0, $values['failed']);
        $this->assertSame('pending', SslQueue::rowState((object) ($values + (array) $this->row())));
    }

    public function testADomainChangedDuringTheLookupPutsTheRowBackWithoutItsDomain()
    {
        $row = $this->row(array('attempts' => 1, 'message' => 'timeout', 'domain_id' => 5, 'domain_name' => 'example.com'));
        $values = SslQueue::afterResult($row, 'domain_changed');

        // no attempt counted and nothing finished, like a deferral, but the domain is cleared for the next claim
        $this->assertSame(array('processing' => 0, 'domain_id' => 0, 'domain_name' => ''), $values);
        $this->assertSame('retrying', SslQueue::rowState((object) ($values + (array) $row)));
        $this->assertSame('pending', SslQueue::rowState((object) (SslQueue::afterResult($this->row(), 'domain_changed') + (array) $this->row())));
    }

    public function testTheRemovedDomainCodesAreGone()
    {
        foreach (array('no_domain', 'domain_not_imported', 'ambiguous_domain') as $code) {
            $this->assertNotContains($code, SslQueue::$failure_codes, $code);
        }
    }

    public function testAPastedHostIsLinkedQueuedOrAddedWithoutADomain()
    {
        $this->assertSame('link', SslQueue::pastedAction('one', ''));
        // more than one record of the best-matching name never fails or waits
        $this->assertSame('no_domain', SslQueue::pastedAction('many', ''));
        $this->assertSame('no_domain', SslQueue::pastedAction('many', 'example.com'));
        $this->assertSame('wait', SslQueue::pastedAction('none', 'example.com'));
        $this->assertSame('recheck', SslQueue::pastedAction('none', ''));
        $this->assertSame('link', SslQueue::pastedAction('none', '', 'one'));
        $this->assertSame('no_domain', SslQueue::pastedAction('none', '', 'many'));
        $this->assertSame('no_domain', SslQueue::pastedAction('none', '', 'none'));
    }

    public function testAClaimWaitsOnlyWhenTheRowHasntWaitedAlready()
    {
        $this->assertSame('link', SslQueue::claimAction('one', '', false));
        $this->assertSame('link', SslQueue::claimAction('one', '', true));
        $this->assertSame('no_domain', SslQueue::claimAction('many', 'example.com', false));
        $this->assertSame('wait', SslQueue::claimAction('none', 'example.com', false));
        $this->assertSame('recheck', SslQueue::claimAction('none', 'example.com', true));
        $this->assertSame('recheck', SslQueue::claimAction('none', '', false));
        $this->assertSame('link', SslQueue::claimAction('none', 'example.com', true, 'one'));
        $this->assertSame('no_domain', SslQueue::claimAction('none', 'example.com', true, 'many'));
        $this->assertSame('no_domain', SslQueue::claimAction('none', '', false, 'none'));
    }

    public function testAWaitingRowIsReleasedWithItsDomainOrWithoutOne()
    {
        $linked = array('wait_for_domain' => 0, 'domain_id' => 4, 'domain_name' => 'example.com');
        $none = array('wait_for_domain' => 0, 'domain_id' => 0, 'domain_name' => '');

        $this->assertSame($linked, SslQueue::releaseValues(array('one', 4, 'example.com'), false, false));
        $this->assertSame($linked, SslQueue::releaseValues(array('one', 4, 'example.com'), true, true));
        // still in the Domain Queue within WAIT_DAYS: no change, so it keeps waiting
        $this->assertNull(SslQueue::releaseValues(array('none', 0, ''), true, false));
        $this->assertSame($none, SslQueue::releaseValues(array('none', 0, ''), true, true));
        $this->assertSame($none, SslQueue::releaseValues(array('none', 0, ''), false, false));
        $this->assertSame($none, SslQueue::releaseValues(array('many', 0, ''), true, false));
    }

    public function testOnlyAReleasedRowHasWaited()
    {
        $never = (object) array('wait_for_domain' => 0, 'wait_since' => '1970-01-01 00:00:00');
        $waiting = (object) array('wait_for_domain' => 1, 'wait_since' => '2026-11-20 10:00:00');
        $released = (object) array('wait_for_domain' => 0, 'wait_since' => '2026-11-20 10:00:00');

        $this->assertFalse(SslQueue::waitDone($never));
        $this->assertFalse(SslQueue::waitDone($waiting));
        $this->assertTrue(SslQueue::waitDone($released));

        // a release keeps wait_since, so the released row has waited (and is never sent back to wait a fresh WAIT_DAYS)
        $values = SslQueue::releaseValues(array('none', 0, ''), false, true);
        $this->assertTrue(SslQueue::waitDone((object) ($values + (array) $waiting)));
        $this->assertSame('no_domain', SslQueue::claimAction('none', 'example.com', SslQueue::waitDone((object) ($values + (array) $waiting)), 'none'));
    }

    public function testTheStates()
    {
        $this->assertSame('pending', SslQueue::rowState($this->row(array('processing' => 0))));
        $this->assertSame('processing', SslQueue::rowState($this->row()));
        $this->assertSame('waiting', SslQueue::rowState($this->row(array('processing' => 0, 'wait_for_domain' => 1))));
        $this->assertSame('retrying', SslQueue::rowState($this->row(array('processing' => 0, 'attempts' => 2))));
        $this->assertSame('imported', SslQueue::rowState($this->row(array('processing' => 0, 'finished' => 1, 'message' => 'untrusted'))));
        $this->assertSame('already_in_queue', SslQueue::rowState($this->row(array('processing' => 0, 'finished' => 1, 'already_in_queue' => 1))));
        $this->assertSame('already_in_ssl', SslQueue::rowState($this->row(array('processing' => 0, 'finished' => 1, 'already_in_ssl' => 1))));
        $this->assertSame('failed', SslQueue::rowState($this->row(array('processing' => 0, 'finished' => 1, 'failed' => 1))));
    }

} //@formatter:on
