<?php
/**
 * /tests/Unit/DomainNameSearchTest.php
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
use DomainMOD\DomainNameSearch;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

/*
 * DomainNameSearch, the Bulk Updater's domain picker search (domains/name-search.php): its terms, its SQL and
 * parameters, and how it turns the grouped rows into results, without a database.
 */
class DomainNameSearchTest extends TestCase
{
    // A search answered with $rows (each array('domain', 'statuses', 'records'), as the grouped query gives them)
    private function find($text, array $rows, &$pdo = null)
    {
        $pdo = new RecordingPdo(array('FROM domains' => array($rows)));
        // Domain's constructor connects to the database, which checkFormat() doesn't need
        $search = new DomainNameSearch($pdo, Instance::withoutConstructor(Domain::class));
        return $search->find($text);
    }

    private function row($domain, $statuses = '1', $records = 1)
    {
        return array('domain' => $domain, 'statuses' => $statuses, 'records' => (string) $records);
    }

    private function names(array $found)
    {
        return array_column($found['results'], 'domain');
    }

    public function testTermsAreTheTextInLowercase()
    {
        $this->assertSame(array('example'), DomainNameSearch::terms('  ExAmple '));
    }

    public function testPunycodeAlsoSearchesTheStoredForm()
    {
        $this->assertSame(array('xn--mnchen-3ya.de', 'münchen.de'), DomainNameSearch::terms('XN--MNCHEN-3YA.DE'));
    }

    public function testAStoredFormThatMatchesAddsNoSecondTerm()
    {
        $this->assertSame(array('münchen.de'), DomainNameSearch::terms('MÜNCHEN.DE'));
    }

    public function testATrailingDotAddsTheNameWithoutIt()
    {
        $this->assertSame(array('example.com.', 'example.com'), DomainNameSearch::terms('example.com.'));
    }

    public function testAnInvalidNameIsSearchedAsTyped()
    {
        $this->assertSame(array('old_name'), DomainNameSearch::terms('old_name'));
        $this->assertSame(array('*.com'), DomainNameSearch::terms('*.com'));
    }

    public function testWildcardsAndBackslashesAreMatchedLiterally()
    {
        $query = DomainNameSearch::query(array('a%b_c\\d'));

        $this->assertSame('%a\\%b\\_c\\\\d%', $query['params']['c1']);
        $this->assertSame('a\\%b\\_c\\\\d%', $query['params']['p1']);
        $this->assertSame('%.a\\%b\\_c\\\\d%', $query['params']['d1']);
        $this->assertSame('%-a\\%b\\_c\\\\d%', $query['params']['h1']);
        $this->assertSame(4, substr_count($query['sql'], "ESCAPE '\\\\'"));
    }

    public function testOneTermsQueryAndItsOrder()
    {
        $query = DomainNameSearch::query(array('shop'));

        $this->assertSame(array('c1' => '%shop%', 'p1' => 'shop%', 'd1' => '%.shop%', 'h1' => '%-shop%'), $query['params']);
        $sql = preg_replace('/\s+/', ' ', $query['sql']);
        $this->assertStringContainsString("FROM domains WHERE domain LIKE :c1 ESCAPE '\\\\' GROUP BY domain", $sql);
        $this->assertStringContainsString("ORDER BY CASE WHEN domain LIKE :p1 ESCAPE '\\\\' THEN 0"
            . " WHEN domain LIKE :d1 ESCAPE '\\\\' OR domain LIKE :h1 ESCAPE '\\\\' THEN 1 ELSE 2 END, domain", $sql);
        $this->assertStringContainsString('GROUP_CONCAT(DISTINCT active ORDER BY active) AS statuses, COUNT(*) AS records', $sql);
        $this->assertStringEndsWith('LIMIT 5001', $sql);
        $this->assertStringNotContainsString('JOIN', $sql);
    }

    public function testTwoTermsEachHaveTheirOwnParameters()
    {
        $query = DomainNameSearch::query(array('xn--mnchen-3ya.de', 'münchen.de'));

        $this->assertSame(array('c1', 'p1', 'd1', 'h1', 'c2', 'p2', 'd2', 'h2'), array_keys($query['params']));
        $this->assertSame('%münchen.de%', $query['params']['c2']);
        $sql = preg_replace('/\s+/', ' ', $query['sql']);
        $this->assertStringContainsString("WHERE domain LIKE :c1 ESCAPE '\\\\' OR domain LIKE :c2 ESCAPE '\\\\'", $sql);
        $this->assertStringContainsString("WHEN domain LIKE :p1 ESCAPE '\\\\' OR domain LIKE :p2 ESCAPE '\\\\' THEN 0", $sql);
        // Each placeholder is used once, since native prepares can't repeat a name
        foreach (array_keys($query['params']) as $name) {
            $this->assertSame(1, substr_count($sql, ':' . $name . ' '), $name);
        }
    }

    public function testTheParametersAreBound()
    {
        $this->find('Shop', array(), $pdo);

        $this->assertCount(1, $pdo->log);
        $this->assertSame(array('c1' => '%shop%', 'p1' => 'shop%', 'd1' => '%.shop%', 'h1' => '%-shop%'),
            $pdo->log[0]['params']);
    }

