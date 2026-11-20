<?php
/**
 * /tests/Unit/SegmentResultsTest.php
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

use DomainMOD\ListQuery;
use DomainMOD\Segment;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The segment results export's chunked reader (Segment::resultsChunks()) and its count (Segment::resultsCount()), against
// a stand-in database that pages through the segment's rows the way the real keyset queries would
class SegmentResultsTest extends TestCase
{
    // The segment's rows: array(name, id, bytes), in any order
    private $rows = array();

    // How many times the rows query returns each id (2 for a domain a damaged fees table joins twice)
    private $copies = array();

    private $pdo;

    protected function setUp(): void
    {
        $this->pdo = new RecordingPdo(array(
            'AS ks_id' => function ($sql, $params) {
                return $this->keysAnswer($sql, $params);
            },
            'SELECT id, domain' => function ($sql, $params) {
                return $this->keysAnswer($sql, $params);
            },
            'SELECT d.id, d.domain' => function ($sql, $params) {
                return $this->rowsAnswer($params);
            },
            'SELECT COUNT(*)' => array(array(array('count' => 7)))));
    }

    // $count rows named d1.com, d2.com, and so on, with ids in the opposite order to their names, so the export order
    // (name, then id) is never the id order
    private function named($count, $bytes = 100)
    {
        $rows = array();
        for ($n = 1; $n <= $count; $n++) {
            $rows[] = array(sprintf('d%03d.com', $n), 1000 - $n, $bytes);
        }
        return $rows;
    }

    private function sorted(array $rows)
    {
        usort($rows, function ($a, $b) {
            return array($a[0], $a[1]) <=> array($b[0], $b[1]);
        });
        return $rows;
    }

    // A keys query's rows: those after the keyset's name and id, in the export order, up to the query's LIMIT
    private function keysAnswer($sql, array $params)
    {
        $this->assertSame(1, preg_match('/LIMIT (\d+)\s*$/', $sql, $match));
        $rows = $this->sorted($this->rows);
        if (array_key_exists('ks_d', $params)) {
            $this->assertSame($params['ks_d'], $params['ks_d2']);
            $rows = array_values(array_filter($rows, function ($row) use ($params) {
                return $row[0] > $params['ks_d'] || ($row[0] === $params['ks_d'] && $row[1] > $params['ks_i']);
            }));
        }
        $answer = array();
        foreach (array_slice($rows, 0, (int) $match[1]) as $row) {
            // The missing domains' query reads only each row's id and name
            $answer[] = strpos($sql, 'SELECT id, domain') !== false ? array('id' => $row[1], 'domain' => $row[0])
                : array('ks_id' => $row[1], 'ks_name' => $row[0], 'ks_bytes' => $row[2]);
        }
        return $answer;
    }

    // A rows query's rows: the rows with the ids it names, in the export order, each as many times as it joins
    private function rowsAnswer(array $params)
    {
        $ids = array();
        foreach ($params as $name => $value) {
            if (strpos($name, 'ks_id') === 0) $ids[] = $value;
        }
        $answer = array();
        foreach ($this->sorted($this->rows) as $row) {
            if (!in_array($row[1], $ids, true)) continue;
            for ($copy = 0; $copy < ($this->copies[$row[1]] ?? 1); $copy++) {
                $answer[] = array('id' => $row[1], 'domain' => $row[0]);
            }
        }
        return $answer;
    }

    // Every row the reader gives, as array(name, id), and the number of groups
    private function read($type, $keys_per_chunk)
    {
        $read = array();
        $groups = 0;
        foreach (Segment::resultsChunks($this->pdo, $type, 1, 5, $keys_per_chunk) as $group) {
            $groups++;
            foreach ($group as $id => $row) {
                // A missing domain is only its name, keyed by its id
                $read[] = $type === 'missing' ? array($row, $id) : array($row->domain, $row->id);
            }
        }
        return array($read, $groups);
    }

    private function expected($with_ids = true)
    {
        return array_map(function ($row) use ($with_ids) {
            return array($row[0], $with_ids ? $row[1] : null);
        }, $this->sorted($this->rows));
    }

    public static function chunkSizes()
    {
        return array('one at a time' => array(1), 'uneven' => array(3), 'exact' => array(5), 'all at once' => array(1000));
    }

    #[DataProvider('chunkSizes')]
    public function testEveryInactiveOrFilteredRowIsReadOnceInOrder($keys_per_chunk)
    {
        // Two domains share a name, as two rows can in the domains table, and a lower id comes after a higher one
        $this->rows = array_merge($this->named(9), array(array('d004.com', 5, 100), array('d004.com', 7, 100)));

        foreach (array('inactive', 'filtered') as $type) {
            $this->pdo->log = array();
            list($read) = $this->read($type, $keys_per_chunk);
            $this->assertSame($this->expected(), $read);

            foreach ($this->pdo->statementsWith('AS ks_id') as $statement) {
                $this->assertStringContainsString("AND " . $type . " = '1'", $statement['sql']);
                $this->assertSame(5, $statement['params']['segid']);
            }
        }
    }

    #[DataProvider('chunkSizes')]
    public function testEveryMissingDomainIsReadOnceInOrder($keys_per_chunk)
    {
        $this->rows = array_merge($this->named(9), array(array('d004.com', 5, 0)));

        list($read, $groups) = $this->read('missing', $keys_per_chunk);
        $this->assertSame($this->expected(), $read);
        // Each chunk is one query, with no rows query
        $this->assertSame($groups, count($this->pdo->statementsWith("missing = '1'")) - (10 % $keys_per_chunk === 0 ? 1 : 0));
        $this->assertSame(array(), $this->pdo->statementsWith('SELECT d.id, d.domain'));
    }

    public function testAFullLastChunkEndsWithAnEmptyQuery()
    {
        $this->rows = $this->named(4);
        $this->read('filtered', 2);
        $keys_queries = $this->pdo->statementsWith('AS ks_id');
        $this->assertCount(3, $keys_queries);
        $this->assertSame(array('ks_d' => 'd004.com', 'ks_d2' => 'd004.com', 'ks_i' => 996),
            array_diff_key($keys_queries[2]['params'], array('segid' => true)));
        // Each chunk starts after the last name, or the same name with a higher id, as the order is name, then id
        $this->assertStringContainsString(' AND (d.domain > :ks_d OR (d.domain = :ks_d2 AND d.id > :ks_i))'
            . ' ORDER BY d.domain ASC, d.id ASC LIMIT 2', preg_replace('/\s+/', ' ', $keys_queries[2]['sql']));
        $this->assertStringNotContainsString(':ks_d', $keys_queries[0]['sql']);

        $this->pdo->log = array();
        $this->read('missing', 2);
        $this->assertStringContainsString(" AND missing = '1' AND (domain > :ks_d OR (domain = :ks_d2 AND id > :ks_i))"
            . ' ORDER BY domain ASC, id ASC LIMIT 2',
            preg_replace('/\s+/', ' ', $this->pdo->statementsWith('SELECT id, domain')[1]['sql']));
    }

    public function testRowsAreReadInGroupsByTheirEstimatedSize()
    {
        // Rows estimated at a third of the budget each, so a chunk of 7 is read in three groups (3, 3, 1)
        $this->rows = $this->named(7, intdiv(ListQuery::EXPORT_GROUP_BYTES, 3));

        list($read, $groups) = $this->read('inactive', 1000);
        $this->assertSame($this->expected(), $read);
        $this->assertSame(3, $groups);

        $sizes = array_map(function ($statement) {
            return count(array_filter(array_keys($statement['params']), function ($name) {
                return strpos($name, 'ks_id') === 0;
            }));
        }, $this->pdo->statementsWith('SELECT d.id, d.domain'));
        $this->assertSame(array(3, 3, 1), $sizes);
    }

    public function testARowBiggerThanTheBudgetIsAGroupOnItsOwn()
    {
        $this->rows = array(array('a.com', 1, 100), array('b.com', 2, ListQuery::EXPORT_GROUP_BYTES * 2),
            array('c.com', 3, 100));

        list($read, $groups) = $this->read('filtered', 1000);
        $this->assertSame($this->expected(), $read);
        $this->assertSame(3, $groups);
    }

    public function testTheKeysQueryEstimatesEachRowFromItsNotes()
    {
        $this->rows = $this->named(1);
        $this->read('inactive', 10);
        $sql = $this->pdo->statementsWith('AS ks_id')[0]['sql'];
        $this->assertStringContainsString('COALESCE(LENGTH(d.notes), 0) + ' . ListQuery::EXPORT_ROW_BYTES . ' AS ks_bytes',
            $sql);
        $this->assertStringContainsString('ORDER BY d.domain ASC, d.id ASC', $sql);
    }

    public function testEveryJoinedCopyOfARowIsWritten()
    {
        // A domain a damaged fees table joins twice is written twice, as the count counts it twice
        $this->rows = $this->named(3);
        $this->copies = array(998 => 2);

        list($read) = $this->read('inactive', 2);
        $this->assertSame(array(array('d001.com', 999), array('d002.com', 998), array('d002.com', 998),
            array('d003.com', 997)), $read);

        foreach ($this->pdo->statementsWith('SELECT d.id, d.domain') as $statement) {
            $this->assertSame(1, $statement['params']['user_id']);
            $this->assertStringContainsString('LEFT JOIN fees AS f', $statement['sql']);
            $this->assertStringContainsString('ORDER BY d.domain ASC, d.id ASC', $statement['sql']);
            // The keys query matched them against the segment already, in the same snapshot
            $this->assertStringNotContainsString('segment_data', $statement['sql']);
            $this->assertArrayNotHasKey('segid', $statement['params']);
        }
    }

    public function testTheDefaultChunkSizes()
    {
        $this->rows = $this->named(2);
        Segment::resultsChunks($this->pdo, 'missing', 1, 5)->current();
        Segment::resultsChunks($this->pdo, 'filtered', 1, 5)->current();
        $keys = $this->pdo->statementsWith('LIMIT');
        $this->assertStringEndsWith('LIMIT ' . Segment::RESULTS_MISSING_KEYS, trim($keys[0]['sql']));
        $this->assertStringEndsWith('LIMIT ' . Segment::RESULTS_KEYS, trim($keys[1]['sql']));
    }

    public function testTheCountUsesTheSameJoinsAsTheRows()
    {
        $this->assertSame(7, Segment::resultsCount($this->pdo, 'filtered', 1, 5));
        $statement = $this->pdo->statementsWith('SELECT COUNT(*)')[0];
        $this->assertStringContainsString('LEFT JOIN fees AS f', $statement['sql']);
        $this->assertStringContainsString("AND filtered = '1'", $statement['sql']);
        $this->assertSame(array('user_id' => 1, 'segid' => 5), $statement['params']);

        $this->pdo->log = array();
        $this->assertSame(7, Segment::resultsCount($this->pdo, 'missing', 1, 5));
        $statement = $this->pdo->statementsWith('SELECT COUNT(*)')[0];
        $this->assertStringContainsString("AND missing = '1'", $statement['sql']);
        $this->assertSame(array('segid' => 5), $statement['params']);
    }

    public function testThePageQueryIsUnchanged()
    {
        $sql = preg_replace('/\s+/', ' ', Segment::resultsSql('filtered'));
        $this->assertStringStartsWith(' SELECT d.id, d.domain, d.tld,', $sql);
        $this->assertStringEndsWith("WHERE d.domain IN (SELECT domain FROM segment_data WHERE segment_id = :segid AND "
            . "filtered = '1') ORDER BY d.domain ASC", $sql);
        // Anything but 'filtered' reads the inactive flag
        $this->assertStringContainsString("AND inactive = '1'", Segment::resultsSql('something else'));
    }

} //@formatter:on
