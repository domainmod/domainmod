<?php
/**
 * /tests/Unit/TypeOrderTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Wherever domains, hosting, and SSL certificates appear together, they're in that order: Domains, then Hosting, then SSL
 * Certificates. This checks the main places, the sidebar, the Dashboard, Reporting, and the expiration email, by where
 * each part appears in the source.
 */
class TypeOrderTest extends TestCase
{
    protected function source($path)
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public static function orders()
    {
        return array(
            'sidebar items' => array('_includes/layout/menu-main.inc.php', array(
                '<?php echo $web_root; ?>/domains/"', '<?php echo $web_root; ?>/hosting/"', '<?php echo $web_root; ?>/ssl/"')),
            'sidebar asset groups' => array('_includes/layout/menu-main.inc.php', array(
                "<li class=\"nav-header\"><?php echo _('Domains') ?></li>", "<li class=\"nav-header\"><?php echo _('Hosting') ?></li>",
                "<li class=\"nav-header\"><?php echo _('SSL Certificates') ?></li>", "<li class=\"nav-header\"><?php echo _('Shared') ?></li>")),
            'sidebar custom fields' => array('_includes/layout/menu-main.inc.php', array(
                '/admin/domain-fields/"', '/admin/hosting-fields/"', '/admin/ssl-fields/"')),
            'dashboard totals' => array('dashboard/index.php', array(
                'number_format($total_live_domains)', 'number_format($total_active_hosting)', 'number_format($total_live_ssl)',
                'number_format($total_sold_domains)')),
            'dashboard expiring' => array('dashboard/index.php', array(
                "displayExpPanel(_('Domains')", "displayExpPanel(_('Hosting Services')", "displayExpPanel(_('SSL Certificates')")),
            'dashboard pending' => array('dashboard/index.php', array(
                "_('Pending Domains')", "_('Pending Hosting Services')", "_('Pending SSL Certificates')")),
            'dashboard chart series' => array('dashboard/index.php', array(
                "array_column(array_column(\$renewal_months, 'domains'), 'cost')",
                "array_column(array_column(\$renewal_months, 'hosting'), 'cost')",
                "array_column(array_column(\$renewal_months, 'certs'), 'cost')")),
            'reporting renewals' => array('reporting/index.php', array(
                '/reporting/domains/upcoming-renewals.php', '/reporting/hosting/upcoming-renewals.php', '/reporting/ssl/upcoming-renewals.php')),
            'reporting fees' => array('reporting/index.php', array(
                '/reporting/domains/registrar-fees.php', '/reporting/hosting/provider-fees.php', '/reporting/ssl/provider-fees.php')),
            'reporting at risk' => array('reporting/index.php', array(
                '/reporting/domains/at-risk.php', '/reporting/hosting/at-risk.php', '/reporting/ssl/at-risk.php')),
            'expiration email' => array('classes/DomainMOD/Email.php', array(
                '$this->showDomainsHtml(', '$this->showHostingHtml(', '$this->showSslHtml(',
                '$this->showDomainsText(', '$this->showHostingText(', '$this->showSslText(')),
            'reporting costs' => array('reporting/index.php', array(
                "landingLinks(_('Domain Costs by:')", "landingLinks(_('Hosting Costs by:')", "landingLinks(_('SSL Costs by:')")),
        );
    }

    /*
     * The sidebar's main items: the three types, then the Watch List right after SSL Certificates, the Import Queue, the
     * Bulk Updater, Segments, Reporting, Change History, and Assets
     */
    public function testTheSidebarsMainItemsAreInOrder()
    {
        $source = $this->source('_includes/layout/menu-main.inc.php');
        preg_match_all('#<a href="<\?php echo \$web_root; \?>/([a-z]*)/?" class="nav-link#', $source, $matches);
        $this->assertSame(array('', 'domains', 'hosting', 'ssl', 'watch', 'queue', 'bulk', 'segments', 'reporting', 'history'),
            array_slice($matches[1], 0, 10));
        $this->assertLessThan(strpos($source, "aria-label=\"<?php echo _('Assets'); ?>\""), strpos($source, '/history/"'));
    }

    #[DataProvider('orders')]
    public function testTheTypesAreInOrder($path, $parts)
    {
        $source = $this->source($path);
        $positions = array();
        foreach ($parts as $part) {
            $this->assertSame(1, substr_count($source, $part), $part);
            $positions[] = strpos($source, $part);
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

} //@formatter:on
