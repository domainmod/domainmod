<?php
/**
 * /tests/Unit/DynadotRestTest.php
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

use DomainMOD\Dynadot;
use DomainMOD\Format;
use DomainMOD\HttpClient;
use DomainMOD\Log;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\ScriptedDynadot;
use PHPUnit\Framework\TestCase;

/*
 * Dynadot's RESTful API path: signing, the details parser and list paging read from responses recorded in Dynadot's
 * sandbox (tests/Fixtures/Dynadot, cut down to the fields the parser reads, with example names), the fallback to api3
 * when Dynadot refuses the credentials, and the pacing, time budget, and deadlines. ScriptedDynadot answers from a
 * script instead of the network and runs on a clock the test controls.
 */
class DynadotRestTest extends TestCase
{
    const KEY = 'example-api-key-123';
    const SECRET = 'example-secret-456';
    const PLACEHOLDERS = array('no.dns-servers.1', 'no.dns-servers.2');
    const API3_DETAILS = '<DomainInfoResponse><DomainInfoHeader><ResponseCode>0</ResponseCode><Status>success</Status></DomainInfoHeader><DomainInfoContent><Domain><Name>two-servers-example.com</Name><Expiration>1826724874060</Expiration><NameServerSettings><Type>Name Servers</Type><NameServers><NameServer><ServerName>ns1.example.net</ServerName></NameServer><NameServer><ServerName>ns2.example.net</ServerName></NameServer></NameServers></NameServerSettings><Privacy>none</Privacy><RenewOption>auto-renew</RenewOption></Domain></DomainInfoContent></DomainInfoResponse>';
    const API3_LIST = '<ListDomainInfoResponse><ListDomainInfoHeader><ResponseCode>0</ResponseCode><Status>success</Status></ListDomainInfoHeader><ListDomainInfoContent><DomainInfoList><DomainInfo><Domain><Name>one-example.com</Name></Domain><Domain><Name>two-example.com</Name></Domain></DomainInfo></DomainInfoList></ListDomainInfoContent></ListDomainInfoResponse>';

    protected $log;

    protected function setUp(): void
    {
        // the run's state is static, so every test starts from a new run
        foreach (array('legacy_accounts' => array(), 'warned_accounts' => array(), 'last_rest_call' => null,
                       'details_budget_end' => null, 'list_paging_end' => null) as $name => $value) {
            (new \ReflectionProperty(Dynadot::class, $name))->setValue(null, $value);
        }
        Dynadot::$last_call_skipped = false;
        Dynadot::$last_list_sent = false;
        Dynadot::$last_list_fell_back = null;
        Dynadot::$list_reserve = 0;
        HttpClient::clearDeadline();
    }

    protected function tearDown(): void
    {
        HttpClient::clearDeadline();
    }

    protected function dynadot($class = ScriptedDynadot::class)
    {
        $this->log = $this->createMock(Log::class);
        return Instance::withoutConstructor($class, array('log' => $this->log, 'format' => new Format()));
    }

    protected function details($name, $domain)
    {
        return $this->dynadot()->parseRestFullInfo(ScriptedDynadot::fixture($name)[1], $domain);
    }

