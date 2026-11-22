<?php
/**
 * /tests/Unit/ListCellFocusTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Hosting Services and Change History lists open a cut-short cell in full only on keyboard focus. With
 * :focus-within, a mouse click focused the link first, the cell widened and the text moved, and the click missed the
 * link (found at 4.27.0's [K] on a cancelled service's name).
 */
class ListCellFocusTest extends TestCase
{

    public static function lists()
    {
        return array('hosting' => array('hosting-main'), 'history' => array('history-main'));
    }

    #[DataProvider('lists')]
    public function testCellOpensOnlyOnKeyboardFocus($list)
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/datatables/' . $list . '.css');
        $this->assertStringNotContainsString('td:focus-within', $css);
        $this->assertStringContainsString('#' . $list . ' > tbody > tr > td:has(:focus-visible) {', $css);
        // A cut-short cell with no link is itself the keyboard stop (js/server-list.js)
        $opens_up = " {\n    overflow: visible;\n    overflow-wrap: anywhere;\n    white-space: normal;\n}";
        $this->assertStringContainsString('#' . $list . ' > tbody > tr > td:focus-visible' . $opens_up, $css);
        $this->assertStringContainsString('#' . $list . ' > tbody > tr > td:has(:focus-visible)' . $opens_up, $css);
        $this->assertStringContainsString('#' . $list . ':not(.dataTable) > tbody > tr > td' . $opens_up, $css);
        $this->assertStringContainsString('#' . $list . ':not(.dataTable) > tbody > tr > td {', $css);
    }

} //@formatter:on
