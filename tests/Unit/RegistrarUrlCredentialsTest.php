<?php
/**
 * /tests/Unit/RegistrarUrlCredentialsTest.php
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

use DomainMOD\AboveCom;
use DomainMOD\DreamHost;
use DomainMOD\Enom;
use DomainMOD\Fabulous;
use DomainMOD\Freenom;
use DomainMOD\InternetBs;
use DomainMOD\Namecheap;
use DomainMOD\NameSilo;
use DomainMOD\ResellerClub;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * These registrar classes put the account's username, password, API key, API secret, or reseller ID straight into the
 * API address's query string, so a value with &, #, +, =, ?, %, or a space in it was cut short or changed on the way
 * (p&ss became pw=p plus a stray ss parameter, and a + became a space), and the registrar turned the account down.
 * Each value is now URL-encoded, so the registrar gets it exactly as saved.
 */
class RegistrarUrlCredentialsTest extends TestCase
{
    // A value with every character that changes a query string's meaning
    const TYPED = 'a&b#c+d e=f?g%h/i';

    // eNom splits the domain with a Domain, whose constructor fetches the shared connection (never used here)
    protected function setUp(): void
    {
        UnconnectedDatabase::install();
    }

    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
    }

    // Each class, its getApiUrl() arguments for each command, and the query keys that should carry TYPED (with a 2,
    // 3, and so on added to tell the values apart)
    public static function urls()
    {
        $t = self::TYPED;
        return array(
            'Above.com' => array(AboveCom::class, array(array($t, 'domainlist')), array('key' => $t)),
            'DreamHost' => array(DreamHost::class, array(array($t, 'domainlist')), array('key' => $t)),
            'eNom' => array(Enom::class, array(array($t, $t . '2', '', 'domainlist'), array($t, $t . '2', 'example.com', 'info'),
                array($t, $t . '2', 'example.com', 'privacy'), array($t, $t . '2', 'example.com', 'autorenewal')),
                array('uid' => $t, 'pw' => $t . '2')),
            'Fabulous' => array(Fabulous::class, array(array($t, $t . '2', '', 'domainlist'), array($t, $t . '2', 'example.com', 'info')),
                array('username' => $t, 'password' => $t . '2')),
            'Freenom' => array(Freenom::class, array(array($t, $t . '2', '', 'domainlist'), array($t, $t . '2', 'example.com', 'info')),
                array('email' => $t, 'password' => $t . '2')),
            'Internet.bs' => array(InternetBs::class, array(array($t, $t . '2', 'domainlist', ''), array($t, $t . '2', 'info', 'example.com')),
                array('apiKey' => $t, 'password' => $t . '2')),
            'Namecheap' => array(Namecheap::class, array(array($t . '2', 'domainlist', '', $t, '192.0.2.1', 1),
                array($t . '2', 'info', 'example.com', $t, '192.0.2.1', ''), array($t . '2', 'autorenewal', 'example.com', $t, '192.0.2.1', '')),
                array('ApiUser' => $t, 'ApiKey' => $t . '2', 'UserName' => $t)),
            'NameSilo' => array(NameSilo::class, array(array($t, '', 'domainlist'), array($t, 'example.com', 'info')), array('key' => $t)),
            'ResellerClub' => array(ResellerClub::class, array(array($t, $t . '2', 'info', 'example.com'), array($t, $t . '2', 'dns', 'example.com')),
                array('auth-userid' => $t, 'api-key' => $t . '2')),
        );
    }

    // A URL's query string as a list of [key, value] pairs, decoded the way a server reads them
    private function query($url)
    {
        $this->assertStringNotContainsString('#', $url, $url);
        $pairs = array();
        foreach (explode('&', (string) parse_url($url, PHP_URL_QUERY)) as $pair) {
            if ($pair === '') continue;
            $parts = explode('=', $pair, 2);
            $pairs[] = array(urldecode($parts[0]), urldecode($parts[1] ?? ''));
        }
        return $pairs;
    }

    #[DataProvider('urls')]
    public function testCredentialsReachTheRegistrarAsSaved($class, array $calls, array $expected)
    {
        $registrar = Instance::withoutConstructor($class);

        foreach ($calls as $arguments) {

            $url = $registrar->getApiUrl(...$arguments);
            $pairs = $this->query($url);

            foreach ($expected as $key => $value) {
                $this->assertContains(array($key, $value), $pairs, $url);
            }

            // Nothing in a value was read as a key of its own
            foreach ($pairs as $pair) {
                $this->assertStringNotContainsString('b#c', $pair[0], $url);
                $this->assertNotSame('b', $pair[0], $url);
                $this->assertNotSame('f?g%h/i', $pair[1], $url);
            }

        }
    }

    // A domain and the other parameters after the credentials are still sent
    public function testTheRestOfTheAddressIsUnchanged()
    {
        $url = Instance::withoutConstructor(Enom::class)->getApiUrl(self::TYPED, 'pw', 'example.com', 'info');
        $this->assertStringEndsWith('&pw=pw&sld=example&tld=com&ResponseType=XML', $url);

        $url = Instance::withoutConstructor(ResellerClub::class)->getApiUrl('123', 'key', 'info', 'example.com');
        $this->assertSame('https://httpapi.com/api/domains/details-by-name.json?auth-userid=123&api-key=key&domain-name=example.com&options=OrderDetails', $url);
    }

} //@formatter:on
