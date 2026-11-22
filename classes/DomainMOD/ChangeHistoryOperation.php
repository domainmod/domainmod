<?php
/**
 * /classes/DomainMOD/ChangeHistoryOperation.php
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
 * One action in the Change History (from ChangeHistory::start()), recorded inside the caller's transaction:
 *
 *     $before = $op->capture('domain', $ids);   // locks the rows and reads every recorded column and custom field
 *     // ... the page's existing UPDATE statements, unchanged ...
 *     $op->compare($before);                    // reads the rows again and writes one row per field that changed
 *     $op->created('domain', $new_id);          // after an insert (its snapshot is taken at finish())
 *     $op->deleting('ssl', $ssl_ids);           // before a delete: one delete row per item, with a snapshot
 *     $op->record('domain', $id, 'dns_servers', $old, $new, 'rdap');   // an explicit change
 *     $op->finish();                            // just before the commit
 *
 * Its change_operations row is written with its first change row, so an action that changed nothing leaves nothing
 * behind. An action records at most one row per item and field: a second change to the same field updates the row's new
 * side and keeps its old side, and a row whose final value is back to the original is removed. Before the database
 * update that adds the history, every method does nothing.
 */
class ChangeHistoryOperation
{
    protected $pdo;
    protected $enabled;
    protected $source;
    protected $user_id;
    protected $action;
    protected $note;
    protected $note_code;
    protected $time;
    protected $operation_id = 0;
    protected $has_rows = false;
    // Per item type (every type in ChangeHistory::ITEM_TYPES, filled in by the constructor)
    protected $created = array();
    protected $custom_fields = array();
    protected $fee_currencies = array();
    protected $names = array();
    // Named locks the action holds until it's released (see holdNamedLock())
    protected $named_locks = array();
    protected $status_table = null;
    // Temporary cost tables not yet dropped (see captureCosts())
    protected $temp_tables = array();
    // Each capture() gets its own number, and the numbers compare() has seen are kept, so a capture compared a second
    // time also checks its unchanged fields, whose row from the first compare() goes if the value is back where it
    // started. A capture compared once keeps nothing per item.
    protected $capture_seq = 0;
    protected $compared = array();

    public function __construct($pdo, $enabled, $source, $user_id, $action = '', $note = '', $note_code = '')
    {
        $this->pdo = $pdo;
        $this->enabled = (bool) $enabled;
        $this->source = (string) $source;
        $this->user_id = (int) $user_id;
        $this->action = (string) $action;
        $this->note = (string) $note;
        $this->note_code = (string) $note_code;
        // When the action's changes happened: set with its first change row, after its locks, so two actions on one
        // item read in the order their changes were made (see ensureOperation())
        $this->time = '';
        foreach (array_keys(ChangeHistory::ITEM_TYPES) as $type) {
            $this->created[$type] = array();
            $this->fee_currencies[$type] = array();
        }

        // Both item types' custom field definitions are read (shared-locked) first, before any item or field data row
        // is locked or inserted, in the lock order every writer follows, so a custom field delete can't deadlock with it.
        // A custom field delete itself holds only its own definition (see CustomFieldSchema::delete()).
        if ($this->enabled && $this->source !== ChangeHistory::SOURCE_CUSTOM_FIELD) {
            $this->customFields('domain');
            $this->customFields('ssl');
        }
    }

    // Whether this action is recorded (false before the database update that adds the history)
    public function enabled()
    {
        return $this->enabled;
    }

    // The action's change_operations id, or 0 when nothing has been recorded yet
    public function operationId()
    {
        return $this->operation_id;
    }

    /*
     * The currency each fee had before the action, for the before-images captured after this call: fee id => currency
     * code, or '' for "no prior currency" (a fee row the action itself inserted, whose id an old item may still point
     * at). Only the fee pages and the imports that insert fees need it, since everyone else captures before any fee
     * changes.
     */
    public function setFeeCurrencies($type, array $currencies)
    {
        foreach ($currencies as $fee_id => $code) $this->fee_currencies[$type][(int) $fee_id] = (string) $code;
    }

    /*
     * Locks the items (SELECT ... FOR UPDATE, in ascending id order) and reads their recorded values: every column and
     * custom field, or only the columns in $only (and the custom fields in $custom: null for all of them when $only is
     * null and none otherwise, true for all, or a list of field names). Long text is read only up to what a history value
     * can hold, with its full hash and length worked out by the database. Returns the before-images for compare(), in
     * the order of $ids (which is the order compare() writes their rows in, such as the order a list was pasted in).
     */
    public function capture($type, array $ids, $only = null, $custom = null)
    {
        $fields = ChangeHistory::fields($type, $only);
        $custom_fields = $this->pickCustomFields($type, $only, $custom);
        $images = array();

        if ($this->enabled && $ids) {
            foreach ($this->readImages($type, $ids, $fields, $custom_fields, true) as $id => $image) {
                foreach ($image['values'] as $key => $value) {
                    if (($value['kind'] ?? '') === 'cost' && isset($this->fee_currencies[$type][$image['fee_id']])) {
                        $image['values'][$key]['d'] = $this->fee_currencies[$type][$image['fee_id']];
                    }
                }
                $images[$id] = $image;
            }
        }

        // The images in the order the ids were given (the locks above were still taken in ascending id order)
        $ordered = array();
        foreach ($ids as $id) {
            if (isset($images[(int) $id])) $ordered[(int) $id] = $images[(int) $id];
        }
        $images = $ordered + $images;

        return array('type' => $type, 'fields' => $fields, 'custom' => $custom_fields, 'images' => $images,
            'capture_id' => ++$this->capture_seq);
    }

