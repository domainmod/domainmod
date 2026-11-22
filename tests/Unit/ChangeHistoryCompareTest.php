<?php
/**
 * /tests/Unit/ChangeHistoryCompareTest.php
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

use DomainMOD\ChangeHistory;
use DomainMOD\ChangeHistoryOperation;
use DomainMOD\RestoreLock;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

/*
 * The Change History's comparison rules, its cutting of long values, its snapshots, what it never records, and its
 * refusal to start outside a transaction
 */
class ChangeHistoryCompareTest extends TestCase
{
    protected function tearDown(): void
    {
        RestoreLock::$test_state = null;
        ChangeHistory::forgetAvailability();
    }

    // A connection whose history is off (as before the database update), set in isAvailable()'s cache, since a stand-in
    // connection can't run its MariaDB locking read
    private function offConnection()
    {
        $pdo = new RecordingPdo();
        $cache = new \ReflectionProperty(ChangeHistory::class, 'available');
        $cache->setValue(null, array(spl_object_id($pdo) => false));
        return $pdo;
    }

    public function testValuesAreComparedByTheirKind()
    {
        $this->assertSame('2026-01-02', ChangeHistory::normalize('date', '2026-01-02 00:00:00'));
        $this->assertSame('10.50', ChangeHistory::normalize('cost', '10.5'));
        $this->assertSame('7', ChangeHistory::normalize('id', '07'));
        $this->assertSame('', ChangeHistory::normalize('text', null));
        $this->assertTrue(ChangeHistory::same('cost', array('v' => '10.5', 'd' => 'USD'), array('v' => '10.50', 'd' => 'USD')));
        $this->assertFalse(ChangeHistory::same('cost', array('v' => '10', 'd' => 'USD'), array('v' => '10', 'd' => 'CAD')));
        $this->assertTrue(ChangeHistory::same('int', array('v' => '1'), array('v' => 1)));
        $this->assertFalse(ChangeHistory::same('text', array('v' => 'a'), array('v' => 'A')));
    }

    /*
     * A date or time stamp that was never set reads as blank: the column default (1970-01-01) and the zero date a blank
     * custom field is saved as (the connection's sql_mode isn't strict) are both '', so Change History shows them blank,
     * as the edit page does, and a move from one to the other isn't a change
     */
    public function testAnUnsetDateOrTimeStampIsBlank()
    {
        foreach (array('date', 'stamp') as $kind) {
            foreach (array('1970-01-01', '0000-00-00', '1970-01-01 00:00:00', '0000-00-00 00:00:00', '', null) as $unset) {
                $this->assertSame('', ChangeHistory::normalize($kind, $unset));
            }
        }
        $this->assertSame('2026-01-02', ChangeHistory::normalize('date', '2026-01-02'));
        $this->assertSame('2026-01-02 03:04:05', ChangeHistory::normalize('stamp', '2026-01-02 03:04:05'));
        // A real time near 1970 is still a time
        $this->assertSame('1970-01-01 00:00:01', ChangeHistory::normalize('stamp', '1970-01-01 00:00:01'));
        $this->assertTrue(ChangeHistory::same('date', array('v' => '1970-01-01'), array('v' => '0000-00-00')));
        $this->assertTrue(ChangeHistory::same('stamp', array('v' => '1970-01-01 00:00:00'), array('v' => '0000-00-00 00:00:00')));
        $this->assertFalse(ChangeHistory::same('stamp', array('v' => '0000-00-00 00:00:00'), array('v' => '2026-01-02 03:04:05')));
        // Custom Date and Time Stamp fields are compared that way
        $this->assertSame('date', ChangeHistory::CUSTOM_FIELD_KINDS[4]);
        $this->assertSame('stamp', ChangeHistory::CUSTOM_FIELD_KINDS[5]);
    }

    public function testLongTextIsComparedByItsHashAndLength()
    {
        $old = array('v' => 'same start', 'h' => hash('sha256', 'one'), 'l' => 3);
        $new = array('v' => 'same start', 'h' => hash('sha256', 'two'), 'l' => 3);
        $this->assertFalse(ChangeHistory::same('long', $old, $new));
        $this->assertTrue(ChangeHistory::same('long', $old, $old));
    }

    public function testCuttingNeverSplitsACharacter()
    {
        list($kept, $cut) = ChangeHistory::cut(str_repeat('é', 10), 5);
        $this->assertSame(str_repeat('é', 2), $kept);
        $this->assertTrue($cut);
        $this->assertSame(array('abc', false), ChangeHistory::cut('abc', 5));
        list($kept) = ChangeHistory::cut('a' . "\u{1F600}", 4);
        $this->assertSame('a', $kept);
    }

    public function testASnapshotKeepsEveryShortFieldAndMarksCutValues()
    {
        $values = array('notes' => array('v' => str_repeat('n', 300000), 'd' => '', 'long' => true),
            'domain' => array('v' => 'example.com', 'd' => ''), 'cat_id' => array('v' => '3', 'd' => 'Personal'));
        $json = ChangeHistory::snapshotJson($values);
        $this->assertLessThanOrEqual(ChangeHistory::SNAPSHOT_BYTES, strlen($json));
        $snapshot = json_decode($json, true);
        $this->assertSame('example.com', $snapshot['domain']['v']);
        $this->assertSame('Personal', $snapshot['cat_id']['d']);
        $this->assertSame(1, $snapshot['notes']['cut']);
    }

