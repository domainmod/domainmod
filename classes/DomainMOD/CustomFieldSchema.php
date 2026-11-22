<?php
/**
 * /classes/DomainMOD/CustomFieldSchema.php
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
 * Adding and deleting a custom field's columns (the value column in domain_field_data or ssl_cert_field_data, and its
 * display column in user_settings), and the daily clean-up of columns a part-way add or delete left behind. ALTER TABLE
 * commits straight away, so each of these holds the item type's custom field named lock (ChangeHistory::lockCustomFields())
 * from before its first write until after its last ALTER TABLE, and the request's shared restore lock, so a restore can't
 * replace the field data tables in the middle of one.
 *
 * A delete loses every item's value, so it records each non-empty one in the Change History first, in one transaction
 * that also deletes the definition row, and only then drops the columns. A field's columns are created first and its
 * definition row inserted last, so nothing ever sees a definition whose column is missing.
 */
class CustomFieldSchema
{
    // Each field type's value column (custom_field_types.id)
    const COLUMN_SQL = array(
        1 => "TINYINT(1) NOT NULL DEFAULT '0'",
        2 => "VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL",
        3 => "LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL",
        4 => "DATE NOT NULL DEFAULT '1970-01-01'",
        5 => "DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'",
        6 => "VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL");

    // The values each field type holds when it's empty (an unticked box, an unset date)
    const EMPTY_VALUES = array(1 => array('', '0'), 4 => array('', '1970-01-01', '0000-00-00'),
        5 => array('', '1970-01-01 00:00:00', '0000-00-00 00:00:00'));

    // The prefix of each item type's display columns in user_settings
    public static function displayPrefix($type)
    {
        return $type === 'ssl' ? 'dispcsf_' : 'dispcdf_';
    }

    // Takes the restore check and the custom field lock, or throws with the restore or busy message
    protected static function lock(\PDO $pdo, $type)
    {
        RestoreLock::forWrite(ChangeHistory::isAvailable($pdo));
        if (!ChangeHistory::lockCustomFields($pdo, $type, 10)) throw new RestoreLockException(RestoreLock::message(false));
    }

