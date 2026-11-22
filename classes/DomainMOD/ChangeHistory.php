<?php
/**
 * /classes/DomainMOD/ChangeHistory.php
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
 * The Change History of domains, SSL certificates, and watched domains (the Watch List): one row in change_operations per action (one Save, one bulk
 * action, one import, one fee change), and one row in change_history per field that changed (or per item created or
 * deleted). Every write site calls start() inside its own transaction, so a change and its history commit or roll back
 * together (see ChangeHistoryOperation). This class holds what's recorded and how values compare, and the history's
 * availability, retention, and pruning.
 */
class ChangeHistory
{
    const SOURCE_USER = 'user';
    const SOURCE_BULK = 'bulk';
    const SOURCE_CSV_IMPORT = 'csv_import';
    const SOURCE_QUEUE = 'queue';
    const SOURCE_SSL_QUEUE = 'ssl_queue';
    const SOURCE_REFRESH = 'refresh';
    const SOURCE_SCHEDULED_REFRESH = 'scheduled_refresh';
    const SOURCE_ASSET = 'asset';
    const SOURCE_MAINTENANCE = 'maintenance';
    const SOURCE_CUSTOM_FIELD = 'custom_field';
    const SOURCE_WATCH_LIST = 'watch_list';
    const SOURCE_API = 'api';
    const SOURCE_UPGRADE = 'upgrade';
    const SOURCE_RESTORE = 'restore';
    const SOURCE_SYSTEM = 'system';

    const SOURCES = array('user', 'bulk', 'csv_import', 'queue', 'ssl_queue', 'refresh', 'scheduled_refresh', 'asset',
        'maintenance', 'custom_field', 'watch_list', 'api', 'upgrade', 'restore', 'system');

    // The most a stored value holds (a TEXT column), in bytes
    const MAX_VALUE_BYTES = 65535;
    // A create or delete row's snapshot, in bytes of JSON
    const SNAPSHOT_BYTES = 262144;
    // The values one chunk's before-images may hold, and again its after-images, in bytes
    const CAPTURE_BUDGET = 16777216;
    // What one long field can bring from the database at most: 65,535 characters of up to three bytes each
    const LONG_FIELD_BYTES = 196605;
    const MAX_CHUNK = 1000;
    // History rows per INSERT, and the values per INSERT, in bytes
    const INSERT_ROWS = 500;
    const INSERT_BYTES = 1048576;
    // Pruning: rows per batch, and the time it may take, in seconds
    const PRUNE_BATCH = 5000;
    const PRUNE_BUDGET = 60;
    // Months the history is kept when the setting can't be read (0 in the setting is Forever)
    const DEFAULT_RETENTION_MONTHS = 36;
    const RETENTION_CHOICES = array(12, 24, 36, 60, 120, 0);
    // The first version with the Change History
    const FIRST_VERSION = '4.27.0';

    /*
     * Each item type: its table, the column with its name, its custom field data table and that table's item id column,
     * its custom field definitions, and its fee table. A watched domain has no custom fields and no fees, so those are ''.
     */
    const ITEM_TYPES = array(
        'domain' => array('table' => 'domains', 'name' => 'domain', 'field_data' => 'domain_field_data',
            'field_id' => 'domain_id', 'fields' => 'domain_fields', 'fee_table' => 'fees'),
        'ssl' => array('table' => 'ssl_certs', 'name' => 'name', 'field_data' => 'ssl_cert_field_data',
            'field_id' => 'ssl_id', 'fields' => 'ssl_cert_fields', 'fee_table' => 'ssl_fees'),
        'hosting' => array('table' => 'hosting_services', 'name' => 'name', 'field_data' => 'hosting_service_field_data',
            'field_id' => 'service_id', 'fields' => 'hosting_service_fields', 'fee_table' => 'hosting_fees'),
        'watch' => array('table' => 'watch_domains', 'name' => 'domain', 'field_data' => '', 'field_id' => '', 'fields' => '',
            'fee_table' => ''));

