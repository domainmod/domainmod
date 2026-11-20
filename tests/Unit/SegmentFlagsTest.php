<?php
/**
 * /tests/Unit/SegmentFlagsTest.php
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
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

// The Domains list's segment flags (DomainList::writeSegmentFlags()): one transaction, batch by batch, with every name
// bound, and no UPDATE with a subquery
class SegmentFlagsTest extends TestCase
{
    const SEGMENT = "'a.com', 'b.com'";

    protected function setUp(): void
    {
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }
        (new \ReflectionProperty(DomainList::class, 'segment_index'))->setValue(null, array());
    }

    // The segment's active rows, $count of them (id 1 is d00001.com, and so on), in id order, as the batch query pages
    // through them
    private function segmentRows($count)
    {
        return function ($sql, $params) use ($count) {
            $rows = array();
            for ($id = $params['sg_last'] + 1; $id <= $count && count($rows) < DomainList::SEGMENT_FLAG_BATCH; $id++) {
                $rows[] = array('id' => $id, 'domain' => sprintf('d%05d.com', $id));
            }
            return $rows;
        };
    }

    // The filters match every other domain in each batch (sg0, sg2, and so on)
    private function everyOtherMatch()
    {
        return function ($sql, $params) {
            $rows = array();
            foreach ($params as $name => $value) {
                if (preg_match('/^sg(\d+)$/', $name, $n) && $n[1] % 2 === 0) $rows[] = array('domain' => $value);
            }
            return $rows;
        };
    }

    private function pdo($domains, $segment = self::SEGMENT, array $extra = array())
    {
        return new RecordingPdo($extra + array(
            'LOCK IN SHARE MODE' => array(array(array('segment' => $segment))),
            'FROM segment_data' => $this->segmentRows($domains),
            'SELECT DISTINCT d.domain' => $this->everyOtherMatch()));
    }

    public function testItIsOneTransactionWithABatchQueryAndOneUpdatePerBatch()
    {
        $pdo = $this->pdo(DomainList::SEGMENT_FLAG_BATCH + 3);
        $list = new DomainList($pdo, 1, array());

        $result = $list->writeSegmentFlags(7, " AND d.active IN ('1')", self::SEGMENT);

        $this->assertSame('written', $result);
        $sql = array_column($pdo->log, 'sql');
        // After the index check: the isolation level, the transaction, the segment's list, then the batches
        $start = array_search('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', $sql, true);
        $this->assertSame('START TRANSACTION', $sql[$start + 1]);
        $this->assertStringContainsString('LOCK IN SHARE MODE', $sql[$start + 2]);
        $this->assertSame('COMMIT', end($sql));
        // Two batches: the full one and the three left over, each starting after the last id of the one before
        $batches = $pdo->statementsWith('FROM segment_data');
        $this->assertCount(2, $batches);
        $this->assertSame(0, $batches[0]['params']['sg_last']);
        $this->assertSame(DomainList::SEGMENT_FLAG_BATCH, $batches[1]['params']['sg_last']);
        $this->assertCount(2, $pdo->statementsWith('SELECT DISTINCT d.domain'));
        $this->assertCount(2, $pdo->statementsWith('UPDATE segment_data'));
    }

    public function testEachBatchIsTheSegmentsActiveRowsLockedInIdOrder()
    {
        $pdo = $this->pdo(3);
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);

        $batch = preg_replace('/\s+/', ' ', $pdo->statementsWith('FROM segment_data')[0]['sql']);
        $this->assertStringContainsString("WHERE segment_id = :segid AND active = '1' AND id > :sg_last ORDER BY id ASC LIMIT "
            . DomainList::SEGMENT_FLAG_BATCH . " FOR UPDATE", $batch);
        // Without the index there's no hint to name it
        $this->assertStringNotContainsString('FORCE INDEX', $batch);
    }

    // segment_data's indexes, as INFORMATION_SCHEMA.STATISTICS rows: array(index name, position, column, ignored)
    private function indexRows(array $columns)
    {
        $rows = array();
        foreach ($columns as $column) {
            $rows[] = array('TABLE_NAME' => 'segment_data', 'INDEX_NAME' => $column[0], 'SEQ_IN_INDEX' => $column[1],
                'COLUMN_NAME' => $column[2], 'IGNORED' => $column[3] ?? 'NO');
        }
        return array('INFORMATION_SCHEMA' => array($rows));
    }

    private function batchSql(array $index_rows)
    {
        $pdo = $this->pdo(3, self::SEGMENT, $this->indexRows($index_rows));
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);
        return $pdo->statementsWith('FROM segment_data')[0]['sql'];
    }

    public function testTheIndexIsNamedWhereItExists()
    {
        $this->assertStringContainsString('FROM segment_data FORCE INDEX (`segment_id`)',
            $this->batchSql(array(array('PRIMARY', 1, 'id'), array('segment_id', 1, 'segment_id'))));
    }

    public function testAnIndexOnTheSegmentsIdAloneIsNamedWhateverItsName()
    {
        // The upgrade names it segment_id_flags when another index already has the name segment_id
        $this->assertStringContainsString('FROM segment_data FORCE INDEX (`segment_id_flags`)',
            $this->batchSql(array(array('segment_id', 1, 'segment_id'), array('segment_id', 2, 'domain'),
                array('segment_id_flags', 1, 'segment_id'))));
    }

    public function testAnIndexWithMoreColumnsOrIgnoredIsNotNamed()
    {
        // Its entries aren't in id order within a segment, so the batches could lock rows out of id order
        $this->assertStringNotContainsString('FORCE INDEX',
            $this->batchSql(array(array('segment_id', 1, 'segment_id'), array('segment_id', 2, 'domain'))));
        $this->assertStringNotContainsString('FORCE INDEX',
            $this->batchSql(array(array('segment_id', 1, 'segment_id', 'YES'))));
        // An invisible index on MySQL
        $rows = $this->indexRows(array(array('segment_id', 1, 'segment_id')));
        unset($rows['INFORMATION_SCHEMA'][0][0]['IGNORED']);
        $rows['INFORMATION_SCHEMA'][0][0]['IS_VISIBLE'] = 'NO';
        $pdo = $this->pdo(3, self::SEGMENT, $rows);
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);
        $this->assertStringNotContainsString('FORCE INDEX', $pdo->statementsWith('FROM segment_data')[0]['sql']);
        $this->assertStringNotContainsString('FORCE INDEX', $this->batchSql(array(array('domain', 1, 'domain'))));
    }

    public function testEveryValueIsBoundAndNoUpdateHasASubquery()
    {
        $pdo = $this->pdo(4);
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, " AND d.active IN ('1')", self::SEGMENT);

        $update = $pdo->statementsWith('UPDATE segment_data')[0];
        $this->assertDoesNotMatchRegularExpression('/SELECT/i', $update['sql']);
        // The matched names are compared in SQL, with the column's collation, as the filters compared them
        $this->assertStringContainsString("SET filtered = CASE WHEN domain IN (:sm0, :sm1) THEN '0' ELSE '1' END "
            . "WHERE id IN (:si0, :si1, :si2, :si3)", preg_replace('/\s+/', ' ', $update['sql']));
        $this->assertSame(array('si0' => 1, 'si1' => 2, 'si2' => 3, 'si3' => 4, 'sm0' => 'd00001.com', 'sm1' => 'd00003.com'),
            $update['params']);
        $match = $pdo->statementsWith('SELECT DISTINCT d.domain')[0];
        $this->assertSame(array('sg0' => 'd00001.com', 'sg1' => 'd00002.com', 'sg2' => 'd00003.com', 'sg3' => 'd00004.com'),
            $match['params']);
        // No domain name or id is pasted into any statement
        foreach ($pdo->log as $entry) {
            $this->assertStringNotContainsString('d00001.com', $entry['sql']);
        }
    }

    public function testTheMatchedNamesReachTheUpdateAsTheDatabaseReturnedThem()
    {
        // A segment row stored with a trailing space equals the domain without it in SQL (the column's PAD SPACE
        // collation), so the filters' match clears its flag, as it did before 4.27.0
        $pdo = $this->pdo(1, self::SEGMENT, array(
            'FROM segment_data' => array(array(array('id' => 1, 'domain' => 'a.com '))),
            'SELECT DISTINCT d.domain' => array(array(array('domain' => 'a.com')))));
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);

        $update = $pdo->statementsWith('UPDATE segment_data')[0];
        $this->assertStringContainsString('CASE WHEN domain IN (:sm0)', $update['sql']);
        $this->assertSame(array('si0' => 1, 'sm0' => 'a.com'), $update['params']);
    }

    public function testABatchWithNoMatchesSetsEveryFlag()
    {
        $pdo = $this->pdo(2, self::SEGMENT, array('SELECT DISTINCT d.domain' => array(array())));
        (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);

        $update = $pdo->statementsWith('UPDATE segment_data')[0];
        $this->assertStringContainsString("SET filtered = '1'", $update['sql']);
        $this->assertSame(array('si0' => 1, 'si1' => 2), $update['params']);
    }

    public function testTheMatchesComeFromTheOtherFiltersAndTheirParameters()
    {
        $pdo = $this->pdo(2);
        $list = new DomainList($pdo, 1, array());
        $filter_sql = " AND d.active IN ('1') AND (d.domain LIKE :sw0 ESCAPE '\\\\')";

        $list->writeSegmentFlags(7, $filter_sql, self::SEGMENT, array('sw0' => '%d%'));

        $query = $pdo->statementsWith('SELECT DISTINCT d.domain')[0];
        $this->assertStringContainsString($list->fromSql() . $filter_sql . ' AND d.domain IN (:sg0, :sg1)', $query['sql']);
        $this->assertSame('%d%', $query['params']['sw0']);
        $this->assertDoesNotMatchRegularExpression('/FOR UPDATE|LOCK IN SHARE MODE/', $query['sql']);
    }

    public function testAChangedSegmentWritesNothing()
    {
        $pdo = $this->pdo(4, "'a.com', 'b.com', 'c.com'");

        $this->assertSame('changed', (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT));
        $this->assertSame('ROLLBACK', end($pdo->log)['sql']);
        $this->assertCount(0, $pdo->statementsWith('UPDATE segment_data'));
    }

    public function testADeletedSegmentWritesNothing()
    {
        $pdo = new RecordingPdo(array('LOCK IN SHARE MODE' => array(array())));

        $this->assertSame('changed', (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT));
        $this->assertCount(0, $pdo->statementsWith('UPDATE segment_data'));
    }

    private function deadlock()
    {
        $e = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
        $e->errorInfo = array('40001', 1213, 'Deadlock found when trying to get lock');
        return $e;
    }

    public function testADeadlockIsTriedOnceMore()
    {
        $tries = 0;
        $pdo = $this->pdo(2, self::SEGMENT, array('UPDATE segment_data' => function () use (&$tries) {
            if (++$tries === 1) throw $this->deadlock();
            return array();
        }));

        $this->assertSame('written', (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT));
        $this->assertSame(2, $tries);
        $sql = array_column($pdo->log, 'sql');
        $this->assertSame(1, count(array_keys($sql, 'ROLLBACK')));
        $this->assertSame('COMMIT', end($sql));
    }

    public function testASecondDeadlockIsThrownAfterTheRollback()
    {
        $pdo = $this->pdo(2, self::SEGMENT, array('UPDATE segment_data' => $this->deadlock()));

        try {
            (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);
            $this->fail('The second deadlock is thrown');
        } catch (\PDOException $e) {
            $this->assertSame(1213, $e->errorInfo[1]);
        }
        $sql = array_column($pdo->log, 'sql');
        $this->assertSame(2, count(array_keys($sql, 'ROLLBACK')));
        $this->assertSame('ROLLBACK', end($sql));
        $this->assertNotContains('COMMIT', $sql);
    }

    public function testAnyOtherErrorIsThrownAfterTheRollbackWithoutARetry()
    {
        $error = new \PDOException('Table is read only');
        $error->errorInfo = array('HY000', 1036, 'Table is read only');
        $pdo = $this->pdo(2, self::SEGMENT, array('UPDATE segment_data' => $error));

        try {
            (new DomainList($pdo, 1, array()))->writeSegmentFlags(7, '', self::SEGMENT);
            $this->fail('The error is thrown');
        } catch (\PDOException $e) {
            $this->assertSame($error, $e);
        }
        $this->assertCount(1, $pdo->statementsWith('UPDATE segment_data'));
        $this->assertSame('ROLLBACK', end($pdo->log)['sql']);
    }

} //@formatter:on
