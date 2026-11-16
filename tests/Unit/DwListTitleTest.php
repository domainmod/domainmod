<?php
/**
 * /tests/Unit/DwListTitleTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The page titles of the Data Warehouse's account and zone lists (their settings files), which come from the view
// chosen on the Data Warehouse page.
class DwListTitleTest extends TestCase
{
    public static function titleCases()
    {
        return array(
            'accounts, all servers' => array('dw-list-accounts', array('s_dw_view_all' => 1), 'Listing All Server Accounts'),
            'accounts, one server' => array('dw-list-accounts', self::oneServer(), 'Listing Server Accounts on Web 1 (web1.example.com)'),
            // The title once read "Listing Server Accounts on  ()" here
            'accounts, opened directly' => array('dw-list-accounts', array(), 'Server Accounts'),
            'accounts, after leaving View All' => array('dw-list-accounts', array('s_dw_view_all' => ''), 'Server Accounts'),
            'zones, all servers' => array('dw-list-zones', array('s_dw_view_all' => 1), 'Listing All DNS Zones & Records'),
            'zones, one server' => array('dw-list-zones', self::oneServer(), 'Listing DNS Zones & Records on Web 1 (web1.example.com)'),
            'zones, opened directly' => array('dw-list-zones', array(), 'DNS Zones & Records'),
        );
    }

    #[DataProvider('titleCases')]
    public function testTheTitleNamesOnlyAChosenServer($settings_file, $session, $expected)
    {
        $_SESSION = $session;

        $this->assertSame($expected, $this->pageTitle($settings_file));
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    private static function oneServer()
    {
        return array('s_dw_view_all' => '', 's_dw_server_id' => 3, 's_dw_server_name' => 'Web 1', 's_dw_server_host' => 'web1.example.com');
    }

    // The $page_title the settings file sets (it only sets variables, so it can be loaded more than once)
    private function pageTitle($settings_file)
    {
        $page_title = null;
        require DIR_INC . '/settings/' . $settings_file . '.inc.php';

        return $page_title;
    }
}
