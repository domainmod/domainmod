<?php
/**
 * /tests/Unit/HtmxErrorAlertTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * The shared htmx error handler in the footer (showHtmxError()). Each failed request added another alert, and an earlier
 * result stayed below it, so repeated failures stacked up (two alerts under Refresh, for example). A result area now
 * holds only the latest message, and anywhere else the new message takes the place of the earlier one. The tests read
 * the footer's script, since the unit suite runs no browser.
 */
class HtmxErrorAlertTest extends TestCase
{
    // The showHtmxError() function, and the footer's two listeners that call it
    protected function parts()
    {
        $footer = file_get_contents(dirname(__DIR__, 2) . '/_includes/layout/footer.inc.php');
        $show = substr($footer, strpos($footer, 'var showHtmxError = function'));
        $show = substr($show, 0, strpos($show, "document.addEventListener('htmx:beforeSwap'"));
        $swap = substr($footer, strpos($footer, "document.addEventListener('htmx:beforeSwap'"));
        $swap = substr($swap, 0, strpos($swap, '});'));
        $send = substr($footer, strpos($footer, "document.addEventListener('htmx:sendError'"));
        $send = substr($send, 0, strpos($send, '});'));
        return array($show, $swap, $send);
    }

    public function testEachAlertIsMarked()
    {
        list($show) = $this->parts();
        $this->assertStringContainsString("domainmod-message domainmod-htmx-error';", $show);
    }

    // htmx fires htmx:beforeSwap on the target, so the link or form that sent the request comes from its requestConfig
    public function testTheControlIsTheRequestsOwn()
    {
        list($show, $swap, $send) = $this->parts();
        $this->assertStringContainsString('var showHtmxError = function (element, target, message, control) {', $show);
        $this->assertStringContainsString('control = control || element;', $show);
        $this->assertStringContainsString('event.detail.requestConfig ? event.detail.requestConfig.elt : null);', $swap);
        // htmx:sendError fires on the control itself
        $this->assertStringContainsString('showHtmxError(event.detail.elt, event.detail.target);', $send);
    }

    // A result area (not swapped whole, with the control outside it) is emptied first, as a new result would empty it
    public function testAResultAreaHoldsOnlyTheMessage()
    {
        list($show) = $this->parts();
        $this->assertStringContainsString("var swap_element = control.closest('[hx-swap]');", $show);
        $this->assertStringContainsString("swap_element.getAttribute('hx-swap').indexOf('outerHTML') === 0;", $show);
        $this->assertStringContainsString("if (!swapped_whole && !target.contains(control)) {\n"
            . "                    target.replaceChildren(alert);", $show);
    }

    // Anywhere else (Run Now's card, or the Look up button's own place), only an earlier message goes, and the rest stays
    public function testElsewhereOnlyTheEarlierMessageGoes()
    {
        list($show) = $this->parts();
        $else = substr($show, strpos($show, 'target.replaceChildren(alert);'));
        $this->assertMatchesRegularExpression("~\\} else \\{\\s+Array\\.prototype\\.slice\\.call\\(target\\.children\\)\\.forEach\\(function \\(child\\) \\{\\s+"
            . "if \\(child\\.classList\\.contains\\('domainmod-htmx-error'\\)\\) child\\.remove\\(\\);\\s+\\}\\);\\s+"
            . "target\\.insertAdjacentElement\\('afterbegin', alert\\);~", $else);
    }

} //@formatter:on
