<?php
/**
 * /tests/Unit/SecurityPageTest.php
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

use DomainMOD\Form;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;


/*
 * Change Password and MFA are one Security page (settings/security/): the Password card first, then MFA's cards.
 * The Settings menu has one Security item, last, and no Change Password item. settings/password/ only forwards to the
 * Security page. While changing the password is the sign-in step to do, the Password card works and nothing of MFA
 * does (TwoFactorSessionTest checks allowedActions() and the pages that step can open). Outside that forced change,
 * the Password card asks for the current password, checked as sign-in checks it. The pages are scripts, so
 * most of this checks their source, like TwoFactorSetupMessageTest.
 */
class SecurityPageTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTheSettingsMenuEndsWithSecurityAndHasNoChangePassword()
    {
        $html = self::menu('settings-security', 'settings');

        $links = self::settingsLinks($html);
        $this->assertSame(array('/settings/profile/', '/settings/display/', '/settings/defaults/', '/settings/security/'),
            array_column($links, 'href'));
        $this->assertSame('Security', $links[3]['label']);
        $this->assertSame(array(false, false, false, true), array_column($links, 'active'));
        $this->assertStringNotContainsString('settings/password', $html);
        $this->assertStringNotContainsString('Change Password', $html);
        $this->assertStringNotContainsString('Two-Factor Sign-In', $html);
    }

    #[RunInSeparateProcess]
    public function testTheSecurityItemIsOnlyActiveOnTheSecurityPage()
    {
        $links = self::settingsLinks(self::menu('settings-profile', 'settings'));

        $this->assertSame(array(true, false, false, false), array_column($links, 'active'));
    }

    public function testTheSettingsPageHasOneSecurityTileForEveryone()
    {
        $source = self::code('/settings/index.php');

        $this->assertSame(1, preg_match_all('/landingTile\s*\(\s*\'\/settings\/security\/\'/', $source));
        $this->assertSame(1, preg_match_all('/landingTile\s*\(\s*\'\/settings\/security\/\'\s*,\s*_\s*\(\s*\'Security\'\s*\)/', $source));
        $this->assertStringNotContainsString('/settings/password/', $source);
        // The Password card works on the demo too, so the tile isn't left out there
        $this->assertStringNotContainsString('DEMO_INSTALLATION', $source);
        $this->assertMatchesRegularExpression('/\$page_title\s*=\s*_\s*\(\s*\'Security\'\s*\)\s*;/',
            self::code('/_includes/settings/settings-security.inc.php'));
    }

    public function testChangePasswordOnlyForwardsToTheSecurityPage()
    {
        $this->assertMatchesRegularExpression('/^\s*<\?php\s*\?>\s*<\?php\s*header\s*\(\s*\'Location: \.\.\/security\/\'\s*,\s*true\s*,' .
            '\s*302\s*\)\s*;\s*exit\s*;\s*$/', self::code('/settings/password/index.php'));
    }

    public function testNoPageSendsUsersToTheOldChangePasswordAddress()
    {
        $found = array();
        $skip = array('.data', '.git', 'node_modules', 'temp', 'tests', 'theme', 'vendor');
        // Local scratch files for trying out classes by hand, which git ignores (as in ReservedColumnNameTest)
        $skip_files = array('/modules/test.php', '/test.php');
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(DIR_ROOT, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($skip) {
                return !($iterator->hasChildren() && in_array($file->getFilename(), $skip, true));
            }));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = substr($file->getPathname(), strlen(DIR_ROOT));
            if (in_array($path, $skip_files, true)) continue;
            if (strpos(self::code($path), 'settings/password') !== false) $found[] = $path;
        }

        $this->assertSame(array(), $found);
    }

    public function testThePasswordCardWorksWhileNothingOfMfaDoes()
    {
        $source = self::code('/settings/security/index.php');

        // A change of password is the Password card's own POST, never one of MFA's actions, and its fields are only read
        // (as strings) for that POST
        foreach (array('new_password', 'new_password_confirmation') as $field) {
            $this->assertNotNull(self::offset('/\$' . $field . '\s*=\s*\$changing_password\s*&&\s*is_string\s*\(\s*\$_POST\s*\[\s*\'' .
                $field . '\'\s*\]\s*\?\?\s*null\s*\)\s*\?/', $source), $field);
        }
        $changing = self::offset('/\$changing_password\s*=\s*\$_SERVER\s*\[\s*\'REQUEST_METHOD\'\s*\]\s*==\s*\'POST\'\s*&&\s*' .
            '\(\s*\$_POST\s*\[\s*\'action\'\s*\]\s*\?\?\s*\'\'\s*\)\s*===\s*\'change_password\'\s*;/', $source);
        $password_post = self::offset('/if\s*\(\s*\$changing_password\s*&&\s*\$current_password_ok\s*&&\s*\$new_password\s*!=\s*""/', $source);
        $password_errors = self::offset('/\}\s*else\s*\{\s*if\s*\(\s*\$changing_password\s*\)\s*\{/', $source);
        // Every other POST is one of MFA's actions, and only those $allowed lists run (none while the password is to be
        // changed, on the demo, or before the database has MFA)
        $mfa_post = self::offset('/if\s*\(\s*\$_SERVER\s*\[\s*\'REQUEST_METHOD\'\s*\]\s*==\s*\'POST\'\s*&&\s*!\s*\$changing_password\s*\)\s*\{\s*' .
            '\$action\s*=\s*is_string\s*\(\s*\$_POST\s*\[\s*\'action\'\s*\]\s*\?\?\s*null\s*\)\s*\?\s*\$_POST\s*\[\s*\'action\'\s*\]\s*:\s*\'\'\s*;[^{}]*' .
            'if\s*\(\s*DEMO_INSTALLATION\s*==\s*true\s*\|\|\s*!\s*in_array\s*\(\s*\$action\s*,\s*\$allowed\s*,\s*true\s*\)\s*\)\s*\{/', $source);
        $allowed = self::offset('/\$allowed\s*=\s*DEMO_INSTALLATION\s*==\s*true\s*\|\|\s*!\s*\$two_factor_available\s*\?\s*array\s*\(\s*\)\s*:\s*' .
            '\$two_factor\s*->\s*allowedActions\s*\(/', $source);

        foreach (array($changing, $password_post, $password_errors, $mfa_post, $allowed) as $offset) $this->assertNotNull($offset);
        $this->assertLessThan($password_post, $changing);
        $this->assertLessThan($mfa_post, $password_errors);
        $this->assertLessThan($password_post, $allowed);

        // The Password card's form, shown whenever no MFA step fills the page. While the password is the step to do,
        // $allowed is empty, so $do is always '' and there are no codes to confirm: the card shows.
        $card = self::offset('/if\s*\(\s*\$do\s*===\s*\'\'\s*&&\s*!\s*\(\s*\$codes_shown\s*!==\s*null\s*&&\s*' .
            'in_array\s*\(\s*\'confirm_codes\'\s*,\s*\$allowed\s*,\s*true\s*\)\s*\)\s*\)\s*\{[^{}]*' .
            'showInputHidden\s*\(\s*\'action\'\s*,\s*\'change_password\'\s*\)/', $source);
        $this->assertNotNull($card);
        $this->assertNotNull(self::offset('/if\s*\(\s*!\s*in_array\s*\(\s*\$do\s*,\s*array\s*\(\s*\'setup\'\s*,\s*\'replace\'\s*,\s*\'regenerate\'\s*\)\s*,\s*true\s*\)\s*' .
            '\|\|\s*!\s*in_array\s*\(\s*\'password_\'\s*\.\s*\$do\s*,\s*\$allowed\s*,\s*true\s*\)\s*\)\s*\$do\s*=\s*\'\'\s*;/', $source));
        // A sign-in step only picks its view when $allowed has that step's password check
        $this->assertSame(2, preg_match_all('/\$do\s*=\s*\'(setup|replace)\'\s*;/', $source));
        $this->assertSame(2, preg_match_all('/in_array\s*\(\s*\'password_(setup|replace)\'\s*,\s*\$allowed\s*,\s*true\s*\)\s*\)\s*' .
            '\$do\s*=\s*\'\1\'\s*;/', $source));
        $this->assertNotNull(self::offset('/elseif\s*\(\s*in_array\s*\(\s*\'password\'\s*,\s*\$steps\s*,\s*true\s*\)\s*\)\s*\{\s*' .
            'echo\s*\$mfa_note\s*\(\s*_\s*\(\s*\'Please change your password first\.\'\s*\)\s*\)/', $source));
    }

    public function testThePasswordChangeKeepsItsChecksAndSessionHandling()
    {
        $source = self::code('/settings/security/index.php');

        foreach (array(
            // Only the account this session signed in with, still active, with the same password (and MFA stamp)
            '/AND\s+`password`\s*=\s*:session_hash\s+AND\s+active\s*=\s*\'1\'"\s*\.\s*\$stamp_condition\s*\)/',
            '/\$changed\s*=\s*\$two_factor\s*->\s*changePassword\s*\(/',
            // A new session ID and security token, and the account's flags as they are now
            '/if\s*\(\s*!\s*session_regenerate_id\s*\(\s*true\s*\)\s*\)\s*\{/',
            '/\$csrf\s*->\s*newToken\s*\(\s*\)\s*;/',
            '/\$login\s*->\s*setPermissions\s*\(\s*\$login\s*->\s*getUserInfo\s*\(/',
            // Back through checks.php during the sign-in steps, otherwise this page
            '/if\s*\(\s*\(\s*\$_SESSION\s*\[\s*\'s_running_login_checks\'\s*\]\s*\?\?\s*\'\'\s*\)\s*==\s*\'1\'\s*\)\s*\{\s*' .
                'header\s*\(\s*\'Location: \.\.\/\.\.\/checks\.php\'\s*\)\s*;\s*\}\s*else\s*\{\s*header\s*\(\s*\'Location: index\.php\'\s*\)\s*;\s*\}/',
            // Each mistake on the Password card's fields
            '/_\s*\(\s*\'Your passwords were left blank\'\s*\)/',
            '/_\s*\(\s*"Your passwords didn\'t match"\s*\)/',
            '/\$user\s*->\s*passwordTooShortMessage\s*\(\s*\)/',
            '/\$user\s*->\s*passwordTooLongMessage\s*\(\s*\)/') as $pattern) {
            $this->assertNotNull(self::offset($pattern, $source), $pattern);
        }
    }

    public function testTheCurrentPasswordIsCheckedExceptDuringTheForcedChange()
    {
        $source = self::code('/settings/security/index.php');

        // Forced: the account's temporary password flag, read from the database. Otherwise the typed password has to
        // pass password_verify() against the stored hash (the session's, which authCheck() matched with the account's
        // and the UPDATE requires again), so a wrong or empty one never passes.
        $forced = self::offset('/\$forced_password_change\s*=\s*\$user_info\s*&&\s*\(string\)\s*\$user_info\s*->\s*new_password\s*===\s*\'1\'\s*;/', $source);
        $info = self::offset('/\$user_info\s*=\s*\$login\s*->\s*getUserInfo\s*\(\s*\$_SESSION\s*\[\s*\'s_user_id\'\s*\]\s*\)\s*;/', $source);
        $ok = self::offset('/\$current_password_ok\s*=\s*\$forced_password_change\s*\|\|\s*\(\s*\$current_password\s*!==\s*\'\'\s*&&\s*' .
            'password_verify\s*\(\s*\$current_password\s*,\s*\(string\)\s*\(\s*\$_SESSION\s*\[\s*\'s_stored_hash\'\s*\]\s*\?\?\s*\'\'\s*\)\s*\)\s*\)\s*;/', $source);
        $typed = self::offset('/\$current_password\s*=\s*\$changing_password\s*&&\s*is_string\s*\(\s*\$_POST\s*\[\s*\'current_password\'\s*\]\s*\?\?\s*null\s*\)\s*\?\s*' .
            '\$_POST\s*\[\s*\'current_password\'\s*\]\s*:\s*\'\'\s*;/', $source);
        $change = self::offset('/if\s*\(\s*\$changing_password\s*&&\s*\$current_password_ok\s*&&\s*\$new_password\s*!=\s*""/', $source);
        foreach (array($info, $forced, $typed, $ok, $change) as $offset) $this->assertNotNull($offset);
        $this->assertLessThan($forced, $info);
        $this->assertLessThan($ok, $forced);
        $this->assertLessThan($change, $ok);
        // Set once, and never changed before the write it guards
        $this->assertSame(1, preg_match_all('/\$current_password_ok\s*(=(?!=)|\.=|\|=|&=|\?\?=)/', $source));
        $this->assertSame(1, preg_match_all('/\$forced_password_change\s*(=(?!=)|\.=|\|=|&=|\?\?=)/', $source));

        // A wrong current password gets its own field error, and each mistake in the new password still gets its own
        // (the last check names "too long", so it can't be reached by a wrong current password alone)
        $this->assertNotNull(self::offset('/if\s*\(\s*!\s*\$current_password_ok\s*\)\s*\{\s*\$form\s*->\s*addFieldError\s*\(\s*\'current_password\'\s*,\s*' .
            '_\s*\(\s*\'That password is not right\.\'\s*\)\s*\)\s*;\s*\}/', $source));
        $this->assertNotNull(self::offset('/\}\s*elseif\s*\(\s*\$user\s*->\s*passwordTooLong\s*\(\s*\$typed_password\s*\)\s*\)\s*\{\s*' .
            '\$form\s*->\s*addFieldError\s*\(\s*array\s*\(\s*\'new_password\'\s*,\s*\'new_password_confirmation\'\s*\)\s*,\s*\$user\s*->\s*passwordTooLongMessage\s*\(\s*\)\s*\)\s*;\s*\}/', $source));

        // The field: first in the card, only outside the forced change, as a current password
        $field = self::offset('/if\s*\(\s*!\s*\$forced_password_change\s*\)\s*\{\s*echo\s*\$form\s*->\s*showInputText\s*\(\s*\'current_password\'\s*,\s*' .
            '_\s*\(\s*\'Current Password\'\s*\)\s*,\s*\'\'\s*,\s*\'\'\s*,\s*\'72\'\s*,\s*\'current\'\s*,\s*\'1\'/', $source);
        $new = self::offset('/showInputText\s*\(\s*\'new_password\'/', $source);
        $this->assertNotNull($field);
        $this->assertNotNull($new);
        $this->assertLessThan($new, $field);
    }

    public function testACurrentPasswordFieldIsOneBrowsersCanFillIn()
    {
        Form::$field_errors = array();
        Form::$field_errors_shown = array();
        $form = new Form();

        $current = $form->showInputText('current_password', 'Current Password', '', '', '72', 'current', '1', '', '');
        $this->assertStringContainsString('type="password"', $current);
        $this->assertStringContainsString('autocomplete="current-password"', $current);
        $this->assertStringContainsString('dir="ltr"', $current);
        // A new password and a plain text field are as before
        $new = $form->showInputText('new_password', 'New Password', '', '', '72', '1', '1', '', '');
        $this->assertStringContainsString('type="password"', $new);
        $this->assertStringContainsString('autocomplete="new-password"', $new);
        $text = $form->showInputText('new_name', 'Name', '', '', '72', '', '1', '', '');
        $this->assertStringContainsString('type="text"', $text);
        $this->assertStringContainsString('autocomplete="on"', $text);
    }

    // The menu as a signed-in user who can change data sees it, on a page with this slug and section.
    private static function menu($slug, $software_section)
    {
        if (!defined('WEB_ROOT')) define('WEB_ROOT', '');
        $_SESSION = array('s_read_only' => '0', 's_is_admin' => 0, 's_csrf_token' => str_repeat('a', 64));
        $web_root = '';
        ob_start();
        require DIR_INC . '/layout/menu-main.inc.php';
        return ob_get_clean();
    }

    // The Settings submenu's links, in order, each with its path, label, and whether it's the active one.
    private static function settingsLinks($html)
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><ul>' . $html . '</ul>');
        libxml_clear_errors();

        $links = array();
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//a[@aria-label="Settings"]/following-sibling::ul[1]//a') as $link) {
            if (!$link instanceof \DOMElement) continue;
            $links[] = array('href' => $link->getAttribute('href'), 'label' => trim($link->textContent),
                'active' => in_array('active', explode(' ', $link->getAttribute('class')), true));
        }
        return $links;
    }

    // A file's source with each comment replaced by a space, so code that's commented out doesn't count.
    private static function code($path)
    {
        $source = file_get_contents(DIR_ROOT . $path);
        if ($source === false) return '';
        $code = '';
        foreach (\PhpToken::tokenize($source) as $token) {
            $code .= $token->is(array(T_COMMENT, T_DOC_COMMENT)) ? ' ' : $token->text;
        }
        return $code;
    }

    // The offset of the pattern's only match, or null when the pattern doesn't match exactly once.
    private static function offset($pattern, $source)
    {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) return null;
        return $matches[0][0][1];
    }

} //@formatter:on
