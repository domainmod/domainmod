<?php
/**
 * /tests/Unit/StatusIconSpacingTest.php
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

/*
 * The room between a list's status icon and the item's name, read from css/base.css. On the Domains, SSL Certificates,
 * and Hosting lists the name's link follows the icon (Layout::statusBadge() with an icon) and a space, and gets a little
 * more room than the space alone.
 */
class StatusIconSpacingTest extends TestCase
{
    // Every rule in css/base.css (those inside @media too), as its selector and declarations, with comments removed
    protected function rules()
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/base.css');
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        foreach ($matches as $match) {
            $rules[] = array('selector' => preg_replace('/\s+/', ' ', trim($match[1])), 'body' => trim($match[2]));
        }
        return $rules;
    }

    // One rule adds the room on the reading side only (logical, so right-to-left pages get it on the right)
    public function testTheNameGetsALittleMoreRoom()
    {
        $rules = array_values(array_filter($this->rules(), function ($rule) {
            return $rule['selector'] === '.domainmod-status-icon + a';
        }));
        $this->assertCount(1, $rules);
        $this->assertSame('margin-inline-start: 0.25rem;', $rules[0]['body']);
    }

    // The icon is the span the rule names
    public function testTheStatusIconIsTheElementTheRuleNames()
    {
        $html = (new Layout())->statusBadge('E', 'Expired', 'danger', 'bi-x-circle');
        $this->assertMatchesRegularExpression('/^<span class="domainmod-status-icon [^"]*"/', $html);
        $this->assertStringEndsWith('</span>', $html);
    }

    // On each list the name's link is the icon's next element, with only a space between them
    public static function lists()
    {
        return array(
            'domains' => array('DomainListColumns', "\$html .= '<a href=\"edit.php?did='"),
            'ssl' => array('SslListColumns', "return \$html . '<a href=\"edit.php?sslcid='"),
            'hosting' => array('HostingListColumns', "return \$html . '<a href=\"edit.php?hsid='"));
    }

    #[DataProvider('lists')]
    public function testTheNamesLinkFollowsTheIcon($class, $link)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/DomainMOD/' . $class . '.php');
        $this->assertMatchesRegularExpression('/\$html \.= \$layout->statusBadge\(\$badge\[0\], \$badge\[1\], \$badge\[2\], \$badge\[3\]\) \. \'&nbsp;\';\n\s*\}\n\s*'
            . preg_quote($link, '/') . '/', $source, $class);
    }

} //@formatter:on
