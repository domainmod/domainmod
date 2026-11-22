<?php
/**
 * /tests/Unit/WatchListTest.php
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
use DomainMOD\ChangeHistoryList;
use DomainMOD\ChangeHistoryListColumns;
use DomainMOD\ChangeHistoryOperation;
use DomainMOD\Export;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\WatchList;
use DomainMOD\WatchListColumns;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The Watch List's list, paste limits, table, messages, and export cells, and the Change History's watch item type
 * (its recorder path with no custom fields or fees, its filters, names, values, and links)
 */
#[RunTestsInSeparateProcesses]
class WatchListTest extends TestCase
{
    protected function setUp(): void
    {
        define('WEB_ROOT', '/dm');
        define('SOFTWARE_TITLE', 'DomainMOD');
    }

    public function testTheFiltersAreChecked()
    {
        $state = WatchList::normalizeState(array('stage' => 'redemption', 'reason' => 'catch', 'tld' => '.COM', 'drop' => '7',
            'added' => 'me', 'archived' => 'only'));
        $this->assertSame(array('stage' => 'redemption', 'reason' => 'catch', 'tld' => 'com', 'drop' => '7', 'added' => 'me',
            'archived' => 'only'), $state);
        $state = WatchList::normalizeState(array('stage' => 'nope', 'reason' => 'x', 'tld' => 'a b', 'drop' => '9', 'archived' => 'x'));
        $this->assertSame(array('stage' => '', 'reason' => '', 'tld' => '', 'drop' => '', 'added' => '', 'archived' => 'hide'), $state);
    }

    public function testTheFiltersSql()
    {
        $list = new WatchList(new RecordingPdo(), 4);
        $parts = $list->filterParts(WatchList::normalizeState(array('drop' => '7', 'added' => 'me')), '2027-03-10');
        $this->assertStringContainsString('w.archived = 0', $parts['sql']);
        $this->assertSame(array('f_drop_end' => '2027-03-17', 'f_drop_start' => '2027-03-10', 'f_added' => 4), $parts['params']);
        $this->assertStringNotContainsString('archived', $list->filterParts(WatchList::normalizeState(array('archived' => 'show')))['sql']);
    }

    public function testAListThatsTooLongIsRefusedBeforeItsRead()
    {
        $list = new WatchList(new RecordingPdo(), 1);
        $too_long = 'The list is too long. Please paste up to 1,000 domains at a time.';
        // 100,000 characters are read (they hold no domain, so they're refused for that instead), and one more isn't
        $this->assertSame('Enter the domains to watch', $list->checkPaste(str_repeat(' ', 99996) . "\r\n\r\n")['error']);
        $this->assertSame($too_long, $list->checkPaste(str_repeat(' ', 100001))['error']);
        $this->assertSame(array(), $list->checkPaste(str_repeat(' ', 100001))['names']);
        // 5,000 lines (4,999 line breaks) are read, and 5,001 aren't
        $this->assertSame('Enter the domains to watch', $list->checkPaste(str_repeat("\n", 4999))['error']);
        $this->assertSame($too_long, $list->checkPaste(str_repeat("\n", 5000))['error']);
    }

    public function testTheNotesHashIgnoresHowLinesBreak()
    {
        // Notes saved with line feeds alone match the same notes as a browser sends them back
        $this->assertSame(WatchList::notesHash("\nOne\nTwo"), WatchList::notesHash("\r\nOne\r\nTwo"));
        $this->assertSame(WatchList::notesHash("One\rTwo"), WatchList::notesHash("One\r\nTwo"));
        $this->assertNotSame(WatchList::notesHash("\nOne"), WatchList::notesHash('One'));
    }

    public function testTheTableMatchesTheInstaller()
    {
        $source = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/install/go.php'));
        if (strpos($source, '`watch_domains`') === false) $this->markTestSkipped('install/go.php has no Watch List yet (its held patch)');
        $this->assertStringContainsString(preg_replace('/\s+/', ' ', trim(WatchList::tableSql())), $source);
        $this->assertContains('watch_domains', ChangeHistory::backupTables('4.27.0'));
    }

