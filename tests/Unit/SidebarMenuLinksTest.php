<?php
/**
 * /tests/Unit/SidebarMenuLinksTest.php
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

use DomainMOD\Database;
use DomainMOD\Hosting;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;


/*
 * The sidebar's items with a submenu and a landing page (Assets, Settings, Maintenance, and Administration): the name
 * is a link to the landing page, and the arrow is a separate button that opens and closes the submenu (aria-expanded,
 * aria-controls naming the submenu, and a translated label). The item is open and highlighted, and its button says so,
 * on every page in its section, the landing page included. Help has no landing page, so it keeps AdminLTE's "#" link
 * with the arrow inside. Maintenance holds the fee and rate updates, which used to be in Assets, and read-only users,
 * who can't run them, don't get it. js/sidebar-menu.js stops AdminLTE's treeview opening or closing the submenu while
 * the name's link leaves the page, and keeps aria-expanded on the button rather than the link.
 */
class SidebarMenuLinksTest extends TestCase
{
    public static function parents()
    {
        return array(
            'Assets' => array('Assets', '/assets/', 'menu-assets', 'Show or hide the Assets menu', '/assets/index.php',
                '/settings/assets-main.inc.php', 'assets', 'assets-main'),
            'Settings' => array('Settings', '/settings/', 'menu-settings', 'Show or hide the Settings menu', '/settings/index.php',
                '/settings/settings-main.inc.php', 'settings', 'settings-main'),
            'Maintenance' => array('Maintenance', '/maintenance/', 'menu-maintenance', 'Show or hide the Maintenance menu',
                '/maintenance/index.php', '/settings/maintenance-main.inc.php', 'maintenance', 'maintenance-main'),
            'Administration' => array('Administration', '/admin/', 'menu-admin', 'Show or hide the Administration menu',
                '/admin/index.php', '/settings/admin-main.inc.php', 'admin', 'admin-main'),
        );
    }

    #[RunInSeparateProcess]
    #[DataProvider('parents')]
    public function testTheNameLinksToTheLandingPageAndTheArrowIsAButton($name, $href, $id, $label)
    {
        $xpath = self::xpath(self::menu('dashboard', 'dashboard', 1));
        $item = self::item($xpath, $name);
        $this->assertNotNull($item, $name);
        $this->assertContains('domainmod-menu-parent', self::classes($item));
        $this->assertNotContains('menu-open', self::classes($item));

        // The item's children, in order: the link, the button, and the submenu
        $children = array();
        foreach ($item->childNodes as $child) {
            if ($child instanceof \DOMElement) $children[] = $child;
        }
        $this->assertSame(array('a', 'button', 'ul'), array_map(function ($child) { return $child->tagName; }, $children));
        list($link, $button, $submenu) = $children;

        $this->assertSame($href, $link->getAttribute('href'));
        $this->assertContains('nav-link', self::classes($link));
        $this->assertNotContains('active', self::classes($link));
        $this->assertFalse($link->hasAttribute('aria-expanded'));
        $this->assertSame(0, $xpath->query('.//*[contains(concat(" ", @class, " "), " nav-arrow ")]', $link)->length);
        $this->assertSame($name, trim($link->textContent));

        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame(array('domainmod-menu-toggle'), self::classes($button));
        $this->assertSame($label, $button->getAttribute('aria-label'));
        $this->assertSame('false', $button->getAttribute('aria-expanded'));
        $this->assertSame($id, $button->getAttribute('aria-controls'));
        $this->assertFalse($button->hasAttribute('tabindex'));
        $this->assertFalse($button->hasAttribute('disabled'));
        $arrow = $xpath->query('./i', $button);
        $this->assertSame(1, $arrow->length);
        $this->assertContains('nav-arrow', self::classes($arrow->item(0)));
        $this->assertSame('true', $arrow->item(0)->getAttribute('aria-hidden'));

        $this->assertSame($id, $submenu->getAttribute('id'));
        $this->assertSame(array('nav', 'nav-treeview'), self::classes($submenu));
        $this->assertSame(1, $xpath->query('//*[@id="' . $id . '"]')->length);
    }

    #[RunInSeparateProcess]
    #[DataProvider('parents')]
    public function testTheItemIsOpenOnItsLandingPage($name, $href, $id, $label, $page, $settings, $section, $slug)
    {
        // The landing page is in the item's section, so the item is open and highlighted there
        $this->assertStringContainsString("require_once DIR_INC . '" . $settings . "';", self::code($page));
        $this->assertStringContainsString('$software_section = "' . $section . '";', self::code('/_includes' . $settings));
        $this->assertStringContainsString('$slug = "' . $slug . '";', self::code('/_includes' . $settings));

        $item = self::item(self::xpath(self::menu($slug, $section, 1)), $name);
        $this->assertContains('menu-open', self::classes($item));
        $this->assertContains('active', self::classes($item->getElementsByTagName('a')->item(0)));
        $this->assertSame('true', $item->getElementsByTagName('button')->item(0)->getAttribute('aria-expanded'));
    }

