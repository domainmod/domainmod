<?php
/**
 * /tests/Unit/FormatTest.php
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

use DomainMOD\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function testCleanAndSplitDomainsGivesEachLinesStoredFormOnce()
    {
        // The Bulk Updater's and Add Domains' pasted list: blank lines go, each line is normalised and sanitized, and
        // duplicates are removed (keeping the first one's position)
        $raw = "Example.com\r\n\r\n  xn--mnchen-3ya.de  \r\n\t\r\nexample.COM\r\nbad domain\r\nTom's.com\r\nMÜNCHEN.DE\r\n";

        $this->assertSame(array(0 => 'example.com', 1 => 'münchen.de', 3 => 'bad domain', 4 => 'Tom&#039;s.com'),
            (new Format())->cleanAndSplitDomains($raw));
    }

    public function testCleanAndSplitDomainsKeepsABareLineFeed()
    {
        // Browsers send a textarea's lines with a carriage return and line feed together, which is what the list is split
        // on, so a bare line feed stays inside one line
        $this->assertSame(array("a.com\nb.com"), (new Format())->cleanAndSplitDomains("a.com\nb.com"));
    }

    public function testFormatForMysqlGivesTheStoredSegmentFormat()
    {
        $format = new Format();

        $this->assertSame("'a.com','b.com','münchen.de'", $format->formatForMysql(array('a.com', 'b.com', 'münchen.de')));
        $this->assertSame("'a.com','b.com'", $format->formatForMysql(array('a .com', ' b.com ')));
        $this->assertSame("''", $format->formatForMysql(array()));
    }

    public function testFormatForMysqlDoesNotEscapeQuotesItself()
    {
        // It adds no escaping of its own: its callers pass text Sanitize::text() has already encoded (' becomes
        // &#039;), and a raw quote passes straight through.
        $format = new Format();

        $this->assertSame("'tom&#039;s.com'", $format->formatForMysql(array('tom&#039;s.com')));
        $this->assertSame("'tom's.com'", $format->formatForMysql(array("tom's.com")));
    }

    public function testObfuscHidesEveryByte()
    {
        $format = new Format();

        $this->assertSame('******', $format->obfusc('secret'));
        $this->assertSame('**', $format->obfusc('é'));
        $this->assertSame('', $format->obfusc(''));
    }

    public function testUpperAndLowerCaseAreMultibyteSafe()
    {
        $this->assertSame('OSTRZEŻENIE', Format::toUpper('Ostrzeżenie'));
        $this->assertSame('ostrzeżenie', Format::toLower('OSTRZEŻENIE'));
        $this->assertSame('ÉTÉ', Format::toUpper('été'));
        $this->assertSame('münchen', Format::toLower('MÜNCHEN'));
    }

    public function testStripSpacingAndReplaceBreaks()
    {
        $format = new Format();

        $this->assertSame("a\nb", $format->stripSpacing("\n\n  a\n\n \t\nb  "));
        $this->assertSame('one<BR><BR>two<BR>three', $format->replaceBreaks(" one\n\ntwo\nthree\n"));
    }

} //@formatter:on
