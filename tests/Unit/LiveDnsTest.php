<?php
/**
 * /tests/Unit/LiveDnsTest.php
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

use DomainMOD\LiveDns;
use DomainMOD\Tests\Support\ScriptedResolver as R;
use PHPUnit\Framework\TestCase;

/*
 * LiveDns, with a scripted resolver and clock, so no real DNS is used: the lookup's order, deadlines, and cleaning, the
 * result codes, the merge into the stored row (stale sets, the IP basis, and the history baselines), the comparison
 * states, the list badge, and the filter SQL. Each check is run the way a refresh runs it: lookup(), merge(), then
 * compare() on the merged row.
 */
class LiveDnsTest extends TestCase
{
    const PROFILE = array('ns1.example.net', 'ns2.example.net');
    const ASSET = '198.51.100.4';

    private $started = 1000000;

    protected function tearDown(): void
    {
        R::uninstall();
    }

    // A resolver answering the usual complete set for example.com
    private function healthy($domain = 'example.com')
    {
        return (new R())->install()
            ->answer($domain . '.', 'NS', R::ns($domain, array('ns2.example.net', 'NS1.Example.NET.')))
            ->answer($domain . '.', 'A', R::a($domain, array(self::ASSET)))
            ->answer($domain . '.', 'AAAA', R::aaaa($domain, array('2001:DB8::1')))
            ->answer('www.' . $domain . '.', 'A', R::a('www.' . $domain, array(self::ASSET)))
            ->answer($domain . '.', 'MX', R::mx($domain, array(10 => 'mail.example.net')));
    }

    /*
     * One refresh's DNS step: the observation, the merge (null when dropped), and the stored row after it with its
     * states (the stored row unchanged when dropped or not started). $seconds is how long the refresh's deadline has
     * left when the DNS step starts.
     */
    private function check(R $r, $stored, $domain = 'example.com', $seconds = 30, array $context = array())
    {
        $context += array('dns_id' => 5, 'profile' => self::PROFILE, 'ip_id' => 5, 'asset' => self::ASSET);
        $this->started += 1000000;

        $observation = LiveDns::lookup($domain, $stored, $r->now + $seconds);
        $merged = ($observation === null) ? null
            : LiveDns::merge($stored, $observation, $domain, $this->started, gmdate('Y-m-d H:i:s', (int) ($this->started / 1000000)));
        $row = ($merged === null) ? $stored : $merged['row'];
        if (is_array($row)) $row = LiveDns::compare($row, $context['dns_id'], $context['profile'], $context['ip_id'], $context['asset']) + $row;

        return array('observation' => $observation, 'merged' => $merged, 'row' => $row,
            'history' => $merged === null ? array() : $merged['history']);
    }

    public function testAHealthyDomainIsCompleteAndMatchesItsProfileAndAsset()
    {
        $r = $this->healthy();
        $c = $this->check($r, null);

        $this->assertSame('ok', $c['observation']['result']);
        $this->assertSame("ns1.example.net\nns2.example.net", $c['row']['name_servers']);
        $this->assertSame('2001:db8::1', $c['row']['aaaa']);
        $this->assertSame('10 mail.example.net', $c['row']['mx']);
        $this->assertSame('match', $c['row']['ns_state']);
        $this->assertSame('match', $c['row']['ip_state']);
        $this->assertSame('ok', $c['row']['list_state']);
        $this->assertSame('bare', $c['row']['ip_basis']);
        $this->assertSame('', $c['row']['stale_sets']);
        $this->assertSame('ns,a,aaaa,www_a,www_aaaa,mx', $c['row']['seen_sets']);
        $this->assertSame('ns', $c['row']['first_set']);
        $this->assertSame(array(), $c['history']);
        $this->assertSame('ns,ips', $c['row']['history_seen']);
    }

    public function testNamesAreAskedAbsoluteInGroupOrderAndIdnsAsPunycode()
    {
        $r = (new R())->install();
        $this->check($r, null, 'münchen.de');

        $this->assertSame(array('xn--mnchen-3ya.de.|NS', 'xn--mnchen-3ya.de.|A', 'xn--mnchen-3ya.de.|AAAA',
            'www.xn--mnchen-3ya.de.|A', 'www.xn--mnchen-3ya.de.|AAAA', 'xn--mnchen-3ya.de.|MX'), $r->calls);
    }

    public function testANameThatCantBeConvertedIsBadNameWithNoQuery()
    {
        $r = (new R())->install();
        $c = $this->check($r, null, 'bad..name');

        $this->assertSame('bad_name', $c['observation']['result']);
        $this->assertSame(array(), $r->calls);
        $this->assertSame('failed', $c['row']['list_state']);
        $this->assertSame('failed', LiveDns::lastCheckSuffix($c['row']));
    }

    public function testCleaningNormalisesNamesAndAddressesAndRemovesDuplicates()
    {
        $r = (new R())->install()
            ->answer('example.com.', 'NS', R::ns('example.com', array('NS1.EXAMPLE.NET.', ' ns1.example.net', 'ns2.bücher.example')))
            ->answer('example.com.', 'AAAA', R::aaaa('example.com', array('2001:0DB8:0000::0001', '2001:db8::1')))
            ->answer('example.com.', 'MX', array_merge(R::mx('example.com', array(20 => 'B.example.net.')),
                R::mx('example.com', array(10 => 'z.example.net')), R::mx('example.com', array(20 => 'a.example.net'))));
        $c = $this->check($r, null);

        $this->assertSame("ns1.example.net\nns2.xn--bcher-kva.example", $c['row']['name_servers']);
        $this->assertSame('2001:db8::1', $c['row']['aaaa']);
        $this->assertSame("10 z.example.net\n20 a.example.net\n20 b.example.net", $c['row']['mx']);
    }

