<?php
/**
 * /tests/Unit/SslQueueStatusPageTest.php
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
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The SSL Queue status page's pure rules: retryAction() (what Retry Failed does with each failed row), progressCounts()
 * (the progress line), freshClaim() (a claim that keeps the processing notice up), and exportText() (how a text cell is
 * prepared for the CSV export).
 */
class SslQueueStatusPageTest extends TestCase
{

    private function row($id, $exact, $processing = 0)
    {
        return (object) array('id' => $id, 'exact_domain' => $exact, 'processing' => $processing);
    }

    public function testAFailedRowWithNoOtherUnfinishedRowIsReopened()
    {
        $this->assertSame(array('reopen'), SslQueue::retryAction($this->row(1, 0), array()));
        $this->assertSame(array('reopen'), SslQueue::retryAction($this->row(1, 1), array()));
    }

    public function testAnotherExactDomainRowMarksTheFailedRowWhateverItsKind()
    {
        $this->assertSame(array('mark'), SslQueue::retryAction($this->row(1, 0), array($this->row(2, 1))));
        $this->assertSame(array('mark'), SslQueue::retryAction($this->row(1, 1), array($this->row(2, 1, 1))));

        // after a tick box added an exact-domain row beside a claimed pasted row, the exact-domain row still wins
        $this->assertSame(array('mark'), SslQueue::retryAction($this->row(1, 1), array($this->row(2, 0, 1), $this->row(3, 1))));
    }

    public function testAFailedExactDomainRowUpgradesAnUnclaimedPastedRow()
    {
        $this->assertSame(array('mark_upgrade', 2), SslQueue::retryAction($this->row(1, 1), array($this->row(2, 0))));
    }

    public function testAFailedExactDomainRowStaysFailedWhileThePastedRowIsBeingChecked()
    {
        $this->assertSame(array('busy'), SslQueue::retryAction($this->row(1, 1), array($this->row(2, 0, 1))));
    }

    public function testAFailedPastedRowBesideAnotherPastedRowIsMarked()
    {
        $this->assertSame(array('mark'), SslQueue::retryAction($this->row(1, 0), array($this->row(2, 0))));
        $this->assertSame(array('mark'), SslQueue::retryAction($this->row(1, 0), array($this->row(2, 0, 1))));
    }

    public function testProgressCountsFinishedAndFailedRowsAsCompleted()
    {
        $rows = array((object) array('finished' => 1), (object) array('finished' => 1), (object) array('finished' => 0));

        $this->assertSame(array(2, 3), SslQueue::progressCounts($rows));
        $this->assertSame(array(0, 0), SslQueue::progressCounts(array()));
    }

    public function testOnlyARecentClaimCountsAsProcessing()
    {
        $now = gmmktime(12, 0, 0, 1, 15, 2027);
        $claim = function ($processing, $seconds_ago) use ($now) {
            return (object) array('processing' => $processing, 'last_attempt' => gmdate('Y-m-d H:i:s', $now - $seconds_ago));
        };

        $this->assertTrue(SslQueue::freshClaim($claim(1, 60), $now));
        $this->assertTrue(SslQueue::freshClaim($claim(1, SslQueue::CLAIM_TIMEOUT), $now));

        // a claim older than CLAIM_TIMEOUT is one a stopped run left behind, and an unclaimed row isn't processing
        $this->assertFalse(SslQueue::freshClaim($claim(1, SslQueue::CLAIM_TIMEOUT + 1), $now));
        $this->assertFalse(SslQueue::freshClaim($claim(0, 60), $now));
    }

    public function testExportTextIsDecodedThenStrippedThenProtected()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);

        // a stored issuer is escaped, and the CSV shows it as the table does
        $this->assertSame('Example & Sons', $queue->exportText('Example &amp; Sons'));

        // a direction mark in front can't hide a formula from the check
        $this->assertSame("'=HYPERLINK(\"x\")", $queue->exportText("\u{2066}=HYPERLINK(&quot;x&quot;)\u{2069}"));
        $this->assertSame("'=1+1", $queue->exportText("\u{2066}\u{2069}=1+1"));

        // a display name stored without cleanup (shown as Added By) is protected the same way
        $this->assertSame("'=Test Admin", $queue->exportText("\u{2066}=Test Admin"));
        $this->assertSame('www.example.com', $queue->exportText('www.example.com'));
    }

} //@formatter:on
