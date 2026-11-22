<?php
/**
 * /tests/Unit/SchedulerChangeAllTest.php
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

use DomainMOD\Layout;
use DomainMOD\Scheduler;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * Disable All and Enable All on the Task Scheduler page (Scheduler::disableAll(), enableAll(), changeAllMessage(), and
 * addChangeAllAction(), and their handler in admin/scheduler/update.php). DB_NAME and DEMO_INSTALLATION are constants,
 * and page actions are kept in Layout's static properties, so each test runs in its own process.
 */
#[RunTestsInSeparateProcesses]
class SchedulerChangeAllTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('DB_NAME')) define('DB_NAME', 'domainmod_test');
        $_SESSION = array();
    }

    // Every active task is disabled as Disable does it, each under its own lock, which is released afterwards
    public function testDisablesEveryActiveTask()
    {
        $pdo = $this->pdo(array(1, 2, 3));
        $counts = $this->scheduler($pdo)->disableAll();

        $this->assertSame(array('changed' => 3, 'running' => 0), $counts);
        $updates = $pdo->statementsWith('UPDATE scheduler');
        $this->assertSame(array(1, 2, 3), array_column(array_column($updates, 'params'), 'id'));
        $this->assertStringContainsString("SET active = '0',", $updates[0]['sql']);
        $this->assertStringContainsString("next_run = '1970-01-01 00:00:00'", $updates[0]['sql']);
        $this->assertStringContainsString("AND active = '1'", $updates[0]['sql']);
        $this->assertLockedAroundEachUpdate($pdo, 3);
        // Only the active tasks are looked at
        $this->assertStringContainsString("WHERE active = '1'", $pdo->statementsWith('WHERE active =')[0]['sql']);
    }

    // Every inactive task is enabled as Enable does it, with its next run worked out from its own expression and zone
    public function testEnablesEveryInactiveTask()
    {
        $pdo = $this->pdo(array(4, 5));
        $counts = $this->scheduler($pdo)->enableAll();

        $this->assertSame(array('changed' => 2, 'running' => 0), $counts);
        $this->assertStringContainsString("WHERE active = '0'", $pdo->statementsWith('WHERE active =')[0]['sql']);
        $updates = $pdo->statementsWith('UPDATE scheduler');
        $this->assertSame(array(4, 5), array_column(array_column($updates, 'params'), 'id'));
        $this->assertStringContainsString("SET active = '1',", $updates[0]['sql']);
        $this->assertStringContainsString("AND active = '0'", $updates[0]['sql']);
        $this->assertStringContainsString('next_run = :next_run', $updates[0]['sql']);
        // 07:00 in New York is 11:00 or 12:00 UTC, and an every-5-minutes task's next run is on a fifth minute
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d 1[12]:00:00$/', $updates[0]['params']['next_run']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:[0-5][05]:00$/', $updates[1]['params']['next_run']);
        $this->assertLockedAroundEachUpdate($pdo, 2);
    }

    // A task another admin changed after the list was read isn't changed again or counted
    public function testATaskChangedElsewhereIsNotCounted()
    {
        foreach (array('disableAll', 'enableAll') as $method) {
            $pdo = $this->pdo(array(1, 2, 3), array(), array(2));
            $counts = $this->scheduler($pdo)->$method();

            $this->assertSame(array('changed' => 2, 'running' => 0), $counts, $method);
            $this->assertCount(3, $pdo->statementsWith('RELEASE_LOCK'));
        }
    }

    // A running task holds its lock, so it's skipped and left as it is, and its lock is never released by Disable All
    // or Enable All
    public function testSkipsARunningTask()
    {
        foreach (array('disableAll', 'enableAll') as $method) {
            $pdo = $this->pdo(array(1, 2, 3), array(2));
            $counts = $this->scheduler($pdo)->$method();

            $this->assertSame(array('changed' => 2, 'running' => 1), $counts, $method);
            $this->assertSame(array(1, 3), array_column(array_column($pdo->statementsWith('UPDATE scheduler'), 'params'), 'id'));
            $released = array_column(array_column($pdo->statementsWith('RELEASE_LOCK'), 'params'), 'lock_name');
            $this->assertCount(2, $released);
            foreach ($released as $lock_name) {
                $this->assertStringEndsNotWith('_2', $lock_name);
            }
        }
    }

    public function testDoesNothingWhenEveryTaskIsAlreadyChanged()
    {
        foreach (array('disableAll', 'enableAll') as $method) {
            $pdo = $this->pdo(array());
            $counts = $this->scheduler($pdo)->$method();

            $this->assertSame(array('changed' => 0, 'running' => 0), $counts, $method);
            $this->assertSame(array(), $pdo->statementsWith('UPDATE scheduler'));
            $this->assertSame(array(), $pdo->statementsWith('GET_LOCK'));
        }
    }

    // A failed update still releases the task's lock
    public function testReleasesTheLockWhenTheUpdateFails()
    {
        $pdo = $this->pdo(array(1), array(), new \PDOException('The table is locked'));

        try {
            $this->scheduler($pdo)->disableAll();
            $this->fail('The failed update should have been thrown');
        } catch (\PDOException $e) {
            $this->assertCount(1, $pdo->statementsWith('RELEASE_LOCK'));
        }
    }

    public function testTheResultMessages()
    {
        $this->assertSame(array('success', 'All tasks are now disabled.'), Scheduler::changeAllMessage(false, 4, 0));
        $this->assertSame(array('success', 'All tasks were already disabled.'), Scheduler::changeAllMessage(false, 0, 0));
        $this->assertSame(array('warning', '3 tasks were disabled. 1 task is running and was left enabled, so try again once it finishes.'),
            Scheduler::changeAllMessage(false, 3, 1));
        $this->assertSame(array('warning', '1 task was disabled. 2 tasks are running and were left enabled, so try again once they finish.'),
            Scheduler::changeAllMessage(false, 1, 2));
        $this->assertSame(array('warning', '1 task is running and was left enabled, so try again once it finishes.'),
            Scheduler::changeAllMessage(false, 0, 1));

        $this->assertSame(array('success', 'All tasks are now enabled.'), Scheduler::changeAllMessage(true, 10, 0));
        $this->assertSame(array('success', 'All tasks were already enabled.'), Scheduler::changeAllMessage(true, 0, 0));
        $this->assertSame(array('warning', '9 tasks were enabled. 1 task is running and was left disabled, so enable it once it finishes.'),
            Scheduler::changeAllMessage(true, 9, 1));
        $this->assertSame(array('warning', '2 tasks are running and were left disabled, so enable them once they finish.'),
            Scheduler::changeAllMessage(true, 0, 2));
    }

    // Disable All shows while any task is active, as a confirmed action that goes to the handler with the CSRF token
    public function testDisableAllShowsWhileATaskIsActive()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '0';
        $html = $this->pageActions(array('0', '1', '0'));

        $this->assertStringContainsString('Disable All', $html);
        $this->assertStringNotContainsString('Enable All', $html);
        $this->assertStringContainsString('data-bs-toggle="modal"', $html);
        $this->assertStringContainsString('update.php?a=da&amp;csrf_token=' . $_SESSION['s_csrf_token'], $html);
        $this->assertStringContainsString("Disable every task? They won&#039;t run on their schedule until you enable them again, but Run Now still runs them.", $html);
    }

    // Once every task is off, the same spot offers Enable All, and its question names Refresh Domains while it's off
    public function testEnableAllShowsOnceEveryTaskIsDisabled()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '0';
        $html = $this->pageActions(array('0', '0'), array('domain-queue', 'domain-refresh'));

        $this->assertStringContainsString('Enable All', $html);
        $this->assertStringNotContainsString('Disable All', $html);
        $this->assertStringContainsString('update.php?a=ea&amp;csrf_token=' . $_SESSION['s_csrf_token'], $html);
        $this->assertStringContainsString('Enable every task? This includes Refresh Domains, which looks up your domains with registrar APIs, RDAP and WHOIS servers, and DNS resolvers.', $html);
    }

    // Without Refresh Domains (a database that doesn't have it yet), Enable All's question doesn't name it
    public function testEnableAllWithoutRefreshDomains()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '0';
        $html = $this->pageActions(array('0', '0'), array('domain-queue', 'cleanup'));

        $this->assertStringContainsString('Enable every task? Each one will run on its schedule again.', $html);
        $this->assertStringNotContainsString('Refresh Domains', $html);
    }

    // The button flips both ways as the tasks change
    public function testTheButtonFlips()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '0';

        $this->assertStringContainsString('a=da&amp;', $this->pageActions(array('1', '1')));
        $this->assertStringContainsString('a=ea&amp;', $this->pageActions(array('0', '0')));
        $this->assertStringContainsString('a=da&amp;', $this->pageActions(array('0', '1')));
    }

    public function testNothingShowsWithoutTasks()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '0';

        $this->assertSame('', $this->pageActions(array()));
    }

    public function testBothAreHiddenFromReadOnlyUsers()
    {
        define('DEMO_INSTALLATION', false);
        $_SESSION['s_read_only'] = '1';

        $this->assertSame('', $this->pageActions(array('1', '1')));
        $this->assertSame('', $this->pageActions(array('0', '0')));
    }

    public function testBothAreHiddenOnTheDemo()
    {
        define('DEMO_INSTALLATION', true);
        $_SESSION['s_read_only'] = '0';

        $this->assertSame('', $this->pageActions(array('1', '1')));
        $this->assertSame('', $this->pageActions(array('0', '0')));
    }

    // The handler checks the admin, the CSRF token, and read-only users before anything else, refuses the demo before
    // it changes anything, and is reached from the page
    public function testTheHandlerRefusesWhatItShould()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/scheduler/update.php');
        $handler = strpos($source, "if (\$a == 'da' || \$a == 'ea')");
        $this->assertNotFalse($handler);
        foreach (array('$system->checkAdminUser(', '$csrf->check(', '$system->readOnlyCheck(') as $check) {
            $this->assertNotFalse(strpos($source, $check));
            $this->assertLessThan($handler, strpos($source, $check));
        }
        // The demo is refused, and stops there, before anything changes
        $demo = strpos($source, 'if (DEMO_INSTALLATION == true)', $handler);
        $this->assertNotFalse($demo);
        $change = strpos($source, '$counts = $enable ? $schedule->enableAll() : $schedule->disableAll();', $handler);
        $this->assertNotFalse($change);
        $refusal = substr($source, $demo, $change - $demo);
        $this->assertStringContainsString("_('You are not authorized to perform that action.')", $refusal);
        $this->assertStringContainsString("header(\"Location: index.php\");\n        exit;", $refusal);
        // Enable All for 'ea', and the result saved as its own type of message before going back to the page
        $this->assertStringEndsWith("\$enable = \$a == 'ea';\n    ", $refusal);
        $result = substr($source, $change, strpos($source, '// A change waits for a running task') - $change);
        $this->assertStringContainsString("DomainMOD\\Scheduler::changeAllMessage(\$enable, \$counts['changed'], \$counts['running']);", $result);
        $this->assertStringContainsString("\$_SESSION['s_message_' . \$type] = (\$_SESSION['s_message_' . \$type] ?? '') . \$text . '<BR>';", $result);
        $this->assertStringContainsString("header(\"Location: index.php\");\n    exit;", $result);
        // They take their own locks, so the one-task lock check before the other actions leaves them out
        $this->assertStringContainsString("if (in_array(\$a, array('u', 'e', 'd'), true) && !\$schedule->getTaskLock(\$id))", $source);

        $page = file_get_contents(dirname(__DIR__, 2) . '/admin/scheduler/index.php');
        $this->assertStringContainsString('$schedule->addChangeAllAction($layout, $result);', $page);
    }

    // Each task's update runs after its own lock is taken and before that lock is released, so cron can't run the task
    // in between
    private function assertLockedAroundEachUpdate(RecordingPdo $pdo, $tasks)
    {
        $order = array();
        foreach ($pdo->log as $entry) {
            if (strpos($entry['sql'], 'GET_LOCK') !== false) {
                $order[] = 'lock ' . substr($entry['params']['lock_name'], strrpos($entry['params']['lock_name'], '_') + 1);
            } elseif (strpos($entry['sql'], 'UPDATE scheduler') !== false) {
                $order[] = 'update ' . $entry['params']['id'];
            } elseif (strpos($entry['sql'], 'RELEASE_LOCK') !== false) {
                $order[] = 'release ' . substr($entry['params']['lock_name'], strrpos($entry['params']['lock_name'], '_') + 1);
            }
        }
        $expected = array();
        foreach (array_column(array_column($pdo->statementsWith('UPDATE scheduler'), 'params'), 'id') as $id) {
            array_push($expected, 'lock ' . $id, 'update ' . $id, 'release ' . $id);
        }
        $this->assertCount($tasks * 3, $expected);
        $this->assertSame($expected, $order);
    }

    // A Scheduler on a stand-in database
    private function scheduler($pdo)
    {
        return Instance::withoutConstructor(Scheduler::class, array('deeb' => (object) array('cnxx' => $pdo)));
    }

    // A database with these tasks to change, where the tasks in $running hold their locks, and the tasks in $update were
    // changed by someone else after they were read (or every update throws $update). Task 4 runs daily at 07:00 New
    // York time, and every other task every 5 minutes in UTC.
    private function pdo(array $ids, array $running = array(), $update = array())
    {
        $rows = array_map(function ($id) {
            return array('id' => $id);
        }, $ids);

        return new RecordingPdo(array(
            'WHERE active =' => array($rows),
            'WHERE id = :task_id' => function ($sql, $params) {
                return $params['task_id'] === 4
                    ? array(array('id' => 4, 'expression' => '0 7 * * *', 'timezone' => 'America/New_York'))
                    : array(array('id' => $params['task_id'], 'expression' => '*/5 * * * *', 'timezone' => 'UTC'));
            },
            'GET_LOCK' => function ($sql, $params) use ($running) {
                $id = (int) substr($params['lock_name'], strrpos($params['lock_name'], '_') + 1);
                return array(array('lock' => in_array($id, $running, true) ? '0' : '1'));
            },
            'RELEASE_LOCK' => array(array(array('released' => '1'))),
            'UPDATE scheduler' => $update instanceof \Throwable ? $update : function ($sql, $params) use ($update) {
                return in_array($params['id'], $update, true) ? array() : array(array());
            },
        ));
    }

    // The page actions for tasks with these active flags (and slugs, when given)
    private function pageActions(array $active, array $slugs = array())
    {
        $tasks = array();
        foreach ($active as $index => $flag) {
            $tasks[] = (object) array('active' => $flag, 'slug' => $slugs[$index] ?? 'cleanup');
        }
        $reflection = new \ReflectionClass(Layout::class);
        $reflection->setStaticPropertyValue('page_actions', array());

        $layout = new Layout();
        Instance::withoutConstructor(Scheduler::class)->addChangeAllAction($layout, $tasks);
        return $layout->renderPageActions();
    }

} //@formatter:on
