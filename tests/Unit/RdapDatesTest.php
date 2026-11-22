<?php
/**
 * /tests/Unit/RdapDatesTest.php
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
 * What the Watch List added to Rdap and Whois: the registration and deletion dates, where the expiry date came from,
 * whether the answer came through a redirect, whether the domain's own request was sent, and whether the bootstrap file
 * in use is a stale copy, played through Rdap::$test_fetch (the real fetch() path, with no network)
 */
#[RunTestsInSeparateProcesses]
class RdapDatesTest extends TestCase
{
    const BOOTSTRAP = '{"services":[[["com","net"],["https://rdap.registry.example/"]]]}';
    const REGISTRY = 'https://rdap.registry.example/domain/';
    const REGISTRAR = 'https://rdap.registrar.example/domain/';

    protected function setUp(): void
    {
        define('DIR_TEMP', sys_get_temp_dir() . '/domainmod-rdap-dates-' . getmypid());
        @mkdir(DIR_TEMP);
        @unlink(DIR_TEMP . '/rdap-dns.json');
        @unlink(DIR_TEMP . '/rdap-dns.failed');
    }

    protected function tearDown(): void
    {
        Rdap::$test_fetch = null;
        @unlink(DIR_TEMP . '/rdap-dns.json');
        @unlink(DIR_TEMP . '/rdap-dns.failed');
        @rmdir(DIR_TEMP);
    }

    private function record($domain, array $events, array $extra = array())
    {
        $list = array();
        foreach ($events as $action => $date) $list[] = array('eventAction' => $action, 'eventDate' => $date);
        return json_encode($extra + array('objectClassName' => 'domain', 'ldhName' => $domain, 'status' => array('active'),
            'events' => $list));
    }

    // Answers by URL, each array(status, body, Location), with anything else a 404
    private function play(array $answers)
    {
        Rdap::$test_fetch = function ($url) use ($answers) {
            if ($url === 'https://data.iana.org/rdap/dns.json') return $answers[$url] ?? array(200, self::BOOTSTRAP);
            return $answers[$url] ?? array(404, '');
        };
    }

    public function testTheRegistrarsExpiryDateIsPreferredAndItsSourceSaid()
    {
        $this->play(array(
            self::REGISTRY . 'example.com' => array(200, $this->record('example.com', array('registration' => '2001-05-02T10:00:00Z',
                'expiration' => '2028-05-02T10:00:00Z', 'deletion' => '2020-01-01T00:00:00Z'),
                array('links' => array(array('rel' => 'related', 'href' => self::REGISTRAR . 'example.com', 'type' => 'application/rdap+json'))))),
            self::REGISTRAR . 'example.com' => array(200, $this->record('example.com', array('expiration' => '2027-05-02T10:00:00Z')))));
        $rdap = new Rdap();
        $result = $rdap->lookup('example.com', microtime(true) + 30);
        $this->assertSame('ok', $result['status']);
        $this->assertSame(array('2027-05-02', 'registrar', '2001-05-02', '2020-01-01', false),
            array($result['expiry_date'], $result['expiry_source'], $result['registration_date'], $result['deletion_date'], $result['redirected']));
        $this->assertTrue($rdap->domain_request_sent);
    }

    public function testTheRegistrysDateWhenTheRegistrarFails()
    {
        $this->play(array(
            self::REGISTRY . 'example.com' => array(200, $this->record('example.com', array('expiration' => '2028-05-02T10:00:00Z'),
                array('links' => array(array('rel' => 'related', 'href' => self::REGISTRAR . 'example.com'))))),
            self::REGISTRAR . 'example.com' => array(500, '')));
        $result = (new Rdap())->lookup('example.com', microtime(true) + 30);
        $this->assertSame(array('2028-05-02', 'registry'), array($result['expiry_date'], $result['expiry_source']));

        // A registry date with no registrar link
        $this->play(array(self::REGISTRY . 'example.com' => array(200, $this->record('example.com', array('expiration' => '2029-05-02T10:00:00Z')))));
        $result = (new Rdap())->lookup('example.com', microtime(true) + 30);
        $this->assertSame(array('2029-05-02', 'registry'), array($result['expiry_date'], $result['expiry_source']));

        // No expiry date at all: no source
        $this->play(array(self::REGISTRY . 'example.net' => array(200, $this->record('example.net', array()))));
        $result = (new Rdap())->lookup('example.net', microtime(true) + 30);
        $this->assertSame(array('', ''), array($result['expiry_date'], $result['expiry_source']));
    }

