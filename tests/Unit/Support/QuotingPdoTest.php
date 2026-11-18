<?php
/**
 * /tests/Unit/Support/QuotingPdoTest.php
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
namespace DomainMOD\Tests\Unit\Support;

use DomainMOD\Tests\Support\QuotingPdo;
use PHPUnit\Framework\TestCase;

class QuotingPdoTest extends TestCase
{
    public function testQuotesLikeMysql()
    {
        $pdo = new QuotingPdo();

        $this->assertSame("'it\\'s a \\\\ test'", $pdo->quote("it's a \\ test"));
        $this->assertSame("''", $pdo->quote(''));
    }

} //@formatter:on
