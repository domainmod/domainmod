<?php
/**
 * /tests/Support/RecordingExport.php
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
namespace DomainMOD\Tests\Support;

use DomainMOD\Export;

/*
 * An Export that streams into a temporary file instead of a download, for testing a streamed export (Export::begin()
 * to finish() or fail()) without a web request. It
 * keeps what would have gone to the browser, the session, and the log, and it doesn't touch PHPUnit's own output
 * buffers or end the test with exit. $file_answer replaces the file openFile() gives (such as false, or a stream that
 * can't be written), and $log_error makes the log write throw.
 */
class RecordingExport extends Export
{
    public $events = array();
    public $logged = array();
    public $redirected_to = null;
    public $file_answer = null;
    public $log_error = null;
    public $stream;
    public $opened_after = null;
    public $headers = array();
    protected $path;
    protected $pdo;

    // $pdo is the stand-in database, so the order of its statements and this export's events can be compared
    public function __construct(RecordingPdo $pdo)
    {
        $this->pdo = $pdo;
    }

    public function openFile($base_filename, $append_data)
    {
        $this->events[] = 'open';
        $this->opened_after = count($this->pdo->log);
        if ($this->file_answer !== null) return $this->file_answer;
        return parent::openFile($base_filename, $append_data);
    }

    // A temporary file in place of the response, so openFile() itself (its BOM) still runs
    protected function outputStream()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'export');
        $this->stream = fopen($this->path, 'w+');
        return $this->stream;
    }

    // What was written to the file
    public function written()
    {
        return (string) file_get_contents($this->path);
    }

    // Removes the temporary file, for the test's tearDown()
    public function removeFile()
    {
        if (is_resource($this->stream)) fclose($this->stream);
        if ($this->path !== null && is_file($this->path)) unlink($this->path);
    }

    public function fileClosed()
    {
        $file = $this->file_answer ?? $this->stream;
        return !is_resource($file);
    }

    public function chunkWritten()
    {
        $this->events[] = 'chunk';
    }

    // The statements sent before the file was opened
    public function statementsBeforeOpen()
    {
        return array_slice(array_column($this->pdo->log, 'sql'), 0, (int) $this->opened_after);
    }

    protected function endBuffers()
    {
        $this->events[] = 'buffers';
    }

    protected function closeSession()
    {
        $this->events[] = 'close session';
    }

    protected function reopenSession()
    {
        $this->events[] = 'reopen session';
    }

    // Leaves PHP's settings alone, and registers no shutdown function in PHPUnit's process
    protected function startRequest()
    {
        $this->events[] = 'start request';
    }

    protected function redirect($url)
    {
        $this->redirected_to = $url;
    }

    protected function logError($message, array $extra)
    {
        $this->logged[] = array('message' => $message, 'extra' => $extra, 'statements' => count($this->pdo->log));
        if ($this->log_error !== null) throw $this->log_error;
    }

    protected function sendHeader($header)
    {
        $this->headers[] = $header;
    }

    protected function stop()
    {
        $this->events[] = 'stop';
    }

    protected function extendTimeLimit()
    {
    }

} //@formatter:on
