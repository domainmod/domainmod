<?php
/**
 * /tests/Unit/LayoutReadOnlyAddTest.php
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

use DomainMOD\Layout;
use PHPUnit\Framework\TestCase;

/*
 * Read-only users can't add anything, so setPageAddAction() and addPageAddMenuItem() do nothing for them, while their
 * other page actions (such as Export) still show. Page actions are kept in static properties, so they and the session
 * flag are reset before and after each test.
 */
class LayoutReadOnlyAddTest extends TestCase
{
    private $had_read_only;
    private $read_only;

    protected function setUp(): void
    {
        $this->had_read_only = isset($_SESSION) && array_key_exists('s_read_only', $_SESSION);
        $this->read_only = $this->had_read_only ? $_SESSION['s_read_only'] : null;
        $this->clearPageActions();
    }

    protected function tearDown(): void
    {
        if ($this->had_read_only) {
            $_SESSION['s_read_only'] = $this->read_only;
        } else {
            unset($_SESSION['s_read_only']);
        }
        $this->clearPageActions();
    }

    public function testReadOnlyUsersGetNoAddButtonOrAddMenu()
    {
        $_SESSION['s_read_only'] = '1';
        $html = $this->declareActions();

        $this->assertStringContainsString('>Export</a>', $html);
        $this->assertStringNotContainsString('add.php', $html);
        $this->assertStringNotContainsString('csv-importer', $html);
        $this->assertStringNotContainsString('btn-danger', $html);
    }

    public function testReadOnlyUsersGetNothingWhenAddIsThePageOnlyAction()
    {
        $_SESSION['s_read_only'] = '1';
        $layout = new Layout();
        $layout->setPageAddAction('Add Web Server', 'add-server.php');

        $this->assertSame('', $layout->renderPageActions());
    }

    public function testReadOnlyUsersGetNoActionThatChangesData()
    {
        $_SESSION['s_read_only'] = '1';
        $layout = new Layout();
        $layout->addPageAction('Manage Servers', 'servers.php', 'bi-hdd-stack');
        $layout->addPageAction('Rebuild DW', 'rebuild.php?token=1', 'bi-arrow-repeat', array('confirm' => 'Rebuild now?'));
        $html = $layout->renderPageActions();

        $this->assertStringContainsString('servers.php', $html);
        $this->assertStringNotContainsString('rebuild.php', $html);
        $this->assertStringNotContainsString('Rebuild DW', $html);
    }

    public function testOtherUsersStillGetActionsThatChangeData()
    {
        $_SESSION['s_read_only'] = '0';
        $layout = new Layout();
        $layout->addPageAction('Rebuild DW', 'rebuild.php?token=1', 'bi-arrow-repeat', array('confirm' => 'Rebuild now?'));

        $this->assertStringContainsString('Rebuild DW', $layout->renderPageActions());
    }

    public function testOtherUsersStillGetTheAddButtonAndItsMenu()
    {
        foreach (array('0', null) as $read_only) {
            $this->clearPageActions();
            if ($read_only === null) {
                unset($_SESSION['s_read_only']);
            } else {
                $_SESSION['s_read_only'] = $read_only;
            }
            $html = $this->declareActions();

            $this->assertStringContainsString('<a class="btn btn-danger btn-sm" href="add.php">', $html);
            $this->assertStringContainsString('<a class="dropdown-item" href="csv-importer/">', $html);
            $this->assertStringContainsString('>Export</a>', $html);
        }
    }

    private function declareActions()
    {
        $layout = new Layout();
        $layout->addPageAction('Export', 'index.php?export_data=1', 'bi-download');
        $layout->setPageAddAction('Add Domain', 'add.php');
        $layout->addPageAddMenuItem('CSV Importer', 'csv-importer/', 'bi-filetype-csv');
        return $layout->renderPageActions();
    }

    private function clearPageActions()
    {
        $reflection = new \ReflectionClass(Layout::class);
        $reflection->setStaticPropertyValue('page_actions', array());
        $reflection->setStaticPropertyValue('page_add_action', null);
        $reflection->setStaticPropertyValue('page_add_menu', array());
    }

} //@formatter:on
