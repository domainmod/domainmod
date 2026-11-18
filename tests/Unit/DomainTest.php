<?php
/**
 * /tests/Unit/DomainTest.php
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

use DomainMOD\Domain;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Domain's IDN methods use idn_to_ascii() and idn_to_utf8(), which come from the intl extension when it's installed
 * and from symfony/polyfill-intl-idn when it isn't. The suite runs on both (the intl run, and every other run), and
 * every case here must give the same answer on each.
 */
class DomainTest extends TestCase
{
    public function testUsesIntlWhenInstalledAndThePolyfillOtherwise()
    {
        $function = new \ReflectionFunction('idn_to_ascii');

        if (extension_loaded('intl')) {
            $this->assertSame('intl', $function->getExtensionName());
        } else {
            $this->assertFalse($function->getExtensionName());
            $this->assertStringContainsString('polyfill-intl-idn', str_replace('\\', '/', (string) $function->getFileName()));
        }
    }

    public static function asciiNames()
    {
        return array(
            'Unicode' => array('münchen.de', 'xn--mnchen-3ya.de'),
            'upper case Unicode' => array('MÜNCHEN.DE', 'xn--mnchen-3ya.de'),
            'punycode' => array('xn--mnchen-3ya.de', 'xn--mnchen-3ya.de'),
            'a Unicode TLD' => array('рф', 'xn--p1ai'),
            'Japanese' => array('例え.テスト', 'xn--r8jz45g.xn--zckzah'),
            'full-width letters' => array('ＥＸＡＭＰＬＥ.com', 'example.com'),
            'German sharp s, kept' => array('faß.de', 'xn--fa-hia.de'),
            'a trailing dot, kept' => array('example.com.', 'example.com.'),
            'padded' => array(' example.com ', 'example.com'),
            'empty' => array('', ''),
            'spaces only' => array('   ', ''),
            'null' => array(null, ''),
            'a space inside' => array('exa mple.com', ''),
            'an underscore' => array('example_x.com', ''),
            'a leading hyphen' => array('-abc.com', ''),
            'a trailing hyphen' => array('abc-.com', ''),
            'hyphens in the third and fourth places' => array('ab--c.com', ''),
            'an empty label' => array('a..b', ''),
            'a leading dot' => array('.example.com', ''),
            'bad punycode' => array('xn--zz.com', ''),
            'a label over 63 characters' => array(str_repeat('a', 64) . '.com', ''),
            'a zero-width non-joiner' => array("a\u{200C}b.com", ''),
        );
    }

    #[DataProvider('asciiNames')]
    public function testToAscii($input, $expected)
    {
        $this->assertSame($expected, Domain::toAscii($input));
    }

    public static function storedNames()
    {
        return array(
            'Unicode' => array('münchen.de', 'münchen.de'),
            'upper case Unicode' => array('MÜNCHEN.DE', 'münchen.de'),
            'punycode' => array('xn--mnchen-3ya.de', 'münchen.de'),
            'upper case ASCII' => array('Example.COM', 'example.com'),
            'a trailing dot' => array('example.com.', 'example.com'),
            'ideographic full stops' => array("example\u{3002}com\u{3002}", 'example.com'),
            'a punycode TLD' => array('xn--p1ai', 'рф'),
            'Japanese' => array('xn--r8jz45g.xn--zckzah', '例え.テスト'),
            'German sharp s' => array('xn--zca.de', 'ß.de'),
            'outside the Basic Multilingual Plane' => array("\u{1F600}.com", 'xn--e28h.com'),
            'outside the Basic Multilingual Plane, as punycode' => array('xn--e28h.com', 'xn--e28h.com'),
            'two trailing dots' => array('example.com..', ''),
            'only a dot' => array('.', ''),
            'empty' => array('', ''),
            'invalid' => array('exa mple.com', ''),
        );
    }

    #[DataProvider('storedNames')]
    public function testNormalizeGivesTheStoredForm($input, $expected)
    {
        $this->assertSame($expected, Domain::normalize($input));
    }

    #[DataProvider('storedNames')]
    public function testNormalizeIsStableOnItsOwnResult($input, $expected)
    {
        $this->assertSame($expected, Domain::normalize(Domain::normalize($input)));
        $this->assertSame($expected, Domain::normalizeCached($input));
    }

    public function testNormalizeOrKeep()
    {
        $this->assertSame('münchen.de', Domain::normalizeOrKeep(' xn--mnchen-3ya.de '));
        $this->assertSame('not a domain', Domain::normalizeOrKeep('  not a domain '));
        $this->assertSame('', Domain::normalizeOrKeep(null));
    }

    public function testForApi()
    {
        $this->assertSame('xn--mnchen-3ya.de', Domain::forApi('münchen.de'));
        $this->assertSame('example.com', Domain::forApi('example.com'));
        // a name that can't be converted goes to the registrar as it is, untrimmed
        $this->assertSame(' bad domain ', Domain::forApi(' bad domain '));
        $this->assertSame('-abc.com', Domain::forApi('-abc.com'));
    }

