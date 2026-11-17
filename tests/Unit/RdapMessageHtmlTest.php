<?php
/**
 * /tests/Unit/RdapMessageHtmlTest.php
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

use DomainMOD\DomainQueue;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * DomainQueue::getRdapMessageHtml(): the RDAP message as HTML, with the TLD kept left to right in the "no server"
 * messages, and the plain getRdapMessage() unchanged for the export.
 */
class RdapMessageHtmlTest extends TestCase
{

    public function testTheTldIsKeptLeftToRight()
    {
        $queue = Instance::withoutConstructor(DomainQueue::class);

        $this->assertSame('No RDAP server for <bdi dir="ltr">.co.uk</bdi> domains', $queue->getRdapMessageHtml('no_server|co.uk'));
        $this->assertSame('Neither RDAP nor WHOIS is available for <bdi dir="ltr">.example</bdi> domains', $queue->getRdapMessageHtml('no_lookup|example'));
    }

    public function testThePlainMessageIsUnchanged()
    {
        $queue = Instance::withoutConstructor(DomainQueue::class);

        $this->assertSame('No RDAP server for .co.uk domains', $queue->getRdapMessage('no_server|co.uk'));
    }

    public function testOtherMessagesAreEscapedOnly()
    {
        $queue = Instance::withoutConstructor(DomainQueue::class);

        $this->assertSame('WHOIS couldn&#039;t be reached (your server may block port 43)', $queue->getRdapMessageHtml('whois_unreachable'));
        $this->assertSame('', $queue->getRdapMessageHtml('edited'));
    }

    public function testATldIsEscaped()
    {
        $queue = Instance::withoutConstructor(DomainQueue::class);

        $this->assertStringContainsString('<bdi dir="ltr">.&lt;b&gt;</bdi>', $queue->getRdapMessageHtml('no_server|<b>'));
    }

} //@formatter:on
