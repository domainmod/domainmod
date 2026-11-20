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
 * isn't a success), afterRetry() (Retry Failed), and rowState() (the state the status page shows).
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
