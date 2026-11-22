<?php
/**
 * /tests/Unit/DomainListSegmentTest.php
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
use DomainMOD\Segment;
use DomainMOD\Tests\Support\QuotingPdo;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

// The Domains list's segment filter, from a stored domain list the page has already read, and the segment's "Matching
// But Filtered Domains" count, worked out from the list's own filters
class DomainListSegmentTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }
    }

    public function testTheSegmentFilterUsesTheListItIsGiven()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $state = DomainList::normalizeState(array('segid' => '7'));

        $parts = $list->filterParts($state, '', '', '', "'a.com', 'b.com'");

        $this->assertSame(" AND d.domain IN ('a.com', 'b.com')", $parts['segid_string']);
    }

    public function testNoSegmentNeedsNoList()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());

        $parts = $list->filterParts(DomainList::normalizeState(array()), '', '', '', "'a.com'");

        $this->assertSame('', $parts['segid_string']);
    }

    public function testTheFilteredCountCarriesTheListsFiltersAndParameters()
    {
        $pdo = new RecordingPdo(array('FROM segment_data AS sd' => array(array(array('count(*)' => 12)))));
        $list = new DomainList($pdo, 1, array());
        $filter_sql = " AND d.active IN ('1') AND d.domain IN ('a.com', 'b.com') AND (d.domain LIKE :sw0 ESCAPE '\\\\')";

        $count = $list->segmentFilteredCount(7, $filter_sql, array('sw0' => '%a%'));

        $this->assertSame(12, $count);
        $query = $pdo->statementsWith('FROM segment_data AS sd')[0];
        $sql = preg_replace('/\s+/', ' ', $query['sql']);
        $this->assertStringContainsString("WHERE sd.segment_id = :sf_segid AND sd.active = '1' AND NOT EXISTS (SELECT 1 "
            . preg_replace('/\s+/', ' ', trim($list->fromSql())) . preg_replace('/\s+/', ' ', $filter_sql)
            . " AND d.domain = sd.domain)", $sql);
        $this->assertSame(array('sf_segid' => 7, 'sw0' => '%a%'), $query['params']);
        // One plain read, and no other statement apart from the version checks (the join order's, Live DNS's, and whether
        // hosting is available, see DomainList::hostingAvailable()), with no locking clause or write in any of them
        foreach ($pdo->log as $entry) {
            $this->assertDoesNotMatchRegularExpression('/FOR UPDATE|LOCK IN SHARE MODE|UPDATE |INSERT |DELETE /',
                $entry['sql']);
        }
        $this->assertCount(1, $pdo->statementsWith('FROM segment_data AS sd'));
        $this->assertLessThanOrEqual(3, count($pdo->statementsWith('FROM settings')));
        $this->assertCount(count($pdo->statementsWith('FROM settings')) + 1, $pdo->log);
    }

    public function testTheStoredRowIsOnePlainReadThatNeverLogs()
    {
        $pdo = new RecordingPdo(array('FROM segments' => array(array(
            array('name' => 'Seg', 'number_of_domains' => 2, 'segment' => "'a.com', 'b.com'")))));

        $row = Segment::storedRow($pdo, 7);

        $this->assertSame("'a.com', 'b.com'", $row->segment);
        $this->assertSame('Seg', $row->name);
        $this->assertCount(1, $pdo->log);
        $this->assertSame(array('seg_id' => 7), $pdo->log[0]['params']);
    }

    public function testAMissingSegmentIsFalse()
    {
        $pdo = new RecordingPdo();

        $this->assertFalse(Segment::storedRow($pdo, 7));
        // Only the read, with no log entry written
        $this->assertCount(1, $pdo->log);
    }

} //@formatter:on
