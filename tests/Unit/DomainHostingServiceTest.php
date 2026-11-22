<?php
/**
 * /tests/Unit/DomainHostingServiceTest.php
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

use DomainMOD\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Domains and hosting services (13a): a domain picks an optional hosting service on its add and edit pages, the Bulk
 * Updater, and the Domain Queue's Fix and Import page, and no longer names a hosting provider. Before the database
 * update (Hosting::isAvailable() false) none of that is shown, and the five domain inserts write the old hosting_id as
 * 0, so it doesn't take its old default of 1 (a real provider on some installs, which the upgrade notice would count).
 */
class DomainHostingServiceTest extends TestCase
{
    const LEGACY_MARKER = '// Legacy: domains.hosting_id before 4.27.0';

    // The five domain inserts, each with the condition its pre-update branch sits behind
    public static function insertSites()
    {
        return array(
            'domain add' => array('domains/add.php', 'if (!DomainMOD\Hosting::legacyDomainColumn($pdo)) {'),
            'bulk add domains' => array('bulk/main/index.php', 'if (!DomainMOD\Hosting::legacyDomainColumn($pdo)) {'),
            'queue fix and import' => array('queue/edit/index.php', 'if (!DomainMOD\Hosting::legacyDomainColumn($pdo)) {'),
            'csv importer' => array('classes/DomainMOD/Domain.php', 'if (!Hosting::legacyDomainColumn($pdo)) {'),
            'queue import' => array('classes/DomainMOD/DomainQueue.php', 'if (!$this->legacy_domain_column) {'));
    }