    // The open and highlighted rules: the fee reports open Assets, the fee and rate updates (the maintenance section)
    // open Maintenance rather than Assets, and the Data Warehouse opens Administration
    #[RunInSeparateProcess]
    public function testTheOtherPagesThatOpenAnItemStillDo()
    {
        foreach (array(array('reporting-domain-fees', 'reporting', 'Assets'), array('reporting-ssl-fees', 'reporting', 'Assets'),
                     array('reporting-hosting-fees', 'reporting', 'Assets'), array('maintenance-main', 'maintenance', 'Maintenance'),
                     array('assets-registrars', 'assets', 'Assets'), array('settings-profile', 'settings', 'Settings'),
                     array('admin-users-main', 'admin', 'Administration'), array('dw-main', 'dw', 'Administration')) as $page) {
            list($slug, $section, $open) = $page;
            $xpath = self::xpath(self::menu($slug, $section, 1));
            foreach (array('Assets', 'Settings', 'Maintenance', 'Administration') as $name) {
                $item = self::item($xpath, $name);
                $expected = $name === $open;
                $this->assertSame($expected, in_array('menu-open', self::classes($item), true), $slug . ' ' . $name);
                $this->assertSame($expected, in_array('active', self::classes($item->getElementsByTagName('a')->item(0)), true), $slug . ' ' . $name);
                $this->assertSame($expected ? 'true' : 'false', $item->getElementsByTagName('button')->item(0)->getAttribute('aria-expanded'),
                    $slug . ' ' . $name);
            }
        }
        // The Reporting item itself isn't highlighted on the fee reports, as before
        $xpath = self::xpath(self::menu('reporting-domain-fees', 'reporting', 1));
        $this->assertNotContains('active', self::classes($xpath->query('//a[@aria-label="Reporting"]')->item(0)));
    }

    // Help has no landing page, so it keeps AdminLTE's link that only opens and closes the submenu. It's the only
    // item with a "#" link, and the only arrow inside a link.
    #[RunInSeparateProcess]
    public function testHelpKeepsItsSubmenuOnlyLink()
    {
        $xpath = self::xpath(self::menu('dashboard', 'dashboard', 1));
        $help = self::item($xpath, 'Help');
        $this->assertNotContains('domainmod-menu-parent', self::classes($help));
        $this->assertSame(0, $help->getElementsByTagName('button')->length);
        $this->assertSame('#', $help->getElementsByTagName('a')->item(0)->getAttribute('href'));

        $hash_links = array();
        foreach ($xpath->query('//a[@href="#"]') as $link) $hash_links[] = $link->getAttribute('aria-label');
        $this->assertSame(array('Help'), $hash_links);
        $this->assertSame(1, $xpath->query('//a//*[contains(concat(" ", @class, " "), " nav-arrow ")]')->length);
        $this->assertSame(4, $xpath->query('//button[@class="domainmod-menu-toggle"]')->length);
        // Every item with a submenu is either Help or has its own button
        foreach ($xpath->query('//ul[@id="navigation"]/li[ul]') as $item) {
            $name = $item->getElementsByTagName('a')->item(0)->getAttribute('aria-label');
            $this->assertSame($name !== 'Help', in_array('domainmod-menu-parent', self::classes($item), true), $name);
        }
    }