    /*
     * The recorded columns of each item type, and how each compares: 'text' as stored, 'long' (notes) by hash and length,
     * 'date' as Y-m-d (an unset date as blank), 'id' and 'int' as whole numbers (an id also keeps the referenced asset's name), and 'cost' as a
     * two-decimal amount with its fee's currency. Everything else (fee_id, fee_fixed, tld, the insert-only columns,
     * update_time, and the registry status columns) isn't recorded. A watched domain's check results (its stage, expiry
     * date, registrar, and statuses) are recorded with ChangeHistoryOperation::record() by WatchCheck, not compared.
     */
    const FIELDS = array(
        'domain' => array('domain' => 'text', 'account_id' => 'id', 'owner_id' => 'id', 'registrar_id' => 'id',
            'expiry_date' => 'date', 'cat_id' => 'id', 'dns_id' => 'id', 'ip_id' => 'id', 'service_id' => 'id',
            'function' => 'text', 'notes' => 'long', 'autorenew' => 'int', 'privacy' => 'int', 'active' => 'int',
            'total_cost' => 'cost'),
        'ssl' => array('name' => 'text', 'domain_id' => 'id', 'account_id' => 'id', 'owner_id' => 'id',
            'ssl_provider_id' => 'id', 'type_id' => 'id', 'ip_id' => 'id', 'cat_id' => 'id', 'expiry_date' => 'date',
            'notes' => 'long', 'active' => 'int', 'total_cost' => 'cost'),
        'hosting' => array('name' => 'text', 'account_id' => 'id', 'owner_id' => 'id', 'hosting_id' => 'id',
            'type_id' => 'id', 'ip_id' => 'id', 'cat_id' => 'id', 'hostname' => 'text', 'billing_term' => 'int',
            'renewal_day' => 'int', 'expiry_date' => 'date', 'autorenew' => 'int', 'notes' => 'long', 'active' => 'int',
            'total_cost' => 'cost'),
        'watch' => array('reason' => 'text', 'notes' => 'long', 'archived' => 'int'));

    // The fields that follow the account: recorded only when they change without the account changing
    const FOLLOWS_ACCOUNT = array('domain' => array('owner_id', 'registrar_id'), 'ssl' => array('owner_id', 'ssl_provider_id'),
        'hosting' => array('owner_id', 'hosting_id'), 'watch' => array());

    /*
     * The facts a create or delete snapshot keeps: the insert-only ones, and for a watched domain what its checks last
     * found, so a deleted entry's last known state can still be read
     */
    const SNAPSHOT_FACTS = array('domain' => array('creation_type_id', 'created_by', 'insert_time', 'tld'),
        'ssl' => array('creation_type_id', 'created_by', 'insert_time'),
        'hosting' => array('creation_type_id', 'created_by', 'insert_time'),
        'watch' => array('tld', 'stage', 'statuses', 'expiry_date', 'registration_date', 'registrar_name',
            'registrar_iana_id', 'moved_domain_id', 'created_by', 'insert_time'));

    // Fields that aren't columns, recorded with ChangeHistoryOperation::record() by the code that owns them
    const EXTRA_FIELDS = array('dns_servers', 'transfer_lock', 'registry_problem', 'epp_status', 'live_ns', 'live_ips',
        'issuer', 'stage', 'registrar', 'statuses', 'moved_domain_id');

    // Column names that are never recorded, whatever list they turn up in (a unit test checks the lists against it)
    const DENY = array('password', 'api_key', 'api_secret', 'api_app_name', 'username', 'email_address', 'secret',
        'token', 'two_factor_secret', 'two_factor_stamp', 'code_hash', 'token_hash', 'smtp_password', 'reseller_id');

    // Custom field types (custom_field_types.id) and how their values compare: Text Area by hash and length, and a Date or
    // Time Stamp left blank as blank (see normalize())
    const CUSTOM_FIELD_KINDS = array(1 => 'int', 2 => 'text', 3 => 'long', 4 => 'date', 5 => 'stamp', 6 => 'text');
    // The custom field type that's a check box (custom_field_types id 1)
    const CUSTOM_FIELD_CHECKBOX = 1;

    /*
     * The tables a full backup made by each version holds, by the version that changed the set, for the restore's check
     * of whether a dump is complete (see RestoreHistory). The entry that applies is the newest one at or below the
     * dump's version. The Data Warehouse's runtime tables (dw_accounts, dw_dns_zones, dw_dns_records), which the
     * warehouse rebuilds from its servers, aren't in it. A unit test compares the newest entry with install/go.php.
     */
    const BACKUP_TABLES = array(
        '4.27.0' => array('api_registrars', 'categories', 'change_history', 'change_operations', 'creation_types',
            'currencies', 'currency_conversions', 'custom_field_types', 'dns', 'domain_field_data', 'domain_fields',
            'domain_queue', 'domain_queue_history', 'domain_queue_list', 'domain_queue_list_history', 'domain_queue_temp',
            'domain_refresh_status',
            'domains', 'dw_servers', 'fees', 'goal_activity', 'hosting', 'hosting_fees', 'hosting_provider_accounts',
            'hosting_service_field_data', 'hosting_service_fields', 'hosting_services', 'hosting_types', 'ip_addresses',
            'languages', 'log', 'owners',
            'registrar_accounts', 'registrars', 'scheduler', 'segment_data', 'segments', 'settings', 'ssl_accounts',
            'ssl_cert_field_data', 'ssl_cert_fields', 'ssl_cert_status', 'ssl_cert_types', 'ssl_certs', 'ssl_fees',
            'ssl_providers', 'ssl_queue', 'ssl_queue_history', 'timezones', 'user_recovery_codes',
            'user_remembered_devices', 'user_settings', 'users', 'watch_domains'));

