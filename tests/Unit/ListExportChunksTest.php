<?php
/**
 * /tests/Unit/ListExportChunksTest.php
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
use DomainMOD\SslList;
use DomainMOD\Tests\Support\FieldRows;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Export and the Raw List's chunked reader (ListQuery::exportChunks() and exportKeyBatches()) and its snapshot, against
// a stand-in database that pages through a list of keys the way the real keyset queries would
class ListExportChunksTest extends TestCase
{
    /*
     * A list's custom field data rows as keys, in the export order: array(expiry, name, asset id, field data row id,
     * bytes). An asset can have more than one field data row (a damaged database).
     */
    private $keys = array();

    // Every keys query's and rows query's SQL and parameters, in the order they ran
    private $pdo;

    protected function setUp(): void
    {
        // The index checks are kept by connection object, and a new stand-in can reuse an old one's id
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }

        $this->pdo = new RecordingPdo(array(
            'AS ks_expiry' => function ($sql, $params) {
                return $this->keysAnswer($sql, $params);
            },
            'SELECT d.id, d.domain' => function ($sql, $params) {
                return $this->rowsAnswer($params);
            },
            'SELECT sslc.id, sslc.domain_id' => function ($sql, $params) {
                return $this->rowsAnswer($params);
            }));
    }

    // $count assets, one data row each, each with its own expiry date, so the export order is the asset order
    private function assets($count, $first_id = 1)
    {
        $keys = array();
        for ($id = $first_id; $id < $first_id + $count; $id++) {
            $keys[] = array(sprintf('2027-%02d-%02d', 1 + intdiv($id, 28) % 12, 1 + $id % 28), 'd' . $id . '.com', $id,
                1000 + $id, 100);
        }
        return $keys;
    }

    private function sorted(array $keys)
    {
        usort($keys, function ($a, $b) {
            return array($a[0], $a[1], $a[2]) <=> array($b[0], $b[1], $b[2]);
        });
        return $keys;
    }

    // The keys a query asks for: after the keyset, or one asset's rows after a data row's id, up to its LIMIT
    private function keysAnswer($sql, array $params)
    {
        preg_match('/LIMIT (\d+)$/', trim($sql), $limit);
        $rows = array();

        if (isset($params['ks_asset'])) {

            $matches = array_filter($this->keys, function ($key) use ($params) {
                return $key[2] == $params['ks_asset'] && (!isset($params['ks_after']) || $key[3] > $params['ks_after']);
            });
            usort($matches, function ($a, $b) {
                return $a[3] <=> $b[3];
            });

        } else {

            $matches = array_filter($this->sorted($this->keys), function ($key) use ($params) {
                if (!isset($params['ks_e'])) return true;
                return array($key[0], $key[1], $key[2]) > array($params['ks_e'], $params['ks_d'], $params['ks_i']);
            });

        }

        foreach (array_slice(array_values($matches), 0, (int) $limit[1]) as $key) {
            $rows[] = array('ks_expiry' => $key[0], 'ks_name' => $key[1], 'ks_id' => $key[2], 'ks_field_id' => $key[3],
                'ks_bytes' => $key[4]);
        }
        return $rows;
    }

    // The full rows a group asks for, by asset id and field data row id, in the export order
    private function rowsAnswer(array $params)
    {
        $ids = array();
        $field_ids = array();
        foreach ($params as $name => $value) {
            if (preg_match('/^ks_id\d+$/', $name)) $ids[] = $value;
            if (preg_match('/^ks_fd\d+$/', $name)) $field_ids[] = $value;
        }

        $rows = array();
        foreach ($this->sorted($this->keys) as $key) {
            if (in_array($key[2], $ids, true) && in_array($key[3], $field_ids, true)) {
                $rows[] = array('id' => $key[2], 'domain' => $key[1], 'field_id' => $key[3]);
            }
        }
        return $rows;
    }

    private function domainList()
    {
        return new DomainList($this->pdo, 1, FieldRows::of(', dfd.project_code'));
    }

    // Every row the reader yields, as array(asset id, field data row id), in order
    private function exported(ListQuery $list, $limit, $filter_sql = '', array $params = array())
    {
        $written = array();
        foreach ($list->exportChunks($filter_sql, $params, $limit) as $rows) {
            foreach ($rows as $row) {
                $written[] = array($row->id, $row->field_id);
            }
        }
        return $written;
    }

    private function keysQueries()
    {
        return $this->pdo->statementsWith('AS ks_expiry');
    }

    public function testEveryRowIsWrittenOnceInOrder()
    {
        $this->keys = $this->assets(10);

        $written = $this->exported($this->domainList(), 3);

        $expected = array();
        foreach ($this->sorted($this->keys) as $key) {
            $expected[] = array($key[2], $key[3]);
        }
        $this->assertSame($expected, $written);
    }

    public function testItStopsAtTheFirstShortBatch()
    {
        $this->keys = $this->assets(7);
        $this->exported($this->domainList(), 3);

        // Every batch before the last is full, and the last is short
        $counts = array();
        foreach ($this->keysQueries() as $query) {
            $counts[] = count($this->keysAnswer($query['sql'], $query['params']));
        }
        $this->assertLessThan(3, array_pop($counts));
        $this->assertSame(array(3), array_unique($counts));
    }

    public function testAnEmptyListRunsOneKeysQuery()
    {
        $this->keys = array();

        $this->assertSame(array(), $this->exported($this->domainList(), 3));
        $this->assertCount(1, $this->keysQueries());
        $this->assertCount(0, $this->pdo->statementsWith('SELECT d.id, d.domain'));
    }

    public function testEachKeysQueryStartsAfterTheLastKeptKey()
    {
        $this->keys = $this->assets(5);
        $this->exported($this->domainList(), 3);

        $queries = $this->keysQueries();
        $this->assertArrayNotHasKey('ks_e', $queries[0]['params']);
        // Each keys query is the keyset after the filters, in the export order, up to the limit
        $this->assertStringEndsWith(DomainList::EXPORT_ORDER_SQL . ' LIMIT 3', $queries[0]['sql']);
        $this->assertStringEndsWith(DomainList::keysetSql(array(1, 2, 3))['sql'] . DomainList::EXPORT_ORDER_SQL
            . ' LIMIT 3', $queries[1]['sql']);
        // The first batch (assets 1 to 3) is full, so asset 3 is put back and the next starts after asset 2
        $sorted = $this->sorted($this->keys);
        $this->assertSame(array($sorted[1][0], $sorted[1][0], $sorted[1][1], $sorted[1][1], $sorted[1][2]),
            array($queries[1]['params']['ks_e'], $queries[1]['params']['ks_e2'], $queries[1]['params']['ks_d'],
                $queries[1]['params']['ks_d2'], $queries[1]['params']['ks_i']));
    }

    public function testTheFiltersSearchAndKeysetParametersAreAllBound()
    {
        $this->keys = $this->assets(5);
        $this->exported($this->domainList(), 3, " AND d.active = '1' AND (d.domain LIKE :sw0 ESCAPE '\\\\')",
            array('sw0' => '%d%'));

        foreach ($this->pdo->log as $entry) {
            if (strpos($entry['sql'], 'FROM') === false || strpos($entry['sql'], 'settings') !== false) continue;
            $this->assertStringContainsString(" AND d.active = '1' AND (d.domain LIKE :sw0", $entry['sql']);
            $this->assertSame('%d%', $entry['params']['sw0']);
            // Every name in the SQL is bound, and nothing else is
            preg_match_all('/:([a-z0-9_]+)/', $entry['sql'], $names);
            $this->assertEqualsCanonicalizing(array_unique($names[1]), array_keys($entry['params']));
        }
    }

    public function testAGroupReadsExactlyTheRowsItsKeysSized()
    {
        $this->keys = $this->assets(2);
        $this->keys[] = array($this->keys[1][0], $this->keys[1][1], 2, 5000, 100);
        $this->exported($this->domainList(), 1000);

        $rows_query = $this->pdo->statementsWith('SELECT d.id, d.domain')[0];
        $this->assertStringContainsString(' AND d.id IN (:ks_id0, :ks_id1) AND dfd.id IN (:ks_fd0, :ks_fd1, :ks_fd2)',
            $rows_query['sql']);
        $this->assertSame(array(1, 2), array($rows_query['params']['ks_id0'], $rows_query['params']['ks_id1']));
        $this->assertEqualsCanonicalizing(array(1001, 1002, 5000), array($rows_query['params']['ks_fd0'],
            $rows_query['params']['ks_fd1'], $rows_query['params']['ks_fd2']));
        $this->assertStringEndsWith(DomainList::EXPORT_ORDER_SQL, $rows_query['sql']);
    }

    public static function limits()
    {
        return array('1,000 keys' => array(1000), '5,000 keys' => array(5000));
    }

    #[DataProvider('limits')]
    public function testTwoDataRowsForOneAssetAreBothWrittenWhenABatchEndsBetweenThem($limit)
    {
        // The asset in place $limit of the export order gets a second data row, so the first batch ends with one of its
        // two rows. That row is put back unread, and the next batch reads both.
        $this->keys = $this->assets($limit + 5);
        $duplicate = $this->sorted($this->keys)[$limit - 1];
        $this->keys[] = array($duplicate[0], $duplicate[1], $duplicate[2], 999999, 100);

        $written = $this->exported($this->domainList(), $limit);

        $this->assertCount($limit + 6, $written);
        $this->assertCount($limit + 6, array_unique(array_map('serialize', $written)));
        $this->assertContains(array($duplicate[2], $duplicate[3]), $written);
        $this->assertContains(array($duplicate[2], 999999), $written);
        // The first batch keeps fewer keys than the limit once its last asset is put back, but it isn't the last batch
        $this->assertGreaterThanOrEqual(2, count($this->keysQueries()));
    }

    public function testAnAssetWithMoreDataRowsThanABatchIsReadOnItsOwnThenTheRest()
    {
        $this->keys = $this->assets(4);
        $big = $this->sorted($this->keys)[1];
        for ($n = 1; $n <= 7; $n++) {
            $this->keys[] = array($big[0], $big[1], $big[2], 2000 + $n, 100);
        }

        $written = $this->exported($this->domainList(), 3);

        $this->assertCount(4 + 7, $written);
        $this->assertCount(4 + 7, array_unique(array_map('serialize', $written)));
        // The asset's own batches go by the data rows' ids
        $asset_queries = $this->pdo->statementsWith(':ks_asset');
        $this->assertGreaterThanOrEqual(3, count($asset_queries));
        $this->assertStringContainsString('ORDER BY dfd.id ASC', $asset_queries[0]['sql']);
        $this->assertStringContainsString(' AND d.id = :ks_asset ', $asset_queries[0]['sql']);
        $this->assertStringContainsString(' AND dfd.id > :ks_after ORDER BY', $asset_queries[1]['sql']);
        $this->assertArrayNotHasKey('ks_after', $asset_queries[0]['params']);
        $this->assertArrayHasKey('ks_after', $asset_queries[1]['params']);
        // In the export order: the asset's rows together, in its place, then every asset after it
        $expected = array();
        foreach ($this->sorted($this->keys) as $key) {
            if (end($expected) !== $key[2]) $expected[] = $key[2];
        }
        $order = array();
        foreach (array_column($written, 0) as $id) {
            if (end($order) !== $id) $order[] = $id;
        }
        $this->assertSame($expected, $order);
    }

    public function testByteGroupsSplitAtTheBudget()
    {
        $keys = array();
        foreach (array(3, 3, 3, 5, 9, 1) as $n => $bytes) {
            $keys[] = array('ks_id' => $n, 'ks_bytes' => $bytes);
        }

        $groups = ListQuery::byteGroups($keys, 8);

        $this->assertSame(array(array(0, 1), array(2, 3), array(4), array(5)), array_map(function ($group) {
            return array_column($group, 'ks_id');
        }, $groups));
    }

    public function testARowOverTheBudgetIsReadOnItsOwn()
    {
        $this->keys = $this->assets(3);
        $this->keys[1][4] = ListQuery::EXPORT_GROUP_BYTES + 1;

        $this->exported($this->domainList(), 1000);

        $groups = $this->pdo->statementsWith('SELECT d.id, d.domain');
        $this->assertCount(3, $groups);
        $this->assertStringContainsString('d.id IN (:ks_id0)', $groups[1]['sql']);
    }

    public function testEachRowsSizeCountsItsNotesAndCustomFields()
    {
        $this->keys = $this->assets(1);
        $this->exported(new SslList($this->pdo, 1, FieldRows::of(', sslfd.order_ref, sslfd.issued_at')), 1000);

        $this->assertStringContainsString('(COALESCE(LENGTH(sslc.notes), 0) + COALESCE(LENGTH(sslfd.order_ref), 0) + '
            . 'COALESCE(LENGTH(sslfd.issued_at), 0) + ' . ListQuery::EXPORT_ROW_BYTES . ') AS ks_bytes',
            $this->keysQueries()[0]['sql']);

        // The keys and the full rows both keep certificates with no domain (a LEFT JOIN on the domain)
        $rows_queries = $this->pdo->statementsWith('SELECT sslc.id, sslc.domain_id');
        $this->assertNotEmpty($rows_queries);
        foreach (array($this->keysQueries()[0]['sql'], $rows_queries[0]['sql']) as $sql) {
            $this->assertMatchesRegularExpression('/LEFT JOIN domains AS d( FORCE INDEX \(PRIMARY\))? ON d\.id = sslc\.domain_id/', $sql);
            $this->assertStringNotContainsString('sslc.domain_id = d.id', $sql);
        }
    }

    public function testTheReaderNeverStartsOrEndsATransaction()
    {
        $this->keys = $this->assets(10);
        $this->exported($this->domainList(), 3);

        foreach ($this->pdo->log as $entry) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(START TRANSACTION|COMMIT|ROLLBACK|SET TRANSACTION)/i',
                $entry['sql']);
        }
    }

    public function testTheSnapshotSendsExactlyItsStatements()
    {
        $list = $this->domainList();

        $list->beginSnapshot();
        $list->endSnapshot(true);
        $list->beginSnapshot();
        $list->endSnapshot(false);

        $this->assertSame(array('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', 'COMMIT',
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY',
            'ROLLBACK'), array_column($this->pdo->log, 'sql'));
    }

    public function testFiveThousandKeysAtATimeWithoutTheIndexes()
    {
        // No database version, so no fixed join order
        $this->assertSame(ListQuery::EXPORT_KEYS_UNINDEXED, $this->domainList()->exportKeysPerChunk());
    }

    public static function indexes()
    {
        return array('with the expiry date index' => array(true, ListQuery::EXPORT_KEYS),
            'without it' => array(false, ListQuery::EXPORT_KEYS_UNINDEXED));
    }

    #[DataProvider('indexes')]
    public function testOneThousandKeysAtATimeOnlyWithEveryIndex($expiry_index, $expected)
    {
        $pdo = new RecordingPdo(array(
            'FROM settings' => array(array(array('db_version' => '4.26.0'))),
            "'expiry_date'" => array($expiry_index ? array(array('TABLE_NAME' => 'ssl_certs', 'INDEX_NAME' => 'expiry_date',
                'COLUMN_NAME' => 'expiry_date')) : array()),
            'INFORMATION_SCHEMA' => function () {
                $rows = array(array('TABLE_NAME' => 'ssl_cert_field_data', 'INDEX_NAME' => 'ssl_id', 'COLUMN_NAME' => 'ssl_id'));
                foreach (SslList::JOIN_ORDER as $table) {
                    $rows[] = array('TABLE_NAME' => $table[0], 'INDEX_NAME' => $table[2], 'COLUMN_NAME' => 'id');
                }
                return $rows;
            }));

        $this->assertSame($expected, (new SslList($pdo, 1, array()))->exportKeysPerChunk());
    }

} //@formatter:on
