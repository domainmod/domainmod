<?php
/**
 * /classes/DomainMOD/ChangeHistoryList.php
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
namespace DomainMOD;

/*
 * The Change History list (history/index.php, its data endpoint history/list-data.php, and its export), built like the
 * Domains list: its filters come from the request, never the session, so another tab's filters can't change this tab's
 * rows. Rows are one change each, newest first. The filters only read change_history (the columns people filter on are
 * copied onto every row), and the action's username and note are read for the page's rows only (an order by who made
 * the change also reads the username of every row the filters leave, to sort them).
 *
 * An item picked by id (type and id, from an edit page) shows only its current life, the rows after the newest delete
 * row for that type and id, since a deleted item's id can be given to a new one. A deleted item's own life is picked with
 * life (its delete row's id): the rows after the delete row before it, up to and including that one.
 */
class ChangeHistoryList extends ListQuery
{
    const RANGES = array('today', '7', '30', '90', 'all', 'custom');
    const DEFAULT_RANGE = '30';
    // Rows per export chunk: a create or delete row's snapshot can be up to 256 KB, so a chunk stays near 25 MB at most
    const EXPORT_CHUNK = 100;
    // How many keys the export reads per query, in the index's order (see exportChunks())
    const EXPORT_SCAN = 5000;
    // The most ids of one action the export keeps from its keys (a larger action's rows are read in id order instead)
    const EXPORT_KEEP = 1000;

    // The largest page the list shows: a create or delete row's snapshot can be up to 256 KB
    const MAX_HISTORY_PAGE = 50;

    /*
     * The page sizes the list offers (see ListQuery::pageSizes()), up to MAX_HISTORY_PAGE, so a page of large snapshots
     * stays within a usual PHP memory limit
     */
    public static function pageSizes($preference)
    {
        $sizes = parent::pageSizes($preference);
        $sizes['sizes'] = array_values(array_filter($sizes['sizes'], function ($size) {
            return $size <= self::MAX_HISTORY_PAGE;
        }));
        if (!$sizes['sizes']) $sizes['sizes'] = array(self::MAX_HISTORY_PAGE);
        if ($sizes['default'] > self::MAX_HISTORY_PAGE) $sizes['default'] = self::MAX_HISTORY_PAGE;
        return $sizes;
    }

    public function __construct(\PDO $pdo, $user_id)
    {
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
        $this->fields = array();
        $this->field_columns = '';
    }

    /*
     * The list's order, as ListQuery::orderSql() gives it, with the row's id (ascending) as the last tie-breaker. Sorted
     * by When (the default), one action's rows stay together, since they share its time, and keep the order they were
     * recorded in (a page's form order, or the order a list was pasted), whichever way the actions are sorted, unless
     * a column after When sorts them otherwise. An order without When (by an item's name, its type, the source, or who
     * made the change) puts the rows that tie on it newest first, in the same way.
     */
    public static function orderSql(array $order, array $sort_map, array $default)
    {
        $sql = parent::orderSql($order, $sort_map, $default);
        if (strpos($sql, 'ch.changed_time') === false) $sql .= ', ch.changed_time DESC, ch.operation_id DESC';
        return $sql . ', ch.id ASC';
    }

    public function fromSql()
    {
        return "
            FROM change_history AS ch
            WHERE 1 = 1";
    }

    public function selectSql()
    {
        return "
            SELECT ch.id, ch.operation_id, ch.item_type, ch.item_id, ch.item_name, ch.change_type, ch.field, ch.old_value,
                   ch.new_value, ch.old_display, ch.new_display, ch.snapshot, ch.via, ch.detail, ch.source, ch.user_id,
                   ch.changed_time,
                   (SELECT co.username FROM change_operations AS co WHERE co.id = ch.operation_id) AS op_username,
                   (SELECT co.note FROM change_operations AS co WHERE co.id = ch.operation_id) AS op_note,
                   (SELECT co.note_code FROM change_operations AS co WHERE co.id = ch.operation_id) AS op_note_code,
                   (SELECT co.`action` FROM change_operations AS co WHERE co.id = ch.operation_id) AS op_action,
                   (SELECT MAX(d.id)
                    FROM change_history AS d
                    WHERE d.item_type = ch.item_type
                      AND d.item_id = ch.item_id
                      AND d.change_type = 'delete') AS last_delete_id";
    }

