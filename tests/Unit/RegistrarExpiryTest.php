<?php
/**
 * /tests/Unit/RegistrarExpiryTest.php
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

use DomainMOD\AboveCom;
use DomainMOD\DreamHost;
use DomainMOD\Enom;
use DomainMOD\Namecheap;
use DomainMOD\RegistrarResponse;
use DomainMOD\ResellerClub;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The expiry dates that Above.com, DreamHost, eNom, Namecheap, and ResellerClub give for the Domain Queue and domain
 * refreshes, read from hand-written values (no network). On 32-bit PHP (the official Docker image's armhf build), an
 * expiry past 2038 became 1970-01-01, or a TypeError for ResellerClub's seconds, so each date is checked on both
 * widths, and 64-bit PHP gives exactly the same results as before.
 */
class RegistrarExpiryTest extends TestCase
{
    protected $zone;

    protected function setUp(): void
    {
        $this->zone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    public function testDatesPast2038()
    {
        $this->assertSame('2040-06-01', Instance::withoutConstructor(AboveCom::class)->processExpiry('2040-06-01'));
        $this->assertSame('2040-06-01', Instance::withoutConstructor(DreamHost::class)->processExpiry('2040-06-01'));
        $this->assertSame('2040-06-01', Instance::withoutConstructor(Enom::class)->processExpiry('6/1/2040 2:30:00 PM'));
        $this->assertSame('2040-06-01', Instance::withoutConstructor(Namecheap::class)->processExpiry('06/01/2040'));
        $this->assertSame('2040-06-02', Instance::withoutConstructor(ResellerClub::class)->processExpiry('2222222222'));
    }

    // A Namecheap refresh reads its expiry through processExpiry(), from a hand-written domains.getinfo answer
    public function testANamecheapRefreshReadsItsExpiry()
    {
        $namecheap = new class extends Namecheap {
            public function __construct()
            {
            }

            public function apiCall($full_url)
            {
                if (strpos($full_url, 'namecheap.domains.getinfo') !== false) {
                    return '<?xml version="1.0" encoding="utf-8"?>
<ApiResponse Status="OK"><CommandResponse><DomainGetInfoResult>
  <DomainDetails><ExpiredDate>06/01/2040</ExpiredDate></DomainDetails>
  <Whoisguard Enabled="True"/>
  <DnsDetails><Nameserver>dns1.example.net</Nameserver><Nameserver>dns2.example.net</Nameserver></DnsDetails>
</DomainGetInfoResult></CommandResponse></ApiResponse>';
                }
                return '<?xml version="1.0" encoding="utf-8"?>
<ApiResponse Status="OK"><CommandResponse><DomainGetListResult>
  <Domain><Name>example.com</Name><AutoRenew>true</AutoRenew></Domain>
  <Domain><Name>example.org</Name><AutoRenew>false</AutoRenew></Domain>
</DomainGetListResult></CommandResponse></ApiResponse>';
            }
        };
        $this->assertSame(array('2040-06-01', array('dns1.example.net', 'dns2.example.net'), '1', '1'),
            $namecheap->getFullInfo('user', 'key', '192.0.2.1', 'example.com'));
    }

    public function testDatesBefore2038AreUnchanged()
    {
        $this->assertSame('2027-03-04', Instance::withoutConstructor(AboveCom::class)->processExpiry('2027-03-04'));
        $this->assertSame('2027-03-04', Instance::withoutConstructor(Enom::class)->processExpiry('3/4/2027 11:00:00 AM'));
        $this->assertSame('2027-03-04', Instance::withoutConstructor(Namecheap::class)->processExpiry('03/04/2027'));
        $this->assertSame('2027-03-04', Instance::withoutConstructor(ResellerClub::class)->processExpiry('1804118400'));
        $this->assertSame('2038-01-19', RegistrarResponse::expiryDateFromSeconds(2147483647));
    }

    // A date with no zone is read in PHP's time zone, then given in UTC, except for Namecheap, whose date stays local
    public function testTimeZones()
    {
        date_default_timezone_set('America/Vancouver');
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDate('06/01/2040 20:00'));
        $this->assertSame('2040-06-01', RegistrarResponse::expiryDate('06/01/2040 20:00', true));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDate('2040-06-01T23:30:00-08:00'));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDate('2040-06-01T23:30:00-08:00', true));
        $this->assertSame('2027-03-05', RegistrarResponse::expiryDate('03/04/2027 20:00'));
        $this->assertSame('2027-03-04', RegistrarResponse::expiryDate('03/04/2027 20:00', true));
    }

    // Seconds as a numeric string (with leading zeros, a sign, or spaces too), or as the float json_decode() gives on
    // 32-bit PHP
    public function testSecondsInEachForm()
    {
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds('0002222222222'));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds('+2222222222'));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds(' 2222222222'));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds('2222222222 '));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds('2222222222.0'));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds(json_decode('2222222222')));
        $this->assertSame('2040-06-02', RegistrarResponse::expiryDateFromSeconds(2222222222.0));
        $this->assertSame('1970-01-01', RegistrarResponse::expiryDateFromSeconds('000'));
    }

    // An unreadable or empty date still gives 1970-01-01, as it always has, on both widths. Spaces alone are read as
    // strtotime() reads them on each PHP version (now before PHP 8.4, unreadable since).
    public function testUnreadableDatesAreUnchanged()
    {
        $this->assertSame('1970-01-01', RegistrarResponse::expiryDate(''));
        $this->assertSame(gmdate('Y-m-d', strtotime('   ')), RegistrarResponse::expiryDate('   '));
        $this->assertSame('1970-01-01', RegistrarResponse::expiryDate('not a date'));
        $this->assertSame('1970-01-01', Instance::withoutConstructor(AboveCom::class)->processExpiry(''));
    }

} //@formatter:on