    public function testResultMessages()
    {
        $this->assertSame('2 domains added to the Watch List. Checks begin on the next scheduled run. 1 domain was already on the Watch List.',
            WatchList::resultMessage(array('ok' => true, 'added' => 2, 'unarchived' => 0, 'skipped' => 1, 'live' => array(), 'room' => -1, 'busy' => false)));
        $this->assertStringContainsString('room for 3 more', WatchList::resultMessage(array('room' => 3, 'live' => array(), 'busy' => false)));
        $live = WatchList::resultMessage(array('live' => array('a.com', 'b.com', 'c.com', 'd.com', 'e.com', 'f.com', 'g.com'), 'busy' => false));
        $this->assertStringStartsWith('You already have these domains in DomainMOD: a.com, b.com', $live);
        $this->assertStringEndsWith('Plus 2 others', $live);
    }

    private function exportRow(array $values = array())
    {
        return (object) ($values + array('id' => 3, 'domain' => 'example.com', 'stage' => 'redemption', 'has_baseline' => 1,
            'pending_stage' => '', 'failures' => 0, 'last_attempt' => '2027-03-10 09:00:00', 'statuses' => 'redemptionPeriod,pendingDelete',
            'expiry_date' => '2027-01-01', 'registration_date' => '1970-01-01', 'drop_from' => '2027-03-15', 'drop_to' => '2027-04-14',
            'drop_basis' => 'redemption', 'registrar_name' => 'Example &amp; Co', 'reason' => 'catch', 'notes' => '=1+2',
            'added_by' => "\u{2066}=cmd\u{2069} User", 'insert_time' => '2027-01-01 00:00:00', 'archived' => 0, 'created_by' => 2,
            'next_due' => '2027-03-11 09:00:00', 'registrar_iana_id' => '', 'tld' => 'com'));
    }

    public function testExportCellsAreProtected()
    {
        $columns = new WatchListColumns(array('s_default_timezone' => 'UTC'));
        $cells = $columns->exportCells(new Export(), $this->exportRow());
        $this->assertCount(count(WatchListColumns::exportHeadings()), $cells);
        $this->assertSame(array('example.com', 'In redemption', 'redemptionPeriod, pendingDelete', '2027-01-01', '', '2027-03-15',
            '2027-04-14', 'Estimate', 'Example & Co', 'Try to catch when it drops', "'=1+2", "'=cmd User"), array_slice($cells, 0, 12));
        // A deleted creator is shown as Unknown user
        $this->assertSame('Unknown user', $columns->exportCells(new Export(), $this->exportRow(array('added_by' => '')))[11]);
    }

    public function testCellsAreEscapedAndReadOnlyUsersGetNoTickBoxes()
    {
        $columns = new WatchListColumns(array('s_default_timezone' => 'UTC', 's_read_only' => '0'));
        $this->assertSame('select', $columns->columns()[0]['key']);
        $cells = $columns->cells($this->exportRow(array('domain' => 'x<b>.com')), '');
        $this->assertStringContainsString('x&lt;b&gt;.com', $cells[1]);
        $this->assertStringContainsString('form="watch-bulk-form"', $cells[0]);
        // A latest attempt that didn't update the details says so beside its time
        $failed = $columns->cells($this->exportRow(array('last_success' => '2027-03-09 09:00:00')), '');
        $this->assertStringContainsString('not updated', $failed[10]);
        $this->assertStringNotContainsString('not updated', $columns->cells($this->exportRow(array('last_success' => '2027-03-10 09:00:00')), '')[10]);
        $read_only = new WatchListColumns(array('s_default_timezone' => 'UTC', 's_read_only' => '1'));
        $this->assertSame('domain', $read_only->columns()[0]['key']);
        // A pending delete entry without a fast slot says so in Next Check
        $columns->setFastSlots(array(), strtotime('2027-03-10 09:00:00 UTC'));
        $next = $columns->cells($this->exportRow(array('stage' => 'pending_delete')), '')[11];
        $this->assertStringContainsString('fast checks full', $next);
    }

