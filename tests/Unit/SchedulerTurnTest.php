<?php
/**
 * /tests/Unit/SchedulerTurnTest.php
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

use DomainMOD\Scheduler;
use DomainMOD\Tests\Support\FullDiskStream;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * Who runs the turn tasks when cron runs overlap (Scheduler::takeTurn(), the turn lock, the claims, and
 * temp/scheduler-turn): a contested run runs a turn task only once it has claimed it, the holder claims its owner as
 * it takes the turn, every claim goes once the run is done with its task, the turn moves only for a task that had its
 * turn, and a run that can't use the file still takes turns, starting the cycle at a random task. The database is a RecordingPdo whose named locks
 * are free unless the test lists them in $held (another run holds them), and temp/ is a folder of the test's own.
 */
#[RunTestsInSeparateProcesses]
class SchedulerTurnTest extends TestCase
{
    // The time limit before each test, put back afterwards (some tests set their own)
    protected $max_execution_time;
    // The turn locks another run holds, by part ('' for the turn lock, or a task's slug for its claim), and those a third
    // run holds
    protected $held = array();
    protected $third = array();

    protected function setUp(): void
    {
        define('DIR_TEMP', sys_get_temp_dir() . '/domainmod-scheduler-turn-' . getmypid());
        define('DB_NAME', 'domainmod_test');
        @mkdir(DIR_TEMP);
        $this->clean();
        $this->max_execution_time = ini_get('max_execution_time');
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, null);
        ini_set('max_execution_time', $this->max_execution_time);
        $this->clean();
        @rmdir(DIR_TEMP);
    }

    private function clean()
    {
        $path = DIR_TEMP . '/scheduler-turn';
        if (is_dir($path)) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    // The part of a turn lock's name after the prefix: '' for the turn lock, or a task's slug
    private function part($lock_name)
    {
        $prefix = 'domainmod_turn_' . substr(md5('domainmod_test'), 0, 12);
        $this->assertStringStartsWith($prefix, $lock_name);
        return ltrim(substr($lock_name, strlen($prefix)), '_');
    }

    // A connection whose turn locks are free unless they're in $this->held, with $overrides added to its answers
    private function pdo(array $overrides = array())
    {
        return new RecordingPdo($overrides + array(
            'GET_LOCK' => function ($sql, $params) {
                $part = $this->part($params['lock_name']);
                return array(array('got' => in_array($part, $this->held, true) || in_array($part, $this->third, true) ? 0 : 1));
            },
            'IS_USED_LOCK' => function ($sql, $params) {
                $part = $this->part($params['lock_name']);
                return array(array('holder' => in_array($part, $this->held, true) ? 5 : (in_array($part, $this->third, true) ? 7 : null)));
            },
        ));
    }

    // The locks a statement ($text, such as GET_LOCK) was sent for, by part, in order
    private function parts(RecordingPdo $pdo, $text)
    {
        return array_map(function ($entry) {
            return $this->part($entry['params']['lock_name']);
        }, $pdo->statementsWith($text));
    }

    // A Scheduler on $pdo whose random start, used without the file, is $start
    private function scheduler(RecordingPdo $pdo, $start = 'domain-queue')
    {
        $scheduler = new class($pdo) extends Scheduler {
            public static $start = 'domain-queue';

            public function __construct($pdo)
            {
                $this->deeb = (object) array('cnxx' => $pdo);
            }

            // takeTurn() as turn() calls it, after setting up the run's turn, with the run's due turn tasks ($due, by
            // default every task that isn't waiting)
            public function take(array $states, ?array $due = null)
            {
                $due = $due ?? array_keys(array_filter($states, function ($state) {
                    return $state !== 'waiting';
                }));
                (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => '', 'handle' => null,
                    'turn_lock' => false, 'claims' => array(), 'pointer' => '', 'stored' => '', 'ran' => array(),
                    'locked_out' => false, 'due' => $due));
                return $this->takeTurn($states, $due);
            }

            protected static function randomTurn()
            {
                return self::$start;
            }
        };
        $scheduler::$start = $start;
        return $scheduler;
    }

    private function eligible()
    {
        return array_fill_keys(Scheduler::TURN_TASKS, 'eligible');
    }

    private function stored()
    {
        return file_get_contents(DIR_TEMP . '/scheduler-turn');
    }

    // As turn() leaves a run that takes no turns, after the turn tasks in $ran ran
    private function uncontested(array $ran)
    {
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => '', 'handle' => null,
            'turn_lock' => false, 'claims' => array(), 'pointer' => '', 'stored' => '', 'ran' => array_fill_keys($ran, true),
            'locked_out' => false, 'due' => $ran));
    }

    public function testARunThatFindsTheTurnHeldRunsOnlyTheTurnTasksItCanClaim()
    {
        ini_set('max_execution_time', '30');
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'watch-list');
        // The holder claimed the Domain Queue and the Watch List, so the other two became due after its run began
        $this->held = array('', 'domain-queue', 'watch-list');
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);

        $this->assertSame('', $scheduler->take($this->eligible()));
        $this->assertSame(array(''), $this->parts($pdo, 'GET_LOCK'));

        $this->assertTrue($scheduler->leftToOtherRun('domain-queue'));
        $this->assertFalse($scheduler->leftToOtherRun('ssl-queue'));
        $this->assertFalse($scheduler->leftToOtherRun('domain-refresh'));
        $this->assertTrue($scheduler->leftToOtherRun('watch-list'));
        $this->assertFalse($scheduler->leftToOtherRun('cleanup'));
        // It claimed the two it runs, and asked about nothing else
        $this->assertSame(array('', 'domain-queue', 'ssl-queue', 'domain-refresh', 'watch-list'), $this->parts($pdo, 'GET_LOCK'));
        // A task before them keeps time for the largest needs of the turn tasks that haven't run
        Scheduler::noteRan('ssl-queue');
        $this->assertSame(max(Scheduler::turnNeeds('domain-refresh'), Scheduler::turnNeeds('watch-list')),
            Scheduler::reserveFor('cleanup'));

        // This run is done with the SSL Queue, and the holder with the Watch List, which this run can now claim (its own
        // task lock and reread then decide whether it runs)
        $scheduler->releaseClaim('ssl-queue');
        $this->held = array('', 'domain-queue');
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));

        // It never moves the turn, even after the task it points at ran here and the holder has gone, and lets go only
        // of the claims it still has
        Scheduler::noteRan('watch-list');
        $this->held = array();
        $asked = count($pdo->statementsWith('GET_LOCK'));
        $scheduler->finishTurn();
        $this->assertCount($asked, $pdo->statementsWith('GET_LOCK'));
        $this->assertSame('watch-list', $this->stored());
        $this->assertSame(array('ssl-queue', 'domain-refresh', 'watch-list'), $this->parts($pdo, 'RELEASE_LOCK'));
        $this->assertNull((new \ReflectionProperty(Scheduler::class, 'turn'))->getValue());
    }

    public function testTheHolderClaimsItsOwnerAtOnceAndItsOtherTasksAsItReachesThem()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'ssl-queue');
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);
        // Refresh Domains is due without work, and the Watch List is off
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'eligible', 'domain-refresh' => 'passed',
            'watch-list' => 'passed');

        $this->assertSame('ssl-queue', $scheduler->take($states, array('domain-queue', 'ssl-queue', 'domain-refresh')));
        $this->assertSame(array('', 'ssl-queue'), $this->parts($pdo, 'GET_LOCK'));
        $this->assertFalse($scheduler->leftToOtherRun('domain-queue'));
        $this->assertFalse($scheduler->leftToOtherRun('ssl-queue'));
        $this->assertFalse($scheduler->leftToOtherRun('domain-refresh'));
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));
        // The owner isn't asked for again, and a task that isn't due is never claimed
        $this->assertSame(array('', 'ssl-queue', 'domain-queue', 'domain-refresh'), $this->parts($pdo, 'GET_LOCK'));
        $this->assertSame(Scheduler::turnNeeds('ssl-queue'), Scheduler::reserveFor('domain-queue'));

        // The file is locked while it holds the turn
        $other = fopen(DIR_TEMP . '/scheduler-turn', 'r');
        $this->assertFalse(flock($other, LOCK_EX | LOCK_NB));

        Scheduler::noteRan('ssl-queue');
        $scheduler->finishTurn();
        $this->assertSame('domain-refresh', $this->stored());
        // The claims, then the turn lock, with the file already let go of
        $this->assertSame(array('ssl-queue', 'domain-queue', 'domain-refresh', ''), $this->parts($pdo, 'RELEASE_LOCK'));
        $this->assertTrue(flock($other, LOCK_EX | LOCK_NB));
        fclose($other);
    }

    public function testATaskAnotherRunClaimedFirstWaitsForItsTurn()
    {
        // A run that found the turn held claimed the Watch List, whose turn it is, before this run took the turn
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'watch-list');
        $this->held = array('watch-list');
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);

        $this->assertSame('domain-queue', $scheduler->take($this->eligible()));
        $this->assertSame(array('', 'watch-list', 'domain-queue'), $this->parts($pdo, 'GET_LOCK'));
        $this->assertTrue($scheduler->leftToOtherRun('watch-list'));
        Scheduler::noteRan('domain-queue');
        $scheduler->finishTurn();
        $this->assertSame('watch-list', $this->stored());

        // Let go of before this run reaches it, the Watch List is claimed and run here after all, but with only the time
        // left after the owner, so it doesn't count as its turn
        $scheduler = $this->scheduler($pdo);
        $this->assertSame('domain-queue', $scheduler->take($this->eligible()));
        $this->held = array();
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));
        Scheduler::noteRan('domain-queue');
        Scheduler::noteRan('watch-list');
        $scheduler->finishTurn();
        $this->assertSame('watch-list', $this->stored());

        // With every eligible task claimed elsewhere, no task owns the run
        $this->held = array('domain-queue', 'ssl-queue', 'domain-refresh', 'watch-list');
        $this->assertSame('', $this->scheduler($this->pdo())->take($this->eligible()));
    }

    public function testTheHolderMovesTheTurnOnlyWhenTheTaskItPointsAtRanInIt()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'domain-refresh');
        $scheduler = $this->scheduler($this->pdo());

        // The task the turn points at never ran in this run (noteRan() wasn't called for it), so it keeps its turn
        $this->assertSame('domain-refresh', $scheduler->take($this->eligible()));
        Scheduler::noteRan('domain-queue');
        $scheduler->finishTurn();
        $this->assertSame('domain-refresh', $this->stored());

        $this->assertSame('domain-refresh', $scheduler->take($this->eligible()));
        Scheduler::noteRan('domain-refresh');
        $scheduler->finishTurn();
        $this->assertSame('watch-list', $this->stored());
    }

    public function testADatabaseErrorTakesNoTurnAndLeavesNothing()
    {
        // Asking for the turn lock fails
        $scheduler = $this->scheduler($this->pdo(array('GET_LOCK' => new \PDOException('server gone away'))));
        $this->assertSame('', $scheduler->take($this->eligible()));
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));
        $this->assertFileDoesNotExist(DIR_TEMP . '/scheduler-turn');

        // Asking whether the claims are held fails
        $this->held = array('');
        $scheduler = $this->scheduler($this->pdo(array('IS_USED_LOCK' => new \PDOException('server gone away'))));
        $this->assertSame('', $scheduler->take($this->eligible()));
        $this->assertFalse($scheduler->leftToOtherRun('domain-queue'));

        // A claim that can't be asked for leaves its task to the other run
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);
        $this->assertSame('', $scheduler->take($this->eligible()));
        (new \ReflectionProperty(RecordingPdo::class, 'answers'))->setValue($pdo, array('GET_LOCK' => new \PDOException('server gone away')));
        $this->assertTrue($scheduler->leftToOtherRun('ssl-queue'));
    }

    public function testATurnLockHeldWithNoClaimsIsTriedAgain()
    {
        // A run that takes no turns is moving the turn, and lets go of the lock after the first try
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'watch-list');
        $tries = 0;
        $pdo = $this->pdo(array(
            'GET_LOCK' => function ($sql, $params) use (&$tries) {
                if ($this->part($params['lock_name']) !== '') return array(array('got' => 1));
                return array(array('got' => ++$tries > 1 ? 1 : 0));
            },
        ));
        $scheduler = $this->scheduler($pdo);
        $this->assertSame('watch-list', $scheduler->take($this->eligible()));
        $this->assertSame(array('', '', 'watch-list'), $this->parts($pdo, 'GET_LOCK'));

        // A claim by a run that isn't holding the turn says nothing about it, so it's tried again
        $this->held = array('');
        $this->third = array('watch-list');
        $tries = 0;
        $pdo = $this->pdo(array(
            'GET_LOCK' => function ($sql, $params) use (&$tries) {
                $part = $this->part($params['lock_name']);
                if ($part === 'watch-list') return array(array('got' => 0));
                if ($part !== '') return array(array('got' => 1));
                return array(array('got' => ++$tries > 1 ? 1 : 0));
            },
        ));
        $this->assertSame('domain-queue', $this->scheduler($pdo)->take($this->eligible()));
        $this->third = array();

        // A claim the holder has on a task that isn't due in this run still means the turn is really held
        $this->held = array('', 'watch-list');
        $started = microtime(true);
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'eligible', 'domain-refresh' => 'passed',
            'watch-list' => 'waiting');
        $this->assertSame('', $this->scheduler($this->pdo())->take($states));
        $this->assertLessThan(1, microtime(true) - $started);

        // Held with no claims past TURN_RETRY_WAIT, it takes no turn, and claims each task as it reaches it
        $this->held = array('');
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);
        $started = microtime(true);
        $this->assertSame('', $scheduler->take($this->eligible()));
        $this->assertGreaterThanOrEqual(Scheduler::TURN_RETRY_WAIT, microtime(true) - $started);
        $this->assertLessThan(Scheduler::TURN_RETRY_WAIT + 1, microtime(true) - $started);
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));
    }

    public function testARunThatTakesNoTurnsMovesTheTurnUnderTheTurnLock()
    {
        ini_set('max_execution_time', '30');
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'domain-refresh');

        // It claims nothing, so it never leaves a task
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);
        $this->uncontested(array('domain-queue'));
        $this->assertFalse($scheduler->leftToOtherRun('domain-queue'));
        $this->assertCount(0, $pdo->log);

        // A turn task ran, but not the one the turn points at
        $scheduler->finishTurn();
        $this->assertSame('domain-refresh', $this->stored());
        $this->assertSame(array(''), $this->parts($pdo, 'GET_LOCK'));
        $this->assertSame(array(''), $this->parts($pdo, 'RELEASE_LOCK'));

        $this->uncontested(array('domain-refresh'));
        $this->scheduler($this->pdo())->finishTurn();
        $this->assertSame('watch-list', $this->stored());

        // With the turn held by another run, it leaves the turn as it is
        $this->held = array('');
        $pdo = $this->pdo();
        $this->uncontested(array('watch-list'));
        $this->scheduler($pdo)->finishTurn();
        $this->assertSame('watch-list', $this->stored());
        $this->assertCount(0, $pdo->statementsWith('RELEASE_LOCK'));

        // Without a time limit, or with no turn task run, it touches nothing
        $this->held = array();
        foreach (array(array('0', array('watch-list')), array('30', array())) as list($limit, $ran)) {
            ini_set('max_execution_time', $limit);
            $pdo = $this->pdo();
            $this->uncontested($ran);
            $this->scheduler($pdo)->finishTurn();
            $this->assertSame('watch-list', $this->stored());
            $this->assertCount(0, $pdo->log);
        }
    }

    public function testTheHolderLetsGoOfEachClaimOnceItsDoneWithTheTask()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'domain-queue');
        $pdo = $this->pdo();
        $scheduler = $this->scheduler($pdo);
        $this->assertSame('domain-queue', $scheduler->take($this->eligible()));

        $this->assertFalse($scheduler->leftToOtherRun('ssl-queue'));
        $scheduler->releaseClaim('domain-queue');
        $scheduler->releaseClaim('domain-queue');
        $scheduler->releaseClaim('cleanup');
        $this->assertSame(array('domain-queue'), $this->parts($pdo, 'RELEASE_LOCK'));

        // Only the claims still held go at the end, then the turn lock
        Scheduler::noteRan('domain-queue');
        $scheduler->finishTurn();
        $this->assertSame(array('domain-queue', 'ssl-queue', ''), $this->parts($pdo, 'RELEASE_LOCK'));

        // A run without claims has none to let go of
        $scheduler->releaseClaim('ssl-queue');
        $this->assertCount(3, $pdo->statementsWith('RELEASE_LOCK'));
    }

    public function testAClaimThatFailsToGoBackIsTriedAgainAtTheEnd()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'domain-queue');
        $fail = true;
        $released = array();
        $pdo = $this->pdo(array(
            'RELEASE_LOCK' => function ($sql, $params) use (&$fail, &$released) {
                $part = $this->part($params['lock_name']);
                if ($part === 'ssl-queue' && $fail) throw new \PDOException('query interrupted');
                $released[] = $part;
                return array(array('released' => 1));
            },
        ));
        $scheduler = $this->scheduler($pdo);
        $this->assertSame('domain-queue', $scheduler->take($this->eligible()));
        $this->assertFalse($scheduler->leftToOtherRun('ssl-queue'));

        $scheduler->releaseClaim('ssl-queue');
        $fail = false;
        $scheduler->finishTurn();
        $this->assertSame(array('domain-queue', 'ssl-queue', ''), $released);
    }

    public function testAClaimThatFailsToGoBackKeepsTheTurnLockButLetsGoOfTheRest()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'domain-queue');
        $released = array();
        $pdo = $this->pdo(array(
            'RELEASE_LOCK' => function ($sql, $params) use (&$released) {
                $part = $this->part($params['lock_name']);
                if ($part === 'domain-queue') throw new \PDOException('query interrupted');
                $released[] = $part;
                return array(array('released' => 1));
            },
        ));
        $scheduler = $this->scheduler($pdo);

        $this->assertSame('domain-queue', $scheduler->take($this->eligible()));
        $this->assertFalse($scheduler->leftToOtherRun('ssl-queue'));
        $scheduler->finishTurn();
        $this->assertSame(array('ssl-queue'), $released);
    }

    public function testAnOwnerClaimThatFailsTakesNoTurnAndLetsGoOfEverything()
    {
        file_put_contents(DIR_TEMP . '/scheduler-turn', 'watch-list');
        $pdo = $this->pdo(array(
            'GET_LOCK' => function ($sql, $params) {
                if ($this->part($params['lock_name']) === 'watch-list') throw new \PDOException('query interrupted');
                return array(array('got' => 1));
            },
        ));
        $scheduler = $this->scheduler($pdo);

        $this->assertSame('', $scheduler->take($this->eligible()));
        $this->assertSame(array(''), $this->parts($pdo, 'RELEASE_LOCK'));
        $this->assertFalse($scheduler->leftToOtherRun('watch-list'));
        $handle = fopen(DIR_TEMP . '/scheduler-turn', 'r');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));
        fclose($handle);
    }

    public function testWithoutTheFileEveryTaskWithWorkStillGetsTurns()
    {
        // Something the file can't be made over, as in a temp folder the cron user can't write
        mkdir(DIR_TEMP . '/scheduler-turn');
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'passed', 'domain-refresh' => 'eligible',
            'watch-list' => 'eligible');
        $owners = array();
        foreach (Scheduler::TURN_TASKS as $start) {
            $pdo = $this->pdo();
            $scheduler = $this->scheduler($pdo, $start);
            $owner = $scheduler->take($states);
            $owners[$owner] = true;

            // It holds the turn, claims its owner, and keeps time for it
            $this->assertSame(array('', $owner), $this->parts($pdo, 'GET_LOCK'));
            if ($owner === 'watch-list') $this->assertSame(Scheduler::turnNeeds('watch-list'), Scheduler::reserveFor('domain-refresh'));
            Scheduler::noteRan($owner);
            $scheduler->finishTurn();
            $this->assertSame(array($owner, ''), $this->parts($pdo, 'RELEASE_LOCK'));
            $this->assertDirectoryExists(DIR_TEMP . '/scheduler-turn');
        }
        ksort($owners);
        $this->assertSame(array('domain-queue', 'domain-refresh', 'watch-list'), array_keys($owners));
    }

    public function testANewOrEmptyFileStartsTheCycleAtARandomTaskAndKeepsWhereItPoints()
    {
        foreach (array(null, '', 'nonsense') as $contents) {
            $this->clean();
            if ($contents !== null) file_put_contents(DIR_TEMP . '/scheduler-turn', $contents);
            $scheduler = $this->scheduler($this->pdo(), 'domain-refresh');
            $this->assertSame('domain-refresh', $scheduler->take($this->eligible()));
            Scheduler::noteRan('domain-refresh');
            $scheduler->finishTurn();
            $this->assertSame('watch-list', $this->stored());
        }

        // A random start at a task that's waiting keeps the turn pointed at it, written for the next run
        file_put_contents(DIR_TEMP . '/scheduler-turn', '');
        $scheduler = $this->scheduler($this->pdo(), 'domain-refresh');
        $states = array('domain-queue' => 'eligible', 'ssl-queue' => 'passed', 'domain-refresh' => 'waiting',
            'watch-list' => 'eligible');
        $this->assertSame('watch-list', $scheduler->take($states));
        Scheduler::noteRan('watch-list');
        $scheduler->finishTurn();
        $this->assertSame('domain-refresh', $this->stored());

        // So does a run that takes no turns
        ini_set('max_execution_time', '30');
        file_put_contents(DIR_TEMP . '/scheduler-turn', '');
        $this->uncontested(array('domain-queue'));
        $this->scheduler($this->pdo(), 'ssl-queue')->finishTurn();
        $this->assertSame('ssl-queue', $this->stored());
    }

    public function testAFailedWriteLeavesTheOldTurnInTheFile()
    {
        stream_wrapper_register('fulldisk', FullDiskStream::class);
        FullDiskStream::$contents = 'domain-refresh';
        (new \ReflectionProperty(Scheduler::class, 'turn'))->setValue(null, array('owner' => 'domain-refresh',
            'handle' => fopen('fulldisk://scheduler-turn', 'r+'), 'turn_lock' => true, 'claims' => array(),
            'pointer' => 'domain-refresh', 'stored' => 'domain-refresh', 'ran' => array('domain-refresh' => true),
            'locked_out' => false, 'due' => array('domain-refresh')));

        $this->scheduler($this->pdo())->finishTurn();
        $this->assertSame('domain-refresh', FullDiskStream::$contents);
        stream_wrapper_unregister('fulldisk');
    }

    public function testTheRandomStartCanBeAnyTurnTask()
    {
        $random = new \ReflectionMethod(Scheduler::class, 'randomTurn');
        $seen = array();
        for ($i = 0; $i < 400 && count($seen) < count(Scheduler::TURN_TASKS); $i++) $seen[$random->invoke(null)] = true;
        ksort($seen);
        $sorted = Scheduler::TURN_TASKS;
        sort($sorted);
        $this->assertSame($sorted, array_keys($seen));
    }

} //@formatter:on
