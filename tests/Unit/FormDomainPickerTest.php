<?php
/**
 * /tests/Unit/FormDomainPickerTest.php
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
 * Form::showDomainPicker() and Form::showDomainListCount(), the Bulk Updater's domain picker and its list's count
 * (js/bulk-domain-picker.js): hidden until the script sets them up, never submitted, and with every message escaped.
 */
class FormDomainPickerTest extends TestCase
{
    private function picker($search_url = '/dm/domains/name-search.php')
    {
        $html = (new Form())->showDomainPicker('raw_domain_list', 'Find a domain', 'Type part of a name.', $search_url);
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_NOERROR);
        return array($html, new \DOMXPath($document));
    }

    // The one element the query finds
    private function element(\DOMXPath $xpath, $query)
    {
        $element = $xpath->query($query)->item(0);
        $this->assertInstanceOf(\DOMElement::class, $element, $query);
        return $element;
    }

    public function testThePickerIsHiddenUntilTheScriptSetsItUp()
    {
        list(, $xpath) = $this->picker();

        $field = $this->element($xpath, '//div[contains(@class, "domainmod-domain-picker-field")]');
        $this->assertNotNull($field);
        $this->assertTrue($field->hasAttribute('hidden'));
        $this->assertTrue($this->element($xpath, '//div[@id="domainmod-field-raw_domain_list-picker-more"]')->hasAttribute('hidden'));
    }

    public function testTheSearchBoxIsNeverSubmitted()
    {
        list(, $xpath) = $this->picker();

        $select = $this->element($xpath, '//select');
        $this->assertFalse($select->hasAttribute('name'));
        $this->assertSame(0, $xpath->query('//select/option')->length);
        $this->assertSame('form-select domainmod-domain-picker', $select->getAttribute('class'));
        $this->assertSame('domainmod-field-raw_domain_list', $select->getAttribute('data-list'));
    }

    public function testTheLabelAndHelpBelongToTheBox()
    {
        list(, $xpath) = $this->picker();

        $select = $this->element($xpath, '//select');
        $this->assertSame('domainmod-field-raw_domain_list-picker', $select->getAttribute('id'));
        $this->assertSame('Find a domain', $this->element($xpath, '//label[@for="domainmod-field-raw_domain_list-picker"]')->textContent);
        $help = $xpath->query('//*[@id="' . $select->getAttribute('aria-describedby') . '"]')->item(0);
        $this->assertSame('Type part of a name.', $help->textContent);
    }

    public function testTheMessagesAreInDataAttributes()
    {
        list(, $xpath) = $this->picker();

        $select = $this->element($xpath, '//select');
        $this->assertSame('/dm/domains/name-search.php', $select->getAttribute('data-search-url'));
        $this->assertSame('No matching domains.', $select->getAttribute('data-no-results'));
        $this->assertSame('%1$s, %2$s', $select->getAttribute('data-option'));
        $this->assertSame('Added %s to the list.', $select->getAttribute('data-added'));
        $this->assertSame('%s is already on the list.', $select->getAttribute('data-already'));
        $this->assertSame("That didn't work. Please reload the page and try again.", $select->getAttribute('data-error'));
    }

    public function testTheDataAttributesAreEscaped()
    {
        list($html) = $this->picker('/a"b<c>&\'d/domains/name-search.php');

        $this->assertStringContainsString('data-search-url="/a&quot;b&lt;c&gt;&amp;&#039;d/domains/name-search.php"', $html);
        $this->assertStringContainsString('data-error="That didn&#039;t work. Please reload the page and try again."', $html);
    }

    public function testTheLabelAndHelpAreEscaped()
    {
        $html = (new Form())->showDomainPicker('raw_domain_list', 'Find <b>', 'Such as <example.com> & more', '/dm');

        $this->assertStringContainsString('>Find &lt;b&gt;</label>', $html);
        $this->assertStringContainsString('>Such as &lt;example.com&gt; &amp; more</div>', $html);
    }

    public function testTheCountIsHiddenUntilTheScriptFillsItIn()
    {
        $html = (new Form())->showDomainListCount('raw_domain_list');
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
        $count = $this->element(new \DOMXPath($document), '//div[contains(@class, "domainmod-domain-list-count")]');

        $this->assertTrue($count->hasAttribute('hidden'));
        $this->assertSame('domainmod-field-raw_domain_list', $count->getAttribute('data-list'));
        $this->assertSame('Domains on the list: 0', trim(preg_replace('/\s+/', ' ', $count->textContent)));
    }

} //@formatter:on
