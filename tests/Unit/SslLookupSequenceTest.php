<?php
/**
 * /tests/Unit/SslLookupSequenceTest.php
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

use DomainMOD\SslLookup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * SslLookup::fetch()'s hostname sequence: which names are tried, recorded by a $test_fetch spy, with the time from the
 * $test_clock hook. A pasted host (no www. attempt) is tried once. A tick-box domain falls back to www. only after a
 * quick failure with enough time left. A pacing deferral on either name defers the whole lookup.
 */
class SslLookupSequenceTest extends TestCase
{
    private $tried = array();
    private $deadlines = array();
    private $now = 1000.0;

    protected function setUp(): void
    {
        SslLookup::$test_clock = function () { return $this->now; };
    }

    protected function tearDown(): void
    {
        SslLookup::$test_fetch = null;
        SslLookup::$test_clock = null;
    }

    // The spy: each hostname's canned code, and how far the clock moves while it's "looked up"
    private function spy(array $codes, array $seconds = array())
    {
        SslLookup::$test_fetch = function ($host, $deadline) use ($codes, $seconds) {
            $this->tried[] = $host;
            $this->deadlines[] = $deadline;
            $this->now += $seconds[$host] ?? 1;
            return array($codes[$host] ?? 'no_dns', array('host' => $host));
        };
    }

    public function testAPastedHostIsTriedOnce()
    {
        $this->spy(array('shop.example.com' => 'no_dns'));

        $result = (new SslLookup())->fetch('shop.example.com', $this->now + 15, false);

        $this->assertSame('no_dns', $result[0]);
        $this->assertSame(array('shop.example.com'), $this->tried);
    }

    public function testADeferralOnAPastedHostIsDeferred()
    {
        $this->spy(array('shop.example.com' => 'deferred'));

        $this->assertSame('deferred', (new SslLookup())->fetch('shop.example.com', $this->now + 15, false)[0]);
        $this->assertSame(array('shop.example.com'), $this->tried);
    }

    public function testADeferralOnTheBareDomainDefersTheRow()
    {
        $this->spy(array('example.com' => 'deferred'));

        $this->assertSame('deferred', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
        $this->assertSame(array('example.com'), $this->tried);
    }

    public function testADeferralOnTheWwwNameDefersTheRow()
    {
        $this->spy(array('example.com' => 'no_dns', 'www.example.com' => 'deferred'));

        $this->assertSame('deferred', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
        $this->assertSame(array('example.com', 'www.example.com'), $this->tried);
    }

    public static function fastFailures()
    {
        return array('no_dns' => array('no_dns'), 'no_connect' => array('no_connect'), 'no_tls' => array('no_tls'),
            'name_mismatch' => array('name_mismatch'));
    }

    #[DataProvider('fastFailures')]
    public function testAFastFailureIsFollowedByWww($code)
    {
        $this->spy(array('example.com' => $code, 'www.example.com' => 'ok'));

        $result = (new SslLookup())->fetch('example.com', $this->now + 30, true);

        $this->assertSame('ok', $result[0]);
        $this->assertSame('www.example.com', $result[1]['host']);
        $this->assertSame(array('example.com', 'www.example.com'), $this->tried);

        // both names share the one deadline
        $this->assertSame(array(1030.0, 1030.0), $this->deadlines);
    }

    public function testADomainStartingWithWwwStillGetsTheWwwAttempt()
    {
        $this->spy(array('www.example' => 'no_tls', 'www.www.example' => 'ok'));

        $this->assertSame('ok', (new SslLookup())->fetch('www.example', $this->now + 30, true)[0]);
        $this->assertSame(array('www.example', 'www.www.example'), $this->tried);
    }

    public function testAResetOnTheBareDomainIsFollowedByWww()
    {
        $deadlines = array();
        SslLookup::$test_fetch = function ($host, $deadline) use (&$deadlines) {
            $this->tried[] = $host;
            $deadlines[] = $deadline;
            $this->now += 1;
            return ($host === 'example.com') ? array('timeout', array('host' => $host, 'reset' => true)) : array('ok', array('host' => $host));
        };

        $deadline = $this->now + 30;
        $this->assertSame('ok', (new SslLookup())->fetch('example.com', $deadline, true)[0]);
        $this->assertSame(array('example.com', 'www.example.com'), $this->tried);

        // the www. name gets what's left of the same deadline, not a new one
        $this->assertSame(array($deadline, $deadline), $deadlines);
    }

    public function testWwwIsntTriedAfterAResetWithTooLittleTimeLeft()
    {
        // the reset takes 7 of 15 seconds, so fewer than MIN_LOOKUP_TIME are left for www.
        SslLookup::$test_fetch = function ($host, $deadline) {
            $this->tried[] = $host;
            $this->now += 7;
            return array('timeout', array('host' => $host, 'reset' => true));
        };

        $this->assertSame('timeout', (new SslLookup())->fetch('example.com', $this->now + 15, true)[0]);
        $this->assertSame(array('example.com'), $this->tried);
    }

    public function testWhenWwwFailsAfterAResetTheRetryableTimeoutIsKept()
    {
        SslLookup::$test_fetch = function ($host, $deadline) {
            $this->tried[] = $host;
            $this->now += 1;
            return ($host === 'example.com') ? array('timeout', array('host' => $host, 'reset' => true)) : array('no_dns', array('host' => $host));
        };

        $this->assertSame('timeout', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
        $this->assertSame(array('example.com', 'www.example.com'), $this->tried);
    }

    public function testATimeoutOnTheBareDomainIsntFollowedByWww()
    {
        $this->spy(array('example.com' => 'timeout'));

        $this->assertSame('timeout', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
        $this->assertSame(array('example.com'), $this->tried);
    }

    public function testWwwIsntTriedWithTooLittleTimeLeft()
    {
        // the bare domain's lookup leaves less than MIN_LOOKUP_TIME
        $this->spy(array('example.com' => 'no_connect'), array('example.com' => 8));

        $this->assertSame('no_connect', (new SslLookup())->fetch('example.com', $this->now + 15, true)[0]);
        $this->assertSame(array('example.com'), $this->tried);
    }

    public function testTheWwwAttemptIsntStoppedByASecondTimeCheck()
    {
        // exactly MIN_LOOKUP_TIME is left after the bare domain, and the clock then moves on a little each time it's read
        $this->spy(array('example.com' => 'no_connect', 'www.example.com' => 'ok'), array('example.com' => 20));
        $reads = 0;
        SslLookup::$test_clock = function () use (&$reads) { return $this->now + ($reads++ > 1 ? 0.001 * $reads : 0); };

        $this->assertSame('ok', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
        $this->assertSame(array('example.com', 'www.example.com'), $this->tried);
    }

    public function testWhenBothFailTheBareDomainsCodeIsKept()
    {
        $this->spy(array('example.com' => 'name_mismatch', 'www.example.com' => 'no_dns'));

        $this->assertSame('name_mismatch', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
    }

    public function testARetryableWwwFailureIsKept()
    {
        $this->spy(array('example.com' => 'no_dns', 'www.example.com' => 'timeout'));

        $this->assertSame('timeout', (new SslLookup())->fetch('example.com', $this->now + 30, true)[0]);
    }

    public function testNoHostStartsWithTooLittleTime()
    {
        $this->spy(array('example.com' => 'ok'));

        $this->assertSame('timeout', (new SslLookup())->fetch('example.com', $this->now + 9, false)[0]);
        $this->assertSame(array(), $this->tried);
    }

} //@formatter:on
