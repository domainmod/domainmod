<?php
/**
 * /tests/Unit/ReportPageLengthTest.php
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

// The page-length menus of the reports that open at the user's number of domains or SSL certificates per page (their
// settings files). Each list opens at that number, and the menu lists it once, in order with 50, 75, 100, and All.
class ReportPageLengthTest extends TestCase
{
    // Each report's settings file, the session value it reads, and how many paged lists it sets up
    public static function reportCases()
    {
        return array(
            'At-Risk Domains' => array('reporting-domain-at-risk', 's_number_of_domains', 1),
            'Upcoming Renewals' => array('reporting-domain-upcoming-renewals', 's_number_of_domains', 1),
            'TLD Pricing' => array('reporting-domain-tld-pricing', 's_number_of_domains', 2),
            'DW Potential Problems' => array('reporting-dw-potential-problems', 's_number_of_domains', 3),
            'At-Risk SSL Certificates' => array('reporting-ssl-at-risk', 's_number_of_ssl_certs', 1),
            'SSL Upcoming Renewals' => array('reporting-ssl-upcoming-renewals', 's_number_of_ssl_certs', 1),
            'SSL Cost by Domain' => array('reporting-ssl-cost-by-domain', 's_number_of_domains', 1),
        );
    }

    public static function lengthCases()
    {
        $numbers = array(
            // The menu once read "50, 50, 75, 100, All" here
            'one of the menu\'s own numbers' => array(50, 50, array(50, 75, 100, -1)),
            'another of them' => array(100, 100, array(50, 75, 100, -1)),
            'fewer' => array(20, 20, array(20, 50, 75, 100, -1)),
            'in between' => array(60, 60, array(50, 60, 75, 100, -1)),
            'more' => array(250, 250, array(50, 75, 100, 250, -1)),
            'from the database, as text' => array('75', 75, array(50, 75, 100, -1)),
            'not signed in' => array(null, 50, array(50, 75, 100, -1)),
            'zero' => array(0, 50, array(50, 75, 100, -1)),
            'negative' => array(-1, 50, array(50, 75, 100, -1)),
        );
        $cases = array();
        foreach (self::reportCases() as $report => $report_case) {
            foreach ($numbers as $number => $number_case) {
                $cases[$report . ', ' . $number] = array_merge($report_case, $number_case);
            }
        }
        return $cases;
    }

    #[DataProvider('lengthCases')]
    public function testEachListOpensAtTheUsersNumberAndListsItOnce($settings_file, $session_key, $lists, $number, $page_length, $menu)
    {
        $_SESSION = $number === null ? array() : array($session_key => $number);

        $options = $this->datatableOptions($settings_file);

        $this->assertSame(array_fill(0, $lists, $menu), array_map(function ($items) {
            return array_map('intval', explode(', ', $items));
        }, $this->values('/"lengthMenu": \[ ([-\d, ]+) \],\s*"searching": true/', $options)));
        $this->assertSame(array_fill(0, $lists, (string) $page_length), $this->values('/"pageLength": (-?\d+),/', $options));
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    // Every match of the pattern's first group in the DataTables options
    private function values($pattern, $options)
    {
        preg_match_all($pattern, $options, $matches);

        return $matches[1];
    }

    // The $datatable_options the settings file sets (it only sets variables, so it can be loaded more than once)
    private function datatableOptions($settings_file)
    {
        require DIR_INC . '/settings/' . $settings_file . '.inc.php';

        return $datatable_options;
    }
}
