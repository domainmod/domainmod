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
 * Form::showSavedPassword(): a stored secret (the SMTP password, a registrar account's password, API key, and API secret,
 * an SSL provider account's password, a DW server's API token and remote access key) is never put in the page. The
 * field is an empty password field that browsers don't fill in with a saved sign-in, and the note about leaving it
 * empty and the box that removes the saved value only show when a value is stored. Form::savedSecretToStore() decides
 * what saving the field does.
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

        $this->assertStringContainsString('Leave this empty to keep the saved value.', $html);
    }

    public function testThereIsNoKeepNoteWithoutASavedPassword()
    {
        $html = (new Form())->showSavedPassword('new_password', 'Password (255)', false, '255');

        $this->assertStringNotContainsString('Leave this empty to keep the saved value.', $html);
        $this->assertStringContainsString('value=""', $html);
    }

    public function testTheRemoveBoxShowsUnderTheFieldWhenAValueIsSaved()
    {
        $html = (new Form())->showSavedPassword('new_api_key', 'API Key', true, '255');

        $this->assertStringContainsString('type="checkbox" name="new_api_key_remove" id="domainmod-field-new_api_key_remove" value="1">', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="domainmod-field-new_api_key_remove">Remove the saved value</label>', $html);
        // inside the field's own grid item, after the input
        $this->assertGreaterThan(strpos($html, 'name="new_api_key"'), strpos($html, 'new_api_key_remove'));
        $this->assertSame(1, substr_count($html, '<div class="mb-3">'));
        $this->assertStringEndsWith('</div>', trim($html));
    }

    public function testThereIsNoRemoveBoxWithoutASavedValue()
    {
        $html = (new Form())->showSavedPassword('new_api_key', 'API Key', false, '255');

        $this->assertStringNotContainsString('_remove', $html);
        $this->assertStringNotContainsString('Remove the saved value', $html);
    }

    public function testTheRemoveBoxIsTickedAgainAfterAFailedSubmit()
    {
        $unticked = (new Form())->showSavedPassword('new_password', 'Password (255)', true, '255', false);
        $ticked = (new Form())->showSavedPassword('new_password', 'Password (255)', true, '255', true);

        $this->assertStringNotContainsString(' checked', $unticked);
        $this->assertStringContainsString('value="1" checked>', $ticked);
    }

    public function testTheFieldsOwnHelpComesBeforeTheKeepNote()
    {
        $saved = (new Form())->showSavedPassword('new_api_token', 'API Token (255)', true, '255', false, 'Enter the API token.');
        $not_saved = (new Form())->showSavedPassword('new_api_token', 'API Token (255)', false, '255', false, 'Enter the API token.');

        $this->assertStringContainsString('Enter the API token. Leave this empty to keep the saved value.', $saved);
        $this->assertStringContainsString('Enter the API token.', $not_saved);
        $this->assertStringNotContainsString('keep the saved value', $not_saved);
    }

    public function testAnEmptyMaxlengthLeavesTheLengthOpen()
    {
        $html = (new Form())->showSavedPassword('new_hash', 'Remote Access Key/Hash', true, '');

        $this->assertStringNotContainsString('maxlength', $html);
        $this->assertStringContainsString('type="password"', $html);
    }

    public function testATypedValueIsSaved()
    {
        $this->assertSame('n3w-s3cret', Form::savedSecretToStore('n3w-s3cret', false));
    }

    public function testATypedValueWinsOverTheRemoveBox()
    {
        $this->assertSame('n3w-s3cret', Form::savedSecretToStore('n3w-s3cret', true));
    }

    public function testTheRemoveBoxBlanksTheSavedValue()
    {
        $this->assertSame('', Form::savedSecretToStore('', true));
    }

    public function testAnEmptyFieldKeepsTheSavedValue()
    {
        $this->assertNull(Form::savedSecretToStore('', false));
    }

    public function testZeroIsAValueNotAnEmptyField()
    {
        $this->assertSame('0', Form::savedSecretToStore('0', false));
    }

} //@formatter:on