    public function testAMalformedRecordMakesItsSetIncompleteAndKeepsTheStoredOne()
    {
        $first = $this->check($this->healthy(), null);

        $r = $this->healthy()
            ->answer('example.com.', 'A', R::a('example.com', array('203.0.113.9', 'not-an-address')))
            ->answer('example.com.', 'NS', R::ns('example.com', array('bad_name.example.net')));
        $c = $this->check($r, $first['row']);

        $this->assertSame('partial', $c['observation']['result']);
        $this->assertSame(self::ASSET, $c['row']['a']);
        $this->assertSame("ns1.example.net\nns2.example.net", $c['row']['name_servers']);
        $this->assertSame('ns,a', $c['row']['stale_sets']);
        $this->assertSame('ok', $c['row']['list_state']);
        $this->assertSame('incomplete', LiveDns::lastCheckSuffix($c['row']));
    }

    public function testRecordsOfAnotherTypeAreIgnored()
    {
        $cname = array(array('host' => 'www.example.com', 'type' => 'CNAME', 'target' => 'cdn.example.net'));
        $r = $this->healthy()->answer('www.example.com.', 'A', array_merge($cname, R::a('cdn.example.net', array('192.0.2.7'))));
        $c = $this->check($r, null);

        $this->assertSame("cname:cdn.example.net\n192.0.2.7", $c['row']['www_a']);
    }

    public function testACnameTargetIsKeptAsItsOwnLine()
    {
        $r = $this->healthy()->answer('example.com.', 'A', R::a('edge.cdn.example', array('192.0.2.20', '192.0.2.3')));
        $c = $this->check($r, null);

        $this->assertSame("cname:edge.cdn.example\n192.0.2.3\n192.0.2.20", $c['row']['a']);
        $this->assertSame(array('192.0.2.3', '192.0.2.20', '2001:db8::1'), LiveDns::usedAddresses($c['row']));
    }

    public function testOversizedSetsAreSortedBeforeTheCapSoAnswerOrderDoesntMatter()
    {
        $addresses = array();
        for ($i = 1; $i <= 120; $i++) $addresses[] = '10.0.' . intdiv($i, 250) . '.' . ($i % 250);
        $reversed = array_reverse($addresses);

        $one = $this->check($this->healthy()->answer('example.com.', 'A', R::a('example.com', $addresses)), null);
        $two = $this->check($this->healthy()->answer('example.com.', 'A', R::a('example.com', $reversed)), null);

        $this->assertSame($one['row']['a'], $two['row']['a']);
        $this->assertCount(LiveDns::SET_CAP, LiveDns::lines($one['row']['a']));
        $this->assertSame('10.0.0.1', LiveDns::lines($one['row']['a'])[0]);
        $this->assertSame('a', $one['row']['truncated_sets']);
    }

    public function testTheCapLeavesOutTheCnameLine()
    {
        $addresses = array();
        for ($i = 1; $i <= 100; $i++) $addresses[] = '10.1.0.' . $i;
        $c = $this->check($this->healthy()->answer('example.com.', 'A', R::a('edge.example.net', $addresses)), null);

        $this->assertCount(101, LiveDns::lines($c['row']['a']));
        $this->assertSame('', $c['row']['truncated_sets']);
    }

    public function testATruncatedNsSetIsUnknownAndRecordsNoHistory()
    {
        $first = $this->check($this->healthy(), null);

        $servers = array();
        for ($i = 1; $i <= 101; $i++) $servers[] = 'ns' . $i . '.example.net';
        $c = $this->check($this->healthy()->answer('example.com.', 'NS', R::ns('example.com', $servers)), $first['row']);

        $this->assertSame('unknown', $c['row']['ns_state']);
        $this->assertSame(array(), $c['history']);
        $this->assertSame("ns1.example.net\nns2.example.net", $c['row']['history_ns']);
    }

    public function testATruncatedAddressSetStillMatchesButNeverDiffers()
    {
        $addresses = array();
        for ($i = 1; $i <= 101; $i++) $addresses[] = '10.2.0.' . $i;

        $c = $this->check($this->healthy()->answer('example.com.', 'A', R::a('example.com', $addresses)), null,
            'example.com', 30, array('asset' => '10.2.0.5'));
        $this->assertSame('match', $c['row']['ip_state']);

        $c = $this->check($this->healthy()->answer('example.com.', 'A', R::a('example.com', $addresses)), null,
            'example.com', 30, array('asset' => '203.0.113.200'));
        $this->assertSame('unknown', $c['row']['ip_state']);
    }

    public function testIpv6FormsAreComparedEqual()
    {
        $c = $this->check($this->healthy(), null, 'example.com', 30, array('asset' => '2001:DB8:0::1'));
        $this->assertSame('match', $c['row']['ip_state']);

        $c = $this->check($this->healthy(), null, 'example.com', 30, array('asset' => '2001:db8::1&#x200b;'));
        $this->assertSame('not_set', $c['row']['ip_state']);
    }

