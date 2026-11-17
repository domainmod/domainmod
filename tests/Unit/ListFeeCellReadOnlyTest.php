<?php
/**
 * /tests/Unit/ListFeeCellReadOnlyTest.php
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
use DomainMOD\DomainListColumns;
use DomainMOD\Layout;
use DomainMOD\SslListColumns;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Domains and SSL Certificates lists' Fee cells (and the Domains list's TLD cell) link to the item's fee, except for
 * read-only users, who can't edit fees, so they get the same text without the link (as on the fee reports).
 */
class ListFeeCellReadOnlyTest extends TestCase
{
    private function cell($class, $key, $read_only)
    {
        $currency = new class extends Currency {
            public function __construct() {}
            public function format($amount, $symbol, $order, $space) { return $symbol . number_format((float) $amount, 2); }
        };
        $settings = array('s_default_currency_symbol' => '$', 's_default_currency_symbol_order' => '0',
            's_default_currency_symbol_space' => '0', 's_read_only' => $read_only);
        $columns = Instance::withoutConstructor($class, array('settings' => $settings, 'layout' => new Layout(),
            'currency' => $currency));
        $row = (object) array('total_cost' => '10', 'conversion' => '1', 'r_id' => 3, 'f_id' => 12, 'tld' => 'com',
            'sslp_id' => 6);
        $method = new \ReflectionMethod($class, 'cell');
        return $method->invoke($columns, array('key' => $key), $row, '2026-11-17');
    }

    public function testEditorsGetLinksToTheFee()
    {
        $this->assertSame('<a href="../assets/edit/registrar-fee.php?rid=3&fee_id=12">$10.00</a>', $this->cell(DomainListColumns::class, 'fee', '0'));
        $this->assertSame('<a href="../assets/edit/registrar-fee.php?rid=3&fee_id=12"><bdi dir="ltr">.com</bdi></a>', $this->cell(DomainListColumns::class, 'tld', '0'));
        $this->assertSame('<a href="../assets/edit/ssl-provider-fee.php?sslpid=6&fee_id=12">$10.00</a>', $this->cell(SslListColumns::class, 'fee', ''));
    }

    public function testReadOnlyUsersGetPlainText()
    {
        $this->assertSame('$10.00', $this->cell(DomainListColumns::class, 'fee', '1'));
        $this->assertSame('<bdi dir="ltr">.com</bdi>', $this->cell(DomainListColumns::class, 'tld', '1'));
        $this->assertSame('$10.00', $this->cell(SslListColumns::class, 'fee', '1'));
    }

    public function testTheReadOnlyFlagIsCopiedFromTheSession()
    {
        $this->assertSame('1', DomainListColumns::sessionCopy(array('s_read_only' => '1'))['s_read_only']);
        $this->assertSame('1', SslListColumns::sessionCopy(array('s_read_only' => '1'))['s_read_only']);
    }

} //@formatter:on
