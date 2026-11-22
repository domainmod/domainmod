<?php
/**
 * /tests/Unit/ChangeHistoryWriteSitesTest.php
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
 * A safety net for the Change History: every PHP file that writes the item tables (domains, ssl_certs, and their custom
 * field data), deletes fee rows (which can take an item's cost currency away), or writes a table named by a variable,
 * must be a site that records its changes or an exempt one, so a new write can't skip the history. Code review still
 * checks the write sites by hand.
 */
class ChangeHistoryWriteSitesTest extends TestCase
{
    // The files that record their changes in the Change History
    // Files whose actions finish on more than one path, by how many finish() calls each has (both Refreshes finish a
    // quiet read early, as well as a full save)
    const FINISHES = array('classes/DomainMOD/DomainRefresh.php' => 2, 'classes/DomainMOD/SslRefresh.php' => 2);

    const CAPTURED = array(
        'classes/DomainMOD/Csv.php', 'admin/domain-fields/add.php', 'admin/domain-fields/edit.php', 'admin/ssl-fields/add.php', 'admin/ssl-fields/edit.php',
        'assets/add/registrar-fee.php', 'assets/add/ssl-provider-fee.php', 'assets/edit/registrar-account.php',
        'assets/edit/registrar-fee.php', 'assets/edit/registrar.php', 'assets/edit/ssl-provider-account.php',
        'assets/edit/ssl-provider-fee.php', 'assets/edit/ssl-provider.php', 'assets/edit/ssl-type.php',
        'assets/zero-out-domain-fees.php', 'assets/zero-out-ssl-fees.php', 'bulk/cf/step-two/index.php',
        'bulk/main/index.php', 'classes/DomainMOD/CustomFieldSchema.php', 'classes/DomainMOD/Domain.php',
        'classes/DomainMOD/DomainQueue.php', 'classes/DomainMOD/DomainRefresh.php', 'classes/DomainMOD/Maintenance.php',
        'classes/DomainMOD/SslQueue.php', 'classes/DomainMOD/SslRefresh.php', 'domains/add.php', 'domains/edit.php',
        'maintenance/update-domain-fees.php', 'maintenance/update-ssl-fees.php', 'queue/edit/index.php', 'ssl/add.php',
        'ssl/edit.php');

    // The files that write these tables without recording them, and why
    const EXEMPT = array(
        'install/go.php' => 'the installation, before there is anything to record',
        'classes/DomainMOD/ChangeHistoryOperation.php' => 'the recorder itself (its own temporary tables)',
        'classes/DomainMOD/RestoreRecovery.php' => "the restore's clean-up of the history tables",
        'classes/DomainMOD/RestoreImport.php' => 'the restore, which replaces every table',
        'classes/DomainMOD/DwBuild.php' => 'the Data Warehouse tables',
        'classes/DomainMOD/DwClean.php' => 'the Data Warehouse tables',
        'classes/DomainMOD/Segment.php' => 'the segment tables');

    // Any file under these folders is exempt: the database updates run before the history exists, or build it
    const EXEMPT_FOLDERS = array('_includes/updates/', 'vendor/', 'tests/', '.data/', 'theme/');

    // SQL keywords in capitals or in lower case, as MariaDB takes either (mixed case is left out, so English text such
    // as "Update domains" doesn't match)
    const VERBS = '(INSERT INTO|UPDATE|DELETE FROM|REPLACE INTO|ALTER TABLE|insert into|update|delete from|replace into|alter table)';

    const WRITES = '/' . self::VERBS . '\s+`?(domains|ssl_certs|domain_field_data|ssl_cert_field_data)\b'
        . '|(DELETE FROM|delete from)\s+`?(fees|ssl_fees)\b|' . self::VERBS . '\s+`?"\s*\.\s*\$/';