    public function testTheAddedColumnFollowsAddedBy()
    {
        $columns = new WatchListColumns(array('s_default_timezone' => 'Pacific/Honolulu', 's_read_only' => '0'));
        $keys = array_column($columns->columns(), 'key');
        $this->assertSame(array('select', 'domain', 'stage', 'expiry', 'registered', 'drop', 'registrar', 'reason', 'added_by',
            'added', 'checked', 'next'), $keys);
        $index = array_search('added', $keys, true);
        $this->assertSame('Added', $columns->columns()[$index]['title']);
        $this->assertSame('<th class="domainmod-list-added">Added</th>', $columns->headerCells()[$index]);
        // Oldest or newest first by when it was added, with the entry's id for entries added together
        $this->assertSame(array('w.insert_time', 'w.id'), $columns->sortMap()[$index]);
        $this->assertSame(' ORDER BY w.insert_time DESC, w.id DESC, w.domain ASC, w.id ASC',
            WatchList::orderSql(array(array($index, 'desc')), $columns->sortMap(), $columns->defaultOrder()));
        $this->assertSame(' ORDER BY w.insert_time ASC, w.id ASC, w.domain ASC, w.id ASC',
            WatchList::orderSql(array(array($index, 'asc')), $columns->sortMap(), $columns->defaultOrder()));
        // The date only, in the viewer's time zone (05:00 UTC on 2027-01-02 is still 2027-01-01 in Honolulu), isolated
        // left to right, and blank when it's unset
        $this->assertSame('<bdi dir="ltr">2027-01-01</bdi>', $columns->cells($this->exportRow(array('insert_time' => '2027-01-02 05:00:00')), '')[$index]);
        $this->assertSame('<bdi dir="ltr">2027-01-02</bdi>', $columns->cells($this->exportRow(array('insert_time' => '2027-01-02 12:00:00')), '')[$index]);
        $this->assertSame('', $columns->cells($this->exportRow(array('insert_time' => '1970-01-01 00:00:00')), '')[$index]);
        // Read-only users get it in the same place, without the tick boxes
        $read_only = new WatchListColumns(array('s_default_timezone' => 'UTC', 's_read_only' => '1'));
        $this->assertSame(array_slice($keys, 1), array_column($read_only->columns(), 'key'));
    }

    public function testTheRegisteredColumnFollowsTheExpiryColumn()
    {
        $columns = new WatchListColumns(array('s_default_timezone' => 'UTC', 's_read_only' => '0'));
        $keys = array_column($columns->columns(), 'key');
        $index = array_search('registered', $keys, true);
        $this->assertSame(array_search('expiry', $keys, true) + 1, $index);
        $this->assertSame('Registered', $columns->columns()[$index]['title']);
        // It sorts with the same expressions as Expiry
        $this->assertSame(array("w.registration_date = '1970-01-01'", 'w.registration_date'), $columns->sortMap()[$index]);
        $this->assertSame('<th class="domainmod-list-registered">Registered</th>', $columns->headerCells()[$index]);
        // The date shows only once the entry has details, and never when it's unset
        $this->assertSame('2020-05-06', $columns->cells($this->exportRow(array('registration_date' => '2020-05-06')), '')[$index]);
        $this->assertSame('', $columns->cells($this->exportRow(), '')[$index]);
        $this->assertSame('', $columns->cells($this->exportRow(array('registration_date' => '2020-05-06', 'has_baseline' => 0)), '')[$index]);
        $this->assertSame('&lt;b&gt;', $columns->cells($this->exportRow(array('registration_date' => '<b>')), '')[$index]);
        // Read-only users get the same columns, without the tick boxes
        $read_only = new WatchListColumns(array('s_default_timezone' => 'UTC', 's_read_only' => '1'));
        $this->assertSame(array_slice($keys, 1), array_column($read_only->columns(), 'key'));
    }