    public function testAValueCutAtCaptureIsMarkedEvenWhenItFitsItsShare()
    {
        $json = ChangeHistory::snapshotJson(array('notes' => array('v' => 'short prefix', 'd' => '', 'long' => true, 'cut' => true)));
        $this->assertSame(1, json_decode($json, true)['notes']['cut']);
    }

    public function testASnapshotWithEscapedCharactersStillFits()
    {
        $values = array('a' => array('v' => str_repeat('"\\', 200000), 'd' => '', 'long' => true),
            'b' => array('v' => str_repeat("\x01", 200000), 'd' => '', 'long' => true));
        $this->assertLessThanOrEqual(ChangeHistory::SNAPSHOT_BYTES, strlen(ChangeHistory::snapshotJson($values)));
    }

    public function testNothingSecretIsEverRecorded()
    {
        foreach (ChangeHistory::FIELDS as $fields) {
            $this->assertSame(array(), array_intersect(array_keys($fields), ChangeHistory::DENY));
        }
        $this->assertSame(array(), array_intersect(ChangeHistory::EXTRA_FIELDS, ChangeHistory::DENY));
    }

    public function testInternalColumnsAreNotRecorded()
    {
        foreach (array('fee_id', 'fee_fixed', 'tld', 'creation_type_id', 'created_by', 'insert_time', 'update_time',
                     'transfer_lock', 'epp_status', 'epp_status_time', 'epp_status_failed_time') as $column) {
            $this->assertArrayNotHasKey($column, ChangeHistory::FIELDS['domain']);
        }
    }

    public function testChunksAreSizedForTheWorstCase()
    {
        $this->assertSame(ChangeHistory::MAX_CHUNK, ChangeHistoryOperation::chunkSizeFor(0, 15));
        $this->assertLessThanOrEqual(85, ChangeHistoryOperation::chunkSizeFor(1, 15));
        $this->assertSame(1, ChangeHistoryOperation::chunkSizeFor(200, 0));
    }

    public function testItRefusesToStartOutsideATransaction()
    {
        RestoreLock::$test_state = array('state' => 'held', 'flag' => false);
        $this->expectException(\LogicException::class);
        (new ChangeHistory($this->offConnection()))->start(ChangeHistory::SOURCE_USER, 1);
    }

    public function testBeforeTheUpgradeItRecordsNothing()
    {
        RestoreLock::$test_state = array('state' => 'held', 'flag' => false);
        $pdo = $this->offConnection();
        $pdo->beginTransaction();
        $op = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_USER, 1);
        $this->assertFalse($op->enabled());
        $this->assertSame(array(), $op->capture('domain', array(1, 2))['images']);
        $op->finish();
        $this->assertSame(0, $op->operationId());
    }

    public function testARestoreStopsEveryRecordedWrite()
    {
        RestoreLock::$test_state = array('state' => 'held', 'flag' => true);
        $pdo = $this->offConnection();
        $pdo->beginTransaction();
        $this->expectException(\DomainMOD\RestoreLockException::class);
        (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_USER, 1);
    }

    // The pruning cutoff keeps the day within the target month, so it never removes a day too many
    public function testThePruningCutoffStaysInTheTargetMonth()
    {
        $this->assertSame('2027-02-28 12:00:00', ChangeHistory::monthsBefore('2028-02-29 12:00:00', 12));
        $this->assertSame('2026-02-28 00:00:00', ChangeHistory::monthsBefore('2026-03-31 00:00:00', 1));
        $this->assertSame('2023-11-20 08:00:00', ChangeHistory::monthsBefore('2026-11-20 08:00:00', 36));
    }

    public function testTheBackupTablesUseTheNewestEntryAtOrBelowTheVersion()
    {
        $this->assertNull(ChangeHistory::backupTables('4.26.0'));
        $this->assertContains('change_history', ChangeHistory::backupTables('4.27.0'));
        $this->assertSame(ChangeHistory::backupTables('4.27.0'), ChangeHistory::backupTables('4.28.1'));
    }

    // Every table install/go.php creates is in the newest full-backup list, and, once install/go.php creates the
    // history tables (after the held install patches are applied), the two match exactly
    public function testTheBackupTablesMatchTheInstaller()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/install/go.php');
        preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/', $source, $matches);
        $versions = array_keys(ChangeHistory::BACKUP_TABLES);
        usort($versions, 'version_compare');
        $listed = ChangeHistory::BACKUP_TABLES[end($versions)];
        $this->assertSame(array(), array_diff($matches[1], $listed));
        if (in_array('change_history', $matches[1], true)) {
            $installed = $matches[1];
            sort($installed);
            sort($listed);
            $this->assertSame($listed, $installed);
            foreach (ChangeHistory::tableSql() as $sql) {
                $this->assertStringContainsString(preg_replace('/\s+/', ' ', trim($sql)), preg_replace('/\s+/', ' ', $source));
            }
        }
    }

} //@formatter:on
