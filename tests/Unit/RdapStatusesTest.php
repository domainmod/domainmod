<?php
/**
 * /tests/Unit/RdapStatusesTest.php
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
use PHPUnit\Framework\TestCase;

/*
 * Rdap::lookup()'s statuses and its identity check, with recorded responses instead of the network: which record
 * the statuses come from (status_read found, none, or incomplete), how the registrar's "related" link is chosen, and
 * which records count as the domain asked for.
 */
class RdapStatusesTest extends TestCase
{
    const REGISTRY = 'https://rdap.registry.example/domain/';
    const REGISTRAR = 'https://rdap.registrar.example/domain/';

    protected function setUp(): void
    {
        RdapFixture::reset();
    }

    private function record($domain, array $extra = array())
    {
        return $extra + array('objectClassName' => 'domain', 'ldhName' => $domain,
            'events' => array(array('eventAction' => 'expiration', 'eventDate' => '2027-01-31T00:00:00Z')));
    }

    private function related($domain = 'example.com', array $link = array())
    {
        return array($link + array('rel' => 'related', 'href' => self::REGISTRAR . $domain, 'type' => 'application/rdap+json'));
    }

    private function json($value)
    {
        return json_encode($value);
    }

    private function lookup(array $responses, $domain = 'example.com', $status_only = false)
    {
        $rdap = RdapFixture::make($responses);
        return array($rdap->lookup($domain, microtime(true) + 30, $status_only), $rdap);
    }

