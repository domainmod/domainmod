<?php
/**
 * /tests/Unit/SslAssetsTest.php
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

use DomainMOD\SslAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * SslAssets::providerName(): well-known issuers get plain provider names, other issuers keep theirs, and a name that's
 * empty or starts with a formula character gives "Unknown SSL Provider". SslAssets and RdapAssets compare names the same
 * way whether they're stored HTML-escaped or plain.
 */
class SslAssetsTest extends TestCase
{

    public static function issuers()
    {
        return array(
            "Let's Encrypt" => array("Let's Encrypt", "Let's Encrypt"),
            'Sectigo' => array('Sectigo Limited', 'Sectigo'),
            'DigiCert' => array('DigiCert Inc', 'DigiCert'),
            'GlobalSign' => array('GlobalSign nv-sa', 'GlobalSign'),
            'Google' => array('Google Trust Services', 'Google Trust Services'),
            'Google LLC' => array('Google Trust Services LLC', 'Google Trust Services'),
            'Amazon' => array('Amazon', 'Amazon'),
            'ZeroSSL' => array('ZeroSSL', 'ZeroSSL'),
            'GoDaddy' => array('GoDaddy.com, Inc.', 'GoDaddy'),
            'Entrust' => array('Entrust, Inc.', 'Entrust'),
            'other issuer' => array('Example CA', 'Example CA'),
            'leading whitespace' => array(' Example CA', 'Example CA'),
            'formula after a space' => array('- =Example CA', 'Unknown SSL Provider'),
            'equals sign' => array('=', 'Unknown SSL Provider'),
            'plus' => array('+Example', 'Unknown SSL Provider'),
            'at sign' => array('@Example', 'Unknown SSL Provider'),
            'empty' => array('', 'Unknown SSL Provider'));
    }

    #[DataProvider('issuers')]
    public function testProviderName($issuer, $expected)
    {
        $this->assertSame($expected, SslAssets::providerName($issuer));
    }

    // Names are stored HTML-escaped, but older or imported rows can hold the plain form: both must match the same
    // provider or registrar, or the SSL Queue and RDAP imports add a duplicate (found at 4.27.0's [K], Let's Encrypt)
    public static function sameNames()
    {
        return array(
            'apostrophe' => array("Let's Encrypt", 'Let&#039;s Encrypt'),
            'ampersand' => array('Smith & Co', 'Smith &amp; Co'),
            'case and spaces' => array('  Example   CA ', 'example ca'));
    }

    // Only what Sanitize::text() escapes is unescaped, so a plain name with other entity text keeps its own identity
    public function testComparableLeavesOtherEntitiesAlone()
    {
        foreach (array(SslAssets::class, \DomainMOD\RdapAssets::class) as $class) {
            $assets = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $comparable = new \ReflectionMethod($class, 'comparable');
            $this->assertNotSame($comparable->invoke($assets, 'Example &colon; Registry'), $comparable->invoke($assets, 'Example : Registry'), $class);
        }
    }

    #[DataProvider('sameNames')]
    public function testComparableMatchesEscapedAndPlainNames($plain, $stored)
    {
        foreach (array(SslAssets::class, \DomainMOD\RdapAssets::class) as $class) {
            $assets = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $comparable = new \ReflectionMethod($class, 'comparable');
            $this->assertSame($comparable->invoke($assets, $plain), $comparable->invoke($assets, $stored), $class);
        }
    }

    public function testComparableKeepsDifferentNamesApart()
    {
        foreach (array(SslAssets::class, \DomainMOD\RdapAssets::class) as $class) {
            $assets = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $comparable = new \ReflectionMethod($class, 'comparable');
            $this->assertNotSame($comparable->invoke($assets, "Let's Encrypt"), $comparable->invoke($assets, 'Lets Encrypt'), $class);
        }
    }

} //@formatter:on
