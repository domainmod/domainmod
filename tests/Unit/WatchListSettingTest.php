<?php
/**
 * /tests/Unit/WatchListSettingTest.php
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
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\WatchCheck;
use DomainMOD\WatchList;
use PHPUnit\Framework\TestCase;

/*
 * System Settings' "Use the Watch List" (settings.use_watch_list, on by default). WatchList::isEnabled() is on only when
 * the database has the Watch List and the setting is on (a setting that can't be read counts as on). Off, the menu item,
 * the Dashboard tile, the pages, and the task's work all stop, and nothing deletes an entry, so turning it back on brings
 * everything back.
 */
class WatchListSettingTest extends TestCase
{
    protected function tearDown(): void
    {
        WatchList::resetAvailable();
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
    }

    protected function source($path)
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    // isEnabled() with the database at 4.27.0 and the setting's stored value (or a read that fails)
    protected function enabledWith($answer)
    {
        $pdo = new RecordingPdo(array('SELECT use_watch_list FROM settings' => $answer));
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, Instance::withoutConstructor(Database::class, array('cnxx' => $pdo)));
        WatchList::resetAvailable(true);
        return WatchList::isEnabled();
    }

    public function testTheSettingTurnsTheWatchListOnAndOff()
    {
        $this->assertTrue($this->enabledWith(array(array(array('use_watch_list' => '1')))));
        $this->assertFalse($this->enabledWith(array(array(array('use_watch_list' => '0')))));
        // No settings row yet, or a column that isn't there, counts as on (the default)
        $this->assertTrue($this->enabledWith(array(false)));
        $this->assertTrue($this->enabledWith(new \PDOException('Unknown column')));
    }

    // The task reads the setting as it is now, so a long cron run that read it earlier doesn't run the checks after the
    // setting was turned off
    public function testAFreshReadSeesTheSettingChange()
    {
        $answers = array(array(array('use_watch_list' => '1')), array(array('use_watch_list' => '0')));
        $pdo = new RecordingPdo(array('SELECT use_watch_list FROM settings' => $answers));
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, Instance::withoutConstructor(Database::class, array('cnxx' => $pdo)));
        WatchList::resetAvailable(true);
        $this->assertTrue(WatchList::isEnabled());
        $this->assertTrue(WatchList::isEnabled());
        $this->assertFalse(WatchList::isEnabled(true));
        $this->assertStringContainsString("if (!WatchList::isEnabled(true)) return \$summary + array('off' => true);", $this->source('classes/DomainMOD/WatchCheck.php'));
    }

    public function testItsOffBeforeTheDatabaseUpdate()
    {
        WatchList::resetAvailable(false);
        $this->assertFalse(WatchList::isEnabled());
    }

    // Off, the task does nothing and needs no time, without a query of the list
    public function testTheTaskDoesNothingWhileOff()
    {
        $pdo = new RecordingPdo(array('SELECT use_watch_list FROM settings' => array(array(array('use_watch_list' => '0')))));
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, Instance::withoutConstructor(Database::class, array('cnxx' => $pdo)));
        WatchList::resetAvailable(true);
        $this->assertFalse(WatchCheck::reserveNeeded($pdo));
        $check = Instance::withoutConstructor(WatchCheck::class, array('pdo' => $pdo));
        $this->assertSame(array('lookups' => 0, 'archived' => 0, 'schedule' => 0, 'off' => true), $check->run(true));
        $this->assertSame(array(), $pdo->statementsWith('watch_domains'));
    }

    // Every entry point checks the setting, not just the database update
    public function testTheEntryPointsCheckTheSetting()
    {
        $this->assertStringContainsString('if (DomainMOD\\WatchList::isEnabled()) { ?>', $this->source('_includes/layout/menu-main.inc.php'));
        $this->assertStringContainsString('if (DomainMOD\\WatchList::isEnabled()) {', $this->source('dashboard/index.php'));
        $this->assertStringContainsString('if (!DomainMOD\\WatchList::isEnabled()) {', $this->source('watch/list-data.php'));
        foreach (array('watch/index.php', 'watch/add.php', 'watch/edit.php') as $page) {
            $source = $this->source($page);
            $this->assertStringContainsString("\$watch_off = \$watch_available && !DomainMOD\\WatchList::isEnabled();\nif (\$watch_off) \$watch_available = false;", $source, $page);
            $this->assertStringContainsString("echo DomainMOD\\WatchList::offState(\$layout, (\$_SESSION['s_is_admin'] ?? 0) === 1);", $source, $page);
        }
        $this->assertStringContainsString('DomainMOD\\WatchList::isEnabled()', $this->source('domains/add.php'));
        $this->assertStringContainsString('DomainMOD\\WatchList::isEnabled()', $this->source('queue/add/rdap.php'));
        $run = $this->source('admin/scheduler/run.php');
        // As a warning, which the htmx Run Now's result shows (an info message would wait for the next page)
        $this->assertStringContainsString("\$_SESSION['s_message_warning'] .= _('The Watch List is turned off in System Settings, so nothing was checked.')", $run);
        // Run Now's message follows the run's own reading of the setting
        $this->assertStringContainsString("if (empty(\$watch_summary['off'])) {", $run);
    }

    // The setting is saved by its own form, only by an administrator who can change data, checked with a locking read
    public function testTheSettingsFormSavesItForAnAdministrator()
    {
        $source = $this->source('admin/settings/index.php');
        $this->assertStringContainsString("if (\$_SERVER['REQUEST_METHOD'] == 'POST' && (\$_POST['watch_form'] ?? '') === '1') {", $source);
        $this->assertStringContainsString("if (\$watch_user && (int) \$watch_user->admin === 1 && (int) \$watch_user->read_only === 0) {", $source);
        $this->assertStringContainsString('SET use_watch_list = :use_watch_list,', $source);
        $this->assertStringContainsString("'new_use_watch_list', DomainMOD\\WatchList::isEnabled() ? 1 : 0,", $source);
        $this->assertStringContainsString("\$layout->formSection(_('Watch List'), 'watch-list',", $source);
        // Never on the demo
        $this->assertStringContainsString('$watch_available = DomainMOD\\WatchList::isAvailable() && DEMO_INSTALLATION == false;', $source);
    }

    // A fresh install has the column, on, as the database update adds it (the held install patch, before the release)
    public function testTheInstallerHasTheSetting()
    {
        $source = $this->source('install/go.php');
        if (strpos($source, '`watch_domains`') === false) $this->markTestSkipped('install/go.php has no Watch List yet (its held patch)');
        $this->assertStringContainsString("`history_retention_months` INT(10) UNSIGNED NOT NULL DEFAULT '36',\n            `use_watch_list` TINYINT(1) NOT NULL DEFAULT '1',", $source);
    }

} //@formatter:on
