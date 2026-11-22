<?php
/**
 * /tests/Unit/AtRiskStatusTest.php
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
 * Reporting::atRiskDomains() with the registry status columns from renewalDomainsWithStatus(): a domain in the next 30
 * days without a transfer lock, or that the registry has on hold or is deleting, is at risk, while one further out
 * isn't, and rows without the columns (before the database update) work as before. Privacy off isn't a reason, since
 * At Risk means might not renew (Greg's choice, 2026-11-21). The Dashboard's At Risk tile counts the same function
 * over its wider rows, so these also keep the tile and the report in step.
 */
class AtRiskStatusTest extends TestCase
{
    const TODAY = '2026-11-20';
    const RISK_END = '2026-12-20';

    private function row($expiry_date, array $values = array())
    {
        return (object) ($values + array('domain' => $expiry_date, 'expiry_date' => $expiry_date, 'autorenew' => '1',
            'privacy' => '1', 'transfer_lock' => 2, 'registry_problem' => 0));
    }

    private function listed(array $rows)
    {
        return array_column((new Reporting())->atRiskDomains($rows, self::TODAY, self::RISK_END), 'domain');
    }

    public function testANewReasonInTheWindowIsAtRisk()
    {
        $this->assertSame(array('2026-12-01'), $this->listed(array($this->row('2026-12-01', array('transfer_lock' => 1)))));
        $this->assertSame(array('2026-12-02'), $this->listed(array($this->row('2026-12-02', array('registry_problem' => '1')))));
        $this->assertSame(array(), $this->listed(array($this->row('2026-12-03'))));
        $this->assertSame(array(), $this->listed(array($this->row('2026-12-04', array('transfer_lock' => 0)))));
    }

    public function testDomainsFurtherOutArentAtRisk()
    {
        // on hold or unlocked, but expiring in 90 days: in neither the report nor the Dashboard's count
        $this->assertSame(array(), $this->listed(array($this->row('2027-02-18', array('registry_problem' => 1)),
            $this->row('2027-02-19', array('transfer_lock' => 1)))));
    }

    public function testAnOverdueDomainIsListedOnce()
    {
        $this->assertSame(array('2026-11-01'), $this->listed(array($this->row('2026-11-01', array('transfer_lock' => 1,
            'registry_problem' => 1)))));
    }

    // Privacy off alone doesn't put a domain at risk, in the window or with rows from before the database update, while
    // an overdue domain is listed whatever its privacy
    public function testPrivacyOffIsntAReason()
    {
        $this->assertSame(array(), $this->listed(array($this->row('2026-12-01', array('privacy' => '0')))));
        $this->assertSame(array('2026-12-02'), $this->listed(array($this->row('2026-12-02', array('privacy' => '0', 'autorenew' => '0')))));
        $this->assertSame(array('2026-11-01'), $this->listed(array($this->row('2026-11-01', array('privacy' => '0')))));
        $plain = (object) array('domain' => '2026-12-03', 'expiry_date' => '2026-12-03', 'autorenew' => '1', 'privacy' => '0');
        $this->assertSame(array(), $this->listed(array($plain)));
    }

    public function testRowsWithoutTheColumnsWorkAsBefore()
    {
        $plain = function ($expiry_date, $autorenew) {
            return (object) array('domain' => $expiry_date, 'expiry_date' => $expiry_date, 'autorenew' => $autorenew, 'privacy' => '1');
        };

        $this->assertSame(array('2026-12-01'), $this->listed(array($plain('2026-12-01', '0'), $plain('2026-12-02', '1'))));
    }

} //@formatter:on
