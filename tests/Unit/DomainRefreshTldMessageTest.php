<?php
/**
 * /tests/Unit/DomainRefreshTldMessageTest.php
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

use DomainMOD\DomainRefresh;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * DomainRefresh::tldMessage(): Refresh's messages hold the TLD as ".%s", and the TLD is isolated left to right with its
 * dot, so a right-to-left page doesn't show ".co.uk" as "co.uk.". The method never uses the database, so the object is
 * built without its constructor.
 */
class DomainRefreshTldMessageTest extends TestCase
{
    private function tldMessage($message, $tld)
    {
        $method = new \ReflectionMethod(DomainRefresh::class, 'tldMessage');
        return $method->invoke(Instance::withoutConstructor(DomainRefresh::class), $message, $tld);
    }

    public function testTheDotIsIsolatedWithTheTld()
    {
        $this->assertSame('Neither RDAP nor WHOIS is available for <bdi dir="ltr">.co.uk</bdi> domains.',
            $this->tldMessage('Neither RDAP nor WHOIS is available for .%s domains.', 'co.uk'));
    }

    public function testATranslationKeepsItsOwnWording()
    {
        $this->assertSame('لا تتوفر خدمة RDAP ولا خدمة WHOIS لنطاقات <bdi dir="ltr">.co.uk</bdi>.',
            $this->tldMessage('لا تتوفر خدمة RDAP ولا خدمة WHOIS لنطاقات .%s.', 'co.uk'));
    }

    public function testATldWithoutADotBeforeItIsStillIsolated()
    {
        $this->assertSame('TLD <bdi dir="ltr">co.uk</bdi>: no WHOIS server.',
            $this->tldMessage('TLD %s: no WHOIS server.', 'co.uk'));
    }

    public function testTheTldIsEscaped()
    {
        $this->assertSame('No WHOIS for <bdi dir="ltr">.&lt;b&gt;</bdi> domains.',
            $this->tldMessage('No WHOIS for .%s domains.', '<b>'));
    }

} //@formatter:on
