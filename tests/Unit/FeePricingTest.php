<?php
/**
 * /tests/Unit/FeePricingTest.php
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
 * The fee comparisons behind TLD Pricing and SSL Pricing: Reporting::sslOverview() and sslRow() rank each certificate
 * type's providers, and tldRow() still ranks each TLD's registrars the same way now that both share rankFees().
 */
class FeePricingTest extends TestCase
{
    private function sslFee($fee_id, $provider_id, $provider, $type_id, $type, $renewal, $initial, $has_rate = 1)
    {
        return (object) array('fee_id' => $fee_id, 'ssl_provider_id' => $provider_id, 'ssl_provider_name' => $provider,
            'type_id' => $type_id, 'type' => $type, 'has_rate' => $has_rate,
            'price' => array('renewal' => $renewal, 'initial' => $initial));
    }

    private function certs($type_id, $provider_id, $certs)
    {
        return (object) array('type_id' => $type_id, 'ssl_provider_id' => $provider_id, 'certs' => $certs);
    }

    public function testSslOverviewRanksEachTypeAndSortsByCertificates()
    {
        $prices = array(
            'fees' => array(
                $this->sslFee(1, 10, 'Beta SSL', 1, 'Standard', 20.0, 25.0),
                $this->sslFee(2, 11, 'Alpha SSL', 1, 'Standard', 15.0, 30.0),
                $this->sslFee(3, 10, 'Beta SSL', 2, 'Wildcard', 90.0, 90.0),
                $this->sslFee(4, 12, 'No Rate SSL', 2, 'Wildcard', 1.0, 1.0, 0),
                $this->sslFee(5, 11, 'Alpha SSL', 3, 'EV', 200.0, 200.0),
            ),
            'certs' => array($this->certs(2, 10, 3), $this->certs(1, 10, 1), $this->certs(1, 11, 1)),
        );

        $overview = (new Reporting())->sslOverview($prices);

        // Wildcard has the most certificates, then Standard, and EV has none
        $this->assertSame(array('Wildcard', 'Standard', 'EV'), array_column($overview['rows'], 'type'));
        $this->assertSame(array(3, 2, 0), array_column($overview['rows'], 'certs'));
        $this->assertSame(1, $overview['left_out']);

        $standard = $overview['rows'][1];
        $this->assertSame(1, $standard['type_id']);
        $this->assertSame(array('Alpha SSL', 'Beta SSL'), array_map(function ($fee) { return $fee->ssl_provider_name; }, $standard['fees']));
        $this->assertSame(array(0), $standard['cheapest']);
        $this->assertSame(array(1), $standard['most_expensive']);
        $this->assertEquals(5.0, $standard['spread']);
        $this->assertSame(2, $standard['providers']);

        // The fee with no rate is left out, so Wildcard has one provider and no spread
        $this->assertSame(1, $overview['rows'][0]['providers']);
        $this->assertEquals(0.0, $overview['rows'][0]['spread']);
    }

    public function testSslRowRanksByThePriceTypeAndTiesShareToTheCent()
    {
        $fees = array(
            $this->sslFee(1, 10, 'Gamma SSL', 1, 'Standard', 10.004, 50.0),
            $this->sslFee(2, 11, 'Alpha SSL', 1, 'Standard', 10.0, 40.0),
            $this->sslFee(3, 12, 'Beta SSL', 1, 'Standard', 30.0, 40.001),
        );
        $reporting = new Reporting();

        $renewal = $reporting->sslRow(1, $fees, 4, 'renewal');
        $this->assertSame(array('Alpha SSL', 'Gamma SSL', 'Beta SSL'), array_map(function ($fee) { return $fee->ssl_provider_name; }, $renewal['fees']));
        $this->assertSame(array(0, 1), $renewal['cheapest']);
        $this->assertSame(array(2), $renewal['most_expensive']);
        $this->assertSame(4, $renewal['certs']);

        $initial = $reporting->sslRow(1, $fees, 4, 'initial');
        $this->assertSame(array('Alpha SSL', 'Beta SSL', 'Gamma SSL'), array_map(function ($fee) { return $fee->ssl_provider_name; }, $initial['fees']));
        $this->assertSame(array(0, 1), $initial['cheapest']);
        $this->assertEquals(10.0, $initial['spread']);
        $this->assertSame(3, $initial['providers']);
    }

    // Two fees for one provider (on different accounts) count as one provider
    public function testSslRowCountsProvidersNotFees()
    {
        $fees = array(
            $this->sslFee(1, 10, 'Alpha SSL', 1, 'Standard', 10.0, 10.0),
            $this->sslFee(2, 10, 'Alpha SSL', 1, 'Standard', 12.0, 12.0),
        );

        $this->assertSame(1, (new Reporting())->sslRow(1, $fees, 0, 'renewal')['providers']);
    }

    public function testTldRowStillRanksRegistrars()
    {
        $fee = function ($registrar_id, $name, $renewal) {
            return (object) array('registrar_id' => $registrar_id, 'registrar_name' => $name,
                'price' => array('renewal' => $renewal, 'initial' => 0.0, 'transfer' => 0.0));
        };

        $row = (new Reporting())->tldRow('com', array($fee(2, 'Zeta', 12.0), $fee(1, 'Acme', 12.0), $fee(3, 'Mid', 15.5)), 7, 'renewal');

        $this->assertSame('com', $row['tld']);
        $this->assertSame(array('Acme', 'Zeta', 'Mid'), array_map(function ($fee) { return $fee->registrar_name; }, $row['fees']));
        $this->assertSame(array(0, 1), $row['cheapest']);
        $this->assertSame(array(2), $row['most_expensive']);
        $this->assertEquals(12.0, $row['low']);
        $this->assertEquals(15.5, $row['high']);
        $this->assertEquals(3.5, $row['spread']);
        $this->assertSame(3, $row['registrars']);
        $this->assertSame(7, $row['domains']);
    }
}