    protected function listColumns(array $settings)
    {
        return new ChangeHistoryListColumns($settings);
    }

    /*
     * The filters from a request, checked: each is its default when it's missing or not one of its allowed values.
     * type (domain or ssl), id (an item's id, only with type), life (a delete row's id, only with type and id), name (an
     * item name search), source, user, field, op (one action), range (a preset, or custom with daterange's two dates).
     */
    public static function normalizeState(array $get)
    {
        $text = function ($key) use ($get) {
            return is_string($get[$key] ?? null) ? trim($get[$key]) : '';
        };
        $int = function ($key) use ($get) {
            $value = filter_var($get[$key] ?? '', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
            return $value === false ? 0 : (int) $value;
        };

        $type = in_array($text('type'), array('domain', 'ssl'), true) ? $text('type') : '';
        $id = $type !== '' ? $int('id') : 0;
        $life = $id !== 0 ? $int('life') : 0;
        // A life that's given but isn't a whole number names no kept delete row, so it shows nothing (-1), never the
        // current item
        if ($id !== 0 && $life === 0 && array_key_exists('life', $get)) $life = -1;
        $source = in_array($text('source'), ChangeHistory::SOURCES, true) ? $text('source') : '';
        $field = preg_match('/^(cf:)?[a-z_]{1,60}$/i', $text('field')) ? $text('field') : '';
        $range = in_array($text('range'), self::RANGES, true) ? $text('range') : self::DEFAULT_RANGE;
        // A custom range is two valid dates, the first not after the second. Dates that were given but don't make one
        // fall back to the default range, and range_invalid says so (the page shows a message).
        $daterange = $range === 'custom' ? $text('daterange') : '';
        $range_invalid = false;
        if ($daterange !== '' && !self::validDates($daterange)) {
            $range = self::DEFAULT_RANGE;
            $daterange = '';
            $range_invalid = true;
        }
        $name = mb_substr($text('name'), 0, self::MAX_SEARCH_LENGTH, 'UTF-8');

        return array('type' => $type, 'id' => $id, 'life' => $life, 'name' => $name, 'source' => $source,
            'user' => $text('user') === 'system' ? -1 : $int('user'), 'field' => $field, 'op' => $int('op'), 'range' => $range,
            'daterange' => $daterange, 'range_invalid' => $range_invalid);
    }

    // Whether a custom range is 'YYYY-MM-DD - YYYY-MM-DD', with real dates and the first not after the second
    public static function validDates($daterange)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) - (\d{4})-(\d{2})-(\d{2})$/', (string) $daterange, $m)) return false;
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || !checkdate((int) $m[5], (int) $m[6], (int) $m[4])) return false;
        // The day after the last date has to be a date too (it's the range's exclusive end)
        if ((int) $m[4] > 9998) return false;
        return substr($daterange, 0, 10) <= substr($daterange, -10);
    }

    /*
     * The UTC times a range covers, as array(from, to), either of which may be '' (no limit): a preset counts whole days
     * in the viewer's time zone ($zone), so "Today" in Vancouver means Vancouver's day. $start_date and $end_date are a
     * custom range's checked dates (Y-m-d).
     */
    public static function rangeTimes($range, $zone, $start_date = '', $end_date = '', $now = 'now')
    {
        if ($range === 'all') return array('', '');
        $tz = Time::zone($zone);

        // Each boundary is the start of a calendar day in the viewer's zone, worked out from the date itself, so a day
        // whose midnight a clock change skips starts at its first real moment and no hour is carried into another day
        $day_start = function ($ymd) use ($tz) {
            $date = new \DateTime($ymd . ' 00:00:00', $tz);
            $date->setTimezone(new \DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        };
        $add_days = function ($ymd, $days) {
            $date = new \DateTime($ymd . ' 12:00:00', new \DateTimeZone('UTC'));
            $date->modify(($days < 0 ? '-' : '+') . abs((int) $days) . ' days');
            return $date->format('Y-m-d');
        };

        if ($range === 'custom') {
            if ($start_date === '' || $end_date === '') return array('', '');
            return array($day_start($start_date), $day_start($add_days($end_date, 1)));
        }

        $today = new \DateTime($now, $tz);
        $today->setTimezone($tz);
        $days = $range === 'today' ? 0 : (int) $range - 1;
        // Up to the end of today, as a custom range ends, so a row dated later (a clock set ahead) isn't counted as today's
        return array($day_start($add_days($today->format('Y-m-d'), -$days)), $day_start($add_days($today->format('Y-m-d'), 1)));
    }

    /*
     * The filters as SQL and bound values, array('sql' => ..., 'params' => ..., 'life_ok' => whether a given life names a
     * kept delete row for the item). An item picked by id applies the lifetime rule (see the class comment).
     */
    public function filterParts(array $state, $from_time, $to_time)
    {
        $sql = '';
        $params = array();
        $life_ok = true;

        if ($state['type'] !== '') {
            $sql .= " AND ch.item_type = :f_type";
            $params['f_type'] = $state['type'];
        }
        if ($state['id'] !== 0) {
            $sql .= " AND ch.item_id = :f_id";
            $params['f_id'] = $state['id'];
            if ($state['life'] !== 0) {
                $stmt = $this->pdo->prepare("
                    SELECT count(*)
                    FROM change_history
                    WHERE id = :life
                      AND item_type = :item_type
                      AND item_id = :item_id
                      AND change_type = 'delete'");
                $stmt->bindValue('life', max(0, $state['life']), \PDO::PARAM_INT);
                $stmt->bindValue('item_type', $state['type'], \PDO::PARAM_STR);
                $stmt->bindValue('item_id', $state['id'], \PDO::PARAM_INT);
                $stmt->execute();
                $life_ok = (int) $stmt->fetchColumn() > 0;
                $stmt->closeCursor();
                if (!$life_ok) {
                    $sql .= " AND 1 = 0";
                } else {
                    $sql .= " AND ch.id <= :f_life
                      AND ch.id > COALESCE((SELECT MAX(d.id) FROM change_history AS d
                                            WHERE d.item_type = :f_life_type AND d.item_id = :f_life_id
                                              AND d.change_type = 'delete' AND d.id < :f_life_before), 0)";
                    $params += array('f_life' => $state['life'], 'f_life_type' => $state['type'], 'f_life_id' => $state['id'],
                        'f_life_before' => $state['life']);
                }
            } else {
                $sql .= " AND ch.id > COALESCE((SELECT MAX(d.id) FROM change_history AS d
                                                WHERE d.item_type = :f_cur_type AND d.item_id = :f_cur_id
                                                  AND d.change_type = 'delete'), 0)";
                $params += array('f_cur_type' => $state['type'], 'f_cur_id' => $state['id']);
            }
        }
        if ($state['name'] !== '') {
            // As typed (names are stored HTML-encoded), or as its stored IDN form, as the Domains keyword filter does
            $sql .= " AND (ch.item_name LIKE :f_name OR ch.item_name LIKE :f_name_idn)";
            $params['f_name'] = '%' . self::likeValue((new Sanitize())->text($state['name'])) . '%';
            $idn = Domain::normalize($state['name']);
            $params['f_name_idn'] = '%' . self::likeValue($idn !== '' ? $idn : (new Sanitize())->text($state['name'])) . '%';
        }
        if ($state['source'] !== '') {
            $sql .= " AND ch.source = :f_source";
            $params['f_source'] = $state['source'];
        }
        if ($state['user'] !== 0) {
            // -1 is DomainMOD itself (the system's changes, user id 0)
            $sql .= " AND ch.user_id = :f_user";
            $params['f_user'] = max(0, $state['user']);
        }
        if ($state['field'] !== '') {
            $sql .= " AND ch.field = :f_field";
            $params['f_field'] = $state['field'];
        }
        if ($state['op'] !== 0) {
            $sql .= " AND ch.operation_id = :f_op";
            $params['f_op'] = $state['op'];
        }
        if ($from_time !== '') {
            $sql .= " AND ch.changed_time >= :f_from";
            $params['f_from'] = $from_time;
        }
        if ($to_time !== '') {
            $sql .= " AND ch.changed_time < :f_to";
            $params['f_to'] = $to_time;
        }

        return array('sql' => $sql, 'params' => $params, 'life_ok' => $life_ok);
    }

    /*
     * The action line's counts for each operation (operation id => array(changes, items)), from the rows still kept, so
     * an action part-pruned near the retention cutoff never shows a mismatched pair
     */
    public function operationCounts(array $operation_ids)
    {
        $operation_ids = array_values(array_unique(array_filter(array_map('intval', $operation_ids))));
        if (!$operation_ids) return array();

        $counts = array();
        $stmt = $this->pdo->query("
            SELECT operation_id, count(*) AS changes, count(DISTINCT item_type, item_id) AS items
            FROM change_history
            WHERE operation_id IN (" . implode(',', $operation_ids) . ")
            GROUP BY operation_id");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(int) $row['operation_id']] = array((int) $row['changes'], (int) $row['items']);
        }
        $stmt->closeCursor();
        return $counts;
    }

    /*
     * The export's rows in the list's order (newest action first, one action's rows in the order they were recorded), a
     * chunk at a time, as a generator of chunks, each a generator of rows, so PHP holds only a chunk at once. The keys are
     * read in the changed_time index's own order (newest first by time and id, keyset, $scan_size at a time). Every row of
     * one action shares its time, so once a second's keys are all read, its actions go out newest first: a small action's
     * ids are kept from the keys and its rows read by id with the others', $keys_per_chunk at a time, and a large one's
     * rows are read straight from the table in id order from its first id (keyset), so neither query sorts more than it
     * returns and PHP never holds a large action's ids. It never starts or ends a transaction: the export runs it inside
     * beginSnapshot() and endSnapshot().
     */
    public function exportChunks($filter_sql, array $params, $keys_per_chunk = null, $scan_size = null, $keep = null)
    {
        $limit = max(1, (int) ($keys_per_chunk ?? self::EXPORT_CHUNK));
        $scan = max(1, (int) ($scan_size ?? self::EXPORT_SCAN));
        $keep = max(1, (int) ($keep ?? self::EXPORT_KEEP));
        $last = null;
        $window_time = null;
        // The second being read: operation id => array(first id, ids while there are at most $keep of them or null, last id)
        $window = array();
        // Ids already in the list's order, waiting to make up a full chunk
        $pending = array();
        while (true) {
            $stmt = $this->pdo->prepare("SELECT ch.id, ch.operation_id, ch.changed_time" . $this->fromSql() . $filter_sql
                . ($last !== null ? " AND (ch.changed_time < :ks_time OR (ch.changed_time = :ks_time_same AND ch.id < :ks_id))" : '')
                . " ORDER BY ch.changed_time DESC, ch.id DESC LIMIT " . $scan);
            $stmt->execute($params + ($last !== null ? array('ks_time' => $last[0], 'ks_time_same' => $last[0], 'ks_id' => $last[1]) : array()));
            $keys = $stmt->fetchAll(\PDO::FETCH_NUM);
            $stmt->closeCursor();
            foreach ($keys as $key) {
                // A new second: the one before is complete, so its rows go out
                if ($window_time !== null && (string) $key[2] !== $window_time) {
                    yield from $this->exportWindow($window, $pending, $limit, $filter_sql, $params);
                    $window = array();
                }
                $window_time = (string) $key[2];
                $operation_id = (int) $key[1];
                $id = (int) $key[0];
                // Read newest id first, so the first id seen is the action's last, and the last seen its first
                if (!isset($window[$operation_id])) $window[$operation_id] = array($id, array(), $id);
                $window[$operation_id][0] = $id;
                if ($window[$operation_id][1] !== null) {
                    $window[$operation_id][1][] = $id;
                    if (count($window[$operation_id][1]) > $keep) $window[$operation_id][1] = null;
                }
            }
            if (count($keys) < $scan) break;
            $end = end($keys);
            $last = array((string) $end[2], (int) $end[0]);
        }
        yield from $this->exportWindow($window, $pending, $limit, $filter_sql, $params);
        foreach (array_chunk($pending, $limit) as $ids) yield $this->exportRows($ids);
        $pending = array();
    }

    // One second's actions, newest first (see exportChunks()): full chunks of the small ones' ids, and the large ones read
    // in id order, with $pending carrying what's left of a chunk to the next second
    protected function exportWindow(array $window, array &$pending, $limit, $filter_sql, array $params)
    {
        krsort($window);
        foreach ($window as $operation_id => $action) {
            if ($action[1] !== null) {
                $pending = array_merge($pending, array_reverse($action[1]));
                while (count($pending) >= $limit) yield $this->exportRows(array_splice($pending, 0, $limit));
                continue;
            }
            // A large action: what's waiting goes first, then its own rows a chunk at a time
            foreach (array_chunk($pending, $limit) as $ids) yield $this->exportRows($ids);
            $pending = array();
            // Through the primary key, between the action's first and last ids, so each chunk reads only what it returns
            // (another index would sort all of the action's rows for every chunk)
            $from = (int) $action[0];
            $force = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FORCE INDEX (PRIMARY)' : '';
            while (true) {
                $stmt = $this->pdo->prepare($this->selectSql() . "
                    FROM change_history AS ch" . $force . "
                    WHERE 1 = 1" . $filter_sql . " AND ch.operation_id = :ks_operation AND ch.id >= :ks_from AND ch.id <= :ks_to
                    ORDER BY ch.id LIMIT " . $limit);
                $stmt->execute($params + array('ks_operation' => (int) $operation_id, 'ks_from' => $from, 'ks_to' => (int) $action[2]));
                $rows = $stmt->fetchAll();
                $stmt->closeCursor();
                if (!$rows) break;
                $from = (int) end($rows)->id + 1;
                yield (function () use ($rows) {
                    foreach ($rows as $row) yield $row;
                })();
                if (count($rows) < $limit) break;
            }
        }
    }

    // One chunk's rows, read by id and given back in the order of $ids
    protected function exportRows(array $ids)
    {
        $stmt = $this->pdo->query($this->selectSql() . $this->fromSql() . " AND ch.id IN (" . implode(',', array_map('intval', $ids)) . ")");
        $by_id = array();
        foreach ($stmt->fetchAll() as $row) $by_id[(int) $row->id] = $row;
        $stmt->closeCursor();
        return (function () use ($ids, $by_id) {
            foreach ($ids as $id) {
                if (isset($by_id[(int) $id])) yield $by_id[(int) $id];
            }
        })();
    }

    // The newest restore marker (its time and reason), or null
    public function lastRestore()
    {
        $row = $this->pdo->query("
            SELECT insert_time, `action`
            FROM change_operations
            WHERE source = 'restore'
            ORDER BY id DESC
            LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Whether any change at all has been recorded
    public function hasAnyChanges()
    {
        return $this->pdo->query("SELECT id FROM change_history LIMIT 1")->fetchColumn() !== false;
    }

    // An item's newest changes in its current life, for its edit page's Change History section
    public function itemRows($type, $id, $limit = 20)
    {
        // No item (an id that isn't a positive number, or an unknown type) has no changes, rather than everyone's
        if ((int) $id <= 0 || !isset(ChangeHistory::ITEM_TYPES[$type])) return array();
        $state = self::normalizeState(array('type' => $type, 'id' => (string) $id, 'range' => 'all'));
        $filter = $this->filterParts($state, '', '');
        // The newest actions, each action's rows in the order they were recorded (see orderSql())
        $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter['sql']
            . " ORDER BY ch.changed_time DESC, ch.operation_id DESC, ch.id ASC LIMIT " . max(1, (int) $limit));
        $stmt->execute($filter['params']);
        $rows = $stmt->fetchAll();
        $stmt->closeCursor();
        return $rows;
    }

} //@formatter:on