    public function testAStatusOnlyLookupReadsNoDates()
    {
        $this->play(array(self::REGISTRY . 'example.com' => array(200, $this->record('example.com',
            array('registration' => '2001-05-02T10:00:00Z', 'expiration' => '2028-05-02T10:00:00Z', 'deletion' => '2020-01-01T00:00:00Z')))));
        $result = (new Rdap())->lookup('example.com', microtime(true) + 30, true);
        $this->assertSame(array('', '', '', ''), array($result['registration_date'], $result['deletion_date'], $result['expiry_date'],
            $result['expiry_source']));
    }

    public function testANotFoundSaysWhetherItCameThroughARedirect()
    {
        $this->play(array());
        $result = (new Rdap())->lookup('example.com', microtime(true) + 30);
        $this->assertSame(array('not_found', false), array($result['status'], $result['redirected']));

        $this->play(array(self::REGISTRY . 'example.com' => array(302, '', 'https://moved.example/domain/example.com')));
        $result = (new Rdap())->lookup('example.com', microtime(true) + 30);
        $this->assertSame(array('not_found', true), array($result['status'], $result['redirected']));
    }

    public function testTheRequestFlagIsOnlySetOnceTheDomainsRequestStarts()
    {
        $this->play(array());
        $rdap = new Rdap();
        $rdap->lookup('example.com', microtime(true) + 30);
        $this->assertTrue($rdap->domain_request_sent);
        // The same instance, stopped by its time guard before the next entry's request: false again
        $result = $rdap->lookup('example.net', microtime(true) + 3);
        $this->assertSame('error', $result['status']);
        $this->assertFalse($rdap->domain_request_sent);
    }

    public function testAStaleBootstrapAfterAFailedRefreshIsSaid()
    {
        file_put_contents(DIR_TEMP . '/rdap-dns.json', self::BOOTSTRAP);
        touch(DIR_TEMP . '/rdap-dns.json', time() - 8 * 86400);
        $this->play(array('https://data.iana.org/rdap/dns.json' => array(500, '')));
        $rdap = new Rdap();
        $rdap->log = new class {
            public function warning($message, $extra = array()) {}
        };
        $this->assertTrue($rdap->hasBootstrap(microtime(true) + 30));
        $this->assertTrue(Rdap::$bootstrap_stale);
        // Another instance in the same process sees the same map, and says the same
        $this->assertSame('no_server', (new Rdap())->lookup('example.org', microtime(true) + 30)['status']);
        $this->assertTrue(Rdap::$bootstrap_stale);
    }

    public function testTheStaleFlagIsSetDuringTheCooldownToo()
    {
        file_put_contents(DIR_TEMP . '/rdap-dns.json', self::BOOTSTRAP);
        touch(DIR_TEMP . '/rdap-dns.json', time() - 8 * 86400);
        touch(DIR_TEMP . '/rdap-dns.failed');
        $this->play(array());
        $this->assertTrue((new Rdap())->hasBootstrap(microtime(true) + 30));
        $this->assertTrue(Rdap::$bootstrap_stale);
    }

    public function testAFreshBootstrapIsntStale()
    {
        $this->play(array());
        $this->assertTrue((new Rdap())->hasBootstrap(microtime(true) + 30));
        $this->assertFalse(Rdap::$bootstrap_stale);
    }

    public function testWhoisReadsTheCreationDate()
    {
        $whois = new Whois();
        $responses = array(
            "Domain Name: EXAMPLE.COM\nCreation Date: 1995-08-14T04:00:00Z\nRegistry Expiry Date: 2027-08-13T04:00:00Z\n" => '1995-08-14',
            "domain: example.it\nCreated:             2000-01-15 00:00:00\nExpire Date:         2027-01-15\n" => '2000-01-15',
            "Domain name:\n    example.co.uk\n\n    Registered on: 26-Aug-1996\n    Expiry date:  26-Aug-2027\n" => '1996-08-26',
            "Domain Name: example.com\nRegistry Expiry Date: 2027-08-13\n" => '');
        foreach ($responses as $response => $expected) {
            $this->assertSame($expected, $whois->parse($response, 'example.com')['registration_date'], $response);
        }
    }

} //@formatter:on
