<?php
/**
 * /tests/Unit/ChangeHistoryListTest.php
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
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The Change History list: its filters from a request, its date ranges in the viewer's time zone, the lifetime rule for
 * an item picked by id, its cells (escaping, right-to-left isolation, deleted items), and the notes' changed lines
 */
#[RunTestsInSeparateProcesses]
class ChangeHistoryListTest extends TestCase
{
    protected function setUp(): void
    {
        define('WEB_ROOT', '/dm');
        define('SOFTWARE_TITLE', 'DomainMOD');
    }

    public function testTheFiltersAreChecked()
    {
        $state = ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '12', 'life' => '40', 'source' => 'bulk',
            'range' => 'all', 'field' => 'cf:purchaser', 'op' => 'x'));
        $this->assertSame(array('domain', 12, 40, 'bulk', 'all', 'cf:purchaser', 0),
            array($state['type'], $state['id'], $state['life'], $state['source'], $state['range'], $state['field'], $state['op']));

        // An id needs a type, life needs an id, and anything unknown is its default
        $state = ChangeHistoryList::normalizeState(array('id' => '12', 'life' => '40', 'source' => 'nope', 'range' => '45',
            'field' => 'a b'));
        $this->assertSame(array('', 0, 0, '', '30', ''),
            array($state['type'], $state['id'], $state['life'], $state['source'], $state['range'], $state['field']));
        $this->assertSame('', ChangeHistoryList::normalizeState(array('range' => '7', 'daterange' => 'x'))['daterange']);
    }

    public function testACustomRangeNeedsTwoRealDatesInOrder()
    {
        $state = ChangeHistoryList::normalizeState(array('range' => 'custom', 'daterange' => '2026-01-01 - 2026-01-31'));
        $this->assertSame(array('custom', '2026-01-01 - 2026-01-31', false), array($state['range'], $state['daterange'], $state['range_invalid']));

        // An impossible date, a reversed range, or a single date falls back to the default range and says so
        foreach (array('2026-02-01 - 2026-02-30', '2026-11-20 - 2026-11-01', '2026-11-20', 'invalid') as $dates) {
            $state = ChangeHistoryList::normalizeState(array('range' => 'custom', 'daterange' => $dates));
            $this->assertSame(array(ChangeHistoryList::DEFAULT_RANGE, '', true), array($state['range'], $state['daterange'], $state['range_invalid']), $dates);
        }
    }

    public function testAnEmptyLifeShowsNothingRatherThanTheCurrentItem()
    {
        $this->assertSame(-1, ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '12', 'life' => ''))['life']);
        $this->assertSame(0, ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '12'))['life']);
    }

    public function testRangesCountWholeDaysInTheViewersTimeZone()
    {
        // 03:00 UTC on 2026-11-20 is still 2026-11-19 in Honolulu (UTC-10, with no clock changes)
        list($from, $to) = ChangeHistoryList::rangeTimes('today', 'Pacific/Honolulu', '', '', '2026-11-20 03:00:00 UTC');
        // and a preset ends where today ends, as a custom range does
        $this->assertSame(array('2026-11-19 10:00:00', '2026-11-20 10:00:00'), array($from, $to));
        list($from) = ChangeHistoryList::rangeTimes('7', 'UTC', '', '', '2026-11-20 12:00:00 UTC');
        $this->assertSame('2026-11-14 00:00:00', $from);
        $this->assertSame(array('', ''), ChangeHistoryList::rangeTimes('all', 'UTC'));
        $this->assertSame(array('2026-01-01 10:00:00', '2026-01-03 10:00:00'),
            ChangeHistoryList::rangeTimes('custom', 'Pacific/Honolulu', '2026-01-01', '2026-01-02'));
    }

    // A day whose midnight a clock change skips (Santiago, 2026-09-06) starts at its first real moment, and no hour is
    // carried into another day
    public function testAClockChangeAtMidnightMovesNoHour()
    {
        list($from, $to) = ChangeHistoryList::rangeTimes('custom', 'America/Santiago', '2026-09-01', '2026-09-06');
        $this->assertSame(array('2026-09-01 04:00:00', '2026-09-07 03:00:00'), array($from, $to));
        list($from) = ChangeHistoryList::rangeTimes('7', 'America/Santiago', '', '', '2026-09-06 18:00:00 UTC');
        $this->assertSame('2026-08-31 04:00:00', $from);
    }

    public function testAnItemShowsOnlyItsCurrentLife()
    {
        $list = new ChangeHistoryList(new RecordingPdo(), 1);
        $filter = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5')), '', '');
        $this->assertStringContainsString("d.change_type = 'delete'", $filter['sql']);
        $this->assertStringContainsString('ch.id > COALESCE', $filter['sql']);
        $this->assertSame(array('f_type' => 'domain', 'f_id' => 5, 'f_cur_type' => 'domain', 'f_cur_id' => 5), $filter['params']);
    }

    public function testALifeThatIsNoLongerKeptShowsNothing()
    {
        // Row 40 is domain 5's delete row, and row 41 is SSL certificate 5's
        $pdo = new RecordingPdo(array('WHERE id = :life' => function ($sql, array $params) {
            return array(array(((int) $params['life'] === 40 && $params['item_type'] === 'domain') ? 1 : 0));
        }));
        $list = new ChangeHistoryList($pdo, 1);
        $kept = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5', 'life' => '40')), '', '');
        $this->assertTrue($kept['life_ok']);
        $this->assertStringContainsString('ch.id <= :f_life', $kept['sql']);
        // Another item's delete row isn't this item's life
        $other = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5', 'life' => '41')), '', '');
        $this->assertFalse($other['life_ok']);
        $this->assertStringContainsString('AND 1 = 0', $other['sql']);
    }

    // The filters run against an id three items used in turn (each life ends with its delete row)
    public function testEachLifeOfAReusedIdIsKeptApart()
    {
        // Run against real SQL where this PHP has SQLite. Without it (one of the test images), the same cases are checked
        // as the bounds the filter binds, rather than skipped.
        $pdo = $this->memoryDb();
        if ($pdo === null) {
            // Row 40 is domain 5's delete row
            $list = new ChangeHistoryList(new RecordingPdo(array('WHERE id = :life' => function ($sql, array $params) {
                return array(array((int) $params['life'] === 40 ? 1 : 0));
            })), 1);
            $life = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5', 'life' => '40')), '', '');
            $this->assertStringContainsString('ch.id <= :f_life', $life['sql']);
            $this->assertStringContainsString('d.id < :f_life_before', $life['sql']);
            $this->assertSame(array(40, 40), array($life['params']['f_life'], $life['params']['f_life_before']));
            $current = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5')), '', '');
            $this->assertStringContainsString('ch.id > COALESCE', $current['sql']);
            $this->assertStringNotContainsString(':f_life', $current['sql']);
            $bad = $list->filterParts(ChangeHistoryList::normalizeState(array('type' => 'domain', 'id' => '5', 'life' => 'x')), '', '');
            $this->assertStringContainsString('1 = 0', $bad['sql']);
            return;
        }
        $pdo->exec("CREATE TABLE change_history (id INTEGER, item_type TEXT, item_id INTEGER, change_type TEXT)");
        $pdo->exec("INSERT INTO change_history VALUES (10, 'domain', 5, 'update'), (20, 'domain', 5, 'delete'),
            (30, 'domain', 5, 'create'), (35, 'ssl', 5, 'delete'), (40, 'domain', 5, 'delete'), (50, 'domain', 5, 'create')");
        $list = new ChangeHistoryList($pdo, 1);
        $ids = function (array $get) use ($list, $pdo) {
            $filter = $list->filterParts(ChangeHistoryList::normalizeState($get + array('type' => 'domain', 'id' => '5')), '', '');
            $stmt = $pdo->prepare("SELECT ch.id" . $list->fromSql() . $filter['sql'] . " ORDER BY ch.id");
            $stmt->execute($filter['params']);
            return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        };
        $this->assertSame(array(50), $ids(array()));
        $this->assertSame(array(30, 40), $ids(array('life' => '40')));
        $this->assertSame(array(10, 20), $ids(array('life' => '20')));
        // A life that isn't a whole number shows nothing, never the current item
        $this->assertSame(array(), $ids(array('life' => 'x')));
    }

    private function row(array $values)
    {
        return (object) array_merge(array('id' => 10, 'operation_id' => 3, 'item_type' => 'domain', 'item_id' => 5,
            'item_name' => 'example.com', 'change_type' => 'update', 'field' => 'function', 'old_value' => '',
            'new_value' => '', 'old_display' => '', 'new_display' => '', 'snapshot' => '', 'via' => '', 'detail' => '',
            'source' => 'user', 'user_id' => 1, 'changed_time' => '2026-11-20 03:00:00', 'op_username' => 'admin',
            'op_note' => '', 'op_note_code' => '', 'op_action' => '', 'last_delete_id' => null), $values);
    }

    public function testCellsDecodeStoredTextAndEscapeItOnce()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'Pacific/Honolulu'));
        $cells = $columns->cells($this->row(array('old_value' => 'R&amp;D', 'new_value' => '<b>x</b>')), '');
        // When stays left to right and on one line
        $this->assertSame('<bdi dir="ltr" class="text-nowrap">2026-11-19 17:00</bdi>', $cells[0]);
        $this->assertSame('<a href="/dm/domains/edit.php?did=5"><bdi dir="ltr">example.com</bdi></a>', $cells[1]);
        $this->assertSame('R&amp;D', $cells[4]);
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', $cells[5]);
    }

    public function testADeletedItemIsNamedButNotLinked()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $cells = $columns->cells($this->row(array('last_delete_id' => 11)), '');
        $this->assertStringNotContainsString('<a ', $cells[1]);
        $this->assertStringContainsString('(Deleted)', $cells[1]);
        // A delete row never links, even when a new item has the id now
        $this->assertSame('', ChangeHistoryListColumns::itemUrl($this->row(array('change_type' => 'delete'))));
    }

    public function testValuesReadInWords()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $this->assertSame('Yes', $columns->valueText('domain', 'privacy', '1', ''));
        $this->assertSame('Sold', $columns->valueText('domain', 'active', '10', ''));
        $this->assertSame('None', $columns->valueText('ssl', 'domain_id', '0', ''));
        $this->assertSame('10.99 USD', $columns->valueText('domain', 'total_cost', '10.99', 'USD'));
        $this->assertSame('10.99 (no currency)', $columns->valueText('domain', 'total_cost', '10.99', ''));
    }

    // A custom check box shows Yes or No only where its row or snapshot recorded it as one (a field's name may have been
    // reused for another kind of field since), and the stored value otherwise
    public function testACustomCheckBoxReadsYesOrNoOnlyWhereRecordedAsOne()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $this->assertSame('Yes', $columns->valueText('domain', 'cf:spf', '1', '', true));
        $this->assertSame('No', $columns->valueText('domain', 'cf:spf', '0', '', true));
        $this->assertSame('1', $columns->valueText('domain', 'cf:spf', '1', ''));
        $this->assertSame('maybe', $columns->valueText('domain', 'cf:spf', 'maybe', '', true));
        $this->assertTrue(ChangeHistoryListColumns::isCheckbox($this->row(array('field' => 'cf:spf', 'detail' => 'cf_checkbox'))));
        $this->assertFalse(ChangeHistoryListColumns::isCheckbox($this->row(array('field' => 'cf:spf'))));
        $cells = $columns->cells($this->row(array('field' => 'cf:spf', 'old_value' => '0', 'new_value' => '1',
            'old_display' => 'SPF', 'detail' => 'cf_checkbox')), '');
        $this->assertSame('No', $cells[4]);
        $this->assertSame('Yes', $cells[5]);
    }

    // A snapshot's facts: the creator by username, the time it was added in local time, and the TLD with its dot
    public function testASnapshotsFactsReadPlainly()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'Pacific/Honolulu'));
        $columns->setFactNames(array(), array(7 => 'rashdown'));
        $lines = $columns->snapshotLines($this->row(array('change_type' => 'delete', 'snapshot' => json_encode(array(
            'cf:spf' => array('v' => '1', 'name' => 'SPF Record', 'cb' => 1),
            'fact:created_by' => array('v' => '7'), 'fact:insert_time' => array('v' => '2026-11-20 13:00:00'),
            'fact:tld' => array('v' => 'com'))))));
        $read = array();
        foreach ($lines as $line) $read[$line[0]] = $line[1];
        $this->assertSame('Yes', $read['SPF Record']);
        $this->assertSame('rashdown', $read['Created By']);
        $this->assertSame('2026-11-20 03:00', $read['Added On']);
        $this->assertSame('.com', $read['TLD']);
    }

    // A Notes change that only added lines says so in From
    public function testANotesChangeThatOnlyAddedLinesSaysNothingWasRemoved()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => 'first', 'new_value' => "first\nsecond")), '');
        $this->assertStringContainsString('Nothing removed', $cells[4]);
        $this->assertStringNotContainsString('Nothing removed', $cells[5]);
        // Not when a line was removed, a blank one included
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => "first\ngone", 'new_value' => "first\nsecond")), '');
        $this->assertStringNotContainsString('Nothing removed', $cells[4]);
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => "First\n\nSecond", 'new_value' => "First\nSecond\nThird")), '');
        $this->assertStringNotContainsString('Nothing removed', $cells[4]);
        // Not when only the line breaks changed
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => "First\r\nSecond", 'new_value' => "First\nSecond")), '');
        $this->assertStringNotContainsString('Nothing removed', $cells[4]);
        // Only a blank line added says so too
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => "First\nSecond", 'new_value' => "First\n\nSecond")), '');
        $this->assertStringContainsString('Nothing removed', $cells[4]);
        // An empty note has no lines, as onlyAdded() counts it
        $cells = $columns->cells($this->row(array('field' => 'notes', 'old_value' => '', 'new_value' => ' ')), '');
        $this->assertStringContainsString('Nothing removed', $cells[4]);
        $this->assertSame(0, ChangeHistoryListColumns::lineCount(''));
        $this->assertSame(1, ChangeHistoryListColumns::lineCount('First'));
        $this->assertSame(3, ChangeHistoryListColumns::lineCount("First\r\n\nThird"));
        $this->assertTrue(ChangeHistoryListColumns::onlyAdded('', "First"));
    }

    // One action's rows keep the order they were recorded in, whichever way the actions are sorted
    public function testAnActionsRowsKeepTheirRecordedOrder()
    {
        $map = (new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC')))->sortMap();
        $this->assertSame(' ORDER BY ch.changed_time DESC, ch.operation_id DESC, ch.id ASC',
            ChangeHistoryList::orderSql(array(), $map, array(array(0, 'desc'))));
        $this->assertSame(' ORDER BY ch.changed_time ASC, ch.operation_id ASC, ch.id ASC',
            ChangeHistoryList::orderSql(array(array(0, 'asc')), $map, array(array(0, 'desc'))));
    }

    // Item, Type, Source, and By sort by what they show, and the other four columns don't sort
    public function testItemTypeSourceAndBySortByWhatTheyShow()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $keys = array_column($columns->columns(), 'key');
        $this->assertSame(array('when', 'item', 'type', 'field', 'from', 'to', 'source', 'by', 'note'), $keys);
        $map = $columns->sortMap();
        $sorted = array();
        foreach ($map as $index => $expressions) $sorted[] = $keys[$index];
        $this->assertSame(array('when', 'item', 'type', 'source', 'by'), $sorted);
        foreach (array('field', 'from', 'to', 'note') as $key) {
            $this->assertArrayNotHasKey(array_search($key, $keys, true), $map, $key);
        }

        $item = array_search('item', $keys, true);
        $type = array_search('type', $keys, true);
        $source = array_search('source', $keys, true);
        $by = array_search('by', $keys, true);
        $this->assertSame(array('ch.item_name'), $map[$item]);
        // A type and a source by their names, in the viewer's language, and an unknown code as itself
        $this->assertSame(array("CASE ch.item_type WHEN 'domain' THEN 'Domain' WHEN 'ssl' THEN 'SSL Certificate'"
            . " WHEN 'watch' THEN 'Watched Domain' ELSE ch.item_type END"), $map[$type]);
        // A source by its name, the first part of its cell
        $this->assertCount(1, $map[$source]);
        $this->assertStringStartsWith("CASE ch.source WHEN 'user' THEN 'Add or Edit Page' WHEN 'bulk' THEN 'Bulk Updater'", $map[$source][0]);
        $this->assertStringContainsString(" WHEN 'system' THEN 'DomainMOD' ELSE ch.source END", $map[$source][0]);
        foreach (ChangeHistory::SOURCES as $code) {
            $this->assertStringContainsString(" WHEN '" . $code . "' THEN "
                . ChangeHistoryListColumns::sqlText(ChangeHistoryListColumns::sourceText($code, '', '')) . ' ', $map[$source][0], $code);
        }
        // Who made the change by the username recorded with the action, decoded as its cell shows it, DomainMOD for the
        // system, or the user's id when no username was recorded
        $this->assertCount(1, $map[$by]);
        $this->assertStringStartsWith("CASE WHEN ch.user_id = 0 THEN 'DomainMOD'", $map[$by][0]);
        $this->assertStringContainsString("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE((SELECT co.username FROM change_operations AS co WHERE co.id = ch.operation_id), '&lt;', '<')", $map[$by][0]);
        $this->assertStringEndsWith(", '&amp;', '&'), ''), CONCAT('#', ch.user_id)) END", $map[$by][0]);
        // which is what the cells show
        $this->assertSame('DomainMOD', $columns->byText($this->row(array('user_id' => 0, 'op_username' => ''))));
        $this->assertSame('#42', $columns->byText($this->row(array('user_id' => 42, 'op_username' => ''))));
        $this->assertSame('#42', $columns->byText($this->row(array('user_id' => 42, 'op_username' => null))));
        $this->assertSame('R&D', $columns->byText($this->row(array('user_id' => 42, 'op_username' => 'R&amp;D'))));

        // Each way, the rows that tie go newest first, one action's rows in the order they were recorded
        $ties = ', ch.changed_time DESC, ch.operation_id DESC, ch.id ASC';
        $default = $columns->defaultOrder();
        $this->assertSame(' ORDER BY ch.item_name ASC' . $ties, ChangeHistoryList::orderSql(array(array($item, 'asc')), $map, $default));
        $this->assertSame(' ORDER BY ch.item_name DESC' . $ties, ChangeHistoryList::orderSql(array(array($item, 'desc')), $map, $default));
        foreach (array($type, $source, $by) as $index) {
            foreach (array('asc', 'desc') as $direction) {
                $this->assertSame(' ORDER BY ' . implode(' ' . strtoupper($direction) . ', ', $map[$index]) . ' ' . strtoupper($direction) . $ties,
                    ChangeHistoryList::orderSql(array(array($index, $direction)), $map, $default));
            }
        }
        // An order that already has the When column keeps it as it is, and a column after When still sorts the rows that
        // tie on it, the row's id coming last
        $this->assertSame(' ORDER BY ch.item_name ASC, ch.changed_time ASC, ch.operation_id ASC, ch.id ASC',
            ChangeHistoryList::orderSql(array(array($item, 'asc'), array(0, 'asc')), $map, $default));
        $this->assertSame(' ORDER BY ch.changed_time DESC, ch.operation_id DESC, ch.item_name DESC, ch.id ASC',
            ChangeHistoryList::orderSql(array(array(0, 'desc'), array($item, 'desc')), $map, $default));
        // A column that doesn't sort falls back to When
        $this->assertSame(' ORDER BY ch.changed_time DESC, ch.operation_id DESC, ch.id ASC',
            ChangeHistoryList::orderSql(array(array(array_search('note', $keys, true), 'asc')), $map, $default));
    }

    // A name's quotes and backslashes can't end the literal it's put in, and stored text is decoded as the cells show it
    public function testASortNameIsAlwaysOneLiteral()
    {
        $this->assertSame("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u, '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&#039;', ''''), '&amp;', '&')",
            ChangeHistoryListColumns::decodedSql('u'));
        $this->assertSame("'It''s'", ChangeHistoryListColumns::sqlText("It's"));
        $this->assertSame("'a\\\\'' OR 1 = 1 -- '", ChangeHistoryListColumns::sqlText("a\\' OR 1 = 1 -- "));
        $this->assertSame("CASE x WHEN 'a''b' THEN 'c''d' ELSE x END", ChangeHistoryListColumns::nameSort('x', array("a'b" => "c'd")));
    }

    public function testANotesChangeShowsOnlyTheLinesAddedOrRemoved()
    {
        $this->assertSame(array(array('old line'), array('new line')),
            ChangeHistoryListColumns::lineChanges("kept\nold line", "new line\nkept"));
        $this->assertSame(array(array(), array()), ChangeHistoryListColumns::lineChanges("a\nb", "a\nb"));
    }

    public function testSourcesNameTheirActionAndLookup()
    {
        $this->assertSame('Bulk Updater: Change DNS Profile', ChangeHistoryListColumns::sourceText('bulk', 'bulk_CDNS', ''));
        $this->assertSame('Refresh, RDAP', ChangeHistoryListColumns::sourceText('refresh', 'domain_refresh', 'rdap'));
    }

    // The export strips the isolation marks before formula protection, so a mark can't hide a formula from it
    public function testAnExportCellCantHideAFormulaBehindAnIsolationMark()
    {
        $export = new \DomainMOD\Export();
        $this->assertSame("'=1+1", ChangeHistoryListColumns::exportCell($export, "\u{2066}=1+1\u{2069}"));
        $this->assertSame('plain', ChangeHistoryListColumns::exportCell($export, "\u{2066}plain\u{2069}"));
    }

    // The export reads in the list's order (newest action first, each action's rows by ascending id), even when a second's
    // rows span the key reads and the row chunks
    public function testTheExportKeepsTheListsOrder()
    {
        $pdo = $this->memoryDb();
        if ($pdo === null) {
            $this->assertSame(5000, ChangeHistoryList::EXPORT_SCAN);
            return;
        }
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $pdo->exec("CREATE TABLE change_operations (id INTEGER, username TEXT, note TEXT, note_code TEXT, `action` TEXT)");
        $pdo->exec("CREATE TABLE change_history (id INTEGER, operation_id INTEGER, item_type TEXT, item_id INTEGER, item_name TEXT,
            change_type TEXT, field TEXT, old_value TEXT, new_value TEXT, old_display TEXT, new_display TEXT, snapshot TEXT, via TEXT,
            detail TEXT, source TEXT, user_id INTEGER, changed_time TEXT)");
        $rows = array(array(1, 1, '2026-11-20 10:00:00'), array(2, 1, '2026-11-20 10:00:00'), array(3, 1, '2026-11-20 10:00:00'),
            array(4, 2, '2026-11-20 11:00:00'), array(5, 2, '2026-11-20 11:00:00'), array(6, 3, '2026-11-20 11:00:00'),
            array(7, 4, '2026-11-20 12:00:00'));
        foreach ($rows as $row) {
            $pdo->exec("INSERT INTO change_history VALUES (" . $row[0] . ", " . $row[1] . ", 'domain', " . $row[0] . ", 'x', 'update',
                'notes', '', '', '', '', '', '', '', 'user', 1, '" . $row[2] . "')");
        }
        $list = new ChangeHistoryList($pdo, 1);
        // (chunk, key read, and the most ids kept for one action: 1 reads the larger actions straight from the table)
        foreach (array(array(2, 2, 1000), array(1, 3, 1000), array(100, 100, 1000), array(2, 2, 1), array(1, 1, 1)) as $sizes) {
            $ids = array();
            foreach ($list->exportChunks('', array(), $sizes[0], $sizes[1], $sizes[2]) as $chunk) {
                foreach ($chunk as $row) $ids[] = (int) $row->id;
            }
            $this->assertSame(array(7, 6, 4, 5, 1, 2, 3), $ids, implode('/', $sizes));
        }
    }

    // An in-memory SQLite connection for a test that runs real SQL, or null on a PHP without pdo_sqlite
    private function memoryDb()
    {
        return extension_loaded('pdo_sqlite') ? new \PDO('sqlite::memory:') : null;
    }
} //@formatter:on