    // A recorded details response changed by $change, which gets the decoded domain_info by reference
    protected function changedDetails($name, callable $change)
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/Fixtures/Dynadot/' . $name . '.json'), true);
        $change($fixture['body']['data']['domain_info']);
        return json_encode($fixture['body']);
    }

    // A list page with these names, as a RESTful response
    protected function page($page, array $names, $total, $has_next_page, $http_code = 200, $privacy = 'Full Privacy')
    {
        $rows = array();
        foreach ($names as $name) $rows[] = array('domain_name' => $name, 'privacy' => $privacy, 'status' => 'active');
        return array($http_code, json_encode(array('code' => 200, 'message' => 'Success', 'data' => array(
            'domain_info_list' => $rows,
            'pagination_result' => array('page' => $page, 'page_size' => 100, 'total' => $total,
                                         'has_next_page' => $has_next_page ? 'Yes' : 'No')))));
    }

    public function testTheSignatureMatchesAnIndependentlyWorkedExample()
    {
        // worked out with Python's hmac module, as in Dynadot's sample code
        $this->assertSame('XjTTT73Z++d+9azbuqv1+HcNWuVjlqocIRbc55Fpdp8=', $this->dynadot()->signature(self::KEY,
            self::SECRET, '/restful/v2/domains/example.com', '3f2b8c1e-9d4a-4b6f-8e2d-1a2b3c4d5e6f'));
    }

    public function testRecordedDetailsReadAsApi3ReadsTheSameDomains()
    {
        $this->assertSame(array('2027-11-20', array('dns1.registrar-servers.com', 'dns2.registrar-servers.com'), '1', '0'),
            $this->details('details-two-servers', 'two-servers-example.com'));
        // one custom name server is one record, not lost (the 4.26.0 case)
        $this->assertSame(array('2027-11-20', array('dns1.registrar-servers.com'), '1', '0'),
            $this->details('details-one-server', 'one-server-example.com'));
        // parking, forwarding, and Dynadot's DNS have no name servers in api3, so they get the placeholders
        $this->assertSame(array('2027-11-20', self::PLACEHOLDERS, '1', '0'), $this->details('details-parked', 'parked-example.com'));
        $this->assertSame(array('2027-11-20', self::PLACEHOLDERS, '0', '0'), $this->details('details-forwarded', 'forwarded-example.com'));
        $this->assertSame(array('2027-11-20', self::PLACEHOLDERS, '1', '0'), $this->details('details-dynadot-dns', 'dynadot-dns-example.com'));
        $this->assertSame(array('2027-11-20', array('ns1.dynadot.com', 'ns1.dynadot.com'), '0', '1'), $this->details('details-auto-renew', 'auto-renew-example.com'));
        $this->assertSame(array('2027-11-20', array('ns1.dynadot.com', 'ns1.dynadot.com'), '1', '0'), $this->details('details-do-not-renew', 'do-not-renew-example.com'));
    }

    public function testAnInternationalDomainMatchesInEitherForm()
    {
        $expected = array('2027-11-20', array('ns1.dynadot.com', 'ns1.dynadot.com'), '0', '0');
        $this->assertSame($expected, $this->details('details-idn', 'exämple.com'));
        $this->assertSame($expected, $this->details('details-idn', 'xn--exmple-cua.com'));
    }

    public function testDetailsForAnotherDomainFail()
    {
        $this->assertNull($this->details('details-two-servers', 'one-server-example.com'));
        $this->assertNull($this->dynadot()->parseRestFullInfo($this->changedDetails('details-two-servers', function (&$info) {
            $info['domain_name'] = array('two-servers-example.com');
        }), 'two-servers-example.com'));
    }

    public function testMissingPrivacyOrRenewalReadsAsOffAndAMissingExpiryAsNoDate()
    {
        $body = $this->changedDetails('details-auto-renew', function (&$info) {
            unset($info['privacy'], $info['renew_option'], $info['expiration_date']);
        });
        $this->assertSame(array('', array('ns1.dynadot.com', 'ns1.dynadot.com'), '0', '0'),
            $this->dynadot()->parseRestFullInfo($body, 'auto-renew-example.com'));
    }

    public function testAnUnknownDnsTypeGivesThePlaceholders()
    {
        $body = $this->changedDetails('details-parked', function (&$info) {
            $info['glue_info']['glue_type'] = 'REGISTRAR_STEALTH';
        });
        $this->assertSame(self::PLACEHOLDERS, $this->dynadot()->parseRestFullInfo($body, 'parked-example.com')[1]);
    }

    public function testDamagedNameServersFailInsteadOfGivingPlaceholders()
    {
        $no_type = $this->changedDetails('details-two-servers', function (&$info) {
            unset($info['glue_info']['glue_type']);
        });
        $no_servers = $this->changedDetails('details-two-servers', function (&$info) {
            $info['glue_info']['nameserver_list'] = array(array('server_name' => ''), array('server_name' => '  '),
                array('server_name' => 123), array('ip_list' => array()));
        });
        $this->assertNull($this->dynadot()->parseRestFullInfo($no_type, 'two-servers-example.com'));
        $this->assertNull($this->dynadot()->parseRestFullInfo($no_servers, 'two-servers-example.com'));
    }

    public function testFailedOrUnreadableResponsesFail()
    {
        $d = $this->dynadot();
        $this->assertNull($d->parseRestFullInfo(ScriptedDynadot::fixture('details-not-found')[1], 'example.com'));
        $this->assertNull($d->parseRestFullInfo('', 'example.com'));
        $this->assertNull($d->parseRestFullInfo(false, 'example.com'));
        $this->assertNull($d->parseRestFullInfo('<html>Bad Gateway</html>', 'example.com'));
        $this->assertNull($d->parseRestFullInfo('{"code":200,"data":{"domain_info":"x"}}', 'example.com'));
        $failed = json_decode(ScriptedDynadot::fixture('details-two-servers')[1], true);
        $failed['code'] = 400;
        $this->assertNull($d->parseRestFullInfo(json_encode($failed), 'two-servers-example.com'));
    }

    public function testExpiryMustBeAWholeNumberOfMillisecondsInRange()
    {
        $d = $this->dynadot();
        $this->assertSame('2027-11-20', $d->processRestExpiry(1826724874060));
        $this->assertSame('2027-11-20', $d->processRestExpiry('1826724874060'));
        $this->assertSame('9999-12-31', $d->processRestExpiry('253402300799999'));
        $this->assertSame('', $d->processRestExpiry('253402300800000'));
        $this->assertSame('', $d->processRestExpiry(-1));
        $this->assertSame('', $d->processRestExpiry('999'));
        $this->assertSame('', $d->processRestExpiry(1.5));
        $this->assertSame('', $d->processRestExpiry(null));
    }

    // 32-bit PHP (the armhf Docker image) decodes the milliseconds as a float, and (int) stops there at 2038
    public function testExpiryPast2038AndAsAFloatOnAnyPhp()
    {
        $d = $this->dynadot();
        $this->assertSame('2051-07-13', $d->processRestExpiry('2572858791000'));
        $this->assertSame('2051-07-13', $d->processExpiry('2572858791000'));
        $this->assertSame('2051-07-13', $d->processExpiry('0002572858791000'));
        $this->assertSame('2038-01-19', $d->processExpiry('2147483647000'));
        $this->assertSame('2027-11-20', $d->processRestExpiry(json_decode('1826724874060')));
        $this->assertSame('', $d->processRestExpiry(1826724874060.5));
        $this->assertSame('', $d->processRestExpiry(-1826724874060.0));
    }

    public function testOnlyCredentialRefusalsCount()
    {
        $d = $this->dynadot();
        $this->assertTrue($d->isRefusal(...ScriptedDynadot::fixture('refused-wrong-secret')));
        $this->assertTrue($d->isRefusal(...ScriptedDynadot::fixture('refused-wrong-key')));
        $this->assertTrue($d->isRefusal(403, '{"code":"403","message":"Forbidden"}'));
        // other bad requests, a missing signature header, mismatched codes, and anything that isn't Dynadot's JSON
        $this->assertFalse($d->isRefusal(...ScriptedDynadot::fixture('list-out-of-range')));
        $this->assertFalse($d->isRefusal(400, '{"code":400,"error":{"description":"This command requires X-Signature. The X-Signature header not entered"}}'));
        $this->assertFalse($d->isRefusal(200, ScriptedDynadot::fixture('refused-wrong-key')[1]));
        $this->assertFalse($d->isRefusal(401, ''));
        $this->assertFalse($d->isRefusal(401, false));
        $this->assertFalse($d->isRefusal(403, '<html>Forbidden</html>'));
    }

    public function testDetailsAreSignedAndSentToTheRestfulApi()
    {
        $d = $this->dynadot();
        $d->responses[] = ScriptedDynadot::fixture('details-idn');

        $this->assertSame('2027-11-20', $d->getFullInfo(self::KEY, self::SECRET, 'exämple.com')[0]);
        $this->assertSame(array('https://api.dynadot.com/restful/v2/domains/xn--exmple-cua.com'), $d->urls);

        $headers = $d->headers[0];
        $this->assertSame('Authorization: Bearer ' . self::KEY, $headers[1]);
        $this->assertMatchesRegularExpression('/^X-Request-ID: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $headers[2]);
        $request_id = substr($headers[2], strlen('X-Request-ID: '));
        $this->assertSame('X-Signature: ' . $d->signature(self::KEY, self::SECRET, '/restful/v2/domains/xn--exmple-cua.com', $request_id), $headers[3]);
        $this->assertFalse(Dynadot::$last_call_skipped);
    }

    public function testAServerErrorFailsWithoutTryingApi3()
    {
        $d = $this->dynadot();
        $d->responses[] = array(500, ScriptedDynadot::fixture('details-two-servers')[1]);

        $this->assertSame(array('', array(), '', ''), $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));
        $this->assertCount(1, $d->urls);
    }

    public function testARefusedSecretFallsBackToApi3OnceAndIsRemembered()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('refused-wrong-secret'), array(200, self::API3_DETAILS), array(200, self::API3_DETAILS));
        $this->log->expects($this->once())->method('addEntry')->with('warning', Dynadot::FALLBACK_MESSAGE, $this->callback(function ($extra) {
            return $extra['HTTP Status'] === 400 && !str_contains(json_encode($extra), self::KEY);
        }));

        $expected = array('2027-11-20', array('ns1.example.net', 'ns2.example.net'), '0', '1');
        $this->assertSame($expected, $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));
        $this->assertSame($expected, $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));

        // one RESTful request, then api3 for both calls
        $this->assertStringStartsWith('https://api.dynadot.com/restful/', $d->urls[0]);
        $this->assertStringStartsWith('https://api.dynadot.com/api3.xml?', $d->urls[1]);
        $this->assertStringStartsWith('https://api.dynadot.com/api3.xml?', $d->urls[2]);
    }

    public function testAFallbackThatFailsGivesTheUsualFailure()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('refused-wrong-key'), array(200, '<Response><ResponseHeader><ResponseCode>-1</ResponseCode><Error>invalid key</Error></ResponseHeader></Response>'));

        $this->assertSame(array('', array(), '', ''), $d->getFullInfo(self::KEY, self::SECRET, 'example.com'));
        $this->assertCount(2, $d->urls);
        $this->assertStringContainsString('/api3.xml?', $d->urls[1]);
    }

    public function testAnAccountWithoutASecretUsesApi3Directly()
    {
        $d = $this->dynadot();
        $d->responses = array(array(200, self::API3_DETAILS), array(200, self::API3_LIST));

        $this->assertSame('2027-11-20', $d->getFullInfo(self::KEY, '', 'two-servers-example.com')[0]);
        $this->assertSame(array(2, array('one-example.com', 'two-example.com')), $d->getDomainList(self::KEY, ' '));
        $this->assertTrue(Dynadot::$last_list_sent);
        $this->assertNull(Dynadot::$last_list_fell_back);
        $this->assertStringStartsWith('https://api.dynadot.com/api3.xml?key=' . self::KEY . '&command=domain_info', $d->urls[0]);
        $this->assertSame(array(), $d->headers);
    }

    public function testRequestsArePacedToOneASecond()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('details-two-servers'), ScriptedDynadot::fixture('details-two-servers'));
        $d->transfer_seconds = 0.25;

        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');

        $this->assertEqualsWithDelta(0.75, $d->slept, 0.0001);
    }

    public function testOnceTheBudgetIsUsedNoRequestIsSent()
    {
        $d = $this->dynadot();
        $d->responses[] = ScriptedDynadot::fixture('details-two-servers');
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');

        $d->clock += Dynadot::DETAILS_BUDGET;
        $this->assertSame(array('', array(), '', ''), $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));
        $this->assertTrue(Dynadot::$last_call_skipped);
        $this->assertCount(1, $d->urls);
    }

    public function testARequestRunsWithTheBudgetAsItsDeadlineAndPutsTheOldOneBack()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('details-two-servers'), ScriptedDynadot::fixture('details-two-servers'));
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');
        $budget_end = $d->deadlines[0];
        $this->assertEqualsWithDelta(1000000.0 + Dynadot::DETAILS_BUDGET, $budget_end, 0.0001);
        $this->assertNull(HttpClient::getDeadline());

        // with two seconds of the budget left, the request still goes, with the budget's end as its deadline
        $d->clock = $budget_end - 2;
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');
        $this->assertSame($budget_end, $d->deadlines[1]);
        $this->assertFalse(Dynadot::$last_call_skipped);
        $this->assertNull(HttpClient::getDeadline());
    }

    public function testAnEarlierCallerDeadlineIsUsedAndPutBackUnchanged()
    {
        $d = $this->dynadot();
        $d->responses[] = ScriptedDynadot::fixture('details-two-servers');
        $d->transfer_seconds = 5;
        $refresh_deadline = $d->clock + 45;
        HttpClient::setDeadline($refresh_deadline);

        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');

        $this->assertSame($refresh_deadline, $d->deadlines[0]);
        $this->assertSame($refresh_deadline, HttpClient::getDeadline());
    }

    public function testAWaitThatWouldOverrunStopsTheRequestBeforeItLeaves()
    {
        $d = $this->dynadot();
        $d->responses[] = ScriptedDynadot::fixture('details-two-servers');
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');

        // a caller deadline 1.5 seconds away leaves no room for the one-second wait plus a second for the request
        HttpClient::setDeadline($d->clock + 1.5);
        $this->assertSame(array('', array(), '', ''), $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));
        $this->assertTrue(Dynadot::$last_call_skipped);
        $this->assertCount(1, $d->urls);
    }

    public function testASleepThatOverrunsStopsTheRequestBeforeItLeaves()
    {
        $d = $this->dynadot(get_class(new class extends ScriptedDynadot {
            protected function sleepFor($seconds)
            {
                parent::sleepFor($seconds + 1.5); // the process slept longer than asked
            }
        }));
        $d->responses[] = ScriptedDynadot::fixture('details-two-servers');
        $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com');

        // 2.5 seconds is enough for the one-second wait and a second for the request, until the sleep overruns
        HttpClient::setDeadline($d->clock + 2.5);
        $this->assertSame(array('', array(), '', ''), $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com'));
        $this->assertCount(1, $d->urls);
    }

    public function testRecordedPagesListEveryDomainAndPageOneIsCheckedAgain()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('list-page-1'), ScriptedDynadot::fixture('list-page-2'),
                              ScriptedDynadot::fixture('list-page-3'), ScriptedDynadot::fixture('list-page-1'));

        list($count, $list) = $d->getDomainList(self::KEY, self::SECRET);

        $this->assertSame(8, $count);
        $this->assertSame(array('two-servers-example.com', 'one-server-example.com', 'parked-example.com',
            'forwarded-example.com', 'dynadot-dns-example.com', 'auto-renew-example.com', 'do-not-renew-example.com',
            'xn--exmple-cua.com'), $list);
        $this->assertSame('https://api.dynadot.com/restful/v2/domains?page=2&page_size=100&sort=name_asc', $d->urls[1]);
        $this->assertCount(4, $d->urls);
        $this->assertTrue(Dynadot::$last_list_sent);
    }

    public function testAOnePageListIsNotCheckedAgain()
    {
        $d = $this->dynadot();
        $d->responses = array($this->page(1, array('one-example.com', 'two-example.com'), 2, false));

        $this->assertSame(array(2, array('one-example.com', 'two-example.com')), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertCount(1, $d->urls);
    }

    public function testAnEmptyAccountListsNoDomains()
    {
        $d = $this->dynadot();
        $d->responses[] = ScriptedDynadot::fixture('list-empty');

        $this->assertSame(array(0, array()), $d->getDomainList(self::KEY, self::SECRET));
    }

    public function testAFailedLaterPageFailsTheWholeListWithoutApi3()
    {
        foreach (array(array(500, ScriptedDynadot::fixture('list-page-2')[1]), ScriptedDynadot::fixture('refused-wrong-secret'),
                       ScriptedDynadot::fixture('list-out-of-range')) as $failure) {

            $this->setUp();
            $d = $this->dynadot();
            $d->responses = array(ScriptedDynadot::fixture('list-page-1'), $failure);

            $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
            $this->assertCount(2, $d->urls);
            $this->assertNull(Dynadot::$last_list_fell_back);

        }
    }

    public function testARefusalOnTheFirstPageListsThroughApi3AndIsReported()
    {
        $d = $this->dynadot();
        $d->responses = array(ScriptedDynadot::fixture('refused-wrong-secret'), array(200, self::API3_LIST),
                              array(200, self::API3_LIST), array(200, self::API3_DETAILS));

        $this->assertSame(array(2, array('one-example.com', 'two-example.com')), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertSame(400, Dynadot::$last_list_fell_back);

        // a later list and details for the account use api3 straight away, and the list still reports the refusal
        Dynadot::$last_list_fell_back = null;
        $this->assertSame(2, $d->getDomainList(self::KEY, self::SECRET)[0]);
        $this->assertSame(400, Dynadot::$last_list_fell_back);
        $this->assertSame('2027-11-20', $d->getFullInfo(self::KEY, self::SECRET, 'two-servers-example.com')[0]);
        $this->assertCount(4, $d->urls);
        // every request went through the budgeted path, which sets a deadline (the unbudgeted api3 path has none)
        $this->assertCount(4, $d->deadlines);
        $this->assertNotNull($d->deadlines[3]);
        $this->assertStringContainsString('/restful/', $d->urls[0]);
        foreach (array(1, 2, 3) as $i) $this->assertStringContainsString('/api3.xml?', $d->urls[$i]);
    }

    public function testAFallbackListWithNoTimeLeftIsNotReportedAsFallingBack()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 30;
        $d->clock = $d->request_started + 6.5; // 23.5 seconds left, 17.625 after the margin
        $d->transfer_seconds = 17; // the refused first page leaves 0.625 seconds, too little for the api3 request
        $d->responses[] = ScriptedDynadot::fixture('refused-wrong-secret');

        $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertCount(1, $d->urls);
        $this->assertTrue(Dynadot::$last_list_sent);
        $this->assertNull(Dynadot::$last_list_fell_back);
    }

    public function testInconsistentPagesFailTheList()
    {
        $cases = array(
            'total changes' => array($this->page(1, array('a-example.com'), 2, true), $this->page(2, array('b-example.com', 'c-example.com'), 3, false)),
            'repeated page' => array($this->page(1, array('a-example.com'), 3, true), $this->page(2, array('a-example.com'), 3, true),
                $this->page(3, array('b-example.com', 'c-example.com'), 3, false)),
            'empty page that says more follow' => array($this->page(1, array(), 0, true)),
            'count differs from total' => array($this->page(1, array('a-example.com'), 2, false)),
            'wrong page number' => array($this->page(2, array('a-example.com'), 1, false)),
            'page one changed on the re-check' => array($this->page(1, array('a-example.com'), 2, true),
                $this->page(2, array('b-example.com'), 2, false), $this->page(1, array('c-example.com'), 2, true)),
            'no pagination' => array(array(200, '{"code":200,"data":{"domain_info_list":[]}}')),
            'list is an object' => array(array(200, '{"code":200,"data":{"domain_info_list":{},"pagination_result":{"page":1,"total":0,"has_next_page":"No"}}}')),
            'number for a name' => array(array(200, '{"code":200,"data":{"domain_info_list":[{"domain_name":12}],"pagination_result":{"page":1,"total":1,"has_next_page":"No"}}}')),
            'blank name' => array($this->page(1, array(''), 1, false)),
            'failure code with HTTP 200' => array(array(200, '{"code":400,"data":{"domain_info_list":[],"pagination_result":{"page":1,"total":0,"has_next_page":"No"}}}')),
        );

        foreach ($cases as $label => $responses) {

            $this->setUp();
            $d = $this->dynadot();
            $d->responses = $responses;
            $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET), $label);

        }
    }

    public function testAPrivacyChangeOnTheReCheckStillImports()
    {
        $d = $this->dynadot();
        $d->responses = array($this->page(1, array('a-example.com'), 2, true), $this->page(2, array('b-example.com'), 2, false),
                              $this->page(1, array('a-example.com'), 2, true, 200, 'Privacy Off'));

        $this->assertSame(array(2, array('a-example.com', 'b-example.com')), $d->getDomainList(self::KEY, self::SECRET));
    }

    public function testBothSpellingsOfADomainCountOnce()
    {
        $d = $this->dynadot();
        $d->responses = array($this->page(1, array('exämple.com'), 1, true), $this->page(2, array('xn--exmple-cua.com'), 1, false),
                              $this->page(1, array('exämple.com'), 1, true));

        $this->assertSame(array(1, array('exämple.com')), $d->getDomainList(self::KEY, self::SECRET));
    }

    public function testTheListStopsAtThePageLimit()
    {
        $d = $this->dynadot(get_class(new class extends ScriptedDynadot {
            const MAX_PAGES = 2;
        }));
        $d->responses = array($this->page(1, array('a-example.com'), 3, true), $this->page(2, array('b-example.com'), 3, true));
        $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertCount(2, $d->urls);

        // the last allowed page can still end the list
        $this->setUp();
        $d = $this->dynadot(get_class($d));
        $d->responses = array($this->page(1, array('a-example.com'), 2, true), $this->page(2, array('b-example.com'), 2, false),
                              $this->page(1, array('a-example.com'), 2, true));
        $this->assertSame(2, $d->getDomainList(self::KEY, self::SECRET)[0]);
    }

    public function testAListThatRunsOutOfTimeLogsTheHint()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 30;
        $d->clock = $d->request_started + 5; // 25 seconds left, 18.75 after the margin
        $d->responses = array($this->page(1, array('a-example.com'), 2, true), array(429, '{"code":429}'));
        $this->log->expects($this->once())->method('addEntry')->with('warning', $this->stringContains('see the Registrar Notes'));

        $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
    }

    public function testAListThatFailsWithTimeToSpareLogsNoHint()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 600;
        $d->clock = $d->request_started;
        $d->responses = array(array(429, '{"code":429}'));
        $this->log->expects($this->never())->method('addEntry');

        $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
    }

    public function testListPagingLeavesTheReserveFree()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 40;
        $d->clock = $d->request_started; // 30 seconds after the margin
        $d->transfer_seconds = 19.5;
        Dynadot::$list_reserve = 10;
        $d->responses = array($this->page(1, array('a-example.com'), 2, true));

        // page 1 uses 19.5 of the 20 seconds left for paging, so page 2 isn't sent
        $this->assertSame(array('', ''), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertCount(1, $d->urls);
        $this->assertEqualsWithDelta($d->request_started + 20, $d->deadlines[0], 0.0001);
    }

    public function testTheReserveNeverTakesMoreThanHalfOfThePagingTime()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 10;
        $d->clock = $d->request_started; // 7.5 seconds after the margin, less than the reserve
        Dynadot::$list_reserve = 10;
        $d->responses = array($this->page(1, array('a-example.com'), 1, false));

        $this->assertSame(array(1, array('a-example.com')), $d->getDomainList(self::KEY, self::SECRET));
        $this->assertEqualsWithDelta($d->request_started + 3.75, $d->deadlines[0], 0.0001);
    }

    public function testLaterListsKeepTheSamePagingEnd()
    {
        $d = $this->dynadot();
        $d->max_execution_time = 10;
        $d->clock = $d->request_started;
        $d->transfer_seconds = 1;
        Dynadot::$list_reserve = 10;
        $d->responses = array($this->page(1, array('a-example.com'), 1, false), $this->page(1, array('a-example.com'), 1, false));

        $d->getDomainList(self::KEY, self::SECRET);
        $d->getDomainList(self::KEY, 'another-secret');

        // the second list doesn't get half of what's left after the first: both stop at the same time
        $this->assertSame($d->deadlines[0], $d->deadlines[1]);
    }

} //@formatter:on
