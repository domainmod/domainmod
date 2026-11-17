<?php
/**
 * /classes/DomainMOD/Validate.php
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

class Validate
{

   public function tld($tld)
    {
        // a TLD such as de, co.uk, рф, or its punycode form xn--p1ai
        if(Domain::normalize($tld) !== '') {

            return true;

        } else {

            return false;

        }

    }

   public function text($text)
    {
        /*
         * Strip whitespace from beginning and end of value
         * Make sure there's a value
         * Make sure the value isn't all whitespace
         * Make sure the value is 2 or more characters
         */

        $clean_text = trim($text);

        if($clean_text != '' && !ctype_space($clean_text) && strlen($clean_text) >= 2) {

            return true;

        } else {

            return false;

        }

    }

   public function feeError($fee, $required)
    {
        /*
         * Checks a fee typed into a fee form, and returns the message to show under its field, or '' when the fee can be
         * saved. A fee is a plain number with a period for decimals, such as 12.50 (0 is a fee too). A blank fee is
         * refused only when the field is required, and saves as 0 otherwise. The largest fee is the largest number the
         * fee columns hold (DECIMAL(10,2)). A fee that isn't text or a number (such as a posted array) counts as blank.
         */

        $clean_fee = is_string($fee) || is_int($fee) || is_float($fee) ? trim((string) $fee) : '';

        if($clean_fee === '') {

            return $required ? _("Enter the fee, or 0 if there isn't one.") : '';

        }

        $is_number = function ($value) {
            return preg_match('/^-?([0-9]+(\.[0-9]*)?|\.[0-9]+)$/', $value) === 1;
        };

        if(strpos($clean_fee, ',') !== false) {

            // A comma used for decimals, such as 12,50, gets its own message. Any other comma (such as the thousands
            // separator in 1,250.00) gets the general one.
            if(substr_count($clean_fee, ',') === 1 && strpos($clean_fee, '.') === false &&
                $is_number(str_replace(',', '.', $clean_fee))) {

                /* TRANSLATORS: %s is an example fee, such as 12.50 */
                return sprintf(_('Use a period for decimals, such as %s.'), '12.50');

            }

            /* TRANSLATORS: %s is an example fee, such as 12.50 */
            return sprintf(_('Enter the fee as a number, such as %s.'), '12.50');

        }

        if(!$is_number($clean_fee)) {

            /* TRANSLATORS: %s is an example fee, such as 12.50 */
            return sprintf(_('Enter the fee as a number, such as %s.'), '12.50');

        }

        // Any digit but 0 after a minus sign makes it negative (read from the text, since a tiny value such as -0.000…1
        // turns into 0 as a number)
        if($clean_fee[0] === '-' && preg_match('/[1-9]/', $clean_fee) === 1) {

            return _("A fee can't be negative.");

        }

        if((float) $clean_fee > 99999999.99) {

            /* TRANSLATORS: %s is the largest fee that can be saved, 99999999.99 */
            return sprintf(_('A fee can be at most %s.'), '99999999.99');

        }

        return '';

    }

} //@formatter:on
