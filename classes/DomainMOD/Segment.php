<?php
/**
 * /classes/DomainMOD/Segment.php
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

class Segment
{
    /*
     * The segment results export reads this many keys at a time (see resultsChunks()). Each chunk matches the domains
     * against the segment's whole stored list again, so the chunks are larger than the Domains export's, which keeps
     * that repeated work small. The keys are narrow, and the full rows are still read in groups by their size.
     */
    const RESULTS_KEYS = 5000;

    // And this many of its missing domains, which are only names, so more of them fit in the same memory
    const RESULTS_MISSING_KEYS = 20000;

    public $deeb;
    public $log;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.segment');
    }

    public function trimLength($input_segment, $max_length)
    {
        if (strlen($input_segment) > $max_length) {

            $output_segment = substr($input_segment, 0, $max_length);
            $pos = strrpos($output_segment, ", ");

            if ($pos === false) {

                return substr($output_segment, 0, $max_length) . "...";

            }

            return substr($output_segment, 0, $pos) . "...";

        } else {

            return $input_segment;

        }
    }

    // The query behind the segment's Matching But Inactive (inactive) or Matching But Filtered (filtered) list, for the
    // signed-in user's currency conversions (bind user_id and segid). The summary counts every domain with the flag, so
    // the list joins everything else with left joins: a domain without a fee, category, DNS profile, IP address, host,
    // or account is still listed.
    public static function resultsSql($flag)
    {
        return self::resultsSelectSql() . self::resultsFromSql($flag) . "
            ORDER BY d.domain ASC";
    }

    // The columns resultsSql() reads
    protected static function resultsSelectSql()
    {
        return "
            SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.autorenew, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name";
    }

    // The tables and conditions resultsSql() reads from (bind user_id and segid)
    protected static function resultsFromSql($flag)
    {
        return self::resultsJoinSql() . self::resultsWhereSql($flag);
    }

    // The condition that picks the segment's inactive or filtered domains (bind segid)
    protected static function resultsWhereSql($flag)
    {
        $flag = $flag === 'filtered' ? 'filtered' : 'inactive';

        return "
            WHERE d.domain IN (SELECT domain FROM segment_data WHERE segment_id = :segid AND " . $flag . " = '1')";
    }

    // The tables resultsSql() reads from (bind user_id)
    protected static function resultsJoinSql()
    {
        return "
            FROM domains AS d
            LEFT JOIN registrar_accounts AS ra ON d.account_id = ra.id
            LEFT JOIN registrars AS r ON ra.registrar_id = r.id
            LEFT JOIN owners AS o ON ra.owner_id = o.id
            LEFT JOIN fees AS f ON d.registrar_id = f.registrar_id AND d.tld = f.tld
            LEFT JOIN currency_conversions AS cc ON f.currency_id = cc.currency_id AND cc.user_id = :user_id
            LEFT JOIN categories AS cat ON d.cat_id = cat.id
            LEFT JOIN dns ON d.dns_id = dns.id
            LEFT JOIN ip_addresses AS ip ON d.ip_id = ip.id
            LEFT JOIN hosting AS h ON d.hosting_id = h.id";
    }

    /*
     * How many rows the segment results export of $type ('inactive', 'filtered', or 'missing') writes: the rows
     * resultsChunks() reads, counted with the same joins, so a domain that a damaged fees table joins twice is counted
     * twice, as it's written twice
     */
    public static function resultsCount(\PDO $pdo, $type, $user_id, $seg_id)
    {
        if ($type === 'missing') {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM segment_data
                WHERE segment_id = :segid
                  AND missing = '1'");

        } else {

            $stmt = $pdo->prepare("SELECT COUNT(*)" . self::resultsFromSql($type));
            $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);

        }
        $stmt->bindValue('segid', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $count;
    }

    /*
     * The segment's results of $type ('inactive', 'filtered', or 'missing') for its export, a group at a time, as a
     * generator of groups (each an array of rows), in domain order, then by id, so two rows with the same name always
     * come in the same order. Each chunk starts straight after the last row of the one before, so PHP never holds the
     * whole list. The inactive and filtered domains are read in two steps, like the Domains export (see
     * ListQuery::exportChunks()). A narrow query reads the next domains' ids, each with an estimate of its row's size
     * in bytes (its notes, plus ListQuery::EXPORT_ROW_BYTES for the rest). Then the full rows for those ids are read in
     * groups whose estimated sizes add up to at most ListQuery::EXPORT_GROUP_BYTES (see ListQuery::byteGroups()), apart
     * from a row estimated at more than that, which is a group on its own. The missing domains are only names, so each
     * chunk is one query, and its group is the names, by their segment_data ids. A chunk is RESULTS_KEYS keys, or
     * RESULTS_MISSING_KEYS names, unless $keys_per_chunk says otherwise. It never starts or ends a transaction: the page
     * runs it inside the export's read-only snapshot (see Export::startSnapshot()).
     */
    public static function resultsChunks(\PDO $pdo, $type, $user_id, $seg_id, $keys_per_chunk = null)
    {
        $limit = $keys_per_chunk ?? ($type === 'missing' ? self::RESULTS_MISSING_KEYS : self::RESULTS_KEYS);
        $limit = max(1, (int) $limit);
        $last = null;

        do {

            $keyset = self::resultsKeysetSql($type === 'missing' ? 'domain' : 'd.domain',
                $type === 'missing' ? 'id' : 'd.id', $last);

            if ($type === 'missing') {

                $stmt = $pdo->prepare("
                    SELECT id, domain
                    FROM segment_data
                    WHERE segment_id = :segid
                      AND missing = '1'" . $keyset['sql'] . "
                    ORDER BY domain ASC, id ASC
                    LIMIT " . $limit);

            } else {

                $stmt = $pdo->prepare("
                    SELECT d.id AS ks_id, d.domain AS ks_name,
                        COALESCE(LENGTH(d.notes), 0) + " . ListQuery::EXPORT_ROW_BYTES . " AS ks_bytes
                    FROM domains AS d" . self::resultsWhereSql($type) . $keyset['sql'] . "
                    ORDER BY d.domain ASC, d.id ASC
                    LIMIT " . $limit);

            }
            $stmt->bindValue('segid', $seg_id, \PDO::PARAM_INT);
            foreach ($keyset['params'] as $name => $value) {
                $stmt->bindValue($name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            $keys = $stmt->fetchAll($type === 'missing' ? \PDO::FETCH_KEY_PAIR : \PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$keys) return;

            if ($type === 'missing') {

                yield $keys;
                $last = array(end($keys), (int) array_key_last($keys));

            } else {

                foreach (ListQuery::byteGroups($keys) as $group) {
                    yield self::resultsRows($pdo, $user_id, array_column($group, 'ks_id'));
                }
                $last_key = end($keys);
                $last = array($last_key['ks_name'], (int) $last_key['ks_id']);

            }

        } while (count($keys) === $limit);
    }

    /*
     * The full rows (see resultsSql()) of the domains with these ids, in the export's order. The keys query has already
     * matched them against the segment in the same snapshot, so the segment's list isn't read again here.
     */
    protected static function resultsRows(\PDO $pdo, $user_id, array $ids)
    {
        $params = array();
        foreach (array_values($ids) as $n => $id) {
            $params['ks_id' . $n] = (int) $id;
        }

        $stmt = $pdo->prepare(self::resultsSelectSql() . self::resultsJoinSql() . "
            WHERE d.id IN (:" . implode(', :', array_keys($params)) . ")
            ORDER BY d.domain ASC, d.id ASC");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        foreach ($params as $name => $id) {
            $stmt->bindValue($name, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();
        return $rows;
    }

    /*
     * The condition that starts a chunk of resultsChunks() straight after the last row of the one before ($last: its
     * name and id, or null for the first chunk, which has no condition), as " AND ..." SQL and its bound parameters. As
     * in ListQuery::keysetSql(), it's written out rather than as a row comparison, and every placeholder has its own
     * name.
     */
    protected static function resultsKeysetSql($name_column, $id_column, $last)
    {
        if ($last === null) return array('sql' => '', 'params' => array());

        return array(
            'sql' => " AND (" . $name_column . " > :ks_d OR (" . $name_column . " = :ks_d2 AND " . $id_column
                . " > :ks_i))",
            'params' => array('ks_d' => (string) $last[0], 'ks_d2' => (string) $last[0], 'ks_i' => (int) $last[1]));
    }

    /*
     * A segment's name, its number of domains, and its stored domain list (already quoted for SQL), as an object, or
     * false when the segment no longer exists. Unlike getSegment(), it never writes to the log, so it can run inside an
     * export's read-only snapshot.
     */
    public static function storedRow(\PDO $pdo, $seg_id)
    {
        $stmt = $pdo->prepare("
            SELECT `name`, number_of_domains, `segment`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        $stmt->closeCursor();
        return $result;
    }

    public function getSegment($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `segment`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getName($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT `name`
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Segment name';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getNumberOfDomains($seg_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT number_of_domains
            FROM segments
            WHERE id = :seg_id");
        $stmt->bindValue('seg_id', $seg_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve Number of Domains for Segment';
            $log_extra = array('Segment ID' => $seg_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on
