<?php
/**
 * /tests/Unit/ValidateFeeTest.php
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
namespace DomainMOD\Tests\Unit;

use DomainMOD\Validate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Validate::feeError(), the check every fee form runs on each fee: 0 is a fee, a blank fee is refused only when the field
 * is required, and a negative fee, a comma for decimals, anything that isn't a plain number, and a fee too large for the
 * fee columns are refused with their own messages.
 */
class ValidateFeeTest extends TestCase
{
    public static function acceptedCases()
    {
        return array(
            'zero' => array('0'),
            'zero with decimals' => array('0.00'),
            'decimals' => array('12.50'),
            'whole number' => array('12'),
            'no leading digit' => array('.5'),
            'no trailing digits' => array('12.'),
            'spaces around it' => array("  12.50 \t"),
            'negative zero' => array('-0'),
            'negative zero with decimals' => array('-0.00'),
            'largest fee' => array('99999999.99'),
            'a number, not a string' => array(12.5),
        );
    }

    #[DataProvider('acceptedCases')]
    public function testAcceptsAPlainNumber($fee)
    {
        $validate = new Validate();

        $this->assertSame('', $validate->feeError($fee, true));
        $this->assertSame('', $validate->feeError($fee, false));
    }

    public static function blankCases()
    {
        return array(
            'empty' => array(''),
            'spaces only' => array('   '),
            'whitespace only' => array(" \t\n"),
            'null' => array(null),
            // A field posted as an array (new_fee[]=12.50) would otherwise warn "Array to string conversion"
            'posted array' => array(array('12.50')),
        );
    }

    #[DataProvider('blankCases')]
    public function testRefusesABlankFeeOnlyWhenRequired($fee)
    {
        $validate = new Validate();

        $this->assertSame("Enter the fee, or 0 if there isn't one.", $validate->feeError($fee, true));
        $this->assertSame('', $validate->feeError($fee, false));
    }

    public static function refusedCases()
    {
        $comma = 'Use a period for decimals, such as 12.50.';
        $number = 'Enter the fee as a number, such as 12.50.';
        $negative = "A fee can't be negative.";
        $large = 'A fee can be at most 99999999.99.';

        return array(
            'comma for decimals' => array('12,50', $comma),
            'comma for decimals, no leading digit' => array(',5', $comma),
            'comma for decimals, with spaces' => array(' 12,50 ', $comma),
            'thousands separator' => array('1,250.00', $number),
            'two commas' => array('1,250,000', $number),
            'comma alone' => array(',', $number),
            'negative' => array('-1', $negative),
            'negative decimals' => array('-0.01', $negative),
            'negative too small for a float' => array('-0.' . str_repeat('0', 400) . '1', $negative),
            'negative with a comma' => array('-12,50', $comma),
            'exponent' => array('1e3', $number),
            'hexadecimal' => array('0x1A', $number),
            'currency symbol' => array('$12.50', $number),
            'currency code' => array('12.50 USD', $number),
            'space inside' => array('12 50', $number),
            'two periods' => array('1.2.3', $number),
            'period alone' => array('.', $number),
            'plus sign' => array('+5', $number),
            'words' => array('free', $number),
            'just over the largest fee' => array('100000000', $large),
            'very large' => array('99999999999999999999', $large),
        );
    }

    #[DataProvider('refusedCases')]
    public function testRefusesAFeeThatCantBeSaved($fee, $message)
    {
        $validate = new Validate();

        // Required or not, a fee that's been entered has to be one that can be saved
        $this->assertSame($message, $validate->feeError($fee, true));
        $this->assertSame($message, $validate->feeError($fee, false));
    }

} //@formatter:on
