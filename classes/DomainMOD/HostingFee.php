<?php
/**
 * /classes/DomainMOD/HostingFee.php
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
 * Hosting fees: the amounts as whole cents (so a fee's total is checked exactly as it's stored), the fee that
 * matches a service's provider, service type, and billing term, and the fee edit page's delete check. On 32-bit PHP
 * (the official Docker image's armhf build), cents past its int range (21,474,836.47) are whole-number floats instead,
 * which hold every amount and total here exactly.
 */
class HostingFee
{
    // The largest amount a DECIMAL(10,2) column holds, in cents (99,999,999.99)
    const MAX_CENTS = 9999999999;

    /*
     * A fee typed into a fee form, already passed by Validate::feeError(), as whole cents, rounded half away from zero
     * the way the database rounds a decimal string into DECIMAL(10,2) (12.345 is 1235). A blank fee is 0, and so is a
     * negative zero such as -0.00. Read from the text, never through a float, so 0.29 is always 29.
     */
    public static function toCents($fee)
    {
        $fee = is_string($fee) || is_int($fee) ? trim((string) $fee) : '';
        if ($fee === '') return 0;
        if ($fee[0] === '-') $fee = substr($fee, 1); // feeError() only lets a zero through with a minus sign
        if (preg_match('/^([0-9]*)(?:\.([0-9]*))?$/', $fee, $parts) !== 1) return 0;

        $whole = ltrim($parts[1], '0');
        $fraction = $parts[2] ?? '';
        // PHP turns an int sum or product that overflows into a float, so on 32-bit PHP only the whole part's own cast
        // needs a check
        $whole_number = (int) $whole;
        if (PHP_INT_SIZE < 8 && (string) $whole_number !== ($whole === '' ? '0' : $whole)) $whole_number = (float) $whole;
        $cents = $whole_number * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (strlen($fraction) > 2 && (int) $fraction[2] >= 5) $cents++;
        return $cents;
    }

    // Whole cents as the decimal string that's bound to a DECIMAL(10,2) column, such as 1235 as "12.35"
    public static function fromCents($cents)
    {
        $cents = self::number($cents);
        if (is_int($cents)) return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        $whole = $cents < 0 ? ceil($cents / 100) : floor($cents / 100);
        return sprintf('%.0f', $whole) . '.' . sprintf('%02.0f', abs($cents - $whole * 100));
    }

    // Cents as a number: an int, or on 32-bit PHP a whole-number float when it's past the int range
    private static function number($cents)
    {
        if (PHP_INT_SIZE >= 8 || is_int($cents)) return (int) $cents;

        $cents = round((float) $cents);
        return $cents >= PHP_INT_MIN && $cents <= PHP_INT_MAX ? (int) $cents : $cents;
    }

    /*
     * The field errors for a fee whose total doesn't fit: a service's cost is the renewal fee plus the misc fee (and a
     * new service's first term could be the initial fee plus the misc fee), and each sum has to fit DECIMAL(10,2) as the
     * amounts do. Returns field name => message, empty when both sums fit.
     */
    public static function totalErrors($initial_cents, $renewal_cents, $misc_cents)
    {
        $errors = array();
        if (self::number($renewal_cents) + self::number($misc_cents) > self::MAX_CENTS) {
            /* TRANSLATORS: %s is the largest total that can be saved, 99999999.99 */
            $errors['new_renewal_fee'] = sprintf(_('The renewal fee and misc fee together can be at most %s.'), '99999999.99');
        }
        if (self::number($initial_cents) + self::number($misc_cents) > self::MAX_CENTS) {
            /* TRANSLATORS: %s is the largest total that can be saved, 99999999.99 */
            $errors['new_initial_fee'] = sprintf(_('The initial fee and misc fee together can be at most %s.'), '99999999.99');
        }
        return $errors;
    }

    /*
     * The id of the fee for a provider, service type, and billing term (the lowest id, should there ever be more than one),
     * or 0 when there's none. $lock is '' for a plain read, or 'LOCK IN SHARE MODE' or 'FOR UPDATE' for a locking read
     * inside a transaction (the fee_group key makes it a seek).
     */
    public static function findId(\PDO $pdo, $hosting_id, $type_id, $billing_term, $lock = '')
    {
        $lock = in_array($lock, array('LOCK IN SHARE MODE', 'FOR UPDATE'), true) ? ' ' . $lock : '';
        $stmt = $pdo->prepare("
            SELECT id
            FROM hosting_fees
            WHERE hosting_id = :hosting_id
              AND type_id = :type_id
              AND billing_term = :billing_term
            ORDER BY id
            LIMIT 1" . $lock);
        $stmt->bindValue('hosting_id', (int) $hosting_id, \PDO::PARAM_INT);
        $stmt->bindValue('type_id', (int) $type_id, \PDO::PARAM_INT);
        $stmt->bindValue('billing_term', (int) $billing_term, \PDO::PARAM_INT);
        $stmt->execute();
        $fee_id = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $fee_id === false ? 0 : (int) $fee_id;
    }

    /*
     * Why a fee can't be deleted, given how many hosting services use it, or '' when none does (the fee edit page's
     * Danger Zone removes only a fee no service uses, so no service's cost ever changes)
     */
    public static function deleteRefusal($services)
    {
        $services = (int) $services;
        if ($services <= 0) return '';
        return sprintf(ngettext("%s hosting service uses this fee, so it can't be deleted.",
            "%s hosting services use this fee, so it can't be deleted.", $services), number_format($services));
    }

} //@formatter:on
