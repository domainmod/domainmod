<?php
/**
 * /tests/Unit/SystemPublicIpTest.php
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

use DomainMOD\System;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// System Information's Remote IP Address (System::publicIp()): only an IP address from the lookup is shown, never an
// error page or anything else the outside service sends back.
class SystemPublicIpTest extends TestCase
{
    public static function answers()
    {
        return array(
            'an IPv4 address' => array('203.0.113.7', '203.0.113.7'),
            'an IPv6 address' => array('2001:db8::1', '2001:db8::1'),
            'with a trailing line break' => array("203.0.113.7\n", '203.0.113.7'),
            'a failed call (false)' => array(false, ''),
            'a failed call (empty)' => array('', ''),
            'an error page' => array('<html><body>Bad Gateway</body></html>', ''),
            'markup around an address' => array('<b>203.0.113.7</b>', ''),
            'not quite an address' => array('203.0.113.256', ''),
        );
    }

    #[DataProvider('answers')]
    public function testKeepsOnlyAnIpAddress($answer, $expected)
    {
        $system = Instance::withoutConstructor(System::class);
        $this->assertSame($expected, $system->publicIp($answer));
    }

} //@formatter:on
