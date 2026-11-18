<?php
/**
 * /tests/Unit/ValidateTest.php
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

use DomainMOD\Validate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidateTest extends TestCase
{
    public static function tlds()
    {
        return array(
            'a plain TLD' => array('de', true),
            'a second-level TLD' => array('co.uk', true),
            'a Unicode TLD' => array('рф', true),
            'its punycode form' => array('xn--p1ai', true),
            'upper case' => array('COM', true),
            'empty' => array('', false),
            'a space' => array('bad tld', false),
            'a leading hyphen' => array('-de', false),
            'bad punycode' => array('xn--zz', false),
        );
    }

    #[DataProvider('tlds')]
    public function testTld($tld, $expected)
    {
        $this->assertSame($expected, (new Validate())->tld($tld));
    }

    public static function texts()
    {
        return array(
            'two characters' => array('ab', true),
            'a name' => array('Example Registrar', true),
            'padded to two' => array(' ab ', true),
            'one character' => array('a', false),
            'one character, padded' => array('  a  ', false),
            'empty' => array('', false),
            'spaces' => array('   ', false),
            'tabs and line breaks' => array("\t\r\n", false),
        );
    }

    #[DataProvider('texts')]
    public function testText($text, $expected)
    {
        $this->assertSame($expected, (new Validate())->text($text));
    }

} //@formatter:on
