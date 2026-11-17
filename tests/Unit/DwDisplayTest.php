<?php
/**
 * /tests/Unit/DwDisplayTest.php
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

use DomainMOD\DwDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The Data Warehouse's display helpers that don't use the database: DNS records' parts and list, WHM's timestamps as
// dates, and the accounts tables' columns and cells.
class DwDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array('s_default_timezone' => 'UTC');
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    public static function recordCases()
    {
        return array(
            'A' => array(array('type' => 'A', 'name' => 'www.example.com.', 'address' => '192.0.2.10', 'ttl' => '14400', 'line' => '12'),
                'www.example.com.', '192.0.2.10', '14400', '12'),
            'AAAA' => array(array('type' => 'AAAA', 'name' => 'example.com.', 'address' => '2001:db8::1', 'ttl' => '300'),
                'example.com.', '2001:db8::1', '300', ''),
            'CNAME' => array(array('type' => 'CNAME', 'name' => 'ftp.example.com.', 'cname' => 'example.com', 'ttl' => '14400'),
                'ftp.example.com.', 'example.com', '14400', ''),
            'MX' => array(array('type' => 'MX', 'name' => 'example.com.', 'preference' => '10', 'exchange' => 'mail.example.com', 'ttl' => '14400'),
                'example.com.', '10 mail.example.com', '14400', ''),
            'NS' => array(array('type' => 'NS', 'name' => 'example.com.', 'nsdname' => 'ns1.example.net', 'ttl' => '86400'),
                'example.com.', 'ns1.example.net', '86400', ''),
            'TXT' => array(array('type' => 'TXT', 'name' => 'example.com.', 'txtdata' => 'v=spf1 +a +mx ~all', 'ttl' => '14400'),
                'example.com.', 'v=spf1 +a +mx ~all', '14400', ''),
            'SRV' => array(array('type' => 'SRV', 'name' => '_sip._tcp.example.com.', 'raw' => '0 5 5060 sip.example.com.', 'ttl' => '14400'),
                '_sip._tcp.example.com.', '0 5 5060 sip.example.com.', '14400', ''),
            'a comment has no name or TTL' => array(array('type' => 'COMMENT', 'name' => 'x', 'raw' => '; cPanel first:11.0', 'ttl' => '14400', 'line' => '1'),
                '', '; cPanel first:11.0', '', '1'),
            'the zone TTL is just its TTL' => array(array('type' => 'ZONE TTL', 'name' => 'x', 'ttl' => '14400', 'line' => '3'),
                '', '', '14400', '3'),
            'an unknown type takes the first field that has a value' => array(array('type' => 'CAA', 'name' => 'example.com.', 'address' => '', 'cname' => '', 'txtdata' => '0 issue "letsencrypt.org"', 'raw' => 'raw line', 'ttl' => '3600'),
                'example.com.', '0 issue "letsencrypt.org"', '3600', ''),
            'an unknown type with nothing but raw data' => array(array('type' => 'TLSA', 'name' => 'example.com.', 'raw' => 'raw line'),
                'example.com.', 'raw line', '', ''),
            'empty fields' => array(array(), '', '', '', ''),
        );
    }

    #[DataProvider('recordCases')]
    public function testRecordPartsUseTheRecordsOwnFields($record, $name, $value, $ttl, $line)
    {
        $parts = DwDisplay::recordParts((object) $record);

        $this->assertSame((string) ($record['type'] ?? ''), $parts['type']);
        $this->assertSame($name, $parts['name']);
        $this->assertSame($value, $parts['value']);
        $this->assertSame($ttl, $parts['ttl']);
        $this->assertSame($line, $parts['line']);
        $this->assertSame(array(), $parts['facts']);
    }

    public function testTheSoaRecordNamesItsServerAndEmailAndListsItsTimers()
    {
        $parts = DwDisplay::recordParts(array('type' => 'SOA', 'name' => 'example.com.', 'mname' => 'ns1.example.net',
            'rname' => 'hostmaster.example.com', 'serial' => '2026111601', 'refresh' => '3600', 'retry' => '1800',
            'expire' => '1209600', 'minimum' => '86400', 'ttl' => '86400'));

        $this->assertSame('example.com.', $parts['name']);
        $this->assertSame('ns1.example.net hostmaster.example.com', $parts['value']);
        $this->assertSame(array('Serial' => '2026111601', 'Refresh' => '3600', 'Retry' => '1800', 'Expire' => '1209600',
            'Minimum TTL' => '86400'), $parts['facts']);
    }

    public function testHtmlInATxtRecordIsReturnedAsPlainText()
    {
        $parts = DwDisplay::recordParts(array('type' => 'TXT', 'name' => 'x.example.com.', 'txtdata' => '<script>alert(1)</script>'));

        $this->assertSame('<script>alert(1)</script>', $parts['value']);
    }

    public function testTheRecordListEscapesEverythingAndIsolatesNamesAndValues()
    {
        $html = DwDisplay::recordList(array(
            (object) array('type' => 'TXT', 'name' => '<b>x</b>.example.com.', 'txtdata' => '"><script>alert(1)</script>', 'ttl' => '300', 'line' => '7'),
            (object) array('type' => '<i>A</i>', 'name' => 'example.com.', 'address' => '192.0.2.1')));

        $this->assertStringStartsWith('<ul class="list-unstyled mb-0 domainmod-dw-records"><li>', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('<span class="badge text-bg-secondary">TXT</span> <span class="domainmod-dw-record-name"><bdi dir="ltr">&lt;b&gt;x&lt;/b&gt;.example.com.</bdi></span> <bdi dir="ltr">&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</bdi>', $html);
        $this->assertStringContainsString('<span class="text-body-secondary">TTL 300</span> <span class="text-body-secondary">Line 7</span>', $html);
        $this->assertStringContainsString('<span class="badge text-bg-secondary">&lt;i&gt;A&lt;/i&gt;</span>', $html);
    }

    public function testTheSoaRecordsTimersAreAStackedFactList()
    {
        $html = DwDisplay::recordList(array(array('type' => 'SOA', 'name' => 'example.com.', 'mname' => 'ns1.example.net',
            'rname' => 'hostmaster.example.com', 'serial' => '5', 'refresh' => '3600', 'retry' => '1800',
            'expire' => '1209600', 'minimum' => '86400')));

        $this->assertStringContainsString('<dl class="domainmod-facts domainmod-facts-stacked"><dt>Serial</dt><dd><bdi dir="ltr">5</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Minimum TTL</dt><dd><bdi dir="ltr">86400</bdi></dd></dl></li>', $html);
    }

    public function testNoRecordsIsAnEmptyList()
    {
        $this->assertSame('', DwDisplay::recordList(array()));
    }

    public static function timestampCases()
    {
        return array(
            'a timestamp' => array(1700000000, '2023-11-14'),
            'a timestamp as text' => array('1700000000', '2023-11-14'),
            'zero' => array(0, '-'),
            'zero as text' => array('0', '-'),
            'an empty string' => array('', '-'),
            'null' => array(null, '-'),
            'a negative number' => array(-1700000000, '-'),
            'text' => array('yesterday', '-'),
            'a decimal' => array('1700000000.5', '-'),
        );
    }

    #[DataProvider('timestampCases')]
    public function testTimestampDate($timestamp, $expected)
    {
        $this->assertSame($expected, DwDisplay::timestampDate($timestamp));
    }

    public function testTimestampDateIsInTheUsersTimeZone()
    {
        $_SESSION['s_default_timezone'] = 'America/Vancouver';

        $this->assertSame('2023-11-14', DwDisplay::timestampDate(1700000000));
        // 2023-11-15 02:00 UTC is still the 14th in Vancouver
        $this->assertSame('2023-11-14', DwDisplay::timestampDate(1700013600));
    }

    public function testTheAccountCellsMatchTheColumnsWithAndWithoutTheDomain()
    {
        foreach (array(true, false) as $with_domain) {
            $columns = DwDisplay::accountColumns($with_domain);
            $this->assertCount(count($columns), DwDisplay::accountCells($this->account(), $with_domain));
            $this->assertCount(count($columns), DwDisplay::accountHeaderCells($with_domain));
            foreach (DwDisplay::accountCells($this->account(), $with_domain) as $cell) {
                $this->assertMatchesRegularExpression('#^<td[^>]*>.*</td>$#s', $cell);
            }
        }

        $this->assertSame('Domain', DwDisplay::accountColumns()[0]['label']);
        $this->assertSame('Server', DwDisplay::accountColumns(false)[0]['label']);
        $this->assertCount(count(DwDisplay::accountColumns()) - 1, DwDisplay::accountColumns(false));
    }

    public function testTheAccountHeaderMarksTheDetailsAndNumberColumns()
    {
        $cells = DwDisplay::accountHeaderCells();

        $this->assertSame('<th>Domain</th>', $cells[0]);
        $this->assertSame('<th class="text-end">Created</th>', $cells[2]);
        $this->assertSame('<th class="text-end">Disk Used</th>', $cells[5]);
        $this->assertSame('<th>Suspended</th>', $cells[6]);
        $this->assertSame('<th class="none">Contact</th>', $cells[7]);
        $this->assertSame('<th class="none">Suspend Reason</th>', end($cells));
    }

    public function testTheAccountCellsAreEscapedAndIsolated()
    {
        $cells = DwDisplay::accountCells($this->account());

        $this->assertSame('<td data-order="example.com"><bdi dir="ltr">example.com</bdi> <a class="text-decoration-underline" href="list-zones.php?domain=example.com">DNS Zone</a></td>', $cells[0]);
        // the server's name is stored escaped, and is drawn escaped once
        $this->assertSame('<td>Tom &amp; Jerry&#039;s</td>', $cells[1]);
        $this->assertSame('<td class="text-end text-nowrap" data-order="1700000000">2023-11-14</td>', $cells[2]);
        $this->assertSame('<td><bdi dir="ltr">192.0.2.10</bdi></td>', $cells[3]);
        $this->assertSame('<td>&lt;b&gt;Gold&lt;/b&gt;</td>', $cells[4]);
        $this->assertSame('<td class="text-end" data-order="1234.5">1,235 MB</td>', $cells[5]);
        $this->assertSame('<td><span class="badge text-bg-danger">Yes</span></td>', $cells[6]);
        $this->assertSame('<td><bdi dir="ltr">admin@example.com</bdi></td>', $cells[7]);
        $this->assertSame('<td><bdi dir="ltr">/home/exampl</bdi></td>', $cells[12]);
        $this->assertSame('<td>500 MB</td>', $cells[13]);
        $this->assertSame('<td>2023-11-14</td>', $cells[21]);
        $this->assertSame('<td>&quot;Unpaid&quot;</td>', $cells[22]);
    }

    public function testAnAccountThatIsntSuspendedShowsNoAndNoDate()
    {
        $account = $this->account();
        $account->suspended = '0';
        $account->suspendtime = '0';
        $account->disklimit = 'unlimited';
        $cells = DwDisplay::accountCells($account, false);

        $this->assertSame('<td>No</td>', $cells[5]);
        $this->assertSame('<td>unlimited</td>', $cells[12]);
        $this->assertSame('<td>-</td>', $cells[20]);
    }

    public function testADomainLinkIsEncoded()
    {
        $account = $this->account();
        $account->domain = 'a&b".example.com';

        $this->assertStringContainsString('href="list-zones.php?domain=a%26b%22.example.com"', DwDisplay::accountCells($account)[0]);
    }

    private function account()
    {
        return (object) array('domain' => 'example.com', 'dw_server_name' => 'Tom &amp; Jerry&#039;s',
            'unix_startdate' => '1700000000', 'ip' => '192.0.2.10', 'plan' => '<b>Gold</b>', 'diskused' => '1234.5',
            'suspended' => '1', 'email' => 'admin@example.com', 'owner' => 'root', 'user' => 'exampl',
            'theme' => 'jupiter', 'shell' => '/bin/bash', 'partition' => 'home', 'disklimit' => '500', 'maxpop' => '10',
            'maxlst' => '0', 'maxaddons' => '1', 'maxsub' => '5', 'maxsql' => '3', 'maxftp' => '2', 'maxparked' => '0',
            'suspendtime' => '1700000000', 'suspendreason' => '"Unpaid"');
    }
}
