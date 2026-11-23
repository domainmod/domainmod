<?php
/**
 * /tests/Unit/BreadcrumbsTest.php
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
 * Every page with the full layout shows a breadcrumb trail. The page loads its settings file
 * (_includes/settings/<slug>.inc.php), which names the trail's file (_includes/breadcrumbs/<slug>.inc.php) with $slug.
 * The trail starts at the Dashboard (_includes/layout/breadcrumbs.inc.php), follows the sidebar menu's sections, and
 * ends with the page's own title, not linked.
 */
class BreadcrumbsTest extends TestCase
{
    // Each page with the full layout, and the settings file (and so the trail) meant for it. A page copied from a
    // sibling that kept the sibling's settings file fails here.
    const PAGES = array(
            'admin/backup/index.php'                      => 'admin-backup-main',
            'admin/debug-log/index.php'                   => 'admin-debug-log-main',
            'admin/defaults/index.php'                    => 'admin-defaults',
            'admin/domain-fields/add.php'                 => 'admin-add-custom-domain-field',
            'admin/domain-fields/edit.php'                => 'admin-edit-custom-domain-field',
            'admin/domain-fields/index.php'               => 'admin-custom-domain-fields',
            'admin/dw/add-server.php'                     => 'dw-add-server',
            'admin/dw/dw.php'                             => 'dw-main',
            'admin/dw/edit-server.php'                    => 'dw-edit-server',
            'admin/dw/intro.php'                          => 'dw-intro',
            'admin/dw/list-accounts.php'                  => 'dw-list-accounts',
            'admin/dw/list-zones.php'                     => 'dw-list-zones',
            'admin/dw/servers.php'                        => 'dw-servers',
            'admin/hosting-fields/add.php'                => 'admin-add-custom-hosting-field',
            'admin/hosting-fields/edit.php'               => 'admin-edit-custom-hosting-field',
            'admin/hosting-fields/index.php'              => 'admin-custom-hosting-fields',
            'admin/index.php'                             => 'admin-main',
            'admin/info/index.php'                        => 'admin-info',
            'admin/maintenance/index.php'                 => 'admin-maintenance-main',
            'admin/scheduler/index.php'                   => 'admin-scheduler-main',
            'admin/settings/index.php'                    => 'admin-settings',
            'admin/ssl-fields/add.php'                    => 'admin-add-custom-ssl-field',
            'admin/ssl-fields/edit.php'                   => 'admin-edit-custom-ssl-field',
            'admin/ssl-fields/index.php'                  => 'admin-custom-ssl-fields',
            'admin/tools/index.php'                       => 'admin-tools-main',
            'admin/upgrade/index.php'                     => 'admin-upgrade-main',
            'admin/users/add.php'                         => 'admin-users-add',
            'admin/users/edit.php'                        => 'admin-users-edit',
            'admin/users/index.php'                       => 'admin-users-main',
            'assets/account-owners.php'                   => 'assets-owners',
            'assets/add/account-owner.php'                => 'assets-add-owner',
            'assets/add/category.php'                     => 'assets-add-category',
            'assets/add/dns.php'                          => 'assets-add-dns',
            'assets/add/host.php'                         => 'assets-add-host',
            'assets/add/hosting-fee.php'                  => 'assets-add-hosting-fee',
            'assets/add/hosting-provider-account.php'     => 'assets-add-hosting-account',
            'assets/add/hosting-type.php'                 => 'assets-add-hosting-type',
            'assets/add/ip-address.php'                   => 'assets-add-ip-address',
            'assets/add/registrar-account.php'            => 'assets-add-registrar-account',
            'assets/add/registrar-fee.php'                => 'assets-add-registrar-fee',
            'assets/add/registrar.php'                    => 'assets-add-registrar',
            'assets/add/ssl-provider-account.php'         => 'assets-add-ssl-account',
            'assets/add/ssl-provider-fee.php'             => 'assets-add-ssl-provider-fee',
            'assets/add/ssl-provider.php'                 => 'assets-add-ssl-provider',
            'assets/add/ssl-type.php'                     => 'assets-add-ssl-type',
            'assets/categories.php'                       => 'assets-categories',
            'assets/dns.php'                              => 'assets-dns',
            'assets/edit/account-owner.php'               => 'assets-edit-owner',
            'assets/edit/category.php'                    => 'assets-edit-category',
            'assets/edit/dns.php'                         => 'assets-edit-dns',
            'assets/edit/host.php'                        => 'assets-edit-host',
            'assets/edit/hosting-fee.php'                 => 'assets-edit-hosting-fee',
            'assets/edit/hosting-provider-account.php'    => 'assets-edit-hosting-account',
            'assets/edit/hosting-type.php'                => 'assets-edit-hosting-type',
            'assets/edit/ip-address.php'                  => 'assets-edit-ip-address',
            'assets/edit/registrar-account.php'           => 'assets-edit-registrar-account',
            'assets/edit/registrar-fee.php'               => 'assets-edit-registrar-fee',
            'assets/edit/registrar.php'                   => 'assets-edit-registrar',
            'assets/edit/ssl-provider-account.php'        => 'assets-edit-ssl-account',
            'assets/edit/ssl-provider-fee.php'            => 'assets-edit-ssl-provider-fee',
            'assets/edit/ssl-provider.php'                => 'assets-edit-ssl-provider',
            'assets/edit/ssl-type.php'                    => 'assets-edit-ssl-type',
            'assets/hosting-accounts.php'                 => 'assets-hosting-accounts',
            'assets/hosting-fees.php'                     => 'assets-hosting-fees',
            'assets/hosting-types.php'                    => 'assets-hosting-types',
            'assets/hosting.php'                          => 'assets-hosting',
            'assets/index.php'                            => 'assets-main',
            'assets/ip-addresses.php'                     => 'assets-ip-addresses',
            'assets/registrar-accounts.php'               => 'assets-registrar-accounts',
            'assets/registrar-fees.php'                   => 'assets-registrar-fees',
            'assets/registrars.php'                       => 'assets-registrars',
            'assets/ssl-accounts.php'                     => 'assets-ssl-accounts',
            'assets/ssl-provider-fees.php'                => 'assets-ssl-provider-fees',
            'assets/ssl-providers.php'                    => 'assets-ssl-providers',
            'assets/ssl-types.php'                        => 'assets-ssl-types',
            'bulk/cf/step-one/index.php'                  => 'bulk-main',
            'bulk/cf/step-two/index.php'                  => 'bulk-main',
            'bulk/index.php'                              => 'bulk-main',
            'bulk/main/index.php'                         => 'bulk-main',
            'dashboard/index.php'                         => 'dashboard-main',
            'debug/off/index.php'                         => 'system-debug',
            'debug/on/index.php'                          => 'system-debug',
            'domains/add.php'                             => 'domains-add',
            'domains/csv-importer/index.php'              => 'csv-importer-main',
            'domains/edit.php'                            => 'domains-edit',
            'domains/index.php'                           => 'domains-main',
            'domains/results.php'                         => 'domains-results',
            'feedback/index.php'                          => 'feedback-main',
            'history/index.php'                           => 'history-main',
            'hosting/add.php'                             => 'hosting-add',
            'hosting/edit.php'                            => 'hosting-edit',
            'hosting/index.php'                           => 'hosting-main',
            'maintenance/index.php'                       => 'maintenance-main',
            'queue/add/rdap.php'                          => 'queue-add-rdap',
            'queue/add/ssl.php'                           => 'queue-add-ssl',
            'queue/add/step-one/index.php'                => 'queue-add',
            'queue/add/step-two/index.php'                => 'queue-add',
            'queue/edit/index.php'                        => 'queue-edit',
            'queue/index.php'                             => 'queue-main',
            'queue/intro.php'                             => 'queue-info',
            'reporting/domains/at-risk.php'               => 'reporting-domain-at-risk',
            'reporting/domains/costs.php'                 => 'reporting-domain-costs',
            'reporting/domains/registrar-fees.php'        => 'reporting-domain-fees',
            'reporting/domains/tld-pricing.php'           => 'reporting-domain-tld-pricing',
            'reporting/domains/upcoming-renewals.php'     => 'reporting-domain-upcoming-renewals',
            'reporting/dw/potential-problems.php'         => 'reporting-dw-potential-problems',
            'reporting/hosting/at-risk.php'               => 'reporting-hosting-at-risk',
            'reporting/hosting/costs.php'                 => 'reporting-hosting-costs',
            'reporting/hosting/provider-fees.php'         => 'reporting-hosting-fees',
            'reporting/hosting/upcoming-renewals.php'     => 'reporting-hosting-upcoming-renewals',
            'reporting/index.php'                         => 'reporting-main',
            'reporting/renewal-calendar.php'              => 'reporting-renewal-calendar',
            'reporting/ssl/at-risk.php'                   => 'reporting-ssl-at-risk',
            'reporting/ssl/costs.php'                     => 'reporting-ssl-costs',
            'reporting/ssl/provider-fees.php'             => 'reporting-ssl-fees',
            'reporting/ssl/ssl-pricing.php'               => 'reporting-ssl-pricing',
            'reporting/ssl/upcoming-renewals.php'         => 'reporting-ssl-upcoming-renewals',
            'segments/add.php'                            => 'segments-add',
            'segments/edit.php'                           => 'segments-edit',
            'segments/index.php'                          => 'segments-main',
            'settings/defaults/index.php'                 => 'settings-defaults',
            'settings/display/index.php'                  => 'settings-display',
            'settings/index.php'                          => 'settings-main',
            'settings/profile/index.php'                  => 'settings-profile',
            'settings/security/index.php'                 => 'settings-security',
            'ssl/add.php'                                 => 'ssl-add',
            'ssl/edit.php'                                => 'ssl-edit',
            'ssl/index.php'                               => 'ssl-main',
            'watch/add.php'                               => 'watch-add',
            'watch/edit.php'                              => 'watch-edit',
            'watch/index.php'                             => 'watch-main',
    );

