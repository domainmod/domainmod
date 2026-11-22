<?php
/**
 * /tests/Unit/DenseListTableTest.php
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
 * The dense list style (domainmod-dense-table in css/base.css), the Domains list's look for any table that opts in, and
 * the Import Queue's three tables, which use it. Each table sits straight inside a scroll box the page draws for when
 * JavaScript is off, and DataTables (without Responsive) makes its own box around just the table, which
 * domainmodTable() then keeps in place of the page's. The queue's status wording is checked here too.
 */
class DenseListTableTest extends TestCase
{
    // The border between columns, and around the box
    const LINE = '1px solid var(--bs-border-color-translucent)';

    // The Import Queue's tables, by the end of their id, with each scroll box's name
    const QUEUE_TABLES = array('lists' => 'Domain List Queue', 'domains' => 'Domain Queue', 'hosts' => 'SSL Queue');

    protected function root()
    {
        return dirname(__DIR__, 2);
    }

    // Every rule in a stylesheet (base.css unless another is named), as its selector (with the spaces evened out) and
    // declarations, with comments removed
    protected function rules($file = '/css/base.css')
    {
        $css = file_get_contents($this->root() . $file);
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        foreach ($matches as $match) {
            $rules[] = array('selector' => preg_replace('/\s+/', ' ', trim($match[1])), 'body' => $match[2]);
        }
        return $rules;
    }

    // The value a property ends up with from the rules with this exact selector (the last one wins)
    protected function value(array $rules, $selector, $property)
    {
        $value = null;
        $found = false;
        foreach ($rules as $rule) {
            if ($rule['selector'] !== $selector) continue;
            $found = true;
            foreach (explode(';', $rule['body']) as $declaration) {
                if (strpos($declaration, ':') === false) continue;
                list($name, $setting) = array_map('trim', explode(':', $declaration, 2));
                if (strtolower($name) === $property) $value = $setting;
            }
        }
        $this->assertTrue($found, $selector);
        return $value;
    }

    // Where the last rule with this exact selector that sets a property is, among all the rules (null if none does)
    protected function lastSetting(array $rules, $selector, $property)
    {
        $last = null;
        foreach ($rules as $index => $rule) {
            if ($rule['selector'] !== $selector) continue;
            foreach (explode(';', $rule['body']) as $declaration) {
                if (strpos($declaration, ':') === false) continue;
                if (strtolower(trim(explode(':', $declaration, 2)[0])) === $property) $last = $index;
            }
        }
        return $last;
    }

    public function testTheSharedStyleBoxesTheTableWithDividersAndALastRowWithoutALine()
    {
        $rules = $this->rules();
        $this->assertSame(self::LINE, $this->value($rules, '.domainmod-list-scroll:has(> .domainmod-dense-table)', 'border'));
        $this->assertSame(self::LINE, $this->value($rules, '.domainmod-dense-table > :is(thead, tbody) > tr > :not(:last-child)', 'border-inline-end'));
        $this->assertSame(self::LINE, $this->value($rules, '.domainmod-dense-table > tbody > tr > td', 'border-block-end'));
        $this->assertSame('0', $this->value($rules, '.domainmod-list-scroll:has(> .domainmod-dense-table) > .domainmod-dense-table > tbody > tr:last-child > td', 'border-block-end'));
    }

    public function testTheSharedStyleHasDenseRowsAHoverAndAPinnedFirstColumn()
    {
        $rules = $this->rules();
        $cell = '.domainmod-dense-table > tbody > tr > td';
        $this->assertSame('.3125rem .5rem', $this->value($rules, $cell, 'padding'));
        $this->assertSame('none', $this->value($rules, $cell, 'box-shadow'));
        $this->assertSame('middle', $this->value($rules, $cell, 'vertical-align'));
        $this->assertSame('var(--bs-secondary-bg)', $this->value($rules, '.domainmod-dense-table > tbody > tr:hover > td', 'background'));
        $first = '.domainmod-dense-table > :is(thead, tbody) > tr > :first-child';
        $this->assertSame('sticky', $this->value($rules, $first, 'position'));
        $this->assertSame('0', $this->value($rules, $first, 'inset-inline-start'));
        $this->assertSame('min(18rem, 45vw)', $this->value($rules, $first, 'max-inline-size'));
    }

