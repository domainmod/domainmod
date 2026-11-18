<?php
/**
 * /tests/Unit/RegistrarResponseTest.php
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

use DomainMOD\RegistrarResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistrarResponseTest extends TestCase
{
    private $file;

    protected function tearDown(): void
    {
        if ($this->file !== null && is_file($this->file)) unlink($this->file);
    }

    public function testParseXmlGivesTheRegistrarClassesShape()
    {
        $xml = '<r><list><domain><name>a.com</name></domain><domain><name>b.com</name></domain></list><empty/><n>5</n></r>';

        $this->assertSame(array(array('list' => array('domain' => array(array('name' => 'a.com'), array('name' => 'b.com'))),
            'empty' => array(), 'n' => '5')), RegistrarResponse::parseXml($xml));
    }

    public function testParseXmlGivesOneRecordWithoutAList()
    {
        $this->assertSame(array(array('list' => array('domain' => array('name' => 'a.com')))),
            RegistrarResponse::parseXml('<r><list><domain><name>a.com</name></domain></list></r>'));
    }

    public static function notXml()
    {
        return array('empty' => array(''), 'spaces' => array("  \n"), 'null' => array(null), 'an array' => array(array('<r/>')),
            'an HTML error page' => array('<html><body>Error'), 'JSON' => array('{"a": 1}'), 'text' => array('Service Unavailable'));
    }

    #[DataProvider('notXml')]
    public function testParseXmlGivesNullForAnythingElse($body)
    {
        $this->assertNull(RegistrarResponse::parseXml($body));
    }

    public function testParseXmlNeverLoadsAnExternalEntity()
    {
        $this->file = tempnam(sys_get_temp_dir(), 'domainmod-entity-');
        file_put_contents($this->file, 'SECRET-FILE-CONTENTS');
        $xml = '<!DOCTYPE r [<!ENTITY e SYSTEM "file://' . $this->file . '">]><r><a>&e;</a></r>';

        $parsed = RegistrarResponse::parseXml($xml);

        $this->assertIsArray($parsed);
        $this->assertStringNotContainsString('SECRET-FILE-CONTENTS', json_encode($parsed));
    }

    public function testParseXmlLeavesLibxmlErrorHandlingAsItWas()
    {
        // Both ways round, so a parseXml() that always turned internal errors off (or on) afterwards fails, and with
        // internal errors on, a broken response leaves nothing in the error buffer for the caller to trip over
        foreach (array(false, true) as $before) {
            $previous = libxml_use_internal_errors($before);
            try {
                RegistrarResponse::parseXml('<broken');
                $this->assertSame(array(), libxml_get_errors(), $before ? 'on' : 'off');
                $this->assertSame($before, libxml_use_internal_errors($before), $before ? 'on' : 'off');
            } finally {
                libxml_use_internal_errors($previous);
                libxml_clear_errors();
            }
        }
    }

    public function testParseXmlNeverAsksForAnExternalResource()
    {
        // A loader that records every request libxml makes for an external DTD or entity, so a change that let
        // libxml fetch one fails here even if the entity's text doesn't end up in the result
        $requests = array();
        $loader = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(function ($public, $system) use (&$requests) {
            $requests[] = $system;
            return null;
        });
        try {
            RegistrarResponse::parseXml('<!DOCTYPE r SYSTEM "http://example.invalid/r.dtd" [<!ENTITY e SYSTEM "http://example.invalid/e">]><r><a>&e;</a></r>');
        } finally {
            libxml_set_external_entity_loader($loader);
        }
        $this->assertSame(array(), $requests);
    }

    public function testParseJson()
    {
        $this->assertSame(array('a' => 1, 'b' => array(1, 2)), RegistrarResponse::parseJson('{"a": 1, "b": [1, 2]}'));
        $this->assertSame(array(), RegistrarResponse::parseJson('[]'));
        $this->assertSame(array(), RegistrarResponse::parseJson('{}'));
        foreach (array('', ' ', null, '{broken', '"text"', '5', 'null', 'true') as $body) {
            $this->assertNull(RegistrarResponse::parseJson($body), var_export($body, true));
        }
    }

    public function testIsJsonList()
    {
        $this->assertTrue(RegistrarResponse::isJsonList('[]'));
        $this->assertTrue(RegistrarResponse::isJsonList(' [{"a": 1}]'));
        $this->assertFalse(RegistrarResponse::isJsonList('{}'));
        $this->assertFalse(RegistrarResponse::isJsonList('[broken'));
        $this->assertFalse(RegistrarResponse::isJsonList(null));
    }

    public function testIsJsonListAt()
    {
        $body = '{"data": {"domains": [], "count": {"n": 0}, "text": "x"}}';

        $this->assertTrue(RegistrarResponse::isJsonListAt($body, 'data', 'domains'));
        $this->assertFalse(RegistrarResponse::isJsonListAt($body, 'data', 'count'));
        $this->assertFalse(RegistrarResponse::isJsonListAt($body, 'data', 'text'));
        $this->assertFalse(RegistrarResponse::isJsonListAt($body, 'data', 'missing'));
        $this->assertFalse(RegistrarResponse::isJsonListAt($body, 'data', 'domains', 'deeper'));
        $this->assertTrue(RegistrarResponse::isJsonListAt('[1, 2]'));
        $this->assertFalse(RegistrarResponse::isJsonListAt('', 'data'));
        $this->assertFalse(RegistrarResponse::isJsonListAt('{broken', 'data'));
    }

    public function testIsXmlContainer()
    {
        $data = array('list' => array('domain' => array(array('name' => 'a.com'))), 'empty' => array(), 'text' => 'x');

        $this->assertTrue(RegistrarResponse::isXmlContainer($data, 'list', 'domain'));
        // an account with no domains may leave the list out
        $this->assertTrue(RegistrarResponse::isXmlContainer($data, 'missing', 'domain'));
        $this->assertTrue(RegistrarResponse::isXmlContainer($data, 'empty', 'domain'));
        // text where a container should be means it isn't a domain list
        $this->assertFalse(RegistrarResponse::isXmlContainer($data, 'text', 'domain'));
        $this->assertFalse(RegistrarResponse::isXmlContainer($data, 'text'));
        $this->assertFalse(RegistrarResponse::isXmlContainer(null, 'list'));
    }

    public function testGet()
    {
        $data = array('a' => array('b' => array('c' => 'value', 'n' => null)));

        $this->assertSame('value', RegistrarResponse::get($data, 'a', 'b', 'c'));
        $this->assertSame(array('c' => 'value', 'n' => null), RegistrarResponse::get($data, 'a', 'b'));
        $this->assertNull(RegistrarResponse::get($data, 'a', 'b', 'n'));
        $this->assertNull(RegistrarResponse::get($data, 'a', 'missing', 'c'));
        $this->assertNull(RegistrarResponse::get($data, 'a', 'b', 'c', 'deeper'));
        $this->assertSame($data, RegistrarResponse::get($data));
        $this->assertNull(RegistrarResponse::get(null, 'a'));
    }

    public function testToList()
    {
        $this->assertSame(array(), RegistrarResponse::toList(null));
        $this->assertSame(array(), RegistrarResponse::toList(''));
        $this->assertSame(array(), RegistrarResponse::toList(array()));
        $this->assertSame(array(array('name' => 'a.com')), RegistrarResponse::toList(array('name' => 'a.com')));
        $this->assertSame(array('a.com'), RegistrarResponse::toList('a.com'));
        $this->assertSame(array(array('name' => 'a.com'), array('name' => 'b.com')),
            RegistrarResponse::toList(array(array('name' => 'a.com'), array('name' => 'b.com'))));
    }

    public function testToXmlList()
    {
        // only an absent list is empty: an empty element (<domain/>) is one empty record
        $this->assertSame(array(), RegistrarResponse::toXmlList(null));
        $this->assertSame(array(array()), RegistrarResponse::toXmlList(array()));
        $this->assertSame(array(), RegistrarResponse::toXmlList(''));
        $this->assertSame(array(array('name' => 'a.com')), RegistrarResponse::toXmlList(array('name' => 'a.com')));
    }

    public function testText()
    {
        $this->assertSame('a.com', RegistrarResponse::text('a.com'));
        $this->assertSame('5', RegistrarResponse::text(5));
        $this->assertSame('', RegistrarResponse::text(array()));
        $this->assertSame('', RegistrarResponse::text(null));
        $this->assertSame('', RegistrarResponse::text(1.5));
        $this->assertSame('', RegistrarResponse::text(true));
    }

} //@formatter:on
