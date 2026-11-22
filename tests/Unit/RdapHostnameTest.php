<?php
/**
 * /tests/Unit/RdapHostnameTest.php
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
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * Rdap::isHostname() checks a name before the RDAP, WHOIS, SSL, and Live DNS lookups look it up. Its label pattern
 * ended with $, which also matches just before a final line feed, so a name ending in a line feed (or with one at the
 * end of any label, such as "example\n.com") passed. The constructor's Log connects to the database, so the object is
 * built without it.
 */
class RdapHostnameTest extends TestCase
{
    public function testHostnames()
    {
        $rdap = Instance::withoutConstructor(Rdap::class);
        $this->assertTrue($rdap->isHostname('example.com'));
        $this->assertTrue($rdap->isHostname('xn--mnchen-3ya.de'));
        $this->assertTrue($rdap->isHostname('a-1.b.example'));
        $this->assertFalse($rdap->isHostname('example.com.'));
        $this->assertFalse($rdap->isHostname('-example.com'));
        $this->assertFalse($rdap->isHostname(''));
    }

    public function testALineFeedIsRefused()
    {
        $rdap = Instance::withoutConstructor(Rdap::class);
        $this->assertFalse($rdap->isHostname("example.com\n"));
        $this->assertFalse($rdap->isHostname("example\n.com"));
        $this->assertFalse($rdap->isHostname("com\n"));
        $this->assertFalse($rdap->isHostname("example.com\r\n"));
    }

} //@formatter:on
