<?php
/**
 * /tests/Unit/LayoutTest.php
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LayoutTest extends TestCase
{
    public static function links()
    {
        return array(
            'http' => array('http://example.com/'),
            'https' => array('https://example.com/a?b=c&d=e'),
            'ftp' => array('ftp://example.com/file'),
            'ftps' => array('ftps://example.com/file'),
            'sftp' => array('sftp://example.com/file'),
            'mailto' => array('mailto:someone@example.com'),
            'upper case' => array('HTTPS://example.com/'),
            'no scheme' => array('example.com/page'),
            'a relative path' => array('/docs/page'),
        );
    }

    #[DataProvider('links')]
    public function testCustomFieldLinkLinksSafeAddresses($url)
    {
        $expected = "[<a target='_blank' rel='noopener noreferrer' href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . "'>open</a>]";

        $this->assertSame($expected, (new Layout())->customFieldLink($url));
    }

    public static function unsafeAddresses()
    {
        return array(
            'javascript' => array('javascript:alert(1)'),
            'mixed case' => array('JaVaScRiPt:alert(1)'),
            'a tab inside the scheme' => array("java\tscript:alert(1)"),
            'a line break inside the scheme' => array("java\nscript:alert(1)"),
            'a carriage return inside the scheme' => array("java\rscript:alert(1)"),
            'a NUL byte inside the scheme' => array("java\0script:alert(1)"),
            'data' => array('data:text/html,<script>alert(1)</script>'),
            'vbscript' => array('vbscript:msgbox(1)'),
            'file' => array('file:///etc/passwd'),
        );
    }

    #[DataProvider('unsafeAddresses')]
    public function testCustomFieldLinkShowsOtherSchemesAsText($url)
    {
        $html = (new Layout())->customFieldLink($url);

        $this->assertSame(htmlspecialchars(trim($url), ENT_QUOTES, 'UTF-8'), $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    public function testCustomFieldLinkEscapesQuotesInTheAddress()
    {
        $html = (new Layout())->customFieldLink("https://example.com/?q='><script>x</script>\"");

        $this->assertSame("[<a target='_blank' rel='noopener noreferrer' href='https://example.com/?q=&#039;&gt;&lt;script&gt;x"
            . "&lt;/script&gt;&quot;'>open</a>]", $html);
    }

    public function testCustomFieldLinkIsEmptyForNoAddress()
    {
        $this->assertSame('', (new Layout())->customFieldLink(''));
        $this->assertSame('', (new Layout())->customFieldLink('   '));
        $this->assertSame('', (new Layout())->customFieldLink(null));
    }

    public function testCustomFieldTextEscapes()
    {
        $this->assertSame('&lt;b&gt;&quot;Tom&#039;s&quot;&lt;/b&gt;', (new Layout())->customFieldText('<b>"Tom\'s"</b>'));
    }

    public function testIsLeftToRightWithoutALanguage()
    {
        // The bootstrap leaves DEFAULT_LANGUAGE undefined (CurrencyTest checks a right-to-left language)
        $this->assertFalse((new Layout())->isRtl());
    }

    public function testLtrIsolatesAndEscapes()
    {
        $this->assertSame('<bdi dir="ltr">a&amp;b.com</bdi>', (new Layout())->ltr('a&b.com'));
    }

} //@formatter:on
