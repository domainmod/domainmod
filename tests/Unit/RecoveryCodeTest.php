<?php
/**
 * /tests/Unit/RecoveryCodeTest.php
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

use DomainMOD\RecoveryCode;
use PHPUnit\Framework\TestCase;

class RecoveryCodeTest extends TestCase
{
    public function testGeneratesTenDifferentFormattedCodes()
    {
        $codes = (new RecoveryCode())->generate();

        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        foreach ($codes as $code) $this->assertMatchesRegularExpression('/^[A-Z2-7]{4}(-[A-Z2-7]{4}){3}$/D', $code);
    }

    public function testDashesSpacesAndCaseDontMatter()
    {
        $recovery = new RecoveryCode();

        $this->assertSame('ABCD2345EFGH6723', $recovery->normalise('ABCD-2345-EFGH-6723'));
        $this->assertSame('ABCD2345EFGH6723', $recovery->normalise(' abcd 2345-efgh 6723 '));
        $this->assertSame($recovery->hash('ABCD-2345-EFGH-6723'), $recovery->hash('abcd2345efgh6723'));
    }

    public function testRefusesAnythingThatCantBeACode()
    {
        $recovery = new RecoveryCode();

        $this->assertFalse($recovery->normalise('ABCD-2345-EFGH-672'));
        $this->assertFalse($recovery->normalise('ABCD-2345-EFGH-67233'));
        // 0, 1, 8, and 9 aren't in the base32 alphabet
        $this->assertFalse($recovery->normalise('ABCD-2345-EFGH-6701'));
        $this->assertFalse($recovery->normalise("ABCD-2345-EFGH-6723\n"));
        $this->assertFalse($recovery->normalise(array('ABCD-2345-EFGH-6723')));
        $this->assertFalse($recovery->hash(''));
    }

    public function testTheHashIsSha256OfTheNormalisedCode()
    {
        $this->assertSame(hash('sha256', 'ABCD2345EFGH6723'), (new RecoveryCode())->hash('abcd-2345-efgh-6723'));
    }

} //@formatter:on