    public function testAddressesOutsidePublicRangesAreStoredAndMatched()
    {
        foreach (array('192.168.1.10' => false, '::ffff:192.168.1.10' => false, 'fec0::1' => true, '::ffff:8.8.8.8' => true) as $address => $public) {

            $v6 = strpos($address, ':') !== false;
            $r = (new R())->install()->answer('example.com.', $v6 ? 'AAAA' : 'A',
                $v6 ? R::aaaa('example.com', array($address)) : R::a('example.com', array($address)));
            $c = $this->check($r, null, 'example.com', 30, array('asset' => $address));

            $this->assertSame('match', $c['row']['ip_state'], $address);
            $this->assertSame($public, LiveDns::isPublicAddress(LiveDns::usedAddresses($c['row'])[0]), $address);

        }
    }

    public function testANullMxIsKept()
    {
        foreach (array('', '.') as $target) {
            $c = $this->check($this->healthy()->answer('example.com.', 'MX', R::mx('example.com', array(0 => $target))), null);
            $this->assertSame('0 .', $c['row']['mx']);
            $this->assertSame('ok', $c['observation']['result']);
        }
    }

    public function testAProfileIsComparedThroughTheSameNormaliser()
    {
        $c = $this->check($this->healthy(), null, 'example.com', 30, array('profile' => array('NS.Example.net.', 'ns1.example.net', '')));
        $this->assertSame('differs', $c['row']['ns_state']);

        $r = $this->healthy()->answer('example.com.', 'NS', R::ns('example.com', array('ns.example.net', 'ns.xn--bcher-kva.example')));
        $c = $this->check($r, null, 'example.com', 30, array('profile' => array('NS.Example.net.', 'ns.bücher.example')));
        $this->assertSame('match', $c['row']['ns_state']);
    }

    public function testMoreThanTenLiveServersNeverMatchAProfile()
    {
        $servers = array();
        for ($i = 1; $i <= 11; $i++) $servers[] = 'ns' . $i . '.example.net';
        $c = $this->check($this->healthy()->answer('example.com.', 'NS', R::ns('example.com', $servers)), null,
            'example.com', 30, array('profile' => array_slice($servers, 0, 10)));

        $this->assertSame('differs', $c['row']['ns_state']);
    }

    public function testTheNsAndIpStatesFollowTheirRulesInOrder()
    {
        $row = $this->check($this->healthy(), null)['row'];

        $this->assertSame('not_set', LiveDns::compare($row, 1, self::PROFILE, 5, self::ASSET)['ns_state']);
        $this->assertSame('not_set', LiveDns::compare($row, 5, self::PROFILE, 1, self::ASSET)['ip_state']);
        $this->assertSame('not_set', LiveDns::compare($row, 5, self::PROFILE, 5, '-')['ip_state']);
        $this->assertSame('not_set', LiveDns::compare($row, 5, self::PROFILE, 5, 'server one')['ip_state']);
        $this->assertSame('differs', LiveDns::compare($row, 5, array('ns9.example.net'), 5, '203.0.113.1')['ns_state']);
        $this->assertSame('differs', LiveDns::compare($row, 5, self::PROFILE, 5, '203.0.113.1')['ip_state']);

        // A match with the other set never answered, then unknown once the address isn't there
        $one = array_merge($row, array('seen_sets' => 'ns,a', 'aaaa' => ''));
        $this->assertSame('match', LiveDns::compare($one, 5, self::PROFILE, 5, self::ASSET)['ip_state']);
        $this->assertSame('unknown', LiveDns::compare($one, 5, self::PROFILE, 5, '203.0.113.1')['ip_state']);

        // Both used sets stored empty
        $empty = array_merge($row, array('a' => '', 'aaaa' => ''));
        $this->assertSame('none', LiveDns::compare($empty, 5, self::PROFILE, 5, self::ASSET)['ip_state']);
        $this->assertSame('no_addresses', LiveDns::compare($empty, 5, self::PROFILE, 5, self::ASSET)['list_state']);

        // An NS set never answered, or stored empty
        $this->assertSame('unknown', LiveDns::compare(array_merge($row, array('seen_sets' => 'a,aaaa')), 5, self::PROFILE, 5, self::ASSET)['ns_state']);
        $this->assertSame('unknown', LiveDns::compare(array_merge($row, array('name_servers' => '')), 5, self::PROFILE, 5, self::ASSET)['ns_state']);

        // No basis yet, or unknown
        foreach (array('', 'unknown') as $basis) {
            $this->assertSame('unknown', LiveDns::compare(array_merge($row, array('ip_basis' => $basis)), 5, self::PROFILE, 5, self::ASSET)['ip_state']);
        }
    }

    public function testCompareTakesTheNewStatesOverTheRowsOldOnes()
    {
        $row = array_merge($this->check($this->healthy(), null)['row'], array('ns_state' => 'differs', 'ip_state' => 'differs'));
        $this->assertSame('ok', LiveDns::compare($row, 5, self::PROFILE, 5, self::ASSET)['list_state']);
    }