    public static function sameDomains()
    {
        return array(
            'the same ASCII name' => array('example.com', 'example.com', true),
            'ASCII in another case' => array('Example.COM', 'example.com', true),
            'another ASCII name' => array('example.org', 'example.com', false),
            'punycode' => array('xn--mnchen-3ya.de', 'münchen.de', true),
            'upper case punycode' => array('XN--MNCHEN-3YA.DE', 'münchen.de', true),
            'Unicode' => array('MÜNCHEN.de', 'münchen.de', true),
            'a trailing dot' => array('münchen.de.', 'münchen.de', true),
            'an ASCII name with a trailing dot' => array('EXAMPLE.COM.', 'example.com', true),
            'padded' => array(' example.com ', 'example.com', true),
            'another IDN' => array('xn--mnchen-3ya.de', 'muenchen.de', false),
            'empty' => array('', '', false),
            'invalid' => array('bad name', 'bad name', false),
        );
    }

    #[DataProvider('sameDomains')]
    public function testSameDomain($outside, $target, $expected)
    {
        $this->assertSame($expected, Domain::sameDomain($outside, $target));
    }

    public static function tlds()
    {
        return array(
            'a plain TLD' => array('example.com', 'com'),
            'a second-level TLD' => array('example.co.uk', 'co.uk'),
            'a punycode TLD' => array('xn--e1afmkfd.xn--p1ai', 'рф'),
            'upper case' => array('example.CO.UK', 'co.uk'),
            'no dot' => array('localhost', 'localhost'),
            'an invalid TLD, kept' => array('example.bad tld', 'bad tld'),
        );
    }

    #[DataProvider('tlds')]
    public function testTldOf($domain, $expected)
    {
        $this->assertSame($expected, Domain::tldOf($domain));
    }

    public function testNormalizeTld()
    {
        $this->assertSame('рф', Domain::normalizeTld(' XN--P1AI '));
        $this->assertSame('co.uk', Domain::normalizeTld('CO.UK'));
        $this->assertSame('bad tld', Domain::normalizeTld(' bad tld '));
        // valid, but over the 50-character TLD columns once normalised, so it's left as it is (in upper case)
        $this->assertSame(str_repeat('A', 51), Domain::normalizeTld(str_repeat('A', 51)));
        $this->assertSame(str_repeat('a', 50), Domain::normalizeTld(str_repeat('A', 50)));
    }

    public static function formats()
    {
        return array(
            'a plain name' => array('example.com', true),
            'a subdomain' => array('www.example.co.uk', true),
            'an IDN' => array('münchen.de', true),
            'punycode' => array('xn--mnchen-3ya.de', true),
            'no dot' => array('example', false),
            'a leading dot' => array('.example.com', false),
            'a trailing dot' => array('example.com.', false),
            'a space' => array('exa mple.com', false),
            'a tab' => array("example.com\t", false),
            'an asterisk' => array('*.example.com', false),
            'empty' => array('', false),
            'null' => array(null, false),
            'invalid IDNA' => array('-abc.com', false),
        );
    }

    #[DataProvider('formats')]
    public function testCheckFormat($input, $expected)
    {
        $domain = Instance::withoutConstructor(Domain::class);

        $this->assertSame($expected, $domain->checkFormat($input));
    }

    public function testFindInvalidDomainsCountsEveryBadLineAndShowsTheFirstFive()
    {
        // The Bulk Updater's and Add Domains' check of a pasted list
        $domain = Instance::withoutConstructor(Domain::class);
        $lines = array('good.com', 'bad1', 'bad2', 'münchen.de', 'bad3', 'bad4', 'bad5', 'bad6');

        list($to_display, $has_invalid, $count, $message) = $domain->findInvalidDomains($lines);

        $this->assertSame(5, $to_display);
        $this->assertSame(1, $has_invalid);
        $this->assertSame(6, $count);
        $this->assertSame('Line 2 contains an invalid domain<BR>Line 3 contains an invalid domain<BR>'
            . 'Line 5 contains an invalid domain<BR>Line 6 contains an invalid domain<BR>'
            . 'Line 7 contains an invalid domain<BR>', $message);
    }

    public function testFindInvalidDomainsWithAValidList()
    {
        $domain = Instance::withoutConstructor(Domain::class);

        $this->assertSame(array(5, 0, 0, ''), $domain->findInvalidDomains(array('a.com', 'b.org')));
    }

    public function testDomainPartAndNewExpiry()
    {
        $domain = Instance::withoutConstructor(Domain::class);

        $this->assertSame('example', $domain->getDomainPart('example.co.uk'));
        $this->assertSame('2028-03-01', $domain->getNewExpiry('2026-03-01', 2));
    }

} //@formatter:on
