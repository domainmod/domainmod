<?php
/**
 * /tests/Unit/NoticeTest.php
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// The database upgrade notice (notice.php and Notice). SOFTWARE_VERSION and SOFTWARE_TITLE are constants, so the tests
// that draw the notice run in their own process and define them themselves.
class NoticeTest extends TestCase
{
    public static function upgradeCases()
    {
        return array(
            'asked for, with an upgrade waiting' => array('u', '4.25.0', true),
            'from a much older version' => array('u', '4.06.00', true),
            // notice.php once assigned 'u' here instead of comparing, so a direct visit drew "v4.26.0 to v4.26.0"
            'a direct visit' => array('', '4.25.0', false),
            'another action' => array('x', '4.25.0', false),
            'asked for, with the database already upgraded' => array('u', '4.26.0', false),
            'asked for, with no version in the session' => array('u', '', false),
            'asked for, with a version that isn\'t text' => array('u', null, false),
            'an action that isn\'t text' => array(array('u'), '4.25.0', false),
        );
    }

    #[DataProvider('upgradeCases')]
    public function testShowsTheUpgradeOnlyWhenAskedAndWaiting($action, $db_version, $expected)
    {
        $this->assertSame($expected, Notice::showsUpgrade($action, $db_version, '4.26.0'));
    }

    #[RunInSeparateProcess]
    public function testTheTitleNamesBothVersionsAndEscapesTheDatabaseOne()
    {
        $this->defineSoftware();
        $title = (new Notice())->upgradeTitle('4.25.0<b>');

        $this->assertStringContainsString('v4.25.0&lt;b&gt; to v4.26.0', $title);
        $this->assertStringNotContainsString('<b>', $title);
    }

    #[RunInSeparateProcess]
    public function testTheBodyHasOneWarningInNormalCase()
    {
        $this->defineSoftware();
        $body = (new Notice())->upgradeBody('4.25.0', '<div class="requirements-here"></div>', 'csrf_token=abc%2B1');

        $this->assertSame(1, substr_count($body, 'alert alert-danger'));
        $this->assertSame(1, substr_count($body, '>Critical Warning</h3>'));
        $this->assertStringNotContainsString('*****', $body);
        $this->assertStringNotContainsString('CRITICAL', $body);
        $this->assertStringContainsString('We strongly recommend that you back up your DomainMOD installation directory', $body);
        $this->assertStringContainsString('make a note of your current version (4.25.0)', $body);
    }

    #[RunInSeparateProcess]
    public function testTheBodyShowsTheChecklistAndTheUpgradeLink()
    {
        $this->defineSoftware();
        $body = (new Notice())->upgradeBody('4.25.0', '<div class="requirements-here"></div>', 'csrf_token=abc%2B1');

        $this->assertStringContainsString('<h3 class="h5 mt-4 mb-3">v4.26.0 Software Requirements</h3>', $body);
        $this->assertStringContainsString('<div class="requirements-here"></div>', $body);
        $this->assertStringContainsString('href="checks.php?u=1&amp;csrf_token=abc%2B1"', $body);
        $this->assertStringContainsString('Upgrade Database', $body);
    }

    #[RunInSeparateProcess]
    public function testTheBodyEscapesTheDatabaseVersion()
    {
        $this->defineSoftware();
        $body = (new Notice())->upgradeBody('4.25.0<script>', '', 'csrf_token=abc');

        $this->assertStringContainsString('(4.25.0&lt;script&gt;)', $body);
        $this->assertStringNotContainsString('<script>', $body);
    }

    private function defineSoftware()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        define('SOFTWARE_TITLE', 'DomainMOD');
    }

} //@formatter:on