    // Whether the history is on, by connection, worked out once per request (see isAvailable())
    protected static $available = array();

    protected $pdo;

    // $pdo is the shared connection (Database::getInstance()->cnxx), or a test's stand-in for it
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /*
     * Starts an action, inside the caller's transaction (it throws without one, so a write site that has none is found in
     * testing). Before the database update that adds the history, or while the start marker is missing, it returns an
     * operation that records nothing, so the write still goes ahead. It throws when a restore is in progress or the
     * restore lock is busy (see RestoreLock::forWrite()), so the caller's write rolls back.
     */
    public function start($source, $user_id, $action = '', $note = '', $note_code = '')
    {
        if (!$this->pdo->inTransaction()) {
            throw new \LogicException('The Change History has to start inside a transaction');
        }

        $available = self::isAvailable($this->pdo);
        RestoreLock::forWrite($available);

        return new ChangeHistoryOperation($this->pdo, $available, $source, (int) $user_id, (string) $action,
            (string) $note, (string) $note_code);
    }

    /*
     * Whether the history is recorded and shown: both tables exist and the start marker (written in the same
     * transaction as the database version, by the upgrade, a fresh install, or a restore's cleanup) is there. Worked out
     * once per request and connection. A database without the tables, or without the marker's columns, answers false,
     * and any other database error (such as a lock timeout) is thrown, so a write stops rather than going unrecorded.
     */
    public static function isAvailable($pdo)
    {
        $key = spl_object_id($pdo);
        if (isset(self::$available[$key])) return self::$available[$key];

        /*
         * A locking read, so a write site's check inside its transaction never opens the transaction's snapshot before
         * the write site's own locking reads (a plain read would, and later plain reads would then see the rows as they
         * were before those locks). The start marker is the first operation row (it's written into empty tables, and
         * pruning never removes it), so it's read as the lowest id: that locks only the gap below it, while every new
         * operation row goes in above the highest id, so the check never holds up another action's insert. A marker
         * that isn't first (which nothing writes) is looked for through the (source, action) index instead. The changes
         * table is checked in the table catalogue.
         */
        try {
            $stmt = $pdo->query("
                SELECT source, `action`
                FROM change_operations
                ORDER BY id
                LIMIT 1
                LOCK IN SHARE MODE");
            $first = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $available = is_array($first) && $first['source'] === 'system' && $first['action'] === 'history_started';
            if (!$available && is_array($first)) {
                $stmt = $pdo->query("
                    SELECT id
                    FROM change_operations
                    WHERE source = 'system'
                      AND `action` = 'history_started'
                    LIMIT 1
                    LOCK IN SHARE MODE");
                $available = $stmt->fetchColumn() !== false;
                $stmt->closeCursor();
            }
            if ($available) {
                $stmt = $pdo->query("
                    SELECT count(*)
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'change_history'");
                $available = (int) $stmt->fetchColumn() > 0;
                $stmt->closeCursor();
            }
        } catch (\PDOException $e) {
            // Only a missing table or column (before the database update, or a restore part-way) means no history. Any
            // other error, such as a lock timeout, stops the write rather than letting it go unrecorded.
            if (!in_array((int) ($e->errorInfo[1] ?? 0), array(1054, 1146), true)) throw $e;
            $available = false;
        }

        return self::$available[$key] = $available;
    }

    // Forgets what isAvailable() worked out, after the history's tables or markers change (a restore's cleanup)
    public static function forgetAvailability()
    {
        self::$available = array();
    }

    // The fields of an item type a capture reads, limited to $only when it's given (unknown names are left out)
    public static function fields($type, $only = null)
    {
        $fields = self::FIELDS[$type];
        if ($only === null) return $fields;
        return array_intersect_key($fields, array_flip($only));
    }

    /*
     * A value as it's compared and stored: dates as Y-m-d, time stamps as stored, amounts as two-decimal strings, ids and
     * flags as whole numbers, and text as stored, with NULL as ''. A date or time stamp that was never set (Time::isUnset(),
     * such as the column default 1970-01-01 or the zero date a blank custom field is saved as) is '', so it shows blank
     * and a move between those placeholders isn't a change. Long values are compared by hash and length instead (see
     * same()).
     */
    public static function normalize($kind, $value)
    {
        if ($value === null) $value = '';
        if (($kind === 'date' || $kind === 'stamp') && Time::isUnset($value)) return '';
        if ($kind === 'date') return substr(trim((string) $value), 0, 10);
        if ($kind === 'cost') return number_format((float) $value, 2, '.', '');
        if ($kind === 'id' || $kind === 'int') return (string) (int) $value;
        return (string) $value;
    }

    /*
     * Whether two captured values are the same. Each is array('v' => value, 'h' => hash, 'l' => length, 'd' => display):
     * a long value compares by its full value's SHA-256 and length, a cost by its amount and currency, and anything else
     * by its normalised value.
     */
    public static function same($kind, array $old, array $new)
    {
        if ($kind === 'long') return (string) $old['h'] === (string) $new['h'] && (int) $old['l'] === (int) $new['l'];
        if ($kind === 'cost') {
            return self::normalize('cost', $old['v']) === self::normalize('cost', $new['v'])
                && (string) $old['d'] === (string) $new['d'];
        }
        return self::normalize($kind, $old['v']) === self::normalize($kind, $new['v']);
    }

    /*
     * The longest start of $text that fits in $bytes bytes, cut on a character boundary (never part-way through a UTF-8
     * character), as array(text, whether anything was cut)
     */
    public static function cut($text, $bytes)
    {
        $text = (string) $text;
        if (strlen($text) <= $bytes) return array($text, false);
        if ($bytes <= 0) return array('', $text !== '');

        $kept = substr($text, 0, $bytes);
        // Step back over a character the cut split: continuation bytes are 10xxxxxx
        $end = strlen($kept);
        $start = $end;
        while ($start > 0 && (ord($kept[$start - 1]) & 0xC0) === 0x80) $start--;
        if ($start > 0) {
            $lead = ord($kept[$start - 1]);
            $need = $lead >= 0xF0 ? 4 : ($lead >= 0xE0 ? 3 : ($lead >= 0xC0 ? 2 : 1));
            if ($end - ($start - 1) < $need) $kept = substr($kept, 0, $start - 1);
        }
        return array($kept, true);
    }

    /*
     * A create or delete row's snapshot as JSON, within SNAPSHOT_BYTES. $values maps each key (the field code for a
     * column, 'cf:<field_name>' for a custom field, 'fact:<name>' for an insert-only fact) to array('v' => value,
     * 'd' => display, 'long' => whether it's long text, 'cut' => whether the capture already cut it, 'name' => a custom
     * field's name, 'cb' => whether a custom field is a check box). Every short value goes in first (custom field entries are left out when they'd crowd out the rest,
     * see below), and what's left is shared fairly among the long values, each cut on a character boundary to its share,
     * so a long note can't push out a later short field. A value is marked as cut whenever the capture's 64 KB prefix or
     * the share removed text. Shares are measured as JSON-encoded bytes, and the finished JSON is checked: if it's still
     * over, the long values are cut further until it fits.
     */
    public static function snapshotJson(array $values, $limit = self::SNAPSHOT_BYTES)
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        $entry = function ($value, $text, $cut) {
            $out = array('v' => $text);
            if ((string) ($value['d'] ?? '') !== '') $out['d'] = (string) $value['d'];
            if (isset($value['name'])) $out['name'] = (string) $value['name'];
            if (!empty($value['cb'])) $out['cb'] = 1;
            if ($cut) $out['cut'] = 1;
            return $out;
        };

        $snapshot = array();
        $long = array();
        foreach ($values as $key => $value) {
            if (!empty($value['long'])) {
                $long[] = $key;
                $snapshot[$key] = $entry($value, '', !empty($value['cut']));
            } else {
                $snapshot[$key] = $entry($value, (string) $value['v'], !empty($value['cut']));
            }
        }

        /*
         * Short values usually fit, but hundreds of custom fields with long labels may not: custom field entries are then
         * left out from the end, short ones first and then Text Areas (the item's own fields always stay), until the rest
         * takes at most half the limit when there are long values to share the rest, or the whole limit when there aren't.
         * '_cut' marks a snapshot that left entries out.
         */
        $trim = function (array $snapshot, array $long, $room) use ($flags) {
            $custom = array_filter(array_keys($snapshot), function ($key) {
                return strpos((string) $key, 'cf:') === 0;
            });
            $optional = array_merge(array_values(array_intersect($custom, $long)), array_values(array_diff($custom, $long)));
            while (strlen(json_encode($snapshot, $flags)) > $room && $optional) {
                unset($snapshot[array_pop($optional)]);
                $snapshot['_cut'] = 1;
            }
            return array($snapshot, array_values(array_filter($long, function ($key) use ($snapshot) {
                return isset($snapshot[$key]);
            })));
        };
        list($snapshot, $long) = $trim($snapshot, $long, $long ? (int) floor($limit / 2) : $limit);

        if (!$long) return json_encode($snapshot, $flags);

        // The room left is shared fairly: a value shorter than its share takes only what it needs, and what it leaves is
        // shared among the longer ones
        $base = strlen(json_encode($snapshot, $flags));
        $room = max(0, $limit - $base - 16 * count($long));
        $sizes = array();
        foreach ($long as $key) $sizes[$key] = strlen(json_encode((string) $values[$key]['v'], $flags)) - 2;
        asort($sizes);
        $shares = array();
        $left = count($sizes);
        foreach ($sizes as $key => $size) {
            $shares[$key] = min($size, (int) floor($room / $left));
            $room -= $shares[$key];
            $left--;
        }

        for ($attempt = 0, $scale = 1.0; $attempt < 20; $attempt++, $scale *= 0.9) {
            foreach ($long as $key) {
                $text = (string) $values[$key]['v'];
                // JSON escaping can make a value longer than its bytes, so the share is measured encoded
                $fit = self::cutEncoded($text, (int) floor($shares[$key] * $scale), $flags);
                $snapshot[$key] = $entry($values[$key], $fit, !empty($values[$key]['cut']) || $fit !== $text);
            }
            $json = json_encode($snapshot, $flags);
            if (strlen($json) <= $limit) return $json;
        }

        foreach ($long as $key) $snapshot[$key] = $entry($values[$key], '', true);
        list($snapshot) = $trim($snapshot, $long, $limit);
        return json_encode($snapshot, $flags);
    }

    // The longest start of $text whose JSON-encoded form (without its quotes) fits in $bytes, on a character boundary
    protected static function cutEncoded($text, $bytes, $flags)
    {
        if (strlen(json_encode($text, $flags)) - 2 <= $bytes) return $text;
        list($fit) = self::cut($text, $bytes);
        while ($fit !== '' && strlen(json_encode($fit, $flags)) - 2 > $bytes) {
            list($fit) = self::cut($fit, (int) floor(strlen($fit) * 0.9));
        }
        return $fit;
    }

    /*
     * The retention setting, in months (0 is Forever), or the default when the column can't be read (before the database
     * update adds it)
     */
    public static function retentionMonths(\PDO $pdo)
    {
        try {
            $stmt = $pdo->query("SELECT history_retention_months FROM settings LIMIT 1");
            $months = $stmt->fetchColumn();
            $stmt->closeCursor();
            return $months === false ? self::DEFAULT_RETENTION_MONTHS : (int) $months;
        } catch (\Throwable $e) {
            return self::DEFAULT_RETENTION_MONTHS;
        }
    }

    /*
     * Removes history older than the retention setting, run by the daily cleanup under its shared restore lock. It
     * prunes a prefix of ids, never rows picked by time alone, so a delete row (which always has a higher id than the
     * changes it ends) is never removed while an earlier change to the same item is kept: rows go in id order up to the
     * first one dated at or after the cutoff (see the comment in the method). Each batch of PRUNE_BATCH rows is its own
     * transaction, followed by the operations it left with no changes (never the start and restore markers). It stops
     * at the first recent row, at a short batch, or when its time budget runs out, so a backlog clears over several
     * days. Returns how many change rows it removed.
     */
    public function prune($budget = self::PRUNE_BUDGET, $now = null)
    {
        if (!self::isAvailable($this->pdo)) return 0;

        $lock = RestoreLock::shared();
        if ($lock['state'] !== 'held' || $lock['flag']) return 0;

        $months = self::retentionMonths($this->pdo);
        if ($months <= 0) return 0;

        $cutoff = self::monthsBefore($now ?? 'now', $months);
        $started = microtime(true);

        // The budget is cut down to the time the request has left, so a scheduler run with little time left still
        // finishes its other steps, and pruning doesn't start at all with less than five seconds to spare
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0) {
            $elapsed = microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? $started);
            $budget = min($budget, $limit - $elapsed - 15);
        }
        if ($budget < 5) return 0;

        /*
         * Pruning removes a whole prefix of ids, found a batch at a time: each batch reads the next rows in id order
         * without locks, takes them up to the first one dated at or after the cutoff (where pruning stops), and locks and
         * removes just those, so it never waits on another action's rows (a batch that starts at a recent row locks
         * nothing). An item's life is a run of ids ending with its delete row, so a prefix never keeps part of a life
         * whose delete row it removed. A row a wrong clock dated out of order can only stop pruning early, and a slow
         * action's row committed late isn't seen until it's committed, when its recent date stops the prefix. Each batch
         * is bounded, so a large backlog makes progress on every run.
         */
        // The budget is checked before each batch
        $removed = 0;
        while (microtime(true) - $started < $budget) {

            $this->pdo->beginTransaction();
            try {
                // The setting, read with a shared lock inside the batch, so a change saved meanwhile (such as to Forever)
                // either commits first and stops this run, or waits for this batch
                $setting = $this->pdo->query("SELECT history_retention_months FROM settings LIMIT 1 LOCK IN SHARE MODE")->fetchColumn();
                if ($setting === false || (int) $setting !== $months) {
                    $this->pdo->rollBack();
                    break;
                }

                // The next rows in id order, read without locks (pruning does nothing else in this transaction), so a batch
                // never waits on another action's rows that aren't committed yet (those stay, as recent rows do)
                $stmt = $this->pdo->query("
                    SELECT id, operation_id, changed_time
                    FROM change_history FORCE INDEX (PRIMARY)
                    ORDER BY id
                    LIMIT " . self::PRUNE_BATCH);
                $fetched = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                // The batch's prefix: its rows up to the first one dated at or after the cutoff
                $rows = array();
                $reached = false;
                foreach ($fetched as $row) {
                    if ((string) $row['changed_time'] >= $cutoff) {
                        $reached = true;
                        break;
                    }
                    $rows[] = $row;
                }
                // Then just those rows are locked, by primary key, before they're removed
                if ($rows) {
                    $this->pdo->query("
                        SELECT id
                        FROM change_history
                        WHERE id IN (" . implode(',', array_map('intval', array_column($rows, 'id'))) . ")
                        FOR UPDATE")->closeCursor();
                }

                if ($rows) {
                    $ids = array_map('intval', array_column($rows, 'id'));
                    $this->pdo->exec("DELETE FROM change_history WHERE id IN (" . implode(',', $ids) . ")");
                    $operation_ids = array_values(array_unique(array_map('intval', array_column($rows, 'operation_id'))));
                    foreach (array_chunk($operation_ids, self::MAX_CHUNK) as $chunk) {
                        $this->pdo->exec("
                            DELETE FROM change_operations
                            WHERE id IN (" . implode(',', $chunk) . ")
                              AND source NOT IN ('system', 'restore')
                              AND NOT EXISTS (SELECT 1 FROM change_history AS ch WHERE ch.operation_id = change_operations.id)");
                    }
                }
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;
            }

            $removed += count($rows);
            if ($reached || count($fetched) < self::PRUNE_BATCH) break;

        }

        return $removed;
    }

    // The columns this version creates in each history table, for the restore's check of a restored table's shape
    const EXPECTED_COLUMNS = array(
        'change_operations' => array('id', 'source', 'action', 'user_id', 'username', 'note', 'note_code', 'item_count',
            'insert_time'),
        'change_history' => array('id', 'operation_id', 'item_type', 'item_id', 'item_name', 'change_type', 'field',
            'old_value', 'new_value', 'old_display', 'new_display', 'old_hash', 'old_length', 'snapshot', 'via', 'detail',
            'source', 'user_id', 'changed_time'));

    /*
     * The two tables' CREATE TABLE statements, the same as install/go.php's (a unit test checks they match), for the
     * restore's cleanup when a restored table is missing or lacks one of this version's columns
     */
    public static function tableSql()
    {
        return array(
            'change_operations' => "
            CREATE TABLE IF NOT EXISTS `change_operations` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `source` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `action` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `note` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `note_code` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `item_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`),
                KEY `insert_time` (`insert_time`),
                KEY `source_action` (`source`, `action`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1",
            'change_history' => "
            CREATE TABLE IF NOT EXISTS `change_history` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `operation_id` INT(10) UNSIGNED NOT NULL,
                `item_type` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `item_id` INT(10) UNSIGNED NOT NULL,
                `item_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `change_type` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `field` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `old_value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `new_value` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `old_display` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `new_display` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `old_hash` CHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `old_length` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `snapshot` MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `via` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `detail` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `source` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `changed_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
                PRIMARY KEY  (`id`),
                UNIQUE KEY `operation_item` (`operation_id`, `item_type`, `item_id`, `field`, `change_type`),
                KEY `item` (`item_type`, `item_id`, `id`),
                KEY `item_life` (`item_type`, `item_id`, `change_type`, `id`),
                KEY `changed_time` (`changed_time`, `id`),
                KEY `source` (`source`, `changed_time`),
                KEY `user_id` (`user_id`, `changed_time`),
                KEY `field` (`field`, `changed_time`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");
    }

    /*
     * Writes the start marker (a system operation with the action history_started) unless one is there, inside the
     * caller's transaction. $note_code ties a restore's fresh marker to its restore id ('started:<id>'), so a retried
     * cleanup doesn't write it twice.
     */
    public static function writeStartMarker(\PDO $pdo, $note_code = '')
    {
        $stmt = $pdo->prepare("
            SELECT count(*)
            FROM change_operations
            WHERE source = 'system'
              AND `action` = 'history_started'" . ($note_code !== '' ? "
              AND note_code = :note_code" : ''));
        if ($note_code !== '') $stmt->bindValue('note_code', $note_code, \PDO::PARAM_STR);
        $stmt->execute();
        $exists = (int) $stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        if ($exists) return;

        /*
         * Restore markers already there (written by a restore of an older backup, before its database recorded history)
         * are written again after the start marker, with higher ids, so the start marker is the first operation row, as
         * isAvailable() reads it. Only restore markers are moved, and only when no change rows point at them. Anything
         * else stays where it is.
         */
        $earlier = $pdo->query("
            SELECT *
            FROM change_operations
            ORDER BY id
            LIMIT 101")->fetchAll(\PDO::FETCH_ASSOC);
        $move = $earlier && count($earlier) <= 100;
        foreach ($earlier as $row) {
            if (($row['source'] ?? '') !== self::SOURCE_RESTORE) $move = false;
        }
        if ($move) {
            $ids = implode(',', array_map('intval', array_column($earlier, 'id')));
            $move = (int) $pdo->query("SELECT count(*) FROM change_history WHERE operation_id IN (" . $ids . ")")->fetchColumn() === 0;
        }
        if ($move) $pdo->exec("DELETE FROM change_operations WHERE id IN (" . $ids . ")");

        $stmt = $pdo->prepare("
            INSERT INTO change_operations
            (source, `action`, user_id, username, note, note_code, item_count, insert_time)
            VALUES
            ('system', 'history_started', 0, '', '', :note_code, 0, :insert_time)");
        $stmt->bindValue('note_code', $note_code, \PDO::PARAM_STR);
        $stmt->bindValue('insert_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();

        if ($move) {
            foreach ($earlier as $row) {
                unset($row['id']);
                $columns = array_keys($row);
                foreach ($columns as $column) {
                    if (!preg_match('/^[a-z_]+$/', $column)) throw new \RuntimeException('Unexpected change_operations column');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO change_operations
                    (`" . implode('`, `', $columns) . "`)
                    VALUES
                    (:" . implode(', :', $columns) . ")");
                foreach ($row as $column => $value) {
                    $stmt->bindValue($column, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
                }
                $stmt->execute();
            }
        }
        self::forgetAvailability();
    }

    /*
     * The named lock for an item type's custom fields (GET_LOCK(), keyed by the database name as the queue's named locks
     * are), held by a custom field add or delete from before its first write until after its last ALTER TABLE, by the
     * daily orphan column clean-up, and by the backup download for the whole dump. Named locks belong to the connection,
     * so they survive the commits ALTER TABLE makes.
     */
    public static function customFieldLockName($type)
    {
        return 'domainmod_custom_fields_' . $type . '_' . substr(md5(DB_NAME), 0, 12);
    }

    // Takes an item type's custom field lock, waiting up to $wait seconds, and says whether it got it
    public static function lockCustomFields(\PDO $pdo, $type, $wait = 10)
    {
        $stmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, :wait)");
        $stmt->bindValue('lock_name', self::customFieldLockName($type), \PDO::PARAM_STR);
        $stmt->bindValue('wait', (int) $wait, \PDO::PARAM_INT);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        return $got;
    }

    // Lets go of an item type's custom field lock (a failure, such as a lost connection, is ignored: the lock goes with it)
    public static function unlockCustomFields(\PDO $pdo, $type)
    {
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', self::customFieldLockName($type), \PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
        }
    }

    /*
     * Whether the history already knows an issuer for an SSL certificate in its current life (the rows after the newest
     * delete row for its id): an issuer row, or an issuer in its create snapshot. SSL Refresh's first lookup of a
     * certificate whose history has never had one isn't recorded as a change.
     */
    public static function knowsIssuer(\PDO $pdo, $ssl_id)
    {
        $stmt = $pdo->prepare("
            SELECT change_type, field, snapshot
            FROM change_history
            WHERE item_type = 'ssl'
              AND item_id = :item_id
              AND id > COALESCE((SELECT MAX(d.id)
                                 FROM change_history AS d
                                 WHERE d.item_type = 'ssl'
                                   AND d.item_id = :delete_item_id
                                   AND d.change_type = 'delete'), 0)
              AND (field = 'issuer' OR change_type = 'create')");
        $stmt->bindValue('item_id', (int) $ssl_id, \PDO::PARAM_INT);
        $stmt->bindValue('delete_item_id', (int) $ssl_id, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rows as $row) {
            if ($row['field'] === 'issuer') return true;
            $snapshot = json_decode((string) $row['snapshot'], true);
            if ((string) ($snapshot['issuer']['v'] ?? '') !== '') return true;
        }
        return false;
    }

    /*
     * A fee page's capture branches for ChangeHistoryOperation::captureCosts(): the items its reassignment reaches (the
     * registrar and TLD, the SSL provider and type, or the hosting provider, service type, and billing term in $key_c) and
     * every item on the fee row it edits or adds, whose currency follows that row, as two branches, each read through its
     * own index, rather than one OR, so neither scans the table
     */
    public static function feeScope($type, $key_a, $key_b, $fee_id, $key_c = null)
    {
        if ($type === 'hosting') {
            return array(
                array(" AND i.hosting_id = :fee_scope_a AND i.type_id = :fee_scope_b AND i.billing_term = :fee_scope_c",
                    array('fee_scope_a' => (int) $key_a, 'fee_scope_b' => (int) $key_b, 'fee_scope_c' => (int) $key_c)),
                array(" AND i.fee_id = :fee_scope_fee", array('fee_scope_fee' => (int) $fee_id)));
        }
        $columns = $type === 'ssl' ? array('ssl_provider_id', 'type_id') : array('registrar_id', 'tld');
        return array(
            array(" AND i." . $columns[0] . " = :fee_scope_a AND i." . $columns[1] . " = :fee_scope_b",
                array('fee_scope_a' => $key_a, 'fee_scope_b' => $key_b)),
            array(" AND i.fee_id = :fee_scope_fee", array('fee_scope_fee' => (int) $fee_id)));
    }

    /*
     * A fee page's note for its action (with the note code 'fee'): the fee's registrar and TLD, or SSL provider and type,
     * as the administrator saw them, read from the fee's own keys (and, for hosting, its billing term in $key_c)
     */
    public static function feeNote(\PDO $pdo, $type, $key_a, $key_b, $key_c = null)
    {
        if ($type === 'hosting') {
            $stmt = $pdo->prepare("
                SELECT CONCAT(h.name, ' (', t.type, ', ', :key_c, ')')
                FROM hosting AS h, hosting_types AS t
                WHERE h.id = :key_a
                  AND t.id = :key_b");
            $stmt->bindValue('key_b', (int) $key_b, \PDO::PARAM_INT);
            $stmt->bindValue('key_c', Hosting::termLabel($key_c), \PDO::PARAM_STR);
        } elseif ($type === 'ssl') {
            $stmt = $pdo->prepare("
                SELECT CONCAT(p.name, ' (', t.type, ')')
                FROM ssl_providers AS p, ssl_cert_types AS t
                WHERE p.id = :key_a
                  AND t.id = :key_b");
            $stmt->bindValue('key_b', (int) $key_b, \PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("
                SELECT CONCAT(r.name, ' (.', :key_b, ')')
                FROM registrars AS r
                WHERE r.id = :key_a");
            $stmt->bindValue('key_b', (string) $key_b, \PDO::PARAM_STR);
        }
        $stmt->bindValue('key_a', (int) $key_a, \PDO::PARAM_INT);
        $stmt->execute();
        $note = $stmt->fetchColumn();
        $stmt->closeCursor();
        return (string) ($note === false ? '' : $note);
    }

    /*
     * The time $months months before $now (UTC), with the day kept within the target month, so 2028-02-29 less a year is
     * 2027-02-28, never 2027-03-01 (which would remove a day too many)
     */
    public static function monthsBefore($now, $months)
    {
        $date = new \DateTime($now, new \DateTimeZone('UTC'));
        $day = (int) $date->format('j');
        $target = (clone $date)->modify('first day of -' . (int) $months . ' months');
        $day = min($day, (int) $target->format('t'));
        $target->setDate((int) $target->format('Y'), (int) $target->format('n'), $day);
        return $target->format('Y-m-d H:i:s');
    }

    // The tables a full backup made by $version holds (see BACKUP_TABLES), or null for a version below the first entry
    public static function backupTables($version)
    {
        $tables = null;
        $versions = array_keys(self::BACKUP_TABLES);
        usort($versions, 'version_compare');
        foreach ($versions as $entry) {
            if (version_compare((string) $version, $entry, '>=')) $tables = self::BACKUP_TABLES[$entry];
        }
        return $tables;
    }

} //@formatter:on
