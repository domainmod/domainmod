<?php
/**
 * /tests/Unit/DwCleanReorderTest.php
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

use DomainMOD\DwClean;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

// The display order DwClean gives each DNS record type (new_order), which the zone lists sort by. Every type once got
// the same number, so records showed in zone file order instead of grouped by type.
class DwCleanReorderTest extends TestCase
{
    public function testTheUsualTypesComeFirstInTheirSetOrder()
    {
        $this->assertSame(
            array(1 => 'COMMENT', 2 => 'ZONE TTL', 3 => 'SOA', 4 => 'NS', 5 => 'MX', 6 => 'A', 7 => 'CNAME', 8 => 'TXT', 9 => 'SRV'),
            DwClean::typeOrder(array('A', 'TXT', 'SOA', 'NS')));
    }

    public function testOtherTypesFollowAlphabeticallyAndOnlyOnce()
    {
        $order = DwClean::typeOrder(array('CAA', 'a', 'AAAA', 'ns', 'caa', 'PTR', 'Mx'));

        $this->assertSame(array(10 => 'AAAA', 11 => 'CAA', 12 => 'PTR'), array_slice($order, 9, null, true));
    }

    public function testEachTypeIsSavedWithItsOwnPlace()
    {
        $pdo = new FakeReorderConnection(array('NS', 'AAAA', 'A', 'SOA'));
        $clean = Instance::withoutConstructor(DwClean::class, array('deeb' => (object) array('cnxx' => $pdo)));

        $clean->reorderRecords();

        $this->assertSame(array(
            array('new_order' => 1, 'type' => 'COMMENT'),
            array('new_order' => 2, 'type' => 'ZONE TTL'),
            array('new_order' => 3, 'type' => 'SOA'),
            array('new_order' => 4, 'type' => 'NS'),
            array('new_order' => 5, 'type' => 'MX'),
            array('new_order' => 6, 'type' => 'A'),
            array('new_order' => 7, 'type' => 'CNAME'),
            array('new_order' => 8, 'type' => 'TXT'),
            array('new_order' => 9, 'type' => 'SRV'),
            array('new_order' => 10, 'type' => 'AAAA'),
        ), $pdo->statement->executed);
    }
}

// A stand-in for the PDO connection reorderRecords() uses: the types query answers with $types, and the UPDATE's
// statement keeps the values its bound parameters had at each execute().
class FakeReorderConnection
{
    public $statement;
    private $types;

    public function __construct(array $types)
    {
        $this->types = $types;
    }

    public function query($sql)
    {
        $types = $this->types;
        return new class($types) {
            private $types;

            public function __construct($types)
            {
                $this->types = $types;
            }

            public function fetchAll($mode = null)
            {
                return $this->types;
            }
        };
    }

    public function prepare($sql)
    {
        $this->statement = new FakeReorderStatement();
        return $this->statement;
    }
}

class FakeReorderStatement
{
    public $executed = array();
    private $params = array();

    public function bindParam($name, &$variable, $type = null)
    {
        $this->params[$name] = &$variable;
        return true;
    }

    public function bindValue($name, $value, $type = null)
    {
        $this->params[$name] = $value;
        return true;
    }

    public function execute($params = null)
    {
        $values = array();
        foreach ($this->params as $name => $value) {
            $values[$name] = $value;
        }
        $this->executed[] = $values;
        return true;
    }
} //@formatter:on
