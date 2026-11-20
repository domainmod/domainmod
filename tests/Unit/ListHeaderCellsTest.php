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
use DomainMOD\Tests\Support\FieldRows;
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

    public function testTheDomainsListHasNoControlHeading()
    {
        // The Domains list keeps every column and scrolls sideways, so it has no Responsive details column
        $cells = (new DomainListColumns(array('s_display_domain_tld' => '1'), array()))->headerCells();

        $this->assertSame(array('<th class="domainmod-list-domain">Domain</th>', '<th class="domainmod-list-tld">TLD</th>'), $cells);
    }

    public function testTheSslListHasNoControlHeading()
    {
        // The SSL Certificates list keeps every column and scrolls sideways, so it has no Responsive details column
        $cells = (new SslListColumns(array('s_display_ssl_expiry_date' => '1'), array()))->headerCells();

        $this->assertSame(array('<th class="domainmod-list-name">Name</th>', '<th class="domainmod-list-expiry">Expiry</th>'), $cells);
    }

    public function testCustomFieldHeadingsShareOneClass()
    {
        // Every custom field column gets domainmod-list-custom for the list's styles, whatever the field is called
        $columns = new DomainListColumns(array('s_cdf_data' => array(
            array('field' => 'note', 'name' => 'Note', 'type_id' => '2', 'value' => '1'),
            array('field' => 'renew_date', 'name' => 'Renew Date', 'type_id' => '4', 'value' => '1'),
        )), FieldRows::of(', dfd.note, dfd.renew_date', array('s_cdf_data' => array(
            array('field' => 'note', 'name' => 'Note', 'type_id' => '2', 'value' => '1'),
            array('field' => 'renew_date', 'name' => 'Renew Date', 'type_id' => '4', 'value' => '1'),
        ))));

        $this->assertSame(array('<th class="domainmod-list-domain">Domain</th>', '<th class="domainmod-list-custom">Note</th>',
            '<th class="domainmod-list-custom">Renew Date</th>'), $columns->headerCells());
    }

    public function testEveryHeadingCellHasText()
    {
        $cells = (new DomainListColumns(array(), array()))->headerCells();

        foreach ($cells as $cell) {
            $this->assertNotSame('', trim(strip_tags($cell)));
        }
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
