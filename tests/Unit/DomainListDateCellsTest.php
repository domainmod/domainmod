<?php
/**
 * /tests/Unit/DomainListDateCellsTest.php
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

use DomainMOD\Database;
use DomainMOD\DomainListColumns;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Domains list's custom Date and Time Stamp cells are blank when the field was never set (the 1970-01-01 default
 * or a zero date) and otherwise show the stored value. The constructor's Currency only fetches the shared Database
 * object, so one that never connected is put in its place for each test and removed again afterwards.
 */
class DomainListDateCellsTest extends TestCase
{
    const FIELDS_SQL = ', dfd.renewed_on, dfd.checked_at';

    protected function setUp(): void
    {
        $this->setDatabase(Instance::withoutConstructor(Database::class));
    }

    protected function tearDown(): void
    {
        $this->setDatabase(null);
    }

    public function testUnsetDatesAndTimeStampsAreBlank()
    {
        foreach (array(array('1970-01-01', '1970-01-01 00:00:00'), array('0000-00-00', '0000-00-00 00:00:00'),
                     array(null, null), array('', '')) as $values) {
            $cells = $this->cells($values[0], $values[1]);
            $this->assertSame('', $cells[1], var_export($values[0], true));
            $this->assertSame('', $cells[2], var_export($values[1], true));
        }
    }

    public function testSetDatesAndTimeStampsShowTheStoredValue()
    {
        $cells = $this->cells('2026-02-03', '2026-02-03 04:05:06');
        $this->assertSame('2026-02-03', $cells[1]);
        $this->assertSame('2026-02-03 04:05:06', $cells[2]);

        // A real time one second after the default still shows
        $cells = $this->cells('1970-01-02', '1970-01-01 00:00:01');
        $this->assertSame('1970-01-02', $cells[1]);
        $this->assertSame('1970-01-01 00:00:01', $cells[2]);
    }

    private function cells($date, $time_stamp)
    {
        $settings = DomainListColumns::sessionCopy(array('s_cdf_data' => array(
            array('name' => 'Renewed On', 'field' => 'renewed_on', 'type_id' => '4', 'value' => '1'),
            array('name' => 'Checked At', 'field' => 'checked_at', 'type_id' => '5', 'value' => '1'))));
        $columns = new DomainListColumns($settings, self::FIELDS_SQL);
        $this->assertSame(array('domain', 'cf_renewed_on', 'cf_checked_at'),
            array_column($columns->columns(), 'key'));

        $row = (object) array('id' => 3, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0',
            'expiry_date' => '2027-01-01', 'renewed_on' => $date, 'checked_at' => $time_stamp);
        return $columns->cells($row, '2026-11-17');
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
