<?php
/**
 * /tests/Unit/BodyButtonsTest.php
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

use DomainMOD\Notice;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// The body buttons that go somewhere are links styled as buttons (Layout::linkButton()), never a button inside a link
class BodyButtonsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTheUpgradeButtonIsOneRedLink()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        define('SOFTWARE_TITLE', 'DomainMOD');
        $body = (new Notice())->upgradeBody('4.25.0', '', 'csrf_token=abc%2B1');

        $this->assertStringContainsString('<a class="btn btn-danger domainmod-css-button" href="checks.php?u=1&amp;csrf_token=abc%2B1">Upgrade Database</a>', $body);
        $this->assertStringNotContainsString('<button', $body);
    }

} //@formatter:on
