<?php
/**
 * /classes/DomainMOD/Domain.php
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

class Domain
{
    public $deeb;
    public $log;
    public $time;
    // The Change History action an import's addDomain() calls note their new domains in (see Csv::parse())
    public $history_op = null;

    // IDNA options for converting domain names in both directions: UTS #46 nontransitional processing, with the
    // hostname rules (letters, digits, and hyphens in each label) and the Bidi and ContextJ checks
    const IDNA_TO_ASCII = IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ;
    const IDNA_TO_UNICODE = IDNA_NONTRANSITIONAL_TO_UNICODE | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ;

    // normalize() results for this PHP process, keyed by the raw value, so a list that's scanned once per domain
    // converts each entry only once
    private static $normalized = array();

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.domain');
        $this->time = new Time();
    }

    public function findInvalidDomains($lines)
    {
        $invalid_to_display = 5;
        $invalid_domains = 0;
        $invalid_count = 0;
        $result_message = '';

        foreach ($lines as $key => $domain) {

            if (!$this->checkFormat($domain)) {

                if ($invalid_count < $invalid_to_display) {

                    $result_message .= sprintf(_('Line %s contains an invalid domain'), number_format($key + 1)) . '<BR>';

                }

                $invalid_domains = 1;
                $invalid_count++;

            }

        }

        return array($invalid_to_display, $invalid_domains, $invalid_count, $result_message);
    }

    /*
     * The domains on a list that aren't in DomainMOD: the first few, to show, and how many there are in all. Used before
     * an action that changes existing domains, so it doesn't run on a domain that isn't there.
     */
    public function findMissingDomains($lines)
    {
        $missing_to_display = 5;
        $missing = array();
        $missing_count = 0;

        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM domains
            WHERE domain = :domain
            LIMIT 1");

        foreach ($lines as $domain) {

            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchColumn();
            $stmt->closeCursor();

            if (!$result) {

                if ($missing_count < $missing_to_display) $missing[] = $domain;
                $missing_count++;

            }

        }

        return array($missing, $missing_count);
    }

    /*
     * The field error for a domain list with domains that aren't in DomainMOD, from findMissingDomains()' results: how
     * many are missing, the ones to show, and how many others there are. Shared by the Bulk Updater pages.
     */
    public function missingDomainsMessage($missing_to_display, $missing_count)
    {
        $missing_message = sprintf(ngettext("%s domain on your list isn't in %s:", "%s domains on your list aren't in %s:", $missing_count), number_format($missing_count), SOFTWARE_TITLE) . ' ' . implode(', ', $missing_to_display) . '.';
        if (($missing_count - count($missing_to_display)) == 1) {
            $missing_message .= ' ' . sprintf(_('Plus %s other'), number_format($missing_count - count($missing_to_display))) . '.';
        } elseif (($missing_count - count($missing_to_display)) > 1) {
            $missing_message .= ' ' . sprintf(_('Plus %s others'), number_format($missing_count - count($missing_to_display))) . '.';
        }

        return $missing_message;
    }

    /*
     * A domain name (or TLD) in punycode, the form registrars, DNS, and WHM expect, or '' if it isn't a valid name.
     * Used for anything that leaves DomainMOD, and for validation.
     */
    public static function toAscii($input)
    {
        $input = trim((string) $input);
        // idn_to_ascii() throws a ValueError on an empty string
        if ($input === '') return '';
        $ascii = idn_to_ascii($input, self::IDNA_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        return is_string($ascii) ? $ascii : '';
    }

    /*
     * The form DomainMOD stores a domain name (or TLD) in: lowercase Unicode (münchen.de), whether it arrives in
     * Unicode or punycode, without a trailing dot. Names with a character outside the Basic Multilingual Plane, which
     * the utf8 (3-byte) columns can't hold, are stored in punycode instead. Returns '' if the name isn't valid.
     * Running it on its own result gives the same result.
     */
    public static function normalize($input)
    {
        $ascii = self::toAscii($input);
        if ($ascii === '') return '';

        // the root dot, removed after IDNA mapping so a trailing ideographic full stop (。) is handled too
        if (str_ends_with($ascii, '.')) $ascii = substr($ascii, 0, -1);
        if ($ascii === '' || str_ends_with($ascii, '.')) return '';

        $unicode = idn_to_utf8($ascii, self::IDNA_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
        if (!is_string($unicode) || $unicode === '') return '';
        $unicode = mb_strtolower($unicode, 'UTF-8');

        // any 4-byte UTF-8 character (outside the Basic Multilingual Plane)
        if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $unicode)) return strtolower($ascii);

        return $unicode;
    }

    // A typed or imported domain in its stored form, or the input unchanged (trimmed) if it isn't a valid domain, so
    // the usual invalid-domain checks still see and report what was entered
    public static function normalizeOrKeep($input)
    {
        $normalized = self::normalize($input);
        return $normalized !== '' ? $normalized : trim((string) $input);
    }

    // A domain name as a registrar API expects it (punycode), or the name unchanged if it can't be converted (an old
    // invalid name, which the registrar then reports as unknown)
    public static function forApi($domain)
    {
        $ascii = self::toAscii($domain);
        return $ascii !== '' ? $ascii : (string) $domain;
    }

    // normalize(), remembered for the rest of this PHP process
    public static function normalizeCached($input)
    {
        $key = (string) $input;
        if (!array_key_exists($key, self::$normalized)) {
            self::$normalized[$key] = self::normalize($key);
        }
        return self::$normalized[$key];
    }

    /*
     * Whether a domain name from a registrar (or another outside system) is the same domain as $target, which must
     * already be normalised. Canonical ASCII names are compared directly; anything else (Unicode, punycode, a trailing
     * dot, surrounding spaces) is normalised first
     */
    public static function sameDomain($outside_name, $target)
    {
        $outside_name = (string) $outside_name;
        $lower = strtolower($outside_name);
        if (preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $lower) && !preg_match('/(^|\.)xn--/', $lower)) {
            return $lower === $target;
        }
        $normalized = self::normalizeCached($outside_name);
        return $normalized !== '' && $normalized === $target;
    }

    // The TLD (everything after the first dot), normalised, or the extracted text unchanged if it doesn't normalise or
    // would be too long for the 50-character TLD columns once normalised
    public static function tldOf($domain)
    {
        return self::normalizeTld(preg_replace("/^((.*?)\.)(.*)$/", "\\3", (string) $domain));
    }

    // A TLD in its stored form, or unchanged (trimmed) if it doesn't normalise or would be too long for the
    // 50-character TLD columns once normalised (the IDN upgrade leaves those as they are too)
    public static function normalizeTld($tld)
    {
        $tld = trim((string) $tld);
        $normalized = self::normalize($tld);
        return ($normalized !== '' && mb_strlen($normalized, 'UTF-8') <= 50) ? $normalized : $tld;
    }

    public function checkFormat($input_domain)
    {
        $input_domain = (string) $input_domain;
        if ($input_domain === '' || self::normalize($input_domain) === '') return false;

        if (
            // positive
            preg_match("/^(.+?)\.(.+?)$/", $input_domain) && // has at least one period in the middle

            // negative
            $input_domain[0] != '.' && // is the first character a period
            $input_domain[strlen($input_domain) - 1] != '.' && // is the last character a period
            !preg_match("/\\s/", $input_domain) && // are there any spaces
            !preg_match("/\*/", $input_domain) // are there any asterisks
        ) {

            return true;

        } else {

            return false;

        }
    }

    public function renew($domain, $renewal_years, $notes)
    {
        $expiry_date = $this->getExpiry($domain);
        $new_expiry = $this->getNewExpiry($expiry_date, $renewal_years);
        $this->writeNewExpiry($domain, $new_expiry, $notes);
    }

    public function getDomain($domain_id)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT domain
            FROM domains
            WHERE id = :domain_id");
        $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve domain';
            $log_extra = array('Domain ID' => $domain_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getExpiry($domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT expiry_date
            FROM domains
            WHERE domain = :domain");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = "Unable to retrieve domain's expiry date";
            $log_extra = array('Domain' => $domain);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getNewExpiry($expiry_date, $renewal_years)
    {
        $expiry_pieces = explode("-", $expiry_date);
        return $expiry_pieces[0] + $renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
    }

    public function writeNewExpiry($domain, $new_expiry, $notes)
    {
        $pdo = $this->deeb->cnxx;

        if ($notes != '') {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :new_expiry,
                    notes = CONCAT(:notes, '\r\n\r\n', notes),
                    update_time = :update_time
                WHERE domain = :domain");
            $stmt->bindValue('new_expiry', $new_expiry, \PDO::PARAM_STR);
            $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

        } else {

            $stmt = $pdo->prepare("
                UPDATE domains
                SET expiry_date = :new_expiry,
                    update_time = :update_time
                WHERE domain = :domain");
            $stmt->bindValue('new_expiry', $new_expiry, \PDO::PARAM_STR);
            $bind_timestamp = $this->time->stamp();
            $stmt->bindValue('update_time', $bind_timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
            $stmt->execute();

        }
    }

    public function getTld($domain)
    {
        return self::tldOf($domain);
    }

    public function getDomainPart($domain)
    {
        $temp = preg_replace("/^((.*?)\.)(.*)$/", "\\1", $domain);
        return rtrim($temp, ".");
    }

    public function checkDomainExistence($domain)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
            SELECT domain
            FROM domains
            WHERE domain = :domain
            LIMIT 1");
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    public function getFeeIdAndTotalCost($privacy, $tld, $registrar_id)
    {
        $pdo = $this->deeb->cnxx;

        if ($privacy === 1) {
            $fee_string = 'renewal_fee + privacy_fee + misc_fee';
        } else {
            $fee_string = 'renewal_fee + misc_fee';
        }

        $stmt = $pdo->prepare("
                    SELECT id, (" . $fee_string . ") AS total_cost
                    FROM fees
                    WHERE registrar_id = :registrar_id
                      AND tld = :tld");
        $stmt->bindValue('registrar_id', $registrar_id, \PDO::PARAM_INT);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $fee_id = $result->id;
            $total_cost = $result->total_cost;

        } else {

            $fee_id = 0;
            $total_cost = 0;

        }

        return array($fee_id, $total_cost);
    }

    /*
     * Adds a domain (the CSV importer, through DomainAdd), with no hosting service: service_id keeps its default of 0.
     * Before the update, the old hosting_id column is still there, and is written as 0 so it doesn't take its old
     * default of 1 (a real provider on some installs).
     */
    public function addDomain($oid, $rid, $raid, $domain, $tld, $expiry_date, $cat_id, $dns_id, $ip_id, $fee_id,
                              $total_cost, $function, $notes, $autorenew, $privacy, $creation_type_id, $created_by,
                              $status)
    {
        $pdo = $this->deeb->cnxx;

        // Before the update, the old hosting_id column is written as 0 so it doesn't take its old default of 1 (a real
        // provider on some installs). A stopped upgrade that has dropped it writes the domain as the update does.
        if (!Hosting::legacyDomainColumn($pdo)) {
            $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                     fee_id, total_cost, `function`, notes, autorenew, privacy, creation_type_id,
                     created_by, `active`, insert_time, update_time)
                    VALUES
                    (:oid, :rid, :raid, :domain, :tld, :expiry_date, :cat_id,
                     :dns_id, :ip_id, :fee_id, :total_cost, :function, :notes,
                     :autorenew, :privacy, :creation_type_id, :user_id, :status, :insert_time, :update_time)");
        } else {
            // Legacy: domains.hosting_id before 4.27.0
            $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                     hosting_id, fee_id, total_cost, `function`, notes, autorenew, privacy, creation_type_id,
                     created_by, `active`, insert_time, update_time)
                    VALUES
                    (:oid, :rid, :raid, :domain, :tld, :expiry_date, :cat_id,
                     :dns_id, :ip_id, 0, :fee_id, :total_cost, :function, :notes,
                     :autorenew, :privacy, :creation_type_id, :user_id, :status, :insert_time, :update_time)");
        }
        $stmt->bindValue('oid', $oid, \PDO::PARAM_INT);
        $stmt->bindValue('rid', $rid, \PDO::PARAM_INT);
        $stmt->bindValue('raid', $raid, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->bindValue('expiry_date', $expiry_date, \PDO::PARAM_STR);
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('fee_id', $fee_id, \PDO::PARAM_INT);
        $stmt->bindValue('total_cost', strval($total_cost), \PDO::PARAM_STR);
        $stmt->bindValue('function', $function, \PDO::PARAM_STR);
        $stmt->bindValue('notes', $notes, \PDO::PARAM_LOB);
        $stmt->bindValue('autorenew', $autorenew, \PDO::PARAM_INT);
        $stmt->bindValue('privacy', $privacy, \PDO::PARAM_INT);
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->bindValue('user_id', $created_by, \PDO::PARAM_INT);
        $stmt->bindValue('status', $status, \PDO::PARAM_INT);
        $timestamp = $this->time->stamp();
        $stmt->bindValue('insert_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $temp_domain_id = $pdo->lastInsertId('id');
        if ($this->history_op !== null) $this->history_op->created('domain', $temp_domain_id);

        $stmt = $pdo->prepare("
                    INSERT INTO domain_field_data
                    (domain_id, insert_time)
                    VALUES
                    (:temp_domain_id, :timestamp)");
        $stmt->bindValue('temp_domain_id', $temp_domain_id, \PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        $result = $pdo->query("
                    SELECT field_name
                    FROM domain_fields
                    ORDER BY `name`
                    LOCK IN SHARE MODE")->fetchAll();

        if ($result) {

            $field_array = array();

            foreach ($result as $row) {

                $field_array[] = $row->field_name;

            }

            foreach ($field_array as $field) {

                $full_field = "new_" . $field;

                $stmt = $pdo->prepare("
                            UPDATE domain_field_data
                            SET `" . $field . "` = :full_field
                            WHERE domain_id = :temp_domain_id");
                $stmt->bindValue('full_field', ${$full_field} ?? '', \PDO::PARAM_STR);
                $stmt->bindValue('temp_domain_id', $temp_domain_id, \PDO::PARAM_INT);
                $stmt->execute();

            }

        }

        return $temp_domain_id;

    }

    /*
     * Keeps the SSL certificates of deleted domains, without a domain (domain_id 0), and returns how many were changed.
     * Their lookup status rows are kept, since their names, and so the sites they describe, haven't changed. It
     * starts no transaction and locks no domain itself: the domain deletes call it (through unlinkSslCertificatesRecorded())
     * inside their transaction, after locking the domains and before deleting them, so the domains are locked first and
     * its update then locks the certificates, in the same order as the SSL Queue and SSL Refresh.
     */
    public function unlinkSslCertificates(array $domain_ids, $ssl_ids = null)
    {
        // With $ssl_ids (a page of certificates already locked and captured, see unlinkSslCertificatesRecorded()), only
        // those are changed
        if ($ssl_ids !== null) {
            $ssl_ids = array_values(array_map('intval', $ssl_ids));
            if (!$ssl_ids) return 0;
            $stmt = $this->deeb->cnxx->prepare("
                UPDATE ssl_certs
                SET domain_id = 0,
                    update_time = :update_time
                WHERE id IN (" . implode(',', $ssl_ids) . ")");
            $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount();
        }

        $domain_ids = array_values(array_map('intval', $domain_ids));
        if (!$domain_ids) return 0;

        $placeholders = array();
        foreach ($domain_ids as $index => $domain_id) {
            $placeholders[] = ':domain_id' . $index;
        }

        $stmt = $this->deeb->cnxx->prepare("
            UPDATE ssl_certs
            SET domain_id = 0,
                update_time = :update_time
            WHERE domain_id IN (" . implode(', ', $placeholders) . ")");
        $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
        foreach ($domain_ids as $index => $domain_id) {
            $stmt->bindValue('domain_id' . $index, $domain_id, \PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    /*
     * The service delete's unlink: sets service_id to 0 for the given domains that still have $service_id, and returns
     * how many were changed. It starts no transaction and locks nothing itself: hosting/edit.php calls it under the
     * hosting lock, inside its transaction, with a page of domains it has already locked and captured.
     */
    public function clearHostingService(array $domain_ids, $service_id)
    {
        $domain_ids = array_values(array_unique(array_map('intval', $domain_ids)));
        if (!$domain_ids || (int) $service_id <= 0) return 0;

        $stmt = $this->deeb->cnxx->prepare("
            UPDATE domains
            SET service_id = 0,
                update_time = :update_time
            WHERE id IN (" . implode(',', $domain_ids) . ")
              AND service_id = :service_id");
        $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->bindValue('service_id', (int) $service_id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /*
     * Unlinks the SSL certificates of domains about to be deleted, recording each unlink in the Change History. The
     * certificates' ids looked up before the transaction started ($known_ssl_ids, as the Bulk Updater does) are locked
     * first, through the primary key in ascending id order across every domain, as every writer locks them. Then each
     * domain's certificates are locked, one domain at a time in ascending id, in keyset pages of 1,000 through the
     * (domain_id) index (named with FORCE INDEX when one exists, so each page is a seek in id order), which also finds a
     * certificate linked since the lookup. Once every certificate is locked, they're captured, unlinked, and compared,
     * 1,000 at a time, domain by domain. The domains go in the order $domain_ids gives them when $in_order is set (such
     * as the order a list was pasted, so the history lists them that way), otherwise in ascending domain id, and each
     * domain's certificates go in ascending certificate id. Each certificate gets a domain_id row from the domain to
     * none in the same action, marked as a side effect. It runs after the domains are locked and snapshotted
     * and before they're deleted, so the domain's name can still be read for the history. Returns how many certificates
     * were unlinked.
     */
    public function unlinkSslCertificatesRecorded(array $domain_ids, ChangeHistoryOperation $op, array $known_ssl_ids = array(), $in_order = false)
    {
        $pdo = $this->deeb->cnxx;
        $domain_ids = array_values(array_unique(array_map('intval', $domain_ids)));
        $lock_order = $domain_ids;
        sort($lock_order);
        if (!$in_order) $domain_ids = $lock_order;
        $index = self::sslDomainIndexName($pdo);
        $unlinked = 0;

        $known_ssl_ids = array_values(array_unique(array_map('intval', $known_ssl_ids)));
        sort($known_ssl_ids);
        foreach (array_chunk($known_ssl_ids, 1000) as $chunk) {
            $pdo->query("
                SELECT id
                FROM ssl_certs FORCE INDEX (PRIMARY)
                WHERE id IN (" . implode(',', $chunk) . ")
                ORDER BY id
                FOR UPDATE")->closeCursor();
        }

        // Each domain's certificates, locked in ascending domain id (keyset pages through the domain_id index)
        $certificates = array();
        foreach ($lock_order as $domain_id) {

            $certificates[$domain_id] = array();
            $last = 0;
            while (true) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM ssl_certs" . ($index !== '' ? " FORCE INDEX (`" . str_replace('`', '``', $index) . "`)" : '') . "
                    WHERE domain_id = :domain_id
                      AND id > :last
                    ORDER BY id
                    LIMIT 1000
                    FOR UPDATE");
                $stmt->bindValue('domain_id', $domain_id, \PDO::PARAM_INT);
                $stmt->bindValue('last', $last, \PDO::PARAM_INT);
                $stmt->execute();
                $ssl_ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
                $stmt->closeCursor();
                if (!$ssl_ids) break;

                $certificates[$domain_id] = array_merge($certificates[$domain_id], $ssl_ids);
                $last = end($ssl_ids);
                if (count($ssl_ids) < 1000) break;

            }

        }

        // Then recorded and unlinked domain by domain, 1,000 at a time (all already locked)
        foreach ($domain_ids as $domain_id) {
            foreach (array_chunk($certificates[$domain_id] ?? array(), 1000) as $ssl_ids) {
                $before = $op->capture('ssl', $ssl_ids, array('domain_id'), false);
                $unlinked += $this->unlinkSslCertificates(array(), $ssl_ids);
                $op->compare($before, 'side_effect');
            }
        }

        return $unlinked;
    }

    // The ssl_certs index whose only column is domain_id (usable, not ignored or invisible), or '' when there's none
    protected static function sslDomainIndexName(\PDO $pdo)
    {
        try {
            $rows = $pdo->query("
                SELECT *
                FROM `INFORMATION_SCHEMA`.`STATISTICS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                  AND `TABLE_NAME` = 'ssl_certs'")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return '';
        }

        $indexes = array();
        foreach ($rows as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $name = (string) $row['index_name'];
            $indexes[$name]['columns'][(int) $row['seq_in_index']] = strtolower((string) $row['column_name']);
            $indexes[$name]['usable'] = ($indexes[$name]['usable'] ?? true)
                && strtoupper((string) ($row['ignored'] ?? 'NO')) !== 'YES'
                && strtoupper((string) ($row['is_visible'] ?? 'YES')) !== 'NO';
        }

        $found = '';
        foreach ($indexes as $name => $index) {
            if ($index['usable'] && $index['columns'] === array(1 => 'domain_id') && ($found === '' || $name === 'domain_id')) {
                $found = (string) $name;
            }
        }
        return $found;
    }

} //@formatter:on
