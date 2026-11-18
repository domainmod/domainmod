<?php
/**
 * /tests/Unit/SanitizeTest.php
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

use DomainMOD\Sanitize;
use PHPUnit\Framework\TestCase;

class SanitizeTest extends TestCase
{
    public function testTextEncodesHtmlAndBothQuotes()
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;Tom&#039;s &amp; co&lt;/a&gt;',
            (new Sanitize())->text('<a href="x">Tom\'s & co</a>'));
    }

    public function testTextKeepsUnicodeAndPlainText()
    {
        $sanitize = new Sanitize();

        $this->assertSame('münchen.de', $sanitize->text('münchen.de'));
        $this->assertSame('', $sanitize->text(''));
    }

    public function testTextEncodesAnAlreadyEncodedValueAgain()
    {
        // Values are stored encoded once, so encoding twice is visible, which is why it's done at one place only
        $this->assertSame('&amp;amp;', (new Sanitize())->text('&amp;'));
    }

    public function testTextBlanksInvalidUtf8()
    {
        $this->assertSame('', (new Sanitize())->text("bad \xC3\x28 bytes"));
    }

} //@formatter:on
