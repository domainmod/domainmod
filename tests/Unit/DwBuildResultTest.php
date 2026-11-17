<?php
/**
 * /tests/Unit/DwBuildResultTest.php
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

use DomainMOD\DwBuild;
use PHPUnit\Framework\TestCase;

// A Data Warehouse rebuild's status and message (DwBuild::resultFor()), which the rebuild page uses to show a success as
// a toast and anything else as an alert.
class DwBuildResultTest extends TestCase
{
    public function testNoServers()
    {
        $this->assertSame(array('status' => 'no-servers', 'message' => 'There are no servers in the Data Warehouse to rebuild.'),
            DwBuild::resultFor(false, array()));
    }

    public function testEveryServerBuilt()
    {
        $this->assertSame(array('status' => 'success', 'message' => 'The Data Warehouse has been rebuilt.'),
            DwBuild::resultFor(true, array()));
    }

    public function testOneFailedServer()
    {
        $this->assertSame(array('status' => 'partial', 'message' => "The Data Warehouse has been rebuilt, but some data couldn't be retrieved from these servers: Web 1. Check their details on the Servers page, then rebuild it again."),
            DwBuild::resultFor(true, array('Web 1')));
    }

    public function testSeveralFailedServersAreNamedAndEscaped()
    {
        // Server names are stored HTML-escaped, so they're decoded, then escaped once for the message
        $result = DwBuild::resultFor(true, array('Tom &amp; Jerry', '<b>Web 2</b>', 'Web &#039;3&#039;'));

        $this->assertSame('partial', $result['status']);
        $this->assertStringContainsString('these servers: Tom &amp; Jerry, &lt;b&gt;Web 2&lt;/b&gt;, Web &#039;3&#039;. Check', $result['message']);
        $this->assertStringNotContainsString('<b>', $result['message']);
        $this->assertStringEndsWith('rebuild it again.', $result['message']);
    }
}
