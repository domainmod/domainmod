<?php
/**
 * /tests/Unit/BootstrapTest.php
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

class BootstrapTest extends TestCase
{
    public function testLoadsTheAppClassesAndThePathConstants()
    {
        $this->assertTrue(class_exists(\DomainMOD\Time::class));
        $this->assertSame(dirname(__DIR__, 2), DIR_ROOT);
        $this->assertFileExists(DIR_INC . '/software.inc.php');
    }

    public function testLeavesTheLanguageUndefined()
    {
        // Layout::isRtl() reads DEFAULT_LANGUAGE, which can't change once it's defined, so only a test running in its
        // own process may define it.
        $this->assertFalse(defined('DEFAULT_LANGUAGE'));
    }

    public function testUsesUtc()
    {
        $this->assertSame('UTC', date_default_timezone_get());
    }

} //@formatter:on