    // Administration and its button are still only shown to administrators. Read-only users still get Assets and
    // Settings, but not Maintenance, since they can't update fees or rates.
    #[RunInSeparateProcess]
    public function testAdministrationIsStillOnlyForAdministrators()
    {
        $html = self::menu('dashboard', 'dashboard', 0, '1');
        $xpath = self::xpath($html);
        $this->assertNull(self::item($xpath, 'Administration'));
        $this->assertNull(self::item($xpath, 'Maintenance'));
        $this->assertStringNotContainsString('menu-admin', $html);
        $this->assertStringNotContainsString('/admin/', $html);
        $this->assertStringNotContainsString('menu-maintenance', $html);
        $this->assertStringNotContainsString('/maintenance/', $html);
        $this->assertSame(2, $xpath->query('//button[@class="domainmod-menu-toggle"]')->length);
        $this->assertNotNull(self::item($xpath, 'Assets'));
        $this->assertNotNull(self::item($xpath, 'Settings'));

        // A user who isn't read-only gets Maintenance, between Settings and Administration
        $xpath = self::xpath(self::menu('dashboard', 'dashboard', 0));
        $this->assertNotNull(self::item($xpath, 'Maintenance'));
        $this->assertNull(self::item($xpath, 'Administration'));
        $names = array();
        foreach ($xpath->query('//ul[@id="navigation"]/li/a') as $link) $names[] = $link->getAttribute('aria-label');
        $this->assertSame(array('Assets', 'Settings', 'Maintenance', 'Help'), array_values(array_intersect($names,
            array('Assets', 'Settings', 'Maintenance', 'Administration', 'Help'))));
        $xpath = self::xpath(self::menu('dashboard', 'dashboard', 1));
        $names = array();
        foreach ($xpath->query('//ul[@id="navigation"]/li/a') as $link) $names[] = $link->getAttribute('aria-label');
        $this->assertSame(array('Assets', 'Settings', 'Maintenance', 'Administration', 'Help'), array_values(array_intersect($names,
            array('Assets', 'Settings', 'Maintenance', 'Administration', 'Help'))));
    }

