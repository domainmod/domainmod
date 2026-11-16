<?php
/**
 * /tests/Unit/DynadotTest.php
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
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * Dynadot::parseFullInfo() and processDns(), the parsing behind a Dynadot domain refresh, read from hand-written
 * domain_info responses modelled on Dynadot's documented XML (no network). Missing or unexpected fields count as no
 * value without PHP warnings, which PHPUnit fails the test on. The constructor's Log connects to the database, so the
 * object is built without it.
 */
class DynadotTest extends TestCase
{
    const EXPIRATION = '1361430589062';
    const EXPIRY_DATE = '2013-02-21';
    const PLACEHOLDERS = array('no.dns-servers.1', 'no.dns-servers.2');

    protected function dynadot()
    {
        return Instance::withoutConstructor(Dynadot::class);
    }

    // A domain_info response with the given NameServerSettings, Privacy, and RenewOption elements (null leaves one out)
    protected function response($name_server_settings, $privacy = '<Privacy>full</Privacy>',
                                $renew_option = '<RenewOption>auto-renew</RenewOption>', $status = 'success',
                                $expiration = '<Expiration>' . self::EXPIRATION . '</Expiration>')
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<DomainInfoResponse>
  <DomainInfoHeader>
    <ResponseCode>0</ResponseCode>
    <Status>' . $status . '</Status>
  </DomainInfoHeader>
  <DomainInfoContent>
    <Domain>
      <Name>example.com</Name>
      ' . $expiration . '
      <Registration>1234567890123</Registration>
      ' . $name_server_settings . '
      <Locked>yes</Locked>
      ' . $privacy . '
      ' . $renew_option . '
      <Note/>
      <Status>active</Status>
    </Domain>
  </DomainInfoContent>
</DomainInfoResponse>';
    }

    protected function nameServers(...$names)
    {
        $servers = '';
        foreach ($names as $i => $name) {
            $servers .= '<NameServer><ServerId>' . $i . '</ServerId><ServerName>' . $name . '</ServerName></NameServer>';
        }
        return '<NameServerSettings><Type>Name Servers</Type><NameServers>' . $servers . '</NameServers></NameServerSettings>';
    }

    public function testANormalResponseWithTwoNameServers()
    {
        $info = $this->dynadot()->parseFullInfo($this->response($this->nameServers('ns1.example.net', 'ns2.example.net')));

        $this->assertSame(array(self::EXPIRY_DATE, array('ns1.example.net', 'ns2.example.net'), '1', '1'), $info);
    }

    public function testOneNameServerIsReadFromItsSingleRecord()
    {
        $info = $this->dynadot()->parseFullInfo($this->response($this->nameServers('ns1.example.net')));

        $this->assertSame(array('ns1.example.net'), $info[1]);
    }

    public function testForwardingWithNoNameServersGivesThePlaceholders()
    {
        $settings = '<NameServerSettings><Type>Forwarding</Type><ForwardTo>https://example.org</ForwardTo></NameServerSettings>';

        $info = $this->dynadot()->parseFullInfo($this->response($settings));

        $this->assertSame(array(self::EXPIRY_DATE, self::PLACEHOLDERS, '1', '1'), $info);
    }

    public function testParkingAsInDynadotsExampleGivesThePlaceholders()
    {
        $settings = '<NameServerSettings><Type>Dynadot Parking</Type><WithAds>Yes</WithAds></NameServerSettings>';

        $info = $this->dynadot()->parseFullInfo($this->response($settings, '<Privacy>none</Privacy>',
            '<RenewOption>no renew option</RenewOption>'));

        $this->assertSame(array(self::EXPIRY_DATE, self::PLACEHOLDERS, '0', '0'), $info);
    }

    public function testNoNameServerSettingsAtAllGivesThePlaceholders()
    {
        $info = $this->dynadot()->parseFullInfo($this->response(''));

        $this->assertSame(self::PLACEHOLDERS, $info[1]);
    }

    public function testMissingPrivacyAndRenewOptionReadAsOff()
    {
        $info = $this->dynadot()->parseFullInfo($this->response($this->nameServers('ns1.example.net'), '', ''));

        $this->assertSame(array(self::EXPIRY_DATE, array('ns1.example.net'), '0', '0'), $info);
    }

    public function testPartialPrivacyCountsAsOn()
    {
        $info = $this->dynadot()->parseFullInfo($this->response($this->nameServers('ns1.example.net'),
            '<Privacy>partial</Privacy>', '<RenewOption>do not renew</RenewOption>'));

        $this->assertSame(array('1', '0'), array($info[2], $info[3]));
    }

    public function testAMissingOrUnreadableExpiryIsNoDate()
    {
        $dynadot = $this->dynadot();

        $this->assertSame('', $dynadot->parseFullInfo($this->response($this->nameServers('ns1.example.net'),
            expiration: ''))[0]);
        $this->assertSame('', $dynadot->parseFullInfo($this->response($this->nameServers('ns1.example.net'),
            expiration: '<Expiration>soon</Expiration>'))[0]);
        $this->assertSame('', $dynadot->parseFullInfo($this->response($this->nameServers('ns1.example.net'),
            expiration: '<Expiration/>'))[0]);
    }

    public function testAFailedStatusGivesNull()
    {
        $this->assertNull($this->dynadot()->parseFullInfo($this->response($this->nameServers('ns1.example.net'),
            status: 'error')));
    }

    public function testAnEmptyOrNonXmlBodyGivesNull()
    {
        $dynadot = $this->dynadot();

        $this->assertNull($dynadot->parseFullInfo(''));
        $this->assertNull($dynadot->parseFullInfo(false));
        $this->assertNull($dynadot->parseFullInfo('<html><body>Bad Gateway</body></html>'));
        $this->assertNull($dynadot->parseFullInfo('not xml'));
    }

    public function testProcessDnsWithoutServersGivesThePlaceholders()
    {
        $dynadot = $this->dynadot();

        $this->assertSame(self::PLACEHOLDERS, $dynadot->processDns(null));
        $this->assertSame(self::PLACEHOLDERS, $dynadot->processDns(array()));
        $this->assertSame(self::PLACEHOLDERS, $dynadot->processDns(array(array('ServerId' => '0', 'ServerName' => array()))));
        $this->assertSame(self::PLACEHOLDERS, $dynadot->processDns('text'));
    }

    public function testProcessDnsReadsAListAndASingleRecord()
    {
        $dynadot = $this->dynadot();

        $this->assertSame(array('ns1.example.net', 'ns2.example.net'), $dynadot->processDns(array(
            array('ServerId' => '0', 'ServerName' => 'ns1.example.net'),
            array('ServerId' => '1', 'ServerName' => 'ns2.example.net'))));
        $this->assertSame(array('ns1.example.net'), $dynadot->processDns(
            array('ServerId' => '0', 'ServerName' => 'ns1.example.net')));
    }

} //@formatter:on
