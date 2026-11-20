<?php
/**
 * /tests/Unit/ExportStreamTest.php
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

use DomainMOD\DomainList;
use DomainMOD\Export;
use DomainMOD\ListQuery;
use DomainMOD\Tests\Support\FailingStream;
use DomainMOD\Tests\Support\RecordingExport;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// A streamed export (Export::begin() to finish()): the order of its steps, its snapshot, and what happens when something fails
class ExportStreamTest extends TestCase
{
    private $pdo;
    private $export;
    private $list;

    protected function setUp(): void
    {
        foreach (array('fixed_join_order', 'export_index') as $cache) {
            (new \ReflectionProperty(ListQuery::class, $cache))->setValue(null, array());
        }
        $_SESSION = array('s_message_danger' => '');
        $this->pdo = new RecordingPdo();
        $this->export = new RecordingExport($this->pdo);
        $this->export->begin('index.php?oid=2');
        $this->list = new DomainList($this->pdo, 1, '');
    }

    protected function tearDown(): void
    {
        $this->export->removeFile();
        $_SESSION = array();
    }

    /*
     * Streams $rows rows in chunks of $chunk as a list page does, failing with $error when $fail_at (a row number) is
     * reached, or with $count_error while the header's numbers are read
     */
    private function stream($expected, $rows, $chunk = 2, $fail_at = 0, $error = null, $count_error = null)
    {
        $export = $this->export;

        try {

            $export->startSnapshot($this->list);
            if ($count_error !== null) throw $count_error;
            $file = $export->openStream('domain_results', '1');
            $export->writeRow($file, array('Header'));
            $export->chunkWritten();
            for ($n = 1; $n <= $rows; $n++) {
                if ($n === $fail_at) throw $error ?? new \RuntimeException('Lost connection');
                $export->writeDataRow($file, array('row ' . $n));
                if ($n % $chunk === 0) $export->chunkWritten();
            }
            $export->finish($expected);

        } catch (\Throwable $e) {

            $export->fail($e);

        }
    }

    private function statements()
    {
        return array_column($this->pdo->log, 'sql');
    }

    // The file's lines, after its BOM
    private function lines()
    {
        $written = $this->export->written();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $written);
        return explode("\n", rtrim(substr($written, 3), "\n"));
    }

    public function testAFinishedExportHasNoIncompleteRow()
    {
        $this->stream(3, 3);

        $this->assertSame(array('Header', '"row 1"', '"row 2"', '"row 3"'), $this->lines());
        $this->assertSame(array(), $this->export->logged);
        $this->assertTrue($this->export->fileClosed());
        $this->assertSame('stop', end($this->export->events));
    }

    public function testTheStepsRunInOrder()
    {
        $this->stream(3, 3);

        // The snapshot and the counts first, then the session and buffers, then the file, header, and chunks
        $this->assertSame(array('start request', 'close session', 'buffers', 'open', 'chunk', 'chunk', 'stop'),
            $this->export->events);
        $this->assertSame(array('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', 'COMMIT'), $this->statements());
    }

    public function testAnErrorPartWayRollsBackThenWritesOneIncompleteRowAndLogs()
    {
        $this->stream(5, 5, 2, 3);

        $this->assertSame(array('Header', '"row 1"', '"row 2"', '"' . Export::incompleteText() . '"'), $this->lines());
        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        // The log write came after the rollback
        $this->assertCount(1, $this->export->logged);
        $this->assertSame(count($this->pdo->log), $this->export->logged[0]['statements']);
        $this->assertSame(2, $this->export->logged[0]['extra']['Rows Written']);
        $this->assertSame('Lost connection', $this->export->logged[0]['extra']['Error']);
        $this->assertTrue($this->export->fileClosed());
        $this->assertNull($this->export->redirected_to);
    }

    public function testTheFileStillClosesWhenTheLogWriteFailsToo()
    {
        $this->export->log_error = new \PDOException('Server has gone away');

        $this->stream(5, 5, 2, 3);

        $lines = $this->lines();
        $this->assertSame('"' . Export::incompleteText() . '"', end($lines));
        $this->assertTrue($this->export->fileClosed());
        $this->assertSame('stop', end($this->export->events));
    }

    public function testTheIncompleteRowIsNeverWrittenTwice()
    {
        $this->stream(5, 5, 2, 3);
        $this->export->writeIncompleteRow();
        $this->export->phase = Export::PHASE_OUTPUT;
        $this->assertSame('none', Export::shutdownAction($this->export->phase, array('type' => E_ERROR),
            $this->export->incomplete_attempted, true));

        $this->assertSame(1, substr_count($this->export->written(), Export::incompleteText()));
    }

    public function testARowThatCantBeWrittenIsAnError()
    {
        // A stream that can't be written: the header row fails, and so does the incomplete row, but the log and the
        // close still happen
        $this->export->file_answer = fopen('php://memory', 'r');

        $this->stream(3, 3);

        $this->assertTrue($this->export->incomplete_attempted);
        $this->assertSame('An export row could not be written', $this->export->logged[0]['extra']['Error']);
        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        $this->assertTrue($this->export->fileClosed());
    }

    public function testAFileThatCantBeOpenedIsAnError()
    {
        // openFile() gives false when the BOM can't be written
        $this->export->file_answer = false;

        $this->stream(3, 3);

        $this->assertSame('The export file could not be opened', $this->export->logged[0]['extra']['Error']);
        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        $this->assertSame('stop', end($this->export->events));
    }

    public function testOnlyAStreamedExportTurnsOffNginxsBuffering()
    {
        $this->stream(3, 3);
        $this->assertContains('X-Accel-Buffering: no', $this->export->headers);
        $this->assertContains('Content-Type: text/csv; charset=UTF-8', $this->export->headers);

        $direct = new RecordingExport($this->pdo);
        $direct->openFile('domain_results', '1');
        $this->assertContains('Content-Type: text/csv; charset=UTF-8', $direct->headers);
        $this->assertNotContains('X-Accel-Buffering: no', $direct->headers);
        $direct->removeFile();
    }

    public function testABomThatCantBeWrittenMeansNoFile()
    {
        if (!in_array('exportfail', stream_get_wrappers(), true)) {
            stream_wrapper_register('exportfail', FailingStream::class);
        }
        $export = new class extends Export {
            protected function outputStream()
            {
                return fopen('exportfail://file', 'w');
            }
        };

        $this->assertFalse($export->openFile('domain_results', '1'));
    }

    public function testAnErrorFromTheDatabaseOnTheThirdChunkRollsBackAndLogsIt()
    {
        // The list reader itself, with keys for 9 domains in batches of 3 (each full batch puts its last domain back, so
        // two are kept, as a group), and the third group's rows query failing
        $rows_queries = 0;
        $this->pdo = new RecordingPdo(array(
            'SELECT count(*)' => array(array(array('count(*)' => 9))),
            'AS ks_expiry' => function ($sql, $params) {
                $after = $params['ks_i'] ?? 0;
                $keys = array();
                for ($id = $after + 1; $id <= min(9, $after + 3); $id++) {
                    $keys[] = array('ks_expiry' => '2027-01-01', 'ks_name' => 'd' . $id . '.com', 'ks_id' => $id,
                        'ks_field_id' => $id, 'ks_bytes' => 100);
                }
                return $keys;
            },
            'SELECT d.id, d.domain' => function ($sql, $params) use (&$rows_queries) {
                if (++$rows_queries === 3) throw new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
                $rows = array();
                foreach ($params as $name => $value) {
                    if (strpos($name, 'ks_id') === 0) $rows[] = array('domain' => 'd' . $value . '.com');
                }
                return $rows;
            }));
        $this->export = new RecordingExport($this->pdo);
        $this->export->begin('index.php');
        $list = new DomainList($this->pdo, 1, '');
        $export = $this->export;

        try {

            $export->startSnapshot($list);
            $count = $list->count('');
            $file = $export->openStream('domain_results', '1');
            foreach ($list->exportChunks('', array(), 3) as $rows) {
                foreach ($rows as $row) {
                    $export->writeDataRow($file, array($row->domain));
                }
                $export->chunkWritten();
            }
            $export->finish($count);

        } catch (\Throwable $e) {

            $export->fail($e);

        }

        // The count ran in the snapshot, before the file started
        $this->assertSame(array('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY'), array_slice($export->statementsBeforeOpen(), 0, 2));
        $before_open = $export->statementsBeforeOpen();
        $this->assertStringContainsString('SELECT count(*)', end($before_open));
        // Then the database's error rolled back, before the one log entry, which names it
        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        $this->assertNotContains('COMMIT', array_column($this->pdo->log, 'sql'));
        $this->assertCount(1, $export->logged);
        $this->assertSame(count($this->pdo->log), $export->logged[0]['statements']);
        $this->assertStringContainsString('server has gone away', $export->logged[0]['extra']['Error']);
        $this->assertSame(4, $export->logged[0]['extra']['Rows Written']);
        $lines = $this->lines();
        $this->assertCount(5, $lines);
        $this->assertSame('"' . Export::incompleteText() . '"', end($lines));
    }

    public function testChunkWrittenSendsOnAFlushableBuffer()
    {
        $received = '';
        ob_start(function ($buffer) use (&$received) {
            $received .= $buffer;
            return '';
        }, 0, PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
        echo 'chunk one';

        (new Export())->chunkWritten();
        $flushed = $received;
        ob_end_clean();

        $this->assertSame('chunk one', $flushed);
    }

    public function testWriteRowAndWriteBlankRowReportWhetherTheyWrote()
    {
        $export = new Export();
        $good = fopen('php://memory', 'w+');
        $bad = fopen('php://memory', 'r');

        $this->assertTrue($export->writeRow($good, array('a')));
        $this->assertTrue($export->writeBlankRow($good));
        $this->assertFalse(@$export->writeRow($bad, array('a')));
        $this->assertFalse(@$export->writeBlankRow($bad));
    }

    public function testARowCountThatDoesntMatchEndsTheFileAsIncomplete()
    {
        $this->stream(4, 3);

        $lines = $this->lines();
        $this->assertSame('"' . Export::incompleteText() . '"', end($lines));
        $this->assertSame(array('Rows Written' => 3, 'Rows Counted' => 4), $this->export->logged[0]['extra']);
        $this->assertSame('COMMIT', end($this->pdo->log)['sql']);
    }

    public function testAnErrorInTheCountsSendsTheUserBackWithNoDownload()
    {
        $this->stream(3, 3, 2, 0, null, new \PDOException('Lock wait timeout exceeded'));

        $this->assertNotContains('open', $this->export->events);
        $this->assertSame('index.php?oid=2', $this->export->redirected_to);
        $this->assertStringContainsString("The export couldn't be created. Please try again.", $_SESSION['s_message_danger']);
        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        $this->assertSame(count($this->pdo->log), $this->export->logged[0]['statements']);
        $this->assertSame('stop', end($this->export->events));
    }

    public function testTheBackAddressIsAlwaysOneOfOurOwnPages()
    {
        $this->assertSame('index.php?oid=2&segid=7', Export::backUrl('index.php?oid=2&segid=7'));
        $this->assertSame('index.php', Export::backUrl('index.php'));
        $this->assertSame('index.php', Export::backUrl('https://example.com/index.php'));
        $this->assertSame('index.php', Export::backUrl('//example.com/index.php'));
        $this->assertSame('index.php', Export::backUrl('//intranet/index.php'));
        $this->assertSame('index.php', Export::backUrl('/index.php'));
        $this->assertSame('index.php', Export::backUrl('domains//index.php'));
        $this->assertSame('domains/index.php?segid=1', Export::backUrl('domains/index.php?segid=1'));
        $this->assertSame('index.php', Export::backUrl("index.php?a=1\r\nSet-Cookie: x=1"));
    }

    public function testAFailedExportOnlySendsTheUserBackToOneOfOurPages()
    {
        $this->export = new RecordingExport($this->pdo);
        $this->export->begin('//intranet/x.php');

        $this->stream(3, 3, 2, 0, null, new \PDOException('Lock wait timeout exceeded'));

        $this->assertSame('index.php', $this->export->redirected_to);
    }

    public function testAnErrorWhileSendingTheUserBackStillEndsTheRequest()
    {
        $export = new class($this->pdo) extends RecordingExport {
            protected function reopenSession()
            {
                throw new \RuntimeException('The session could not be read');
            }
        };
        $export->begin('index.php');
        $this->export = $export;

        $this->stream(3, 3, 2, 0, null, new \PDOException('Lock wait timeout exceeded'));

        $this->assertSame('ROLLBACK', end($this->pdo->log)['sql']);
        $this->assertSame('index.php', $export->redirected_to);
        $this->assertSame('stop', end($export->events));
    }

    public static function shutdowns()
    {
        return array(
            'before output, session open' => array(Export::PHASE_BEFORE_OUTPUT, E_ERROR, false, true, 'back'),
            'before output, session closed' => array(Export::PHASE_BEFORE_OUTPUT, E_ERROR, false, false, 'reopen and back'),
            'during output' => array(Export::PHASE_OUTPUT, E_ERROR, false, false, 'incomplete'),
            'during output, row already written' => array(Export::PHASE_OUTPUT, E_ERROR, true, false, 'none'),
            'finished' => array(Export::PHASE_DONE, E_ERROR, false, false, 'none'),
            'only a warning' => array(Export::PHASE_OUTPUT, E_WARNING, false, false, 'none'),
            'a parse error' => array(Export::PHASE_OUTPUT, E_PARSE, false, false, 'incomplete'),
        );
    }

    #[DataProvider('shutdowns')]
    public function testWhatShutdownDoesForEachPhase($phase, $type, $incomplete_attempted, $session_open, $expected)
    {
        $this->assertSame($expected, Export::shutdownAction($phase, array('type' => $type, 'message' => 'x'),
            $incomplete_attempted, $session_open));
    }

    public function testTheTimeLimitIsNeverShortenedOrAddedWhereThereWasNone()
    {
        $before = ini_get('max_execution_time');

        try {

            foreach (array('5' => '30', '120' => '120', '0' => '0', '-1' => '-1') as $host => $expected) {
                ini_set('max_execution_time', (string) $host);
                Export::resetTimeLimit();
                $this->assertSame((string) $expected, ini_get('max_execution_time'), 'host limit ' . $host);
            }

        } finally {

            set_time_limit((int) $before);
            ini_set('max_execution_time', $before);

        }
    }

    public function testOnlyAnErrorThatEndsTheRequestIsFatal()
    {
        $this->assertTrue(Export::fatalError(array('type' => E_ERROR, 'message' => 'Maximum execution time exceeded')));
        $this->assertTrue(Export::fatalError(array('type' => E_USER_ERROR)));
        $this->assertFalse(Export::fatalError(array('type' => E_WARNING)));
        $this->assertFalse(Export::fatalError(array('type' => E_NOTICE)));
        $this->assertFalse(Export::fatalError(null));
    }

    public function testNoErrorAtShutdownNeedsNothing()
    {
        $this->assertSame('none', Export::shutdownAction(Export::PHASE_OUTPUT, null, false, false));
    }

    public function testMemoryStaysFlatOverTwoHundredThousandRows()
    {
        // 200,000 rows in 1,000-row pages through writeRow(), to a stream that keeps nothing
        $export = new Export();
        $file = fopen('/dev/null', 'w');
        $page = function ($start) {
            $rows = array();
            for ($n = $start; $n < $start + 1000; $n++) {
                $rows[] = (object) array('domain' => 'd' . $n . '.com', 'expiry_date' => '2027-01-01', 'notes' => str_repeat('x', 200));
            }
            return $rows;
        };

        $export->writeRow($file, array('warm up'));
        memory_reset_peak_usage();
        $before = memory_get_usage();
        for ($start = 0; $start < 200000; $start += 1000) {
            foreach ($page($start) as $row) {
                $export->writeRow($file, array($row->domain, $row->expiry_date, $row->notes));
            }
        }
        fclose($file);

        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

} //@formatter:on
