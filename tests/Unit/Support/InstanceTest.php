<?php
/**
 * /tests/Unit/Support/InstanceTest.php
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
namespace DomainMOD\Tests\Unit\Support;

use DomainMOD\Currency;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

class InstanceTest extends TestCase
{
    public function testBuildsWithoutRunningTheConstructor()
    {
        // Currency's constructor connects to the database, which would stop the test (no DB_* settings are defined).
        $currency = Instance::withoutConstructor(Currency::class);

        $this->assertInstanceOf(Currency::class, $currency);
        $this->assertNull($currency->deeb);
        $this->assertNull($currency->log);
    }

    public function testSetsOnlyTheGivenProperties()
    {
        $currency = Instance::withoutConstructor(Currency::class, array('log' => 'a placeholder'));

        $this->assertSame('a placeholder', $currency->log);
        $this->assertNull($currency->deeb);
    }

    public function testRefusesAPropertyTheClassDoesNotHave()
    {
        $this->expectException(\ReflectionException::class);

        Instance::withoutConstructor(Currency::class, array('missing' => 1));
    }

} //@formatter:on