    /*
     * Reads the captured items again (locked, so it sees the latest committed values) and writes one row per field that
     * really changed. $detail is added to each row's codes (such as 'side_effect'), and $via says where looked-up values
     * came from (such as 'rdap' for Refresh). Items created in this action are
     * skipped, since their create row's snapshot holds their final values. The owner and registrar (or SSL provider)
     * are recorded only when they change without the account changing, since otherwise they follow it.
     */
    public function compare(array $before, $detail = '', $via = '')
    {
        if (!$this->enabled || empty($before['images'])) return;

        $type = $before['type'];
        $capture_id = (int) ($before['capture_id'] ?? 0);
        $again = $capture_id > 0 && isset($this->compared[$capture_id]);
        if ($capture_id > 0) $this->compared[$capture_id] = true;
        $ids = array_keys(array_diff_key($before['images'], $this->created[$type]));
        $follows = ChangeHistory::FOLLOWS_ACCOUNT[$type];

        foreach (array_chunk($ids, $this->chunkSize($before['fields'], $before['custom'])) as $chunk) {

            $changes = array();
            $after = $this->readImages($type, $chunk, $before['fields'], $before['custom'], true);

            foreach ($chunk as $id) {

                if (!isset($after[$id])) continue; // deleted in this action: its delete row says so
                $old_image = $before['images'][$id];
                $new_image = $after[$id];
                $account_changed = isset($old_image['values']['account_id'])
                    && !ChangeHistory::same('id', $old_image['values']['account_id'], $new_image['values']['account_id']);

                foreach ($new_image['values'] as $key => $new) {
                    if (!isset($old_image['values'][$key])) continue;
                    if ($account_changed && in_array($key, $follows, true)) continue;
                    $old = $old_image['values'][$key];
                    // Unchanged since the capture. A capture compared again passes it on anyway, so a row the first
                    // compare() wrote for it goes (writeChanges() skips it when there's none).
                    if (!$again && ChangeHistory::same($new['kind'], $old, $new)) continue;
                    $changes[] = array('type' => $type, 'id' => $id, 'name' => $new_image['name'], 'field' => $key,
                        'kind' => $new['kind'], 'old' => $old, 'new' => $new, 'detail' => $detail, 'via' => (string) $via);
                }

            }

            $this->writeChanges($changes);

        }
    }

