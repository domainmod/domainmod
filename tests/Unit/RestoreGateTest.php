<?php
/**
 * /tests/Unit/RestoreGateTest.php
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

use DomainMOD\RestoreRecovery;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The restore gate (_includes/restore-gate.inc.php) and the restore's checks (DomainMOD\RestoreRecovery): the entry
 * scripts that take the lock themselves, reading a backup's facts before it's imported, the manifest, and the order of
 * the discard reasons
 */
#[RunTestsInSeparateProcesses]
class RestoreGateTest extends TestCase
{
    // The gate's own list of entry scripts that take the restore lock themselves is exactly these, and they exist
    public function testTheExceptionListIsFixed()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/_includes/restore-gate.inc.php');
        $this->assertMatchesRegularExpression("~return array\('admin/backup/index.php', 'checks.php', 'cron.php', 'update.php', "
            . "'restore.php', 'logout.php'\);~", $source);
        foreach (array('admin/backup/index.php', 'checks.php', 'cron.php', 'update.php', 'restore.php', 'logout.php') as $file) {
            $this->assertFileExists(dirname(__DIR__, 2) . '/' . $file);
        }
        // It's found by the first file PHP ran, never by anything a request or a page could set
        $this->assertStringContainsString('get_included_files()[0]', $source);
        $this->assertStringNotContainsString('$_GET', substr($source, strpos($source, 'function domainmod_restore_gate_exempt')));
    }

    public function testInitLoadsTheGateBeforeTheLocalHook()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/_includes/init.inc.php');
        $gate = strpos($source, "restore-gate.inc.php");
        $this->assertNotFalse($gate);
        $this->assertLessThan(strpos($source, 'helpers/init.php'), $gate);
        $this->assertGreaterThan(strpos($source, 'textdomain('), $gate);
    }

    // Sign Out never touches the database, so it works during a restore
    public function testLogoutReadsNoDatabase()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/logout.php');
        $this->assertStringNotContainsString('debug.inc.php', $source);
        $this->assertStringNotContainsString('new DomainMOD\System', $source);
    }

    // An htmx request is refused with a plain-text 503 (or a conflict's 409, through the same function), and the shared
    // htmx error handler shows that text, as text
    public function testTheHtmxErrorHandlerShowsTheRefusalsText()
    {
        $gate = file_get_contents(dirname(__DIR__, 2) . '/_includes/restore-gate.inc.php');
        $this->assertStringContainsString('domainmod_restore_refuse(domainmod_restore_conflict_message(), 409);', $gate);
        $refuse = substr($gate, strpos($gate, 'function domainmod_restore_refuse'));
        $this->assertStringContainsString('function domainmod_restore_refuse($message, $status = 503)', $refuse);
        $this->assertStringContainsString('http_response_code($status);', $refuse);
        // Retry-After only on a 503, never on a conflict's 409
        $this->assertStringContainsString("if (\$status === 503) header('Retry-After: 60');", $refuse);
        $this->assertSame(1, substr_count($refuse, 'Retry-After'));
        // The htmx branch: plain text, marked, and nothing else
        $htmx = substr($refuse, strpos($refuse, "if ((\$_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {"));
        $htmx = substr($htmx, 0, strpos($htmx, '}'));
        $this->assertStringContainsString("header('Content-Type: text/plain; charset=utf-8');", $htmx);
        $this->assertStringContainsString("header('X-DomainMOD-Refused: 1');", $htmx);
        $this->assertMatchesRegularExpression('~echo \$message;\s+exit;\s*$~', $htmx);

        $footer = file_get_contents(dirname(__DIR__, 2) . '/_includes/layout/footer.inc.php');
        $show = substr($footer, strpos($footer, 'var showHtmxError = function'));
        $show = substr($show, 0, strpos($show, "document.addEventListener('htmx:beforeSwap'"));
        $this->assertStringContainsString("text.textContent = message || htmx_error;", $show);
        $this->assertStringNotContainsString('innerHTML', $show);
        $swap = substr($footer, strpos($footer, "document.addEventListener('htmx:beforeSwap'"));
        $swap = substr($swap, 0, strpos($swap, '});'));
        $this->assertStringContainsString("var refused = (xhr.status === 503 || xhr.status === 409) && xhr.getResponseHeader('X-DomainMOD-Refused') === '1'\n"
            . "                && (xhr.getResponseHeader('Content-Type') || '').indexOf('text/plain') === 0;", $swap);
        $this->assertStringContainsString("showHtmxError(event.detail.elt, event.detail.target, refused ? xhr.responseText.trim() : '',",
            $swap);

        // The Refresh pages show the same marked answers (503 and 409) in their result area themselves, and stop them
        // there, so the shared handler doesn't add a second alert
        foreach (array('js/domain-refresh.js', 'js/ssl-refresh.js') as $script) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $script);
            $listener = substr($source, strpos($source, "result_area.addEventListener('htmx:beforeSwap'"));
            $listener = substr($listener, 0, strpos($listener, '});'));
            $this->assertStringContainsString('(xhr.status !== 503 && xhr.status !== 409)', $listener, $script);
            $this->assertStringContainsString("xhr.getResponseHeader('X-DomainMOD-Refused') !== '1'", $listener,
                $script);
            $this->assertStringContainsString('event.stopPropagation();', $listener, $script);
            $this->assertStringContainsString('message.textContent = xhr.responseText;', $listener, $script);
        }
    }

    public function testTheFirstRowOfAnInsertIsRead()
    {
        $this->assertSame(array('1', 'http://example.com/a,b', '4.27.0', null, "it's"),
            RestoreRecovery::firstTuple("(1,'http://example.com/a,b','4.27.0',NULL,'it\\'s'),(2,'x','y',NULL,'z');"));
        $this->assertSame(array(), RestoreRecovery::firstTuple('nothing'));
    }

    private function dump($footer = true, $manifest = true, $tables = array('settings', 'change_history', 'change_operations'))
    {
        $file = tempnam(sys_get_temp_dir(), 'restore-test-');
        $text = "-- mysqldump-php https://github.com/ifsnop/mysqldump-php\n";
        foreach ($tables as $table) {
            $text .= "DROP TABLE IF EXISTS `" . $table . "`;\nCREATE TABLE `" . $table . "` (\n  `id` int(10) NOT NULL,\n"
                . ($table === 'settings' ? "  `full_url` varchar(100) NOT NULL,\n  `db_version` varchar(12) NOT NULL,\n" : '')
                . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";
        }
        $text .= "INSERT INTO `settings` VALUES (1,'http://x','4.27.0');\n";
        if ($manifest) $text .= RestoreRecovery::manifestLine(array('settings' => 1), '4.27.0');
        if ($footer) $text .= "-- Dump completed\n";
        file_put_contents($file, $text);
        return $file;
    }

    public function testABackupsFactsAreReadBeforeTheImport()
    {
        $file = $this->dump();
        $facts = RestoreRecovery::scan($file);
        unlink($file);
        $this->assertTrue($facts['complete']);
        $this->assertSame('4.27.0', $facts['version']);
        $this->assertSame(array('settings', 'change_history', 'change_operations'), $facts['tables']);
        $this->assertSame(array('settings' => 1), $facts['manifest']);
    }

    public function testABackupCutShortIsNotComplete()
    {
        $file = $this->dump(false);
        $facts = RestoreRecovery::scan($file);
        unlink($file);
        $this->assertFalse($facts['complete']);
    }

    // The manifest goes just before the completion line, so the file still ends with it
    public function testTheManifestGoesBeforeTheCompletionLine()
    {
        $file = tempnam(sys_get_temp_dir(), 'restore-test-');
        file_put_contents($file, "-- mysqldump-php\nCREATE TABLE `a` (\n  `id` int\n);\n-- Dump completed on: Fri, 20 Nov 2026 10:00:00 +0000\n");
        RestoreRecovery::addManifest($file, array('a' => 0), '4.27.0');
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        unlink($file);
        $this->assertStringStartsWith(RestoreRecovery::MANIFEST_PREFIX, $lines[count($lines) - 2]);
        $this->assertSame('-- Dump completed on: Fri, 20 Nov 2026 10:00:00 +0000', end($lines));
    }

    private function reason($result, array $columns = array())
    {
        if (!defined('SOFTWARE_VERSION')) define('SOFTWARE_VERSION', '4.27.0');
        return RestoreRecovery::reason(new RecordingPdo(), $result, $columns);
    }

    public function testTheDiscardReasonsComeInOrder()
    {
        $full = \DomainMOD\ChangeHistory::backupTables('4.27.0');
        $base = array('restore_id' => 'x', 'tables' => $full, 'version' => '4.27.0', 'manifest' => array(), 'complete' => true,
            'import_ok' => true);
        $this->assertSame('unfinished', $this->reason(null));
        // A dump cut off is "didn't finish", even though it's also missing its history tables
        $this->assertSame('unfinished', $this->reason(array('complete' => false, 'tables' => array()) + $base));
        $this->assertSame('newer', $this->reason(array('version' => '9.0.0', 'tables' => array()) + $base));
        $this->assertSame('no_history', $this->reason(array('version' => '4.26.0', 'tables' => array('settings')) + $base));
        $this->assertSame('incomplete', $this->reason(array('manifest' => null) + $base));
        $this->assertSame('unfinished', $this->reason(array('import_ok' => false) + $base));
    }

    // After an import that finished, the tables' row counts are compared with the manifest, then the history tables'
    // columns
    public function testTheRowCountsAndTheHistoryColumnsAreChecked()
    {
        if (!defined('SOFTWARE_VERSION')) define('SOFTWARE_VERSION', '4.27.0');
        // Every table is empty but domains, which holds one row
        $pdo = new RecordingPdo(array('SELECT count(*) FROM' => function ($sql) {
            return array(array(strpos($sql, '`domains`') !== false ? 1 : 0));
        }));
        $full = \DomainMOD\ChangeHistory::backupTables('4.27.0');
        $columns = array();
        foreach ($full as $table) $columns[$table] = array('id');
        $result = array('restore_id' => 'x', 'tables' => $full, 'version' => '4.27.0', 'complete' => true, 'import_ok' => true,
            'manifest' => array_fill_keys($full, 0));
        // The manifest says two domains, and only one was imported
        $this->assertSame('incomplete', RestoreRecovery::reason($pdo, array('manifest' => array('domains' => 2) + $result['manifest']) + $result, $columns));
        // The counts match, but the history tables lack this version's columns
        $this->assertSame('format', RestoreRecovery::reason($pdo, array('manifest' => array('domains' => 1) + $result['manifest']) + $result, $columns));
        foreach (\DomainMOD\ChangeHistory::EXPECTED_COLUMNS as $table => $expected) $columns[$table] = $expected;
        $this->assertSame('', RestoreRecovery::reason($pdo, array('manifest' => array('domains' => 1) + $result['manifest']) + $result, $columns));
    }

    /*
     * The database lock raises the connection's idle timeout by reading it and setting a whole number, which MySQL and
     * MariaDB both accept. GREATEST(@@SESSION.wait_timeout, 86400) gives a DECIMAL, which MySQL 8 turns down for the
     * variable (error 1232), so the upgrade, which always takes the database lock, was refused as busy. The gate's own
     * function is run, declared from its source, since loading the gate file would run the gate.
     */
    public function testTheIdleTimeoutIsSetAsAWholeNumber()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/_includes/restore-gate.inc.php');
        $this->assertStringNotContainsString('wait_timeout = GREATEST', $source);
        $this->assertSame(1, preg_match('~^function domainmod_restore_db_keep_alive\(\$pdo\)\n\{\n.*?^\}\n~ms', $source, $match));
        // The lock calls it before it asks for any lock
        $lock = substr($source, strpos($source, 'function domainmod_restore_db_lock('));
        $call = strpos($lock, 'domainmod_restore_db_keep_alive($pdo);');
        $this->assertNotFalse($call);
        $this->assertLessThan(strpos($lock, '$gate = '), $call);
        if (!function_exists('domainmod_restore_db_keep_alive')) eval($match[0]);

        // A shorter timeout is raised to a day, as a plain number
        $pdo = new RecordingPdo(array('@@SESSION.wait_timeout' => array(array(array('28800')))));
        domainmod_restore_db_keep_alive($pdo);
        $this->assertSame(array('SELECT @@SESSION.wait_timeout', 'SET SESSION wait_timeout = 86400'),
            array_column($pdo->log, 'sql'));

        // A longer one is left as it is
        foreach (array('86400', '604800') as $idle) {
            $pdo = new RecordingPdo(array('@@SESSION.wait_timeout' => array(array(array($idle)))));
            domainmod_restore_db_keep_alive($pdo);
            $this->assertSame(array('SELECT @@SESSION.wait_timeout'), array_column($pdo->log, 'sql'));
        }
    }

    // A retry after a crash reuses the reason an earlier attempt saved, even when the tables it was decided from have
    // changed since (a discarded history's tables are already empty, which on their own would read as incomplete)
    public function testARetryReusesTheSavedReason()
    {
        $reason = RestoreRecovery::decidedReason(array('restore_id' => 'x', 'reason' => 'format'), function () {
            throw new \LogicException('A saved reason must not be decided again');
        });
        $this->assertSame('format', $reason);
        // with no verdict at all, the reason is decided (and there's nothing to save it in)
        $this->assertSame('incomplete', RestoreRecovery::decidedReason(null, function () {
            return 'incomplete';
        }));
    }

    // The first clean-up saves its reason in the verdict file, and a retry reads it back and reuses it (a real write and
    // read, in a temp folder of its own, with the gate's path function as the gate defines it)
    public function testTheFirstCleanupSavesItsReasonForARetry()
    {
        $folder = sys_get_temp_dir() . '/domainmod-restore-test-' . bin2hex(random_bytes(4));
        mkdir($folder, 0700);
        if (!defined('DIR_TEMP')) define('DIR_TEMP', $folder);
        // Declared in the global namespace, where the gate declares it
        if (!function_exists('domainmod_restore_path')) {
            eval('function domainmod_restore_path($name) { $names = array(\'lock\' => \'restore.lock\', \'flag\' => \'restore.flag\', '
                . '\'staged\' => \'restore.staged.php\', \'result\' => \'restore.result.php\'); return DIR_TEMP . \'/\' . $names[$name]; }');
        }
        try {
            $this->assertSame('format', RestoreRecovery::decidedReason(array('restore_id' => 'r1', 'complete' => true),
                function () {
                    return 'format';
                }));
            $saved = RestoreRecovery::readResult('r1', true);
            $this->assertSame('format', $saved['reason'] ?? null);
            $this->assertSame('format', RestoreRecovery::decidedReason($saved, function () {
                throw new \LogicException('A saved reason must not be decided again');
            }));
        } finally {
            @unlink(DIR_TEMP . '/restore.result.php');
            @rmdir($folder);
        }
    }

} //@formatter:on
