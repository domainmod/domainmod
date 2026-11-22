<?php
/**
 * /tests/Unit/FeeStatusHostingTest.php
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

use DomainMOD\Currency;
use DomainMOD\FeeStatus;
use DomainMOD\Layout;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The fee shown on Edit Hosting Service, and the warning after a save leaves a hosting service without a fee: the
 * billing term, the Renewal Fee, the Misc Fee, the cost per term, and the yearly cost in the fee's own currency with
 * Edit Fee, or what's missing with an Add Fee link that carries the provider, type, and billing term, and no links for
 * read-only users. Names come from the database escaped, so they're shown once escaped, never twice.
 */
class FeeStatusHostingTest extends TestCase
{
    private function feeStatus()
    {
        // A plain currency format, so the test doesn't depend on the page language (Currency::format() checks it)
        $currency = new class extends Currency {
            public function __construct() {}
            public function format($amount, $symbol, $order, $space) { return $symbol . number_format((float) $amount, 2); }
        };
        return Instance::withoutConstructor(FeeStatus::class,
            array('currency' => $currency, 'layout' => new Layout(), 'web_root' => '/dm'));
    }

    private function hostingRow($with_fee, $term = '1')
    {
        return (object) array('name' => 'Client &amp; Co <vps>', 'fee_id' => $with_fee ? '7' : '0',
            'billing_term' => $term, 'provider_id' => '5', 'provider' => 'Host &amp; Co', 'type_id' => '9',
            'type' => 'Shared Pro', 'fee_row_id' => $with_fee ? '7' : null, 'initial_fee' => $with_fee ? '0' : null,
            'renewal_fee' => $with_fee ? '12.5' : null, 'misc_fee' => $with_fee ? '1' : null,
            'currency' => $with_fee ? 'CAD' : null, 'symbol' => '$', 'symbol_order' => '0', 'symbol_space' => '0');
    }

    public function testAHostingFeeShowsItsTermCostsAndCurrencyWithEditFee()
    {
        $html = $this->feeStatus()->hostingSection($this->hostingRow(true), true);

        $this->assertSame(6, substr_count($html, '<dt>'));
        $this->assertStringContainsString('<dt>Billing Term</dt><dd>1 Month</dd>', $html);
        $this->assertStringContainsString('<dt>Renewal Fee</dt><dd><bdi dir="ltr">$12.50</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Misc Fee (added to each renewal)</dt><dd><bdi dir="ltr">$1.00</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Cost Per Term</dt><dd><bdi dir="ltr">$13.50</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Yearly Cost</dt><dd><bdi dir="ltr">$162.00</bdi></dd>', $html);
        // The Initial Fee never counts toward a service's cost, so the card leaves it out
        $this->assertStringNotContainsString('Initial Fee', $html);
        $this->assertStringContainsString('<dt>Currency</dt><dd>CAD</dd>', $html);
        $this->assertStringContainsString('href="/dm/assets/edit/hosting-fee.php?hosting_id=5&amp;fee_id=7"', $html);
    }

    // A term that isn't an allowed one has no yearly figure, as the list leaves it out
    public function testAnInvalidTermHasNoYearlyCost()
    {
        $html = $this->feeStatus()->hostingSection($this->hostingRow(true, '5'), true);

        $this->assertStringContainsString('<dt>Cost Per Term</dt>', $html);
        $this->assertStringNotContainsString('Yearly Cost', $html);
    }

    public function testAMissingHostingFeeNamesTheTypeTermAndProviderWithAFilledInAddFee()
    {
        $html = $this->feeStatus()->hostingSection($this->hostingRow(false, '12'), true);

        $this->assertStringContainsString('No fee for Shared Pro (12 Months) at Host &amp; Co', $html);
        $this->assertStringContainsString('No fee matches this provider, type, and billing term yet. The service is saved without a cost until you add one.', $html);
        $this->assertStringNotContainsString('hidden from', $html);
        $this->assertStringContainsString('href="/dm/assets/add/hosting-fee.php?hosting_id=5&amp;type_id=9&amp;billing_term=12"', $html);
    }

    public function testTheHostingWarningEscapesNamesOnceAndLinksToAddFee()
    {
        $html = $this->feeStatus()->hostingWarning($this->hostingRow(false, '3'), true);

        $this->assertStringStartsWith('Client &amp; Co &lt;vps&gt; has no fee yet', $html);
        $this->assertStringContainsString("has no fee yet, so it's saved without a cost until a fee for Shared Pro (3 Months) at Host &amp; Co is added.", $html);
        $this->assertStringEndsWith(' <a href="/dm/assets/add/hosting-fee.php?hosting_id=5&amp;type_id=9&amp;billing_term=3">Add Fee</a>', $html);
    }

    public function testReadOnlyUsersGetNoHostingFeeLinks()
    {
        $status = $this->feeStatus();
        foreach (array($status->hostingSection($this->hostingRow(true), false), $status->hostingSection($this->hostingRow(false), false),
                       $status->hostingWarning($this->hostingRow(false), false)) as $html) {
            $this->assertStringNotContainsString('<a ', $html);
        }
    }

    // The list builds the same Add Fee link without a FeeStatus, from a row's provider_id, type_id, and billing_term
    public function testTheAddHostingFeeLinkIsBuiltStatically()
    {
        $this->assertSame('/assets/add/hosting-fee.php?hosting_id=5&type_id=9&billing_term=12',
            FeeStatus::addHostingFeeUrl($this->hostingRow(false, '12')));
    }

    public function testAHostingFeeIdWithNoFeeRowCountsAsNoFee()
    {
        $status = $this->feeStatus();
        $row = $this->hostingRow(true);
        $this->assertTrue($status->hasFee($row));
        $row->fee_row_id = null;
        $this->assertFalse($status->hasFee($row));
    }

} //@formatter:on