    public function testEveryListStateRow()
    {
        $base = array('domain' => 'example.com', 'seen_sets' => 'ns,a,aaaa,www_a,www_aaaa,mx', 'name_servers' => 'ns1.example.net',
            'a' => '', 'aaaa' => '', 'www_a' => '', 'www_aaaa' => '', 'ip_basis' => 'bare', 'ns_state' => 'match', 'ip_state' => 'match');

        $this->assertSame('unchecked', LiveDns::listState(null));
        $this->assertSame('failed', LiveDns::listState(array_merge($base, array('seen_sets' => ''))));
        $long = str_repeat('a', 62) . '.' . str_repeat('b', 62) . '.' . str_repeat('c', 62) . '.' . str_repeat('d', 58) . '.com';
        $this->assertSame('failed', LiveDns::listState(array_merge($base, array('domain' => $long, 'seen_sets' => 'www_a,www_aaaa'))));
        $this->assertSame('ok', LiveDns::listState(array_merge($base, array('seen_sets' => 'www_a,www_aaaa'))));
        $this->assertSame('no_dns', LiveDns::listState(array_merge($base, array('name_servers' => '', 'ip_basis' => 'www'))));
        $this->assertSame('both', LiveDns::listState(array_merge($base, array('ns_state' => 'differs', 'ip_state' => 'differs'))));
        $this->assertSame('ns', LiveDns::listState(array_merge($base, array('ns_state' => 'differs', 'ip_state' => 'none'))));
        $this->assertSame('ip', LiveDns::listState(array_merge($base, array('ns_state' => 'not_set', 'ip_state' => 'differs'))));
        $this->assertSame('no_addresses', LiveDns::listState(array_merge($base, array('ns_state' => 'not_set', 'ip_state' => 'none'))));
        $this->assertSame('not_set', LiveDns::listState(array_merge($base, array('ns_state' => 'unknown', 'ip_state' => 'not_set'))));
        $this->assertSame('incomplete', LiveDns::listState(array_merge($base, array('ns_state' => 'unknown', 'ip_state' => 'unknown'))));
        $this->assertSame('ok', LiveDns::listState(array_merge($base, array('ns_state' => 'unknown', 'ip_state' => 'match'))));
        $this->assertSame(LiveDns::LIST_STATES, array('unchecked', 'failed', 'no_dns', 'both', 'ns', 'ip', 'no_addresses',
            'not_set', 'incomplete', 'ok'));
    }

    public function testTheNoDnsBadgeNeedsEveryOneOfItsGuards()
    {
        $base = array('domain' => 'example.com', 'seen_sets' => 'ns,a,aaaa,www_a,www_aaaa', 'name_servers' => '',
            'a' => '', 'aaaa' => '', 'www_a' => '', 'www_aaaa' => '', 'ip_basis' => 'www', 'ns_state' => 'unknown', 'ip_state' => 'none');

        $this->assertSame('no_dns', LiveDns::listState($base));
        // Both bare sets stored empty from a partial check (basis unknown)
        $this->assertNotSame('no_dns', LiveDns::listState(array_merge($base, array('ip_basis' => 'unknown'))));
        // NS never answered
        $this->assertNotSame('no_dns', LiveDns::listState(array_merge($base, array('seen_sets' => 'a,aaaa,www_a,www_aaaa'))));
        // A www address, or a www set never answered
        $this->assertNotSame('no_dns', LiveDns::listState(array_merge($base, array('www_a' => '192.0.2.1'))));
        $this->assertNotSame('no_dns', LiveDns::listState(array_merge($base, array('seen_sets' => 'ns,a,aaaa,www_a'))));
        // Live name servers with no addresses anywhere
        $this->assertNotSame('no_dns', LiveDns::listState(array_merge($base, array('name_servers' => 'ns1.example.net'))));
    }

    // A check whose NS and bare sets answer empty while the www queries time out is no_dns, but doesn't take the No DNS
    // badge (its www sets have no answer), so the edit page and the Refresh note don't say there are no addresses
    public function testANoDnsCheckWithUnansweredWwwSetsIsntTheNoDnsBadge()
    {
        $r = (new R())->install();
        $r->answer('www.example.com.', 'A', array(), 20);
        $c = $this->check($r, null);
        $this->assertSame('no_dns', $c['observation']['result']);
        $this->assertSame('www_a,www_aaaa,mx', $c['row']['stale_sets']);
        $this->assertNotSame('no_dns', $c['row']['list_state']);
    }

    public function testEveryResultCode()
    {
        // no_dns: NS and the bare pair complete and empty, no www addresses
        $c = $this->check((new R())->install(), null);
        $this->assertSame('no_dns', $c['observation']['result']);
        $this->assertSame('no_dns', $c['row']['list_state']);
        $this->assertSame('www', $c['row']['ip_basis']);

        // ...but a www address means it isn't
        $c = $this->check((new R())->install()->answer('www.example.com.', 'A', R::a('www.example.com', array(self::ASSET))), null);
        $this->assertSame('ok', $c['observation']['result']);
        $this->assertSame('match', $c['row']['ip_state']);

        // ok, partial, and failed
        $this->assertSame('ok', $this->check($this->healthy(), null)['observation']['result']);
        $this->assertSame('partial', $this->check($this->healthy()->answer('example.com.', 'MX', false), null)['observation']['result']);
        $c = $this->check((new R())->install()->all('example.com.', false, 0.1)->all('www.example.com.', false, 0.1), null);
        $this->assertSame('failed', $c['observation']['result']);
        $this->assertSame('failed', $c['row']['list_state']);

        // timeout: a first query that overruns the cutoff
        $r = (new R())->install()->answer('example.com.', 'NS', array(), 20);
        $c = $this->check($r, null);
        $this->assertSame('timeout', $c['observation']['result']);
        $this->assertSame(array('example.com.|NS'), $r->calls);
    }

    public function testAnEmptyAnswerExactlyAtTheCutoffIsATimeout()
    {
        $r = (new R())->install()->answer('example.com.', 'NS', array(), LiveDns::DNS_DEADLINE);
        $c = $this->check($r, null);

        $this->assertSame('timeout', $c['observation']['result']);
        $this->assertSame('ns,a,aaaa,www_a,www_aaaa,mx', $c['row']['stale_sets']);
        $this->assertSame('', $c['row']['seen_sets']);
    }