    // As in the Domains list, the first column's values are in bold, its heading is like the other headings, and the
    // message for a search with no matches isn't in bold
    public function testTheSharedStyleBoldsTheFirstColumnsValuesLikeTheDomainsList()
    {
        $domains = $this->rules('/css/datatables/domains-main.css');
        $this->assertSame('600', $this->value($domains, '#domains-main > tbody > tr > td.domainmod-list-domain', 'font-weight'));
        $this->assertSame('600', $this->value($domains, '#domains-main > thead > tr > th', 'font-weight'));

        $rules = $this->rules();
        $this->assertSame('600', $this->value($rules, '.domainmod-dense-table > tbody > tr > td:first-child', 'font-weight'));
        $this->assertSame('600', $this->value($rules, '.domainmod-dense-table > thead > tr > th', 'font-weight'));
        $this->assertNull($this->value($rules, '.domainmod-dense-table > :is(thead, tbody) > tr > :first-child', 'font-weight'));
        $this->assertNull($this->value($rules, '.domainmod-dense-table > tbody > tr > td', 'font-weight'));
        $this->assertSame('inherit', $this->value($rules, '.domainmod-dense-table > tbody > tr > td.dt-empty', 'font-weight'));
        // The message's cell is also the row's first, and both selectors weigh the same, so its weight has to be set
        // after the first column's
        $this->assertGreaterThan($this->lastSetting($rules, '.domainmod-dense-table > tbody > tr > td:first-child', 'font-weight'),
            $this->lastSetting($rules, '.domainmod-dense-table > tbody > tr > td.dt-empty', 'font-weight'));

        // No other rule for these tables sets a weight, so the bold reaches only the first column's cells (and any link
        // in them, which takes the cell's weight), and the queue's own stylesheet leaves it to the shared style
        foreach ($rules as $rule) {
            if (strpos($rule['selector'], 'domainmod-dense-table') === false || stripos($rule['body'], 'font-weight') === false) continue;
            $this->assertContains($rule['selector'], array('.domainmod-dense-table > thead > tr > th',
                '.domainmod-dense-table > tbody > tr > td:first-child', '.domainmod-dense-table > tbody > tr > td.dt-empty'));
        }
        foreach ($this->rules('/css/datatables/queue-main.css') as $rule) {
            $this->assertStringNotContainsStringIgnoringCase('font-weight', $rule['body'], $rule['selector']);
        }
    }

    // The queue's tables use the style, without Responsive and its details column, and each DataTable keeps its own
    // box around the table alone
    public function testTheImportQueueTablesUseTheSharedStyle()
    {
        $settings = file_get_contents($this->root() . '/_includes/settings/queue-main.inc.php');
        $this->assertStringContainsString("\$datatable_class = 'table domainmod-dense-table';", $settings);
        $this->assertStringNotContainsString('"responsive"', $settings);
        $this->assertStringNotContainsString('"control"', $settings);
        $this->assertSame(3, substr_count($settings, 'domainmodTable('));
        $this->assertSame(3, substr_count($settings, '"dom": \\\'<"top"lif>r<"domainmod-list-scroll"t><"bottom"ip><"clear">\\\','));
        // The dates are compared as text, so they stay at the start, and Delete is centred
        $this->assertSame(3, preg_match_all('/type: "string",\s+targets: +"\.domainmod-col-date"/', $settings));
        $this->assertSame(3, preg_match_all('/className: "dt-center",\s+targets: +"\.domainmod-col-delete"/', $settings));

        $page = file_get_contents($this->root() . '/queue/index.php');
        foreach (self::QUEUE_TABLES as $suffix => $name) {
            $box_then_table = '#<div class="domainmod-list-scroll" tabindex="0" role="region" aria-label="<\?php echo '
                . 'htmlspecialchars\(_\(\'' . preg_quote($name, '#') . '\'\), ENT_QUOTES, \'UTF-8\'\); \?>">\n\s*'
                . '<table id="<\?php echo \$slug; \?>-' . $suffix . '" class="<\?php echo \$datatable_class; \?>">#';
            $this->assertMatchesRegularExpression($box_then_table, $page, $suffix);
        }
        // No row keeps the details column's empty cell, so each row has as many cells as its table has headings
        $this->assertStringNotContainsString("_('Details')", $page);
        $this->assertSame(0, preg_match('#<td>\s*</td>#', $page));
        $this->assertStringNotContainsString("_('Date Added')", $page);
        $this->assertSame(3, substr_count($page, 'class="domainmod-col-delete"'));
    }

