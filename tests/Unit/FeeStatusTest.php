<?php
/**
 * /tests/Unit/FeeStatusTest.php
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
 * The fee shown on Edit Domain and Edit SSL Certificate, and the warning after a save leaves an item without a fee: the
 * amounts in the fee's own currency with Edit Fee, or what's missing with an Add Fee link filled in for the item, and
 * no links for read-only users. Names come from the database escaped, so they're shown once escaped, never twice.
 */
class FeeStatusTest extends TestCase
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

    private function domainRow($with_fee)
    {
        return (object) array('domain' => 'a&amp;b.com', 'tld' => 'co.uk', 'fee_id' => $with_fee ? '12' : '0',
            'registrar_id' => '3', 'registrar' => 'R &amp; Co', 'fee_row_id' => $with_fee ? '12' : null,
            'initial_fee' => $with_fee ? '1.5' : null, 'renewal_fee' => $with_fee ? '10' : null,
            'transfer_fee' => $with_fee ? '9' : null, 'privacy_fee' => $with_fee ? '0' : null,
            'misc_fee' => $with_fee ? '0.25' : null, 'currency' => $with_fee ? 'EUR' : null,
            'symbol' => $with_fee ? '&euro;' : null, 'symbol_order' => '0', 'symbol_space' => '0');
    }

    private function sslRow($with_fee)
    {
        return (object) array('name' => 'Shop <cert>', 'fee_id' => $with_fee ? '4' : '0', 'provider_id' => '6',
            'provider' => 'Prov', 'type_id' => '8', 'type' => 'Wildcard', 'fee_row_id' => $with_fee ? '4' : null,
            'initial_fee' => $with_fee ? '50' : null, 'renewal_fee' => $with_fee ? '60' : null,
            'misc_fee' => $with_fee ? '0' : null, 'currency' => $with_fee ? 'USD' : null, 'symbol' => '$',
            'symbol_order' => '0', 'symbol_space' => '0');
    }

    public function testAFeeIdWithNoFeeRowCountsAsNoFee()
    {
        $status = $this->feeStatus();
        $row = $this->domainRow(true);
        $this->assertTrue($status->hasFee($row));
        $row->fee_row_id = null; // the fee was deleted, so the join found nothing
        $this->assertFalse($status->hasFee($row));
        $this->assertFalse($status->hasFee($this->domainRow(false)));
        $this->assertFalse($status->hasFee(false));
    }

    public function testADomainsFeeShowsItsAmountsInItsOwnCurrencyWithEditFee()
    {
        $html = $this->feeStatus()->domainSection($this->domainRow(true), true);

        $this->assertStringContainsString('<dt>Renewal Fee</dt><dd><bdi dir="ltr">€10.00</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Misc Fee</dt><dd><bdi dir="ltr">€0.25</bdi></dd>', $html);
        $this->assertStringContainsString('<dt>Currency</dt><dd>EUR</dd>', $html);
        $this->assertStringContainsString('href="/dm/assets/edit/registrar-fee.php?rid=3&amp;fee_id=12"', $html);
        $this->assertStringContainsString('Edit Fee', $html);
    }

    public function testReadOnlyUsersGetNoFeeLinks()
    {
        $status = $this->feeStatus();
        foreach (array($status->domainSection($this->domainRow(true), false), $status->domainSection($this->domainRow(false), false),
                       $status->sslSection($this->sslRow(true), false), $status->sslSection($this->sslRow(false), false),
                       $status->domainWarning($this->domainRow(false), false), $status->sslWarning($this->sslRow(false), false)) as $html) {
            $this->assertStringNotContainsString('<a ', $html);
        }
    }

    public function testAMissingDomainFeeNamesTheTldAndRegistrarWithAFilledInAddFee()
    {
        $html = $this->feeStatus()->domainSection($this->domainRow(false), true);

        $this->assertStringContainsString('No fee for <bdi dir="ltr">.co.uk</bdi> at R &amp; Co', $html);
        $this->assertStringContainsString('hidden from the Domains list', $html);
        $this->assertStringContainsString('href="/dm/assets/add/registrar-fee.php?rid=3&amp;tld=co.uk"', $html);
        $this->assertStringContainsString('Add Fee', $html);
    }

    public function testAMissingSslFeeNamesTheTypeAndProviderWithAFilledInAddFee()
    {
        $html = $this->feeStatus()->sslSection($this->sslRow(false), true);

        $this->assertStringContainsString('No fee for <bdi dir="ltr">Wildcard</bdi> at Prov', $html);
        $this->assertStringContainsString('href="/dm/assets/add/ssl-provider-fee.php?sslpid=6&amp;type_id=8"', $html);
    }

    public function testAnSslFeeShowsItsThreeAmounts()
    {
        $html = $this->feeStatus()->sslSection($this->sslRow(true), true);

        $this->assertSame(4, substr_count($html, '<dt>'));
        $this->assertStringContainsString('<dt>Renewal Fee</dt><dd><bdi dir="ltr">$60.00</bdi></dd>', $html);
        $this->assertStringContainsString('href="/dm/assets/edit/ssl-provider-fee.php?sslpid=6&amp;fee_id=4"', $html);
    }

    public function testTheWarningsEscapeNamesOnceAndLinkToAddFee()
    {
        $status = $this->feeStatus();
        $domain = $status->domainWarning($this->domainRow(false), true);
        $ssl = $status->sslWarning($this->sslRow(false), true);

        $this->assertStringStartsWith('<bdi dir="ltr">a&amp;b.com</bdi> has no fee yet', $domain);
        $this->assertStringContainsString('a fee for <bdi dir="ltr">.co.uk</bdi> at R &amp; Co is added.', $domain);
        $this->assertStringEndsWith(' <a href="/dm/assets/add/registrar-fee.php?rid=3&amp;tld=co.uk">Add Fee</a>', $domain);
        $this->assertStringStartsWith('Shop &lt;cert&gt; has no fee yet', $ssl);
        $this->assertStringContainsString('hidden from the SSL Certificates list until a fee for Wildcard at Prov is added.', $ssl);
        $this->assertStringEndsWith(' <a href="/dm/assets/add/ssl-provider-fee.php?sslpid=6&amp;type_id=8">Add Fee</a>', $ssl);
    }

    public function testAnOddTldIsEncodedInTheAddFeeLink()
    {
        $row = $this->domainRow(false);
        $row->tld = 'a b&amp;c';
        $this->assertSame('/assets/add/registrar-fee.php?rid=3&tld=a%20b%26c', $this->feeStatus()->addDomainFeeUrl($row));
    }

} //@formatter:on
