<?php
/**
 * /classes/DomainMOD/LiveDns.php
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
 * Live DNS: the name servers, addresses, and mail servers a domain really answers with, looked up through the server's
 * own resolver (dns_get_record()) at the end of a Refresh, and compared with the domain's DNS profile and IP Address
 * asset. It only reports: nothing here changes a domain, a profile, or an asset.
 *
 * Six record sets are looked up: NS, A, and AAAA for the bare domain, A and AAAA for its www name, and MX. Each set in
 * one check is complete (its query answered in time, with records or with none, and every record passed cleaning) or
 * not. Only a complete set replaces the stored one, so the stored row (domain_dns_check) always holds the last complete
 * value of each set, and the comparisons and the list badge are always worked out from it.
 *
 * The lookup (lookup()), the merge of one check into the stored row (merge()), the comparison (compare()), the badge
 * (listState()), and the filter SQL (filterCondition()) are all here. The pure parts take plain arrays, so they're
 * tested without a database or a resolver.
 */
class LiveDns
{
    // The DNS step's own limit, from its start, and the time a query needs left before it starts (PHP's resolver has
    // no per-call timeout, so a query is only started with time for the resolver's own timeout)
    const DNS_DEADLINE = 15;
    const MIN_QUERY_TIME = 5;

    // The most entries kept per set, and the most the edit page shows
    const SET_CAP = 100;
    const SHOW_CAP = 20;

    // The six sets, in the order they're stored and listed
    const SETS = array('ns', 'a', 'aaaa', 'www_a', 'www_aaaa', 'mx');

    // The query groups, most useful first. A pair always runs together.
    const GROUPS = array(
        'ns' => array('ns'),
        'bare' => array('a', 'aaaa'),
        'www' => array('www_a', 'www_aaaa'),
        'mx' => array('mx'),
    );

    // The badges, in the list's sort order (a domain with no row, Not checked, sorts first)
    const LIST_STATES = array('unchecked', 'failed', 'no_dns', 'both', 'ns', 'ip', 'no_addresses', 'not_set',
        'incomplete', 'ok');

    // The Domains list's columns for the badge and its suffix (see DomainList::selectSql()), under ldc1_ aliases, since
    // custom field names can only hold letters and underscores
    const LIST_SELECT = ', ldc.list_state AS ldc1_list_state, ldc.result AS ldc1_result, ldc.stale_sets AS ldc1_stale_sets';

    // The Live DNS column's sort: each badge's place in LIST_STATES, with no row (Not checked) first
    const LIST_SORT = "CASE WHEN ldc.list_state IS NULL THEN 0 WHEN ldc.list_state = 'failed' THEN 1 WHEN ldc.list_state = 'no_dns' THEN 2 WHEN ldc.list_state = 'both' THEN 3 WHEN ldc.list_state = 'ns' THEN 4 WHEN ldc.list_state = 'ip' THEN 5 WHEN ldc.list_state = 'no_addresses' THEN 6 WHEN ldc.list_state = 'not_set' THEN 7 WHEN ldc.list_state = 'incomplete' THEN 8 ELSE 9 END";

    // The Domains filter's values and the badges each one matches ('unchecked' is the domain having no row)
    const FILTERS = array(
        'any' => array('both', 'ns', 'ip'),
        'ns' => array('both', 'ns'),
        'ip' => array('both', 'ip'),
        'nodns' => array('no_dns'),
        'failed' => array('failed'),
        'unchecked' => array(),
    );

    // The stored row's columns for each set
    const COLUMNS = array('ns' => 'name_servers', 'a' => 'a', 'aaaa' => 'aaaa', 'www_a' => 'www_a',
        'www_aaaa' => 'www_aaaa', 'mx' => 'mx');

    const UNSET_TIME = '1970-01-01 00:00:00';

    // The longest host name
    const MAX_NAME = 253;

    // The database version that adds domain_dns_check and user_settings.display_domain_live_dns (see isAvailable())
    const AVAILABLE_FROM = '4.27.0';

    // The domain_dns_check columns this version creates (see isAvailable() and the restore's clean-up)
    const TABLE_COLUMNS = array('domain_id', 'domain', 'checked_time', 'started_us', 'result', 'ns_state', 'ip_state',
        'list_state', 'name_servers', 'a', 'aaaa', 'www_a', 'www_aaaa', 'mx', 'seen_sets', 'stale_sets', 'truncated_sets',
        'retry_sets', 'last_ok_time', 'first_set', 'ip_basis', 'history_ns', 'history_ips', 'history_seen');

    /*
     * Starts in microseconds (started_us) are kept as decimal strings, never PHP integers: on 32-bit PHP (PHP_INT_SIZE 4,
     * such as the Docker image's armhf build) they're too large for an int. These helpers build, compare, and split
     * them.
     */

    // The current time in microseconds, from microtime()'s exact string form (seconds and the fraction separately)
    public static function nowUs()
    {
        $now = explode(' ', microtime());
        return self::usFrom((string) $now[1], (int) min(999999, (int) round((float) $now[0] * 1000000)));
    }

    // Whole seconds (a timestamp, as a string or int) and microseconds as one start
    public static function usFrom($seconds, $microseconds = 0)
    {
        return self::usNormal((string) $seconds . sprintf('%06d', (int) $microseconds));
    }

    // A start as a plain decimal string: digits only, no leading zeros, '0' for none or anything else. A float (what a
    // large integer becomes on 32-bit PHP) is written out in full, exact up to 2^53, far past any start in microseconds.
    public static function usNormal($us)
    {
        if (is_float($us)) $us = ($us >= 0 && $us < 9007199254740992) ? sprintf('%.0f', floor($us)) : '0';
        $us = ltrim(trim((string) $us), '0');
        return ($us === '' || !ctype_digit($us)) ? '0' : $us;
    }

    // Compares two starts as numbers (-1, 0, or 1) without turning them into PHP integers
    public static function usCompare($a, $b)
    {
        $a = self::usNormal($a);
        $b = self::usNormal($b);
        return (strlen($a) <=> strlen($b)) ?: (strcmp($a, $b) <=> 0);
    }

    // A start's whole seconds, as a timestamp (which fits in an int on 32-bit PHP until 2038, as every timestamp does)
    public static function usSeconds($us)
    {
        $us = self::usNormal($us);
        return strlen($us) > 6 ? (int) substr($us, 0, -6) : 0;
    }

    // Whether the table and column are there, by connection, worked out once per request (see isAvailable())
    protected static $available = array();

    // Tests only: a callable($name, $type) standing in for dns_get_record(), given the absolute name (with its root
    // dot) and 'NS', 'A', 'AAAA', or 'MX', and returning an array of records or false, as dns_get_record() does
    public static $test_lookup = null;

    // Tests only: a callable returning the current time (as microtime(true) does), so a test can make queries slow
    public static $test_clock = null;

    protected static $rdap = null;

