<?php
/**
 * /tests/Unit/RawListKeysTest.php
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

use DomainMOD\DomainList;
use DomainMOD\ListQuery;
use DomainMOD\Tests\Support\FieldRows;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The Domains list's Raw List reads only the keys (ListQuery::exportKeyBatches()), never the list's full rows, from its saved query
class RawListKeysTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }
    }

    public static function lists()
    {
        return array(
            'domains' => array(DomainList::class, ', dfd.project_code', 'd.domain'),
        );
    }

    #[DataProvider('lists')]
    public function testOnlyTheNameAndTheKeysAreRead($class, $field_columns, $name)
    {
        $pdo = new RecordingPdo(array('AS ks_expiry' => array(array(array('ks_expiry' => '2027-01-01',
            'ks_name' => 'a', 'ks_id' => 1, 'ks_field_id' => 1, 'ks_bytes' => 10)))));
        $list = new $class($pdo, 1, FieldRows::of($field_columns));
        $saved_from_sql = $list->fromSql() . " AND 1 = 1";

        $names = array();
        foreach ($list->exportKeyBatches($saved_from_sql . " AND (x LIKE :sw0 ESCAPE '\\\\')", array('sw0' => '%a%'), 10,
            false) as $keys) {
            $names = array_merge($names, array_column($keys, 'ks_name'));
        }

        $this->assertSame(array('a'), $names);
        $queries = $pdo->statementsWith('AS ks_expiry');
        $this->assertCount(1, $queries);
        $select = substr($queries[0]['sql'], 0, strpos($queries[0]['sql'], ' FROM'));
        $this->assertStringContainsString(' AS ks_name', $select);
        $this->assertStringContainsString($name . ' AS ks_name', $select);
        // No sizes (so not the custom fields either), and none of the list's own columns, so a row's width can't matter
        $this->assertStringContainsString(' 0 AS ks_bytes', $select);
        $this->assertStringNotContainsString('LENGTH(', $select);
        $this->assertStringNotContainsString(trim($list->selectSql()), $queries[0]['sql']);
        $this->assertStringNotContainsString('registrar_name', $select);
        $this->assertStringNotContainsString('ssl_provider_name', $select);
        $this->assertStringContainsString($saved_from_sql, $queries[0]['sql']);
        $this->assertSame('%a%', $queries[0]['params']['sw0']);
        // And never the full rows
        $this->assertCount(1, array_filter($pdo->log, function ($entry) {
            return strpos($entry['sql'], 'FROM') !== false && strpos($entry['sql'], 'settings') === false;
        }));
    }

} //@formatter:on
