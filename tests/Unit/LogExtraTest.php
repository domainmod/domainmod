<?php
/**
 * /tests/Unit/LogExtraTest.php
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

use DomainMOD\Log;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The debug log's extra details: how new entries save them (as a JSON object), how the Debug Log reads them back (both
// that and the older "key":"value" format), and the level badges.
class LogExtraTest extends TestCase
{
    public static function readableCases()
    {
        return array(
            'the older format' => array('"Last Error":"", "Domain":"example.com", "Registrar":"Example"',
                array('Last Error' => '', 'Domain' => 'example.com', 'Registrar' => 'Example')),
            'a JSON object' => array('{"Last Error":"","Domain":"example.com","Path":"/a/b"}',
                array('Last Error' => '', 'Domain' => 'example.com', 'Path' => '/a/b')),
            'a JSON object with escapes' => array('{"Message":"Say \"hi\"","Path":"C:\\\\temp"}',
                array('Message' => 'Say "hi"', 'Path' => 'C:\\temp')),
            'HTML-like values stay plain text' => array('"Name":"<script>alert(1)</script>", "<b>Key</b>":"<img src=x>"',
                array('Name' => '<script>alert(1)</script>', '<b>Key</b>' => '<img src=x>')),
            'numbers, true, false, and null' => array('{"Count":3,"Rate":1.5,"On":true,"Off":false,"Response":null}',
                array('Count' => '3', 'Rate' => '1.5', 'On' => 'true', 'Off' => 'false', 'Response' => '')),
            'a large number keeps its digits' => array('{"Id":123456789012345678901234567890}',
                array('Id' => '123456789012345678901234567890')),
            'a numeric key' => array('{"0":"first"}', array('0' => 'first')),
            'spaces around the text' => array("  \"Domain\":\"example.com\"\n", array('Domain' => 'example.com')),
        );
    }

    #[DataProvider('readableCases')]
    public function testExtraPairsReadsAFlatObject($extra, $expected)
    {
        $this->assertSame($expected, Log::extraPairs($extra));
    }

    public static function unreadableCases()
    {
        return array(
            'empty' => array(''),
            'only spaces' => array('   '),
            'null' => array(null),
            'an older value with a quote' => array('"Last Error":"", "Message":"Say "hi""'),
            'an older value with a backslash' => array('"Last Error":"", "Path":"C:\new"'),
            'an older value with an escaped quote' => array('"Message":"Say \"hi\""'),
            'a nested object' => array('{"Last Error":"","Response":{"code":200}}'),
            'an array value' => array('{"Last Error":"","Domains":["a.com","b.com"]}'),
            'a top-level list' => array('["a","b"]'),
            'an empty object' => array('{}'),
            'plain text' => array('Something went wrong'),
            'broken JSON' => array('{"Domain":"example.com"'),
        );
    }

    #[DataProvider('unreadableCases')]
    public function testExtraPairsReturnsNullWhenTheTextCantBeRead($extra)
    {
        $this->assertNull(Log::extraPairs($extra));
    }

    public function testExtraFactsKeepsEveryKeyWithADashForAnEmptyValue()
    {
        $facts = Log::extraFacts(array('Last Error' => '', 'Domain' => 'example.com', 'Response' => '', 'Zero' => '0'));

        $this->assertSame(array(
            array('label' => 'Last Error', 'value' => '-'),
            array('label' => 'Domain', 'value' => 'example.com'),
            array('label' => 'Response', 'value' => '-'),
            array('label' => 'Zero', 'value' => '0'),
        ), $facts);
    }

    public function testExtraFactsKeepsANumericKeyAsText()
    {
        $this->assertSame(array(array('label' => '0', 'value' => 'first')), Log::extraFacts(Log::extraPairs('{"0":"first"}')));
    }

    private function formatted($extra_info)
    {
        error_clear_last();
        return Instance::withoutConstructor(Log::class)->formatExtraInfo($extra_info);
    }

    public function testFormatExtraInfoSavesAJsonObjectThatReadsBack()
    {
        $extra_info = array('Domain' => 'example.com', 'Message' => 'Say "hi" to C:\\temp', 'URL' => 'https://example.com/a/b',
            'Name' => 'Café ünïcödé', 'Markup' => '<b>bold</b>');
        $json = $this->formatted($extra_info);

        $this->assertStringStartsWith('{', $json);
        $this->assertStringContainsString('https://example.com/a/b', $json); // slashes unescaped
        $this->assertStringContainsString('Café ünïcödé', $json); // Unicode unescaped
        $this->assertSame(array('Last Error' => '') + $extra_info, Log::extraPairs($json));
    }

    public function testFormatExtraInfoKeepsTheLastPhpError()
    {
        error_clear_last();
        @trigger_error('Something broke', E_USER_NOTICE);
        $json = Instance::withoutConstructor(Log::class)->formatExtraInfo(array('Domain' => 'example.com'));
        error_clear_last();

        $this->assertSame(array('Last Error' => 'Something broke', 'Domain' => 'example.com'), Log::extraPairs($json));
    }

    public function testFormatExtraInfoReadsBackNumbersAndBooleansAsText()
    {
        $json = $this->formatted(array('Count' => 3, 'On' => true, 'Off' => false, 'Response' => null));

        $this->assertSame(array('Last Error' => '', 'Count' => '3', 'On' => 'true', 'Off' => 'false', 'Response' => ''),
            Log::extraPairs($json));
    }

    public function testFormatExtraInfoKeepsEveryFieldWhenOneHasAMalformedByte()
    {
        $json = $this->formatted(array('Domain' => 'example.com', 'Response' => "abc\xC3def", 'Registrar' => 'Example'));

        $this->assertNotSame('', $json);
        $pairs = Log::extraPairs($json);
        $this->assertSame(array('Last Error', 'Domain', 'Response', 'Registrar'), array_keys($pairs));
        $this->assertSame('example.com', $pairs['Domain']);
        $this->assertSame("abc\u{FFFD}def", $pairs['Response']);
        $this->assertSame('Example', $pairs['Registrar']);
    }

    public function testFormatExtraInfoFallsBackToTheOlderFormatWhenJsonCantEncode()
    {
        // A resource (such as an open file) can't be encoded as JSON, so the details are saved the older way instead of
        // being lost
        $handle = fopen('php://memory', 'r');
        $saved = $this->formatted(array('Domain' => 'example.com', 'Handle' => $handle));
        fclose($handle);

        $this->assertMatchesRegularExpression('/^"Last Error":"", "Domain":"example\\.com", "Handle":"Resource id #\\d+"$/', $saved);
    }

    public static function levelCases()
    {
        return array(
            'emergency' => array('emergency', 'Emergency', 'danger'),
            'alert' => array('alert', 'Alert', 'danger'),
            'critical' => array('critical', 'Critical', 'danger'),
            'error' => array('error', 'Error', 'danger'),
            'warning' => array('warning', 'Warning', 'warning'),
            'notice' => array('notice', 'Notice', 'info'),
            'info' => array('info', 'Info', 'info'),
            'debug' => array('debug', 'Debug', 'secondary'),
            'upper case' => array('ERROR', 'Error', 'danger'),
            'log() keeps its word' => array('log', 'log', 'secondary'),
        );
    }

    #[DataProvider('levelCases')]
    public function testLevelInfoNamesAndColoursEachLevel($level, $name, $colour)
    {
        $this->assertSame(array($name, $colour), Log::levelInfo($level));
    }

    public function testLevelBadgeEscapesAnUnknownLevel()
    {
        $this->assertSame('<span class="badge text-bg-danger">Error</span>', Log::levelBadge('error'));
        $this->assertSame('<span class="badge text-bg-secondary">&lt;b&gt;x&lt;/b&gt;</span>', Log::levelBadge('<b>x</b>'));
    }

} //@formatter:on
