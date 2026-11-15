<?php
/**
 * /tests/Unit/BulkMissingDomainsTest.php
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

use DomainMOD\Domain;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The field error the Bulk Updater pages (the main page and Update Custom Domain Field) show when domains on the list
 * aren't in DomainMOD. findMissingDomains() itself needs the database, so it waits for the database tests.
 */
// SOFTWARE_TITLE can't change once it's defined, so these tests define it in their own processes.
#[RunTestsInSeparateProcesses]
class BulkMissingDomainsTest extends TestCase
{
    private function message($missing_to_display, $missing_count)
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');

        // Domain's constructor connects to the database, which the message doesn't need.
        return Instance::withoutConstructor(Domain::class)->missingDomainsMessage($missing_to_display, $missing_count);
    }

    public function testNamesOneMissingDomain()
    {
        $this->assertSame("1 domain on your list isn't in DomainMOD: missing.com.",
            $this->message(array('missing.com'), 1));
    }

    public function testNamesUpToFiveMissingDomains()
    {
        $this->assertSame("5 domains on your list aren't in DomainMOD: a.com, b.com, c.com, d.com, e.com.",
            $this->message(array('a.com', 'b.com', 'c.com', 'd.com', 'e.com'), 5));
    }

    public function testCountsOneOtherBeyondTheFive()
    {
        $this->assertSame("6 domains on your list aren't in DomainMOD: a.com, b.com, c.com, d.com, e.com. Plus 1 other.",
            $this->message(array('a.com', 'b.com', 'c.com', 'd.com', 'e.com'), 6));
    }

    public function testCountsSeveralOthersBeyondTheFive()
    {
        $this->assertSame("7 domains on your list aren't in DomainMOD: a.com, b.com, c.com, d.com, e.com. Plus 2 others.",
            $this->message(array('a.com', 'b.com', 'c.com', 'd.com', 'e.com'), 7));
    }

    public function testFormatsLargeCounts()
    {
        $this->assertSame("1,250 domains on your list aren't in DomainMOD: a.com, b.com, c.com, d.com, e.com. Plus 1,245 others.",
            $this->message(array('a.com', 'b.com', 'c.com', 'd.com', 'e.com'), 1250));
    }

} //@formatter:on