    // Each table's headings in order, from its <table> to its </thead>
    protected function headings($page, $suffix)
    {
        $start = strpos($page, '<table id="<?php echo $slug; ?>-' . $suffix . '"');
        $this->assertNotFalse($start, $suffix);
        $head = substr($page, $start, strpos($page, '</thead>', $start) - $start);
        preg_match_all("#<th[^>]*><\\?php echo _\\('([^']+)'\\); \\?></th>#", $head, $matches);
        return $matches[1];
    }

    // Each row's cells, from the table's <tbody> to its </tbody>
    protected function body($page, $suffix)
    {
        $start = strpos($page, '<table id="<?php echo $slug; ?>-' . $suffix . '"');
        $from = strpos($page, '<tbody>', $start);
        return substr($page, $from, strpos($page, '</tbody>', $from) - $from);
    }

    // As in the Domains list, each item's name is the pinned first column, so it stays in view as the box scrolls, and
    // its status comes straight after it
    public function testTheImportQueueTablesPinTheNameThenTheStatus()
    {
        $page = file_get_contents($this->root() . '/queue/index.php');
        $this->assertSame(array('Registrar Account', 'Status', 'Domain Count', 'Added By', 'Added', 'Delete'), $this->headings($page, 'lists'));
        $this->assertSame(array('Domain', 'Status', 'Registrar Account', 'Expiry Date', 'DNS', 'IP', 'Added By', 'Added', 'Delete'),
            $this->headings($page, 'domains'));
        $this->assertSame(array('Host', 'Status', 'Domain', 'Expiry Date', 'Issuer', 'Added By', 'Added', 'Delete'), $this->headings($page, 'hosts'));

        // The rows' cells are in the same order: the name first, then the status
        $this->assertMatchesRegularExpression('#<tr>\s*<td>\s*<\?php echo \$row_lists->registrar_name; \?>.*?</td>\s*<td>\s*<\?php\s*if \(\$row_lists->finished == \'1\'\)#s',
            $this->body($page, 'lists'));
        $this->assertMatchesRegularExpression('#<tr>\s*<td>\s*<\?php echo \$row_domains->domain; \?>\s*</td>\s*<td><div class="domainmod-queue-status">#',
            $this->body($page, 'domains'));
        $this->assertMatchesRegularExpression('#<tr>\s*<td><\?php\s*if \(\$linked\) \{.*?</td>\s*<td><div class="domainmod-queue-status">#s',
            $this->body($page, 'hosts'));

        // The tables that start sorted still sort by Added, newest first, and the Domain Queue keeps the page's order
        $settings = file_get_contents($this->root() . '/_includes/settings/queue-main.inc.php');
        preg_match_all('/"order": (\[[^\n]*\]),/', $settings, $orders);
        $this->assertSame(array('[[ 4, "desc" ]]', '[]', '[[ 6, "desc" ]]'), $orders[1]);
        $this->assertSame(4, array_search('Added', $this->headings($page, 'lists'), true));
        $this->assertSame(6, array_search('Added', $this->headings($page, 'hosts'), true));

        // A long status wraps in both queues' tables, and the pinned name wraps through the shared style
        $status = ':is(#queue-main-domains, #queue-main-hosts) .domainmod-queue-status';
        $queue_rules = $this->rules('/css/datatables/queue-main.css');
        $this->assertSame('normal', $this->value($queue_rules, $status, 'white-space'));
        $this->assertSame('anywhere', $this->value($queue_rules, $status, 'overflow-wrap'));
        $this->assertSame('13rem', $this->value($queue_rules, $status, 'max-width'));
        $this->assertStringNotContainsString('domainmod-queue-host', file_get_contents($this->root() . '/css/datatables/queue-main.css') . $page);
        $this->assertSame('anywhere', $this->value($this->rules(), '.domainmod-dense-table > tbody > tr > td:first-child', 'overflow-wrap'));
    }

