<?php
/**
 * /tests/Unit/RestoreFinishValueTest.php
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
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * What a Finish Restore form carries (RestoreRecovery::finishValue() and Backup & Restore's use of it): every restore
 * flag gets a value of its own, so a form left open for one flag without an id is refused for a later one. Each test
 * runs in a temp folder of its own, with the gate's functions declared as the gate declares them.
 */
#[RunTestsInSeparateProcesses]
class RestoreFinishValueTest extends TestCase
{
    private $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/domainmod-finish-test-' . bin2hex(random_bytes(4));
        mkdir($this->folder, 0700);
        define('DIR_TEMP', $this->folder);
        $GLOBALS['finish_test_holds_lock'] = true;
        // Declared in the global namespace, where the gate declares them. The lock file is "held" while the test says so.
        eval('function domainmod_restore_path($name) { $names = array(\'lock\' => \'restore.lock\', \'flag\' => \'restore.flag\', '
            . '\'staged\' => \'restore.staged.php\', \'result\' => \'restore.result.php\'); return DIR_TEMP . \'/\' . $names[$name]; }');
        eval('function domainmod_restore_holds_file_exclusive() { return $GLOBALS[\'finish_test_holds_lock\']; }');
    }

    protected function tearDown(): void
    {
        @chmod($this->folder, 0700);
        foreach (glob($this->folder . '/*') ?: array() as $file) @unlink($file);
        @rmdir($this->folder);
    }

    private function flag()
    {
        return DIR_TEMP . '/restore.flag';
    }

    // Backup & Restore's own lines: what its Finish Restore form is shown for (on a page view, or checked against on a
    // POST), for an administrator who isn't read-only
    private static function pageFinishFor($restore_post = false)
    {
        $auth_flags = array('admin' => 1, 'read_only' => 0);
        eval(self::block('/^\$restore_flag_value = .*?^\$restore_finish_for = .*?;$/ms'));
        return $restore_finish_for;
    }

    // Whether a POST of $posted matches the state the form was shown for (the page's check before it finishes the
    // restore), by the page's own lines: the value checked against on a POST, the posted value as read, and the match
    private static function postMatches($posted)
    {
        $restore_finish_for = self::pageFinishFor(true);
        $_POST = array('finish_restore' => $posted);
        eval(self::block('/^    \$finish_restore_posted = .*;$/m'));
        if (!preg_match('/^    if \((.*)\) \{$/', self::block('/^    if \(\$restore_finish_for !== .*$/m'), $condition)) {
            self::fail('admin/backup/index.php has no Finish Restore match');
        }
        return eval('return ' . $condition[1] . ';');
    }

    private static function block($pattern)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/backup/index.php');
        if (!preg_match($pattern, $source, $match)) self::fail('admin/backup/index.php has no match for ' . $pattern);
        return $match[0];
    }

    // Two flags without an id, one after the other: each is given an id of its own, and a form shown for the first is
    // refused for the second, while the second's own form matches it
    public function testTwoFlagsWithoutAnIdInTurnGetTheirOwnValues()
    {
        file_put_contents($this->flag(), "1\n");
        $first = self::pageFinishFor();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        $this->assertSame($first, RestoreRecovery::flagId());
        // The same flag keeps its value on the next page
        $this->assertSame($first, self::pageFinishFor());
        $this->assertTrue(self::postMatches($first));

        // The first is finished, and a later restore leaves another flag without an id
        unlink($this->flag());
        file_put_contents($this->flag(), "1\n");
        $second = self::pageFinishFor();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $second);
        $this->assertNotSame($first, $second);
        $this->assertFalse(self::postMatches($first));
        $this->assertTrue(self::postMatches($second));
        // and a stale form posted before any page has shown the next flag is refused too, without giving that flag an id
        unlink($this->flag());
        file_put_contents($this->flag(), "1\n");
        $this->assertFalse(self::postMatches($second));
        $this->assertNull(RestoreRecovery::flagId());
    }

    // A flag that holds an id keeps it, and no flag (or a staged copy that isn't the web's) shows no form
    public function testAFlagWithAnIdKeepsItsOwn()
    {
        $this->assertSame('', self::pageFinishFor());
        file_put_contents(DIR_TEMP . '/restore.staged.php', RestoreRecovery::GUARD . json_encode(array('kind' => 'cli', 'token' => 't1')));
        $this->assertSame('', self::pageFinishFor());
        file_put_contents(DIR_TEMP . '/restore.staged.php', RestoreRecovery::GUARD . json_encode(array('kind' => 'web', 'token' => 't1')));
        $this->assertSame('staged:t1', self::pageFinishFor());
        unlink(DIR_TEMP . '/restore.staged.php');
        $id = str_repeat('ab', 16);
        file_put_contents($this->flag(), $id . "\n");
        $this->assertSame($id, self::pageFinishFor());
        $this->assertSame($id . "\n", file_get_contents($this->flag()));
    }

    // Without the lock file held exclusively (or without $give_id), a flag without an id is left as it is and gets a
    // value for the file's own state, which stays the same while the file does and changes when it's replaced
    public function testAFlagWithoutAnIdIsOnlyGivenOneUnderTheLock()
    {
        file_put_contents($this->flag(), "1\n");
        $this->assertStringStartsWith('flag:', RestoreRecovery::finishValue());
        $GLOBALS['finish_test_holds_lock'] = false;
        $value = RestoreRecovery::finishValue(true);
        $this->assertMatchesRegularExpression('/^flag:[0-9a-f]{64}$/', $value);
        $this->assertSame("1\n", file_get_contents($this->flag()));
        $this->assertSame($value, RestoreRecovery::finishValue(true));
        $this->assertSame($value, self::pageFinishFor());

        unlink($this->flag());
        file_put_contents($this->flag(), "2\n");
        $this->assertNotSame($value, RestoreRecovery::finishValue(true));
    }

    // A flag that's a link gets a value for its own entry as well as the file it points to, so a link replaced by
    // another to the same, unchanged file doesn't match a form shown for the first
    public function testAReplacedLinkToTheSameFlagDoesntMatch()
    {
        $GLOBALS['finish_test_holds_lock'] = false;
        file_put_contents(DIR_TEMP . '/flag-target', "1\n");
        symlink(DIR_TEMP . '/flag-target', $this->flag());
        symlink(DIR_TEMP . '/flag-target', DIR_TEMP . '/flag-link');
        $value = self::pageFinishFor();
        $this->assertMatchesRegularExpression('/^flag:[0-9a-f]{64}$/', $value);
        $this->assertTrue(self::postMatches($value));
        rename(DIR_TEMP . '/flag-link', $this->flag());
        $this->assertFalse(self::postMatches($value));
        $this->assertTrue(self::postMatches(self::pageFinishFor()));
    }

    // A flag that can't be read is never given an id (the recovery refuses it too), and its value is for that file's
    // state only: the same file keeps it, and a replaced one (here with another modification time) doesn't match
    public function testAFlagThatCantBeReadGetsAValueForItsOwnState()
    {
        file_put_contents($this->flag(), "1\n");
        chmod($this->flag(), 0);
        if (is_readable($this->flag())) $this->markTestSkipped('The flag stays readable for this user (root)');
        $value = self::pageFinishFor();
        $this->assertMatchesRegularExpression('/^flag:[0-9a-f]{64}$/', $value);
        $this->assertFalse(RestoreRecovery::flagId());
        $this->assertSame($value, self::pageFinishFor());
        $this->assertTrue(self::postMatches($value));

        unlink($this->flag());
        file_put_contents($this->flag(), "1\n");
        touch($this->flag(), time() - 120);
        chmod($this->flag(), 0);
        $this->assertFalse(self::postMatches($value));
    }

    // A flag without an id that can't be given one gets the value for its state instead, and a POST is checked against
    // that same value, without giving the flag an id, once it could be given one (here the write fails because temp/
    // isn't writable, where the page itself wouldn't offer the form, standing in for a write that fails on its own)
    public function testAFlagThatCantBeGivenAnIdGetsAValueForItsOwnState()
    {
        file_put_contents($this->flag(), "1\n");
        chmod($this->folder, 0500);
        if (is_writable($this->folder)) $this->markTestSkipped('The folder stays writable for this user (root)');
        $value = self::pageFinishFor();
        $this->assertMatchesRegularExpression('/^flag:[0-9a-f]{64}$/', $value);
        $this->assertNull(RestoreRecovery::flagId());
        $this->assertSame($value, self::pageFinishFor());
        // A form shown then still matches once the folder is writable again
        chmod($this->folder, 0700);
        $this->assertTrue(self::postMatches($value));
        $this->assertNull(RestoreRecovery::flagId());
    }

    // The page takes both of its Finish Restore values from finishValue(), never a value shared by every flag
    public function testThePageUsesTheFlagsOwnValue()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/backup/index.php');
        $this->assertStringContainsString('$restore_flag_value = DomainMOD\RestoreRecovery::finishValue(!$restore_post && $auth_flags[\'read_only\'] === 0);', $source);
        $this->assertStringContainsString('if ($restore_flag_id !== \'\') $restore_finish_for = DomainMOD\RestoreRecovery::finishValue();', $source);
        $this->assertStringNotContainsString("? 'flag'", $source);
        // and the form carries that value
        $this->assertStringContainsString('<input type="hidden" name="finish_restore" value="<?php echo htmlspecialchars($restore_finish_for, '
            . 'ENT_QUOTES, \'UTF-8\'); ?>">', $source);
    }

    // In a sticky temp folder, a flag is given an id only by its own user (who else owns a flag can't be set up without
    // root, so the check itself is pinned), and the same flag there keeps its state's value without posix
    public function testInAStickyFolderOnlyTheFlagsOwnerGivesItAnId()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/DomainMOD/RestoreRecovery.php');
        $this->assertStringContainsString("(!(@fileperms(DIR_TEMP) & 01000)\n            || (function_exists('posix_geteuid') && "
            . "(@lstat(domainmod_restore_path('flag'))['uid'] ?? -1) === posix_geteuid()))", $source);
        chmod($this->folder, 01700);
        file_put_contents($this->flag(), "1\n");
        $value = self::pageFinishFor();
        if (function_exists('posix_geteuid')) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $value);
        } else {
            $this->assertMatchesRegularExpression('/^flag:[0-9a-f]{64}$/', $value);
        }
    }

    // A recovery that fails shows the form again with the flag's value as it is then
    public function testAFailedRecoveryShowsTheFlagsValueAsItIsThen()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/backup/index.php');
        $failed = substr($source, strpos($source, 'if (!$recovery[\'ok\']) {'), 600);
        $this->assertStringContainsString('$restore_flag_value = DomainMOD\RestoreRecovery::finishValue();', $failed);
        $this->assertStringContainsString('if ($restore_flag_value !== \'\') $restore_finish_for = $restore_flag_value;', $failed);
    }

} //@formatter:on
