<?php
/**
 * /tests/Unit/SslQueueDomainMatchTest.php
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
 * SslQueue::bestMatch() and storedSuffixes(): a host links to the domain that is the host itself or its longest parent
 * at a label boundary, compared in ASCII, so a host stored in punycode matches a parent stored in Unicode.
 */
class SslQueueDomainMatchTest extends TestCase
{

    public function testTheLongestSuffixWins()
    {
        $this->assertSame('example.co.uk', SslQueue::bestMatch('a.b.example.co.uk', array('co.uk', 'example.co.uk', 'other.co.uk')));
        $this->assertSame('shop.example.com', SslQueue::bestMatch('shop.example.com', array('example.com', 'shop.example.com')));
        $this->assertSame('example.com', SslQueue::bestMatch('www.shop.example.com', array('example.com')));

        // whatever order the database returns them in
        $this->assertSame('example.co.uk', SslQueue::bestMatch('a.b.example.co.uk', array('example.co.uk', 'co.uk')));
        $this->assertSame('shop.example.com', SslQueue::bestMatch('www.shop.example.com', array('shop.example.com', 'example.com')));
    }

    public function testOnlyALabelBoundaryCounts()
    {
        $this->assertSame('', SslQueue::bestMatch('myexample.com', array('example.com')));
        $this->assertSame('', SslQueue::bestMatch('example.com', array('www.example.com')));
    }

    public function testIdnForms()
    {
        // a label outside the Basic Multilingual Plane makes Domain::normalize() store the whole host in punycode
        $host = \DomainMOD\Domain::normalize("\u{1F600}.münchen.de");
        $this->assertStringStartsWith('xn--', $host);

        $this->assertSame('münchen.de', SslQueue::bestMatch($host, array('münchen.de')));
        $this->assertSame('münchen.de', SslQueue::bestMatch('shop.xn--mnchen-3ya.de', array('münchen.de')));
        $this->assertContains('münchen.de', SslQueue::storedSuffixes($host));
    }

    public function testStoredSuffixes()
    {
        $this->assertSame(array('a.b.example.co.uk', 'b.example.co.uk', 'example.co.uk', 'co.uk'), SslQueue::storedSuffixes('a.b.example.co.uk'));
        $this->assertSame(array('shop.münchen.de', 'münchen.de'), SslQueue::storedSuffixes('shop.xn--mnchen-3ya.de'));
    }

} //@formatter:on
