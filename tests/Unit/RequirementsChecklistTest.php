<?php
/**
 * /tests/Unit/RequirementsChecklistTest.php
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

use DomainMOD\System;
use PHPUnit\Framework\TestCase;

// The requirements checklist (System::requirementsChecklist()). Only the upgrade notice asks for the collapsible list,
// so the installer and System Information still show the full list with no box around it.
class RequirementsChecklistTest extends TestCase
{
    public function testTheListIsClosedWhenEveryRequirementIsMet()
    {
        $html = $this->system(true)->requirementsChecklist(false, true);

        $this->assertSame(1, substr_count($html, '<details'));
        $this->assertStringContainsString('<details class="card mb-0 domainmod-message-why">', $html);
        $this->assertStringContainsString('<summary class="card-header fw-semibold">All Requirements</summary>', $html);
        $this->assertSame(1, substr_count($html, '</details>'));
    }

    public function testTheListIsOpenWhenARequirementIsntMet()
    {
        $html = $this->system(false)->requirementsChecklist(false, true);

        $this->assertStringContainsString('<details class="card mb-0 domainmod-message-why" open>', $html);
        $this->assertStringContainsString('Ask your host to enable the curl PHP extension.', $html);
    }

    public function testTheListHasNoBoxByDefault()
    {
        foreach (array(array(), array(true), array(false, false)) as $arguments) {
            $html = $this->system(false)->requirementsChecklist(...$arguments);

            $this->assertStringNotContainsString('<details', $html);
            $this->assertStringNotContainsString('<summary', $html);
            $this->assertStringContainsString('domainmod-requirements-list', $html);
        }
    }

    // A System whose requirement checks are two made-up rows, the second met or not as given, so no database is needed
    private function system($all_met)
    {
        $system = $this->getMockBuilder(System::class)->disableOriginalConstructor()->onlyMethods(array('getRequirementChecks'))->getMock();
        $system->method('getRequirementChecks')->willReturn(array(
            array('group' => 'software', 'name' => 'PHP 8.2+', 'ok' => true, 'status' => array('Pass', 'Fail'),
                  'note' => 'You have 8.5.0', 'fix' => 'Ask your host to upgrade PHP to version 8.2 or newer.'),
            array('group' => 'extensions', 'name' => 'cURL', 'ok' => $all_met, 'status' => array('Enabled', 'Disabled'),
                  'note' => 'Needed for registrar, RDAP, and Data Warehouse lookups',
                  'fix' => 'Ask your host to enable the curl PHP extension.'),
        ));
        return $system;
    }

} //@formatter:on