    // Maintenance holds the four updates, in order, with Update Hosting Fees only once the database has hosting, and
    // Assets no longer has any of them
    #[RunInSeparateProcess]
    public function testMaintenanceHoldsTheFeeAndRateUpdates()
    {
        $domain = array('/maintenance/update-domain-fees.php', 'Update Domain Fees');
        $hosting = array('/maintenance/update-hosting-fees.php', 'Update Hosting Fees');
        $ssl = array('/maintenance/update-ssl-fees.php', 'Update SSL Fees');
        $rates = array('/maintenance/update-conversions.php', 'Update Conversion Rates');
        // No database (as in the other tests here), then a database before and after the update that adds hosting
        foreach (array(array(null, array($domain, $ssl, $rates)), array('4.26.0', array($domain, $ssl, $rates)),
                     array('4.27.0', array($domain, $hosting, $ssl, $rates))) as $case) {
            list($version, $expected) = $case;
            Hosting::forgetAvailability();
            (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $version === null ? null :
                Instance::withoutConstructor(Database::class, array('cnxx' => new FakeDatabase(array('SELECT db_version' => $version)))));
            try {
                $xpath = self::xpath(self::menu('dashboard', 'dashboard', 0));
            } finally {
                (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
                Hosting::forgetAvailability();
            }
            $links = array();
            foreach ($xpath->query('//ul[@id="menu-maintenance"]/li/a') as $link) {
                $links[] = array(preg_replace('/\\?.*$/', '', $link->getAttribute('href')), trim($link->textContent));
                $this->assertMatchesRegularExpression('/\\?csrf_token=a{64}$/', $link->getAttribute('href'));
            }
            $this->assertSame($expected, $links, (string) $version);
            $this->assertSame(0, $xpath->query('//ul[@id="menu-assets"]//a[contains(@href, "/maintenance/")]')->length);
            $this->assertSame(0, $xpath->query('//ul[@id="menu-assets"]//a[starts-with(@aria-label, "Update ")]')->length);
        }

        $source = self::code('/_includes/layout/menu-main.inc.php');
        foreach (array('update-domain-fees.php', 'update-hosting-fees.php', 'update-ssl-fees.php', 'update-conversions.php') as $file) {
            $this->assertSame(1, substr_count($source, '/maintenance/' . $file), $file);
        }
        // The whole section is left out for read-only users, as each of its items was
        $this->assertMatchesRegularExpression('/if \(\(\$_SESSION\[\'s_read_only\'\] \?\? \'\'\) != \'1\'\) \{\s*' .
            '\$menu_maintenance_open = \$software_section == "maintenance"; \?>/', $source);
    }

    // Administration's page that clears the queues and the debug log is Cleanup in the menu, so the only Maintenance
    // item is the top-level section
    #[RunInSeparateProcess]
    public function testAdministrationsQueueClearingPageIsCleanup()
    {
        $xpath = self::xpath(self::menu('admin-maintenance-main', 'admin', 1));
        $link = $xpath->query('//ul[@id="menu-admin"]/li/a[@href="/admin/maintenance/"]');
        $this->assertSame(1, $link->length);
        $this->assertSame('Cleanup', $link->item(0)->getAttribute('aria-label'));
        $this->assertSame('Cleanup', trim($link->item(0)->textContent));
        $this->assertContains('active', self::classes($link->item(0)));
        $maintenance = $xpath->query('//a[@aria-label="Maintenance"]');
        $this->assertSame(1, $maintenance->length);
        $this->assertSame('/maintenance/', $maintenance->item(0)->getAttribute('href'));
    }

    // The script that keeps the name's click from also opening or closing the submenu, loaded after AdminLTE, and the
    // styles for the button, checked in their source (the clicks themselves need a browser)
    public function testTheScriptAndStylesAreInPlace()
    {
        $footer = self::code('/_includes/layout/footer.inc.php');
        $adminlte = strpos($footer, "/dist/js/adminlte.min.js'");
        $script = strpos($footer, "assetUrl('js/sidebar-menu.js')");
        $this->assertNotFalse($adminlte);
        $this->assertNotFalse($script);
        $this->assertLessThan($script, $adminlte);

        $js = file_get_contents(DIR_ROOT . '/js/sidebar-menu.js');
        $this->assertStringContainsString("document.getElementById('navigation')", $js);
        // A click listener on the menu runs before AdminLTE's, which listens for clicks on the whole page
        $this->assertStringContainsString("menu.addEventListener('click', function (event) {", $js);
        $this->assertStringContainsString("closest('.domainmod-menu-parent > .nav-link')", $js);
        $this->assertStringContainsString("if (leaving !== item) return;\n            leaving = null;\n            event.preventDefault();", $js);
        $this->assertStringContainsString("item.addEventListener('expand.lte.treeview', cancelIfLeaving);", $js);
        $this->assertStringContainsString("item.addEventListener('collapse.lte.treeview', cancelIfLeaving);", $js);
        $this->assertStringContainsString('event.preventDefault();', $js);
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', item.classList.contains('menu-open') ? 'true' : 'false');", $js);
        $this->assertStringContainsString("link.removeAttribute('aria-expanded');", $js);
        // The arrow is a real button, which AdminLTE's own handler toggles, so the script never toggles or navigates
        $this->assertStringNotContainsString('.toggle(', $js);
        $this->assertStringNotContainsString('location', $js);

        // The button is hidden in the collapsed sidebar until it's hovered, and turns with the submenu
        $css = file_get_contents(DIR_ROOT . '/css/base.css');
        $this->assertStringContainsString(".sidebar-mini.sidebar-collapse .app-sidebar:not(:hover) .domainmod-menu-toggle {\n    display: none;\n}", $css);
        $this->assertStringContainsString(".menu-open > .domainmod-menu-toggle .nav-arrow {\n    transform: rotate(90deg);\n}", $css);
        // The link only leaves room for the button where it shows, so collapsed links stay as narrow as the others
        $this->assertStringContainsString("body:not(.sidebar-collapse) .app-sidebar .sidebar-menu .domainmod-menu-parent > .nav-link,\n" .
            ".sidebar-collapse .app-sidebar:hover .sidebar-menu .domainmod-menu-parent > .nav-link {\n    padding-inline-end: 2.75rem;\n}", $css);
        $this->assertStringContainsString(".domainmod-menu-toggle:focus-visible {\n    outline: 2px solid currentColor;", $css);
    }

    // The menu as a page in $software_section with $slug shows it, for an administrator or not, read-only or not.
    private static function menu($slug, $software_section, $is_admin, $read_only = '0')
    {
        if (!defined('WEB_ROOT')) define('WEB_ROOT', '');
        $_SESSION = array('s_read_only' => $read_only, 's_is_admin' => $is_admin, 's_csrf_token' => str_repeat('a', 64));
        $web_root = '';
        ob_start();
        require DIR_INC . '/layout/menu-main.inc.php';
        return ob_get_clean();
    }

    private static function xpath($html)
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    // The main menu's item whose link is labelled $name, or null when there's none.
    private static function item(\DOMXPath $xpath, $name)
    {
        $items = $xpath->query('//ul[@id="navigation"]/li[a[@aria-label="' . $name . '"]]');
        return $items->length === 1 ? $items->item(0) : null;
    }

    private static function classes(\DOMElement $element)
    {
        return array_values(array_filter(explode(' ', $element->getAttribute('class')), 'strlen'));
    }

    // A file's source with each comment replaced by a space, so code that's commented out doesn't count.
    private static function code($path)
    {
        $source = file_get_contents(DIR_ROOT . $path);
        if ($source === false) return '';
        $code = '';
        foreach (\PhpToken::tokenize($source) as $token) {
            $code .= $token->is(array(T_COMMENT, T_DOC_COMMENT)) ? ' ' : $token->text;
        }
        return $code;
    }
}
