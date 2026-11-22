<?php
/**
 * /tests/Unit/RequestStringCastTest.php
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
 * Form values are read only when they're strings, so a crafted form that posts an array (such as reason_was[]=x) is
 * handled like an empty or stale form instead of raising an "Array to string conversion" warning. No page casts a
 * request value straight to a string unless the same statement has checked it first, apart from three older pages
 * still to be changed. AtRiskShowFilterTest covers $_GET.
 */
class RequestStringCastTest extends TestCase
{
    // Older pages that still cast a form value straight to a string, to be changed in a later release
    const OLDER_PAGES = array('/install/administrator/index.php', '/install/email-system/index.php', '/reset.php');

    // The Watch List edit page's draft: a "was" value that isn't a string counts as empty, so that field keeps what was
    // typed, with no warning
    public function testWatchEditDraftReadsTheWasValuesOnlyAsStrings()
    {
        $code = self::block('watch/edit.php', '/^    \$draft_from_post = function .*?^    \};$/ms');
        $hash = \DomainMOD\WatchList::notesHash('Some notes');
        $call = '$draft_from_post(\'expiring\', \'Some notes\')';

        $draft = self::valueAfter($code, array('reason_was' => 'expiring', 'notes_was' => $hash), $call);
        $this->assertSame(array(), $draft);

        $draft = self::valueAfter($code, array('reason_was' => array('x'), 'notes_was' => $hash), $call);
        $this->assertSame(array('reason' => 'expiring'), $draft);

        $draft = self::valueAfter($code, array('reason_was' => 'expiring', 'notes_was' => array('a' => array('x'))),
            $call);
        $this->assertSame(array('notes' => 'Some notes'), $draft);

        $draft = self::valueAfter($code, array(), $call);
        $this->assertSame(array('reason' => 'expiring', 'notes' => 'Some notes'), $draft);
    }

    // Finish Restore: a value that isn't a string is read as empty, which never matches the state the form was shown
    // for, so it's refused like a form from an earlier state
    public function testFinishRestoreReadsTheFormValueOnlyAsAString()
    {
        $line = self::block('admin/backup/index.php', '/^    \$finish_restore_posted = .*;$/m');
        // The value read is the one matched against that state, which can't be empty
        $this->assertStringContainsString(
            'if ($restore_finish_for !== \'\' && hash_equals($restore_finish_for, $finish_restore_posted)) {',
            file_get_contents(dirname(__DIR__, 2) . '/admin/backup/index.php'));
        $result = '$finish_restore_posted';
        $this->assertSame('flag', self::valueAfter($line, array('finish_restore' => 'flag'), $result));
        $this->assertSame('', self::valueAfter($line, array('finish_restore' => array('flag')), $result));
        $this->assertSame('', self::valueAfter($line, array('finish_restore' => array('a' => array('flag'))), $result));
    }

    // No page apart from the older ones above casts a $_POST, $_REQUEST, or $_COOKIE value to a string (which warns for
    // an array), unless the same statement has checked it with is_string() or is_scalar() first
    public function testNoPageCastsAFormValueToAString()
    {
        $root = dirname(__DIR__, 2);
        $found = array();
        $skip = array('.data', '.git', 'locales', 'node_modules', 'temp', 'tests', 'theme', 'vendor');
        // Local scratch files for trying out classes by hand, which git ignores (as in SecurityPageTest)
        $skip_files = array_merge(array('/modules/test.php', '/test.php'), self::OLDER_PAGES);
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($skip) {
                return !($iterator->hasChildren() && in_array($file->getFilename(), $skip, true));
            }));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            if (in_array($path, $skip_files, true)) continue;
            foreach (file($file->getPathname()) as $number => $line) {
                if (self::castsUnchecked($line)) $found[] = $path . ':' . ($number + 1);
            }
        }
        sort($found);
        $this->assertSame(array(), $found);
    }

    // The scan's own check: a cast is flagged unless the same value was checked earlier in the same statement
    public function testTheScanFlagsOnlyUncheckedCasts()
    {
        $this->assertTrue(self::castsUnchecked("\$a = (string) (\$_POST['reason_was'] ?? '');"));
        $this->assertTrue(self::castsUnchecked("\$a = (string) \$_REQUEST['token'];"));
        $this->assertTrue(self::castsUnchecked("\$a = (string)\$_COOKIE['x'];"));
        $this->assertTrue(self::castsUnchecked("\$a = is_string(\$_POST['y']) ? (string) \$_POST['x'] : '';"));
        $this->assertFalse(self::castsUnchecked("\$a = is_scalar(\$_POST['x'] ?? null) ? (string) \$_POST['x'] : '';"));
        $this->assertFalse(self::castsUnchecked("\$a = is_string(\$_POST['x']) ? \$_POST['x'] : '';"));
        $this->assertTrue(self::castsUnchecked("is_string(\$_POST['x']); \$a = (string) \$_POST['x'];"));
        $this->assertTrue(self::castsUnchecked("\$a = is_scalar(\$_POST['x']) ? 'y' : (string) \$_POST['x'];"));
        $this->assertTrue(self::castsUnchecked("\$a = !is_string(\$_POST['x']) ? (string) \$_POST['x'] : '';"));
        $this->assertTrue(self::castsUnchecked("\$a = ! is_string(\$_POST['x']) ? (string) \$_POST['x'] : '';"));
    }

    // Whether $line casts a form value to a string without checking that same value with is_string() or is_scalar()
    // earlier in the same statement, as the condition of a ternary that the cast is part of (the pattern the pages use)
    private static function castsUnchecked($line)
    {
        if (!preg_match_all('/\(string\)\s*\(?\s*(\$_(?:POST|REQUEST|COOKIE)\[[^\]]*\])/', $line, $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) return false;
        foreach ($matches as $match) {
            $before = substr($line, 0, $match[0][1]);
            // "! is_string(" is a negated check, as "!is_string(" is
            $statement = preg_replace('/!\s+/', '!', substr($before, (int) strrpos($before, ';')));
            $guard = '/(?<![!\w])is_(?:string|scalar)\(\s*' . preg_quote($match[1][0], '/') . '[^;?]*\?[^;:]*$/';
            if (!preg_match($guard, $statement)) return true;
        }
        return false;
    }

    // The part of $file that $pattern matches
    private static function block($file, $pattern)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        if (!preg_match($pattern, $source, $match)) self::fail($file . ' has no match for ' . $pattern);
        return $match[0];
    }

    // The value of $result after running $code with $post as the form, failing on any warning or notice
    private static function valueAfter($code, $post, $result)
    {
        $saved = $_POST;
        $_POST = $post;
        set_error_handler(function ($severity, $message) use ($code) {
            throw new \ErrorException($message . ' in: ' . $code, 0, $severity);
        });
        try {
            return eval($code . "\nreturn " . $result . ';');
        } finally {
            restore_error_handler();
            $_POST = $saved;
        }
    }

} //@formatter:on
