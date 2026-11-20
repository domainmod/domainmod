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
 * empty or starts with a formula character gives "Unknown SSL Provider".
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

} //@formatter:on
