<?php
/**
 * /tests/Unit/ListFilterParamsTest.php
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
use DomainMOD\DomainListColumns;
use DomainMOD\ListQuery;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

// The custom field filters' bound values reach every query that runs the list's filters, and only those: the counts,
// the page of rows, the numeric sort check, Export's check before the file starts, and the segment's flags
class ListFilterParamsTest extends TestCase
{
    const FILTER = " AND dfd.`renew` = :cff0";
    const PARAMS = array('cff0' => 1);
    const SEARCH = array('sql' => " AND (d.domain LIKE :sw0 ESCAPE '\\\\')", 'params' => array('sw0' => '%a%'));

    protected function setUp(): void
    {
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }
        (new \ReflectionProperty(DomainList::class, 'segment_index'))->setValue(null, array());
    }

    private function list(RecordingPdo $pdo)
    {
        return new DomainList($pdo, 1, array(array('id' => 1, 'name' => 'Renew', 'field_name' => 'renew', 'type_id' => 1)));
    }

    public function testTheCountGetsOnlyTheFilterParameters()
    {
        $pdo = new RecordingPdo(array('SELECT count(*)' => array(array(array('count(*)' => 3)))));
        $this->assertSame(3, $this->list($pdo)->count(self::FILTER, self::PARAMS));
        $statement = $pdo->statementsWith('SELECT count(*)')[0];
        $this->assertStringEndsWith(self::FILTER, $statement['sql']);
        $this->assertSame(self::PARAMS, $statement['params']);

        // Without a search, counts() is the same count
        $pdo = new RecordingPdo(array('SELECT count(*)' => array(array(array('count(*)' => 3)))));
        $this->assertSame(array(3, 3), $this->list($pdo)->counts(self::FILTER, array('sql' => '', 'params' => array()), self::PARAMS));
        $this->assertSame(self::PARAMS, $pdo->statementsWith('SELECT count(*)')[0]['params']);
    }

    public function testTheCountsAndRowsWithASearchGetBoth()
    {
        $pdo = new RecordingPdo(array('AS filtered' => array(array(array('total' => 5, 'filtered' => 2)))));
        $this->assertSame(array(5, 2), $this->list($pdo)->counts(self::FILTER, self::SEARCH, self::PARAMS));
        $statement = $pdo->statementsWith('AS filtered')[0];
        $this->assertSame(self::SEARCH['params'] + self::PARAMS, $statement['params']);
        // The search only decides which rows count as filtered, and the filters decide which rows count at all
        $this->assertStringContainsString('CASE WHEN 1 = 1' . self::SEARCH['sql'] . ' THEN 1 ELSE 0 END', $statement['sql']);
        $this->assertStringEndsWith(self::FILTER, $statement['sql']);
        $this->assertSame(1, substr_count($statement['sql'], ':sw0'));

        $pdo = new RecordingPdo();
        $this->list($pdo)->rows(self::FILTER, self::SEARCH, ' ORDER BY d.domain ASC', 0, 50, self::PARAMS);
        $statement = $pdo->statementsWith('LIMIT 0, 50')[0];
        $this->assertStringContainsString(self::FILTER . self::SEARCH['sql'] . ' ORDER BY', $statement['sql']);
        $this->assertSame(self::SEARCH['params'] + self::PARAMS, $statement['params']);
    }

    public function testTheNumericSortCheckGetsOnlyTheFilterParameters()
    {
        UnconnectedDatabase::install();
        try {
            $columns = new DomainListColumns(DomainListColumns::sessionCopy(array('s_cdf_data' => array(
                array('field' => 'code', 'name' => 'Code', 'type_id' => '2', 'value' => '1')))),
                array(array('id' => 1, 'name' => 'Code', 'field_name' => 'code', 'type_id' => 2)));
            $pdo = new RecordingPdo(array('AS filled' => array(array(array('filled' => 2, 'other' => 0)))));
            $columns->useNumericSort($pdo, ' FROM domains AS d WHERE 1 = 1' . self::FILTER, self::PARAMS);

            $this->assertSame(self::PARAMS, $pdo->statementsWith('AS filled')[0]['params']);
            // Every value is a number, so the column sorts as numbers
            $this->assertSame("dfd.code != ''", $columns->sortMap()[1][0]);
        } finally {
            UnconnectedDatabase::remove();
        }
    }

    public function testExportsCheckRunsTheFullQueryWithLimitZero()
    {
        $pdo = new RecordingPdo();
        $list = $this->list($pdo);
        $list->exportPreflight(self::FILTER . self::SEARCH['sql'], self::SEARCH['params'] + self::PARAMS);

        $statement = $pdo->log[count($pdo->log) - 1];
        $this->assertSame($list->selectSql() . $list->fromSql() . self::FILTER . self::SEARCH['sql'] . ' LIMIT 0', $statement['sql']);
        $this->assertStringContainsString(', dfd.renew', $statement['sql']);
        $this->assertSame(self::SEARCH['params'] + self::PARAMS, $statement['params']);
    }

    // A column that's gone fails the check, at prepare() or at execute(), before anything is written
    public function testExportsCheckThrowsAnUnknownColumn()
    {
        $error = new \PDOException("Unknown column 'dfd.renew'");
        $error->errorInfo = array('42S22', 1054, "Unknown column 'dfd.renew'");
        $pdo = new RecordingPdo(array('LIMIT 0' => $error));

        try {
            $this->list($pdo)->exportPreflight(self::FILTER, self::PARAMS);
            $this->fail('The check should have thrown');
        } catch (\PDOException $e) {
            $this->assertTrue(\DomainMOD\CustomFieldFilter::fieldsChangedError($e));
        }
    }

    // The SSL list's custom field data alias for its SELECT columns
    public function testTheSslListsColumnsComeFromItsFields()
    {
        $list = new SslList(new RecordingPdo(), 1, array(array('id' => 1, 'name' => 'Ref', 'field_name' => 'order_ref', 'type_id' => 2)));
        $this->assertStringEndsWith(', sslfd.order_ref AS cf1_order_ref', $list->selectSql());
        $this->assertSame(array(array('id' => 1, 'name' => 'Ref', 'field_name' => 'order_ref', 'type_id' => 2)), $list->fields());
    }

    private function segmentPdo(array $fields_now)
    {
        return new RecordingPdo(array(
            'LOCK IN SHARE MODE' => array(array(array('segment' => "'a.com'"))),
            'FROM segment_data' => array(array(array('id' => 1, 'domain' => 'a.com')), array()),
            'SELECT DISTINCT d.domain' => array(array(array('domain' => 'a.com'))),
            'FROM domain_fields' => array($fields_now)));
    }

    // With custom field filters, the flags are only committed when the fields are still the ones the page read
    public function testTheSegmentFlagsRollBackWhenTheFieldsChanged()
    {
        $fields = array(array('id' => '1', 'name' => 'Renew', 'field_name' => 'renew', 'type_id' => '1'));
        $key = (new \DomainMOD\CustomFieldFilter($fields, 'dfd'))->fieldsKey();

        $pdo = $this->segmentPdo($fields);
        $this->assertSame('written', $this->list($pdo)->writeSegmentFlags(7, self::FILTER, "'a.com'", self::PARAMS, $key));
        $this->assertNotEmpty($pdo->statementsWith('COMMIT'));
        $this->assertSame(self::PARAMS + array('sg0' => 'a.com'), $pdo->statementsWith('SELECT DISTINCT d.domain')[0]['params']);

        // Deleted and added again under the same name (a new id), while the flags were being written
        $replaced = array(array('id' => '2') + $fields[0]);
        $pdo = $this->segmentPdo($replaced);
        $this->assertSame('fields_changed', $this->list($pdo)->writeSegmentFlags(7, self::FILTER, "'a.com'", self::PARAMS, $key));
        $this->assertSame(array(), $pdo->statementsWith('COMMIT'));
        $this->assertNotEmpty($pdo->statementsWith('ROLLBACK'));

        // Without custom field filters, the fields aren't read
        $pdo = $this->segmentPdo($replaced);
        $this->assertSame('written', $this->list($pdo)->writeSegmentFlags(7, '', "'a.com'"));
        $this->assertSame(array(), $pdo->statementsWith('FROM domain_fields'));
    }

} //@formatter:on
