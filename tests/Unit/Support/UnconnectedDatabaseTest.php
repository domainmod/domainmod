<?php
/**
 * /tests/Unit/Support/UnconnectedDatabaseTest.php
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
use DomainMOD\Database;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

class UnconnectedDatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
    }

    public function testLetsAConstructorFetchTheSharedDatabase()
    {
        UnconnectedDatabase::install();

        $currency = new Currency();

        $this->assertInstanceOf(Database::class, $currency->deeb);
        $this->assertNull($currency->deeb->cnxx);
    }

    public function testRemovesTheSharedDatabaseAgain()
    {
        UnconnectedDatabase::install();
        UnconnectedDatabase::remove();

        $this->assertNull((new \ReflectionProperty(Database::class, 'instance'))->getValue());
    }

} //@formatter:on
