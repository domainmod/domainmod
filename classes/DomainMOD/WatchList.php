<?php
/**
 * /classes/DomainMOD/WatchList.php
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
 * The Watch List: domains you don't own yet, checked in the background with public RDAP and WHOIS (see WatchCheck). It
 * has its own table (watch_domains), so costs, reports, segments, and the expiration email never see these domains.
 * This class holds the list (built like the Domains list), its filters, the reasons, and the user's writes (adding,
 * archiving, unarchiving, deleting, and editing), each recorded in the Change History as item type 'watch' in the same
 * transaction. Every signed-in user sees and changes the same list, as they do Domains.
 */
class WatchList extends ListQuery
{
    // The first database version with the Watch List
    const AVAILABLE_FROM = '4.27.0';
    // Entries that aren't archived, per install
    const MAX_ENTRIES = 1000;
    // Distinct domains per paste, and what a posted list may hold (in characters, and lines) before it's even read
    const MAX_PASTE = 1000;
    const MAX_PASTE_BYTES = 100000;
    const MAX_PASTE_LINES = 5000;
    // How long an add or unarchive waits for another one to finish, in seconds
    const LOCK_WAIT = 10;
    // Rows per export chunk: each row's notes can be long, so a chunk stays small
    const EXPORT_CHUNK = 50;

    // The Check Watch List task's English description, as the database update and the restore's cleanup store it
    const TASK_DESCRIPTION = 'Checks the domains on the Watch List with public RDAP and WHOIS servers, a few at a time: weekly while a domain is far from expiry, daily near and after expiry, and more often once deletion is pending.';

    const REASONS = array('buy', 'backorder', 'catch', 'negotiating', 'watch');
    const DEFAULT_REASON = 'watch';

    // Every order ends with these, so pages stay stable when values tie
    const TIE_BREAK = array('w.domain ASC', 'w.id ASC');

    const ARCHIVED_CHOICES = array('hide', 'show', 'only');
    const DROP_CHOICES = array('7', '30', 'any');

    // Whether the database has the Watch List, and whether it's in use, worked out once per request (see isAvailable()
    // and isEnabled())
    protected static $available = null;
    protected static $enabled = null;

    public function __construct(\PDO $pdo, $user_id)
    {
        // No custom fields: watched domains have none, and ListQuery's constructor would read a custom field join
        $this->pdo = $pdo;
        $this->user_id = (int) $user_id;
        $this->fields = array();
        $this->field_columns = '';
    }

    /*
     * Whether the Watch List can be used: the database is at 4.27.0 or later, so watch_domains is there (code can be
     * deployed before the database update). Worked out once per request. A database that can't be read (such as during
     * the installation) has no Watch List.
     */
    public static function isAvailable()
    {
        if (self::$available === null) {
            try {
                self::$available = version_compare((string) (new System())->getDbVersion(), self::AVAILABLE_FROM, '>=');
            } catch (\Throwable $e) {
                return false;
            }
        }
        return self::$available;
    }

    /*
     * Whether the Watch List is in use: the database has it (isAvailable()) and System Settings' "Use the Watch List" is
     * on (settings.use_watch_list, on by default). Off, its menu item, Dashboard tile, and pages are hidden and the Check
     * Watch List task does nothing, while its entries are kept. A setting that can't be read counts as on, the default.
     * Worked out once per request, unless $fresh asks for the setting as it is now (the task, which can start well
     * after a long cron run read it).
     */
    public static function isEnabled($fresh = false)
    {
        if (!self::isAvailable()) return false;
        if (self::$enabled === null || $fresh) {
            try {
                $stmt = Database::getInstance()->cnxx->query("SELECT use_watch_list FROM settings LIMIT 1");
                $value = $stmt->fetchColumn();
                $stmt->closeCursor();
                self::$enabled = $value === false || (int) $value === 1;
            } catch (\Throwable $e) {
                return true;
            }
        }
        return self::$enabled;
    }

    // Forgets what isAvailable() and isEnabled() worked out (after a restore or a change of the setting, and for tests)
    public static function resetAvailable($value = null, $enabled = null)
    {
        self::$available = $value;
        self::$enabled = $enabled;
    }

    /*
     * What a Watch List page shows while the Watch List is turned off (see isEnabled()): an administrator gets a link to
     * the setting, and everyone else is told to ask one
     */
    public static function offState(Layout $layout, $is_admin)
    {
        if ($is_admin) {
            return $layout->emptyState('bi-binoculars', _('The Watch List is turned off'),
                _('Turn it on in System Settings to see and check your watched domains again. They were kept while it was off.'),
                '', '', '/admin/settings/#domainmod-section-watch-list', _('System Settings'));
        }
        return $layout->emptyState('bi-binoculars', _('The Watch List is turned off'),
            _('Ask an administrator to turn it on in System Settings. Your watched domains were kept while it was off.'));
    }

