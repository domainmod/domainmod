<?php
/**
 * /tests/Unit/InstallCurrencyCodesTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * The installer's currency step: every currency it offers is a three-letter ISO 4217 code, which the exchange rate
 * lookup needs and every default_currency column holds. It once offered RM, the Malaysian ringgit's symbol, beside MYR.
 */
class InstallCurrencyCodesTest extends TestCase
{
    public function testEveryOfferedCurrencyIsAThreeLetterCode()
    {
        preg_match_all("/showDropdownOption\('([^']*)'/", file_get_contents(DIR_ROOT . '/install/currency/index.php'), $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertSame(array(), array_values(array_filter($matches[1], function ($code) {
            return preg_match('/^[A-Z]{3}$/', $code) !== 1;
        })));
        $this->assertSame(array_values(array_unique($matches[1])), $matches[1]);
    }

} //@formatter:on
