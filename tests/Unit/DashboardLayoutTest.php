<?php
/**
 * /tests/Unit/DashboardLayoutTest.php
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
//@formatter:off
namespace DomainMOD\Tests\Unit;

use DomainMOD\WatchList;
use PHPUnit\Framework\TestCase;

/*
 * The Dashboard's layout F (Greg's choice, 2026-11-21), top to bottom: the totals strip, Needs Attention as sentences
 * with hints, the Coming Up table (beside Needs Attention on extra-large screens), Pending and Import Queue as pills
 * side by side, and the renewal cost chart at the bottom. The old card rows and Dashboard::displayExpPanel() are gone.
 */
class DashboardLayoutTest extends TestCase
{
    protected function source($path = 'dashboard/index.php')
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testTheBlocksAreInOrder()
    {
        $source = $this->source();
        $parts = array("\$totals[] = array('/domains/index.php?is_active=LIVE'", "echo \$dashboard->gettingStarted();",
            "<?php echo _('Needs Attention'); ?>", "<?php echo _('Coming Up'); ?>", "array('pending', _('Pending'), \$pending)",
            "array('import-queue', _('Import Queue'), \$import_queue)", "\$chart->stackedMonthBars('dashboard-renewal-costs'",
            "require_once DIR_INC . '/layout/footer.inc.php';");
        $positions = array();
        foreach ($parts as $part) {
            $this->assertSame(1, substr_count($source, $part), $part);
            $positions[] = strpos($source, $part);
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        // The old card rows and sections are gone
        foreach (array('displayPanel(', 'displayExpPanel(', "\$section = _('Pending Domains');", 'Expiring in the next %s days',
            'domainmod-dashboard-summary') as $old) {
            $this->assertStringNotContainsString($old, $source, $old);
        }
    }

    // The totals strip (Greg's choice, 2026-11-21): Domains, Hosting Services, SSL Certificates, then Sold Domains, each
    // an icon, a number, and a label that agrees with it and links to its list. The hosting total counts every live
    // service and shows once any service exists, and Sold Domains shows only when there are some.
    public function testTheTotalsStrip()
    {
        $source = $this->source();
        $this->assertStringContainsString("\$total_live_hosting = DomainMOD\\Dashboard::countOf(\$hosting_counts, array(1, 2, 3, 4, 5, 6, 7, 8, 9));", $source);
        // Hosting's cell shows when there are services in any status (cancelled ones too), and the counts are only read
        // once the database update has added the hosting tables
        $this->assertStringContainsString('$hosting_available = DomainMOD\Hosting::isAvailable($pdo);', $source);
        $this->assertStringContainsString("\$hosting_counts = \$hosting_available ? DomainMOD\\Dashboard::statusCounts(\$pdo, 'hosting_services') : array();", $source);
        $this->assertStringContainsString('$total_hosting = array_sum($hosting_counts);', $source);
        $totals = array("\$totals[] = array('/domains/index.php?is_active=LIVE', 'bi-globe2', number_format(\$total_live_domains), ngettext('Domain', 'Domains', \$total_live_domains),\n    sprintf(ngettext('%s Domain', '%s Domains', \$total_live_domains), number_format(\$total_live_domains)));",
            "if (\$hosting_available) {\n    \$totals[] = array('/hosting/index.php?is_active=LIVE', 'bi-hdd-rack', number_format(\$total_live_hosting), ngettext('Hosting Service', 'Hosting Services', \$total_live_hosting),\n        sprintf(ngettext('%s Hosting Service', '%s Hosting Services', \$total_live_hosting), number_format(\$total_live_hosting)));\n}",
            "\$totals[] = array('/ssl/index.php?is_active=LIVE', 'bi-shield-lock', number_format(\$total_live_ssl), ngettext('SSL Certificate', 'SSL Certificates', \$total_live_ssl),\n    sprintf(ngettext('%s SSL Certificate', '%s SSL Certificates', \$total_live_ssl), number_format(\$total_live_ssl)));",
            "if (\$total_sold_domains) {\n    \$totals[] = array('/domains/index.php?is_active=10', 'bi-cart', number_format(\$total_sold_domains), ngettext('Sold Domain', 'Sold Domains', \$total_sold_domains),\n        sprintf(ngettext('%s Sold Domain', '%s Sold Domains', \$total_sold_domains), number_format(\$total_sold_domains)));\n}");
        $positions = array();
        foreach ($totals as $total) {
            $this->assertSame(1, substr_count($source, $total), $total);
            $positions[] = strpos($source, $total);
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        // The strip's markup, as before layout F, under a heading for screen readers, with each total read once, as its
        // link's translated phrase, in the order above, or the Getting Started checklist instead
        $strip = substr($source, strpos($source, 'if (!$show_getting_started) { ?>'));
        $strip = substr($strip, 0, strpos($strip, "} else {\n    // The Getting Started checklist, while nothing has been added\n    echo \$dashboard->gettingStarted();\n}"));
        $this->assertStringContainsString('foreach ($totals as $total) { ?>', $strip);
        foreach (array("<h2 class=\"visually-hidden\"><?php echo _('Totals'); ?></h2>",
            '<div class="card domainmod-dashboard-totals d-inline-block mw-100 mb-0">',
            '<div class="d-flex flex-column flex-sm-row">',
            '<div class="domainmod-dashboard-total position-relative d-flex align-items-center gap-3 px-4 py-2">',
            '<i class="bi <?php echo $total[1]; ?> fs-4" aria-hidden="true"></i>',
            '<div class="fs-5 fw-semibold lh-1" aria-hidden="true"><?php echo $total[2]; ?></div>',
            '<a href="<?php echo WEB_ROOT . $total[0]; ?>" class="stretched-link text-body-secondary link-underline-opacity-0 small"><span class="visually-hidden"><?php echo $total[4]; ?></span><span aria-hidden="true"><?php echo $total[3]; ?></span></a>') as $part) {
            $this->assertStringContainsString($part, $strip, $part);
        }
    }

    // Needs Attention: At Risk, hosting renewals to confirm, then the Watch List, each linking to its list, always shown,
    // with "Nothing needs attention." when none has a count
    public function testTheNeedsAttentionItems()
    {
        $source = $this->source();
        $needs = substr($source, strpos($source, '$attention = array();'));
        $needs = substr($needs, 0, strpos($needs, '$expiration_days = (int) $pdo->query("'));
        preg_match_all("/ngettext\\('(%s [^']+)'/", $needs, $matches);
        $this->assertSame(array('%s domain is at risk', '%s hosting renewal to confirm', '%s watched domain is about to drop', '%s item'), $matches[1]);
        // The hints don't show the count, so each picks its sentence by the count, not through ngettext(), apart from At
        // Risk's, one short phrase for a half-width column that doesn't call privacy a reason
        foreach (array('$confirm_count_hosting === 1', '$watch_count_pending_delete === 1') as $pick) {
            $this->assertStringContainsString($pick, $needs, $pick);
        }
        $this->assertStringContainsString("sprintf(_('Overdue, or expiring in the next %s days with auto-renewal off, the transfer lock off, or a registry hold or deletion.'),", $needs);
        $this->assertStringNotContainsString('privacy', $needs);
        preg_match_all("#'bi-[a-z-]+', '(/[^']+)'\\);#", $needs, $links);
        $this->assertSame(array('/reporting/domains/at-risk.php', '/reporting/hosting/at-risk.php?show=confirm#hosting-list',
            '/watch/?stage=pending_delete'), $links[1]);
        $this->assertStringContainsString("<?php echo _('Nothing needs attention.'); ?>", $needs);
        $this->assertStringContainsString('<i class="bi bi-check-circle" aria-hidden="true"></i>', $needs);
        // The block isn't wrapped in a condition: only its list or its empty line is
        $this->assertStringContainsString("} ?>\n<div class=\"row g-4 mt-0\">\n    <div class=\"col-xl\">\n        <section class=\"card card-outline card-danger domainmod-dashboard-block h-100 mb-0\" aria-labelledby=\"domainmod-attention-heading\">", $needs);
        // The sentence is the link, covering the row, described by its hint
        $this->assertStringContainsString('class="stretched-link link-underline-opacity-0 link-underline-opacity-75-hover" aria-describedby="domainmod-attention-hint-<?php echo $index; ?>"', $needs);
        $this->assertStringContainsString('DomainMOD\\Reporting::AT_RISK_DAYS),', $needs);
    }

    // Two columns on extra-large screens (Greg's choice, 2026-11-21): Needs Attention and Coming Up share a row, and
    // Pending and Import Queue the next, each block an equal-width column (col-xl, or col-md for the pills, as before)
    // that's the whole row below it, or when it's the row's only block. The blocks are as tall as the row, and the
    // rows' gutters space them, so the blocks have no margins of their own. The totals strip and the chart stay
    // outside the rows, at full width.
    public function testTheTwoColumnRows()
    {
        $source = $this->source();
        $first = substr($source, strpos($source, '<div class="row g-4 mt-0">'));
        $first = substr($first, 0, strpos($first, '$pending = array('));
        $this->assertSame(2, substr_count($first, '<div class="col-xl">'));
        $this->assertStringContainsString("if (\$coming_up_total) { ?>\n    <div class=\"col-xl\">\n        <section class=\"card card-outline card-danger domainmod-dashboard-block h-100 mb-0\" aria-labelledby=\"domainmod-coming-up-heading\">", $first);
        // The row closes after Coming Up, whether or not it's shown
        $this->assertStringContainsString("        </section>\n    </div><?php\n} ?>\n</div><?php", $first);
        // Both columns are the row's own children: inside it, only the row's opening and closing tags start a line
        // unindented, and only the two columns' tags are indented one step
        $row = substr($first, 0, strpos($first, "} ?>\n</div><?php") + strlen("} ?>\n</div>"));
        $this->assertSame(2, preg_match_all('#^</?div#m', $row));
        $this->assertSame(2, preg_match_all('#^    <div class="col-xl">$#m', $row));
        $this->assertSame(2, preg_match_all('#^    </div>#m', $row));
        $this->assertSame(0, preg_match_all('#^    <(?!div class="col-xl">$|/div>)[a-z]#m', $row));
        $this->assertSame(2, substr_count($source, '<div class="row g-4 mt-0">'));
        // Needs Attention, Coming Up, and the pills' block (one template for both)
        $this->assertSame(3, substr_count($source, 'domainmod-dashboard-block h-100 mb-0'));
        $this->assertStringNotContainsString('domainmod-dashboard-block mt-4', $source);
        // The totals strip comes before the first row, and the chart after the second
        $this->assertLessThan(strpos($source, '<div class="row g-4 mt-0">'), strpos($source, '<div class="card domainmod-dashboard-totals'));
        $this->assertGreaterThan(strrpos($source, '<div class="row g-4 mt-0">'), strpos($source, "\$chart->stackedMonthBars('dashboard-renewal-costs'"));
    }

    // Coming Up's rows are tinted on hover, and while a count has focus, as Needs Attention's are, with the same theme
    // colour, which dark mode redefines, so both modes match
    public function testComingUpRowsHighlight()
    {
        $css = $this->source('css/base.css');
        $this->assertStringContainsString(".domainmod-attention-item:hover,\n.domainmod-attention-item:focus-within {\n    background-color: var(--bs-tertiary-bg);\n}", $css);
        $this->assertStringContainsString(".domainmod-coming-up > tbody > tr:hover > *,\n.domainmod-coming-up > tbody > tr:focus-within > * {\n    background-color: var(--bs-tertiary-bg);\n}", $css);
        // After the rule that clears the cells' background, so it wins at the same place in the cascade, and it only
        // sets a background, leaving the counts' focus outlines alone
        $this->assertGreaterThan(strpos($css, ".domainmod-coming-up > :not(caption) > * > * {"), strpos($css, '.domainmod-coming-up > tbody > tr:hover > *'));
    }

    // The Watch List item: watched domains in the pending delete stage, as the Watch List's stage filter lists them
    public function testTheWatchListItem()
    {
        $source = $this->source();
        $this->assertStringContainsString("\$watch_filter = \$watch_list->filterParts(DomainMOD\\WatchList::normalizeState(array('stage' => 'pending_delete')));", $source);
        $this->assertStringContainsString("\$watch_count_pending_delete = \$watch_list->count(\$watch_filter['sql'], \$watch_filter['params']);", $source);
        $state = WatchList::normalizeState(array('stage' => 'pending_delete'));
        $this->assertSame('pending_delete', $state['stage']);
        $this->assertSame('hide', $state['archived']);
    }

    // Coming Up: a header row of windows and a row per type, each with its chart colour, Hosting's only when hosting is
    // tracked, counts linking to the list filtered to the window, and zeros muted and unlinked
    public function testTheComingUpTable()
    {
        $source = $this->source();
        $table = substr($source, strpos($source, '$coming_up_windows = DomainMOD\\Dashboard::comingUpWindows($expiration_days);'));
        $table = substr($table, 0, strpos($table, '$pending = array('));
        $this->assertStringContainsString("if (\$total_hosting) {\n    \$coming_up[] = array(_('Hosting Services'), 'hosting', '/hosting/index.php',", $table);
        $this->assertStringContainsString('<th scope="col"><span class="visually-hidden"><?php echo _(\'Type\'); ?></span></th>', $table);
        $this->assertStringContainsString("<th scope=\"col\"><?php echo sprintf(ngettext('%s day', '%s days', \$window), number_format(\$window)); ?></th>", $table);
        $this->assertStringContainsString('<th scope="row"><span class="domainmod-type-dot domainmod-type-dot-<?php echo $row[1]; ?>" aria-hidden="true"></span>', $table);
        $this->assertStringContainsString("\$url = \$row[2] . '?daterange=' . urlencode(DomainMOD\\Dashboard::DUE_FROM . ' - ' . \$row[4][\$window]);", $table);
        $this->assertStringContainsString('<td class="domainmod-coming-up-zero">0</td>', $table);
        // Each count's link names its count, type, and window for screen readers, each part its own translated plural
        $this->assertStringContainsString("<span aria-hidden=\"true\"><?php echo number_format(\$count); ?></span><span class=\"visually-hidden\"><?php echo sprintf(_('%1\$s, %2\$s'), \$row[5](\$count), sprintf(ngettext('due within %s day', 'due within %s days', \$window), number_format(\$window))); ?></span>", $table);
        foreach (array("ngettext('%s domain', '%s domains', \$count)", "ngettext('%s hosting service', '%s hosting services', \$count)",
            "ngettext('%s SSL certificate', '%s SSL certificates', \$count)") as $name) {
            $this->assertStringContainsString($name, $table, $name);
        }
        // Domains and SSL certificates count and link to the user's date plus each window, and hosting services to the
        // hosting date plus each window
        $this->assertStringContainsString("DomainMOD\\Dashboard::dueCounts(\$pdo, 'domains', \$coming_up_ends), \$coming_up_ends,", $table);
        $this->assertStringContainsString("DomainMOD\\Dashboard::dueCounts(\$pdo, 'hosting', \$hosting_ends), \$hosting_ends,", $table);
        $this->assertStringContainsString("DomainMOD\\Dashboard::dueCounts(\$pdo, 'ssl', \$coming_up_ends), \$coming_up_ends,", $table);
        $this->assertStringContainsString('if ($coming_up_total) { ?>', $table);
    }

    // Pending's pills, Domains, Hosting, then SSL Certificates, and Import Queue's, each linking where the old tiles did
    public function testThePills()
    {
        $source = $this->source();
        preg_match_all("/array\\(_\\('([^']+)'\\), (\\\$[a-z_]+(?:\\[\\d\\] \\?\\? 0)?), '([^']+)'\\)/", $source, $matches, PREG_SET_ORDER);
        $pills = array();
        foreach ($matches as $match) $pills[$match[1]] = $match[2] . ' ' . $match[3];
        $this->assertSame(array(
            'Domain renewals' => '$domain_counts[3] ?? 0 /domains/index.php?is_active=3',
            'Domain registrations' => '$domain_counts[5] ?? 0 /domains/index.php?is_active=5',
            'Domain transfers' => '$domain_counts[2] ?? 0 /domains/index.php?is_active=2',
            'Domain other' => '$domain_counts[4] ?? 0 /domains/index.php?is_active=4',
            'Hosting renewals' => '$hosting_counts[3] ?? 0 /hosting/index.php?is_active=3',
            'Hosting setup' => '$hosting_counts[5] ?? 0 /hosting/index.php?is_active=5',
            'Hosting other' => '$hosting_counts[4] ?? 0 /hosting/index.php?is_active=4',
            'SSL renewals' => '$ssl_counts[3] ?? 0 /ssl/index.php?is_active=3',
            'SSL registrations' => '$ssl_counts[5] ?? 0 /ssl/index.php?is_active=5',
            'SSL other' => '$ssl_counts[4] ?? 0 /ssl/index.php?is_active=4',
            'Domains pending' => '$total_count_pending /queue/', 'Domains processing' => '$total_count_processing /queue/',
            'Domains finished' => '$total_count_finished /queue/', 'SSL waiting' => '$total_count_ssl_queue /queue/'), $pills);
        // Only pills with a count, and only blocks with a pill
        $this->assertStringContainsString('$pills = array_values(array_filter($block[2], function ($pill) { return $pill[1] > 0; }));', $source);
        $this->assertStringContainsString('if ($pills) $pill_blocks[] = array($block[0], $block[1], $pills);', $source);
        $this->assertStringContainsString('<div class="col-md">', $source);
    }

    // The chart is last, with its own card title as its heading, its figures table, and the Overdue column
    public function testTheChartIsAtTheBottom()
    {
        $source = $this->source();
        $chart = substr($source, strpos($source, 'if ($renewal_rows || $renewal_cert_rows || $renewal_hosting_months) {'));
        $this->assertStringContainsString("<summary><?php echo _('Show the figures'); ?></summary>", $chart);
        $this->assertStringContainsString("\$renewal_series, !empty(\$renewal_months[0]['overdue']), \$after);", $chart);
        $this->assertStringNotContainsString('<h2 class="h4', $chart);
        $this->assertStringContainsString('$footer_no_gap = true;', $chart);
    }

    // The helpers and styles only the old Dashboard used are gone, while displayPanel() stays for the reports and the
    // totals strip keeps its styles
    public function testTheOldHelpersAreGone()
    {
        $class = $this->source('classes/DomainMOD/Dashboard.php');
        $this->assertStringNotContainsString('function displayExpPanel(', $class);
        $this->assertStringContainsString('public function displayPanel(', $class);
        $css = $this->source('css/base.css');
        $this->assertStringNotContainsString('.domainmod-dashboard-summary', $css);
        // The strip's cells keep their dividers and their focus ring
        $this->assertStringContainsString(".domainmod-dashboard-total + .domainmod-dashboard-total {\n    border-top: 1px solid var(--bs-border-color);\n}", $css);
        $this->assertStringContainsString("@media (min-width: 576px) {\n    .domainmod-dashboard-total + .domainmod-dashboard-total {\n        border-top: 0;\n        border-inline-start: 1px solid var(--bs-border-color);\n    }\n}", $css);
        $this->assertStringContainsString(".domainmod-dashboard-total:focus-within {\n    outline: 2px solid var(--bs-focus-ring-color, var(--bs-primary));\n    outline-offset: -2px;\n}", $css);
        foreach (array('.domainmod-dashboard-total + .domainmod-dashboard-total {', '.domainmod-dashboard-total:focus-within {',
            '.domainmod-attention-item {', '.domainmod-coming-up {',
            '.domainmod-type-dot-domains {', '.domainmod-dashboard-pill {') as $rule) {
            $this->assertStringContainsString($rule, $css, $rule);
        }
        $this->assertStringContainsString("background-color: var(--domainmod-chart-hosting);", $css);
    }

} //@formatter:on