    public function testAFailureWithTooLittleTimeLeftStopsWithoutAnotherQuery()
    {
        $r = (new R())->install()->answer('example.com.', 'NS', false, 12);
        $c = $this->check($r, null);

        $this->assertSame(array('example.com.|NS'), $r->calls);
        $this->assertSame('timeout', $c['observation']['result']);
    }

    public function testTheStepDoesntStartWithLessThanFiveSecondsLeft()
    {
        $r = (new R())->install();
        $c = $this->check($r, null, 'example.com', 4.9);

        $this->assertNull($c['observation']);
        $this->assertSame(array(), $r->calls);
    }

    public function testTheCutoffIsCappedByTheRefreshDeadline()
    {
        // The main step finished at second 10 of 45: the cutoff is second 25, so a query ready at second 22 never starts
        $r = (new R())->install()->answer('example.com.', 'NS', R::ns('example.com', self::PROFILE), 6)
            ->answer('example.com.', 'A', array(), 6);
        $c = $this->check($r, null, 'example.com', 35);

        $this->assertSame(array('example.com.|NS', 'example.com.|A'), $r->calls);
        $this->assertSame('partial', $c['observation']['result']);

        // A deadline closer than DNS_DEADLINE cuts the step sooner
        $r = (new R())->install();
        $r->answer('example.com.', 'NS', array(), 4.5)->answer('example.com.', 'A', array(), 3);
        $this->check($r, null, 'example.com', 9);
        $this->assertSame(array('example.com.|NS'), $r->calls);
    }

    public function testANameTooLongForWwwHasCompleteEmptyWwwSetsWithoutQueries()
    {
        $long = str_repeat('a', 62) . '.' . str_repeat('b', 62) . '.' . str_repeat('c', 62) . '.' . str_repeat('d', 58) . '.com';
        $this->assertTrue(LiveDns::wwwTooLong($long));

        // Even when the NS query uses up the time
        $r = (new R())->install()->answer($long . '.', 'NS', array(), 20);
        $c = $this->check($r, null, $long);
        $this->assertSame(array($long . '.|NS'), $r->calls);
        $this->assertSame('timeout', $c['observation']['result']);
        $this->assertSame('www_a,www_aaaa', $c['row']['seen_sets']);
        $this->assertSame('1970-01-01 00:00:00', $c['row']['last_ok_time']);
        $this->assertSame('failed', $c['row']['list_state']);

        // Every real query failing with time left is failed, never partial
        $r = (new R())->install()->all($long . '.', false, 0.1);
        $c = $this->check($r, null, $long);
        $this->assertSame('failed', $c['observation']['result']);
        $this->assertSame('failed', $c['row']['list_state']);
        foreach ($r->calls as $call) $this->assertStringStartsNotWith('www.', $call);
    }

    public function testStaleSetsGoFirstTakingTurnsWithPairsKeptTogether()
    {
        $stored = array('stale_sets' => 'aaaa,mx', 'first_set' => 'ns');
        $this->assertSame(array('aaaa', 'a', 'mx', 'ns', 'www_a', 'www_aaaa'), LiveDns::queryOrder($stored));

        $stored = array('stale_sets' => 'a,aaaa,mx', 'first_set' => 'aaaa');
        $this->assertSame(array('mx', 'a', 'aaaa', 'ns', 'www_a', 'www_aaaa'), LiveDns::queryOrder($stored));

        $this->assertSame(array('ns', 'a', 'aaaa', 'www_a', 'www_aaaa', 'mx'), LiveDns::queryOrder(null));
        $this->assertSame(array('ns', 'a', 'aaaa', 'www_a', 'www_aaaa', 'mx'), LiveDns::queryOrder(array('stale_sets' => '', 'first_set' => 'mx')));
    }

    public function testAWwwOnlyDomainWithSlowAnswersStillGetsItsHistory()
    {
        // Every query takes 4 seconds, so three start per check
        $make = function ($www) {
            $r = (new R())->install();
            $r->default_seconds = 4;
            $r->answer('example.com.', 'NS', R::ns('example.com', self::PROFILE), 4);
            $r->answer('www.example.com.', 'A', R::a('www.example.com', array($www)), 4);
            return $r;
        };

        $row = null;
        $bases = array();
        for ($i = 0; $i < 4; $i++) {
            $c = $this->check($make('192.0.2.50'), $row);
            $row = $c['row'];
            $bases[] = $row['ip_basis'];
            $this->assertSame(array(), $c['history']);
        }
        $this->assertContains('www', $bases);
        $this->assertStringContainsString('ips', $row['history_seen']);
        $this->assertSame('www: 192.0.2.50', $row['history_ips']);

        $recorded = array();
        for ($i = 0; $i < 4 && !$recorded; $i++) {
            $c = $this->check($make('192.0.2.60'), $row);
            $row = $c['row'];
            $recorded = $c['history'];
        }
        $this->assertSame(array(array('live_ips', 'www: 192.0.2.50', 'www: 192.0.2.60')), $recorded);
    }

    public function testAnNsQueryThatAlwaysFailsSlowlyDoesntKeepMxFromBeingAsked()
    {
        $r = null;
        $row = null;
        $asked = false;
        for ($i = 0; $i < 4 && !$asked; $i++) {
            $r = (new R())->install();
            $r->default_seconds = 4;
            $r->answer('example.com.', 'NS', false, 4);
            $row = $this->check($r, $row)['row'];
            $asked = in_array('example.com.|MX', $r->calls, true);
        }
        $this->assertTrue($asked);
    }

