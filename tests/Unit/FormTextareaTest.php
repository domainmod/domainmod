<?php
/**
 * /tests/Unit/FormTextareaTest.php
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
 * Form::showInputTextarea(): a browser drops one line break straight after <textarea>, so the helper writes one there
 * before the value. Notes (or any other text) that start with a blank line then keep it when they're saved again.
 */
class FormTextareaTest extends TestCase
{
    protected function setUp(): void
    {
        Form::$field_errors = array();
        Form::$error_focus_used = false;
        Form::$field_errors_shown = array();
    }

    // The text between the tags, as the page source has it
    private function between($html)
    {
        $this->assertSame(1, preg_match('#<textarea[^>]*>(.*?)</textarea>#s', $html, $matches));
        return $matches[1];
    }

    // What a browser puts in the field: the text between the tags, decoded, with every line break as a line feed, less
    // one line break at the very start
    private function asTheBrowserReadsIt($html)
    {
        return preg_replace('/^\n/', '', self::lineFeeds(html_entity_decode($this->between($html), ENT_QUOTES, 'UTF-8')));
    }

    // Every line break as a line feed, as a browser reads a page (it sends them back as a carriage return and line feed)
    private static function lineFeeds($text)
    {
        return str_replace(array("\r\n", "\r"), "\n", $text);
    }

    public function testALineBreakComesStraightAfterTheOpeningTag()
    {
        $html = (new Form())->showInputTextarea('new_notes', 'Notes', '', 'Renewed early', '', '', '');

        $this->assertStringStartsWith("\n", $this->between($html));
        $this->assertSame("\nRenewed early", $this->between($html));
    }

    public function testValuesComeBackExactlyAsSaved()
    {
        foreach (array('', 'One line', "\nStarts with a blank line", "\r\n\r\nTwo blank lines first", "\rA carriage return first", "Ends with one\n",
            '<b>"Quoted" & tagged</b>') as $value) {
            $html = (new Form())->showInputTextarea('new_notes', 'Notes', '', $value, '', '', '');
            $this->assertSame(self::lineFeeds($value), $this->asTheBrowserReadsIt($html), json_encode($value));
        }
    }
}
