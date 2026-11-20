<?php
/**
 * /tests/Unit/SslAtRiskTest.php
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

use DomainMOD\Reporting;
use PHPUnit\Framework\TestCase;

/*
 * The At-Risk SSL Certificates report's reasons (Reporting::atRiskCerts()), from rows shaped like renewalCerts()'s. A
 * certificate doesn't need a domain, so one with no domain (a NULL joined domain) has no domain reason, while one with
 * an inactive, expired, or earlier-expiring domain still has it.
 */
class SslAtRiskTest extends TestCase
{
    const TODAY = '2026-11-20';
    const RISK_END = '2026-12-20';

    public function testACertificateWithNoDomainHasNoDomainReason()
    {
        $rows = (new Reporting())->atRiskCerts(array($this->row(null, null, null)), self::TODAY, self::RISK_END);

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]->domain_problem);
        $this->assertSame(array('expiring'), $rows[0]->reasons);
    }

    public function testAnOverdueCertificateWithNoDomainIsStillListed()
    {
        $row = $this->row(null, null, null);
        $row->expiry_date = '2026-11-01';
        $rows = (new Reporting())->atRiskCerts(array($row), self::TODAY, self::RISK_END);

        $this->assertSame(array('overdue'), $rows[0]->reasons);
    }

    public function testADomainProblemStillCounts()
    {
        $cases = array(
            'inactive' => $this->row(3, '0', '2027-06-01'),
            'expired' => $this->row(3, '1', '2026-11-10'),
            'first' => $this->row(3, '1', '2026-11-25'),
        );
        foreach ($cases as $problem => $row) {
            $rows = (new Reporting())->atRiskCerts(array($row), self::TODAY, self::RISK_END);
            $this->assertSame($problem, $rows[0]->domain_problem, $problem);
            $this->assertSame(array('expiring', 'domain'), $rows[0]->reasons, $problem);
        }

        // A healthy domain gives no domain reason
        $rows = (new Reporting())->atRiskCerts(array($this->row(3, '1', '2027-06-01')), self::TODAY, self::RISK_END);
        $this->assertSame('', $rows[0]->domain_problem);
        $this->assertSame(array('expiring'), $rows[0]->reasons);
    }

    // A certificate expiring on 2026-12-01, with its joined domain's id, status, and expiry date (all NULL for none)
    private function row($domain_id, $domain_active, $domain_expiry_date)
    {
        return (object) array('id' => 1, 'name' => 'shop.example.com', 'expiry_date' => '2026-12-01',
            'domain_id' => $domain_id, 'domain' => $domain_id === null ? null : 'example.com',
            'domain_active' => $domain_active, 'domain_expiry_date' => $domain_expiry_date, 'has_fee' => 1,
            'has_rate' => 1);
    }

} //@formatter:on
