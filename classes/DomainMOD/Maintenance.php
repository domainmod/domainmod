<?php
/**
 * /classes/DomainMOD/Maintenance.php
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

class Maintenance
{
    public $deeb;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.maintenance');
        $this->time = new Time();
    }

    public function performCleanup()
    {
        $this->lowercaseDomains();
        $this->lowercaseTlds();
        $this->updateTlds();
        $this->updateSegments();
        $this->updateAllFees();
        $this->deleteUnusedFees('fees', 'domains');
        $this->deleteUnusedFees('ssl_fees', 'ssl_certs');
        $this->zeroInvalidIpIds();

        $result_message = _('Maintenance Completed') . '<BR>';

        return $result_message;
    }

    public function lowercaseDomains()
    {
        $this->deeb->cnxx->query("UPDATE domains SET domain = LOWER(domain)");
    }

    public function lowercaseTlds()
    {
        $this->deeb->cnxx->query("UPDATE fees SET tld = LOWER(tld)");
    }

    public function updateTlds()
    {
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("SELECT id, domain FROM domains")->fetchAll();

        if ($result) {

            $pdo = $this->deeb->cnxx;
            $stmt = $pdo->prepare("
                UPDATE domains
                SET tld = :tld
                WHERE id = :id");
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);
            $stmt->bindParam('id', $bind_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_tld = $this->getTld($row->domain);
                $bind_id = $row->id;
                $stmt->execute();

            }

        }
    }

    public function getTld($domain)
    {
        return Domain::tldOf($domain);
    }

    public function updateSegments()
    {
        $pdo = $this->deeb->cnxx;
        $pdo->query("
            UPDATE segment_data
            SET active = '0',
                inactive = '0',
                missing = '0',
                filtered = '0'");

        $pdo->query("
            UPDATE segment_data
            SET active = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))");

        $pdo->query("
            UPDATE segment_data
            SET inactive = '1'
            WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))");

        $pdo->query("
            UPDATE segment_data
            SET missing = '1'
            WHERE domain NOT IN (SELECT domain FROM domains)");
    }

    public function updateAllFees()
    {
        $this->updateDomainFees();
        $this->updateSslFees();
    }

    public function updateDomainFees()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("UPDATE domains SET fee_fixed = '0'");

        $stmt = $pdo->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $pdo->query("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET fee_id = :fee_id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");
            $stmt2->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt2->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt3 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");
            $stmt3->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt3->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt4 = $pdo->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");
            $timestamp = $this->time->stamp();
            $stmt4->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt4->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt4->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_registrar_id = $row->registrar_id;
                $bind_tld = $row->tld;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

                $stmt4->execute();

            }

        }
    }

    public function updateDomainFee($domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT registrar_id, tld
            FROM domains
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $registrar_id = $result->registrar_id;
            $tld = $result->tld;

        }

        $stmt = $pdo->prepare("
            UPDATE domains
            SET fee_fixed = '0'
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            UPDATE fees
            SET fee_fixed = '0',
                update_time = :update_time
            WHERE registrar_id = :registrar_id
              AND tld = :tld");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdo->prepare("
            SELECT id, registrar_id, tld
            FROM fees
            WHERE fee_fixed = '0'
              AND registrar_id = :registrar_id
              AND tld = :tld");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET fee_id = :fee_id
                WHERE registrar_id = :registrar_id
                  AND tld = :tld
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt2 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '1'");
            $stmt2->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt2->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt3 = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.fee_fixed = '1',
                    d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.registrar_id = :registrar_id
                  AND d.tld = :tld
                  AND d.privacy = '0'");
            $stmt3->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt3->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            $stmt4 = $pdo->prepare("
                UPDATE fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE registrar_id = :registrar_id
                  AND tld = :tld");
            $timestamp = $this->time->stamp();
            $stmt4->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt4->bindParam('registrar_id', $bind_registrar_id, \PDO::PARAM_INT);
            $stmt4->bindParam('tld', $bind_tld, \PDO::PARAM_STR);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_registrar_id = $row->registrar_id;
                $bind_tld = $row->tld;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

                $stmt4->execute();

            }

        }
    }

    public function updateSslFees()
    {
        $pdo = $this->deeb->cnxx;

        $pdo->query("UPDATE ssl_certs SET fee_fixed = '0'");

        $stmt = $pdo->prepare("
            UPDATE ssl_fees
            SET fee_fixed = '0',
                update_time = :update_time");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $pdo->query("
            SELECT id, ssl_provider_id, type_id
            FROM ssl_fees
            WHERE fee_fixed = '0'")->fetchAll();

        if ($result) {

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET fee_id = :fee_id
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id
                  AND fee_fixed = '0'");
            $stmt->bindParam('fee_id', $bind_fee_id, \PDO::PARAM_INT);
            $stmt->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            $stmt2 = $pdo->prepare("
                UPDATE ssl_certs sslc
                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                SET sslc.fee_fixed = '1',
                    sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                WHERE sslc.ssl_provider_id = :ssl_provider_id
                  AND sslc.type_id = :type_id");
            $stmt2->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt2->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            $stmt3 = $pdo->prepare("
                UPDATE ssl_fees
                SET fee_fixed = '1',
                    update_time = :update_time
                WHERE ssl_provider_id = :ssl_provider_id
                  AND type_id = :type_id");
            $timestamp = $this->time->stamp();
            $stmt3->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt3->bindParam('ssl_provider_id', $bind_ssl_provider_id, \PDO::PARAM_INT);
            $stmt3->bindParam('type_id', $bind_type_id, \PDO::PARAM_INT);

            foreach ($result as $row) {

                $bind_fee_id = $row->id;
                $bind_ssl_provider_id = $row->ssl_provider_id;
                $bind_type_id = $row->type_id;

                $stmt->execute();

                $stmt2->execute();

                $stmt3->execute();

            }

        }
    }

    public function deleteUnusedFees($fee_table, $compare_table)
    {
        $this->deeb->cnxx->query("
            DELETE FROM " . $fee_table . "
            WHERE id NOT IN (
                             SELECT fee_id
                             FROM " . $compare_table . "
                            )");
    }

    /*
     * IDN upgrade, step 1 of 3 (before the collation changes): puts every stored domain name and TLD into the form
     * Domain::normalize() gives (lowercase Unicode, münchen.de for xn--mnchen-3ya.de). Safe to run again: rows that are
     * already normalised aren't written, so a run that stops part-way (a PHP time limit) can simply be run again.
     * Returns a report (one line per notable change) for the upgrade message; the same lines are written to the log.
     */
    public function normalizeIdnData()
    {
        if (function_exists('set_time_limit')) @set_time_limit(0);
        $report = array();

        $this->normalizeIdnTable('domains', true, $report);
        $this->normalizeIdnTable('domain_queue', true, $report);
        $this->normalizeIdnTable('domain_queue_temp', false, $report);
        $this->normalizeIdnTable('segment_data', false, $report);
        $this->logIdnReport($report);

        // fee merges delete rows, so their report lines are logged in the same transaction as the merges (a rerun
        // couldn't rebuild them)
        $fee_report = array();
        $this->normalizeIdnFees($fee_report);
        $this->normalizeIdnSegments();

        return array_merge($report, $fee_report);
    }

    // IDN upgrade, step 2 of 3: exact (binary) matching on every stored domain name and TLD column. Altering a column
    // to the collation it already has does nothing, so this is safe to run again
    public function changeIdnCollations()
    {
        $pdo = $this->deeb->cnxx;
        $columns = array(
            array('domains', 'domain', 'VARCHAR(255)'), array('domains', 'tld', 'VARCHAR(50)'),
            array('domain_queue', 'domain', 'VARCHAR(255)'), array('domain_queue', 'tld', 'VARCHAR(50)'),
            array('domain_queue_history', 'domain', 'VARCHAR(255)'), array('domain_queue_history', 'tld', 'VARCHAR(50)'),
            array('domain_queue_temp', 'domain', 'VARCHAR(255)'),
            array('segment_data', 'domain', 'VARCHAR(255)'),
            array('fees', 'tld', 'VARCHAR(50)'));
        foreach ($columns as $column) {
            $pdo->query("ALTER TABLE `" . $column[0] . "` MODIFY `" . $column[1] . "` " . $column[2] . " CHARACTER SET utf8 COLLATE utf8_bin NOT NULL");
        }
    }

    // IDN upgrade, step 3 of 3 (after the collation changes): everything that matches by name. Returns a report like
    // normalizeIdnData()'s
    public function finishIdnUpgrade()
    {
        if (function_exists('set_time_limit')) @set_time_limit(0);
        $pdo = $this->deeb->cnxx;
        $report = array();

        // domains that became the same name (Example.com and example.com, or münchen.de and xn--mnchen-3ya.de) are
        // kept, not merged, so the user can decide which to keep
        $result = $pdo->query("
            SELECT domain, COUNT(*) AS total
            FROM domains
            GROUP BY domain
            HAVING COUNT(*) > 1
            ORDER BY domain")->fetchAll();
        foreach ($result as $row) {
            $report[] = sprintf('Domain %s is now listed %d times; check them and delete the extra ones', $row->domain, $row->total);
        }

        // every fee again, from exact TLD matches only: a domain whose old fee only matched through the old collation
        // (a strasse.de fee for a.straße.de) is left without one, and shows under missing fees
        $pdo->query("UPDATE domains SET fee_id = '0', total_cost = '0'");
        $this->updateAllFees();
        $this->updateSegments();

        $this->logIdnReport($report);
        return $report;
    }

    // Normalises one table's domain column (and its tld column, if it has one), a batch at a time
    protected function normalizeIdnTable($table, $has_tld, &$report)
    {
        $pdo = $this->deeb->cnxx;
        $columns = $has_tld ? 'id, domain, tld' : 'id, domain';
        $update = $has_tld
            ? "UPDATE `" . $table . "` SET domain = :domain, tld = :tld WHERE id = :id"
            : "UPDATE `" . $table . "` SET domain = :domain WHERE id = :id";
        $last_id = 0;

        do {

            $stmt = $pdo->prepare("SELECT " . $columns . " FROM `" . $table . "` WHERE id > :last_id ORDER BY id LIMIT 500");
            $stmt->bindValue('last_id', $last_id, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();
            if (!$rows) break;

            $pdo->beginTransaction();
            $write = $pdo->prepare($update);

            foreach ($rows as $row) {

                $last_id = (int) $row->id;
                $domain = Domain::normalize($row->domain);

                if ($domain === '') {

                    if ($table === 'domains') {
                        $report[] = sprintf('Domain %s isn\'t a valid domain name, so it was left as it is', $row->domain);
                    }
                    continue;

                }

                $tld = $has_tld ? Domain::tldOf($domain) : '';
                if ($domain === $row->domain && (!$has_tld || $tld === $row->tld)) continue;

                // IDNA mapping can make a name longer; one that wouldn't fit its column is left as it is
                if (mb_strlen($domain, 'UTF-8') > 255 || ($has_tld && mb_strlen($tld, 'UTF-8') > 50)) {
                    if ($table === 'domains') {
                        $report[] = sprintf('Domain %s would be too long once converted, so it was left as it is', $row->domain);
                    }
                    continue;
                }

                $write->bindValue('domain', $domain, \PDO::PARAM_STR);
                if ($has_tld) $write->bindValue('tld', $tld, \PDO::PARAM_STR);
                $write->bindValue('id', $row->id, \PDO::PARAM_INT);
                $write->execute();

            }

            $pdo->commit();

        } while (count($rows) === 500);
    }

    /*
     * Normalises fee TLDs. Two fees for one registrar that become the same TLD (xn--p1ai and рф, or DE and de) are
     * merged into one, since the fee screens and updateDomainFees() work by registrar and TLD and couldn't keep them
     * apart: the one already in its normalised form is kept (or else the oldest), its domains take it over, and the
     * other is deleted, reported with its old amounts when they differed
     */
    protected function normalizeIdnFees(&$report)
    {
        $pdo = $this->deeb->cnxx;
        $fees = $pdo->query("
            SELECT f.id, f.registrar_id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee,
                   f.misc_fee, f.currency_id, r.name AS registrar_name, c.currency
            FROM fees AS f
            LEFT JOIN registrars AS r ON f.registrar_id = r.id
            LEFT JOIN currencies AS c ON f.currency_id = c.id
            ORDER BY f.id")->fetchAll();

        $groups = array();
        foreach ($fees as $fee) {
            // the stored form (a TLD that doesn't normalise, or would be too long for its column, is left as it is)
            $fee->normalized = Domain::normalizeTld($fee->tld);
            $key = $fee->registrar_id . '|' . $fee->normalized;
            $groups[$key][] = $fee;
        }

        $pdo->beginTransaction();

        foreach ($groups as $group) {

            // the fee to keep: the one whose TLD was already in its normalised form, or else the oldest
            $keep = $group[0];
            foreach ($group as $fee) {
                if ($fee->tld === $fee->normalized) { $keep = $fee; break; }
            }

            foreach ($group as $fee) {

                if ($fee->id === $keep->id) continue;

                $same = $fee->currency_id == $keep->currency_id;
                foreach (array('initial_fee', 'renewal_fee', 'transfer_fee', 'privacy_fee', 'misc_fee') as $amount) {
                    if ((string) $fee->$amount !== (string) $keep->$amount) $same = false;
                }
                if (!$same) {
                    // the domains that move to the kept fee, named in the report while they can still be found
                    $stmt = $pdo->prepare("SELECT domain FROM domains WHERE fee_id = :fee_id ORDER BY domain");
                    $stmt->bindValue('fee_id', $fee->id, \PDO::PARAM_INT);
                    $stmt->execute();
                    $moved = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    $stmt->closeCursor();
                    $line = sprintf('The %s fee for %s (%s: initial %s, renewal %s, transfer %s, privacy %s, misc %s) was merged into its %s fee; check it',
                        $fee->registrar_name, $fee->tld, $fee->currency, $fee->initial_fee, $fee->renewal_fee,
                        $fee->transfer_fee, $fee->privacy_fee, $fee->misc_fee, $keep->normalized);
                    if ($moved) {
                        $line .= sprintf(' (domains moved to it: %s)', implode(', ', $moved));
                    }
                    $report[] = $line;
                    $this->logIdnReport(array($line));
                }

                $stmt = $pdo->prepare("UPDATE domains SET fee_id = :keep_id WHERE fee_id = :fee_id");
                $stmt->bindValue('keep_id', $keep->id, \PDO::PARAM_INT);
                $stmt->bindValue('fee_id', $fee->id, \PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $pdo->prepare("DELETE FROM fees WHERE id = :fee_id");
                $stmt->bindValue('fee_id', $fee->id, \PDO::PARAM_INT);
                $stmt->execute();

            }

            if ($keep->tld !== $keep->normalized) {
                $stmt = $pdo->prepare("UPDATE fees SET tld = :tld WHERE id = :id");
                $stmt->bindValue('tld', $keep->normalized, \PDO::PARAM_STR);
                $stmt->bindValue('id', $keep->id, \PDO::PARAM_INT);
                $stmt->execute();
            }

        }

        $pdo->commit();
    }

    // Removes a segment's entries that became the same name, and rebuilds its saved list and count. Entries are read
    // a batch at a time, and the list is built as it goes, so only the names seen so far are held in memory
    protected function normalizeIdnSegments()
    {
        $pdo = $this->deeb->cnxx;
        $segments = $pdo->query("SELECT id FROM segments ORDER BY id")->fetchAll();

        foreach ($segments as $segment) {

            $pdo->beginTransaction();
            $seen = array();
            $list = '';
            $last_id = 0;
            $delete = $pdo->prepare("DELETE FROM segment_data WHERE id = :id");

            do {

                $stmt = $pdo->prepare("SELECT id, domain FROM segment_data WHERE segment_id = :segment_id AND id > :last_id ORDER BY id LIMIT 1000");
                $stmt->bindValue('segment_id', $segment->id, \PDO::PARAM_INT);
                $stmt->bindValue('last_id', $last_id, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll();
                $stmt->closeCursor();

                foreach ($rows as $row) {
                    $last_id = (int) $row->id;
                    if (isset($seen[$row->domain])) {
                        $delete->bindValue('id', $row->id, \PDO::PARAM_INT);
                        $delete->execute();
                        continue;
                    }
                    $seen[$row->domain] = true;
                    // the same format as Format::formatForMysql()
                    $list .= ($list === '' ? '' : ',') . "'" . str_replace(' ', '', $row->domain) . "'";
                }

            } while (count($rows) === 1000);

            $stmt = $pdo->prepare("UPDATE segments SET segment = :segment, number_of_domains = :total WHERE id = :id");
            $stmt->bindValue('segment', $list, \PDO::PARAM_LOB);
            $stmt->bindValue('total', count($seen), \PDO::PARAM_INT);
            $stmt->bindValue('id', $segment->id, \PDO::PARAM_INT);
            $stmt->execute();
            $pdo->commit();

        }
    }

    // The IDN upgrade's report lines, logged whether or not debugging is on
    protected function logIdnReport($report)
    {
        foreach ($report as $line) {
            $this->log->addEntry('notice', 'IDN upgrade: ' . $line, array());
        }
    }

    public function zeroInvalidIpIds()
    { // This zeroes out API IP address IDs in the registrar_account table that are no longer valid. For example, if an
      // IP has been deleted.
        $pdo = $this->deeb->cnxx;

        $result = $pdo->query("
            SELECT id
            FROM ip_addresses")->fetchAll();

        if ($result) {

            $id_array = array();

            foreach ($result as $row) {

                $id_array[] = $row->id;

            }

            $in_list = str_repeat('?, ', count($id_array) - 1) . '?';
            $sql = "UPDATE registrar_accounts
                    SET api_ip_id = '0'
                    WHERE api_ip_id NOT IN (" . $in_list . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($id_array);

        }

    }

} //@formatter:on
