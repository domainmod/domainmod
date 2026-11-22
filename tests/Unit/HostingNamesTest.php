<?php
/**
 * /tests/Unit/HostingNamesTest.php
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
 * The hosting tables, columns, settings, and parameters were renamed before 4.27.0 shipped (a hosting "account" became a
 * hosting service), and the old names were retired rather than reused, so a leftover use can be found by searching. This
 * walks the files on disk (not git ls-files, so it sees new files during development and works in a release checkout
 * with no .git) and fails on any retired name in the app's PHP and JavaScript. tests/, vendor/, theme/, locales/, docs/,
 * .data/, and _includes/updates/ are left out (older update blocks keep their history, and the tests name the retired
 * names on purpose). install/go.php is scanned once it has the hosting tables (its held changes land at the release).
 *
 * Domains no longer name a hosting provider (domains.hosting_id was replaced by domains.service_id, and the queue
 * tables lost theirs), so hosting_id in a statement on domains, domain_queue, or domain_queue_history fails too. The
 * few places that still need the old column before the database update carry the legacy marker on the statement, and
 * the marker is allowed only in those files.
 *
 * The hourly auto-renew task was removed before it shipped too (a service whose renewal date passes now waits for the user
 * to confirm the renewal with Mark Renewed), so its class, scheduler slug, Change History action, and date helper are
 * retired names as well.
 */
class HostingNamesTest extends TestCase
{
    // Each retired name, as a pattern, with what replaced it
    const RETIRED = array(
        '/\bhosting_accounts\b/' => 'hosting_services',
        '/\bhosting_account_fields\b/' => 'hosting_service_fields',
        '/\bhosting_account_field_data\b/' => 'hosting_service_field_data',
        '/\bhosting_account_id\b/' => 'service_id',
        '/number_of_hosting_accounts/' => 'number_of_hosting_services',
        '/\bs_has_hosting_account\b/' => 's_has_hosting_service',
        '/\bhaid\b/' => 'hsid',
        '/\bha\./' => 'the hs alias',
        '/\bHostingAutoRenew\b/' => 'HostingRenewal (Mark Renewed), since the auto-renew task was removed',
        '/hosting-auto-renew/' => 'nothing, since the auto-renew task was removed',
        '/\bhosting_auto_renew\b/' => 'hosting_renew (Mark Renewed), since the auto-renew task was removed',
        '/\bautoRenewDate\b/' => 'Hosting::renewalState() and Mark Renewed, since the auto-renew task was removed',
    );

    const LEGACY_MARKER = '// Legacy: domains.hosting_id before 4.27.0';

    // The only files whose statements may carry the legacy marker: the upgrade notice's count, the provider list's domain
    // count and export, the Assets page's Providers tile, the provider delete's domain check, and the five domain inserts
    const LEGACY_FILES = array('assets/edit/host.php', 'assets/hosting.php', 'assets/index.php', 'bulk/main/index.php',
        'classes/DomainMOD/Domain.php', 'classes/DomainMOD/DomainQueue.php', 'classes/DomainMOD/Notice.php',
        'domains/add.php', 'queue/edit/index.php');

    const DOMAIN_TABLES = 'domains|domain_queue|domain_queue_history';

    const SKIPPED = array('tests', 'vendor', 'theme', 'locales', 'docs', '.data', '.git', '_includes/updates');

