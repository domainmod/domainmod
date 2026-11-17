<?php
/**
 * /tests/Unit/FormSavedPasswordTest.php
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
 * Form::showSavedPassword(): a stored password (the SMTP password, a registrar account's password) is never put in the
 * page. The field is an empty password field that browsers don't fill in with a saved sign-in, and the note about
 * leaving it empty only shows when a password is stored.
 */
class FormSavedPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        Form::$field_errors = array();
        Form::$error_focus_used = false;
        Form::$field_errors_shown = array();
    }

    public function testTheFieldIsAnEmptyPasswordField()
    {
        $html = (new Form())->showSavedPassword('new_smtp_password', 'SMTP Password (255)', true, '255');

        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('name="new_smtp_password"', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('maxlength="255"', $html);
    }

    public function testTheKeepNoteShowsWhenAPasswordIsSaved()
    {
        $html = (new Form())->showSavedPassword('new_password', 'Password (255)', true, '255');

        $this->assertStringContainsString('Leave this empty to keep the saved password.', $html);
    }

    public function testThereIsNoKeepNoteWithoutASavedPassword()
    {
        $html = (new Form())->showSavedPassword('new_password', 'Password (255)', false, '255');

        $this->assertStringNotContainsString('Leave this empty to keep the saved password.', $html);
        $this->assertStringContainsString('value=""', $html);
    }

} //@formatter:on