    public function testWithOneQueryPerCheckBothHalvesOfAPairAreReached()
    {
        // 8 seconds left and 4-second answers: one query per check
        $row = null;
        $asked = array();
        for ($i = 0; $i < 8; $i++) {
            $r = (new R())->install();
            $r->default_seconds = 4;
            $row = $this->check($r, $row, 'example.com', 8)['row'];
            $asked = array_merge($asked, $r->calls);
        }
        $this->assertContains('example.com.|AAAA', $asked);
        $this->assertContains('www.example.com.|AAAA', $asked);
        $this->assertSame('ns,a,aaaa,www_a,www_aaaa,mx', $row['seen_sets']);
    }

    public function testTheIpBasisRules()
    {
        // A first check: bare A complete and empty, bare AAAA failing, www with an address: unknown, www not used
        $r = (new R())->install()->answer('example.com.', 'AAAA', false)
            ->answer('www.example.com.', 'A', R::a('www.example.com', array(self::ASSET)));
        $c = $this->check($r, null);
        $this->assertSame('unknown', $c['row']['ip_basis']);
        $this->assertSame('unknown', $c['row']['ip_state']);

        // Basis bare, then bare A empty and bare AAAA timing out: unknown, and not No DNS
        $bare = $this->check($this->healthy(), null)['row'];
        $r = $this->healthy()->answer('example.com.', 'A', array())->answer('example.com.', 'AAAA', array(), 20);
        $c = $this->check($r, $bare);
        $this->assertSame('unknown', $c['row']['ip_basis']);
        $this->assertSame('unknown', $c['row']['ip_state']);
        $this->assertNotSame('no_dns', $c['row']['list_state']);

        // Basis www, then the same check: stays www, and the www address is still compared
        $www = $this->check((new R())->install()->answer('www.example.com.', 'A', R::a('www.example.com', array(self::ASSET))), null)['row'];
        $this->assertSame('www', $www['ip_basis']);
        $r = (new R())->install()->answer('example.com.', 'AAAA', array(), 20);
        $c = $this->check($r, $www);
        $this->assertSame('www', $c['row']['ip_basis']);
        $this->assertSame('match', $c['row']['ip_state']);

        // Only one bare set complete, with addresses: bare
        $r = (new R())->install()->answer('example.com.', 'A', R::a('example.com', array(self::ASSET)))->answer('example.com.', 'AAAA', false);
        $this->assertSame('bare', $this->check($r, $www)['row']['ip_basis']);

        // A check completing nothing never moves it
        $r = (new R())->install()->answer('example.com.', 'NS', false, 20);
        $this->assertSame('www', $this->check($r, $www)['row']['ip_basis']);
    }

    public function testADifferenceSurvivesACheckThatCompletesNothing()
    {
        $differs = $this->check((new R())->install()->answer('www.example.com.', 'A', R::a('www.example.com', array('203.0.113.77'))), null)['row'];
        $this->assertSame('ip', $differs['list_state']);

        $c = $this->check((new R())->install()->answer('example.com.', 'NS', false, 20), $differs);
        $this->assertSame('www', $c['row']['ip_basis']);
        $this->assertSame('ip', $c['row']['list_state']);
        $this->assertSame('failed', LiveDns::lastCheckSuffix($c['row']));
        $this->assertSame($differs['last_ok_time'], $c['row']['last_ok_time']);
    }

    public function testAnNsDifferenceClearsWhenTheNsAnswerMatchesBeforeALaterTimeout()
    {
        $differs = $this->check($this->healthy()->answer('example.com.', 'NS', R::ns('example.com', array('ns.other.example'))), null)['row'];
        $this->assertSame('ns', $differs['list_state']);

        $r = $this->healthy()->answer('example.com.', 'A', array(), 20);
        $c = $this->check($r, $differs);
        $this->assertSame('ok', $c['row']['list_state']);
        $this->assertSame('a,aaaa,www_a,www_aaaa,mx', $c['row']['stale_sets']);
    }

    public function testHistoryNeedsACompleteBaselineFirst()
    {
        // A first NS timeout, then a complete check: no row, and only then a baseline
        $first = $this->check((new R())->install()->answer('example.com.', 'NS', false, 20), null);
        $this->assertSame('', $first['row']['history_seen']);
        $second = $this->check($this->healthy(), $first['row']);
        $this->assertSame(array(), $second['history']);
        $this->assertSame('ns,ips', $second['row']['history_seen']);

        // A complete empty NS set, then a timeout, then records: one row from the empty baseline
        $empty = $this->check($this->healthy()->answer('example.com.', 'NS', array()), null)['row'];
        $timeout = $this->check((new R())->install()->answer('example.com.', 'NS', false, 20), $empty)['row'];
        $c = $this->check($this->healthy(), $timeout);
        $this->assertSame(array(array('live_ns', '', "ns1.example.net\nns2.example.net")), $c['history']);
    }

    public function testACutShortCheckRecordsOnlyTheFieldsThatQualify()
    {
        // A, then B with AAAA failing (and NS changing), then B complete records A to B once
        $a = $this->check($this->healthy(), null)['row'];

        $r = $this->healthy()->answer('example.com.', 'NS', R::ns('example.com', array('ns.new.example')))
            ->answer('example.com.', 'A', R::a('example.com', array('203.0.113.5')))->answer('example.com.', 'AAAA', false);
        $b = $this->check($r, $a);
        $this->assertSame(array(array('live_ns', "ns1.example.net\nns2.example.net", 'ns.new.example')), $b['history']);

        $r = $this->healthy()->answer('example.com.', 'NS', R::ns('example.com', array('ns.new.example')))
            ->answer('example.com.', 'A', R::a('example.com', array('203.0.113.5')));
        $c = $this->check($r, $b['row']);
        $this->assertSame(array(array('live_ips', "198.51.100.4\n2001:db8::1", "203.0.113.5\n2001:db8::1")), $c['history']);

        $this->assertSame(array(), $this->check($r, $c['row'])['history']);
    }

