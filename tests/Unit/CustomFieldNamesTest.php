<?php
/**
 * /tests/Unit/CustomFieldNamesTest.php
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

use DomainMOD\CustomField;
use DomainMOD\DomainList;
use DomainMOD\DomainListColumns;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\FieldRows;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * A custom field whose Database Field Name matched one of the list's own columns (such as notes or expiry_date) took
 * that column's place in the Domains and SSL Certificates lists' rows, so the list and its export showed the custom
 * field's value instead. The lists now select each custom field under its own alias (CustomField::listAlias()), and the
 * add pages refuse such names (CustomField::reservedName()). DomainListColumns' constructor creates a Currency, which
 * only fetches the shared database object, so an unconnected one stands in.
 */
class CustomFieldNamesTest extends TestCase
{
    protected function setUp(): void
    {
        UnconnectedDatabase::install();
    }

    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
    }

    public static function lists()
    {
        return array('domains' => array(DomainList::class, 'dfd'), 'SSL certificates' => array(SslList::class, 'sslfd'));
    }

    #[DataProvider('lists')]
    public function testEveryCustomFieldHasItsOwnAlias($list_class, $alias)
    {
        // Every one of the list's own columns as a custom field name, none of which may take a column's place
        $fields = array();
        foreach ($list_class::builtInColumnNames() as $n => $name) {
            $fields[] = array('id' => $n + 1, 'name' => $name, 'field_name' => $name, 'type_id' => 2);
        }
        $sql = (new $list_class(new RecordingPdo(), 1, $fields))->selectSql();

        foreach ($list_class::builtInColumnNames() as $name) {
            $this->assertStringContainsString(', ' . $alias . '.' . $name . ' AS cf1_' . $name, $sql);
            $this->assertStringNotContainsString(', ' . $alias . '.' . $name . ',', $sql . ',');
            $this->assertStringStartsNotWith(CustomField::LIST_ALIAS_PREFIX, $name);
        }
    }

    public function testTheBuiltInNamesComeFromTheSelect()
    {
        $this->assertContains('notes', DomainList::builtInColumnNames());
        $this->assertContains('expiry_date', DomainList::builtInColumnNames());
        $this->assertContains('registrar_name', DomainList::builtInColumnNames());
        $this->assertContains('type_id', SslList::builtInColumnNames());
        $this->assertContains('cat_name', SslList::builtInColumnNames());
    }

    public function testACustomFieldNamedLikeABuiltInColumnDoesNotReplaceIt()
    {
        $settings = DomainListColumns::sessionCopy(array('s_display_domain_expiry_date' => '1', 's_cdf_data' => array(
            array('name' => 'Expiry Date', 'field' => 'expiry_date', 'type_id' => '2', 'value' => '1'))));
        $columns = new DomainListColumns($settings, FieldRows::of(', dfd.expiry_date', $settings));
        $row = (object) array('id' => 3, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0',
            'expiry_date' => '2027-01-01', 'cf1_expiry_date' => 'my own text');

        $cells = $columns->cells($row, '2026-11-17');
        $this->assertSame(array('domain', 'expiry', 'cf_expiry_date'), array_column($columns->columns(), 'key'));
        $this->assertStringContainsString('>2027-01-01</a>', $cells[1]);
        $this->assertSame('my own text', $cells[2]);
    }

    public static function reservedNames()
    {
        return array(
            'a domain column' => array('domain', 'notes', true),
            'any case' => array('domain', 'Expiry_Date', true),
            'a joined column' => array('domain', 'registrar_name', true),
            'the data table id' => array('domain', 'id', true),
            'the data table domain id' => array('domain', 'domain_id', true),
            'the data table times' => array('domain', 'insert_time', true),
            'an SSL id on a domain field' => array('domain', 'ssl_id', false),
            'an ordinary domain field' => array('domain', 'renewal_note', false),
            'an SSL column' => array('ssl', 'cat_name', true),
            'the SSL data table id' => array('ssl', 'ssl_id', true),
            'the domain id on an SSL field' => array('ssl', 'domain_id', true),
            'an ordinary SSL field' => array('ssl', 'order_ref', false),
        );
    }

    #[DataProvider('reservedNames')]
    public function testReservedNames($type, $name, $reserved)
    {
        $this->assertSame($reserved, CustomField::reservedName($type, $name));
    }

    public static function addPages()
    {
        return array('domain fields' => array('admin/domain-fields/add.php', 'domain'),
            'SSL fields' => array('admin/ssl-fields/add.php', 'ssl'));
    }

    #[DataProvider('addPages')]
    public function testTheAddPagesRefuseReservedNames($page, $type)
    {
        // These read the page source, since the pages aren't classes
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $page);
        $call = "DomainMOD\\CustomField::reservedName('" . $type . "', \$new_field_name)";
        $this->assertStringContainsString('&& !' . $call . ' &&', $source);
        $this->assertStringContainsString('elseif (' . $call . ') $form->addFieldError(\'new_field_name\'', $source);
    }

    public static function exportPages()
    {
        return array('domains' => array('domains/index.php'), 'SSL certificates' => array('ssl/index.php'));
    }

    #[DataProvider('exportPages')]
    public function testTheExportsReadEachCustomFieldByItsAlias($page)
    {
        // These read the page source, since the export is page code
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $page);
        $this->assertSame(2, substr_count($source, '$row->{DomainMOD\\CustomField::listAlias($column)}'));
        $this->assertStringNotContainsString('$row->{$column}', $source);
    }
}
