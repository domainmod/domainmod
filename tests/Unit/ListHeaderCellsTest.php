<?php
/**
 * /tests/Unit/ListHeaderCellsTest.php
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
use DomainMOD\SslListColumns;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Domains and SSL Certificates lists' heading cells (ListColumns::headerCells()). The control column's heading was
 * once empty, which axe reports as an empty table header, so it now carries hidden "Details" text for screen readers.
 * The constructors' Currency only fetches the shared Database object, so one that never connected is put in its place
 * for each test and removed again afterwards.
 */
class ListHeaderCellsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setDatabase(Instance::withoutConstructor(Database::class));
    }

    protected function tearDown(): void
    {
        $this->setDatabase(null);
    }

    public function testTheDomainsListsControlHeadingHasHiddenText()
    {
        $cells = (new DomainListColumns(array('s_display_domain_tld' => '1'), ''))->headerCells();

        $this->assertSame(array('<th width="20px"><span class="visually-hidden">Details</span></th>',
            '<th class="all">Domain</th>', '<th>TLD</th>'), $cells);
    }

    public function testTheSslListsControlHeadingHasHiddenText()
    {
        $cells = (new SslListColumns(array('s_display_ssl_expiry_date' => '1'), ''))->headerCells();

        $this->assertSame(array('<th width="20px"><span class="visually-hidden">Details</span></th>',
            '<th class="all">Name</th>', '<th>Expiry</th>'), $cells);
    }

    public function testEveryHeadingCellHasText()
    {
        $cells = (new DomainListColumns(array(), ''))->headerCells();

        foreach ($cells as $cell) {
            $this->assertNotSame('', trim(strip_tags($cell)));
        }
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
