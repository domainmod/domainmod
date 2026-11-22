<?php
/**
 * /tests/Unit/RdapDeadlineTest.php
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

use DomainMOD\Rdap;
use DomainMOD\Whois;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * Lookups the deadline stops before they ask anyone (Rdap::$deadline_stopped and Whois::$deadline_stopped), and IANA
 * downloads the deadline cuts off, which start no hour's wait and are kept only for one attempt (Rdap::newAttempt()),
 * played through Rdap::$test_fetch and Whois::$test_resolve with no network
 */
#[RunTestsInSeparateProcesses]
class RdapDeadlineTest extends TestCase
{
    const BOOTSTRAP = '{"services":[[["com"],["https://rdap.registry.example/"]]]}';

    protected function setUp(): void
    {
        define('DIR_TEMP', sys_get_temp_dir() . '/domainmod-rdap-deadline-' . getmypid());
        @mkdir(DIR_TEMP);
        $this->clean();
    }

    protected function tearDown(): void
    {
        Rdap::$test_fetch = null;
        Whois::$test_resolve = null;
        $this->clean();
        @rmdir(DIR_TEMP);
    }

    private function clean()
    {
        foreach (array('rdap-dns.json', 'rdap-dns.failed', 'whois-servers.json', 'whois-servers.failed') as $file) {
            @unlink(DIR_TEMP . '/' . $file);
        }
    }

    private function play()
    {
        Rdap::$test_fetch = function ($url) {
            if ($url === 'https://data.iana.org/rdap/dns.json') return array(200, self::BOOTSTRAP);
            if (strpos($url, 'https://www.iana.org/domains/root/db/') === 0) {
                return array(200, '<div>WHOIS Server</div> <div>whois.nic.zz</div>');
            }
            if ($url === 'https://rdap.registry.example/domain/example.com') {
                return array(200, json_encode(array('objectClassName' => 'domain', 'ldhName' => 'example.com', 'status' => array('active'))));
            }
            return array(500, '');
        };
    }

    public function testACutOffBootstrapDownloadStartsNoWaitAndIsTriedAgainNextAttempt()
    {
        $this->play();
        $rdap = new Rdap();

        $result = $rdap->lookup('example.com', microtime(true) + 4);
        $this->assertSame('error', $result['status']);
        $this->assertTrue($rdap->deadline_stopped);
        $this->assertFalse($rdap->domain_request_sent);
        $this->assertTrue(Rdap::bootstrapCutOff());
        $this->assertFileDoesNotExist(DIR_TEMP . '/rdap-dns.failed');

        // the same attempt keeps the cutoff, even with time
        $this->assertFalse($rdap->hasBootstrap(microtime(true) + 30));

        // the next attempt downloads it
        Rdap::newAttempt();
        $result = $rdap->lookup('example.com', microtime(true) + 30);
        $this->assertSame('ok', $result['status']);
        $this->assertFalse($rdap->deadline_stopped);
        $this->assertFalse(Rdap::bootstrapCutOff());
    }

    public function testAnExpiredCopyStillServesACutOffAttemptWithoutBeingKept()
    {
        $this->play();
        file_put_contents(DIR_TEMP . '/rdap-dns.json', self::BOOTSTRAP);
        touch(DIR_TEMP . '/rdap-dns.json', time() - 8 * 86400);
        $rdap = new Rdap();

        $this->assertSame('https://rdap.registry.example/', $rdap->serverFor('example.com', microtime(true) + 4));
        $this->assertFalse(Rdap::bootstrapCutOff());
        $this->assertTrue(Rdap::$bootstrap_stale);
        $this->assertFileDoesNotExist(DIR_TEMP . '/rdap-dns.failed');

        // a later attempt with time downloads a fresh copy
        Rdap::newAttempt();
        $rdap->serverFor('example.com', microtime(true) + 30);
        $this->assertFalse(Rdap::$bootstrap_stale);
        $this->assertGreaterThan(time() - 60, filemtime(DIR_TEMP . '/rdap-dns.json'));
    }

    public function testAnExpiredCopyWithoutTheTldSettlesNothingAfterACutOff()
    {
        $this->play();
        file_put_contents(DIR_TEMP . '/rdap-dns.json', '{"services":[[["net"],["https://rdap.other.example/"]]]}');
        touch(DIR_TEMP . '/rdap-dns.json', time() - 8 * 86400);
        $rdap = new Rdap();

        $result = $rdap->lookup('example.com', microtime(true) + 4);
        $this->assertSame('error', $result['status']);
        $this->assertTrue($rdap->deadline_stopped);
        $this->assertTrue(Rdap::downloadCutOff());
        $this->assertFalse(Rdap::bootstrapCutOff());
    }

    public function testADomainRequestTheDeadlineStoppedIsntAFailureButOneThatWasSentIs()
    {
        $this->play();
        file_put_contents(DIR_TEMP . '/rdap-dns.json', self::BOOTSTRAP);
        $rdap = new Rdap();

        $result = $rdap->lookup('example.com', microtime(true) + 4);
        $this->assertSame('error', $result['status']);
        $this->assertTrue($rdap->deadline_stopped);
        $this->assertFalse($rdap->domain_request_sent);

        // a server error after the request was sent is a failure
        $result = $rdap->lookup('broken.com', microtime(true) + 30);
        $this->assertSame('error', $result['status']);
        $this->assertFalse($rdap->deadline_stopped);
        $this->assertTrue($rdap->domain_request_sent);
    }

    public function testACutOffWhoisDiscoveryStartsNoWaitAndCachesNothing()
    {
        $this->play();
        $whois = new Whois();

        $this->assertSame(array(false, ''), $whois->serverFor('example.zz', microtime(true) + 4));
        $this->assertTrue($whois->discovery_cut_off);
        $this->assertFileDoesNotExist(DIR_TEMP . '/whois-servers.failed');

        $result = $whois->lookup('example.zz', microtime(true) + 4);
        $this->assertSame('error', $result['status']);
        $this->assertTrue($whois->deadline_stopped);
        $this->assertFalse($result['connection_failed']);

        // with time, IANA is asked
        $this->assertSame(array(true, 'whois.nic.zz'), $whois->serverFor('example.zz', microtime(true) + 30));
        $this->assertFalse($whois->discovery_cut_off);
    }

    public function testARefusedConnectionFailsButAWaitTheDeadlineEndsIsPutOff()
    {
        file_put_contents(DIR_TEMP . '/whois-servers.json', json_encode(array('zz' => array('server' => 'whois.nic.zz', 'time' => time()))));
        Whois::$test_resolve = function () {
            return '127.0.0.1:1';
        };
        // the refused connection is logged, which reads the debug setting
        define('DEBUG_MODE', 0);
        $whois = new Whois();

        $refused = $whois->lookup('example.zz', microtime(true) + 30);
        $this->assertSame('error', $refused['status']);
        $this->assertTrue($refused['connection_failed']);
        $this->assertFalse($whois->deadline_stopped);
        $this->assertFalse($whois->query_written);

        // the two-second gap to the same server doesn't fit before the deadline
        $paced = $whois->lookup('example.zz', microtime(true) + 2.5);
        $this->assertFalse($paced['connection_failed']);
        $this->assertTrue($whois->deadline_stopped);
    }
}