    /*
     * Whether the database has Live DNS: version AVAILABLE_FROM or later, domain_dns_check with every column this
     * version creates, and user_settings.display_domain_live_dns (a restore that stopped part-way can leave an older
     * table under a 4.27.0 version). Worked out once per request for each connection, as DomainStatus::isAvailable()
     * is, and false if it can't be read. Until it's true, nothing reads or writes the table.
     */
    public static function isAvailable($pdo = null)
    {
        // Without a connection given, the shared one (a connection that fails isn't available)
        try {
            $pdo = $pdo ?? Database::getInstance()->cnxx;
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_object($pdo)) return false;

        $key = spl_object_id($pdo);
        if (isset(self::$available[$key])) return self::$available[$key];

        try {

            $version = (string) $pdo->query("
                SELECT db_version
                FROM settings")->fetchColumn();

            $available = $version !== '' && version_compare($version, self::AVAILABLE_FROM, '>=');

            if ($available) {

                $columns = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM `INFORMATION_SCHEMA`.`COLUMNS`
                    WHERE `TABLE_SCHEMA` = DATABASE()
                      AND ((`TABLE_NAME` = 'domain_dns_check'
                            AND `COLUMN_NAME` IN ('" . implode("', '", self::TABLE_COLUMNS) . "'))
                        OR (`TABLE_NAME` = 'user_settings' AND `COLUMN_NAME` = 'display_domain_live_dns'))")->fetchColumn();
                $available = $columns === count(self::TABLE_COLUMNS) + 1;

            }

        } catch (\Throwable $e) {

            $available = false;

        }

        return self::$available[$key] = $available;
    }

    // Forgets what isAvailable() worked out (after the database update or a restore, and for tests)
    public static function resetAvailable()
    {
        self::$available = array();
    }

    // The table, as install/go.php and the database update create it (and the restore's clean-up, when missing)
    public static function tableSql()
    {
        return "
        CREATE TABLE IF NOT EXISTS `domain_dns_check` (
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `checked_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `started_us` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
            `result` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `ns_state` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `ip_state` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `list_state` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'failed',
            `name_servers` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `a` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `aaaa` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `www_a` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `www_aaaa` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `mx` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `seen_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `stale_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `truncated_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `retry_sets` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `last_ok_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `first_set` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `ip_basis` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            `history_ns` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `history_ips` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `history_seen` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
            PRIMARY KEY  (`domain_id`),
            KEY `list_state` (`list_state`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    }

    /*
     * Looks up one domain's live DNS. $domain is the name in its stored form (domains.domain), $stored the stored row
     * for that same name (an array of domain_dns_check's columns), or null when there's none, and $deadline the
     * absolute time (microtime(true)) the refresh's own per-domain deadline ends. The step's own cutoff is the earlier
     * of DNS_DEADLINE seconds from now and $deadline, and each query only starts with MIN_QUERY_TIME seconds left
     * before it.
     *
     * Returns null when fewer than MIN_QUERY_TIME seconds are left (the step doesn't start), otherwise an observation:
     * result (see resultCode()), first_set (the set asked first, or '' when nothing was asked), and sets, keyed by
     * set name, each with complete (answered strictly before the cutoff and cleaned), answered (complete from a real
     * answer, rather than a www set no name can have), lines (the cleaned entries, with an address set's 'cname:'
     * line first), and truncated (more than SET_CAP entries came back).
     */
    public static function lookup($domain, $stored, $deadline)
    {
        $start = self::now();
        if ($deadline - $start < self::MIN_QUERY_TIME) return null;
        $cutoff = min($start + self::DNS_DEADLINE, $deadline);

        $sets = array();
        foreach (self::SETS as $set) {
            $sets[$set] = array('complete' => false, 'answered' => false, 'lines' => array(), 'truncated' => false);
        }

        $ascii = rtrim(Domain::toAscii($domain), '.');
        if ($ascii === '') return array('result' => 'bad_name', 'first_set' => '', 'sets' => $sets);

        // No www name can be longer than MAX_NAME, so those sets are complete and empty without a query
        if (strlen('www.' . $ascii) > self::MAX_NAME) {
            foreach (self::GROUPS['www'] as $set) $sets[$set]['complete'] = true;
        }

        // A row stored for another name doesn't steer this name's queries
        $same_name = is_array($stored) && (string) ($stored['domain'] ?? '') === (string) $domain;
        $order = self::queryOrder($same_name ? $stored : null);
        $first_set = '';
        $stopped_for_time = false;

        foreach ($order as $set) {

            if ($sets[$set]['complete']) continue;

            if ($cutoff - self::now() < self::MIN_QUERY_TIME) {
                $stopped_for_time = true;
                break;
            }

            if ($first_set === '') $first_set = $set;

            $name = (in_array($set, self::GROUPS['www'], true) ? 'www.' : '') . $ascii;
            $type = self::queryType($set);
            $records = self::query($name . '.', $type);

            // A query that returns at or after the cutoff is a timeout, whatever it returned, and stops the step
            if (self::now() >= $cutoff) {
                $stopped_for_time = true;
                break;
            }

            if (!is_array($records)) continue;

            $cleaned = self::clean($set, $records, $name);
            if ($cleaned === null) continue;

            $sets[$set] = array('complete' => true, 'answered' => true, 'lines' => $cleaned['lines'],
                'truncated' => $cleaned['truncated']);

        }

        return array('result' => self::resultCode($sets, $stopped_for_time), 'first_set' => $first_set,
            'sets' => $sets);
    }

    /*
     * The order the sets are queried in. Normally the usual order (NS, the bare pair, the www pair, then MX). When the
     * stored row lists stale sets, they go first, taking turns: starting from the set after the one the last stored
     * check asked first (first_set), in the usual order, then the rest. Turns go by set rather than by group, so a
     * check with time for only one query still reaches the second half of each pair in time. A pair always runs
     * together: when one half is placed, the other follows straight after it.
     */
    public static function queryOrder($stored)
    {
        $stale = is_array($stored) ? self::setList($stored['stale_sets'] ?? '') : array();
        $order = array();

        $place = function ($set) use (&$order) {
            if (in_array($set, $order, true)) return;
            $order[] = $set;
            $partner = self::partnerOf($set);
            if ($partner !== '' && !in_array($partner, $order, true)) $order[] = $partner;
        };

        if ($stale) {

            $last = array_search((string) ($stored['first_set'] ?? ''), self::SETS, true);
            $begin = ($last === false) ? 0 : $last + 1;

            for ($i = 0; $i < count(self::SETS); $i++) {
                $set = self::SETS[($begin + $i) % count(self::SETS)];
                if (in_array($set, $stale, true)) $place($set);
            }

        }

        foreach (self::SETS as $set) $place($set);

        return $order;
    }

    // The other half of a set's pair, or '' for NS and MX
    public static function partnerOf($set)
    {
        foreach (self::GROUPS as $members) {
            if (count($members) === 2 && in_array($set, $members, true)) {
                return $members[0] === $set ? $members[1] : $members[0];
            }
        }
        return '';
    }

    protected static function queryType($set)
    {
        switch ($set) {
            case 'ns': return 'NS';
            case 'a': case 'www_a': return 'A';
            case 'aaaa': case 'www_aaaa': return 'AAAA';
            default: return 'MX';
        }
    }

    // One query, through the test resolver when one is set. A resolver failure (its own timeout, or SERVFAIL) is
    // false, and its warning isn't shown or logged.
    protected static function query($absolute_name, $type)
    {
        if (self::$test_lookup !== null) return call_user_func(self::$test_lookup, $absolute_name, $type);

        $types = array('NS' => DNS_NS, 'A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX);
        $records = @dns_get_record($absolute_name, $types[$type]);
        return is_array($records) ? $records : false;
    }

    protected static function now()
    {
        if (self::$test_clock !== null) return (float) call_user_func(self::$test_clock);
        return microtime(true);
    }

    /*
     * The overall result of one check, first rule that applies (bad_name is decided before any query): no_dns when NS
     * and both bare address sets were complete and empty and no www set was complete with addresses, ok when all six
     * were complete, partial when at least one answered set was complete and at least one set wasn't, timeout when no
     * answered set was complete and the step stopped for lack of time, and failed otherwise.
     */
    public static function resultCode(array $sets, $stopped_for_time)
    {
        $complete_empty = function ($set) use ($sets) {
            return $sets[$set]['answered'] && $sets[$set]['complete'] && !$sets[$set]['lines'];
        };

        $www_addresses = false;
        foreach (self::GROUPS['www'] as $set) {
            if ($sets[$set]['complete'] && self::addressLines($sets[$set]['lines'])) $www_addresses = true;
        }

        if ($complete_empty('ns') && $complete_empty('a') && $complete_empty('aaaa') && !$www_addresses) return 'no_dns';

        $all = true;
        $any_answered = false;
        foreach (self::SETS as $set) {
            if (!$sets[$set]['complete']) $all = false;
            if ($sets[$set]['answered']) $any_answered = true;
        }

        if ($all) return 'ok';
        if ($any_answered) return 'partial';
        return $stopped_for_time ? 'timeout' : 'failed';
    }

    /*
     * Cleans one set's records: array('lines' => ..., 'truncated' => ...), or null when any record of the asked type
     * fails cleaning, so a malformed answer never becomes a complete set. Host names go through normalizeHost(),
     * addresses are stored in the compressed form inet_ntop() gives, duplicates are removed, and the set is sorted
     * before it's held to SET_CAP, so the resolver's answer rotation can't change what's kept. For A and AAAA, records
     * whose host isn't the name asked for came through a CNAME chain: that name is kept as a 'cname:' line first.
     * Records of other types are ignored.
     */
    public static function clean($set, array $records, $asked_name)
    {
        $type = self::queryType($set);
        $entries = array();
        $hosts = array();

        foreach ($records as $record) {

            if (!is_array($record) || ($record['type'] ?? '') !== $type) continue;

            if ($type === 'NS') {

                $host = self::normalizeHost($record['target'] ?? '');
                if ($host === '') return null;
                $entries[$host] = $host;

            } elseif ($type === 'MX') {

                $priority = $record['pri'] ?? null;
                if (!is_int($priority) && !(is_string($priority) && ctype_digit($priority))) return null;
                $priority = (int) $priority;
                if ($priority > 65535) return null;

                $target = $record['target'] ?? null;
                if (!is_string($target)) return null;

                // A null MX (priority 0 to the root name) says the domain takes no mail
                if ($priority === 0 && ($target === '' || $target === '.')) {
                    $entries['0 .'] = array(0, '.');
                    continue;
                }

                $host = self::normalizeHost($target);
                if ($host === '') return null;
                $entries[$priority . ' ' . $host] = array($priority, $host);

            } else {

                $field = ($type === 'A') ? 'ip' : 'ipv6';
                $address = self::normalizeAddress($record[$field] ?? '', $type === 'A' ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6);
                if ($address === '') return null;

                $host = self::normalizeHost($record['host'] ?? '');
                if ($host === '') return null;

                $entries[$address] = $address;
                $hosts[$host] = $host;

            }

        }

        if ($type === 'MX') {

            uasort($entries, function ($x, $y) {
                return ($x[0] <=> $y[0]) ?: strcmp($x[1], $y[1]);
            });
            $lines = array();
            foreach ($entries as $entry) $lines[] = $entry[0] . ' ' . $entry[1];

        } else {

            $lines = array_values($entries);
            if ($type === 'NS') {
                sort($lines, SORT_STRING);
            } else {
                usort($lines, array(self::class, 'compareAddresses'));
            }

        }

        $truncated = count($lines) > self::SET_CAP;
        $lines = array_slice($lines, 0, self::SET_CAP);

        // The name at the end of a CNAME chain (the first, if a broken answer gave several)
        if ($hosts) {
            $asked = self::normalizeHost($asked_name);
            unset($hosts[$asked]);
            if ($hosts) {
                sort($hosts, SORT_STRING);
                array_unshift($lines, 'cname:' . $hosts[0]);
            }
        }

        return array('lines' => $lines, 'truncated' => $truncated);
    }

    // A host name in the one form Live DNS compares and stores: trimmed, lower case, no trailing dot, and punycode
    // rather than Unicode. '' when it isn't a valid host name.
    public static function normalizeHost($name)
    {
        if (!is_string($name)) return '';
        $name = trim($name);
        if (str_ends_with($name, '.')) $name = substr($name, 0, -1);
        if ($name === '') return '';

        $ascii = strtolower(Domain::toAscii($name));
        if (str_ends_with($ascii, '.')) $ascii = substr($ascii, 0, -1);

        return self::rdap()->isHostname($ascii) ? $ascii : '';
    }

    // An IPv4 or IPv6 address (of the family $flag picks, or either) in its compressed form, or '' if it isn't one
    public static function normalizeAddress($address, $flag = 0)
    {
        if (!is_string($address)) return '';
        $address = trim($address);
        if ($flag === 0) $flag = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if (filter_var($address, FILTER_VALIDATE_IP, $flag) === false) return '';
        return inet_ntop(inet_pton($address));
    }

    // Whether an address is outside every special-use range Rdap knows (an IPv4-mapped IPv6 address is judged by the
    // IPv4 address it carries), for the edit page's "(not a public address)" mark
    public static function isPublicAddress($address)
    {
        return self::rdap()->isPublicAddress($address);
    }

    protected static function rdap()
    {
        if (self::$rdap === null) self::$rdap = new Rdap();
        return self::$rdap;
    }

    // The order addresses are stored in: IPv4 before IPv6, then by their numeric value
    public static function compareAddresses($x, $y)
    {
        $x = inet_pton($x);
        $y = inet_pton($y);
        return (strlen($x) <=> strlen($y)) ?: strcmp($x, $y);
    }

    // A stored set's lines without its 'cname:' line
    public static function addressLines(array $lines)
    {
        return array_values(array_filter($lines, function ($line) {
            return strncmp($line, 'cname:', 6) !== 0;
        }));
    }

    // A stored column's lines ('' is none)
    public static function lines($text)
    {
        $text = (string) $text;
        return $text === '' ? array() : explode("\n", $text);
    }

    // A comma-separated list of set or field codes as an array ('' is none)
    public static function setList($text)
    {
        $text = (string) $text;
        return $text === '' ? array() : explode(',', $text);
    }

    // Whether the domain's www name would be too long to exist
    public static function wwwTooLong($domain)
    {
        $ascii = rtrim(Domain::toAscii($domain), '.');
        return $ascii !== '' && strlen('www.' . $ascii) > self::MAX_NAME;
    }

    /*
     * Merges one observation (from lookup()) into the stored row. $stored is the stored row (or null), $domain the
     * domain's current name in its stored form, $started_us when the refresh started its main lookup (microseconds, as
     * a decimal string, see usNormal()),
     * and $checked_time that start in UTC 'Y-m-d H:i:s'.
     *
     * Returns null when the stored row is for the same name and a check with the same or a later start is already
     * stored (this observation is dropped). Otherwise array('row' => the new row's columns, without the states,
     * 'history' => the Change History rows to record, each array(field, old, new)). A stored row for another name is
     * an empty baseline: its sets, seen sets, and history baselines are discarded, and nothing is recorded.
     */
    public static function merge($stored, array $observation, $domain, $started_us, $checked_time)
    {
        $same_name = is_array($stored) && (string) ($stored['domain'] ?? '') === (string) $domain;

        if ($same_name && self::usCompare($stored['started_us'], $started_us) >= 0) return null;

        $base = $same_name ? $stored : self::emptyRow($domain);
        $sets = $observation['sets'];

        $row = array(
            'domain' => (string) $domain,
            'checked_time' => (string) $checked_time,
            'started_us' => self::usNormal($started_us),
            'result' => (string) $observation['result'],
        );

        $seen = self::setList($base['seen_sets']);
        $truncated = self::setList($base['truncated_sets']);
        $stale = array();
        $answered = false;

        foreach (self::SETS as $set) {

            $column = self::COLUMNS[$set];

            if ($sets[$set]['complete']) {

                $row[$column] = implode("\n", $sets[$set]['lines']);
                if (!in_array($set, $seen, true)) $seen[] = $set;
                $truncated = array_values(array_diff($truncated, array($set)));
                if ($sets[$set]['truncated']) $truncated[] = $set;
                if ($sets[$set]['answered']) $answered = true;

            } else {

                $row[$column] = (string) $base[$column];
                $stale[] = $set;

            }

        }

        $row['seen_sets'] = implode(',', self::ordered($seen));
        $row['stale_sets'] = implode(',', $stale);
        /*
         * The sets that pull the next scheduled check forward (see DomainRefreshSchedule::withDnsRetry()): those this
         * check didn't answer that the stored check before it didn't answer either. So a slow resolver that answers each
         * set every other check (half the sets each time) isn't retried daily for ever, while a set that keeps failing
         * is. With no earlier check for this name, every set this one didn't answer counts.
         */
        $before = ($same_name && self::usNormal($stored['started_us']) !== '0') ? self::setList($base['stale_sets']) : self::SETS;
        $row['retry_sets'] = implode(',', array_values(array_intersect($stale, $before)));
        $row['truncated_sets'] = implode(',', self::ordered($truncated));
        $row['last_ok_time'] = $answered ? (string) $checked_time : (string) $base['last_ok_time'];
        $row['first_set'] = $observation['first_set'] !== '' ? (string) $observation['first_set']
            : (string) $base['first_set'];
        $row['ip_basis'] = self::ipBasis((string) $base['ip_basis'], $sets);

        // The history fields, each with its own baseline
        $history = array();
        $history_seen = self::setList($base['history_seen']);
        $row['history_ns'] = (string) $base['history_ns'];
        $row['history_ips'] = (string) $base['history_ips'];

        $fields = array('ns' => self::historyNs($sets), 'ips' => self::historyIps($sets, $row['ip_basis']));

        foreach ($fields as $field => $value) {

            if ($value === null) continue;

            $column = 'history_' . $field;
            if ($same_name && in_array($field, $history_seen, true) && $value !== $row[$column]) {
                $history[] = array('live_' . $field, $row[$column], $value);
            }
            $row[$column] = $value;
            if (!in_array($field, $history_seen, true)) $history_seen[] = $field;

        }

        $row['history_seen'] = implode(',', array_values(array_intersect(array('ns', 'ips'), $history_seen)));

        return array('row' => $row, 'history' => $history);
    }

    // A row with nothing stored, for a domain's first check or one whose stored row was for another name
    public static function emptyRow($domain = '')
    {
        $row = array('domain' => (string) $domain, 'checked_time' => self::UNSET_TIME, 'started_us' => '0', 'result' => '',
            'ns_state' => 'unknown', 'ip_state' => 'unknown', 'list_state' => 'failed');
        foreach (self::COLUMNS as $column) $row[$column] = '';
        return $row + array('seen_sets' => '', 'stale_sets' => '', 'truncated_sets' => '', 'retry_sets' => '',
            'last_ok_time' => self::UNSET_TIME,
            'first_set' => '', 'ip_basis' => '', 'history_ns' => '', 'history_ips' => '', 'history_seen' => '');
    }

    // Set codes in the usual order
    protected static function ordered(array $sets)
    {
        return array_values(array_intersect(self::SETS, $sets));
    }

    /*
     * Which address sets the IP comparison uses after a check. Only a check that completes a bare address set moves
     * it: both complete gives www when both are empty and bare otherwise, one complete with addresses gives bare, and
     * one complete and empty keeps www (nothing new was learned) or otherwise gives unknown.
     */
    public static function ipBasis($basis, array $sets)
    {
        $a = $sets['a'];
        $aaaa = $sets['aaaa'];

        if ($a['complete'] && $aaaa['complete']) {
            return (!self::addressLines($a['lines']) && !self::addressLines($aaaa['lines'])) ? 'www' : 'bare';
        }

        foreach (array($a, $aaaa) as $one) {
            if (!$one['complete']) continue;
            if (self::addressLines($one['lines'])) return 'bare';
            return $basis === 'www' ? 'www' : 'unknown';
        }

        return $basis;
    }

    // The live_ns history value when it qualifies in this check (the NS set complete and not truncated), else null
    protected static function historyNs(array $sets)
    {
        $ns = $sets['ns'];
        if (!$ns['complete'] || $ns['truncated']) return null;
        return implode("\n", $ns['lines']);
    }

    /*
     * The live_ips history value when it qualifies in this check, else null: both address sets the basis uses
     * complete in this check and not truncated, address lines only (a CNAME that changes target while its addresses
     * stay the same isn't an IP change), sorted, with each www address as 'www: <address>'.
     */
    protected static function historyIps(array $sets, $basis)
    {
        if ($basis === 'bare') {
            $members = self::GROUPS['bare'];
            $prefix = '';
        } elseif ($basis === 'www') {
            $members = self::GROUPS['www'];
            $prefix = 'www: ';
        } else {
            return null;
        }

        $addresses = array();
        foreach ($members as $set) {
            if (!$sets[$set]['complete'] || $sets[$set]['truncated']) return null;
            foreach (self::addressLines($sets[$set]['lines']) as $address) $addresses[] = $address;
        }

        usort($addresses, array(self::class, 'compareAddresses'));

        $lines = array();
        foreach ($addresses as $address) $lines[] = $prefix . $address;
        return implode("\n", $lines);
    }

    /*
     * The states, worked out from the stored row alone (never from what the latest attempt failed to get): ns_state,
     * ip_state, and list_state. $dns_id and $profile_servers are the domain's DNS profile and its servers (dns1 to
     * dns10, blanks ignored), and $ip_id and $asset_ip its IP Address asset and the asset's ip, all as read under the
     * write's locks. A pure comparison, with no DNS query.
     */
    public static function compare(array $row, $dns_id, array $profile_servers, $ip_id, $asset_ip)
    {
        $states = array('ns_state' => self::nsState($row, $dns_id, $profile_servers),
            'ip_state' => self::ipState($row, $ip_id, $asset_ip));
        // The new states win over any the row still holds
        $states['list_state'] = self::listState($states + $row);
        return $states;
    }

    protected static function nsState(array $row, $dns_id, array $profile_servers)
    {
        if ((int) $dns_id === 1) return 'not_set';

        $seen = self::setList($row['seen_sets'] ?? '');
        $live = self::lines($row['name_servers'] ?? '');
        if (!in_array('ns', $seen, true) || in_array('ns', self::setList($row['truncated_sets'] ?? ''), true) || !$live) {
            return 'unknown';
        }

        return self::sameServers($live, $profile_servers) ? 'match' : 'differs';
    }

    // Whether a live NS set (already normalised) and a profile's servers (typed by hand) hold the same servers, order
    // ignored, with the profile's values put through the same normaliser
    public static function sameServers(array $live, array $profile_servers)
    {
        $profile = self::profileServers($profile_servers);
        $live = array_values(array_unique($live));
        sort($live, SORT_STRING);
        return $profile === $live;
    }

    // A profile's servers, normalised, without blanks or duplicates, sorted. A value that isn't a valid host name is
    // kept lower-cased, so it never matches a live server.
    public static function profileServers(array $servers)
    {
        $list = array();
        foreach ($servers as $server) {
            $server = trim((string) $server);
            if ($server === '') continue;
            $host = self::normalizeHost($server);
            $list[] = $host !== '' ? $host : strtolower($server);
        }
        $list = array_values(array_unique($list));
        sort($list, SORT_STRING);
        return $list;
    }

    protected static function ipState(array $row, $ip_id, $asset_ip)
    {
        if ((int) $ip_id === 1) return 'not_set';
        $address = self::normalizeAddress(html_entity_decode((string) $asset_ip, ENT_QUOTES, 'UTF-8'));
        if ($address === '') return 'not_set';

        $used = self::usedSets($row);
        if (!$used) return 'unknown';

        $seen = self::setList($row['seen_sets'] ?? '');
        $truncated = self::setList($row['truncated_sets'] ?? '');

        foreach ($used as $set) {
            if (in_array($set, $seen, true)
                && in_array($address, self::addressLines(self::lines($row[self::COLUMNS[$set]] ?? '')), true)) {
                return 'match';
            }
        }

        $empty = true;
        foreach ($used as $set) {
            if (!in_array($set, $seen, true) || in_array($set, $truncated, true)) return 'unknown';
            if (self::addressLines(self::lines($row[self::COLUMNS[$set]] ?? ''))) $empty = false;
        }

        return $empty ? 'none' : 'differs';
    }

    // The address sets the IP comparison uses, from the stored ip_basis (none for unknown or no basis yet)
    public static function usedSets(array $row)
    {
        switch ((string) ($row['ip_basis'] ?? '')) {
            case 'bare': return self::GROUPS['bare'];
            case 'www': return self::GROUPS['www'];
            default: return array();
        }
    }

    // The live addresses the comparison used (address lines only, in stored order), for suggestions
    public static function usedAddresses(array $row)
    {
        $addresses = array();
        foreach (self::usedSets($row) as $set) {
            foreach (self::addressLines(self::lines($row[self::COLUMNS[$set]] ?? '')) as $address) $addresses[] = $address;
        }
        return array_values(array_unique($addresses));
    }

    /*
     * The list badge for a stored row with its states (null for no row for the domain's current name), first match
     * wins, worked out from the stored sets and states, never the latest attempt's result.
     */
    public static function listState($row)
    {
        if (!is_array($row)) return 'unchecked';

        $seen = self::setList($row['seen_sets'] ?? '');
        $only_www = !array_diff($seen, self::GROUPS['www']);
        if (!$seen || ($only_www && self::wwwTooLong($row['domain'] ?? ''))) return 'failed';

        $stored_empty = function ($set) use ($row, $seen) {
            return in_array($set, $seen, true) && !self::addressLines(self::lines($row[self::COLUMNS[$set]] ?? ''));
        };
        if ($stored_empty('ns') && (string) ($row['ip_basis'] ?? '') === 'www' && $stored_empty('www_a')
            && $stored_empty('www_aaaa')) {
            return 'no_dns';
        }

        $ns = (string) ($row['ns_state'] ?? 'unknown');
        $ip = (string) ($row['ip_state'] ?? 'unknown');

        if ($ns === 'differs' && $ip === 'differs') return 'both';
        if ($ns === 'differs') return 'ns';
        if ($ip === 'differs') return 'ip';
        if ($ip === 'none') return 'no_addresses';
        if ($ns === 'not_set' || $ip === 'not_set') return 'not_set';
        if ($ns === 'unknown' && $ip === 'unknown') return 'incomplete';
        return 'ok';
    }

    // The suffix on a badge's title and in the export: 'failed' when the latest attempt failed, 'incomplete' when it
    // left a set stale, otherwise ''
    public static function lastCheckSuffix($row)
    {
        if (!is_array($row)) return '';
        if (in_array((string) ($row['result'] ?? ''), array('failed', 'timeout', 'bad_name'), true)) return 'failed';
        return (string) ($row['stale_sets'] ?? '') !== '' ? 'incomplete' : '';
    }

    /*
     * The Domains filter's SQL for one value (see FILTERS), as " AND ... ", or '' for any other value. $outer is the
     * outer query's domains table, 'd' or the unaliased 'domains' (the toolbar's dropdown queries), always named in
     * the subquery so a bare domain can't match the check row's own column. Fixed SQL with no parameters, so it works
     * both in queries that bind the page's filter parameters and in ones that run the fragments as plain text.
     */
    public static function filterCondition($value, $outer = 'd')
    {
        if (!is_string($value) || !array_key_exists($value, self::FILTERS)) return '';
        $outer = ($outer === 'domains') ? 'domains' : 'd';

        $match = "SELECT 1 FROM domain_dns_check AS ldcf WHERE ldcf.domain_id = " . $outer . ".id AND ldcf.domain = "
            . $outer . ".domain";

        if ($value === 'unchecked') return " AND NOT EXISTS (" . $match . ") ";

        return " AND EXISTS (" . $match . " AND ldcf.list_state IN ('" . implode("', '", self::FILTERS[$value]) . "')) ";
    }

    /*
     * The stored row for a domain (any name), as column => value, or null. With $lock (inside a transaction), it stays
     * locked until the transaction ends.
     */
    public static function readRow(\PDO $pdo, $domain_id, $lock = false)
    {
        $stmt = $pdo->prepare("
            SELECT `" . implode('`, `', array_diff(self::TABLE_COLUMNS, array('domain_id'))) . "`
            FROM domain_dns_check
            WHERE domain_id = :domain_id" . ($lock ? "
            FOR UPDATE" : ""));
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row ?: null;
    }

    /*
     * Locks what one domain's states are worked out from, inside the caller's transaction, in the one order every writer
     * uses: the domain row, then its IP Address asset, then its DNS profile, then its check row (DomainRefresh::apply()
     * locks the domain and, through its joins, the registrar account's API IP Address before the profile, so taking the
     * profile first could deadlock with it). Returns array('domain' => the domain row, 'servers' => the profile's servers,
     * 'asset_ip' => the asset's ip, 'check' => the stored row or null), or null when the domain is gone.
     */
    public static function lockDomain(\PDO $pdo, $domain_id)
    {
        $stmt = $pdo->prepare("
            SELECT id, domain, active, dns_id, ip_id
            FROM domains
            WHERE id = :domain_id
            FOR UPDATE");
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $domain = $stmt->fetch(\PDO::FETCH_OBJ);
        $stmt->closeCursor();
        if (!$domain) return null;

        $stmt = $pdo->prepare("
            SELECT ip
            FROM ip_addresses
            WHERE id = :ip_id
            FOR UPDATE");
        $stmt->bindValue('ip_id', (int) $domain->ip_id, \PDO::PARAM_INT);
        $stmt->execute();
        $asset_ip = $stmt->fetchColumn();
        $stmt->closeCursor();

        $stmt = $pdo->prepare("
            SELECT dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10
            FROM dns
            WHERE id = :dns_id
            FOR UPDATE");
        $stmt->bindValue('dns_id', (int) $domain->dns_id, \PDO::PARAM_INT);
        $stmt->execute();
        $servers = $stmt->fetch(\PDO::FETCH_NUM);
        $stmt->closeCursor();

        return array('domain' => $domain, 'servers' => $servers ? array_values($servers) : array(),
            'asset_ip' => $asset_ip === false ? '' : (string) $asset_ip, 'check' => self::readRow($pdo, $domain_id, true));
    }

    // The states for a locked domain (see lockDomain()) and a row stored for its current name
    public static function compareLocked(array $locked, array $row)
    {
        return self::compare($row, (int) $locked['domain']->dns_id, $locked['servers'], (int) $locked['domain']->ip_id,
            $locked['asset_ip']);
    }

    // Saves a domain's whole row (an insert, or an update of every column), inside the caller's transaction
    public static function saveRow(\PDO $pdo, $domain_id, array $row)
    {
        $columns = array_diff(self::TABLE_COLUMNS, array('domain_id'));
        $updates = array();
        foreach ($columns as $column) $updates[] = '`' . $column . '` = :u_' . $column;

        $stmt = $pdo->prepare("
            INSERT INTO domain_dns_check
            (domain_id, `" . implode('`, `', $columns) . "`)
            VALUES
            (:domain_id, :i_" . implode(', :i_', $columns) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updates));
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        // started_us goes as a decimal string too (see usNormal()), which the BIGINT column takes as its number
        foreach ($columns as $column) {
            $value = $column === 'started_us' ? self::usNormal($row[$column]) : (string) $row[$column];
            $stmt->bindValue('i_' . $column, $value, \PDO::PARAM_STR);
            $stmt->bindValue('u_' . $column, $value, \PDO::PARAM_STR);
        }
        $stmt->execute();
    }

    // Saves new states for a stored row, inside the caller's transaction, only when they've changed
    protected static function saveStates(\PDO $pdo, $domain_id, array $row, array $states)
    {
        if ($states['ns_state'] === (string) $row['ns_state'] && $states['ip_state'] === (string) $row['ip_state']
            && $states['list_state'] === (string) $row['list_state']) return;

        $stmt = $pdo->prepare("
            UPDATE domain_dns_check
            SET ns_state = :ns_state,
                ip_state = :ip_state,
                list_state = :list_state
            WHERE domain_id = :domain_id");
        $stmt->bindValue('ns_state', $states['ns_state'], \PDO::PARAM_STR);
        $stmt->bindValue('ip_state', $states['ip_state'], \PDO::PARAM_STR);
        $stmt->bindValue('list_state', $states['list_state'], \PDO::PARAM_STR);
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /*
     * Works one domain's states out again from its stored row (no DNS query), in its own short transaction with the locks
     * in lockDomain()'s order, after something it's compared with changed: the domain's DNS profile or IP Address, or the
     * profile or asset itself. A row stored for another name is left alone (it counts as Not checked). A failure (a lock
     * wait timeout, or a database error) is logged and skipped, and never fails the edit that caused it: the daily
     * cleanup works every row out again (see rebuildStates()). Before the database update, nothing is done.
     */
    public static function recompare(\PDO $pdo, $domain_id)
    {
        if (!self::isAvailable($pdo)) return;

        try {

            $pdo->beginTransaction();
            $locked = self::lockDomain($pdo, $domain_id);
            $check = $locked ? $locked['check'] : null;
            if ($check !== null && (string) $check['domain'] === (string) $locked['domain']->domain) {
                self::saveStates($pdo, $domain_id, $check, self::compareLocked($locked, $check));
            }
            $pdo->commit();

        } catch (\Throwable $e) {

            if ($pdo->inTransaction()) $pdo->rollBack();
            self::logFailure('Unable to compare a domain\'s live DNS again', $domain_id, $e);

        }
    }

    // recompare() for several domains, one at a time in ascending id order, so no transaction ever holds two domains
    public static function recompareMany(\PDO $pdo, array $domain_ids)
    {
        $domain_ids = array_values(array_unique(array_map('intval', $domain_ids)));
        sort($domain_ids);
        foreach ($domain_ids as $domain_id) self::recompare($pdo, $domain_id);
    }

    /*
     * The domains with a stored row for their current name that use a DNS profile ('dns_id') or an IP Address asset
     * ('ip_id'), for the recompare after that profile or asset is edited. None before the database update.
     */
    public static function domainsUsing(\PDO $pdo, $column, $id)
    {
        if (!self::isAvailable($pdo) || !in_array($column, array('dns_id', 'ip_id'), true)) return array();

        $stmt = $pdo->prepare("
            SELECT d.id
            FROM domains AS d
            JOIN domain_dns_check AS ldc ON ldc.domain_id = d.id AND ldc.domain = d.domain
            WHERE d." . $column . " = :id
            ORDER BY d.id");
        $stmt->bindValue('id', (int) $id, \PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $stmt->closeCursor();

        return $ids;
    }

    /*
     * Deletes the stored rows of domains being deleted, inside the caller's transaction, after the domains' own DELETE
     * (so the domain row is always locked first, as the DNS write locks it). Before the database update there's no
     * table, so nothing is done.
     */
    public static function deleteRows(\PDO $pdo, $domain_ids)
    {
        $domain_ids = array_values(array_map('intval', $domain_ids));
        if (!$domain_ids || !self::isAvailable($pdo)) return;

        $stmt = $pdo->prepare("
            DELETE FROM domain_dns_check
            WHERE domain_id IN (" . implode(', ', array_fill(0, count($domain_ids), '?')) . ")");
        $stmt->execute($domain_ids);
    }

    /*
     * The daily cleanup's two Live DNS steps: rows whose domain is gone, or that were stored for a name the domain no
     * longer has (they count as Not checked anyway), are deleted, then every row's states are worked out again from
     * its stored sets, so a recompare that was skipped is repaired. Each row whose states would change goes through
     * recompare(), so it's worked out under the usual locks. Before the database update, nothing is done.
     */
    public static function cleanup(\PDO $pdo)
    {
        if (!self::isAvailable($pdo)) return;

        // Read first, then deleted by the check row's own id, stored name, and start, so the delete never locks a domain
        // after a check row (the order every writer uses is the other way round), and a row a refresh stored meanwhile is
        // kept
        $stale = $pdo->query("
            SELECT ldc.domain_id, ldc.domain, ldc.started_us
            FROM domain_dns_check AS ldc
            LEFT JOIN domains AS d ON d.id = ldc.domain_id
            WHERE d.id IS NULL
               OR d.domain <> ldc.domain")->fetchAll(\PDO::FETCH_NUM);
        $delete = $pdo->prepare("
            DELETE FROM domain_dns_check
            WHERE domain_id = :domain_id
              AND domain = :domain
              AND started_us = :started_us");
        foreach ($stale as $row) {
            $delete->bindValue('domain_id', (int) $row[0], \PDO::PARAM_INT);
            $delete->bindValue('domain', (string) $row[1], \PDO::PARAM_STR);
            $delete->bindValue('started_us', self::usNormal($row[2]), \PDO::PARAM_STR);
            $delete->execute();
        }

        self::rebuildStates($pdo);
    }

    // Works every row's states out again (see cleanup()), reading them in batches by domain id
    public static function rebuildStates(\PDO $pdo)
    {
        $last_id = 0;
        $columns = 'ldc.`' . implode('`, ldc.`', array_diff(self::TABLE_COLUMNS, array('domain_id'))) . '`';

        do {

            $stmt = $pdo->prepare("
                SELECT ldc.domain_id, " . $columns . ", d.dns_id, d.ip_id, IFNULL(ip.ip, '') AS asset_ip,
                    dns.dns1, dns.dns2, dns.dns3, dns.dns4, dns.dns5, dns.dns6, dns.dns7, dns.dns8, dns.dns9, dns.dns10
                FROM domain_dns_check AS ldc
                JOIN domains AS d ON d.id = ldc.domain_id AND d.domain = ldc.domain
                LEFT JOIN ip_addresses AS ip ON ip.id = d.ip_id
                LEFT JOIN dns ON dns.id = d.dns_id
                WHERE ldc.domain_id > :last_id
                ORDER BY ldc.domain_id
                LIMIT 500");
            $stmt->bindValue('last_id', $last_id, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $changed = array();
            foreach ($rows as $row) {
                $last_id = (int) $row['domain_id'];
                $servers = array();
                for ($i = 1; $i <= 10; $i++) $servers[] = (string) $row['dns' . $i];
                $states = self::compare($row, (int) $row['dns_id'], $servers, (int) $row['ip_id'], $row['asset_ip']);
                if ($states['ns_state'] !== (string) $row['ns_state'] || $states['ip_state'] !== (string) $row['ip_state']
                    || $states['list_state'] !== (string) $row['list_state']) {
                    $changed[] = $last_id;
                }
            }
            self::recompareMany($pdo, $changed);

        } while (count($rows) === 500);
    }

    // The Live DNS filter's choices, by value (see FILTERS)
    public static function filterChoices()
    {
        return array('any' => _('Any difference'), 'ns' => _('Name servers differ'), 'ip' => _('IP differs'),
            'nodns' => _('No DNS'), 'failed' => _('Check failed'), 'unchecked' => _('Not checked'));
    }

    // Each badge's name, and its icon and colour on the Domains list
    public static function badge($state)
    {
        $badges = array(
            'unchecked' => array(_('Not checked'), 'bi-dash-circle', 'text-body-secondary'),
            'failed' => array(_('Check failed'), 'bi-exclamation-circle', 'text-body-secondary'),
            'no_dns' => array(_('No DNS'), 'bi-x-circle', 'text-danger-emphasis'),
            'both' => array(_('Both differ'), 'bi-exclamation-triangle', 'text-warning-emphasis'),
            'ns' => array(_('Name servers differ'), 'bi-exclamation-triangle', 'text-warning-emphasis'),
            'ip' => array(_('IP differs'), 'bi-exclamation-triangle', 'text-warning-emphasis'),
            'no_addresses' => array(_('No addresses'), 'bi-slash-circle', 'text-warning-emphasis'),
            'not_set' => array(_('Not set'), 'bi-dash-circle', 'text-body-secondary'),
            'incomplete' => array(_('Incomplete'), 'bi-question-circle', 'text-body-secondary'),
            'ok' => array(_('OK'), 'bi-check-circle', 'text-success-emphasis'));
        return $badges[$state] ?? $badges['unchecked'];
    }

    // A Domains list row's badge state and suffix, from its ldc1_ columns (no row for the current name is Not checked)
    protected static function rowBadge($row)
    {
        $list_state = $row->ldc1_list_state ?? null;
        if ($list_state === null) return array('unchecked', '');
        $state = in_array((string) $list_state, self::LIST_STATES, true) ? (string) $list_state : 'failed';
        $suffix = self::lastCheckSuffix(array('result' => $row->ldc1_result ?? '', 'stale_sets' => $row->ldc1_stale_sets ?? ''));
        $suffixes = array('failed' => _('(last check failed)'), 'incomplete' => _('(last check was incomplete)'));
        return array($state, $suffixes[$suffix] ?? '');
    }

    /*
     * The Domains list's Live DNS cell: the badge's icon and name, with the last check's suffix on hover or focus when
     * there is one, linking to the edit page's Live DNS section
     */
    public static function listCell($row)
    {
        list($state, $suffix) = self::rowBadge($row);
        list($label, $icon, $class) = self::badge($state);
        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };
        $tooltip = $suffix !== '' ? ' data-bs-toggle="tooltip" data-bs-title="' . $esc($label . ' ' . $suffix) . '"' : '';

        return '<a href="edit.php?did=' . (int) $row->id . '#domainmod-section-live-dns" class="' . $class . '"' . $tooltip
            . '><i class="bi ' . $icon . '" aria-hidden="true"></i> ' . $esc($label) . '</a>';
    }

    // The Live DNS column's export text: the badge's name and the last check's suffix
    public static function exportText($row)
    {
        list($state, $suffix) = self::rowBadge($row);
        return trim(self::badge($state)[0] . ' ' . $suffix);
    }

    /*
     * The edit page's Live DNS section (heading id domainmod-section-live-dns) in its wrapper (#domainmod-live-dns),
     * which is always on the page, so the in-place Refresh can replace it with an out-of-band swap ($oob). It's an empty
     * wrapper before the database update, for a domain that's gone, and after a database error (logged), so neither the
     * page nor a Refresh's answer breaks. $can_change says whether the viewer can change data (the Refresh button's
     * check), which shows "Click Refresh", the "Use it" buttons, and the "Add this IP Address" links.
     */
    public static function editCard(\PDO $pdo, $domain_id, $can_change, $oob = false)
    {
        $wrapper = '<div id="domainmod-live-dns"' . ($oob ? ' hx-swap-oob="true"' : '') . '>';
        if (!self::isAvailable($pdo)) return $wrapper . '</div>';

        try {
            $body = self::editSection($pdo, $domain_id, $can_change);
        } catch (\Throwable $e) {
            self::logFailure('Unable to show a domain\'s live DNS', $domain_id, $e);
            $body = '';
        }
        if ($body === '') return $wrapper . '</div>';

        return $wrapper . (new Layout())->formSection(_('Live DNS'), 'live-dns', $body) . '</div>';
    }

    // The section's body (see editCard()), or '' for a domain that's gone
    protected static function editSection(\PDO $pdo, $domain_id, $can_change)
    {
        $stmt = $pdo->prepare("
            SELECT d.domain, d.dns_id, d.ip_id, IFNULL(dns.name, '') AS dns_name, dns.dns1, dns.dns2, dns.dns3, dns.dns4,
                dns.dns5, dns.dns6, dns.dns7, dns.dns8, dns.dns9, dns.dns10, IFNULL(ip.name, '') AS ip_name,
                IFNULL(ip.ip, '') AS ip_ip
            FROM domains AS d
            LEFT JOIN dns ON dns.id = d.dns_id
            LEFT JOIN ip_addresses AS ip ON ip.id = d.ip_id
            WHERE d.id = :domain_id");
        $stmt->bindValue('domain_id', (int) $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $domain = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$domain) return '';

        $layout = new Layout();
        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };
        $decode = function ($text) {
            return html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
        };

        $intro = '<p class="text-body-secondary small">' . $esc(_("Refresh looks these up through this server's DNS resolver. They're only reported here, and never change the domain's DNS profile or IP Address.")) . '</p>';

        $row = self::readRow($pdo, $domain_id);
        if ($row === null || (string) $row['domain'] !== (string) $domain['domain']) {
            $text = $esc(_('Not checked yet.'));
            if ($can_change) $text .= ' ' . $esc(_('Click Refresh to look it up.'));
            return '<div class="domainmod-span-all"><p>' . $text . '</p>' . $intro . '</div>';
        }

        $servers = array();
        for ($i = 1; $i <= 10; $i++) $servers[] = (string) $domain['dns' . $i];
        $states = self::compare($row, (int) $domain['dns_id'], $servers, (int) $domain['ip_id'], $domain['ip_ip']);
        $seen = self::setList($row['seen_sets']);
        $stale = self::setList($row['stale_sets']);

        // When it was checked, and how the last check went
        $html = '<div class="domainmod-span-all"><p>' . sprintf($esc(_('Checked %s.')),
            $layout->ltr((new Time())->toUserTimezone($row['checked_time'], 'Y-m-d'))) . '</p>';
        $results = array('failed' => _('The last check failed, so anything shown here is from an earlier check.'),
            'timeout' => _('The last check ran out of time, so anything shown here is from an earlier check.'),
            'bad_name' => _("This domain name can't be looked up in DNS."),
            'no_dns' => _('The domain has no live name servers or IP addresses.'));
        // The no_dns sentence only when the stored sets say so (the No DNS badge), not for a check whose www sets
        // weren't answered
        $result = (string) $row['result'];
        if ($result === 'no_dns' && self::listState($states + $row) !== 'no_dns') $result = '';
        if (isset($results[$result])) $html .= '<p>' . $esc($results[$result]) . '</p>';
        $html .= $intro . '</div>';

        // The live name servers, then the DNS profile's, each marked when the other doesn't have it
        $live_ns = self::lines($row['name_servers']);
        $profile_ns = self::profileServers($servers);
        $ns_differs = $states['ns_state'] === 'differs';

        $html .= '<div class="mb-3"><h3 class="h6">' . $esc(_('Live Name Servers')) . '</h3>'
            . self::setHtml($row, 'ns', $seen, $stale, function ($entry) use ($ns_differs, $profile_ns, $esc) {
                if (!$ns_differs || in_array($entry, $profile_ns, true)) return '';
                return ' <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> '
                    . $esc(_('(not in the DNS profile)')) . '</span>';
            });
        $ns_sentences = array('match' => _('They match the DNS profile.'),
            'differs' => _("They don't match the DNS profile."),
            'not_set' => _("The domain has no DNS profile, so they weren't compared."));
        if (isset($ns_sentences[$states['ns_state']])) $html .= '<p class="mb-0">' . $esc($ns_sentences[$states['ns_state']]) . '</p>';
        $html .= '</div>';

        $html .= '<div class="mb-3"><h3 class="h6">' . $esc(_('DNS Profile')) . '</h3>';
        if ((int) $domain['dns_id'] === 1) {
            $html .= '<p>' . $esc(_('None')) . '</p>';
        } else {
            $html .= '<p class="mb-1">' . $esc($decode($domain['dns_name'])) . '</p><ul class="list-unstyled mb-2">';
            foreach ($profile_ns as $server) {
                $mark = ($ns_differs && !in_array($server, $live_ns, true)) ? ' <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> '
                    . $esc(_('(not live)')) . '</span>' : '';
                $html .= '<li>' . $layout->ltr($server) . $mark . '</li>';
            }
            $html .= '</ul>';
        }
        // Suggestions come only from answers this check got (never a set kept from an earlier check)
        if (in_array($states['ns_state'], array('differs', 'not_set'), true) && $live_ns
            && !in_array('ns', self::setList($row['truncated_sets']), true) && !in_array('ns', $stale, true)) {
            $match = (new DomainQueue())->findDnsProfile($live_ns);
            if ($match !== null && $match['id'] !== (int) $domain['dns_id']) {
                $name = $decode($match['name']);
                $html .= '<p class="mb-0">' . sprintf($esc(_('The %s DNS profile has exactly these live name servers.')), '<strong>' . $esc($name) . '</strong>')
                    . ($can_change ? ' ' . self::useButton('new_dns_id', $match['id'], $name) : '') . '</p>';
            }
        }
        $html .= '</div>';

        // The live addresses, each set with its own name, then the IP Address asset
        $asset_address = self::normalizeAddress($decode($domain['ip_ip']));
        $basis = (string) $row['ip_basis'];
        $names = array('a' => sprintf(_('%s (IPv4)'), $domain['domain']), 'aaaa' => sprintf(_('%s (IPv6)'), $domain['domain']),
            'www_a' => sprintf(_('%s (IPv4)'), 'www.' . $domain['domain']), 'www_aaaa' => sprintf(_('%s (IPv6)'), 'www.' . $domain['domain']));

        $html .= '<div class="mb-3"><h3 class="h6">' . $esc(_('Live IP Addresses')) . '</h3>';
        foreach ($names as $set => $name) {
            $html .= '<p class="mb-0 small text-body-secondary">' . $layout->ltr($name) . '</p>'
                . self::setHtml($row, $set, $seen, $stale, function ($entry) use ($asset_address, $esc) {
                    $mark = '';
                    if ($asset_address !== '' && $entry === $asset_address) {
                        $mark .= ' <span class="text-success-emphasis"><i class="bi bi-check-circle" aria-hidden="true"></i> ' . $esc(_("(the domain's IP Address)")) . '</span>';
                    }
                    if (!self::isPublicAddress($entry)) $mark .= ' <span class="text-body-secondary">' . $esc(_('(not a public address)')) . '</span>';
                    return $mark;
                });
        }
        if ($basis === 'bare' || $basis === 'www') {
            $html .= '<p class="mb-0">' . sprintf($esc(_('The IP Address is compared with the addresses for %s.')),
                $layout->ltr($basis === 'www' ? 'www.' . $domain['domain'] : $domain['domain'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="mb-3"><h3 class="h6">' . $esc(_('IP Address')) . '</h3>';
        if ((int) $domain['ip_id'] === 1) {
            $html .= '<p>' . $esc(_('None')) . '</p>';
        } else {
            $html .= '<p class="mb-1">' . $esc($decode($domain['ip_name'])) . ' ' . $layout->ltr($decode($domain['ip_ip'])) . '</p>';
        }
        $ip_sentences = array('match' => _("It's among the live addresses."),
            'differs' => _("It isn't among the live addresses."),
            'none' => _('The domain has no live addresses.'),
            'not_set' => (int) $domain['ip_id'] === 1 ? _("The domain has no IP Address set, so the live addresses weren't compared.")
                : _("The IP Address isn't a valid address, so the live addresses weren't compared."));
        if (isset($ip_sentences[$states['ip_state']])) $html .= '<p class="mb-2">' . $esc($ip_sentences[$states['ip_state']]) . '</p>';

        if (in_array($states['ip_state'], array('differs', 'not_set'), true)) {
            // only the address sets this check answered
            $live = array();
            foreach (array_diff(self::usedSets($row), $stale) as $set) {
                foreach (self::addressLines(self::lines($row[self::COLUMNS[$set]])) as $address) $live[] = $address;
            }
            $live = array_values(array_unique($live));
            $assets = self::assetsFor($pdo, $live);
            $items = '';
            foreach ($live as $address) {
                if (isset($assets[$address])) {
                    foreach ($assets[$address] as $asset) {
                        if ($asset['id'] === (int) $domain['ip_id']) continue;
                        $items .= '<li>' . sprintf($esc(_('%1$s is the %2$s IP Address.')), $layout->ltr($address), '<strong>' . $esc($asset['name']) . '</strong>')
                            . ($can_change ? ' ' . self::useButton('new_ip_id', $asset['id'], $asset['name'] . ' (' . $asset['ip'] . ')') : '') . '</li>';
                    }
                } elseif ($can_change) {
                    $items .= '<li>' . $layout->ltr($address) . ' <a href="' . $esc(WEB_ROOT . '/assets/add/ip-address.php?' . http_build_query(array('ip' => $address)))
                        . '">' . $esc(_('Add this IP Address')) . '</a></li>';
                }
            }
            if ($items !== '') $html .= '<ul class="list-unstyled mb-0">' . $items . '</ul>';
        }
        $html .= '</div>';

        $html .= '<div class="domainmod-span-all mb-3"><h3 class="h6">' . $esc(_('Live Mail Servers')) . '</h3>'
            . self::setHtml($row, 'mx', $seen, $stale, null) . '</div>';

        return $html;
    }

    /*
     * One stored set for the edit page: its first SHOW_CAP entries (a null MX as "No mail (null MX)"), how many more
     * there are, a "points to" line for an address set's CNAME target, and notes for a set held to SET_CAP, one from an
     * earlier check, or one with no answer yet. $mark gives the extra text after an entry (already escaped), or null.
     */
    protected static function setHtml(array $row, $set, array $seen, array $stale, $mark)
    {
        $layout = new Layout();
        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };

        if (!in_array($set, $seen, true)) return '<p class="mb-2 text-body-secondary">' . $esc(_('No answer yet.')) . '</p>';

        $lines = self::lines($row[self::COLUMNS[$set]]);
        $html = '';
        if ($lines && strncmp($lines[0], 'cname:', 6) === 0) {
            $html .= '<p class="mb-0 small">' . sprintf($esc(_('Points to %s')), $layout->ltr(substr($lines[0], 6))) . '</p>';
        }
        $entries = self::addressLines($lines);

        if (!$entries) {
            $html .= '<p class="mb-2">' . $esc(_('None')) . '</p>';
        } else {
            $html .= '<ul class="list-unstyled mb-2">';
            foreach (array_slice($entries, 0, self::SHOW_CAP) as $entry) {
                $text = ($set === 'mx' && $entry === '0 .') ? $esc(_('No mail (null MX)')) : $layout->ltr($entry);
                $html .= '<li>' . $text . ($mark !== null ? $mark($entry) : '') . '</li>';
            }
            $more = count($entries) - self::SHOW_CAP;
            if ($more > 0) {
                $html .= '<li class="text-body-secondary">' . $esc(sprintf(ngettext('and %s more record', 'and %s more records', $more), number_format($more))) . '</li>';
            }
            $html .= '</ul>';
        }

        if (in_array($set, self::setList($row['truncated_sets']), true)) {
            $html .= '<p class="mb-2 small text-body-secondary">' . $esc(_('The answer had more than 100 entries, so only the first 100 were kept.')) . '</p>';
        }
        if (in_array($set, $stale, true)) {
            $html .= '<p class="mb-2 small text-body-secondary">' . $esc(_("This record set couldn't be checked this time, so this is the answer from an earlier check.")) . '</p>';
        }

        return $html;
    }

    // A "Use it" button that sets one of the edit form's menus (js/domain-refresh.js), carrying the option's id and name
    // so it can be added when the menu doesn't have it yet
    protected static function useButton($menu, $id, $name)
    {
        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };
        return '<button type="button" class="btn btn-sm btn-outline-secondary py-0" data-domainmod-use-menu="' . $esc($menu)
            . '" data-domainmod-use-id="' . (int) $id . '" data-domainmod-use-name="' . $esc($name) . '">' . $esc(_('Use it')) . '</button>';
    }

    // The IP Address assets (not "[no ip address]") whose ip is one of these addresses, by address, as lists of
    // array('id', 'name', 'ip') with the name and ip decoded
    public static function assetsFor(\PDO $pdo, array $addresses)
    {
        if (!$addresses) return array();

        $found = array();
        foreach ($pdo->query("SELECT id, `name`, ip FROM ip_addresses WHERE id <> '1' ORDER BY `name`, id")->fetchAll(\PDO::FETCH_ASSOC) as $asset) {
            $ip = html_entity_decode((string) $asset['ip'], ENT_QUOTES, 'UTF-8');
            $address = self::normalizeAddress($ip);
            if ($address !== '' && in_array($address, $addresses, true)) {
                $found[$address][] = array('id' => (int) $asset['id'],
                    'name' => html_entity_decode((string) $asset['name'], ENT_QUOTES, 'UTF-8'), 'ip' => $ip);
            }
        }
        return $found;
    }

    // Logs a database failure in the DNS step or a recompare, never throwing out of it
    public static function logFailure($message, $domain_id, \Throwable $e)
    {
        try {
            (new Log('class.livedns'))->error($message, array('Domain ID' => (int) $domain_id, 'Error' => get_class($e)));
        } catch (\Throwable $log_error) {
        }
    }


} //@formatter:on