    // The exports' headings use the page's words for the same things
    public function testTheQueueExportHeadingsMatchThePage()
    {
        $page = file_get_contents($this->root() . '/queue/index.php');
        $this->assertStringNotContainsString("_('Already in Queue')", $page);
        $this->assertMatchesRegularExpression("/_\\('Already in Domains'\\),\\s+_\\('Already in Domain Queue'\\),\\s+_\\('Invalid Domain'\\),/", $page);

        $ssl = file_get_contents($this->root() . '/queue/ssl/index.php');
        $this->assertStringNotContainsString("_('Date Added')", $ssl);
        $this->assertMatchesRegularExpression("/_\\('Added By'\\),\\s+_\\('Added'\\)\\s+\\);/", $ssl);
    }

    public function testDomainmodTableSwapsThePageBoxForDataTablesBox()
    {
        $script = file_get_contents($this->root() . '/js/datatables.js');
        $this->assertStringContainsString("table.classList.contains('domainmod-dense-table')", $script);
        $this->assertStringContainsString("table.parentNode.classList.contains('domainmod-list-scroll')", $script);
        // Only once DataTables has put the table in a box of its own
        $this->assertStringContainsString("if (page_box && box !== page_box && box.classList.contains('domainmod-list-scroll')) {", $script);
        $this->assertStringContainsString("['tabindex', 'role', 'aria-label'].forEach(function (name) {", $script);
        $this->assertStringContainsString('if (page_box.hasAttribute(name)) box.setAttribute(name, page_box.getAttribute(name));', $script);
        $this->assertStringContainsString('page_box.replaceWith.apply(page_box', $script);
        // A draw with no matches scrolls back to the start, where the message is
        $this->assertMatchesRegularExpression('/api\.on\(\'draw\', function \(\) \{\s+'
            . 'if \(api\.page\.info\(\)\.recordsDisplay === 0\) box\.scrollLeft = 0;/', $script);
    }

    // "Imported" on the page and in the export, and "Already in SSL Queue" beside the Domain Queue's "Already in Domain
    // Queue"
    public function testTheQueueStatusWording()
    {
        $page = file_get_contents($this->root() . '/queue/index.php');
        $this->assertStringNotContainsString('Successfully Imported', $page);
        $this->assertSame(1, substr_count($page, "\$export_processing = _('Imported');"));
        $this->assertSame(1, substr_count($page, "echo \$layout->highlightText('green', _('Imported'));"));
        $this->assertSame(2, substr_count($page, "_('Imported (Edited Manually)')"));

        $class = file_get_contents($this->root() . '/classes/DomainMOD/SslQueue.php');
        $this->assertStringNotContainsString('Already in the Queue', $class);
        $this->assertStringContainsString("\$label = _('Already in SSL Queue');", $class);
    }

} //@formatter:on