    private function files()
    {
        $root = dirname(__DIR__, 2);
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            foreach (self::EXEMPT_FOLDERS as $folder) {
                if (strpos($path, $folder) === 0) continue 2;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    public function testEveryWriteSiteIsCapturedOrExempt()
    {
        $unlisted = array();
        foreach ($this->files() as $path) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
            if (!preg_match(self::WRITES, $source)) continue;
            if (in_array($path, self::CAPTURED, true) || isset(self::EXEMPT[$path])) continue;
            $unlisted[] = $path;
        }
        $this->assertSame(array(), $unlisted, 'Writes to the item tables that the Change History doesn\'t know about');
    }

    public function testEveryCapturedSiteStillRecords()
    {
        foreach (self::CAPTURED as $path) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
            $this->assertNotFalse($source, $path);
            $this->assertMatchesRegularExpression('/ChangeHistory|history_op|CustomFieldSchema::/', $source, $path);
            // Every capture is compared, and every action is finished
            $this->assertSame(substr_count($source, '->captureCosts('), substr_count($source, '->compareCosts('), $path);
            $this->assertSame(preg_match_all('/\$history_before = /', $source), preg_match_all('/->compare\(\$history_before\b/', $source), $path);
            // Each action started has its own finish() (a branch that starts one must finish it, so a missing finish() in
            // one of the Bulk Updater's branches fails here even while the others finish theirs)
            $this->assertGreaterThanOrEqual(preg_match_all('/\$history_op = \(new (?:DomainMOD\\\\)?ChangeHistory\(/', $source),
                substr_count($source, '$history_op->finish();'), $path);
            // Every insert of an item is recorded as a create (its snapshot is written by finish())
            $this->assertLessThanOrEqual(substr_count($source, '->created('), preg_match_all('/(INSERT INTO|insert into)\s+`?(domains|ssl_certs)\b/', $source), $path);
            // and every delete of an item as a delete row with its snapshot (deleting() before the DELETE)
            $this->assertLessThanOrEqual(substr_count($source, '->deleting('), preg_match_all('/(DELETE|delete)\s+(?:\w+\s+)?(FROM|from)\s+`?(domains|ssl_certs)\b/', $source), $path);
            // The custom field delete names its action $op
            if ($path === 'classes/DomainMOD/CustomFieldSchema.php') {
                $this->assertSame(substr_count($source, '$op = (new ChangeHistory('), substr_count($source, '$op->finish();'), $path);
            }
            if (isset(self::FINISHES[$path])) $this->assertSame(self::FINISHES[$path], substr_count($source, '$history_op->finish();'), $path);
        }
    }

    // The Bulk Updater locks its listed domains in ascending id order, as every writer does: their ids are looked up
    // before any action's transaction starts, then locked through the primary key, and its capture and its delete both
    // use that lock (recording the rows in the order the domains were pasted)
    public function testTheBulkUpdaterLocksItsDomainsInIdOrder()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/bulk/main/index.php');
        $lookup = strpos($source, '$bulk_known_ids = $bulk_lookup_ids($domain_array);');
        $this->assertNotFalse($lookup);
        $this->assertLessThan(strpos($source, '$pdo->beginTransaction();'), $lookup);
        $this->assertStringContainsString('FROM domains FORCE INDEX (PRIMARY)', $source);
        // The ids are sorted before they're split into chunks, so the chunks, like the rows in each, lock in id order
        $lock = substr($source, strpos($source, '$bulk_lock_ids = function'));
        $this->assertSame(1, preg_match('~\$ids = array_values\(array_unique\(\$ids\)\);\s+sort\(\$ids\);~', $lock, $sorted, PREG_OFFSET_CAPTURE));
        $this->assertSame(1, preg_match('~foreach\s*\(\s*array_chunk\(\s*\$ids\s*,~', $lock, $chunked, PREG_OFFSET_CAPTURE));
        $this->assertLessThan($chunked[0][1], $sorted[0][1]);
        $this->assertStringContainsString('$ids = $bulk_pasted_order($bulk_lock_ids($domain_names), $domain_names);', $source);
        $this->assertStringContainsString('$domain_id_list = $bulk_lock_ids($domain_array);', $source);
        // Its rows are recorded in the order the domains were pasted, with the locks still taken in ascending id order
        $this->assertStringContainsString('$pasted_ids = $bulk_pasted_order($domain_id_list, $domain_array);', $source);
        $this->assertStringContainsString("\$history_op->deleting('domain', \$pasted_ids, true);", $source);
        $this->assertStringContainsString('$domain->unlinkSslCertificatesRecorded($pasted_ids, $history_op, $bulk_known_ssl_ids, true);', $source);
    }

    // The SSL type delete refuses inside its transaction when a certificate uses the type, and still rolls back after
    // a failure once the fees are deleted (its catch block)
    public function testTheSslTypeDeleteRollsBackAfterAFailure()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/edit/ssl-type.php');
        $fees = strpos($source, 'DELETE FROM ssl_fees');
        $catch = strpos($source, 'catch (Exception $e)', $fees);
        $this->assertNotFalse($catch);
        $this->assertStringContainsString('$pdo->rollback()', substr($source, $catch, 200));
        $locked = strpos($source, 'FOR UPDATE', strpos($source, "'ssl_type_delete'"));
        $this->assertLessThan($fees, $locked);
        // The locking read's answer refuses the delete before the fees go: a certificate found sets the refusal, which
        // rolls back and leaves the page
        $refusal = strpos($source, 'if ($stmt->fetchColumn()) $history_refused = ', $locked);
        $this->assertNotFalse($refusal);
        $this->assertLessThan($fees, $refusal);
        $this->assertSame(1, preg_match('/if \(\$history_refused !== \'\'\) \{\s*if \(\$pdo->InTransaction\(\)\) \$pdo->rollback\(\);[^}]*exit;/',
            substr($source, $refusal, $fees - $refusal)));
    }
} //@formatter:on