    public function testResultsCarryTheNameItsPunycodeAndItsStatus()
    {
        $found = $this->find('m', array($this->row('münchen.de', '1'), $this->row('example.com', '0')));

        $this->assertSame(array(
            array('domain' => 'münchen.de', 'ascii' => 'xn--mnchen-3ya.de', 'status' => 'Active'),
            array('domain' => 'example.com', 'ascii' => 'example.com', 'status' => 'Expired'),
        ), $found['results']);
        $this->assertFalse($found['more']);
    }

    public function testEveryStatusHasItsName()
    {
        $names = array('0' => 'Expired', '1' => 'Active', '2' => 'Pending (Transfer)', '3' => 'Pending (Renewal)',
            '4' => 'Pending (Other)', '5' => 'Pending (Registration)', '10' => 'Sold');
        foreach ($names as $code => $name) {
            $this->assertSame($name, DomainNameSearch::status((string) $code, 1));
        }
    }

    public function testANameWithTwoRecordsOfOneStatusShowsTheCount()
    {
        $found = $this->find('a', array($this->row('a.com', '1', 2)));

        $this->assertSame('Active, 2 records', $found['results'][0]['status']);
    }

    public function testANameWithTwoRecordsOfDifferentStatusesShowsBoth()
    {
        $found = $this->find('a', array($this->row('a.com', '1,10', 2)));

        $this->assertSame(array('a.com'), $this->names($found));
        $this->assertSame('Active, Sold, 2 records', $found['results'][0]['status']);
    }

    public function testLargeRecordCountsAreFormatted()
    {
        $this->assertSame('Active, 1,200 records', DomainNameSearch::status('1', 1200));
    }

    public function testNamesTheBulkUpdaterCantUseAreSkipped()
    {
        $found = $this->find('e', array(
            $this->row('old_name.example'),   // refused by checkFormat()
            $this->row('xn--mnchen-3ya.de'),  // stored in its old form: the form would look up münchen.de
            $this->row('Upper.com'),          // stored before names were lowercased
            $this->row('nodot'),
            $this->row('example.com'),
        ));

        $this->assertSame(array('example.com'), $this->names($found));
        $this->assertFalse($found['more']);
    }

    public function testTrailingSpacesFromTheGroupAreRemoved()
    {
        $found = $this->find('example', array($this->row('example.com  ', '1', 2)));

        $this->assertSame(array('domain' => 'example.com', 'ascii' => 'example.com', 'status' => 'Active, 2 records'),
            $found['results'][0]);
    }

    public function testFiftyResultsAndMoreWhenAFiftyFirstUsableNameFollows()
    {
        $rows = array();
        for ($i = 1; $i <= 60; $i++) $rows[] = $this->row(sprintf('name%02d.com', $i));

        $found = $this->find('name', $rows);

        $this->assertCount(50, $found['results']);
        $this->assertSame('name50.com', $found['results'][49]['domain']);
        $this->assertTrue($found['more']);
    }

    public function testExactlyFiftyUsableNamesHaveNoMore()
    {
        $rows = array();
        for ($i = 1; $i <= 50; $i++) $rows[] = $this->row(sprintf('name%02d.com', $i));
        $rows[] = $this->row('bad_name.com');

        $found = $this->find('name', $rows);

        $this->assertCount(50, $found['results']);
        $this->assertFalse($found['more']);
    }

    public function testSkippedNamesDontShortenTheResults()
    {
        $rows = array();
        for ($i = 1; $i <= 51; $i++) {
            $rows[] = $this->row(sprintf('bad_%02d.com', $i));
            $rows[] = $this->row(sprintf('good%02d.com', $i));
        }

        $found = $this->find('o', $rows);

        $this->assertCount(50, $found['results']);
        $this->assertSame('good01.com', $found['results'][0]['domain']);
        $this->assertSame('good50.com', $found['results'][49]['domain']);
        $this->assertTrue($found['more']);
    }

    public function testAReadCutShortAtItsLimitSetsMore()
    {
        $rows = array();
        for ($i = 1; $i <= 4990; $i++) $rows[] = $this->row('bad_' . $i . '.com');
        for ($i = 1; $i <= 11; $i++) $rows[] = $this->row('good' . $i . '.com');

        $found = $this->find('o', $rows);

        $this->assertCount(11, $found['results']);
        $this->assertTrue($found['more']);
    }

    public function testNoUsableNameBeforeTheLimitIsMoreWithNoResults()
    {
        $rows = array();
        for ($i = 1; $i <= 5001; $i++) $rows[] = $this->row('bad_' . $i . '.com');

        $found = $this->find('bad', $rows);

        $this->assertSame(array(), $found['results']);
        $this->assertTrue($found['more']);
    }

    public function testAReadBelowItsLimitHasNoMore()
    {
        $rows = array();
        for ($i = 1; $i <= 5000; $i++) $rows[] = $this->row('bad_' . $i . '.com');

        $found = $this->find('bad', $rows);

        $this->assertSame(array(), $found['results']);
        $this->assertFalse($found['more']);
    }

} //@formatter:on