    /*
     * Adds a field: its value column and display column first, then its definition row. Returns false when a field with
     * that database name already exists (checked with the lock held), otherwise true.
     */
    public static function add(\PDO $pdo, $type, $name, $field_name, $description, $type_id, $notes, $user_id, $timestamp)
    {
        $info = ChangeHistory::ITEM_TYPES[$type];
        if (!preg_match('/^[a-zA-Z_]+$/', (string) $field_name) || !isset(self::COLUMN_SQL[(int) $type_id])) {
            throw new \InvalidArgumentException('Invalid custom field');
        }

        self::lock($pdo, $type);

        try {

            $stmt = $pdo->prepare("
                SELECT field_name
                FROM " . $info['fields'] . "
                WHERE field_name = :field_name
                LIMIT 1");
            $stmt->bindValue('field_name', $field_name, \PDO::PARAM_STR);
            $stmt->execute();
            $exists = $stmt->fetchColumn();
            $stmt->closeCursor();
            if ($exists) return false;

            // Columns a part-way delete or add left under this name go first
            self::dropOrphans($pdo, $type, $field_name);

            $pdo->query("
                ALTER TABLE `" . $info['field_data'] . "`
                ADD `" . $field_name . "` " . self::COLUMN_SQL[(int) $type_id]);
            $pdo->query("
                ALTER TABLE `user_settings`
                ADD `" . self::displayPrefix($type) . $field_name . "` TINYINT(1) NOT NULL DEFAULT '0'");

            $stmt = $pdo->prepare("
                INSERT INTO " . $info['fields'] . "
                (`name`, field_name, description, type_id, notes, created_by, insert_time)
                VALUES
                (:new_name, :new_field_name, :new_description, :new_field_type_id, :new_notes, :created_by, :timestamp)");
            $stmt->bindValue('new_name', $name, \PDO::PARAM_STR);
            $stmt->bindValue('new_field_name', $field_name, \PDO::PARAM_STR);
            $stmt->bindValue('new_description', $description, \PDO::PARAM_STR);
            $stmt->bindValue('new_field_type_id', (int) $type_id, \PDO::PARAM_INT);
            $stmt->bindValue('new_notes', $notes, \PDO::PARAM_LOB);
            $stmt->bindValue('created_by', (int) $user_id, \PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, \PDO::PARAM_STR);
            $stmt->execute();
            return true;

        } finally {

            ChangeHistory::unlockCustomFields($pdo, $type);

        }
    }

    /*
     * Deletes a field. One transaction locks its definition row, records each item's non-empty value in the Change History
     * (read a chunk at a time, with long text only up to what a history value holds), and deletes the definition row.
     * Then its value column and display column are dropped. Returns array(name, field_name), or null when the field no
     * longer exists. A DROP that fails leaves a column the daily clean-up removes.
     */
    public static function delete(\PDO $pdo, $type, $field_id, $user_id)
    {
        $info = ChangeHistory::ITEM_TYPES[$type];
        self::lock($pdo, $type);

        try {

            $pdo->beginTransaction();

            try {

                $stmt = $pdo->prepare("
                    SELECT `name`, field_name, type_id
                    FROM " . $info['fields'] . "
                    WHERE id = :field_id
                    FOR UPDATE");
                $stmt->bindValue('field_id', (int) $field_id, \PDO::PARAM_INT);
                $stmt->execute();
                $field = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                if (!$field || !preg_match('/^[a-zA-Z_]+$/', (string) $field['field_name'])) {
                    $pdo->rollBack();
                    return null;
                }

                $op = (new ChangeHistory($pdo))->start(ChangeHistory::SOURCE_CUSTOM_FIELD, $user_id);
                if ($op->enabled()) self::recordValues($pdo, $op, $type, $field);

                $stmt = $pdo->prepare("DELETE FROM " . $info['fields'] . " WHERE id = :field_id");
                $stmt->bindValue('field_id', (int) $field_id, \PDO::PARAM_INT);
                $stmt->execute();

                $op->finish();
                $pdo->commit();

            } catch (\Throwable $e) {

                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;

            }

            $pdo->query("ALTER TABLE `" . $info['field_data'] . "` DROP `" . $field['field_name'] . "`");
            $pdo->query("ALTER TABLE `user_settings` DROP `" . self::displayPrefix($type) . $field['field_name'] . "`");
            return array($field['name'], $field['field_name']);

        } finally {

            ChangeHistory::unlockCustomFields($pdo, $type);

        }
    }

    // The field's non-empty values, a chunk of field data rows at a time, recorded as lost
    protected static function recordValues(\PDO $pdo, ChangeHistoryOperation $op, $type, array $field)
    {
        $info = ChangeHistory::ITEM_TYPES[$type];
        $column = 'fd.`' . $field['field_name'] . '`';
        $type_id = (int) $field['type_id'];
        $long = $type_id === 3;
        // Text is empty only when it has no characters at all (the columns' collation would match a value of spaces to
        // ''), and the other types when they hold their empty value
        $empty_sql = isset(self::EMPTY_VALUES[$type_id])
            ? "CAST(COALESCE(" . $column . ", '') AS CHAR) NOT IN (" . implode(', ', array_map(array($pdo, 'quote'), self::EMPTY_VALUES[$type_id])) . ")"
            : "LENGTH(COALESCE(" . $column . ", '')) > 0";
        $limit = $long ? ChangeHistoryOperation::chunkSizeFor(1, 1) : ChangeHistory::MAX_CHUNK;
        $last = 0;

        while (true) {

            $stmt = $pdo->prepare("
                SELECT fd.id, fd." . $info['field_id'] . " AS item_id,
                       " . ($long ? "SUBSTRING(" . $column . ", 1, " . ChangeHistory::MAX_VALUE_BYTES . ")" : "CAST(" . $column . " AS CHAR)") . " AS value,
                       LENGTH(COALESCE(" . $column . ", '')) AS bytes
                FROM " . $info['field_data'] . " AS fd
                WHERE fd.id > :last
                  AND " . $empty_sql . "
                ORDER BY fd.id
                LIMIT " . $limit . "
                FOR UPDATE");
            $stmt->bindValue('last', $last, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$rows) break;

            // The items' names, with a plain read (names are only for display, so it never waits on an item's lock)
            $item_ids = array_values(array_unique(array_map('intval', array_column($rows, 'item_id'))));
            $names = array();
            $stmt = $pdo->query("SELECT id, `" . $info['name'] . "` FROM " . $info['table'] . " WHERE id IN ("
                . implode(',', $item_ids) . ")");
            foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $names[(int) $row[0]] = (string) $row[1];
            $stmt->closeCursor();

            $values = array();
            foreach ($rows as $row) {
                $last = (int) $row['id'];
                list($value, $cut) = ChangeHistory::cut((string) $row['value'], ChangeHistory::MAX_VALUE_BYTES);
                $values[(int) $row['item_id']] = array($names[(int) $row['item_id']] ?? '', $value,
                    $cut || (int) $row['bytes'] > strlen($value));
            }
            $op->recordFieldLoss($type, $field['field_name'], $field['name'], $values, $type_id === ChangeHistory::CUSTOM_FIELD_CHECKBOX);
            if (count($rows) < $limit) break;

        }
    }

    /*
     * Drops the field data columns with no definition row (never the fixed columns), and separately the display columns
     * in user_settings with no definition, each set checked on its own, so a display column left after its value column
     * was dropped is still found. With $only_name, only that field's columns are checked (a field added again under the
     * same name). The caller holds the custom field lock, or this takes it without waiting and skips when it's busy, as
     * it does when the restore lock can't be had. Returns how many columns it dropped.
     */
    public static function dropOrphans(\PDO $pdo, $type, $only_name = null)
    {
        $info = ChangeHistory::ITEM_TYPES[$type];
        $own_lock = $only_name === null;

        if ($own_lock) {
            $lock = RestoreLock::shared();
            if ($lock['state'] !== 'held' || $lock['flag']) return 0;
            if (!ChangeHistory::lockCustomFields($pdo, $type, 0)) return 0;
        }

        // The daily repair waits at most five seconds for a table another request is reading (such as a long export), so
        // requests that queue behind its ALTER TABLE wait at most that long, and it tries again the next day
        $lock_wait = null;
        if ($own_lock) {
            $lock_wait = (int) $pdo->query("SELECT @@SESSION.lock_wait_timeout")->fetchColumn();
            $pdo->exec("SET SESSION lock_wait_timeout = 5");
        }
        $drop = function ($sql) use ($pdo, $own_lock) {
            try {
                $pdo->query($sql);
                return true;
            } catch (\PDOException $e) {
                if ($own_lock && (int) ($e->errorInfo[1] ?? 0) === 1205) return false;
                throw $e;
            }
        };

        try {

            $defined = $pdo->query("SELECT field_name FROM " . $info['fields'])->fetchAll(\PDO::FETCH_COLUMN);
            // This table's own fixed columns (a domain field may be named ssl_id, and an SSL field domain_id)
            $fixed = array('id', $info['field_id'], 'insert_time', 'update_time');
            $defined = array_map('strtolower', $defined);
            $prefix = self::displayPrefix($type);
            $dropped = 0;

            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name");

            $stmt->execute(array('table_name' => $info['field_data']));
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $column) {
                $lower = strtolower($column);
                if (in_array($lower, $fixed, true) || in_array($lower, $defined, true)) continue;
                if ($only_name !== null && $lower !== strtolower($only_name)) continue;
                if (!preg_match('/^[a-zA-Z_]+$/', $column)) continue;
                if (!$drop("ALTER TABLE `" . $info['field_data'] . "` DROP `" . $column . "`")) return $dropped;
                $dropped++;
            }

            $stmt->execute(array('table_name' => 'user_settings'));
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $column) {
                if (stripos($column, $prefix) !== 0) continue;
                $name = strtolower(substr($column, strlen($prefix)));
                if (in_array($name, $defined, true)) continue;
                if ($only_name !== null && $name !== strtolower($only_name)) continue;
                if (!preg_match('/^[a-zA-Z_]+$/', $column)) continue;
                if (!$drop("ALTER TABLE `user_settings` DROP `" . $column . "`")) return $dropped;
                $dropped++;
            }

            return $dropped;

        } finally {

            if ($lock_wait !== null) $pdo->exec("SET SESSION lock_wait_timeout = " . max(1, $lock_wait));
            if ($own_lock) ChangeHistory::unlockCustomFields($pdo, $type);

        }
    }

} //@formatter:on