    // Each insert writes hosting_id only as a literal 0, only in the else branch of its Hosting::legacyDomainColumn()
    // check, and that statement carries the legacy marker. The branch for an updated database never names hosting_id.
    #[DataProvider('insertSites')]
    public function testEachInsertWritesTheOldColumnAsZeroOnlyBeforeTheUpdate($path, $condition)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $path);
        $this->assertSame(1, substr_count($source, self::LEGACY_MARKER), $path);
        $this->assertSame(1, preg_match('/' . preg_quote($condition, '/') . '(.*?)\} else \{\s*'
            . preg_quote(self::LEGACY_MARKER, '/') . '\s*(\$stmt = \$pdo->prepare\(".*?"\);)/s', $source, $match), $path);
        list(, $updated, $legacy) = $match;
        $this->assertStringContainsString('INSERT INTO domains', $updated, $path);
        $this->assertStringNotContainsString('hosting_id', $updated, $path);
        $this->assertStringContainsString('INSERT INTO domains', $legacy, $path);
        $this->assertStringContainsString('hosting_id', $legacy, $path);
        $this->assertStringNotContainsString(':hosting_id', $legacy, $path);
        $this->assertStringNotContainsString('service_id', $legacy, $path);
        // The two column lists line up, with 0 in hosting_id's place
        $this->assertSame(1, preg_match('/\((.*?)\)\s*VALUES\s*\((.*?)\)"\);/s', $legacy, $lists), $path);
        $columns = array_map('trim', explode(',', $lists[1]));
        $values = array_map('trim', explode(',', $lists[2]));
        $this->assertSame(count($columns), count($values), $path);
        $this->assertSame('0', $values[array_search('hosting_id', $columns, true)], $path);
        // Nothing else in the file names the old column in SQL
        $this->assertSame(1, preg_match_all('/\bhosting_id\b(?![^\n]*(?:\/\/|\*))/', preg_replace('/\/\/[^\n]*|\/\*.*?\*\//s', '', $source)), $path);
    }

    // The edit page and the Bulk Updater's Change Hosting Service only ever write service_id, and only once hosting is
    // available (the edit page leaves the column out of its UPDATE before the update)
    public function testTheUpdatesWriteOnlyTheServiceOnceHostingIsAvailable()
    {
        $edit = file_get_contents(DIR_ROOT . '/domains/edit.php');
        $this->assertStringNotContainsString('hosting_id', preg_replace('/\/\/[^\n]*/', '', $edit));
        $this->assertStringContainsString('" . ($hosting_available ? "service_id = :new_service_id," : "") . "', $edit);
        $this->assertStringContainsString('d.ip_id" . ($hosting_available ? ", d.service_id" : "") . ", d.function', $edit);
        $bulk = file_get_contents(DIR_ROOT . '/bulk/main/index.php');
        $this->assertStringContainsString("'CWH' => array('service_id')", $bulk);
        $this->assertSame(2, substr_count($bulk, 'SET service_id = :new_service_id,'));
    }

    // Every domain-side menu is read with Hosting::postedInt(..., -1), never a cast, and only once hosting is available
    public static function postedSites()
    {
        return array(array('domains/add.php'), array('domains/edit.php'), array('bulk/main/index.php'), array('queue/edit/index.php'));
    }

    #[DataProvider('postedSites')]
    public function testTheServiceIsReadWithPostedInt($path)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $path);
        $this->assertStringContainsString('$hosting_available = DomainMOD\Hosting::isAvailable($pdo);', $source, $path);
        $this->assertStringContainsString("\$new_service_id = \$hosting_available ? DomainMOD\Hosting::postedInt(\$_POST['new_service_id'] ?? '0', -1) : 0;", $source, $path);
        $this->assertStringNotContainsString('(int) ($_POST[\'new_service_id\']', $source, $path);
        // A malformed choice is refused with a field error, and the menu is drawn only once hosting is available
        $this->assertStringContainsString("addFieldError('new_service_id', DomainMOD\Hosting::serviceGoneMessage());", $source, $path);
        $offset = 0;
        $menus = 0;
        while (($at = strpos($source, '$form->showHostingServiceMenu(', $offset)) !== false) {
            $before = substr($source, max(0, $at - 400), min(400, $at));
            // Drawn behind the availability check, or in Change Hosting Service, which is refused before the update
            $this->assertTrue(strpos($before, 'if ($hosting_available) {') !== false
                || strpos($before, '} elseif ($action == "CWH") {') !== false, $path);
            $offset = $at + 1;
            $menus++;
        }
        $this->assertGreaterThanOrEqual(1, $menus, $path);
        // No page offers the old provider menu or its "Choose" error
        $this->assertStringNotContainsString('new_hosting_id', $source, $path);
        $this->assertStringNotContainsString('new_whid', $source, $path);
        $this->assertStringNotContainsString("_('Choose the Hosting Provider')", $source, $path);
    }

    // Before the update, Change Hosting Service isn't offered, and a direct post of it is refused whatever it carries
    public function testChangeHostingServiceNeedsTheUpdate()
    {
        $index = file_get_contents(DIR_ROOT . '/bulk/index.php');
        $this->assertStringContainsString("if (DomainMOD\Hosting::isAvailable()) echo \$form->showDropdownOption('CWH', _('Change Hosting Service'), \$action);", $index);
        $bulk = file_get_contents(DIR_ROOT . '/bulk/main/index.php');
        $this->assertStringContainsString("if (\$hosting_available) echo \$form->showDropdownOption('CWH', _('Change Hosting Service'), \$action);", $bulk);
        $refusal = strpos($bulk, "if (\$action == 'CWH' && !\$hosting_available) {");
        $this->assertNotFalse($refusal);
        $this->assertStringContainsString("\$action = '';\n    \$is_submitted = 0;", substr($bulk, $refusal, 300));
        // It comes before anything acts on the post
        $this->assertLessThan(strpos($bulk, "if (\$_SERVER['REQUEST_METHOD'] == 'POST' && \$is_submitted === 1) {"), $refusal);
        foreach (array("_('The following domains had their Hosting Service changed')", "_('Hosting Service Changed')",
                     "_('Change Hosting Service')") as $text) {
            $this->assertStringContainsString($text, $bulk);
        }
        $this->assertStringNotContainsString('Change Hosting Provider', $bulk);
        $this->assertStringNotContainsString('Change Hosting Provider', $index);
    }

    // The main footer loads the searchable menus' script after Tom Select, through assetUrl(), and the script copies
    // a field error's attributes to Tom Select's box
    public function testTheSearchableMenuWorksOnEveryPage()
    {
        $footer = file_get_contents(DIR_ROOT . '/_includes/layout/footer.inc.php');
        $tom = strpos($footer, "assetUrl(WEBROOT_THEME . '/plugins/tom-select/js/tom-select.complete.min.js')");
        $script = strpos($footer, "assetUrl('js/search-select.js')");
        $this->assertNotFalse($tom);
        $this->assertNotFalse($script);
        $this->assertLessThan($script, $tom);
        $js = file_get_contents(DIR_ROOT . '/js/search-select.js');
        $this->assertStringContainsString("['aria-invalid', 'aria-describedby'].forEach(function (attribute) {", $js);
        $this->assertStringContainsString('tom_select.control_input.setAttribute(attribute, select.getAttribute(attribute));', $js);
    }

    // The menu: optional (no required mark), searchable, "None" first, then the services as given
    public function testTheServiceMenuStartsWithNone()
    {
        $html = (new Form())->showHostingServiceMenu('new_service_id', 9, array(4 => 'Shared (Acme, Greg, greg)',
            9 => 'VPS (Acme, Greg, greg)'));
        $this->assertStringContainsString('domainmod-search-select', $html);
        $this->assertStringContainsString('Hosting Service', $html);
        $this->assertStringNotContainsString('*', strip_tags($html));
        $this->assertSame(3, preg_match_all('/<option value="(\d+)"[^>]*?( selected)?>([^<]*)<\/option>/', $html, $options));
        $this->assertSame(array('0', '4', '9'), $options[1]);
        $this->assertSame(array('None', 'Shared (Acme, Greg, greg)', 'VPS (Acme, Greg, greg)'), $options[3]);
        $this->assertSame(array('', '', ' selected'), $options[2]);
    }

    // The CSV importer still takes the hosting provider columns, so older files import, but creates no provider
    public function testTheCsvImporterIgnoresTheProviderColumns()
    {
        $csv = file_get_contents(DIR_ROOT . '/classes/DomainMOD/Csv.php');
        $this->assertStringNotContainsString('$row[16]', $csv);
        $this->assertStringNotContainsString('$row[17]', $csv);
        $this->assertStringNotContainsString('$row[18]', $csv);
        $this->assertStringContainsString('$registrar = $row[19];', $csv);
        $add = file_get_contents(DIR_ROOT . '/classes/DomainMOD/DomainAdd.php');
        foreach (array('createHost(', 'checkForHostByName(', 'getHostId(', 'hosting_provider', '$host_id') as $text) {
            $this->assertStringNotContainsString($text, $add, $text);
        }
        $parameters = array_map(function ($parameter) {
            return $parameter->getName();
        }, (new \ReflectionMethod(\DomainMOD\Domain::class, 'addDomain'))->getParameters());
        $this->assertNotContains('hosting_id', $parameters);
        $this->assertSame(18, count($parameters));
        // The template keeps its shape
        $sample = file(DIR_ROOT . '/import-template-sample.csv');
        $this->assertNotFalse($sample);
        $this->assertSame(64, count(str_getcsv($sample[0], ',', '"', '')));
    }

    // The service delete's unlink changes only the given domains that still have that service
    public function testClearHostingServiceUnlinksOnlyTheGivenDomains()
    {
        $pdo = new \DomainMOD\Tests\Support\RecordingPdo();
        $domain = \DomainMOD\Tests\Support\Instance::withoutConstructor(\DomainMOD\Domain::class);
        $domain->deeb = (object) array('cnxx' => $pdo);
        $domain->time = new class {
            public function stamp() { return '2026-11-21 10:00:00'; }
        };

        $this->assertSame(0, $domain->clearHostingService(array(), 4));
        $this->assertSame(0, $domain->clearHostingService(array(1, 2), 0));
        $this->assertSame(array(), $pdo->log);

        $domain->clearHostingService(array('3', 1, 3), 4);
        $this->assertCount(1, $pdo->log);
        $sql = preg_replace('/\s+/', ' ', $pdo->log[0]['sql']);
        $this->assertStringContainsString('UPDATE domains SET service_id = 0, update_time = :update_time WHERE id IN (3,1) AND service_id = :service_id', $sql);
        $this->assertSame(4, $pdo->log[0]['params']['service_id']);
    }
} //@formatter:on