    public function testAnIpHistoryValueLeavesOutTheCnameLine()
    {
        $a = $this->check($this->healthy()->answer('example.com.', 'A', R::a('one.cdn.example', array(self::ASSET))), null)['row'];
        $c = $this->check($this->healthy()->answer('example.com.', 'A', R::a('two.cdn.example', array(self::ASSET))), $a);

        $this->assertSame(array(), $c['history']);
        $this->assertStringStartsWith('cname:two.cdn.example', $c['row']['a']);
    }

    public function testATruncatedAddressSetRecordsNothingAndKeepsTheBaseline()
    {
        $a = $this->check($this->healthy(), null)['row'];
        $addresses = array();
        for ($i = 1; $i <= 101; $i++) $addresses[] = '10.3.0.' . $i;
        $c = $this->check($this->healthy()->answer('example.com.', 'A', R::a('example.com', $addresses)), $a);

        $this->assertSame(array(), $c['history']);
        $this->assertSame($a['history_ips'], $c['row']['history_ips']);
    }

    public function testASwitchToWwwIsRecordedButNotFromAFailedBareQuery()
    {
        $bare = $this->check($this->healthy(), null)['row'];

        // The bare AAAA query failing can't switch the basis to www
        $r = $this->healthy()->answer('example.com.', 'A', array())->answer('example.com.', 'AAAA', false);
        $c = $this->check($r, $bare);
        $this->assertSame(array(), $c['history']);

        // Both bare sets complete and empty: the switch is recorded with www lines
        $r = $this->healthy()->answer('example.com.', 'A', array())->answer('example.com.', 'AAAA', array());
        $c = $this->check($r, $c['row']);
        $this->assertSame(array(array('live_ips', "198.51.100.4\n2001:db8::1", 'www: ' . self::ASSET)), $c['history']);
    }

    public function testAnOlderObservationIsDropped()
    {
        $stored = $this->check($this->healthy(), null)['row'];
        $observation = LiveDns::lookup('example.com', $stored, (new R())->install()->now + 30);

        $this->assertNull(LiveDns::merge($stored, $observation, 'example.com', $stored['started_us'], '2026-11-20 00:00:00'));
        $this->assertNull(LiveDns::merge($stored, $observation, 'example.com', $stored['started_us'] - 1, '2026-11-20 00:00:00'));
        $this->assertNotNull(LiveDns::merge($stored, $observation, 'example.com', $stored['started_us'] + 1, '2026-11-20 00:00:00'));
    }

    public function testARowForAnotherNameIsAnEmptyBaseline()
    {
        $old = $this->check($this->healthy('old.example'), null, 'old.example')['row'];
        $old = array_merge($old, array('started_us' => PHP_INT_MAX, 'stale_sets' => 'mx', 'first_set' => 'ns'));

        $r = $this->healthy()->answer('example.com.', 'NS', R::ns('example.com', array('ns.other.example')))
            ->answer('example.com.', 'A', array(), 20);
        $c = $this->check($r, $old);

        $this->assertNotNull($c['merged']);
        $this->assertSame(array(), $c['history']);
        $this->assertSame('example.com', $c['row']['domain']);
        $this->assertSame('ns', $c['row']['seen_sets']);
        $this->assertSame('', $c['row']['a']);
        $this->assertSame('ns', $c['row']['history_seen']);
        // The other name's stale sets don't steer this name's query order
        $this->assertSame('example.com.|NS', $r->calls[0]);
    }

    public function testTheFilterSql()
    {
        $this->assertSame(" AND EXISTS (SELECT 1 FROM domain_dns_check AS ldcf WHERE ldcf.domain_id = d.id AND ldcf.domain = d.domain AND ldcf.list_state IN ('both', 'ns', 'ip')) ",
            LiveDns::filterCondition('any'));
        $this->assertStringContainsString("IN ('both', 'ns'))", LiveDns::filterCondition('ns'));
        $this->assertStringContainsString("IN ('both', 'ip'))", LiveDns::filterCondition('ip'));
        $this->assertStringContainsString("IN ('no_dns'))", LiveDns::filterCondition('nodns'));
        $this->assertStringContainsString("IN ('failed'))", LiveDns::filterCondition('failed'));
        $this->assertSame(" AND NOT EXISTS (SELECT 1 FROM domain_dns_check AS ldcf WHERE ldcf.domain_id = domains.id AND ldcf.domain = domains.domain) ",
            LiveDns::filterCondition('unchecked', 'domains'));
        $this->assertStringContainsString('= d.id', LiveDns::filterCondition('any', 'x; DROP'));

        foreach (array('', 'ok', 'ANY', array('ns'), null, 1) as $value) $this->assertSame('', LiveDns::filterCondition($value));
    }

    // The retry sets: a first incomplete check retries every set it missed, a resolver that answers each set every other
    // check (three queries a check) stops retrying from the second check on, and a set that keeps failing keeps retrying
    public function testTheRetryEndsWhenEverySetIsAnsweredEveryTwoChecks()
    {
        $r = $this->healthy();
        $r->default_seconds = 4;
        foreach (array_keys($r->answers) as $key) $r->answers[$key][1] = 4;

        $c = $this->check($r, null);
        $this->assertSame('www_a,www_aaaa,mx', $c['row']['stale_sets']);
        $this->assertSame('www_a,www_aaaa,mx', $c['row']['retry_sets']);

        for ($i = 0; $i < 4; $i++) {
            $c = $this->check($r, $c['row']);
            $this->assertNotSame('', $c['row']['stale_sets']);
            $this->assertSame('', $c['row']['retry_sets']);
        }
    }

