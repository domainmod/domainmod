<?php
/**
 * /tests/Unit/ExportLookupsTest.php
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

use DomainMOD\Export;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

// The creation types and users an export reads once for all its rows, rather than once for each row
class ExportLookupsTest extends TestCase
{
    public function testCreationTypesAreReadInOneQuery()
    {
        $pdo = new RecordingPdo(array('FROM creation_types' => array(array(
            array('id' => 1, 'name' => 'Installation'), array('id' => 2, 'name' => 'Manual')))));

        $names = Export::creationTypeNames($pdo);

        $this->assertSame(array(1 => 'Installation', 2 => 'Manual'), $names);
        $this->assertCount(1, $pdo->log);
        // The map is keyed by the first column, so the id must come first
        $this->assertMatchesRegularExpression('/SELECT id, `name`\s+FROM creation_types/', $pdo->log[0]['sql']);
        $this->assertSame('Manual', Export::creationTypeName($names, 2));
        $this->assertSame('Manual', Export::creationTypeName($names, '2'));
    }

    public function testAMissingCreationTypeGetsTodaysText()
    {
        // The text System::getCreationType() returns when the creation type isn't there
        $this->assertSame('Unable to retrieve creation type', Export::creationTypeName(array(1 => 'Installation'), 9));

        // It treats a name that's empty or "0" as missing too
        $this->assertSame('Unable to retrieve creation type', Export::creationTypeName(array(1 => '', 2 => '0'), 1));
        $this->assertSame('Unable to retrieve creation type', Export::creationTypeName(array(1 => '', 2 => '0'), 2));
    }

    public function testUsersAreReadInOneQuery()
    {
        $pdo = new RecordingPdo(array('FROM users' => array(array(
            array('id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace'),
            array('id' => 4, 'first_name' => 'Grace', 'last_name' => 'Hopper')))));

        $names = Export::userNames($pdo);

        $this->assertSame(array(1 => 'Ada Lovelace', 4 => 'Grace Hopper'), $names);
        $this->assertCount(1, $pdo->log);
        $this->assertMatchesRegularExpression('/SELECT id, first_name, last_name\s+FROM users/', $pdo->log[0]['sql']);
        $this->assertSame('Grace Hopper', Export::userName($names, '4'));
    }

    public function testADeletedUserGetsTodaysText()
    {
        // User::getFullName() joins the missing row's two empty names with a space
        $this->assertSame(' ', Export::userName(array(1 => 'Ada Lovelace'), 99));
    }

} //@formatter:on