    /*
     * The table's CREATE TABLE statement, the same as install/go.php's and the database update's (a unit test checks
     * they match), for the restore's cleanup when a restored 4.27.0 database is missing it
     */
    public static function tableSql()
    {
        return "
        CREATE TABLE IF NOT EXISTS `watch_domains` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
            `reason` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'watch',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `archived` TINYINT(1) NOT NULL DEFAULT '0',
            `last_status_read` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `has_baseline` TINYINT(1) NOT NULL DEFAULT '0',
            `moved_domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `stage` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `pending_stage` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `pending_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `pending_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `revision` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `statuses` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `registration_date` DATE NOT NULL DEFAULT '1970-01-01',
            `deletion_date` DATE NOT NULL DEFAULT '1970-01-01',
            `expiry_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `grace_base_expiry` DATE NOT NULL DEFAULT '1970-01-01',
            `grace_base_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `grace_open` TINYINT(1) NOT NULL DEFAULT '0',
            `registrar_name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `registrar_iana_id` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `registrar_url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `stage_since` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `stage_last_seen` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `prev_check` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `drop_from` DATE NOT NULL DEFAULT '1970-01-01',
            `drop_to` DATE NOT NULL DEFAULT '1970-01-01',
            `drop_basis` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_attempt` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_success` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `next_due` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `hold_until` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `failures` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
            `last_source` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_result` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            UNIQUE KEY `domain` (`domain`),
            KEY `next_due` (`next_due`),
            KEY `archived_stage` (`archived`, `stage`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1";
    }

    // The reasons' names
    public static function reasonLabels()
    {
        return array('buy' => _('Want to buy'), 'backorder' => _('Backorder placed'),
            'catch' => _('Try to catch when it drops'), 'negotiating' => _('Negotiating'), 'watch' => _('Just watching'));
    }

    public static function reasonLabel($reason)
    {
        $labels = self::reasonLabels();
        return $labels[$reason] ?? (string) $reason;
    }

    // A posted reason, or the default when it isn't one of the list
    public static function checkedReason($reason)
    {
        return in_array($reason, self::REASONS, true) ? $reason : self::DEFAULT_REASON;
    }

    public function fromSql()
    {
        return "
            FROM watch_domains AS w
            LEFT JOIN users AS u ON u.id = w.created_by
            WHERE 1 = 1";
    }

    public function selectSql()
    {
        return "
            SELECT w.id, w.domain, w.tld, w.reason, w.notes, w.archived, w.last_status_read, w.has_baseline,
                   w.moved_domain_id, w.stage, w.pending_stage, w.statuses, w.expiry_date, w.registration_date,
                   w.registrar_name, w.registrar_iana_id, w.drop_from, w.drop_to, w.drop_basis, w.last_attempt,
                   w.last_success, w.last_source, w.next_due, w.hold_until, w.failures, w.created_by, w.insert_time,
                   CASE WHEN u.id IS NULL THEN '' ELSE CONCAT(u.first_name, ' ', u.last_name) END AS added_by";
    }

    protected function listColumns(array $settings)
    {
        return new WatchListColumns($settings);
    }

    /*
     * The filters from a request, checked: each is its default when it's missing or not one of its allowed values.
     * stage (a stage code), reason, tld, drop (7, 30, or any), added (me), and archived (hide, the default, show, or
     * only).
     */
    public static function normalizeState(array $get)
    {
        $text = function ($key) use ($get) {
            return is_string($get[$key] ?? null) ? trim($get[$key]) : '';
        };
        $tld = mb_strtolower($text('tld'), 'UTF-8');
        return array(
            'stage' => in_array($text('stage'), WatchLifecycle::STAGES, true) ? $text('stage') : '',
            'reason' => in_array($text('reason'), self::REASONS, true) ? $text('reason') : '',
            'tld' => mb_strlen($tld, 'UTF-8') <= 50 && preg_match('/^[^\s]*$/u', $tld) ? ltrim($tld, '.') : '',
            'drop' => in_array($text('drop'), self::DROP_CHOICES, true) ? $text('drop') : '',
            'added' => $text('added') === 'me' ? 'me' : '',
            'archived' => in_array($text('archived'), self::ARCHIVED_CHOICES, true) ? $text('archived') : 'hide');
    }

    // The filters as SQL and bound values (array('sql', 'params')), with $today as the drop filters' first day (Y-m-d)
    public function filterParts(array $state, $today = null)
    {
        $today = $today ?? gmdate('Y-m-d');
        $sql = '';
        $params = array();

        if ($state['archived'] === 'hide') $sql .= " AND w.archived = 0";
        if ($state['archived'] === 'only') $sql .= " AND w.archived = 1";
        if ($state['stage'] !== '') {
            $sql .= " AND w.stage = :f_stage";
            $params['f_stage'] = $state['stage'];
        }
        if ($state['reason'] !== '') {
            $sql .= " AND w.reason = :f_reason";
            $params['f_reason'] = $state['reason'];
        }
        if ($state['tld'] !== '') {
            // As stored (Unicode), or as typed when it doesn't normalise
            $sql .= " AND w.tld = :f_tld";
            $params['f_tld'] = Domain::normalizeTld($state['tld']);
        }
        if ($state['drop'] !== '') {
            $sql .= " AND w.drop_basis <> ''";
            if ($state['drop'] !== 'any') {
                // A window that overlaps the next 7 (or 30) days
                $end = (new \DateTimeImmutable($today . ' 00:00:00', new \DateTimeZone('UTC')))->modify('+' . (int) $state['drop'] . ' days');
                $sql .= " AND w.drop_from <= :f_drop_end AND w.drop_to >= :f_drop_start";
                $params['f_drop_end'] = $end->format('Y-m-d');
                $params['f_drop_start'] = $today;
            }
        }
        if ($state['added'] === 'me') {
            $sql .= " AND w.created_by = :f_added";
            $params['f_added'] = $this->user_id;
        }

        return array('sql' => $sql, 'params' => $params);
    }

    /*
     * The export's rows, by domain, a chunk at a time (keyset), as a generator of chunks, each a generator of rows. The
     * inherited one expects a custom field data table, which watched domains don't have. It never starts or ends a
     * transaction: the export runs it inside beginSnapshot() and endSnapshot().
     */
    public function exportChunks($filter_sql, array $params, $keys_per_chunk = null)
    {
        $limit = max(1, (int) ($keys_per_chunk ?? self::EXPORT_CHUNK));
        $last = null;
        while (true) {
            $stmt = $this->pdo->prepare($this->selectSql() . $this->fromSql() . $filter_sql
                . ($last !== null ? " AND w.domain > :ks_domain" : '') . " ORDER BY w.domain LIMIT " . $limit);
            $stmt->execute($params + ($last !== null ? array('ks_domain' => $last) : array()));
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();
            if (!$rows) break;
            $last = (string) end($rows)->domain;
            yield (function () use ($rows) {
                foreach ($rows as $row) yield $row;
            })();
            if (count($rows) < $limit) break;
        }
    }

    // The TLDs on the list (for the TLD filter), as stored
    public function tlds()
    {
        return $this->pdo->query("
            SELECT DISTINCT tld
            FROM watch_domains
            ORDER BY tld")->fetchAll(\PDO::FETCH_COLUMN);
    }

    // Whether the list has any entry at all (archived ones included)
    public function hasEntries()
    {
        return $this->pdo->query("SELECT id FROM watch_domains LIMIT 1")->fetchColumn() !== false;
    }

    // One entry, or null
    public function entry($id)
    {
        $stmt = $this->pdo->prepare($this->selectSql() . ", w.registrar_url, w.deletion_date, w.stage_since,
                   w.expiry_source, w.last_result, w.last_status_read, w.pending_since, w.revision"
            . $this->fromSql() . " AND w.id = :id");
        $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row ?: null;
    }

    // The id of the domain in Domains with this name and a live status (not Expired or Sold), or 0
    public static function liveDomainId(\PDO $pdo, $domain, $lock = false)
    {
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain
              AND active NOT IN ('0', '10')
            ORDER BY id
            LIMIT 1" . ($lock ? "
            LOCK IN SHARE MODE" : ''));
        $stmt->bindValue('domain', (string) $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $id === false ? 0 : (int) $id;
    }

    /*
     * The domain's public RDAP record, from the cached copy of IANA's bootstrap file only (an edit page never downloads
     * it), or '' when the cache has no server for its TLD
     */
    public static function rdapRecordUrl($domain)
    {
        $data = is_readable(DIR_TEMP . '/rdap-dns.json') ? json_decode((string) @file_get_contents(DIR_TEMP . '/rdap-dns.json'), true, 16) : null;
        if (!is_array($data) || !is_array($data['services'] ?? null)) return '';
        $ascii = strtolower(Domain::forApi((string) $domain));
        $labels = explode('.', $ascii);
        $servers = array();
        foreach ($data['services'] as $service) {
            if (!is_array($service) || !is_array($service[0] ?? null) || !is_array($service[1] ?? null)) continue;
            foreach ($service[1] as $url) {
                if (!is_string($url) || stripos($url, 'https://') !== 0) continue;
                foreach ($service[0] as $tld) {
                    if (is_string($tld) && $tld !== '') $servers[strtolower($tld)] = rtrim($url, '/') . '/';
                }
                break;
            }
        }
        // The longest matching suffix wins, as Rdap::serverFor() picks it
        for ($i = 1; $i < count($labels); $i++) {
            $suffix = implode('.', array_slice($labels, $i));
            if (isset($servers[$suffix])) return $servers[$suffix] . 'domain/' . rawurlencode($ascii);
        }
        return '';
    }

    // The domain in Domains with this name (any status), as array(id, active), or null
    public static function domainRow(\PDO $pdo, $domain)
    {
        $stmt = $pdo->prepare("
            SELECT id, active
            FROM domains
            WHERE domain = :domain
            ORDER BY id
            LIMIT 1");
        $stmt->bindValue('domain', (string) $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        $stmt->closeCursor();
        return $row ? array((int) $row[0], (string) $row[1]) : null;
    }

    /*
     * A posted list checked before anything is added, as array('error' => a message or '', 'names' => line => stored
     * name, 'invalid' => the lines that aren't valid (numbered from 1), 'invalid_count'). A list over MAX_PASTE_BYTES or
     * MAX_PASTE_LINES is refused before it's read. Each line is cleaned as the Import Queue's paste page cleans it, and
     * a name is invalid when its format is wrong, its ASCII form can't be looked up (a label or the whole name too long
     * once converted), or its TLD is too long for the tld column. Duplicates are dropped.
     */
    public function checkPaste($raw)
    {
        $raw = (string) $raw;
        $result = array('error' => '', 'names' => array(), 'invalid' => array(), 'invalid_count' => 0);

        // Characters, counted only once the bytes can't be far past the limit (a character is at most four bytes)
        if (strlen($raw) > 4 * self::MAX_PASTE_BYTES || mb_strlen($raw, 'UTF-8') > self::MAX_PASTE_BYTES
            || substr_count($raw, "\n") + 1 > self::MAX_PASTE_LINES) {
            $result['error'] = sprintf(_('The list is too long. Please paste up to %s domains at a time.'), number_format(self::MAX_PASTE));
            return $result;
        }

        $format = new Format();
        $sanitize = new Sanitize();
        $lines = trim($raw) === '' ? array() : array_filter($format->cleanAndSplitDomains($sanitize->text($raw)), 'strlen');
        if (!$lines) {
            $result['error'] = _('Enter the domains to watch');
            return $result;
        }
        $domain = new Domain();
        $rdap = new Rdap();

        foreach ($lines as $key => $name) {
            $tld = Domain::tldOf($name);
            if (!$domain->checkFormat($name) || !$rdap->isHostname(Domain::forApi($name)) || mb_strlen($tld, 'UTF-8') > 50) {
                $result['invalid_count']++;
                if (count($result['invalid']) < 5) $result['invalid'][] = (int) $key + 1;
                continue;
            }
            $result['names'][(int) $key] = $name;
        }

        if (count($result['names']) > self::MAX_PASTE) {
            $result['error'] = sprintf(_('You can add up to %s domains at a time. Please split your list and add it in parts.'), number_format(self::MAX_PASTE));
        }
        return $result;
    }

    /*
     * Adds a checked list of names (from checkPaste()) with a reason and notes, as one Change History action
     * (watch_add): a create row for each new entry, and for an archived entry an unarchive (with the new reason, and the
     * new notes added below its own). Entries already on the list are skipped. Nothing changes when any name is live in
     * Domains, or the entries that would be watched go over MAX_ENTRIES. Returns array('ok', 'added', 'unarchived',
     * 'skipped', 'live' => the live names, 'room' => slots left when the cap refused it, 'busy').
     */
    public function add(array $names, $reason, $notes, $user_id)
    {
        $names = array_values(array_unique(array_map('strval', $names)));
        $reason = self::checkedReason($reason);
        $notes = (string) $notes;
        $result = array('ok' => false, 'added' => 0, 'unarchived' => 0, 'skipped' => 0, 'live' => array(), 'room' => -1,
            'busy' => false);
        if (!$names) return $result;

        if (!$this->lockCap()) {
            $result['busy'] = true;
            return $result;
        }

        try {
            /*
             * The entries already there: found by name before the transaction starts (so this plain read opens no
             * snapshot in it), then locked inside it through the primary key in ascending id order (lockRows()), as
             * every writer locks them, so a bulk action on the same entries can't deadlock with it (a locking read by
             * name would take its locks in the domain index's order). Only an add inserts entries, and every add holds
             * the cap lock, so none with one of these names can be added in between, and lockRows() reads each one as
             * it is when it's locked.
             */
            $ids = array();
            foreach (array_chunk($names, self::MAX_PASTE) as $chunk) {
                $stmt = $this->pdo->prepare("
                    SELECT id
                    FROM watch_domains
                    WHERE domain IN (" . implode(', ', array_fill(0, count($chunk), '?')) . ")");
                $stmt->execute($chunk);
                $ids = array_merge($ids, array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
                $stmt->closeCursor();
            }
            sort($ids);

            // REPEATABLE READ, so the shared read of domains locks a name that isn't there yet, even on a server whose
            // default is READ COMMITTED
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();
            try {
                $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, 'watch_add');

                $existing = array();
                foreach ($this->lockRows($ids) as $row) $existing[(string) $row['domain']] = $row;

                $result['live'] = $this->liveNames($names);
                if ($result['live']) {
                    $this->pdo->rollBack();
                    return $result;
                }

                $new = array();
                $unarchive = array();
                foreach ($names as $name) {
                    if (!isset($existing[$name])) {
                        $new[] = $name;
                    } elseif ((int) $existing[$name]['archived'] === 1) {
                        $unarchive[] = (int) $existing[$name]['id'];
                    } else {
                        $result['skipped']++;
                    }
                }

                $active = $this->activeCount();
                if ($active + count($new) + count($unarchive) > self::MAX_ENTRIES) {
                    $result['room'] = max(0, self::MAX_ENTRIES - $active);
                    $this->pdo->rollBack();
                    return $result;
                }

                $now = gmdate('Y-m-d H:i:s');
                $stmt = $this->pdo->prepare("
                    INSERT INTO watch_domains
                    (domain, tld, reason, notes, created_by, next_due, insert_time, update_time)
                    VALUES
                    (:domain, :tld, :reason, :notes, :created_by, :next_due, :insert_time, :update_time)");
                foreach ($new as $name) {
                    $stmt->bindValue('domain', $name, \PDO::PARAM_STR);
                    $stmt->bindValue('tld', Domain::tldOf($name), \PDO::PARAM_STR);
                    $stmt->bindValue('reason', $reason, \PDO::PARAM_STR);
                    $stmt->bindValue('notes', $notes, \PDO::PARAM_STR);
                    $stmt->bindValue('created_by', (int) $user_id, \PDO::PARAM_INT);
                    $stmt->bindValue('next_due', $now, \PDO::PARAM_STR);
                    $stmt->bindValue('insert_time', $now, \PDO::PARAM_STR);
                    $stmt->bindValue('update_time', $now, \PDO::PARAM_STR);
                    $stmt->execute();
                    $op->created('watch', (int) $this->pdo->lastInsertId());
                }

                if ($unarchive) {
                    // Captured in the order they were pasted, so the history lists them that way (their locks were taken
                    // above, in ascending id order)
                    $before = $op->capture('watch', $unarchive, array('reason', 'notes', 'archived'));
                    $this->unarchiveRows($unarchive, $reason, $notes);
                    $op->compare($before);
                }

                $op->finish();
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;
            }
        } finally {
            $this->unlockCap();
        }

        $result['ok'] = true;
        $result['added'] = count($new);
        $result['unarchived'] = count($unarchive);
        return $result;
    }

    /*
     * What an add() or unarchive() result says, in plain text: why nothing changed (another change being saved, names
     * live in Domains, or the cap), or what changed
     */
    public static function resultMessage(array $result)
    {
        if (!empty($result['busy'])) return _('Another change to the Watch List is being saved. Please try again in a moment.');
        if (!empty($result['live'])) {
            $shown = array_slice($result['live'], 0, 5);
            // TRANSLATORS: %1$s is the software's name, DomainMOD, and %2$s a list of domains
            $message = sprintf(ngettext('You already have this domain in %1$s: %2$s.', 'You already have these domains in %1$s: %2$s.',
                count($result['live'])), SOFTWARE_TITLE, implode(', ', array_map(function ($name) {
                return (string) (new Unsanitize())->text($name);
            }, $shown)));
            $others = count($result['live']) - count($shown);
            if ($others > 0) $message .= ' ' . sprintf(ngettext('Plus %s other', 'Plus %s others', $others), number_format($others));
            return $message;
        }
        if (($result['room'] ?? -1) >= 0) {
            return sprintf(ngettext('The Watch List holds up to %1$s domains, and has room for %2$s more.',
                'The Watch List holds up to %1$s domains, and has room for %2$s more.', $result['room']),
                number_format(self::MAX_ENTRIES), number_format($result['room']));
        }

        $parts = array();
        if (!empty($result['added'])) {
            $parts[] = sprintf(ngettext('%s domain added to the Watch List.', '%s domains added to the Watch List.', $result['added']),
                number_format($result['added']));
        }
        if (!empty($result['unarchived'])) {
            $parts[] = sprintf(ngettext('%s archived domain is being watched again.', '%s archived domains are being watched again.',
                $result['unarchived']), number_format($result['unarchived']));
        }
        if (!empty($result['added']) || !empty($result['unarchived'])) $parts[] = _('Checks begin on the next scheduled run.');
        if (!empty($result['skipped'])) {
            $parts[] = sprintf(ngettext('%s domain was already on the Watch List.', '%s domains were already on the Watch List.',
                $result['skipped']), number_format($result['skipped']));
        }
        if (!$parts) $parts[] = _('None of the selected domains were archived, so nothing changed.');
        return implode(' ', $parts);
    }

    // A fingerprint of an entry's reason and notes as stored, which the edit page's form carries (see saveDetails())
    public static function detailsHash($reason, $notes)
    {
        return hash('sha256', (string) $reason . "\0" . (string) $notes);
    }

    /*
     * Saves an entry's details (its reason and notes) from the edit page, as one action (watch_edit). $loaded is
     * detailsHash() of the details the form was drawn with: when they've changed since (another user's save, or a paste
     * that unarchived the entry and added notes), nothing is saved, so those changes aren't overwritten. Returns saved,
     * changed, or missing (the entry isn't there).
     */
    public function saveDetails($id, $reason, $notes, $user_id, $loaded = null)
    {
        $outcome = 'saved';
        $found = $this->write(array((int) $id), $user_id, 'watch_edit', array('reason', 'notes'), function (array $ids) use ($reason, $notes, $loaded, &$outcome) {
            if ($loaded !== null) {
                $stmt = $this->pdo->prepare("SELECT reason, notes FROM watch_domains WHERE id = :id FOR UPDATE");
                $stmt->bindValue('id', $ids[0], \PDO::PARAM_INT);
                $stmt->execute();
                $current = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                if (!$current || !hash_equals(self::detailsHash($current['reason'], $current['notes']), (string) $loaded)) {
                    $outcome = 'changed';
                    return;
                }
            }
            $stmt = $this->pdo->prepare("
                UPDATE watch_domains
                SET reason = :reason,
                    notes = :notes,
                    revision = revision + 1,
                    update_time = :update_time
                WHERE id = :id");
            $stmt->bindValue('reason', self::checkedReason($reason), \PDO::PARAM_STR);
            $stmt->bindValue('notes', (string) $notes, \PDO::PARAM_STR);
            $stmt->bindValue('update_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->bindValue('id', $ids[0], \PDO::PARAM_INT);
            $stmt->execute();
        });
        return $found > 0 ? $outcome : 'missing';
    }

    // Sets the reason of several entries (the bulk action), as one action. Returns how many entries it changed.
    public function setReason(array $ids, $reason, $user_id)
    {
        $changed = 0;
        $this->write($ids, $user_id, 'watch_bulk_reason', array('reason'), function (array $ids) use ($reason, &$changed) {
            $stmt = $this->pdo->prepare("
                UPDATE watch_domains
                SET reason = :reason,
                    revision = revision + 1,
                    update_time = :update_time
                WHERE id IN (" . implode(',', $ids) . ")
                  AND reason <> :old_reason");
            $stmt->bindValue('reason', self::checkedReason($reason), \PDO::PARAM_STR);
            $stmt->bindValue('update_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->bindValue('old_reason', self::checkedReason($reason), \PDO::PARAM_STR);
            $stmt->execute();
            $changed = $stmt->rowCount();
        });
        return $changed;
    }

    /*
     * Archives entries (watching stops, and the entry is kept for the record), as one action (watch_archive from the
     * edit page, or watch_bulk_archive). Any pending confirmation and open grace comparison are cleared without being
     * recorded. Returns how many entries it archived.
     */
    public function archive(array $ids, $user_id, $action = 'watch_archive')
    {
        $archived = 0;
        $this->write($ids, $user_id, $action, array('archived'), function (array $ids) use (&$archived) {
            $archived = (int) $this->pdo->exec("
                UPDATE watch_domains
                SET archived = 1,
                    pending_stage = '',
                    pending_source = '',
                    pending_since = '1970-01-01 00:00:00',
                    grace_open = 0,
                    grace_base_expiry = '1970-01-01',
                    grace_base_source = '',
                    revision = revision + 1,
                    update_time = " . $this->pdo->quote(gmdate('Y-m-d H:i:s')) . "
                WHERE id IN (" . implode(',', $ids) . ")
                  AND archived = 0");
        });
        return $archived;
    }

    /*
     * Unarchives entries (the edit page, or the bulk action), as one action, under the same lock and checks as adding:
     * nothing changes when any of them is live in Domains or the cap would be passed. Returns array('ok', 'unarchived',
     * 'live' => the live names, 'room' => slots left when the cap refused it, 'busy'), as add() does.
     */
    public function unarchive(array $ids, $user_id, $action = 'watch_unarchive')
    {
        $ids = $this->checkedIds($ids);
        $result = array('ok' => false, 'unarchived' => 0, 'live' => array(), 'room' => -1, 'busy' => false);
        if (!$ids) return $result;

        if (!$this->lockCap()) {
            $result['busy'] = true;
            return $result;
        }

        try {
            // REPEATABLE READ, so the shared read of domains locks a name that isn't there yet, even on a server whose
            // default is READ COMMITTED
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->pdo->beginTransaction();
            try {
                $rows = $this->lockRows($ids);
                $archived = array();
                foreach ($rows as $row) {
                    if ((int) $row['archived'] === 1) $archived[(int) $row['id']] = (string) $row['domain'];
                }
                $result['live'] = $this->liveNames(array_values($archived));
                if ($result['live']) {
                    $this->pdo->rollBack();
                    return $result;
                }
                $active = $this->activeCount();
                if ($active + count($archived) > self::MAX_ENTRIES) {
                    $result['room'] = max(0, self::MAX_ENTRIES - $active);
                    $this->pdo->rollBack();
                    return $result;
                }
                if ($archived) {
                    $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, $action);
                    $before = $op->capture('watch', array_keys($archived), array('archived'));
                    $this->unarchiveRows(array_keys($archived), null, '');
                    $op->compare($before);
                    $op->finish();
                }
                $this->pdo->commit();
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $e;
            }
        } finally {
            $this->unlockCap();
        }

        $result['ok'] = true;
        $result['unarchived'] = count($archived);
        return $result;
    }

    // Deletes entries, as one action (watch_delete or watch_bulk_delete), each with a delete row and its snapshot
    public function delete(array $ids, $user_id, $action = 'watch_delete')
    {
        $ids = $this->checkedIds($ids);
        if (!$ids) return 0;

        $this->pdo->beginTransaction();
        try {
            $found = array_map('intval', array_column($this->lockRows($ids), 'id'));
            if ($found) {
                $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, $action);
                $op->deleting('watch', $found);
                $this->pdo->exec("DELETE FROM watch_domains WHERE id IN (" . implode(',', $found) . ")");
                $op->finish();
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return count($found);
    }

    /*
     * Unarchiving starts a new watching period: everything learned in the earlier one is cleared (not recorded, since
     * it's the start of a period), and the entry is due for a check straight away. With $reason (a paste), the reason is
     * set and $notes are added below the entry's own notes. Runs inside the caller's transaction, on locked rows.
     */
    protected function unarchiveRows(array $ids, $reason, $notes)
    {
        $now = gmdate('Y-m-d H:i:s');
        $set_reason = $reason !== null ? "reason = :reason,
                    notes = CASE WHEN :notes = '' THEN notes WHEN notes = '' THEN :notes_only ELSE CONCAT(notes, '\r\n', :notes_added) END," : '';
        $stmt = $this->pdo->prepare("
            UPDATE watch_domains
            SET " . $set_reason . "
                archived = 0,
                pending_stage = '',
                pending_source = '',
                pending_since = '1970-01-01 00:00:00',
                grace_open = 0,
                grace_base_expiry = '1970-01-01',
                grace_base_source = '',
                expiry_date = '1970-01-01',
                registration_date = '1970-01-01',
                deletion_date = '1970-01-01',
                expiry_source = '',
                registrar_name = '',
                registrar_iana_id = '',
                registrar_url = '',
                statuses = '',
                stage = 'unknown',
                stage_since = '1970-01-01 00:00:00',
                stage_last_seen = '1970-01-01 00:00:00',
                prev_check = '1970-01-01 00:00:00',
                drop_from = '1970-01-01',
                drop_to = '1970-01-01',
                drop_basis = '',
                last_status_read = '',
                last_attempt = '1970-01-01 00:00:00',
                last_source = '',
                last_result = '',
                failures = 0,
                last_success = '1970-01-01 00:00:00',
                moved_domain_id = 0,
                has_baseline = 0,
                hold_until = '1970-01-01 00:00:00',
                next_due = :next_due,
                revision = revision + 1,
                update_time = :update_time
            WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")
              AND archived = 1");
        if ($reason !== null) {
            $stmt->bindValue('reason', self::checkedReason($reason), \PDO::PARAM_STR);
            $stmt->bindValue('notes', (string) $notes, \PDO::PARAM_STR);
            $stmt->bindValue('notes_only', (string) $notes, \PDO::PARAM_STR);
            $stmt->bindValue('notes_added', (string) $notes, \PDO::PARAM_STR);
        }
        $stmt->bindValue('next_due', $now, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $now, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * One recorded write to existing entries: begin, lock the rows (ascending id), start the action, capture $fields,
     * run $update on the ids found, compare, finish, and commit. Returns how many entries it found.
     */
    protected function write(array $ids, $user_id, $action, array $fields, callable $update)
    {
        $ids = $this->checkedIds($ids);
        if (!$ids) return 0;

        $this->pdo->beginTransaction();
        try {
            $found = array_map('intval', array_column($this->lockRows($ids), 'id'));
            if ($found) {
                $op = (new ChangeHistory($this->pdo))->start(ChangeHistory::SOURCE_WATCH_LIST, $user_id, $action);
                $before = $op->capture('watch', $found, $fields);
                $update($found);
                $op->compare($before);
                $op->finish();
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return count($found);
    }

    // Whole, positive, distinct ids in ascending order (the order every write locks rows in)
    protected function checkedIds(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        sort($ids);
        return $ids;
    }

    // Locks the entries (FOR UPDATE, ascending id) and returns the ones there, with their names and archived flags
    protected function lockRows(array $ids)
    {
        $rows = array();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $stmt = $this->pdo->query("
                SELECT id, domain, archived
                FROM watch_domains
                WHERE id IN (" . implode(',', array_map('intval', $chunk)) . ")
                ORDER BY id
                FOR UPDATE");
            $rows = array_merge($rows, $stmt->fetchAll(\PDO::FETCH_ASSOC));
            $stmt->closeCursor();
        }
        return $rows;
    }

    // How many entries aren't archived
    protected function activeCount()
    {
        return (int) $this->pdo->query("SELECT count(*) FROM watch_domains WHERE archived = 0")->fetchColumn();
    }

    // The names that are live in Domains (any status but Expired and Sold), read with a shared lock
    protected function liveNames(array $names)
    {
        $live = array();
        foreach (array_chunk(array_values($names), 500) as $chunk) {
            if (!$chunk) continue;
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT domain
                FROM domains
                WHERE domain IN (" . implode(', ', array_fill(0, count($chunk), '?')) . ")
                  AND active NOT IN ('0', '10')
                LOCK IN SHARE MODE");
            $stmt->execute($chunk);
            $live = array_merge($live, $stmt->fetchAll(\PDO::FETCH_COLUMN));
            $stmt->closeCursor();
        }
        sort($live);
        return $live;
    }

    /*
     * The named lock every write that makes entries active again holds (adding, and unarchiving from the edit page or
     * the bulk action), from before its transaction until after its commit or rollback, so two of them can't both use
     * the last slots under the cap. Its name includes a hash of the database name, as the task locks' names do.
     */
    public static function capLockName()
    {
        return 'domainmod_watch_list_' . substr(md5(DB_NAME), 0, 12);
    }

    protected function lockCap()
    {
        $stmt = $this->pdo->prepare("SELECT GET_LOCK(:lock_name, :wait)");
        $stmt->bindValue('lock_name', self::capLockName(), \PDO::PARAM_STR);
        $stmt->bindValue('wait', self::LOCK_WAIT, \PDO::PARAM_INT);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        return $got;
    }

    protected function unlockCap()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT RELEASE_LOCK(:lock_name)");
            $stmt->bindValue('lock_name', self::capLockName(), \PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();
        } catch (\Throwable $e) {
            // It goes with the connection
        }
    }

} //@formatter:on
