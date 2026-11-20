<?php
/**
 * /tests/Unit/DomainRefreshStatusTest.php
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

use DomainMOD\DomainRefresh;
use DomainMOD\Rdap;
use PHPUnit\Framework\TestCase;

/*
 * DomainRefresh's lock status reads that don't need the database: which RDAP results can be saved, the status-only
 * lookup after a registrar API answer (including one that throws, as does its logger), and a WHOIS lookup recorded as
 * Unknown.
 */
class DomainRefreshStatusTest extends TestCase
{

    private function refresh($rdap, $log = null)
    {
        $refresh = (new \ReflectionClass(RefreshWithRdap::class))->newInstanceWithoutConstructor();
        $refresh->fake_rdap = $rdap;
        $refresh->log = $log ?? new QuietLog();
        $refresh->status_available = true;
        return $refresh;
    }

    private function call($object, $method, ...$args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        return $reflection->invoke($object, ...$args);
    }

    private function row()
    {
        return (object) array('id' => 3, 'domain' => 'example.com', 'tld' => 'com');
    }

    public function testOnlyACompletedReadCanBeSaved()
    {
        $refresh = $this->refresh(null);

        $this->assertSame(array('read' => 'found', 'codes' => array('ok', 'clientTransferProhibited'), 'started' => 'T'),
            $this->call($refresh, 'statusRead', array('status' => 'ok', 'status_read' => 'found',
                'statuses' => array('clientTransferProhibited', 'ok')), 'T'));
        $this->assertSame(array('read' => 'none', 'codes' => array(), 'started' => 'T'),
            $this->call($refresh, 'statusRead', array('status' => 'ok', 'status_read' => 'none', 'statuses' => array('ok')), 'T'));
        $this->assertNull($this->call($refresh, 'statusRead', array('status' => 'ok', 'status_read' => 'incomplete'), 'T'));
        $this->assertNull($this->call($refresh, 'statusRead', array('status' => 'mismatch', 'status_read' => 'found'), 'T'));
    }

    public function testTheStatusOnlyLookupAfterAnApiAnswer()
    {
        $rdap = new FakeRdap(array('status' => 'ok', 'status_read' => 'found', 'statuses' => array('clientTransferProhibited')));
        $status = $this->call($this->refresh($rdap), 'fetchStatusOnly', $this->row(), microtime(true) + 45);

        $this->assertSame(array(array('example.com', true)), $rdap->calls);
        $this->assertSame('found', $status['read']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $status['started']);
    }

    public function testATldWithoutRdapIsUnknown()
    {
        $status = $this->call($this->refresh(new FakeRdap(array('status' => 'no_server'))), 'fetchStatusOnly',
            $this->row(), microtime(true) + 45);

        $this->assertSame('whois', $status['read']);
        $this->assertSame(array(), $status['codes']);
    }

    public function testNoStatusOnlyLookupWithoutEnoughTime()
    {
        $rdap = new FakeRdap(array('status' => 'ok', 'status_read' => 'found', 'statuses' => array('ok')));

        $refresh = $this->refresh($rdap);
        $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 5));
        $this->assertSame(array(), $rdap->calls);
        // no lookup ran, so no failure is recorded
        $this->assertSame('', $refresh->failed_started);
    }

    public function testAFailedOrIncompleteStatusOnlyLookupCantBeSaved()
    {
        foreach (array(array('status' => 'rate_limited'), array('status' => 'error'), array('status' => 'mismatch'),
                     array('status' => 'ok', 'status_read' => 'incomplete')) as $result) {
            $refresh = $this->refresh(new FakeRdap($result));
            $this->assertNull($this->call($refresh, 'fetchStatusOnly', $this->row(), microtime(true) + 45));
            // the failed lookup's start time, for epp_status_failed_time
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $refresh->failed_started);
        }
    }

    public function testAStatusOnlyLookupThatThrowsIsLoggedAndSkipped()
    {
        $log = new QuietLog();
        $this->assertNull($this->call($this->refresh(new FakeRdap(new \RuntimeException('lookup')), $log), 'fetchStatusOnly',
            $this->row(), microtime(true) + 45));
        $this->assertSame(array('RuntimeException'), $log->errors);

        // server discovery (inside the lookup) throwing, and the logger throwing too
        $this->assertNull($this->call($this->refresh(new FakeRdap(new \TypeError('bootstrap')), new FailingLog()), 'fetchStatusOnly',
            $this->row(), microtime(true) + 45));
    }

    public function testPublicDetailsCarryTheStatus()
    {
        $refresh = $this->refresh(null);

        $rdap = $this->call($refresh, 'publicDetails', array('status' => 'ok', 'status_read' => 'found',
            'statuses' => array('serverHold'), 'expiry_date' => '2027-01-31', 'name_servers' => array()), 'rdap', 'T');
        $this->assertSame(array('read' => 'found', 'codes' => array('serverHold'), 'started' => 'T'), $rdap['status']);

        $whois = $this->call($refresh, 'publicDetails', array('status' => 'ok', 'expiry_date' => '2027-01-31',
            'name_servers' => array()), 'whois', 'T');
        $this->assertSame(array('read' => 'whois', 'codes' => array(), 'started' => 'T'), $whois['status']);

        $incomplete = $this->call($refresh, 'publicDetails', array('status' => 'ok', 'status_read' => 'incomplete',
            'expiry_date' => '2027-01-31', 'name_servers' => array()), 'rdap', 'T');
        $this->assertNull($incomplete['status']);
        $this->assertContains("The lock status couldn't be checked this time.", $incomplete['notes']);
        $this->assertSame('T', $refresh->failed_started);

        // before the database update, nothing is read
        $refresh->status_available = false;
        $this->assertNull($this->call($refresh, 'publicDetails', array('status' => 'ok', 'status_read' => 'found',
            'statuses' => array('ok'), 'expiry_date' => '2027-01-31', 'name_servers' => array()), 'rdap', 'T')['status']);
    }

} //@formatter:on

class RefreshWithRdap extends DomainRefresh
{
    public $fake_rdap;

    protected function rdap()
    {
        return $this->fake_rdap;
    }
}

class FakeRdap extends Rdap
{
    public $calls = array();
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function lookup($domain, $deadline = null, $status_only = false)
    {
        $this->calls[] = array($domain, $status_only);
        if ($this->result instanceof \Throwable) throw $this->result;
        return $this->result;
    }
}

class QuietLog
{
    public $errors = array();

    public function error($message, $extra = array())
    {
        $this->errors[] = $extra['Error'] ?? '';
    }
}

class FailingLog
{
    public function error($message, $extra = array())
    {
        throw new \RuntimeException('log');
    }
}