    // Each trail's crumbs between the Dashboard and the current page, as each crumb's link and text (the text's
    // gettext string, or the PHP that prints it)
    const TRAILS = array(
            'admin-add-custom-domain-field' => array('/admin/' => 'Administration', '/admin/domain-fields/' => 'Custom Domain Fields'),
            'admin-add-custom-hosting-field' => array('/admin/' => 'Administration', '/admin/hosting-fields/' => 'Custom Hosting Fields'),
            'admin-add-custom-ssl-field' => array('/admin/' => 'Administration', '/admin/ssl-fields/' => 'Custom SSL Fields'),
            'admin-backup-main' => array('/admin/' => 'Administration'),
            'admin-custom-domain-fields' => array('/admin/' => 'Administration'),
            'admin-custom-hosting-fields' => array('/admin/' => 'Administration'),
            'admin-custom-ssl-fields' => array('/admin/' => 'Administration'),
            'admin-debug-log-main' => array('/admin/' => 'Administration'),
            'admin-defaults' => array('/admin/' => 'Administration'),
            'admin-edit-custom-domain-field' => array('/admin/' => 'Administration', '/admin/domain-fields/' => 'Custom Domain Fields'),
            'admin-edit-custom-hosting-field' => array('/admin/' => 'Administration', '/admin/hosting-fields/' => 'Custom Hosting Fields'),
            'admin-edit-custom-ssl-field' => array('/admin/' => 'Administration', '/admin/ssl-fields/' => 'Custom SSL Fields'),
            'admin-info' => array('/admin/' => 'Administration'),
            'admin-main' => array(),
            'admin-maintenance-main' => array('/admin/' => 'Administration'),
            'admin-scheduler-main' => array('/admin/' => 'Administration'),
            'admin-settings' => array('/admin/' => 'Administration'),
            'admin-tools-main' => array('/admin/' => 'Administration'),
            'admin-upgrade-main' => array('/admin/' => 'Administration'),
            'admin-users-add' => array('/admin/' => 'Administration', '/admin/users/' => 'Users'),
            'admin-users-edit' => array('/admin/' => 'Administration', '/admin/users/' => 'Users'),
            'admin-users-main' => array('/admin/' => 'Administration'),
            'assets-add-category' => array('/assets/' => 'Assets', '/assets/categories.php' => 'Categories'),
            'assets-add-dns' => array('/assets/' => 'Assets', '/assets/dns.php' => 'DNS Profiles'),
            'assets-add-host' => array('/assets/' => 'Assets', '/assets/hosting.php' => 'Hosting Providers'),
            'assets-add-hosting-account' => array('/assets/' => 'Assets', '/assets/hosting-accounts.php' => 'Hosting Accounts'),
            'assets-add-hosting-fee' => array('/assets/' => 'Assets', '/assets/hosting.php' => 'Hosting Providers', '/assets/hosting-fees.php?hosting_id=<?php echo (int) $hosting_id; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$provider_name))); ?>"),
            'assets-add-hosting-type' => array('/assets/' => 'Assets', '/assets/hosting-types.php' => 'Hosting Service Types'),
            'assets-add-ip-address' => array('/assets/' => 'Assets', '/assets/ip-addresses.php' => 'IP Addresses'),
            'assets-add-owner' => array('/assets/' => 'Assets', '/assets/account-owners.php' => 'Account Owners'),
            'assets-add-registrar-account' => array('/assets/' => 'Assets', '/assets/registrar-accounts.php' => 'Domain Registrar Accounts'),
            'assets-add-registrar-fee' => array('/assets/' => 'Assets', '/assets/registrars.php' => 'Domain Registrars', '/assets/registrar-fees.php?rid=<?php echo (int) $rid; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$registrar_name))); ?>"),
            'assets-add-registrar' => array('/assets/' => 'Assets', '/assets/registrars.php' => 'Domain Registrars'),
            'assets-add-ssl-account' => array('/assets/' => 'Assets', '/assets/ssl-accounts.php' => 'SSL Provider Accounts'),
            'assets-add-ssl-provider-fee' => array('/assets/' => 'Assets', '/assets/ssl-providers.php' => 'SSL Providers', '/assets/ssl-provider-fees.php?sslpid=<?php echo (int) $sslpid; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$ssl_provider_name))); ?>"),
            'assets-add-ssl-provider' => array('/assets/' => 'Assets', '/assets/ssl-providers.php' => 'SSL Providers'),
            'assets-add-ssl-type' => array('/assets/' => 'Assets', '/assets/ssl-types.php' => 'SSL Certificate Types'),
            'assets-categories' => array('/assets/' => 'Assets'),
            'assets-dns' => array('/assets/' => 'Assets'),
            'assets-edit-category' => array('/assets/' => 'Assets', '/assets/categories.php' => 'Categories'),
            'assets-edit-dns' => array('/assets/' => 'Assets', '/assets/dns.php' => 'DNS Profiles'),
            'assets-edit-host' => array('/assets/' => 'Assets', '/assets/hosting.php' => 'Hosting Providers'),
            'assets-edit-hosting-account' => array('/assets/' => 'Assets', '/assets/hosting-accounts.php' => 'Hosting Accounts'),
            'assets-edit-hosting-fee' => array('/assets/' => 'Assets', '/assets/hosting.php' => 'Hosting Providers', '/assets/hosting-fees.php?hosting_id=<?php echo (int) $hosting_id; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$provider_name))); ?>"),
            'assets-edit-hosting-type' => array('/assets/' => 'Assets', '/assets/hosting-types.php' => 'Hosting Service Types'),
            'assets-edit-ip-address' => array('/assets/' => 'Assets', '/assets/ip-addresses.php' => 'IP Addresses'),
            'assets-edit-owner' => array('/assets/' => 'Assets', '/assets/account-owners.php' => 'Account Owners'),
            'assets-edit-registrar-account' => array('/assets/' => 'Assets', '/assets/registrar-accounts.php' => 'Domain Registrar Accounts'),
            'assets-edit-registrar-fee' => array('/assets/' => 'Assets', '/assets/registrars.php' => 'Domain Registrars', '/assets/registrar-fees.php?rid=<?php echo (int) $rid; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$registrar_name))); ?>"),
            'assets-edit-registrar' => array('/assets/' => 'Assets', '/assets/registrars.php' => 'Domain Registrars'),
            'assets-edit-ssl-account' => array('/assets/' => 'Assets', '/assets/ssl-accounts.php' => 'SSL Provider Accounts'),
            'assets-edit-ssl-provider-fee' => array('/assets/' => 'Assets', '/assets/ssl-providers.php' => 'SSL Providers', '/assets/ssl-provider-fees.php?sslpid=<?php echo (int) $sslpid; ?>' => "<?php echo sprintf(_('Fees for %s'), \$layout->ltr(\$unsanitize->text(\$ssl_provider_name))); ?>"),
            'assets-edit-ssl-provider' => array('/assets/' => 'Assets', '/assets/ssl-providers.php' => 'SSL Providers'),
            'assets-edit-ssl-type' => array('/assets/' => 'Assets', '/assets/ssl-types.php' => 'SSL Certificate Types'),
            'assets-hosting-accounts' => array('/assets/' => 'Assets'),
            'assets-hosting-fees' => array('/assets/' => 'Assets', '/assets/hosting.php' => 'Hosting Providers'),
            'assets-hosting-types' => array('/assets/' => 'Assets'),
            'assets-hosting' => array('/assets/' => 'Assets'),
            'assets-ip-addresses' => array('/assets/' => 'Assets'),
            'assets-main' => array(),
            'assets-owners' => array('/assets/' => 'Assets'),
            'assets-registrar-accounts' => array('/assets/' => 'Assets'),
            'assets-registrar-fees' => array('/assets/' => 'Assets', '/assets/registrars.php' => 'Domain Registrars'),
            'assets-registrars' => array('/assets/' => 'Assets'),
            'assets-ssl-accounts' => array('/assets/' => 'Assets'),
            'assets-ssl-provider-fees' => array('/assets/' => 'Assets', '/assets/ssl-providers.php' => 'SSL Providers'),
            'assets-ssl-providers' => array('/assets/' => 'Assets'),
            'assets-ssl-types' => array('/assets/' => 'Assets'),
            'bulk-main' => array(),
            'csv-importer-main' => array('/domains/' => 'Domains'),
            'dashboard-main' => array(),
            'domains-add' => array('/domains/' => 'Domains'),
            'domains-edit' => array('/domains/' => 'Domains'),
            'domains-main' => array(),
            'domains-results' => array('/domains/' => 'Domains', '/domains/index.php?segid=<?php echo $segid; ?>' => '<?php echo $segment_name_html; ?>'),
            'dw-add-server' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse', '/admin/dw/servers.php' => 'Manage DW Servers'),
            'dw-edit-server' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse', '/admin/dw/servers.php' => 'Manage DW Servers'),
            'dw-intro' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse'),
            'dw-list-accounts' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse'),
            'dw-list-zones' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse'),
            'dw-main' => array('/admin/' => 'Administration'),
            'dw-servers' => array('/admin/' => 'Administration', '/admin/dw/dw.php' => 'Data Warehouse'),
            'feedback-main' => array(),
            'history-main' => array(),
            'hosting-add' => array('/hosting/' => 'Hosting Services'),
            'hosting-edit' => array('/hosting/' => 'Hosting Services'),
            'hosting-main' => array(),
            'maintenance-main' => array(),
            'queue-add-rdap' => array('/queue/' => 'Import Queue'),
            'queue-add-ssl' => array('/queue/' => 'Import Queue'),
            'queue-add' => array('/queue/' => 'Import Queue'),
            'queue-edit' => array('/queue/' => 'Import Queue'),
            'queue-info' => array('/queue/' => 'Import Queue'),
            'queue-main' => array(),
            'reporting-domain-at-risk' => array('/reporting/' => 'Reporting'),
            'reporting-domain-costs' => array('/reporting/' => 'Reporting'),
            'reporting-domain-fees' => array('/assets/' => 'Assets'),
            'reporting-domain-tld-pricing' => array('/reporting/' => 'Reporting'),
            'reporting-domain-upcoming-renewals' => array('/reporting/' => 'Reporting'),
            'reporting-dw-potential-problems' => array('/reporting/' => 'Reporting'),
            'reporting-hosting-at-risk' => array('/reporting/' => 'Reporting'),
            'reporting-hosting-costs' => array('/reporting/' => 'Reporting'),
            'reporting-hosting-fees' => array('/assets/' => 'Assets'),
            'reporting-hosting-upcoming-renewals' => array('/reporting/' => 'Reporting'),
            'reporting-main' => array(),
            'reporting-renewal-calendar' => array('/reporting/' => 'Reporting'),
            'reporting-ssl-at-risk' => array('/reporting/' => 'Reporting'),
            'reporting-ssl-costs' => array('/reporting/' => 'Reporting'),
            'reporting-ssl-fees' => array('/assets/' => 'Assets'),
            'reporting-ssl-pricing' => array('/reporting/' => 'Reporting'),
            'reporting-ssl-upcoming-renewals' => array('/reporting/' => 'Reporting'),
            'segments-add' => array('/segments/' => 'Segments'),
            'segments-edit' => array('/segments/' => 'Segments'),
            'segments-main' => array(),
            'settings-defaults' => array('/settings/' => 'Settings'),
            'settings-display' => array('/settings/' => 'Settings'),
            'settings-main' => array(),
            'settings-profile' => array('/settings/' => 'Settings'),
            'settings-security' => array('/settings/' => 'Settings'),
            'ssl-add' => array('/ssl/' => 'SSL Certificates'),
            'ssl-edit' => array('/ssl/' => 'SSL Certificates'),
            'ssl-main' => array(),
            'system-debug' => array('/admin/' => 'Administration'),
            'watch-add' => array('/watch/' => 'Watch List'),
            'watch-edit' => array('/watch/' => 'Watch List'),
            'watch-main' => array(),
    );

    // The links that open one record's list, and the value they have to carry for it (the page reads it from the
    // request). The Domains list's plain link (/domains/) opens the whole list.
    const LINK_QUERIES = array('assets/hosting-fees.php' => 'hosting_id', 'assets/registrar-fees.php' => 'rid',
        'assets/ssl-provider-fees.php' => 'sslpid', 'domains/index.php' => 'segid');

    // The folders that hold no pages
    const NOT_PAGES = array('vendor', 'tests', 'theme', 'locales', '_includes', '.data', '.git', 'node_modules', 'temp');

    // Local scratch files for trying out classes by hand, which git ignores (as in SecurityPageTest)
    const SCRATCH_FILES = array('modules/test.php', 'test.php');

    protected static function root()
    {
        return dirname(__DIR__, 2);
    }

    // The layout files that draw the full page header (and so the breadcrumbs), straight or through another one
    protected static function headerLayouts()
    {
        $layouts = array('layout/header.inc.php');
        do {
            $found = count($layouts);
            foreach (glob(self::root() . '/_includes/layout/*.inc.php') as $file) {
                $name = 'layout/' . basename($file);
                if (in_array($name, $layouts, true)) continue;
                foreach ($layouts as $layout) {
                    if (strpos(file_get_contents($file), "DIR_INC . '/" . $layout . "'") !== false) {
                        $layouts[] = $name;
                        break;
                    }
                }
            }
        } while (count($layouts) > $found);
        return $layouts;
    }

    // Every page that draws the full page header, relative to the root
    protected static function headerPages()
    {
        $layouts = self::headerLayouts();
        $pages = array();
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(self::root(), \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) {
                return !($iterator->hasChildren() && in_array($file->getFilename(), self::NOT_PAGES, true));
            }));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $page = str_replace('\\', '/', substr($file->getPathname(), strlen(self::root()) + 1));
            if (strpos($page, 'classes/') === 0 || in_array($page, self::SCRATCH_FILES, true)) continue;
            $source = file_get_contents($file->getPathname());
            foreach ($layouts as $layout) {
                if (strpos($source, "DIR_INC . '/" . $layout . "'") !== false) {
                    $pages[] = $page;
                    break;
                }
            }
        }
        sort($pages);
        return $pages;
    }

    // The settings files a page loads
    protected static function settingsFiles($page)
    {
        preg_match_all("#DIR_INC \. '/settings/([\w.\-]+)\.inc\.php'#", file_get_contents(self::root() . '/' . $page), $matches);
        return $matches[1];
    }

    // A settings file's source
    protected static function settings($slug)
    {
        return file_get_contents(self::root() . '/_includes/settings/' . $slug . '.inc.php');
    }

    // A trail file's crumbs (after the Dashboard), each as its link (null for the current page) and its text
    protected static function crumbs($slug)
    {
        $source = file_get_contents(self::root() . '/_includes/breadcrumbs/' . $slug . '.inc.php');
        preg_match_all('#<li class="breadcrumb-item( active)?">(.*?)</li>#s', $source, $matches, PREG_SET_ORDER);
        $crumbs = array();
        foreach ($matches as $match) {
            $link = preg_match('#^<a href="<\?php echo \$web_root; \?>([^"]*)">(.*)</a>$#s', $match[2], $parts) ? $parts[1] : null;
            $crumbs[] = array('active' => $match[1] !== '', 'link' => $link, 'text' => $link === null ? $match[2] : $parts[2]);
        }
        return $crumbs;
    }

    public function testEveryPageWithTheHeaderIsListed()
    {
        $listed = array_keys(self::PAGES);
        sort($listed);

        $this->assertSame($listed, self::headerPages());
    }

    public static function pageCases()
    {
        $cases = array();
        foreach (self::PAGES as $page => $slug) {
            $cases[$page] = array($page, $slug);
        }
        return $cases;
    }

    #[DataProvider('pageCases')]
    public function testThePageLoadsTheTrailMeantForIt($page, $slug)
    {
        $this->assertSame(array($slug), self::settingsFiles($page));
        $this->assertStringContainsString('$slug = "' . $slug . '";', self::settings($slug));
        $this->assertFileExists(self::root() . '/_includes/breadcrumbs/' . $slug . '.inc.php');
    }

    #[DataProvider('pageCases')]
    public function testTheTrailEndsWithThePageTitle($page, $slug)
    {
        $crumbs = self::crumbs($slug);
        $last = array_pop($crumbs);

        if ($slug === 'dashboard-main') {
            // The Dashboard's own crumb comes from _includes/layout/breadcrumbs.inc.php, so its file adds none
            $this->assertNull($last, $page);
        } else {
            $this->assertSame(array('active' => true, 'link' => null, 'text' => '<?php echo $breadcrumb; ?>'), $last, $page);
            $this->assertMatchesRegularExpression('/^\$breadcrumb = \$page_title;$/m', self::settings($slug), $page);
        }
        foreach ($crumbs as $crumb) {
            $this->assertFalse($crumb['active'], $page);
            $this->assertNotNull($crumb['link'], $page);
        }
    }

    public static function trailCases()
    {
        $cases = array();
        foreach (glob(dirname(__DIR__, 2) . '/_includes/breadcrumbs/*.inc.php') as $file) {
            $cases[basename($file, '.inc.php')] = array(basename($file, '.inc.php'));
        }
        return $cases;
    }

    // Each crumb names and links the page meant for it, carrying the right record's value
    #[DataProvider('trailCases')]
    public function testTheTrailIsTheOneMeantForThePage($slug)
    {
        $this->assertArrayHasKey($slug, self::TRAILS);
        $expected = array();
        foreach (self::TRAILS[$slug] as $link => $text) {
            $expected[] = array('active' => false, 'link' => $link,
                'text' => strpos($text, '<?php') === 0 ? $text : "<?php echo _('" . $text . "'); ?>");
        }
        $crumbs = self::crumbs($slug);
        if ($slug !== 'dashboard-main') array_pop($crumbs);

        $this->assertSame($expected, $crumbs);
    }

    // A trail file no page loads would be left behind by a renamed or removed page
    #[DataProvider('trailCases')]
    public function testEveryTrailIsUsed($slug)
    {
        $this->assertContains($slug, self::PAGES);
    }

    #[DataProvider('trailCases')]
    public function testEveryCrumbLinksToAPage($slug)
    {
        $crumbs = self::crumbs($slug);
        // Only the current page is active, and every trail but the Dashboard's names it
        $active = array_filter($crumbs, function ($crumb) { return $crumb['active']; });
        $this->assertCount($slug === 'dashboard-main' ? 0 : 1, $active, $slug);
        foreach ($crumbs as $crumb) {
            if ($crumb['link'] === null) continue;
            $path = ltrim(strtok($crumb['link'], '?'), '/');
            if ($path === '' || substr($path, -1) === '/') $path .= 'index.php';
            $this->assertFileExists(self::root() . '/' . $path, $slug . ': ' . $crumb['link']);
            $this->assertArrayHasKey($path, self::PAGES, $slug . ': ' . $crumb['link']);
            if (isset(self::LINK_QUERIES[$path]) && strtok($crumb['link'], '?') === '/' . $path) {
                $this->assertStringStartsWith('/' . $path . '?' . self::LINK_QUERIES[$path] . '=<?php echo ', $crumb['link'], $slug);
            }
        }
    }

    // Each record's list reads the value its crumb links carry
    public function testTheLinkedListsReadTheirValues()
    {
        foreach (self::LINK_QUERIES as $page => $name) {
            $this->assertMatchesRegularExpression('/\$_(GET|REQUEST)\[\'' . $name . '\'\]/', file_get_contents(self::root() . '/' . $page), $page);
        }
    }

    // The trails follow the sidebar menu's sections: Assets, Settings, and Administration pages start at the section's
    // landing page, and the fee reports sit under Assets, as they do in the menu. The Maintenance page
    // (maintenance/index.php) is a section of its own.
    public function testTheTrailsFollowTheMenuSections()
    {
        foreach (self::PAGES as $page => $slug) {
            $section = null;
            if (preg_match('#^(assets/(?!index\.php)|reporting/\w+/(registrar|provider)-fees\.php)#', $page)) {
                $section = array('/assets/', "<?php echo _('Assets'); ?>");
            } elseif (preg_match('#^settings/(?!index\.php)#', $page)) {
                $section = array('/settings/', "<?php echo _('Settings'); ?>");
            } elseif (preg_match('#^(admin/(?!index\.php)|debug/)#', $page)) {
                $section = array('/admin/', "<?php echo _('Administration'); ?>");
            }
            if ($section === null) continue;
            $first = self::crumbs($slug)[0];
            $this->assertSame($section, array($first['link'], $first['text']), $page);
        }
    }

    // A page linked from several trails has the same name in each
    public function testALinkedPageHasOneName()
    {
        $names = array();
        foreach (self::PAGES as $slug) {
            foreach (self::crumbs($slug) as $crumb) {
                if ($crumb['link'] === null || strpos($crumb['text'], '_(') === false) continue;
                $path = strtok($crumb['link'], '?');
                $names[$path][$crumb['text']] = true;
            }
        }
        foreach ($names as $path => $texts) {
            $this->assertCount(1, $texts, $path . ': ' . implode(', ', array_keys($texts)));
        }
    }

    // A crumb names the page it links to with that page's title, so the crumb and the page's heading agree: the crumb
    // echoes the same expression the linked page's settings file gives $page_title. The fee lists' titles name their
    // registrar or provider, and the add and edit fee pages set the same variable for their crumbs.
    public function testACrumbUsesTheLinkedPagesTitle()
    {
        foreach (self::PAGES as $slug) {
            foreach (self::crumbs($slug) as $crumb) {
                if ($crumb['link'] === null || strpos($crumb['text'], '_(') === false) continue;
                $path = ltrim(strtok($crumb['link'], '?'), '/');
                if ($path === '' || substr($path, -1) === '/') $path .= 'index.php';
                $this->assertSame(1, preg_match('/^<\?php echo (.+); \?>$/s', $crumb['text'], $text), $slug . ': ' . $crumb['link']);
                $this->assertMatchesRegularExpression('/^\$page_title = ' . preg_quote($text[1], '/') . ';$/m',
                    self::settings(self::PAGES[$path]), $slug . ': ' . $crumb['link']);
            }
        }
    }

    // The fee lists' titles name their registrar or provider, so each page loads its settings file only once it has
    // the name
    public function testTheFeeListsLoadTheirSettingsAfterTheName()
    {
        $names = array('assets/hosting-fees.php' => '$provider_name', 'assets/registrar-fees.php' => '$registrar_name',
            'assets/ssl-provider-fees.php' => '$ssl_provider_name');
        foreach ($names as $page => $name) {
            $source = file_get_contents(self::root() . '/' . $page);
            $named = strpos($source, "\n" . $name . ' = $assets->');
            $loaded = strpos($source, "\nrequire_once DIR_INC . '/settings/" . self::PAGES[$page] . ".inc.php';");
            $this->assertNotFalse($named, $page);
            $this->assertNotFalse($loaded, $page);
            $this->assertGreaterThan($named, $loaded, $page);
        }
    }

    // The pages retitled to their menu names (Greg's choice, 2026-11-21), including the ones no crumb links to
    public function testTheRetitledPagesKeepTheirNames()
    {
        $titles = array('admin-main' => 'Administration', 'admin-tools-main' => 'Tools', 'admin-users-main' => 'Users',
            'assets-hosting-types' => 'Hosting Service Types', 'assets-main' => 'Assets',
            'dw-intro' => 'Data Warehouse Introduction', 'maintenance-main' => 'Maintenance', 'segments-main' => 'Segments');
        foreach ($titles as $slug => $title) {
            $this->assertStringContainsString("\n\$page_title = _('" . $title . "');\n", self::settings($slug), $slug);
        }
    }
}
