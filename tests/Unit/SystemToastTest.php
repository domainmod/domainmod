<?php
/**
 * /tests/Unit/SystemToastTest.php
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

use DomainMOD\Layout;
use DomainMOD\System;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Which successes become a toast (System::showMessageSuccess()): one short part, which can hold a link, such as the new
// domain's edit link in "Domain example.com added". A longer or multi-part success stays in the page.
class SystemToastTest extends TestCase
{
    public static function messages()
    {
        return array(
            'a short success' => array('Domain example.com added', true),
            'a short success with a link' => array('Domain <a href="edit.php?did=7">example.com</a> added<BR>', true),
            'two parts' => array('Domain example.com added<BR>Domain example.net added<BR>', false),
            'over 100 characters' => array(str_repeat('a', 101), false),
            'empty' => array('<BR>', false),
        );
    }

    #[DataProvider('messages')]
    public function testOnlyAShortSuccessIsAToast($message, $toast)
    {
        $system = Instance::withoutConstructor(System::class);
        $html = $system->showMessageSuccess($message);
        $this->assertSame($toast, str_contains($html, 'data-domainmod-toast'));
    }

    public static function lengths()
    {
        return array(
            '100 visible characters' => array(100, true),
            '101 visible characters' => array(101, false),
        );
    }

    // The add pages' message, with the name escaped and isolated in a <bdi>: only the visible text counts, so an
    // ampersand, a quote, or an accented letter in the name counts once, not as its entity
    #[DataProvider('lengths')]
    public function testTheVisibleTextIsCounted($visible, $toast)
    {
        $layout = Instance::withoutConstructor(Layout::class);
        $name = 'A & "B" café ' . str_repeat('x', $visible - 22 - 13);
        $message = 'SSL Certificate <a href="edit.php?sslcid=7">' . $layout->ltr($name) . '</a> added<BR>';
        $system = Instance::withoutConstructor(System::class);
        $this->assertSame($toast, str_contains($system->showMessageSuccess($message), 'data-domainmod-toast'));
    }

    public function testTheLinkIsKept()
    {
        $system = Instance::withoutConstructor(System::class);
        $this->assertStringContainsString('<a href="edit.php?did=7">example.com</a>',
            $system->showMessageSuccess('Domain <a href="edit.php?did=7">example.com</a> added<BR>'));
    }

} //@formatter:on