    public function testTheRegistrysCodesAreFound()
    {
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $this->json($this->record('example.com',
            array('status' => array('client transfer prohibited', 'active'))))));

        $this->assertSame('ok', $result['status']);
        $this->assertSame('found', $result['status_read']);
        $this->assertSame(array('ok', 'clientTransferProhibited'), $result['statuses']);
    }

    public function testNoCodesAnywhereIsNone()
    {
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $this->json($this->record('example.com'))));
        $this->assertSame('none', $result['status_read']);

        list($result) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', array('links' => $this->related()))),
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com'))));
        $this->assertSame('none', $result['status_read']);
    }

    public function testTheRegistrarsCodesAreUsedAfterANoneRegistryRead()
    {
        list($result) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', array('links' => $this->related()))),
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com', array('status' => array('client transfer prohibited'),
                'events' => array(array('eventAction' => 'expiration', 'eventDate' => '2026-01-31T00:00:00Z')))))));

        $this->assertSame('found', $result['status_read']);
        $this->assertSame(array('clientTransferProhibited'), $result['statuses']);
        $this->assertSame('2026-01-31', $result['expiry_date']);
    }

    public function testAStatusOnlyLookupFollowsTheRegistrarAfterANoneRegistryRead()
    {
        list($result, $rdap) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', array('links' => $this->related()))),
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com', array('status' => array('client transfer prohibited'))))),
            'example.com', true);

        $this->assertSame('found', $result['status_read']);
        $this->assertSame(array('clientTransferProhibited'), $result['statuses']);
        $this->assertSame(array(self::REGISTRY . 'example.com', self::REGISTRAR . 'example.com'), $rdap->fetched);
    }

    public function testAFailedRegistrarFallbackIsIncomplete()
    {
        list($result) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', array('links' => $this->related()))),
            self::REGISTRAR . 'example.com' => array('error', '')));

        $this->assertSame('ok', $result['status']);
        $this->assertSame('incomplete', $result['status_read']);
        $this->assertSame('2027-01-31', $result['expiry_date']);
    }

    public function testAMalformedRegistryStatusIsIncompleteAndTheRegistrarCantReplaceIt()
    {
        $registry = $this->json($this->record('example.com', array('status' => array('active', array('client transfer prohibited')),
            'links' => $this->related())));
        $registrar = $this->json($this->record('example.com', array('status' => array('active'),
            'events' => array(array('eventAction' => 'expiration', 'eventDate' => '2026-01-31T00:00:00Z')))));

        // a full lookup still takes the registrar's expiry date, but not its statuses
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry, self::REGISTRAR . 'example.com' => $registrar));
        $this->assertSame('incomplete', $result['status_read']);
        $this->assertSame('2026-01-31', $result['expiry_date']);

        // a status-only lookup doesn't follow the link at all
        list($result, $rdap) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry, self::REGISTRAR . 'example.com' => $registrar), 'example.com', true);
        $this->assertSame('incomplete', $result['status_read']);
        $this->assertSame(array(self::REGISTRY . 'example.com'), $rdap->fetched);

        // a status-only lookup doesn't read the other details, so a broken name server can't get in the way
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $this->json($this->record('example.com',
            array('status' => array('client transfer prohibited'), 'nameservers' => array(array('ldhName' => array('x'))))))), 'example.com', true);
        $this->assertSame('found', $result['status_read']);
        $this->assertSame('', $result['expiry_date']);
    }

    public function testARecordTheObjectDecoderRefusesIsStillUsed()
    {
        // a property name starting with a NUL character (fine for the array decoder) in an unrelated member
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' =>
            '{"objectClassName":"domain","ldhName":"example.com","status":["active"],"x":{"\\u0000flag":true},'
            . '"events":[{"eventAction":"expiration","eventDate":"2027-01-31T00:00:00Z"}]}'));

        $this->assertSame('ok', $result['status']);
        $this->assertSame('2027-01-31', $result['expiry_date']);
        $this->assertSame('incomplete', $result['status_read']);

        // and its registrar link is still followed for the expiry date
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' =>
            '{"objectClassName":"domain","ldhName":"example.com","x":{"\\u0000flag":true},"links":' . $this->json($this->related()) . ','
            . '"events":[{"eventAction":"expiration","eventDate":"2027-01-31T00:00:00Z"}]}',
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com', array('status' => array('active'),
                'events' => array(array('eventAction' => 'expiration', 'eventDate' => '2026-01-31T00:00:00Z')))))));
        $this->assertSame('2026-01-31', $result['expiry_date']);
        $this->assertSame('incomplete', $result['status_read']);
    }

    public function testAJsonObjectStatusIsIncomplete()
    {
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' =>
            '{"objectClassName":"domain","ldhName":"example.com","status":{"0":"active"}}'));

        $this->assertSame('incomplete', $result['status_read']);
    }

    public function testStatusesFromASingleRecord()
    {
        $rdap = RdapFixture::make(array());

        $this->assertSame('incomplete', $rdap->statuses('{"status":"active"}')[0]);
        $this->assertSame('incomplete', $rdap->statuses($this->json(array('status' => array_merge(array_fill(0, 50, 'active'), array('clientTransferProhibited')))))[0]);
        $this->assertSame('incomplete', $rdap->statuses($this->json(array('status' => array(str_repeat('a', 101) . ' https://example.com/x'))))[0]);
        $this->assertSame('found', $rdap->statuses($this->json(array('status' => array('active'))))[0]);
    }

    public function testRelatedLinkChoices()
    {
        $registry = function ($links) {
            return $this->json($this->record('example.com', array('links' => $links)));
        };
        $registrar = $this->json($this->record('example.com', array('status' => array('active'))));
        $valid = $this->related()[0];

        // a rejected link alone
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array(array('rel' => 'related', 'href' => 'http://insecure.example/domain/example.com')))));
        $this->assertSame('incomplete', $result['status_read']);

        // a rejected link, false, or a "type": false link (to another server, which fails) before a valid one: the
        // valid one is followed
        foreach (array(array('rel' => 'related', 'href' => 'http://insecure.example/domain/example.com'), false,
                     array('rel' => 'related', 'href' => 'https://rdap.other.example/domain/example.com', 'type' => false)) as $first) {
            list($result, $rdap) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array($first, $valid)),
                self::REGISTRAR . 'example.com' => $registrar, 'https://rdap.other.example/domain/example.com' => array('error', '')));
            $this->assertSame('found', $result['status_read']);
            $this->assertSame(array(self::REGISTRY . 'example.com', self::REGISTRAR . 'example.com'), $rdap->fetched);
        }

        // "type": null is followed
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array(array('type' => null) + $valid)), self::REGISTRAR . 'example.com' => $registrar));
        $this->assertSame('found', $result['status_read']);

        // "type": [] alone
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array(array('type' => array()) + $valid))));
        $this->assertSame('incomplete', $result['status_read']);

        // links as a JSON object holding a valid-looking link: not followed
        list($result, $rdap) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array('0' => $valid, 'x' => 1)), self::REGISTRAR . 'example.com' => $registrar));
        $this->assertSame('incomplete', $result['status_read']);
        $this->assertSame(array(self::REGISTRY . 'example.com'), $rdap->fetched);

        // a long links list is still searched (the registrar link last of 101)
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array_merge(array_fill(0, 100,
            array('rel' => 'self', 'href' => 'https://rdap.registry.example/x')), array($valid))), self::REGISTRAR . 'example.com' => $registrar));
        $this->assertSame('found', $result['status_read']);

        // and a status list over the cap makes a whole lookup incomplete
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $this->json($this->record('example.com',
            array('status' => array_merge(array_fill(0, 50, 'active'), array('client transfer prohibited')))))));
        $this->assertSame('incomplete', $result['status_read']);

        // a "rel": null link is malformed, not missing
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array(array('rel' => null) + $valid))));
        $this->assertSame('incomplete', $result['status_read']);

        // an HTML-only related link is ignored
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $registry(array(array('type' => 'text/html') + $valid))));
        $this->assertSame('none', $result['status_read']);
    }

    public function testTheIdentityCheck()
    {
        $ok = function ($record, $domain = 'example.com', $redirected = false) {
            $rdap = RdapFixture::make(array());
            RdapFixture::$responses = array(self::REGISTRY . rawurlencode(\DomainMOD\Domain::forApi($domain)) => array('ok', $this->json($record), $redirected));
            return $rdap->lookup($domain, microtime(true) + 30)['status'];
        };

        $this->assertSame('ok', $ok($this->record('example.com')));
        $this->assertSame('ok', $ok($this->record('EXAMPLE.COM.')));
        $this->assertSame('mismatch', $ok($this->record('other.com')));
        $this->assertSame('mismatch', $ok(array('objectClassName' => 'entity', 'ldhName' => 'example.com')));
        $this->assertSame('mismatch', $ok(array('objectClassName' => 'domain', 'ldhName' => array('example.com'))));
        $this->assertSame('mismatch', $ok(array('objectClassName' => 'domain', 'ldhName' => 'example.com', 'unicodeName' => null)));
        $this->assertSame('mismatch', $ok(array('errorCode' => 404, 'title' => 'Not Found')));

        // an IDN: the punycode ldhName, or only a matching unicodeName (with a trailing dot)
        $this->assertSame('ok', $ok($this->record('xn--mnchen-3ya.de'), 'münchen.de'));
        $this->assertSame('ok', $ok(array('objectClassName' => 'domain', 'unicodeName' => 'münchen.de.'), 'münchen.de'));
        $this->assertSame('mismatch', $ok(array('objectClassName' => 'domain', 'ldhName' => 'other.de', 'unicodeName' => 'münchen.de'), 'münchen.de'));

        // a nameless registry record matches only when no redirect was followed
        $this->assertSame('ok', $ok(array('objectClassName' => 'domain')));
        $this->assertSame('mismatch', $ok(array('objectClassName' => 'domain'), 'example.com', true));
    }

    public function testAMismatchedRecordReturnsNothing()
    {
        list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $this->json($this->record('other.com',
            array('status' => array('active'), 'nameservers' => array(array('ldhName' => 'ns1.example.net')))))));

        $this->assertSame('mismatch', $result['status']);
        $this->assertSame('', $result['expiry_date']);
        $this->assertSame(array(), $result['name_servers']);
        $this->assertSame('', $result['registrar_name']);
        $this->assertSame(array(), $result['statuses']);

        // a JSON array (or an error object) sent with HTTP 200 isn't the domain either
        foreach (array('[]', '{"errorCode":404,"title":"Not Found"}') as $body) {
            list($result) = $this->lookup(array(self::REGISTRY . 'example.com' => $body));
            $this->assertSame('mismatch', $result['status'], $body);
        }
    }

    public function testAMismatchedOrNamelessRegistrarRecordIsIgnoredAndItsHostIsntRemembered()
    {
        foreach (array($this->record('other.com', array('status' => array('active'))), array('objectClassName' => 'domain', 'status' => array('active')), array(),
                     $this->record('x', array('ldhName' => array('example.com')))) as $registrar) {

            RdapFixture::reset();
            list($result) = $this->lookup(array(
                self::REGISTRY . 'example.com' => $this->json($this->record('example.com', array('links' => $this->related()))),
                self::REGISTRAR . 'example.com' => $this->json(array_merge($registrar, array('events' => array(array('eventAction' => 'expiration', 'eventDate' => '2020-01-01T00:00:00Z')))))));

            $this->assertSame('ok', $result['status']);
            $this->assertSame('incomplete', $result['status_read']);
            $this->assertSame('2027-01-31', $result['expiry_date']);

            // the next domain's correct referral to the same registrar is still fetched
            list($next, $rdap) = $this->lookup(array(
                self::REGISTRY . 'example.org' => $this->json($this->record('example.org', array('links' => $this->related('example.org')))),
                self::REGISTRAR . 'example.org' => $this->json($this->record('example.org', array('status' => array('active'))))), 'example.org');
            $this->assertSame('found', $next['status_read']);
            $this->assertContains(self::REGISTRAR . 'example.org', $rdap->fetched);

        }
    }

    public function testAStatusOnlyTimeoutDoesntMakeALaterFullLookupSkipTheRegistrar()
    {
        $links = array('links' => $this->related());

        list($status_only) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', $links)),
            self::REGISTRAR . 'example.com' => array('error', '')), 'example.com', true);
        $this->assertSame('incomplete', $status_only['status_read']);

        list($full) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', $links)),
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com', array('events' => array(array('eventAction' => 'expiration', 'eventDate' => '2026-01-31T00:00:00Z')))))));
        $this->assertSame('2026-01-31', $full['expiry_date']);

        // while a full lookup's failure is remembered
        list($failed) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', $links)),
            self::REGISTRAR . 'example.com' => array('error', '')));
        list($after, $rdap) = $this->lookup(array(
            self::REGISTRY . 'example.com' => $this->json($this->record('example.com', $links)),
            self::REGISTRAR . 'example.com' => $this->json($this->record('example.com', array('status' => array('active'))))));
        $this->assertSame('incomplete', $after['status_read']);
        $this->assertSame(array(self::REGISTRY . 'example.com'), $rdap->fetched);
    }

} //@formatter:on

// Rdap with recorded responses (by URL) instead of the network, and a fixed bootstrap
class RdapFixture extends Rdap
{
    public static $responses = array();
    public $fetched = array();

    public static function make(array $responses)
    {
        self::$responses = array();
        foreach ($responses as $url => $response) {
            self::$responses[$url] = is_array($response) ? $response + array(2 => false) : array('ok', $response, false);
        }
        return (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
    }

    public static function reset()
    {
        self::$failed_registrar_hosts = array();
        self::$last_request = array();
    }

    protected function bootstrap($deadline)
    {
        return array('com' => 'https://rdap.registry.example/', 'org' => 'https://rdap.registry.example/',
            'de' => 'https://rdap.registry.example/');
    }

    public function fetch($url, $deadline, $max_bytes, $paced)
    {
        $this->fetched[] = $url;
        $response = self::$responses[$url] ?? array('not_found', '', false);
        $this->redirected = (bool) $response[2];
        return array($response[0], $response[1]);
    }

    public function statuses($body)
    {
        return $this->statusesFrom($body);
    }
}