    public function testTheConfirmNoteSaysWhenTheSecondCheckIsDue()
    {
        $columns = new WatchListColumns(array('s_default_timezone' => 'Asia/Tokyo'));
        $now = strtotime('2027-03-10 09:05:00 UTC');
        $waiting = $this->exportRow(array('pending_stage' => 'not_registered', 'last_attempt' => '2027-03-10 09:00:00'));
        // Due 15 minutes after the last attempt, in the viewer's time zone, escaped and isolated left to right
        $this->assertSame('A new result will be checked again around <bdi dir="ltr">2027-03-10 18:15</bdi>, and it&#039;s recorded only if the second lookup agrees.',
            $columns->confirmNoteHtml($waiting, $now, true));
        // A first answer waiting for its confirmation (the Checking stage) gets the same note
        $first = $this->exportRow(array('pending_stage' => 'redemption', 'has_baseline' => 0, 'last_attempt' => '2027-03-10 09:00:00'));
        $this->assertStringContainsString('around <bdi dir="ltr">2027-03-10 18:15</bdi>', $columns->confirmNoteHtml($first, $now, true));
        // Once the due time has passed, it waits for the task, and Check Now is offered only to users who can press it
        $late = strtotime('2027-03-10 09:15:00 UTC');
        $this->assertSame("A new result is waiting for a second lookup by the Check Watch List task, and it&#039;s recorded only if that lookup agrees. You can also press Check Now.",
            $columns->confirmNoteHtml($waiting, $late, true));
        $this->assertSame("A new result is waiting for a second lookup by the Check Watch List task, and it&#039;s recorded only if that lookup agrees.",
            $columns->confirmNoteHtml($waiting, $late, false));
        // Nothing waiting, an archived entry, or a first check that failed: no note
        $this->assertSame('', $columns->confirmNoteHtml($this->exportRow(), $now, true));
        $this->assertSame('', $columns->confirmNoteHtml($this->exportRow(array('pending_stage' => 'not_registered', 'archived' => 1)), $now, true));
        $this->assertSame('', $columns->confirmNoteHtml($this->exportRow(array('pending_stage' => 'redemption', 'has_baseline' => 0, 'failures' => 2)), $now, true));
    }

    public function testTheHistoryAcceptsWatchedDomains()
    {
        $state = ChangeHistoryList::normalizeState(array('type' => 'watch', 'id' => '4', 'field' => 'cf:region'));
        $this->assertSame(array('watch', 4, ''), array($state['type'], $state['id'], $state['field']));
        // A custom field filter stays for the other types and with no type
        $this->assertSame('cf:region', ChangeHistoryList::normalizeState(array('field' => 'cf:region'))['field']);
        $this->assertSame('cf:region', ChangeHistoryList::normalizeState(array('type' => 'domain', 'field' => 'cf:region'))['field']);

        $this->assertSame('Watched Domain', ChangeHistoryListColumns::typeName('watch'));
        $row = (object) array('id' => 5, 'item_type' => 'watch', 'item_id' => 4, 'change_type' => 'update', 'last_delete_id' => 0);
        $this->assertSame('/dm/watch/edit.php?wid=4', ChangeHistoryListColumns::itemUrl($row));
        $names = ChangeHistoryListColumns::fieldNames('watch');
        foreach (array('stage', 'expiry_date', 'registrar', 'statuses', 'reason', 'notes', 'archived', 'moved_domain_id') as $field) {
            $this->assertArrayHasKey($field, $names);
        }
        $this->assertSame('Watch List: Scheduled Check, RDAP', ChangeHistoryListColumns::sourceText('watch_list', 'watch_check', 'rdap'));
        foreach (array('watch_check_now', 'watch_add', 'watch_edit', 'watch_archive', 'watch_unarchive', 'watch_delete', 'watch_bulk_archive',
                     'watch_bulk_unarchive', 'watch_bulk_delete', 'watch_bulk_reason', 'watch_moved', 'watch_move') as $action) {
            $this->assertNotSame('', ChangeHistoryListColumns::actionNames()[$action] ?? '', $action);
        }
    }

    public function testWatchedDomainValuesReadAsThePageShowsThem()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $this->assertSame('In redemption', $columns->valueText('watch', 'stage', 'redemption', ''));
        $this->assertSame('Backorder placed', $columns->valueText('watch', 'reason', 'backorder', ''));
        $this->assertSame('Yes', $columns->valueText('watch', 'archived', '1', ''));
        $this->assertSame('#42', $columns->valueText('watch', 'moved_domain_id', '42', ''));
        $this->assertSame('None', $columns->valueText('watch', 'moved_domain_id', '0', ''));
        $this->assertSame("redemptionPeriod\npendingDelete", $columns->valueText('watch', 'statuses', 'redemptionPeriod,pendingDelete', ''));
        $this->assertSame('A & B (IANA ID 77)', $columns->valueText('watch', 'registrar', 'A &amp; B', '77'));