    public function testASetThatKeepsFailingKeepsRetrying()
    {
        $r = $this->healthy()->answer('example.com.', 'MX', false);

        $c = $this->check($r, null);
        $this->assertSame('mx', $c['row']['retry_sets']);
        $c = $this->check($r, $c['row']);
        $this->assertSame('mx', $c['row']['retry_sets']);

        $r->answer('example.com.', 'MX', R::mx('example.com', array(10 => 'mail.example.net')));
        $c = $this->check($r, $c['row']);
        $this->assertSame('', $c['row']['retry_sets']);
    }

    public function testARowForAnotherNameRetriesEverySetTheCheckMissed()
    {
        $r = $this->healthy()->answer('example.com.', 'MX', false);
        $old = array_merge(LiveDns::emptyRow('old.example'), array('started_us' => 5, 'stale_sets' => ''));
        $c = $this->check($r, $old);
        $this->assertSame('mx', $c['row']['retry_sets']);
    }

    // The table's one copy of its SQL is the installer's, with every column isAvailable() checks, and every column the
    // stored row (merge() and emptyRow()) writes
    public function testTheTableMatchesTheInstaller()
    {
        $source = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/install/go.php'));
        $this->assertStringContainsString(preg_replace('/\s+/', ' ', trim(LiveDns::tableSql())), $source);
        $this->assertStringContainsString("`display_domain_live_dns` TINYINT(1) NOT NULL DEFAULT '0'", $source);
        foreach (LiveDns::TABLE_COLUMNS as $column) {
            $this->assertStringContainsString('`' . $column . '`', LiveDns::tableSql());
        }
        $row = array_keys(LiveDns::emptyRow('example.com'));
        sort($row);
        $columns = array_values(array_diff(LiveDns::TABLE_COLUMNS, array('domain_id')));
        sort($columns);
        $this->assertSame($columns, $row);
    }

    // The Refresh message says the domain has no live name servers or IP addresses only when the stored sets say so
    // (the No DNS badge): a no_dns check whose www sets weren't answered reads as incomplete
    public function testTheRefreshNoteSaysNoDnsOnlyWithTheBadge()
    {
        $refresh = \DomainMOD\Tests\Support\Instance::withoutConstructor(\DomainMOD\DomainRefresh::class, array());
        $lines = new \ReflectionMethod(\DomainMOD\DomainRefresh::class, 'liveDnsLines');
        $row = array('ns_state' => 'unknown', 'ip_state' => 'none', 'list_state' => 'no_dns', 'ip_basis' => 'www');
        $this->assertSame(array('The domain has no live name servers or IP addresses.'), $lines->invoke($refresh, $row, 'no_dns', '198.51.100.4'));
        $row['list_state'] = 'no_addresses';
        $this->assertSame(array('Some live DNS records couldn&#039;t be checked this time.', 'The domain has no live addresses.'),
            $lines->invoke($refresh, $row, 'no_dns', '198.51.100.4'));
    }

    // Starts in microseconds are decimal strings, compared without PHP integers, so they work where PHP_INT_SIZE is 4
    // (2,147,483,647 is the largest int there, far below a start such as 1795254669148403). Values past even a 64-bit
    // int show the comparison never depends on the integer size.
    public function testStartsInMicrosecondsNeverNeedAnInteger()
    {
        $this->assertSame(1, LiveDns::usCompare('1795254669148404', '1795254669148403'));
        $this->assertSame(-1, LiveDns::usCompare('999999999', '1795254669148403'));
        $this->assertSame(0, LiveDns::usCompare('0001795254669148403', 1795254669148403));
        $this->assertSame(1, LiveDns::usCompare('99999999999999999999999', '99999999999999999999998'));
        $this->assertSame(-1, LiveDns::usCompare('9223372036854775808', '18446744073709551616'));
        $this->assertSame('0', LiveDns::usNormal(''));
        $this->assertSame('0', LiveDns::usNormal('-5'));
        $this->assertSame('0', LiveDns::usNormal('1.5'));
        $this->assertSame('1795254669000042', LiveDns::usFrom(1795254669, 42));
        $this->assertSame(1795254669, LiveDns::usSeconds('1795254669148403'));
        $this->assertSame(0, LiveDns::usSeconds('999999'));
        $now = LiveDns::nowUs();
        $this->assertMatchesRegularExpression('/^\d{16}$/', $now);
        $this->assertLessThanOrEqual(1, abs(LiveDns::usSeconds($now) - time()));

        // merge() keeps the start as a string and orders by it, with a start past a 64-bit int too
        $r = $this->healthy();
        $observation = LiveDns::lookup('example.com', null, $r->now + 30);
        $first = LiveDns::merge(null, $observation, 'example.com', '18446744073709551616000', '2026-11-21 10:00:00');
        $this->assertSame('18446744073709551616000', $first['row']['started_us']);
        $this->assertNull(LiveDns::merge($first['row'], $observation, 'example.com', '18446744073709551615999', '2026-11-21 10:00:00'));
        $this->assertNotNull(LiveDns::merge($first['row'], $observation, 'example.com', '18446744073709551616001', '2026-11-21 10:00:00'));
    }

} //@formatter:on
