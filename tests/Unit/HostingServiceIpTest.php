<?php
/**
 * /tests/Unit/HostingServiceIpTest.php
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

use DomainMOD\Form;
use DomainMOD\Hosting;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Picking a domain's Hosting Service sets its IP Address to the service's, in the browser, and the IP Address keeps
 * following the service until the user changes it on that visit (js/hosting-service-ip.js). On Edit Domain the IP
 * address the domain was saved with is never replaced, only the "[no ip address]" placeholder or one the script filled
 * in. The menu carries what the script needs: the IP Address menu's name, whether to keep a saved IP address, and each
 * service's IP address on its option, so no extra request is made.
 */
class HostingServiceIpTest extends TestCase
{
    // Only the services with an IP address get data-ip-id, and the menu names the IP Address menu
    public function testTheMenuCarriesEachServicesIpAddress()
    {
        $html = (new Form())->showHostingServiceMenu('new_service_id', 0, array(4 => 'Shared (Acme, Greg, greg)',
            9 => 'VPS (Acme, Greg, greg)'), '', '', 'new_ip_id', array(4 => 7));

        $this->assertSame(1, preg_match('/<select [^>]*>/', $html, $select));
        $this->assertStringContainsString(' data-ip-menu="new_ip_id"', $select[0]);
        $this->assertStringContainsString('domainmod-search-select', $select[0]);
        $this->assertSame(3, preg_match_all('/<option value="(\d+)"([^>]*)>/', $html, $options));
        $this->assertSame(array('0', '4', '9'), $options[1]);
        $this->assertStringNotContainsString('data-ip-id', $options[2][0]);
        $this->assertStringContainsString(' data-ip-id="7"', $options[2][1]);
        $this->assertStringNotContainsString('data-ip-id', $options[2][2]);
        // None is still chosen
        $this->assertStringContainsString(' selected', $options[2][0]);
    }

    // Without an IP Address menu (the Bulk Updater's Change Hosting Service) there's nothing for the script to fill in
    public function testTheMenuNamesNoIpMenuUnlessGivenOne()
    {
        $html = (new Form())->showHostingServiceMenu('new_service_id', 4, array(4 => 'Shared (Acme, Greg, greg)'));
        $this->assertStringNotContainsString('data-ip-menu', $html);
        $this->assertStringNotContainsString('data-ip-id', $html);
    }

