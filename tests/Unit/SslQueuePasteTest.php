<?php
/**
 * /tests/Unit/SslQueuePasteTest.php
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

use DomainMOD\SslQueue;
use PHPUnit\Framework\TestCase;

/*
 * The SSL paste page's own parser, SslQueue::parseHostList(): each line keeps its own number, blank lines are skipped,
 * every line is checked before duplicates are dropped, and one invalid line refuses the paste (the page adds nothing
 * when the invalid list isn't empty).
 */
class SslQueuePasteTest extends TestCase
{

    public function testAFullWidthIpAddressIsRefused()
    {
        $this->assertSame(array(array('example.com'), array(2)), SslQueue::parseHostList("example.com\n１２７.０.０.１"));
    }

    public function testABlankLineIsSkipped()
    {
        $this->assertSame(array(array('example.com', 'www.example.com'), array()), SslQueue::parseHostList("example.com\n\nwww.example.com"));
    }

    public function testAWhitespaceLineKeepsTheLineNumbers()
    {
        $this->assertSame(array(array('example.com'), array(3)), SslQueue::parseHostList("example.com\n \t \n１２７.０.０.１"));
        $this->assertSame(array(array('example.com'), array(3)), SslQueue::parseHostList("example.com\r\n\r\n*.example.com"));
        $this->assertSame(array(array('example.com', 'www.example.com'), array()), SslQueue::parseHostList("example.com\n\u{00A0}\u{3000}\nwww.example.com"));
    }

    public function testOnlySpacesAndLineBreaksGiveNothing()
    {
        // the page gives its required-list error for this
        $this->assertSame(array(array(), array()), SslQueue::parseHostList("  \n\t\n\r\n   "));
        $this->assertSame(array(array(), array()), SslQueue::parseHostList(''));
    }

    public function testAPortMustBeWrittenExactlyAs443()
    {
        $this->assertSame(array(array('example.com'), array(2)), SslQueue::parseHostList("example.com\nhttps://example.com:443.5/"));
        $this->assertSame(array(array('www.example.com'), array()), SslQueue::parseHostList('https://www.example.com:443/path'));
        $this->assertSame(array(array(), array(1)), SslQueue::parseHostList('https://example.com:0443/path'));
        $this->assertSame(array(array(), array(1)), SslQueue::parseHostList('https://example.com:8443/'));
    }

    public function testInvalidLinesAreRefused()
    {
        $lines = array('*.example.com', '192.0.2.1', '2001:db8::1', 'localhost', 'localhost.', 'https://localhost./',
            'http://example.com/', 'ftp://example.com', 'example.com/path', 'user@example.com', 'exa mple.com', "bad\xFF.example.com");

        list($hosts, $invalid) = SslQueue::parseHostList(implode("\n", $lines));

        $this->assertSame(array(), $hosts);
        $this->assertSame(range(1, count($lines)), $invalid);
    }

    public function testALineThatIsntValidUtf8IsRefusedWhereverTheBadByteIs()
    {
        // the bad byte is in the path, which would otherwise be dropped
        $this->assertSame(array(array('example.com'), array(2)), SslQueue::parseHostList("example.com\nhttps://www.example.org/path\xFF"));

        // the page shows such a byte as U+FFFD, and the line is still refused when it's sent back unchanged
        $this->assertSame(array(array('example.com'), array(2)), SslQueue::parseHostList("example.com\nhttps://www.example.org/path\u{FFFD}"));
        $this->assertSame(array(array('example.com'), array(2)), SslQueue::parseHostList("example.com\nhttps://www.example.org/a\0b"));
    }

    public function testUrlsAndUnicodeNamesGiveTheirStoredHost()
    {
        $this->assertSame(array(array('www.example.com', 'münchen.de'), array()),
            SslQueue::parseHostList("https://WWW.Example.com/some/path?x=1\nmünchen.de\nxn--mnchen-3ya.de"));
    }

    public function testDuplicatesAreDroppedAfterChecking()
    {
        $this->assertSame(array(array('example.com'), array()), SslQueue::parseHostList("example.com\nhttps://example.com/\nEXAMPLE.COM."));

        // 1,001 lines holding 1,000 distinct hosts: the page's limit counts the hosts, not the lines
        $lines = array('example.com', 'https://example.com/');
        for ($i = 1; $i < SslQueue::MAX_HOSTS; $i++) $lines[] = 'host' . $i . '.example.org';

        list($hosts, $invalid) = SslQueue::parseHostList(implode("\n", $lines));
        $this->assertCount(SslQueue::MAX_HOSTS + 1, $lines);
        $this->assertCount(SslQueue::MAX_HOSTS, $hosts);
        $this->assertSame(array(), $invalid);
    }

} //@formatter:on