        // A deleted entry's snapshot, with its labels, its translated stage, an unset expiry date as the page words it,
        // and a blank unset registration date
        $snapshot = json_encode(array('reason' => array('v' => 'watch'), 'fact:stage' => array('v' => 'pending_delete'),
            'fact:expiry_date' => array('v' => '1970-01-01'), 'fact:registration_date' => array('v' => '1970-01-01'),
            'fact:moved_domain_id' => array('v' => '42'), 'fact:statuses' => array('v' => 'pendingDelete')));
        $lines = $columns->snapshotLines((object) array('item_type' => 'watch', 'snapshot' => $snapshot));
        $this->assertContains(array('Stage', 'Pending delete'), $lines);
        $this->assertContains(array('Expiry Date', 'Expiry date not published.'), $lines);
        $this->assertContains(array('Registration Date', ''), $lines);
        $this->assertContains(array('Moved to Domain', '#42'), $lines);
        $this->assertContains(array('Registry Status', 'pendingDelete'), $lines);

        // A domain that wasn't registered, or hadn't been checked yet, has no expiry date to publish, so it stays blank
        // (as the page shows it), and a set date shows as it is
        foreach (array('not_registered', 'unknown') as $stage) {
            $snapshot = json_encode(array('fact:stage' => array('v' => $stage), 'fact:expiry_date' => array('v' => '1970-01-01')));
            $lines = $columns->snapshotLines((object) array('item_type' => 'watch', 'snapshot' => $snapshot));
            $this->assertContains(array('Expiry Date', ''), $lines, $stage);
        }
        $snapshot = json_encode(array('fact:stage' => array('v' => 'registered'), 'fact:expiry_date' => array('v' => '2027-03-04')));
        $lines = $columns->snapshotLines((object) array('item_type' => 'watch', 'snapshot' => $snapshot));
        $this->assertContains(array('Expiry Date', '2027-03-04'), $lines);
    }

    public function testTheRecorderReadsNoCustomFieldsOrCostsForWatchedDomains()
    {
        $pdo = new RecordingPdo();
        $op = new ChangeHistoryOperation($pdo, true, ChangeHistory::SOURCE_WATCH_LIST, 1, 'watch_edit');
        $before = count($pdo->log);
        $capture = $op->capture('watch', array(3), array('reason'));
        $this->assertSame('watch', $capture['type']);
        foreach (array_slice($pdo->log, $before) as $statement) {
            $this->assertStringNotContainsString('_fields', $statement['sql']);
            $this->assertStringNotContainsString('i.fee_id', $statement['sql']);
        }
        $this->expectException(\LogicException::class);
        $op->captureCosts('watch');
    }

    // A paste finds the entries already there before its transaction and locks them inside it through the primary key in
    // ascending id order (lockRows(), as the bulk actions lock them), never by a locking read by name, and records the
    // unarchived ones in the order they were pasted
    public function testAPasteLocksByIdAndKeepsItsOrder()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/DomainMOD/WatchList.php');
        $add = substr($source, strpos($source, 'public function add('));
        $add = substr($add, 0, strpos($add, "\n    }\n"));
        $lookup = substr($add, strpos($add, 'WHERE domain IN ('), 200);
        $this->assertStringNotContainsString('FOR UPDATE', substr($lookup, 0, strpos($lookup, '");')));
        // the lookup by name runs before the transaction starts (opening no snapshot in it), the locks inside it
        $this->assertLessThan(strpos($add, '$this->pdo->beginTransaction();'), strpos($add, 'WHERE domain IN ('));
        $this->assertGreaterThan(strpos($add, '$this->pdo->beginTransaction();'), strpos($add, 'foreach ($this->lockRows($ids) as $row)'));
        $this->assertStringNotContainsString('sort($unarchive)', $add);
        $this->assertStringContainsString("\$op->capture('watch', \$unarchive,", $add);
    }

} //@formatter:on