    // The app's PHP and JavaScript files, relative to the repository root
    public static function appFiles()
    {
        $root = dirname(__DIR__, 2);
        $files = array();
        $walk = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($root) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($iterator->hasChildren()) return !in_array($relative, self::SKIPPED, true);
                return (bool) preg_match('/\.(php|js)$/', $relative);
            }));
        foreach ($walk as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($relative === 'install/go.php'
                && strpos((string) file_get_contents($file->getPathname()), 'CREATE TABLE IF NOT EXISTS `hosting_services`') === false) {
                continue;
            }
            $files[$relative] = $file->getPathname();
        }
        ksort($files);
        return $files;
    }

    public function testTheWalkFindsTheHostingFiles()
    {
        $files = self::appFiles();
        foreach (array('hosting/index.php', 'classes/DomainMOD/HostingList.php', 'js/server-list.js') as $expected) {
            $this->assertArrayHasKey($expected, $files);
        }
        $this->assertArrayNotHasKey('tests/Unit/HostingNamesTest.php', $files);
    }

    public function testNoAppFileUsesARetiredName()
    {
        $found = array();
        foreach (self::appFiles() as $relative => $path) {
            foreach (self::statements($path) as $statement) {
                if (strpos($statement['raw'], self::LEGACY_MARKER) !== false) continue;
                foreach (self::problems($statement) as $offset => $problem) {
                    $line = $statement['line'] + substr_count(substr($statement['raw'], 0, $offset), "\n");
                    $found[] = $relative . ':' . $line . ' (' . $problem . ')';
                }
            }
        }
        $this->assertSame(array(), $found);
    }

    public function testTheLegacyMarkerIsOnlyInTheAllowedFiles()
    {
        $marked = array();
        foreach (self::appFiles() as $relative => $path) {
            if (strpos((string) file_get_contents($path), self::LEGACY_MARKER) !== false) $marked[] = $relative;
        }
        $this->assertSame(array(), array_values(array_diff($marked, self::LEGACY_FILES)));
    }

    public function testTheStatementCheckCatchesTheOldDomainColumn()
    {
        $bad = array(
            '<?php $pdo->query("SELECT id FROM domains WHERE hosting_id = 1");',
            '<?php $pdo->query("SELECT d.domain FROM domains AS d, hosting AS h WHERE d.hosting_id = h.id");',
            '<?php $s = "UPDATE domains" . " SET hosting_id = 0";',
            '<?php $pdo->prepare("INSERT INTO `domain_queue_history` (domain, hosting_id) VALUES (:d, 0)");');
        $good = array(
            '<?php $pdo->query("SELECT id FROM hosting_services WHERE hosting_id = 1");',
            '<?php $pdo->query("SELECT hs.hosting_id FROM domains AS d LEFT JOIN hosting_services AS hs ON hs.id = d.service_id");',
            '<?php ' . self::LEGACY_MARKER . "\n" . '$pdo->query("SELECT COUNT(*) FROM domains WHERE hosting_id <> 0");');
        foreach ($bad as $code) $this->assertNotSame(array(), self::problemsIn($code), $code);
        foreach ($good as $code) $this->assertSame(array(), self::problemsIn($code), $code);
    }

    // The removed auto-renew task's names are caught wherever they appear, code, strings, or comments
    public function testTheCheckCatchesTheRemovedAutoRenewTask()
    {
        foreach (array('<?php $task = new DomainMOD\HostingAutoRenew();',
                     '<?php if ($row->slug == \'hosting-auto-renew\') { }',
                     '<?php $op = $history->start(\'maintenance\', 0, \'hosting_auto_renew\');',
                     '<?php $date = Hosting::autoRenewDate($a, 1, 1, $b);') as $code) {
            $this->assertNotSame(array(), self::problemsIn($code), $code);
        }
        $this->assertSame(array(), self::problemsIn('<?php $op = $history->start(\'user\', 0, \'hosting_renew\');'));
    }

    // The problems in a piece of PHP, for the self-test
    protected static function problemsIn($code)
    {
        $file = tempnam(sys_get_temp_dir(), 'hnt');
        file_put_contents($file, $code);
        $problems = array();
        foreach (self::statements($file) as $statement) {
            if (strpos($statement['raw'], self::LEGACY_MARKER) !== false) continue;
            $problems = array_merge($problems, array_values(self::problems($statement)));
        }
        unlink($file);
        return $problems;
    }

    // A file's statements (PHP split at each semicolon, JavaScript as one), with their source, the text of their
    // string literals, and the line each starts on
    protected static function statements($path)
    {
        $code = (string) file_get_contents($path);
        if (substr($path, -3) === '.js') return array(array('raw' => $code, 'strings' => '', 'line' => 1));
        $statements = array();
        $current = array('raw' => '', 'strings' => '', 'line' => 1);
        $line = 1;
        foreach (token_get_all($code) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if ($current['raw'] === '' && trim($text) === '') {
                $line += substr_count($text, "\n");
                continue;
            }
            if ($current['raw'] === '') $current['line'] = $line;
            $current['raw'] .= $text;
            if (is_array($token) && in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE), true)) {
                $current['strings'] .= ' ' . $text;
            }
            $line += substr_count($text, "\n");
            if ($text === ';' || (is_array($token) && $token[0] === T_CLOSE_TAG)) {
                $statements[] = $current;
                $current = array('raw' => '', 'strings' => '', 'line' => $line);
            }
        }
        if ($current['raw'] !== '') $statements[] = $current;
        return $statements;
    }

    // A statement's retired names, keyed by their offset in its source
    protected static function problems(array $statement)
    {
        $problems = array();
        foreach (self::RETIRED as $pattern => $replacement) {
            if (preg_match_all($pattern, $statement['raw'], $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) $problems[$match[1]] = 'use ' . $replacement;
            }
        }
        $tables = self::DOMAIN_TABLES;
        $on_domain_table = (bool) preg_match('/\b(?:FROM|JOIN|UPDATE|INTO)\s+`?(?:' . $tables . ')`?(?![\w])/i',
            $statement['strings']);
        if (preg_match_all('/(?:\b(?:d|dq|dqh|' . $tables . ')\.|(?<![\w.`]))`?hosting_id\b/', $statement['strings'], $matches)) {
            foreach ($matches[0] as $match) {
                if (strpos($match, '.') !== false || $on_domain_table) {
                    $problems[] = 'domains.hosting_id is gone, use service_id, or mark a pre-update statement with the legacy marker';
                    break;
                }
            }
        }
        return $problems;
    }
}
