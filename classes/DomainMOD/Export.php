<?php
/**
 * /classes/DomainMOD/Export.php
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
namespace DomainMOD;

class Export
{
    // A streamed export's phases (see begin()): nothing sent yet, the file started, and finished (or failed and dealt with)
    const PHASE_BEFORE_OUTPUT = 'before output';
    const PHASE_OUTPUT = 'output';
    const PHASE_DONE = 'done';

    // How many seconds of PHP's own time a streamed export gets at the start, and again after each chunk (at least, see
    // resetTimeLimit())
    const TIME_LIMIT = 30;

    public $phase = self::PHASE_BEFORE_OUTPUT;
    public $rows_written = 0;
    public $incomplete_attempted = false;
    protected $streaming = false;
    protected $file = false;
    protected $back_url = '';
    protected $list = null;
    protected $in_snapshot = false;

    // Opens the file as a download, and returns it, or false when it can't be opened or its first bytes (the BOM) can't
    // be written
    public function openFile($base_filename, $append_data)
    {
        $this->sendHeader('Content-Encoding: UTF-8');
        $this->sendHeader('Content-Type: text/csv; charset=UTF-8');
        $this->sendHeader("Content-Disposition: attachment; filename=\"" . $base_filename . "_" . $append_data . ".csv\"");
        $this->sendHeader('Content-Transfer-Encoding: binary');
        $this->sendHeader('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        $this->sendHeader("Pragma: no-cache");

        $open_file = $this->outputStream();
        if ($open_file === false) return false;

        // fwrite() rather than fprintf(), which reports the text's length even when nothing could be written
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        if (fwrite($open_file, $bom) !== strlen($bom)) {
            fclose($open_file);
            return false;
        }

        return $open_file;
    }

    // Where the file goes: the response, as a download
    protected function outputStream()
    {
        return fopen('php://output', 'w');
    }

    /*
     * Writes standard CSV (RFC 4180): quotes are escaped by doubling them, with no backslash escape character. PHP's
     * default backslash escape writes a backslash before a quote as is, which can end a cell early, and PHP 8.4
     * deprecates relying on it.
     */
    public function writeRow($open_file, $row_contents)
    {
        // Without the left-to-right isolation marks Currency::format() adds to amounts in right-to-left languages, which
        // only matter on screen
        $row_contents = array_map(function ($value) {
            return is_string($value) ? str_replace(array("\u{2066}", "\u{2069}"), '', $value) : $value;
        }, $row_contents);
        return $this->checkWritten(fputcsv($open_file, $row_contents, ',', '"', ''));
    }

    public function writeBlankRow($open_file)
    {
        $blank_line = array('');
        return $this->checkWritten(fputcsv($open_file, $blank_line, ',', '"', ''));
    }

    // One of a streamed export's rows (not its header block), counted so the total can be checked against the count
    public function writeDataRow($open_file, $row_contents)
    {
        $written = $this->writeRow($open_file, $row_contents);
        if ($written) $this->rows_written++;
        return $written;
    }

    // Whether a row was written. In a streamed export, a row that wasn't is an error, since the file would be cut short.
    protected function checkWritten($result)
    {
        if ($result === false && $this->streaming) throw new \RuntimeException('An export row could not be written');
        return $result !== false;
    }

    /*
     * For text that anyone could have supplied (such as the Debug Log's usernames and URLs): a value starting with a
     * character a spreadsheet reads as a formula gets an apostrophe in front, so opening the file can't run it.
     */
    public function protectFormula($value)
    {
        $value = (string) $value;
        if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) return "'" . $value;
        return $value;
    }

    // Every creation type's name, by id, read once for an export's rows rather than once for each row
    public static function creationTypeNames(\PDO $pdo)
    {
        return $pdo->query("
            SELECT id, `name`
            FROM creation_types")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    // A creation type's name from creationTypeNames(), with the text System::getCreationType() gives one that's missing
    // (or whose name is empty or "0", which it treats the same way)
    public static function creationTypeName(array $names, $creation_type_id)
    {
        return !empty($names[$creation_type_id]) ? $names[$creation_type_id] : 'Unable to retrieve creation type';
    }

    // Every user's full name, by id, read once for an export's rows rather than once for each row
    public static function userNames(\PDO $pdo)
    {
        $names = array();
        foreach ($pdo->query("
            SELECT id, first_name, last_name
            FROM users")->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $names[$row['id']] = $row['first_name'] . ' ' . $row['last_name'];
        }
        return $names;
    }

    // A user's full name from userNames(), as User::getFullName() gives it, which is a single space for a user who has
    // been deleted
    public static function userName(array $names, $user_id)
    {
        return $names[$user_id] ?? ' ';
    }

    /*
     * Starts a streamed export of a long list, as the first thing the export branch does: errors are no longer shown
     * (so one can't be printed into the file, or send headers before a redirect), PHP's time limit is reset (see
     * extendTimeLimit()), and shutdown() is registered for the errors PHP can't catch. $back_url is the list, with its
     * filters, where an export that fails before its file starts sends the user back.
     *
     * The page then calls startSnapshot(), reads what the header block needs, and calls openStream(), which closes the
     * session and starts the file. It writes the header block and the rows (the rows with writeDataRow()), calling
     * chunkWritten() after the header block and after each chunk, and ends with finish(). All of that runs inside one
     * try, whose catch calls fail().
     */
    public function begin($back_url)
    {
        $this->back_url = self::backUrl($back_url);
        $this->streaming = true;
        $this->startRequest();
    }

    // begin()'s changes to the request: no errors shown, the time limit reset, and shutdown() registered
    protected function startRequest()
    {
        ini_set('display_errors', '0');
        $this->extendTimeLimit();
        register_shutdown_function(array($this, 'shutdown'));
    }

    // Where begin() sends the user back: only ever a page of DomainMOD's own, by a relative address (index.php, with or
    // without a query), so a redirect can't leave the site
    public static function backUrl($back_url)
    {
        return preg_match('~^[a-z0-9_-]+(/[a-z0-9_-]+)*\.php(\?[^\r\n]*)?$~i', (string) $back_url) ? $back_url : 'index.php';
    }

    // Starts the list's read-only snapshot (see ListQuery::beginSnapshot()), which fail() rolls back after an error
    public function startSnapshot(ListQuery $list)
    {
        $this->list = $list;
        $list->beginSnapshot();
        $this->in_snapshot = true;
    }

    /*
     * Starts the file, once the header block's numbers are read: the session is closed (so a long export doesn't hold
     * up the user's other tabs), every output buffer that can be ended is ended (so a host that buffers output can't
     * hold the whole file in memory), and the file is opened, or an error is thrown
     */
    public function openStream($base_filename, $append_data)
    {
        $this->closeSession();
        $this->endBuffers();

        $this->phase = self::PHASE_OUTPUT;
        // So nginx passes each chunk on rather than holding the file until it's finished
        $this->sendHeader('X-Accel-Buffering: no');
        $this->file = $this->openFile($base_filename, $append_data);
        if ($this->file === false) throw new \RuntimeException('The export file could not be opened');

        return $this->file;
    }

    /*
     * Ends a streamed export after its last row: the snapshot ends, and the rows written are checked against the
     * $expected count, ending the file with the "incomplete" row (see incompleteText()) when they differ. An error here
     * goes to the page's catch, and so to fail(). It never returns.
     */
    public function finish($expected)
    {
        if ($this->in_snapshot) {
            $this->list->endSnapshot(true);
            $this->in_snapshot = false;
        }

        if ($this->rows_written !== (int) $expected) {
            $this->writeIncompleteRow();
            $this->logError('The export wrote a different number of rows from its count',
                array('Rows Written' => $this->rows_written, 'Rows Counted' => (int) $expected));
        }

        $this->close();
    }

    /*
     * After an error in a streamed export, the snapshot is rolled back first. For an error before the file starts, the
     * error is logged, then the user is sent back to the list with a message, and no download starts. Once the file has
     * started it can't become an error page, so the file ends with the "incomplete" row, then the error is logged. The
     * rollback, the row, the log write, and the message each run in their own try, since the connection, the output, or
     * the session may be gone. It never returns.
     */
    public function fail(\Throwable $e)
    {
        if ($this->in_snapshot) {
            try {
                $this->list->endSnapshot(false);
            } catch (\Throwable $ignored) {
            }
            $this->in_snapshot = false;
        }

        if ($this->phase === self::PHASE_BEFORE_OUTPUT) {

            try {
                $this->logError('The export could not be created', array('Error' => $e->getMessage()));
            } catch (\Throwable $ignored) {
            }
            try {
                $this->sendBack();
            } catch (\Throwable $ignored) {
            }

        } else {

            try {
                $this->writeIncompleteRow();
            } catch (\Throwable $ignored) {
            }
            try {
                $this->logError('The export stopped early', array('Error' => $e->getMessage(),
                    'Rows Written' => $this->rows_written));
            } catch (\Throwable $ignored) {
            }

        }

        $this->close();
    }

    // Closes the file, if it was opened, and ends the request
    protected function close()
    {
        $this->phase = self::PHASE_DONE;
        if ($this->file !== false) {
            $this->closeFile($this->file);
        } else {
            $this->stop();
        }
    }

    /*
     * After each chunk (and the header block): sends what's written on, through a host's output buffer that couldn't be
     * ended but can be flushed, and gives the next chunk its own time limit
     */
    public function chunkWritten()
    {
        self::flushOutput();
        $this->extendTimeLimit();
    }

    // Sends what's been written on, through a host's output buffer that couldn't be ended but can be flushed
    public static function flushOutput()
    {
        $status = ob_get_level() > 0 ? ob_get_status() : array();
        if (((int) ($status['flags'] ?? 0)) & PHP_OUTPUT_HANDLER_FLUSHABLE) ob_flush();
        flush();
    }

    // The row that ends an export that stopped early, so a cut-off file doesn't look complete
    public static function incompleteText()
    {
        return _("This export stopped early because of an error, so it's incomplete. Please try again.");
    }

    // Writes the "incomplete" row, and reports whether it was written. It's only ever tried once, even when that try
    // fails, so a broken connection can't be written to twice.
    public function writeIncompleteRow()
    {
        if ($this->incomplete_attempted || $this->file === false) return false;
        $this->incomplete_attempted = true;
        return fputcsv($this->file, array(self::incompleteText()), ',', '"', '') !== false;
    }

    /*
     * What shutdown() does after an error PHP couldn't catch (such as a time limit), by the export's phase: before the
     * file starts, send the user back to the list ('back'), opening the session again first if it's been closed
     * ('reopen and back'). Once the file has started, end it with the "incomplete" row ('incomplete'), unless writing it
     * has been tried already. Anything else, and any error that isn't fatal, needs nothing ('none').
     */
    public static function shutdownAction($phase, $error, $incomplete_attempted, $session_open)
    {
        if (!self::fatalError($error)) return 'none';

        if ($phase === self::PHASE_BEFORE_OUTPUT) return $session_open ? 'back' : 'reopen and back';
        if ($phase === self::PHASE_OUTPUT && !$incomplete_attempted) return 'incomplete';

        return 'none';
    }

    // Whether an error from error_get_last() is one that ends the request (such as a time limit)
    public static function fatalError($error)
    {
        $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
        return is_array($error) && ((int) ($error['type'] ?? 0) & $fatal) !== 0;
    }

    // Registered by begin(): deals with an error PHP couldn't catch (see shutdownAction())
    public function shutdown()
    {
        $action = self::shutdownAction($this->phase, error_get_last(), $this->incomplete_attempted,
            session_status() === PHP_SESSION_ACTIVE);

        if ($action === 'back' || $action === 'reopen and back') {

            $this->sendBack();

        } elseif ($action === 'incomplete') {

            $this->writeIncompleteRow();

        }
    }

    // Back to the list, with a message, for an export that failed before its file started. The redirect happens even if
    // the session can't be opened again for the message.
    protected function sendBack()
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) $this->reopenSession();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '')
                . _("The export couldn't be created. Please try again.") . '<BR>';
        } catch (\Throwable $ignored) {
        }
        $this->redirect($this->back_url);
    }

    protected function endBuffers()
    {
        self::endOutputBuffers();
    }

    /*
     * Ends every output buffer that can be ended, stopping at one PHP won't remove (without trying, which would add a
     * notice to the output), and at a compression handler (zlib's output compression or ob_gzhandler), which passes
     * output on as it fills, and which would leave the rest of the response uncompressed after a compressed start if it
     * were ended part way
     */
    public static function endOutputBuffers()
    {
        while (ob_get_level() > 0) {
            $status = ob_get_status();
            if (!((int) ($status['flags'] ?? 0) & PHP_OUTPUT_HANDLER_REMOVABLE)) break;
            if (in_array(strtolower((string) ($status['name'] ?? '')), array('zlib output compression', 'ob_gzhandler'),
                true)) break;
            if (!ob_end_flush()) break;
        }
    }

    protected function extendTimeLimit()
    {
        self::resetTimeLimit();
    }

    /*
     * Starts PHP's time limit again, where the host allows it: TIME_LIMIT, or the host's own limit when that's longer. A
     * host with no limit (0, or below) keeps none.
     */
    public static function resetTimeLimit()
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) return;

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (function_exists('set_time_limit') && !in_array('set_time_limit', $disabled, true)) {
            set_time_limit(max(self::TIME_LIMIT, $limit));
        }
    }

    protected function closeSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    }

    protected function reopenSession()
    {
        session_start();
    }

    protected function redirect($url)
    {
        $this->sendHeader('Location: ' . $url);
    }

    protected function sendHeader($header)
    {
        header($header);
    }

    protected function logError($message, array $extra)
    {
        (new Log('class.export'))->critical($message, $extra);
    }

    protected function stop()
    {
        exit;
    }

    public function closeFile($open_file)
    {
        fclose($open_file);
        $this->stop();
    }

} //@formatter:on
