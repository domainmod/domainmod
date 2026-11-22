<?php
/**
 * /tests/Unit/MaintenancePageTest.php
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
 * The Maintenance page (maintenance/index.php), the landing page of the sidebar's Maintenance section. It isn't a class,
 * so these tests read its source: it lists its tasks in the menu's order under a Maintenance Tasks heading, and a
 * read-only user, who can't run them, gets a note instead of the links (the menu leaves them out too).
 */
class MaintenancePageTest extends TestCase
{
    const TASKS = array('update-domain-fees.php', 'update-hosting-fees.php', 'update-ssl-fees.php', 'update-conversions.php');

    private function source($path)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $this->assertNotFalse($source, $path);
        return $source;
    }

    // The task files, in the order their links first appear in $source
    private function taskOrder($source)
    {
        $found = array();
        foreach (self::TASKS as $task) {
            $at = strpos($source, $task . '?');
            $this->assertNotFalse($at, $task);
            $found[$at] = $task;
        }
        ksort($found);
        return array_values($found);
    }

    public function testTheTasksFollowTheMenusOrder()
    {
        $this->assertSame(self::TASKS, $this->taskOrder($this->source('maintenance/index.php')));
        $this->assertSame(self::TASKS, $this->taskOrder($this->source('_includes/layout/menu-main.inc.php')));
    }

    public function testTheBoxIsHeadedMaintenanceTasks()
    {
        $source = $this->source('maintenance/index.php');
        $this->assertStringContainsString("<?php echo \$layout->contentBoxTop(_('Maintenance Tasks'), '3'); ?>", $source);
        $this->assertStringNotContainsString('Fees & Rates', $source);
    }

    // A read-only user gets the note, and every task link is in the branch for everyone else
    public function testAReadOnlyUserGetsANoteInsteadOfTheLinks()
    {
        $source = $this->source('maintenance/index.php');
        $this->assertSame(1, preg_match("/if \\(\\(\\\$_SESSION\\['s_read_only'\\] \\?\\? ''\\) == '1'\\) \\{ \\?>\n(.*?)\n<\\?php \\} else \\{ \\?>\n(.*?)\n<\\?php \\} \\?>\n<\\?php echo \\\$layout->contentBoxBottom\\(\\); \\?>/s", $source, $parts));
        $this->assertStringContainsString("<?php echo _('Your account is read-only, so another user who can make changes needs to run these tasks.'); ?>", $parts[1]);
        $this->assertStringNotContainsString('htmxTaskLink', $parts[1]);
        $this->assertSame(count(self::TASKS), substr_count($parts[2], 'htmxTaskLink('));
        $this->assertSame(count(self::TASKS), substr_count($source, 'htmxTaskLink('));
    }
} //@formatter:on