    /*
     * For set-based cost writes (the fee recalculations, the fee and zero-out pages, and the registrar, SSL provider, and
     * SSL type deletes): copies the cost and fee currency of every item matching $where_sql ("" for every item, or
     * " AND ..." over the item table aliased i, with $params) into a temporary table, inside the transaction (which
     * doesn't end it), reading the items with a locking read so they stay as captured until the commit. The writer's own
     * statements then run unchanged, and compareCosts() walks the table in chunks, so PHP never holds the whole set. A
     * fee whose currency this action changes is given its earlier currency with setFeeCurrencies() before the capture.
     * Where a temporary table can't be created (no permission, or a server that refuses one inside a transaction), the
     * items are captured into memory instead, the cost fields only. Returns what compareCosts() needs.
     */
    public function captureCosts($type, $where_sql = '', array $params = array())
    {
        if (ChangeHistory::ITEM_TYPES[$type]['fee_table'] === '') throw new \LogicException('This item type has no costs');
        if (!$this->enabled) return null;

        // One branch, or several (an array of array(where SQL, bound values)), each read through its own index with direct
        // conditions on the items, since a locking read doesn't lock or freshly read the rows of a subquery
        $branches = is_array($where_sql) ? $where_sql : array(array($where_sql, $params));
        $info = ChangeHistory::ITEM_TYPES[$type];
        $table = 'tmp_change_costs_' . bin2hex(random_bytes(8));

        try {
            $this->pdo->exec("
                CREATE TEMPORARY TABLE `" . $table . "` (
                    `id` INT(10) UNSIGNED NOT NULL,
                    `fee_id` INT(10) UNSIGNED NOT NULL,
                    `total_cost` DECIMAL(10,2) NOT NULL,
                    `currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB");
            $this->temp_tables[$table] = true;
        } catch (\PDOException $e) {
            $ids = array();
            foreach ($branches as $branch) $ids = array_merge($ids, $this->lockIds($info['table'], $branch[0], $branch[1], true));
            return array('memory' => $this->capture($type, array_values(array_unique($ids)), array('total_cost'), false));
        }

        foreach ($branches as $branch) {

            list($branch_sql, $branch_params) = $branch;
            // The items are locked first, on their own, so the fees and currencies joined below only get the shared locks
            // an INSERT ... SELECT takes, never exclusive ones another writer's currency lookup would wait on
            $this->lockIds($info['table'], $branch_sql, $branch_params, false);

            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO `" . $table . "`
                (id, fee_id, total_cost, currency)
                SELECT i.id, i.fee_id, i.total_cost, COALESCE(c.currency, '')
                FROM " . $info['table'] . " AS i
                LEFT JOIN " . $info['fee_table'] . " AS f ON f.id = i.fee_id
                LEFT JOIN currencies AS c ON c.id = f.currency_id
                WHERE 1 = 1" . $branch_sql . "
                ORDER BY i.id");
            $stmt->execute($branch_params);

        }

        foreach ($this->fee_currencies[$type] as $fee_id => $code) {
            $stmt = $this->pdo->prepare("UPDATE `" . $table . "` SET currency = :currency WHERE fee_id = :fee_id");
            $stmt->bindValue('currency', $code, \PDO::PARAM_STR);
            $stmt->bindValue('fee_id', (int) $fee_id, \PDO::PARAM_INT);
            $stmt->execute();
        }

        return array('type' => $type, 'table' => $table);
    }

    // Locks the items matching a branch in keyset chunks of MAX_CHUNK by id, returning their ids when $keep is true
    protected function lockIds($table, $where_sql, array $params, $keep)
    {
        $ids = array();
        $last = 0;
        do {
            $stmt = $this->pdo->prepare("SELECT i.id FROM " . $table . " AS i WHERE i.id > :lock_last" . $where_sql
                . " ORDER BY i.id LIMIT " . ChangeHistory::MAX_CHUNK . " FOR UPDATE");
            $stmt->execute($params + array('lock_last' => $last));
            $locked = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            $stmt->closeCursor();
            if ($locked) $last = end($locked);
            if ($keep) $ids = array_merge($ids, $locked);
        } while (count($locked) === ChangeHistory::MAX_CHUNK);
        return $ids;
    }

    /*
     * Compares the costs captured by captureCosts() with the items as they are now, a chunk of ids at a time, and writes a
     * total_cost row for each item whose amount or currency changed, then drops the temporary table
     */
    public function compareCosts($capture, $detail = '')
    {
        if (isset($capture['type']) && ChangeHistory::ITEM_TYPES[$capture['type']]['fee_table'] === '') {
            throw new \LogicException('This item type has no costs');
        }
        if (!$this->enabled || $capture === null) return;
        if (isset($capture['memory'])) {
            $this->compare($capture['memory'], $detail);
            return;
        }

        $type = $capture['type'];
        $info = ChangeHistory::ITEM_TYPES[$type];
        $table = $capture['table'];
        $last = 0;

        try {
            while (true) {
                $stmt = $this->pdo->prepare("
                    SELECT t.id, t.total_cost AS old_cost, t.currency AS old_currency, i.total_cost AS new_cost,
                           COALESCE(c.currency, '') AS new_currency, i.`" . $info['name'] . "` AS item_name
                    FROM `" . $table . "` AS t
                    JOIN " . $info['table'] . " AS i ON i.id = t.id
                    LEFT JOIN " . $info['fee_table'] . " AS f ON f.id = i.fee_id
                    LEFT JOIN currencies AS c ON c.id = f.currency_id
                    WHERE t.id > :last
                    ORDER BY t.id
                    LIMIT " . ChangeHistory::MAX_CHUNK . "
                    LOCK IN SHARE MODE");
                $stmt->bindValue('last', $last, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                if (!$rows) break;

                $changes = array();
                foreach ($rows as $row) {
                    $last = (int) $row['id'];
                    if (isset($this->created[$type][$last])) continue;
                    $old = array('kind' => 'cost', 'v' => ChangeHistory::normalize('cost', $row['old_cost']), 'd' => (string) $row['old_currency']);
                    $new = array('kind' => 'cost', 'v' => ChangeHistory::normalize('cost', $row['new_cost']), 'd' => (string) $row['new_currency']);
                    if (ChangeHistory::same('cost', $old, $new)) continue;
                    $changes[] = array('type' => $type, 'id' => $last, 'name' => (string) $row['item_name'], 'field' => 'total_cost',
                        'kind' => 'cost', 'old' => $old, 'new' => $new, 'detail' => $detail, 'via' => '');
                }
                $this->writeChanges($changes);
                if (count($rows) < ChangeHistory::MAX_CHUNK) break;
            }
        } finally {
            $this->dropTempTable($table);
        }
    }

    // Notes an item inserted in this action, whose create row (with a snapshot of its final values) is written at finish()
    public function created($type, $id)
    {
        if ($this->enabled && (int) $id > 0) $this->created[$type][(int) $id] = true;
    }

    /*
     * Writes one delete row per item, with a snapshot of every recorded value, read under the items' locks before the
     * caller deletes them, a chunk at a time, keeping nothing
     */
    public function deleting($type, array $ids, $locked = false)
    {
        if (!$this->enabled || !$ids) return;

        $fields = ChangeHistory::fields($type);
        $custom_fields = $this->customFields($type);
        // The snapshots are read in ascending id order, so their locks are taken in that order, unless the caller holds
        // every item's lock already ($locked), when the rows keep the caller's order (such as the order a list was pasted)
        if (!$locked) sort($ids);

        foreach (array_chunk($ids, $this->chunkSize($fields, $custom_fields)) as $chunk) {
            $rows = array();
            $snapshots = $this->snapshots($type, $chunk, $fields, $custom_fields);
            // In the chunk's own order (the snapshots come back by id)
            foreach ($chunk as $id) {
                if (!isset($snapshots[(int) $id])) continue;
                $snapshot = $snapshots[(int) $id];
                $rows[] = $this->row($type, (int) $id, $snapshot['name'], 'delete', '', '', '', '', '', '', 0, $snapshot['json'], '', '');
                unset($this->created[$type][(int) $id]);
            }
            $this->insertRows($rows);
        }
    }

    /*
     * Records one explicit change, for fields that aren't columns (such as Refresh's DNS servers, or SSL Refresh's issuer)
     * or that follow their own rules. $via is where a looked-up value came from (registrar, rdap, whois, dns, or tls).
     */
    public function record($type, $id, $field, $old, $new, $via = '', $old_display = '', $new_display = '', $detail = '')
    {
        if (!$this->enabled) return;
        $old = (string) ($old ?? '');
        $new = (string) ($new ?? '');
        if ($old === $new && (string) $old_display === (string) $new_display) return;

        $this->writeChanges(array(array('type' => $type, 'id' => (int) $id, 'name' => $this->itemName($type, (int) $id),
            'field' => (string) $field, 'kind' => 'text', 'old' => array('v' => $old, 'd' => (string) $old_display),
            'new' => array('v' => $new, 'd' => (string) $new_display), 'detail' => (string) $detail, 'via' => (string) $via)));
    }

    /*
     * Records custom field values lost when the field is deleted, each as a cf:<field_name> change from its value to ''
     * with the field's name at the time: $values maps item id => array(item name, value, whether the value was cut to
     * fit). $checkbox marks the field as a check box, so its values show as Yes or No.
     */
    public function recordFieldLoss($type, $field_name, $field_label, array $values, $checkbox = false)
    {
        if (!$this->enabled || !$values) return;
        $changes = array();
        foreach ($values as $id => $value) {
            list($name, $old, $cut) = $value;
            $changes[] = array('type' => $type, 'id' => (int) $id, 'name' => (string) $name, 'field' => 'cf:' . $field_name,
                'kind' => 'text', 'old' => array('v' => (string) $old, 'd' => '', 'name' => (string) $field_label, 'cut' => (bool) $cut)
                    + ($checkbox ? array('cb' => 1) : array()),
                'new' => array('v' => '', 'd' => ''), 'detail' => '', 'via' => '');
        }
        $this->writeChanges($changes);
    }

    /*
     * Ends the action, just before the caller commits: the create rows' snapshots are read from the rows as they'll be
     * committed, the hashes kept only for merging changes within the action are cleared, an operation left with no rows
     * is removed, and item_count is set
     */
    public function finish()
    {
        if (!$this->enabled) return;

        foreach ($this->created as $type => $created) {
            if (!$created) continue;
            $ids = array_keys($created);
            sort($ids);
            $fields = ChangeHistory::fields($type);
            $custom_fields = $this->customFields($type);
            foreach (array_chunk($ids, $this->chunkSize($fields, $custom_fields)) as $chunk) {
                $rows = array();
                foreach ($this->snapshots($type, $chunk, $fields, $custom_fields) as $id => $snapshot) {
                    $rows[] = $this->row($type, $id, $snapshot['name'], 'create', '', '', '', '', '', '', 0, $snapshot['json'], '', '');
                }
                $this->insertRows($rows);
            }
            $this->created[$type] = array();
        }

        if ($this->operation_id === 0) return;

        $stmt = $this->pdo->prepare("
            UPDATE change_history
            SET old_hash = '', old_length = 0
            WHERE operation_id = :operation_id
              AND old_hash <> ''");
        $stmt->bindValue('operation_id', $this->operation_id, \PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $this->pdo->prepare("
            SELECT count(*)
            FROM (SELECT DISTINCT item_type, item_id
                  FROM change_history
                  WHERE operation_id = :operation_id) AS items");
        $stmt->bindValue('operation_id', $this->operation_id, \PDO::PARAM_INT);
        $stmt->execute();
        $item_count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($item_count === 0) {
            $stmt = $this->pdo->prepare("DELETE FROM change_operations WHERE id = :id");
        } else {
            $stmt = $this->pdo->prepare("UPDATE change_operations SET item_count = :item_count WHERE id = :id");
            $stmt->bindValue('item_count', $item_count, \PDO::PARAM_INT);
        }
        $stmt->bindValue('id', $this->operation_id, \PDO::PARAM_INT);
        $stmt->execute();
        $this->operation_id = 0;
        $this->has_rows = false;
        $this->compared = array();
    }

    /*
     * Takes a named lock (GET_LOCK()) for the action, waiting up to $wait seconds, and throws when it can't be had. It's
     * let go of when the action object goes (see __destruct()), which every caller lets happen after its commit or
     * rollback, so another writer never gets in between the action's changes and its commit, and the lock never outlives
     * the action on a connection that goes on to other work (a caller whose action ends before its own commit, such as
     * the Domain Queue's import, holds the lock itself until then).
     */
    public function holdNamedLock($name, $wait = 10)
    {
        $stmt = $this->pdo->prepare("SELECT GET_LOCK(:lock_name, :wait)");
        $stmt->bindValue('lock_name', $name, \PDO::PARAM_STR);
        $stmt->bindValue('wait', (int) $wait, \PDO::PARAM_INT);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        if (!$got) throw new RestoreLockException(RestoreLock::message(false));
        $this->named_locks[] = $name;
    }

    // An action lets go of its named locks when it goes (after its caller's commit or rollback), and drops any temporary
    // tables left by one that ended without finish() (an error part-way), so they never outlive it on a connection that
    // goes on to other work
    public function __destruct()
    {
        $this->releaseNamedLocks();
        foreach (array_keys($this->temp_tables) as $table) $this->dropTempTable($table);
    }

    // Drops a temporary cost table (a failure is ignored: it goes when the connection closes)
    protected function dropTempTable($table)
    {
        unset($this->temp_tables[$table]);
        try {
            $this->pdo->exec("DROP TEMPORARY TABLE IF EXISTS `" . $table . "`");
        } catch (\Throwable $e) {
        }
    }

    protected function releaseNamedLocks()
    {
        foreach ($this->named_locks as $name) {
            try {
                $stmt = $this->pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
                $stmt->bindValue('lock_name', $name, \PDO::PARAM_STR);
                $stmt->execute();
                $stmt->closeCursor();
            } catch (\Throwable $e) {
                // It goes with the connection
            }
        }
        $this->named_locks = array();
    }

    // The custom fields of an item type, read once per action, shared-locked so a field can't be deleted under it (an
    // item type without custom fields, such as a watched domain, has none, read without a query)
    protected function customFields($type)
    {
        if (isset($this->custom_fields[$type])) return $this->custom_fields[$type];
        if (ChangeHistory::ITEM_TYPES[$type]['fields'] === '') return $this->custom_fields[$type] = array();

        $stmt = $this->pdo->query("
            SELECT id, `name`, field_name, type_id
            FROM " . ChangeHistory::ITEM_TYPES[$type]['fields'] . "
            ORDER BY id
            LOCK IN SHARE MODE");
        $fields = array();
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!preg_match('/^[a-zA-Z_]+$/', (string) $row['field_name'])) continue;
            $fields[$row['field_name']] = array('name' => (string) $row['name'],
                'kind' => ChangeHistory::CUSTOM_FIELD_KINDS[(int) $row['type_id']] ?? 'text',
                'checkbox' => (int) $row['type_id'] === ChangeHistory::CUSTOM_FIELD_CHECKBOX);
        }
        $stmt->closeCursor();

        return $this->custom_fields[$type] = $fields;
    }

    // The custom fields a capture reads (see capture())
    protected function pickCustomFields($type, $only, $custom)
    {
        if (!$this->enabled) return array();
        if ($custom === null) $custom = $only === null;
        if ($custom === false || $custom === array()) return array();
        $fields = $this->customFields($type);
        if ($custom === true) return $fields;
        return array_intersect_key($fields, array_flip((array) $custom));
    }

    /*
     * How many items one query reads, from the most each row can bring: about 192 KB for each long field, and a little
     * for the short ones, so a chunk's values stay within CAPTURE_BUDGET. Between 1 and MAX_CHUNK.
     */
    public static function chunkSizeFor($long_count, $short_count)
    {
        $per_row = 512 + 512 * $short_count + ChangeHistory::LONG_FIELD_BYTES * $long_count;
        return max(1, min(ChangeHistory::MAX_CHUNK, (int) floor(ChangeHistory::CAPTURE_BUDGET / $per_row)));
    }

    protected function chunkSize(array $fields, array $custom_fields)
    {
        $long = 0;
        $short = 0;
        foreach ($fields as $kind) ($kind === 'long') ? $long++ : $short++;
        foreach ($custom_fields as $field) ($field['kind'] === 'long') ? $long++ : $short++;
        return self::chunkSizeFor($long, $short);
    }

    /*
     * Reads the items' images, keyed by id: array('name', 'fee_id', 'values' => key => array('kind', 'v', 'd', and for
     * long text 'h', 'l', and 'cut')), with $facts adding the insert-only facts for a snapshot. A single item, or each
     * item when the long fields leave room for only one per query, reads its long fields one per query, so the database
     * never sends more than one long value at a time.
     */
    protected function readImages($type, array $ids, array $fields, array $custom_fields, $lock, $facts = false)
    {
        $info = ChangeHistory::ITEM_TYPES[$type];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $chunk_size = $this->chunkSize($fields, $custom_fields);
        $single = count($ids) === 1 || $chunk_size === 1;
        $images = array();

        foreach (array_chunk($ids, $chunk_size) as $chunk) {

            // Each value's alias is numbered (v0, v1, ...), and a long value's hash and lengths add a letter (v0h), so no
            // field's name can ever produce another field's alias
            // An item type without fees (a watched domain) has no fee_id
            $columns = array('i.id AS item_id', 'i.`' . $info['name'] . '` AS item_name',
                $info['fee_table'] !== '' ? 'i.fee_id AS item_fee_id' : '0 AS item_fee_id');
            $aliases = array();
            $long = array();
            foreach ($fields as $field => $kind) {
                $aliases[$field] = 'v' . count($aliases);
                $columns[] = $this->columnSql('i.`' . $field . '`', $aliases[$field], $kind, $single);
                if ($kind === 'long') $long[$field] = 'i.`' . $field . '`';
            }
            foreach ($custom_fields as $field_name => $field) {
                $aliases['cf:' . $field_name] = 'v' . count($aliases);
                $columns[] = $this->columnSql('fd.`' . $field_name . '`', $aliases['cf:' . $field_name], $field['kind'], $single);
                if ($field['kind'] === 'long') $long['cf:' . $field_name] = 'fd.`' . $field_name . '`';
            }
            if ($facts) {
                foreach (ChangeHistory::SNAPSHOT_FACTS[$type] as $fact) $columns[] = 'i.`' . $fact . '` AS x_' . $fact;
            }

            $join = $custom_fields ? " LEFT JOIN " . $info['field_data'] . " AS fd ON fd." . $info['field_id'] . " = i.id" : '';
            $stmt = $this->pdo->prepare("
                SELECT " . implode(', ', $columns) . "
                FROM " . $info['table'] . " AS i" . $join . "
                WHERE i.id IN (" . implode(',', $chunk) . ")
                ORDER BY i.id" . ($custom_fields ? ", fd.id" : '') . ($lock ? "
                FOR UPDATE" : ''));
            $stmt->execute();

            $fee_ids = array();
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $id = (int) $row['item_id'];
                if (isset($images[$id])) continue; // a damaged database's second custom field data row
                $image = array('name' => (string) $row['item_name'], 'fee_id' => (int) $row['item_fee_id'], 'values' => array(),
                    'facts' => array());
                foreach ($fields as $field => $kind) $image['values'][$field] = $this->value($kind, $row, $aliases[$field]);
                foreach ($custom_fields as $field_name => $field) {
                    // A check box is marked as one, as it is now, so its history shows Yes or No even if the field's name
                    // is later reused for another kind of field
                    $image['values']['cf:' . $field_name] = $this->value($field['kind'], $row, $aliases['cf:' . $field_name])
                        + array('name' => $field['name']) + (!empty($field['checkbox']) ? array('cb' => 1) : array());
                }
                if ($facts) {
                    foreach (ChangeHistory::SNAPSHOT_FACTS[$type] as $fact) $image['facts'][$fact] = (string) $row['x_' . $fact];
                }
                $fee_ids[$image['fee_id']] = true;
                $images[$id] = $image;
            }
            $stmt->closeCursor();

            // A single item's long values, one per query
            if ($single && $long && isset($images[$chunk[0]])) {
                foreach ($long as $key => $column) {
                    $stmt = $this->pdo->prepare("
                        SELECT SUBSTRING(" . $column . ", 1, " . ChangeHistory::MAX_VALUE_BYTES . ")
                        FROM " . $info['table'] . " AS i" . $join . "
                        WHERE i.id = :id
                        ORDER BY " . ($custom_fields ? "fd.id" : "i.id") . "
                        LIMIT 1" . ($lock ? "
                        FOR UPDATE" : ''));
                    $stmt->bindValue('id', $chunk[0], \PDO::PARAM_INT);
                    $stmt->execute();
                    $prefix = $stmt->fetchColumn();
                    $stmt->closeCursor();
                    $value = &$images[$chunk[0]]['values'][$key];
                    list($value['v'], $cut) = ChangeHistory::cut((string) $prefix, ChangeHistory::MAX_VALUE_BYTES);
                    $value['cut'] = $cut || (int) $value['b'] > strlen($value['v']);
                    unset($value);
                }
            }

            // Each cost's currency: its fee's, read with a locking read so it's the latest committed
            if (isset($fields['total_cost'])) {
                $currencies = $this->feeCurrencies($type, array_keys($fee_ids));
                foreach ($chunk as $id) {
                    if (!isset($images[$id])) continue;
                    $images[$id]['values']['total_cost']['d'] = $currencies[$images[$id]['fee_id']] ?? '';
                }
            }

        }

        return $images;
    }

    // One column of an image's query: long text as its kept start (except for a single item), hash, and lengths
    protected function columnSql($column, $alias, $kind, $single)
    {
        if ($kind !== 'long') return $column . ' AS ' . $alias;
        return ($single ? "'' AS " . $alias : 'SUBSTRING(' . $column . ', 1, ' . ChangeHistory::MAX_VALUE_BYTES . ') AS ' . $alias)
            . ', SHA2(COALESCE(' . $column . ", ''), 256) AS " . $alias . 'h'
            . ', CHAR_LENGTH(COALESCE(' . $column . ", '')) AS " . $alias . 'l'
            . ', LENGTH(COALESCE(' . $column . ", '')) AS " . $alias . 'b';
    }

    // One value of an image from its query row
    protected function value($kind, array $row, $alias)
    {
        if ($kind !== 'long') return array('kind' => $kind, 'v' => ChangeHistory::normalize($kind, $row[$alias] ?? ''), 'd' => '');

        list($text, $cut) = ChangeHistory::cut((string) ($row[$alias] ?? ''), ChangeHistory::MAX_VALUE_BYTES);
        return array('kind' => 'long', 'v' => $text, 'd' => '', 'h' => (string) $row[$alias . 'h'],
            'l' => (int) $row[$alias . 'l'], 'b' => (int) $row[$alias . 'b'],
            'cut' => $cut || (int) $row[$alias . 'b'] > strlen($text));
    }

    // The currency code of each fee (fee id => code), by a locking read of the fees and currencies
    protected function feeCurrencies($type, array $fee_ids)
    {
        $fee_ids = array_values(array_filter(array_map('intval', $fee_ids)));
        if (!$fee_ids) return array();

        $currencies = array();
        foreach (array_chunk($fee_ids, ChangeHistory::MAX_CHUNK) as $chunk) {
            $stmt = $this->pdo->query("
                SELECT f.id, c.currency
                FROM " . ChangeHistory::ITEM_TYPES[$type]['fee_table'] . " AS f
                JOIN currencies AS c ON c.id = f.currency_id
                WHERE f.id IN (" . implode(',', $chunk) . ")
                LOCK IN SHARE MODE");
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $currencies[(int) $row[0]] = (string) $row[1];
            $stmt->closeCursor();
        }
        return $currencies;
    }

    /*
     * The name of each referenced asset for an id field, at the time (so a later rename or delete doesn't change what
     * the history says). An id that names nothing (such as an SSL certificate's domain 0) has
     * no name, and the page shows "None" for a certificate's domain.
     */
    protected function displayNames($type, $field, array $ids)
    {
        $sql = array(
            'domain' => array(
                'account_id' => "SELECT ra.id, CONCAT(r.name, ', ', o.name, ' (', ra.username, ')') FROM registrar_accounts AS ra JOIN registrars AS r ON r.id = ra.registrar_id JOIN owners AS o ON o.id = ra.owner_id WHERE ra.id IN (%s)",
                'owner_id' => "SELECT id, `name` FROM owners WHERE id IN (%s)",
                'registrar_id' => "SELECT id, `name` FROM registrars WHERE id IN (%s)",
                'cat_id' => "SELECT id, `name` FROM categories WHERE id IN (%s)",
                'dns_id' => "SELECT id, `name` FROM dns WHERE id IN (%s)",
                'ip_id' => "SELECT id, CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id IN (%s)",
                'hosting_id' => "SELECT id, `name` FROM hosting WHERE id IN (%s)"),
            'ssl' => array(
                'domain_id' => "SELECT id, domain FROM domains WHERE id IN (%s)",
                'account_id' => "SELECT sa.id, CONCAT(p.name, ', ', o.name, ' (', sa.username, ')') FROM ssl_accounts AS sa JOIN ssl_providers AS p ON p.id = sa.ssl_provider_id JOIN owners AS o ON o.id = sa.owner_id WHERE sa.id IN (%s)",
                'owner_id' => "SELECT id, `name` FROM owners WHERE id IN (%s)",
                'ssl_provider_id' => "SELECT id, `name` FROM ssl_providers WHERE id IN (%s)",
                'type_id' => "SELECT id, `type` FROM ssl_cert_types WHERE id IN (%s)",
                'ip_id' => "SELECT id, CONCAT(`name`, ' (', ip, ')') FROM ip_addresses WHERE id IN (%s)",
                'cat_id' => "SELECT id, `name` FROM categories WHERE id IN (%s)"));

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !isset($sql[$type][$field])) return array();

        $names = array();
        foreach (array_chunk($ids, ChangeHistory::MAX_CHUNK) as $chunk) {
            // Read without a lock: the names are only for display, and a lock here would come after the item's own (a
            // certificate's domain, or an account the SSL Queue locks before its certificates), against the lock order
            $stmt = $this->pdo->query(sprintf($sql[$type][$field], implode(',', $chunk)));
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) {
                $names[(int) $row[0]] = mb_substr((string) $row[1], 0, 255, 'UTF-8');
            }
            $stmt->closeCursor();
        }
        return $names;
    }

    // An item's name for an explicit change (record()), read once per item
    protected function itemName($type, $id)
    {
        if (isset($this->names[$type][$id])) return $this->names[$type][$id];
        $info = ChangeHistory::ITEM_TYPES[$type];
        $stmt = $this->pdo->prepare("SELECT `" . $info['name'] . "` FROM " . $info['table'] . " WHERE id = :id");
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $name = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $this->names[$type][$id] = (string) ($name === false ? '' : $name);
    }

    // Whether the SSL Queue's status table exists (checked once per action)
    protected function hasStatusTable()
    {
        if ($this->status_table === null) {
            $stmt = $this->pdo->query("
                SELECT count(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'ssl_cert_status'");
            $this->status_table = (int) $stmt->fetchColumn() > 0;
            $stmt->closeCursor();
        }
        return $this->status_table;
    }

    // The items' snapshots (create and delete rows), keyed by id: array('name', 'json')
    protected function snapshots($type, array $ids, array $fields, array $custom_fields)
    {
        $images = $this->readImages($type, $ids, $fields, $custom_fields, true, true);
        if (!$images) return array();

        // The asset names for every id field, a query per field for the whole chunk
        $display = array();
        foreach ($fields as $field => $kind) {
            if ($kind !== 'id') continue;
            $display[$field] = $this->displayNames($type, $field, array_map(function ($image) use ($field) {
                return $image['values'][$field]['v'];
            }, $images));
        }

        // An SSL certificate's issuer, from its latest lookup
        // (before the database update that adds the SSL Queue there's no status table, and so no issuer)
        $issuers = array();
        if ($type === 'ssl' && $this->hasStatusTable()) {
            $stmt = $this->pdo->query("
                SELECT ssl_id, refreshed_issuer
                FROM ssl_cert_status
                WHERE ssl_id IN (" . implode(',', array_keys($images)) . ")
                LOCK IN SHARE MODE");
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $issuers[(int) $row[0]] = (string) $row[1];
            $stmt->closeCursor();
        }

        $snapshots = array();
        foreach ($images as $id => $image) {
            $values = array();
            foreach ($image['values'] as $key => $value) {
                $entry = array('v' => $value['v'], 'd' => $value['d'], 'long' => $value['kind'] === 'long',
                    'cut' => !empty($value['cut']));
                if (isset($display[$key])) $entry['d'] = $display[$key][(int) $value['v']] ?? '';
                if (isset($value['name'])) $entry['name'] = $value['name'];
                if (!empty($value['cb'])) $entry['cb'] = 1;
                $values[$key] = $entry;
            }
            foreach ($image['facts'] as $fact => $fact_value) $values['fact:' . $fact] = array('v' => $fact_value, 'd' => '');
            if ($type === 'ssl') $values['issuer'] = array('v' => $issuers[$id] ?? '', 'd' => '');
            $snapshots[$id] = array('name' => $image['name'], 'json' => ChangeHistory::snapshotJson($values));
        }
        return $snapshots;
    }

    /*
     * Writes changes, merging each into the action's existing row for the same item and field when there is one: its new
     * side is replaced and its old side kept, and when the final value equals the original (by the comparison rules,
     * with long text by its full hash and length) the row is removed
     */
    protected function writeChanges(array $changes)
    {
        if (!$changes) return;

        // Asset names for the changed id fields, both sides, a query per field
        $display_ids = array();
        foreach ($changes as $change) {
            if ($change['kind'] !== 'id') continue;
            $display_ids[$change['type']][$change['field']][] = $change['old']['v'];
            $display_ids[$change['type']][$change['field']][] = $change['new']['v'];
        }
        $display = array();
        foreach ($display_ids as $type => $by_field) {
            foreach ($by_field as $field => $ids) $display[$type][$field] = $this->displayNames($type, $field, $ids);
        }

        $existing = $this->has_rows ? $this->existingRows($changes) : array();
        $rows = array();

        foreach ($changes as $change) {

            $kind = $change['kind'];
            $old = $change['old'];
            $new = $change['new'];
            $detail = $change['detail'] !== '' ? array($change['detail']) : array();

            if ($kind === 'id') {
                $old['d'] = $display[$change['type']][$change['field']][(int) $old['v']] ?? '';
                $new['d'] = $display[$change['type']][$change['field']][(int) $new['v']] ?? '';
            } elseif (substr($change['field'], 0, 3) === 'cf:') {
                $old['d'] = mb_substr((string) ($old['name'] ?? ''), 0, 255, 'UTF-8');
                $new['d'] = '';
                if (!empty($old['cb']) || !empty($new['cb'])) $detail[] = 'cf_checkbox';
            }

            list($old_value, $old_cut) = ChangeHistory::cut((string) $old['v'], ChangeHistory::MAX_VALUE_BYTES);
            list($new_value, $new_cut) = ChangeHistory::cut((string) $new['v'], ChangeHistory::MAX_VALUE_BYTES);
            if ($old_cut || !empty($old['cut'])) $detail[] = 'old_truncated';
            if ($new_cut || !empty($new['cut'])) $detail[] = 'new_truncated';

            $key = $change['type'] . '|' . $change['id'] . '|' . $change['field'];

            if (isset($existing[$key])) {

                $row = $existing[$key];
                if ($kind === 'long') {
                    $back = (string) $row['old_hash'] === (string) $new['h'] && (int) $row['old_length'] === (int) $new['l'];
                } elseif ($kind === 'cost') {
                    $back = ChangeHistory::same('cost', array('v' => $row['old_value'], 'd' => $row['old_display']), $new);
                } else {
                    $back = ChangeHistory::normalize($kind, $row['old_value']) === ChangeHistory::normalize($kind, $new['v'])
                        && ($kind !== 'text' || (string) $row['old_display'] === (string) $new['d']
                            || substr($change['field'], 0, 3) === 'cf:');
                }

                if ($back) {
                    $stmt = $this->pdo->prepare("DELETE FROM change_history WHERE id = :id");
                } else {
                    $codes = array_diff(array_filter(explode(',', (string) $row['detail'])), array('new_truncated'));
                    if (in_array('new_truncated', $detail, true)) $codes[] = 'new_truncated';
                    $stmt = $this->pdo->prepare("
                        UPDATE change_history
                        SET new_value = :new_value,
                            new_display = :new_display,
                            detail = :detail
                        WHERE id = :id");
                    $stmt->bindValue('new_value', $new_value, \PDO::PARAM_STR);
                    $stmt->bindValue('new_display', (string) $new['d'], \PDO::PARAM_STR);
                    $stmt->bindValue('detail', implode(',', array_unique($codes)), \PDO::PARAM_STR);
                }
                $stmt->bindValue('id', (int) $row['id'], \PDO::PARAM_INT);
                $stmt->execute();
                continue;

            }

            // A field back where it started, with no row of its own, needs none (for text, only when its display is the same
            // too, as for a merged row above, since record() can change the display alone, such as a registrar's IANA ID)
            if (ChangeHistory::same($kind, $old, $new) && ($kind !== 'text' || (string) $old['d'] === (string) $new['d']
                || substr($change['field'], 0, 3) === 'cf:')) continue;
            $rows[] = $this->row($change['type'], $change['id'], $change['name'], 'update', $change['field'], $old_value,
                $new_value, (string) $old['d'], (string) $new['d'], $kind === 'long' ? (string) $old['h'] : '',
                $kind === 'long' ? (int) $old['l'] : 0, '', $change['via'], implode(',', array_unique($detail)));

        }

        $this->insertRows($rows);
    }

    // The action's rows already written for these changes' items and fields, keyed by type, id, and field
    protected function existingRows(array $changes)
    {
        $by_type = array();
        foreach ($changes as $change) $by_type[$change['type']][$change['id']] = true;

        $rows = array();
        foreach ($by_type as $type => $ids) {
            foreach (array_chunk(array_keys($ids), ChangeHistory::MAX_CHUNK) as $chunk) {
                $stmt = $this->pdo->prepare("
                    SELECT id, item_id, field, old_value, old_display, old_hash, old_length, detail
                    FROM change_history
                    WHERE operation_id = :operation_id
                      AND item_type = :item_type
                      AND change_type = 'update'
                      AND item_id IN (" . implode(',', array_map('intval', $chunk)) . ")");
                $stmt->bindValue('operation_id', $this->operation_id, \PDO::PARAM_INT);
                $stmt->bindValue('item_type', $type, \PDO::PARAM_STR);
                $stmt->execute();
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $rows[$type . '|' . $row['item_id'] . '|' . $row['field']] = $row;
                }
                $stmt->closeCursor();
            }
        }
        return $rows;
    }

    // One history row's values, in the order insertRows() writes them
    protected function row($type, $id, $name, $change_type, $field, $old_value, $new_value, $old_display, $new_display,
                           $old_hash, $old_length, $snapshot, $via, $detail)
    {
        // The name and display columns hold 255 characters (the TEXT values are cut by bytes instead)
        $name = mb_substr((string) $name, 0, 255, 'UTF-8');
        $old_display = mb_substr((string) $old_display, 0, 255, 'UTF-8');
        $new_display = mb_substr((string) $new_display, 0, 255, 'UTF-8');
        return array($type, (int) $id, $name, $change_type, (string) $field, (string) $old_value, (string) $new_value,
            $old_display, $new_display, (string) $old_hash, (int) $old_length, (string) $snapshot, (string) $via,
            (string) $detail);
    }

    /*
     * Writes history rows as multi-row INSERTs of at most INSERT_ROWS rows and INSERT_BYTES of values, well under the
     * smallest default max_allowed_packet. A single larger row is written on its own.
     */
    protected function insertRows(array $rows)
    {
        if (!$rows) return;
        $this->ensureOperation();

        $batch = array();
        $bytes = 0;
        foreach ($rows as $row) {
            $row_bytes = strlen(implode('', $row)) + 64;
            if ($batch && (count($batch) >= ChangeHistory::INSERT_ROWS || $bytes + $row_bytes > ChangeHistory::INSERT_BYTES)) {
                $this->insertBatch($batch);
                $batch = array();
                $bytes = 0;
            }
            $batch[] = $row;
            $bytes += $row_bytes;
        }
        if ($batch) $this->insertBatch($batch);
        $this->has_rows = true;
    }

    protected function insertBatch(array $batch)
    {
        $placeholders = array();
        $params = array();
        foreach ($batch as $row) {
            $placeholders[] = '(?, ' . implode(', ', array_fill(0, count($row), '?')) . ', ?, ?, ?)';
            $params[] = $this->operation_id;
            foreach ($row as $value) $params[] = $value;
            $params[] = $this->source;
            $params[] = $this->user_id;
            $params[] = $this->time;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO change_history
            (operation_id, item_type, item_id, item_name, change_type, field, old_value, new_value, old_display,
             new_display, old_hash, old_length, snapshot, via, detail, source, user_id, changed_time)
            VALUES " . implode(', ', $placeholders));
        foreach ($params as $n => $value) {
            $stmt->bindValue($n + 1, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
    }

    // Writes the action's change_operations row, with the username at the time, before its first change row
    protected function ensureOperation()
    {
        if ($this->operation_id !== 0) return;
        $this->time = gmdate('Y-m-d H:i:s');

        $username = '';
        if ($this->user_id > 0) {
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = :id");
            $stmt->bindValue('id', $this->user_id, \PDO::PARAM_INT);
            $stmt->execute();
            $username = (string) $stmt->fetchColumn();
            $stmt->closeCursor();
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO change_operations
            (source, `action`, user_id, username, note, note_code, item_count, insert_time)
            VALUES
            (:source, :action, :user_id, :username, :note, :note_code, 0, :insert_time)");
        $stmt->bindValue('source', $this->source, \PDO::PARAM_STR);
        $stmt->bindValue('action', $this->action, \PDO::PARAM_STR);
        $stmt->bindValue('user_id', $this->user_id, \PDO::PARAM_INT);
        $stmt->bindValue('username', $username, \PDO::PARAM_STR);
        // A note longer than the column keeps what fits and ends with an ellipsis, so the history and the export show it
        // was shortened
        list($note, $note_cut) = ChangeHistory::cut($this->note, ChangeHistory::MAX_VALUE_BYTES);
        if ($note_cut) {
            list($note) = ChangeHistory::cut($this->note, ChangeHistory::MAX_VALUE_BYTES - strlen('…'));
            $note .= '…';
        }
        $stmt->bindValue('note', $note, \PDO::PARAM_STR);
        $stmt->bindValue('note_code', $this->note_code, \PDO::PARAM_STR);
        $stmt->bindValue('insert_time', $this->time, \PDO::PARAM_STR);
        $stmt->execute();
        $this->operation_id = (int) $this->pdo->lastInsertId();
    }

} //@formatter:on
