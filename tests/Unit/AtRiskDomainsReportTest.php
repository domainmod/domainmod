<?php
/**
 * /tests/Unit/AtRiskDomainsReportTest.php
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
 * The At-Risk Domains report (Greg's choices, 2026-11-21). All lists only the at-risk domains. Privacy isn't one of the
 * reasons its tiles count, and the report doesn't mention privacy at all (no tile, list, column, or note, on the page or
 * in the export), so an old show=privacy link falls back to All. The export follows the list shown and names it. The
 * intros and the Reporting page's tile no longer call privacy a reason.
 */
class AtRiskDomainsReportTest extends TestCase
{
    protected function source($path = 'reporting/domains/at-risk.php')
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testTheReportDoesntMentionPrivacy()
    {
        $source = $this->source();
        $this->assertSame(0, preg_match('/privacy/i', $source));
        // Only the listed filters are kept, so show=privacy, like any other unknown value, shows All
        $this->assertStringContainsString("\$show_values = array('all', 'overdue', 'autorenew', 'nofee');", $source);
        $this->assertStringContainsString("if (!in_array(\$show, \$show_values, true)) \$show = 'all';", $source);
        $this->assertFalse(method_exists('DomainMOD\Reporting', 'privacyOffDomains'));
    }

    public function testTheListAndItsReasons()
    {
        $source = $this->source();
        $conditions = substr($source, strpos($source, '$conditions = array('));
        $conditions = substr($conditions, 0, strpos($conditions, '$counts = array();'));
        $this->assertStringContainsString("'autorenew' => function", $conditions);
        $this->assertStringContainsString('$at_risk = $reporting->atRiskDomains($reporting->renewalDomainsWithStatus($time->userTodayPlusDays($risk_days + 1, $today)), $today, $risk_end);', $source);
        $this->assertStringContainsString("\$list = \$show === 'all' ? \$at_risk : array_values(array_filter(\$at_risk, \$conditions[\$show]));", $source);
        $this->assertStringContainsString("\$list_heading = \$show === 'all' ? _('All At-Risk Domains') : \$reasons[\$show][0];", $source);
        $this->assertStringContainsString('<?php echo $layout->calloutTop(); ?>' . "\n" . '<?php echo $intro; ?>' . "\n" . '<?php echo $layout->calloutBottom(); ?>', $source);
        // The export names its list and writes the list shown, from the Export Report action, which keeps the page's filter
        $export = substr($source, strpos($source, '$export_file = $export->openFile('));
        $export = substr($export, 0, strpos($export, '$export->closeFile($export_file);'));
        $this->assertStringContainsString("\$row_contents = array(\$list_heading);", $export);
        $this->assertStringContainsString('foreach ($list as $row) {', $export);
        $this->assertStringNotContainsString('foreach ($at_risk as $row) {', $export);
        $this->assertStringContainsString("if (\$list) \$layout->addPageAction(_('Export Report'), \$report_filename . '?export_data=1&show=' . \$show, 'bi-download');", $source);
        // The table and the export have the same columns
        preg_match_all("#<th><\?php echo _\('([^']+)'\); \?></th>#", $source, $headings);
        $this->assertSame(array('Domain', 'Reasons', 'Expiry Date', 'Days Left', 'Registrar', 'Account', 'Auto-Renewal', 'Renewal Cost'), $headings[1]);
        preg_match_all("#^        _\('([^']+)'\),?$#m", $export, $columns);
        $this->assertSame(array('Domain', 'Expiry Date', 'Days Left', 'Registrar', 'Account', 'Auto-Renewal', 'Renewal Cost', 'Reasons'), $columns[1]);
    }

    public function testTheTextsDontCallPrivacyAReason()
    {
        $source = $this->source();
        $this->assertStringContainsString('have auto-renewal or the transfer lock turned off', $source);
        $this->assertStringContainsString('with auto-renewal off, plus any past their expiry date', $source);
        $index = $this->source('reporting/index.php');
        $this->assertStringNotContainsString('without auto-renewal or privacy', $index);
        $this->assertStringContainsString("_('Overdue, or expiring in the next %s days with auto-renewal off, the transfer lock off, or a registry hold or deletion')", $index);
    }

} //@formatter:on
