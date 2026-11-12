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

    public function addDomain($oid, $rid, $raid, $domain, $tld, $expiry_date, $cat_id, $dns_id, $ip_id, $hosting_id,
                              $fee_id, $total_cost, $function, $notes, $autorenew, $privacy, $creation_type_id,
                              $created_by, $status)
    {
        $pdo = $this->deeb->cnxx;

        $stmt = $pdo->prepare("
                    INSERT INTO domains
                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id,
                     hosting_id, fee_id, total_cost, `function`, notes, autorenew, privacy, creation_type_id,
                     created_by, `active`, insert_time, update_time)
                    VALUES
                    (:oid, :rid, :raid, :domain, :tld, :expiry_date, :cat_id,
                     :dns_id, :ip_id, :hosting_id, :fee_id, :total_cost, :function, :notes,
                     :autorenew, :privacy, :creation_type_id, :user_id, :status, :insert_time, :update_time)");
        $stmt->bindValue('oid', $oid, \PDO::PARAM_INT);
        $stmt->bindValue('rid', $rid, \PDO::PARAM_INT);
        $stmt->bindValue('raid', $raid, \PDO::PARAM_INT);
        $stmt->bindValue('domain', $domain, \PDO::PARAM_STR);
        $stmt->bindValue('tld', $tld, \PDO::PARAM_STR);
        $stmt->bindValue('expiry_date', $expiry_date, \PDO::PARAM_STR);
        $stmt->bindValue('cat_id', $cat_id, \PDO::PARAM_INT);
        $stmt->bindValue('dns_id', $dns_id, \PDO::PARAM_INT);
        $stmt->bindValue('ip_id', $ip_id, \PDO::PARAM_INT);
        $stmt->bindValue('hosting_id', $hosting_id, \PDO::PARAM_INT);
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
                    ORDER BY `name`")->fetchAll();

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

} //@formatter:on
