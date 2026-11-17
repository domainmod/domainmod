<?php
/**
 * /tests/Unit/CustomFieldValuesTest.php
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

use DomainMOD\CustomField;
use DomainMOD\Form;
use PHPUnit\Framework\TestCase;

/*
 * Custom field values on the add and edit pages, the Domain Queue's edit page, and the Bulk Updater: they're posted in
 * their own array, so a custom field named like one of the page's settings (such as notes) can't take its place, and a
 * Date or Time Stamp that isn't a real date or time is marked under its field instead of being saved as a zero date.
 */
class CustomFieldValuesTest extends TestCase
{
    protected function setUp(): void
    {
        Form::$field_errors = array();
        Form::$error_focus_used = false;
        Form::$field_errors_shown = array();
        $_SESSION = array();
    }

    public function testTheInputNameKeepsCustomFieldsInTheirOwnArray()
    {
        $this->assertSame('custom_fields[notes]', CustomField::inputName('notes'));
    }

    public function testACustomFieldKeepsItsLeftToRightDirection()
    {
        $form = new Form();

        // As when the fields were posted as new_contact_url and new_ip
        $this->assertSame(' dir="ltr"', $form->dirAttribute(CustomField::inputName('contact_url')));
        $this->assertSame(' dir="ltr"', $form->dirAttribute(CustomField::inputName('ip')));
        $this->assertSame('', $form->dirAttribute(CustomField::inputName('purchaser_name')));
    }

    public function testAFieldNamedLikeAPageSettingDoesNotTakeItsPlace()
    {
        // As PHP parses a form with the page's own Notes and a custom field named notes
        parse_str('new_notes=' . urlencode('The domain notes') . '&custom_fields%5Bnotes%5D=' . urlencode('The custom notes'), $post);

        $values = CustomField::postedValues(array('notes'), $post);

        $this->assertSame(array('notes' => 'The custom notes'), $values);
        $this->assertSame('The domain notes', $post['new_notes']);
    }

    public function testMissingAndNonTextValuesAreEmpty()
    {
        $post = array('custom_fields' => array('renewed_on' => array('2026-01-01'), 'checked' => '1'));

        $values = CustomField::postedValues(array('renewed_on', 'checked', 'unticked'), $post);

        $this->assertSame(array('renewed_on' => '', 'checked' => '1', 'unticked' => ''), $values);
        $this->assertSame(array('a' => ''), CustomField::postedValues(array('a'), array('custom_fields' => 'text')));
        $this->assertSame(array('a' => ''), CustomField::postedValues(array('a'), array()));
    }

    public function testDateValues()
    {
        $this->assertSame('', CustomField::valueError(4, ''));
        $this->assertSame('', CustomField::valueError(4, '2026-02-28'));
        $this->assertSame('', CustomField::valueError('4', '2028-02-29'));

        foreach (array('next week', '2026-13-45', '2026-02-30', '2026-2-3', '2026-02-28 10:00:00', ' 2026-02-28', "2026-02-28\n") as $value) {
            $this->assertSame('Enter a date in the format YYYY-MM-DD, or leave it blank', CustomField::valueError(4, $value), $value);
        }
    }

    public function testTimeStampValues()
    {
        $this->assertSame('', CustomField::valueError(5, ''));
        $this->assertSame('', CustomField::valueError(5, '2026-11-17 23:59:59'));
        $this->assertSame('', CustomField::valueError('5', '2026-01-01 00:00:00'));

        foreach (array('now', '2026-11-17', '2026-11-17 24:00:00', '2026-11-17 10:60:00', '2026-11-17 10:00:60',
            '2026-02-30 10:00:00', '2026-11-17T10:00:00', '2026-11-17 10:00', "2026-11-17 10:00:00\n") as $value) {
            $this->assertSame('Enter a time stamp in the format YYYY-MM-DD HH:MM:SS, or leave it blank', CustomField::valueError(5, $value), $value);
        }
    }

    public function testOtherTypesTakeAnyText()
    {
        foreach (array(1, 2, 3, 6) as $type_id) {
            $this->assertSame('', CustomField::valueError($type_id, 'next week'));
        }
    }

    public function testInvalidValuesAreMarkedUnderTheirFields()
    {
        $form = new Form();
        $types = array('renewed_on' => 4, 'checked_at' => 5, 'notes' => 3, 'paid_on' => 4);
        $values = array('renewed_on' => '2026-13-45', 'checked_at' => 'later', 'notes' => 'anything', 'paid_on' => '2026-11-17');

        $this->assertFalse(CustomField::markInvalidValues($form, $types, $values));
        $this->assertSame(array('custom_fields[renewed_on]', 'custom_fields[checked_at]'), array_keys(Form::$field_errors));
        $this->assertStringContainsString('Fix the 2 fields marked below.', $_SESSION['s_message_danger']);
    }

    public function testValidValuesAreNotMarked()
    {
        $form = new Form();

        $this->assertTrue(CustomField::markInvalidValues($form, array('renewed_on' => 4, 'checked_at' => 5),
            array('renewed_on' => '', 'checked_at' => '2026-11-17 08:30:00')));
        $this->assertSame(array(), Form::$field_errors);
    }

} //@formatter:on
