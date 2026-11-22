<?php
/**
 * /tests/Unit/ConflictMessageTest.php
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
 * A save or delete that loses a database conflict (domainmod_restore_is_conflict()) rethrows it to the conflict's own
 * 409 page (domainmod_restore_exception_handler()), which says what happened. Its catch block adds no "Unable to ..."
 * message to the session first, or the user's next page would show that message too.
 */
class ConflictMessageTest extends TestCase
{
    // Folders and files with no page code to check: libraries, tests, private notes, and the installer, whose writes
    // meet no other request (install/go.php is changed only by its held patches)
    const SKIPPED = array('.git/', 'vendor/', 'theme/', 'tests/', '.data/', 'locales/', 'temp/', 'install/go.php');

    // Every catch block in the pages that adds a danger message and rethrows leaves the message out for a conflict
    public function testEveryRethrowLeavesTheMessageOutForAConflict()
    {
        $checked = array();
        foreach ($this->files() as $path => $relative) {
            foreach (self::catchBlocks(file_get_contents($path)) as $line => $body) {
                if (strpos($body, 'throw $e;') === false || strpos($body, "\$_SESSION['s_message_danger']") === false) continue;
                $where = $relative . ':' . $line;
                $checked[] = $where;
                // Either the message alone is left out for a conflict, or (a form that shows a conflict itself) the
                // message and the rethrow are both in the else branch of the conflict check
                $inline = preg_match_all("/^\s*if \(!domainmod_restore_is_conflict\(\\\$e\)\) \\\$_SESSION\['s_message_danger'\] \.= .*<BR>';$/m", $body);
                $branch = preg_match("/^\s*if \(domainmod_restore_is_conflict\(\\\$e\)\) \{.*\} else \{.*\\\$_SESSION\['s_message_danger'\] \.= .*throw \\\$e;/ms", $body);
                $this->assertSame(1, $inline + $branch, $where);
                if ($inline) {
                    $this->assertSame(1, substr_count($body, "\$_SESSION['s_message_danger']"), $where);
                    // The rethrow is a statement of its own, so a conflict still reaches its page
                    $this->assertSame(1, preg_match_all('/^\s*throw \$e;$/m', $body), $where);
                    $this->assertSame(1, substr_count($body, 'throw $e;'), $where);
                }
                // The check comes first, and the exception still goes to the conflict page
                $this->assertLessThan(strpos($body, 'throw $e;'), strpos($body, 'domainmod_restore_is_conflict($e)'), $where);
            }
        }
        // The two deletes this started with are among them, and so are the other pages' saves and deletes
        $this->assertContains('domains/edit.php', array_map(array(self::class, 'file'), $checked));
        $this->assertContains('hosting/edit.php', array_map(array(self::class, 'file'), $checked));
        $this->assertGreaterThanOrEqual(60, count($checked));
    }

    // The domain and hosting service deletes: no message for a conflict, and the exception is rethrown as before
    public function testTheDeletesRethrowAConflictWithoutAMessage()
    {
        foreach (array('domains/edit.php' => 'Unable to delete domain', 'hosting/edit.php' => 'Unable to delete hosting service') as $file => $message) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
            $at = strpos($source, "\$log_message = '" . $message . "';");
            $this->assertNotFalse($at, $file);
            $block = substr($source, $at, 500);
            $this->assertStringContainsString("if (!domainmod_restore_is_conflict(\$e)) \$_SESSION['s_message_danger'] .= \$log_message . '<BR>';", $block, $file);
            $this->assertMatchesRegularExpression('/<BR>\';\n\n {8}throw \$e;\n/', $block, $file);
        }
    }

    // Each catch block's body in $source, by the line its catch is on
    private static function catchBlocks($source)
    {
        $blocks = array();
        $offset = 0;
        while (preg_match('/catch \([^)]*\) \{/', $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1] + strlen($match[0][0]);
            $depth = 1;
            for ($i = $start; $i < strlen($source) && $depth > 0; $i++) {
                if ($source[$i] === '{') $depth++;
                if ($source[$i] === '}') $depth--;
            }
            $blocks[substr_count($source, "\n", 0, $match[0][1]) + 1] = substr($source, $start, $i - $start);
            $offset = $start;
        }
        return $blocks;
    }

    // The file part of "file:line"
    private static function file($checked)
    {
        return substr($checked, 0, strrpos($checked, ':'));
    }

    // Every PHP file outside the skipped folders, full path => path from the root
    private function files()
    {
        $root = dirname(__DIR__, 2);
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            foreach (self::SKIPPED as $skipped) {
                if (strpos($relative, $skipped) === 0) continue 2;
            }
            $files[$file->getPathname()] = $relative;
        }
        ksort($files);
        return $files;
    }

} //@formatter:on
