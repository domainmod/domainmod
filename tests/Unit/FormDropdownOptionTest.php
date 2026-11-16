<?php
/**
 * /tests/Unit/FormDropdownOptionTest.php
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

use DomainMOD\Form;
use PHPUnit\Framework\TestCase;

/*
 * Form::showDropdownOption(): each option takes its direction from its own text, so a long left-to-right name in a
 * right-to-left language is cut at its end rather than its start, and the matching option is still selected.
 */
class FormDropdownOptionTest extends TestCase
{
    public function testAnOptionTakesItsDirectionFromItsText()
    {
        $html = (new Form())->showDropdownOption('7', 'Namecheap, Owner (username)', '3');

        $this->assertSame('<option value="7" dir="auto">Namecheap, Owner (username)</option>', trim($html));
    }

    public function testTheMatchingOptionIsSelected()
    {
        $html = (new Form())->showDropdownOption('3', 'Porkbun', '3');

        $this->assertSame('<option value="3" dir="auto" selected>Porkbun</option>', trim($html));
    }

} //@formatter:on
