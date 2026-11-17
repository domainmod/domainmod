<?php
/**
 * /tests/Unit/DwRecordOrderTest.php
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

use DomainMOD\DwDisplay;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * A zone's DNS records come out in the same order on the DNS zones list and in its export (their order in the zone
 * file, then the line, then the id), and the export looks a zone's records up with bound values, not ones written into
 * the SQL.
 */
class DwRecordOrderTest extends TestCase
{
    private function display($pdo)
    {
        return Instance::withoutConstructor(DwDisplay::class, array('deeb' => (object) array('cnxx' => $pdo)));
    }

    public function testTheDisplayAndTheExportUseTheSameOrder()
    {
        $pdo = new FakeDatabase();
        $display = $this->display($pdo);

        $display->getRecords(5);
        $display->exportRecords(2, "it's.example.com");

        $this->assertSame('new_order, line, id', DwDisplay::RECORD_ORDER);
        $this->assertCount(2, $pdo->queriesWith('ORDER BY new_order, line, id'));
    }

    public function testTheExportBindsTheServerAndDomain()
    {
        $records = array((object) array('id' => 1), (object) array('id' => 2));
        $pdo = new FakeDatabase(array('FROM dw_dns_records' => $records));

        $this->assertSame($records, $this->display($pdo)->exportRecords(2, "it's.example.com"));
        $this->assertStringContainsString('server_id = :server_id', $pdo->queries[0]);
        $this->assertStringContainsString('domain = :domain', $pdo->queries[0]);
        $this->assertStringNotContainsString("it's.example.com", $pdo->queries[0]);
    }
}