    // A data attribute's value is escaped, and a name that could add another attribute is left out
    public function testDataAttributesAreEscaped()
    {
        $html = (new Form())->showDropdownOption('1', 'One', '', array('note' => '"<b>&', 'x onclick' => 'a', "y\n" => 'b'));
        $this->assertStringContainsString(' data-note="&quot;&lt;b&gt;&amp;"', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertSame(1, substr_count($html, 'data-'));
    }

    // Hosting::serviceIps() reads only the given ids, keeps a service's IP address only when it still exists, and
    // leaves out the placeholder (id 1) and None (0, which matches no IP address)
    public function testServiceIpsReadOnlyRealIpAddresses()
    {
        $pdo = new RecordingPdo(array('FROM hosting_services AS hs' => array(array(
            array('id' => '4', 'ip_id' => '7'), array('id' => '9', 'ip_id' => '12')))));

        $this->assertSame(array(4 => 7, 9 => 12), Hosting::serviceIps($pdo, array('4', 9, 4, 0, -3, 'x')));
        $sql = $pdo->log[0]['sql'];
        $this->assertStringContainsString('WHERE hs.id IN (4,9)', $sql);
        $this->assertStringContainsString('INNER JOIN ip_addresses AS ip ON ip.id = hs.ip_id', $sql);
        $this->assertStringContainsString('AND hs.ip_id <> 1', $sql);

        // No ids, no query
        $empty = new RecordingPdo();
        $this->assertSame(array(), Hosting::serviceIps($empty, array(0, '')));
        $this->assertSame(array(), $empty->log);
    }

    // Each domain form with an IP Address menu and a Hosting Service menu: the IP Address menu's name, as the page
    // names it
    public static function formSites()
    {
        return array(
            'domain add' => array('domains/add.php', 'new_ip_id', false),
            'domain edit' => array('domains/edit.php', 'new_ip_id', true),
            'bulk add domains' => array('bulk/main/index.php', 'new_ipid', false),
            'queue fix and import' => array('queue/edit/index.php', 'new_ip_id', false));
    }

    // Each passes its IP Address menu's name (none once a failed save shows the form again, so nothing is filled in) and
    // the services' IP addresses, Edit Domain, a domain that's already saved, keeps its saved IP address, and each loads
    // the script after the footer
    #[DataProvider('formSites')]
    public function testEachDomainFormFillsItsIpMenu($path, $ip_menu, $keep_saved)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $path);
        $this->assertStringContainsString("echo \$form->showDropdownTop('" . $ip_menu . "', _('IP Address'), '', '1', '');", $source, $path);
        $this->assertSame(1, substr_count($source, "\$_SERVER['REQUEST_METHOD'] == 'POST' ? '' : '" . $ip_menu . "', DomainMOD\\Hosting::serviceIps(\$pdo, array_keys(\$services))"
            . ($keep_saved ? ', true);' : ');')), $path);
        $script = strpos($source, "<script src=\"<?php echo \$layout->assetUrl('js/hosting-service-ip.js'); ?>\"></script>");
        $this->assertNotFalse($script, $path);
        $this->assertLessThan($script, strpos($source, "require_once DIR_INC . '/layout/footer.inc.php';"), $path);
    }

    // The Bulk Updater's Change Hosting Service has no IP Address menu, so its menu names none
    public function testChangeHostingServiceNamesNoIpMenu()
    {
        $bulk = file_get_contents(DIR_ROOT . '/bulk/main/index.php');
        $at = strpos($bulk, "// The new hosting service, or None to clear the domains' link");
        $this->assertNotFalse($at);
        $this->assertStringContainsString("echo \$form->showHostingServiceMenu('new_service_id', \$temp_to_compare, DomainMOD\Hosting::serviceMenu(\$pdo, \$temp_to_compare));",
            substr($bulk, $at, 300));
    }

    // Edit Domain's menu says to keep a saved IP address, and a menu without an IP Address menu never does
    public function testTheMenuSaysWhenToKeepASavedIpAddress()
    {
        $form = new Form();
        $this->assertStringContainsString(' data-ip-keep-saved="1"', $form->showHostingServiceMenu('s', 0, array(4 => 'A'), '', '', 'new_ip_id', array(), true));
        $this->assertStringNotContainsString('data-ip-keep-saved', $form->showHostingServiceMenu('s', 0, array(4 => 'A'), '', '', 'new_ip_id'));
        $this->assertStringNotContainsString('data-ip-keep-saved', $form->showHostingServiceMenu('s', 0, array(4 => 'A'), '', '', '', array(), true));
    }

    /*
     * The script sets the IP Address to the service's while the user hasn't changed it on this visit (its own fill, which
     * sends a change event too, doesn't count), and on Edit Domain only from the placeholder or its own last fill. It
     * uses only an IP address the menu lists, and lets the page know, as a pick by the user would.
     */
    public function testTheScriptFollowsTheServiceUntilTheUserChangesTheIp()
    {
        $js = file_get_contents(DIR_ROOT . '/js/hosting-service-ip.js');
        $this->assertStringContainsString("var hostingServiceIpPlaceholder = '1';", $js);
        $this->assertStringContainsString("document.querySelectorAll('select[data-ip-menu]')", $js);
        $this->assertStringContainsString("var keep_saved = service_menu.getAttribute('data-ip-keep-saved') === '1';", $js);
        $this->assertStringContainsString("ip_menu.addEventListener('change', function () {\n        if (!filling) changed_by_user = true;\n    });", $js);
        // Tom Select tells the real menu of a pick, by mouse or keyboard, with a change event
        $this->assertStringContainsString("service_menu.addEventListener('change', function () {", $js);
        $this->assertStringContainsString('if (ip_menu.disabled || changed_by_user) return;', $js);
        $this->assertStringContainsString('if (keep_saved && ip_menu.value !== hostingServiceIpPlaceholder && ip_menu.value !== filled) return;', $js);
        $this->assertStringContainsString("var ip_id = service ? service.getAttribute('data-ip-id') : null;", $js);
        $this->assertStringContainsString('if (!ip_id || ip_id === hostingServiceIpPlaceholder) return;', $js);
        $this->assertStringContainsString('if (!listed) return;', $js);
        // The IP address used is remembered before the menu changes (and when it's already chosen), so the next pick on
        // Edit Domain can replace the script's own fill
        $this->assertLessThan(strpos($js, 'ip_menu.value = ip_id;'), strpos($js, 'filled = ip_id;'));
        $this->assertLessThan(strpos($js, 'filled = ip_id;'), strpos($js, 'if (!listed) return;'));
        // The guards come before the menu is changed, and the script's own change event is sent with filling set
        $this->assertLessThan(strpos($js, 'ip_menu.value = ip_id;'), strpos($js, 'if (keep_saved &&'));
        $this->assertStringContainsString("filling = true;\n        try {\n            ip_menu.dispatchEvent(new Event('change', { bubbles: true }));\n        } finally {\n            filling = false;\n        }", $js);
        // Nothing is fetched
        $this->assertStringNotContainsString('fetch(', $js);
        $this->assertStringNotContainsString('XMLHttpRequest', $js);
    }

} //@formatter:on
