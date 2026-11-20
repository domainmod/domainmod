<?php
/**
 * /settings/two-factor/index.php
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
?>
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$user = new DomainMOD\User();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$two_factor = new DomainMOD\TwoFactor();
$totp = new DomainMOD\Totp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-two-factor.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// The secret, the QR code's address, and plain recovery codes can be on this page, so no copy of it is ever cached
header('Cache-Control: no-store');

/*
 * The Security page: the Password card (Change Password), then MFA's cards. The Password card works whether or not the
 * database has MFA yet. Before it does, MFA's part is a note, and nothing of it is read or allowed.
 */
$two_factor_available = $two_factor->isAvailableNow();
$changing_password = $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') === 'change_password';

$user_id = (int) $_SESSION['s_user_id'];
$now = time();
$state = $two_factor_available ? $two_factor->getState($user_id) : false;

// The account has to be the one the session signed in with (authCheck() has just checked it, but this read is the
// one every write below is tied to), so a change made in another session meanwhile always wins
if ($two_factor_available && (!$state || !hash_equals((string) $state['stamp'], (string) ($_SESSION['s_two_factor_stamp'] ?? '')))) {

    header('Location: ' . WEB_ROOT . '/logout.php');
    exit;

}

// The plain recovery codes of the current set, kept in the session (and only there) until the user confirms they've
// saved them, so a reload or a lost response shows the same codes again
$codes_shown = $two_factor_available ? ($_SESSION['s_two_factor_new_codes'] ?? null) : null;
if (!is_array($codes_shown) || $codes_shown['user_id'] !== $user_id || !hash_equals((string) $state['stamp'], $codes_shown['stamp'])
    || (string) $state['codes_confirmed'] === '1') {
    $codes_shown = null;
}

$has_recovery_permission = $two_factor_available && $two_factor->hasRecoveryPermission($user_id, $state['stamp']);
$allowed = DEMO_INSTALLATION == true || !$two_factor_available ? array() : $two_factor->allowedActions($state, $has_recovery_permission, $codes_shown !== null);
$steps = $two_factor->pendingSteps($state);
$session_hash = (string) ($_SESSION['s_stored_hash'] ?? '');

// The setup in progress, with its secret kept only in the session until its first code is right. It belongs to the
// password permission that started it, so once that permission ends (or a new one replaces it), the secret is gone
// for good and a new setup makes a new one.
$setup = $two_factor_available ? ($_SESSION['s_two_factor_setup'] ?? null) : null;
if (!is_array($setup) || $setup['user_id'] !== $user_id || !hash_equals((string) $state['stamp'], $setup['stamp'])
    || !$two_factor->hasPermission($setup['action'], $user_id, $session_hash, $state['stamp'], $now)
    || $setup['permission_time'] !== ($_SESSION['s_two_factor_permissions'][$setup['action']]['time'] ?? null)) {
    $setup = null;
    unset($_SESSION['s_two_factor_setup']);
}

// The recovery codes as a .txt file, from the session copy only
if (($_GET['download'] ?? '') === '1' && $codes_shown !== null && in_array('confirm_codes', $allowed, true)) {

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="domainmod-recovery-codes.txt"');
    echo sprintf(_('%s recovery codes for %s'), SOFTWARE_TITLE, $state['username']) . "\r\n\r\n";
    echo implode("\r\n", $codes_shown['codes']) . "\r\n";
    exit;

}

// Where a finished change goes: back through checks.php while the sign-in steps are running, otherwise this page
$return_url = ($_SESSION['s_running_login_checks'] ?? '') == '1' ? WEB_ROOT . '/checks.php' : 'index.php';

// Puts a successful change's new stamp in this session, so it stays signed in while the user's other sessions end
$keep_session = function ($stamp) {
    $_SESSION['s_two_factor_stamp'] = $stamp;
    $_SESSION['s_two_factor_verified'] = 1;
};

// The message for a code that wasn't accepted, or '' when it was. The wrong code that set the lock ('lockout') sends its
// email once the response is finished (TwoFactor::redirectThenNotice()).
$code_message = function ($result, $code_type) use ($two_factor, $user_id, $now) {
    if ($result === 'lockout' || $result === 'locked') {
        $state = $two_factor->getState($user_id);
        $minutes_left = max(1, (int) ceil(((int) ($state['locked_until'] ?? 0) - $now) / 60));
        return sprintf(ngettext('Too many wrong codes were entered. Please try again in %d minute.',
            'Too many wrong codes were entered. Please try again in %d minutes.', $minutes_left), $minutes_left);
    }
    if ($result === 'expired') return _('Your account changed while this was being saved. Please try again.');
    if ($result === 'unreadable') return _("Your authenticator code can't be checked right now. Please use one of your recovery codes.");
    if ($result === 'required') return _('MFA is required for all users, so it can\'t be turned off.');
    if ($result === 'ok') return '';
    return $code_type === 'recovery' ? _('That recovery code is not right.') : _('That code is not right.');
};

// The Password card's change, as settings/password/ made it before it moved here
$new_password = $changing_password && is_string($_POST['new_password'] ?? null) ? $sanitize->text($_POST['new_password']) : '';
$new_password_confirmation = $changing_password && is_string($_POST['new_password_confirmation'] ?? null) ? $sanitize->text($_POST['new_password_confirmation']) : '';
// As typed, since that's what's checked and hashed
$typed_password = $unsanitize->text($new_password);

/*
 * The current password, checked as sign-in checks it (password_verify() on the stored hash, which authCheck() has just
 * matched with this session's, and which the UPDATE below requires again). It isn't asked for while changing a
 * temporary password is the sign-in step, since the user has just typed it to sign in.
 */
$login = new DomainMOD\Login();
$user_info = $login->getUserInfo($_SESSION['s_user_id']);
$forced_password_change = $user_info && (string) $user_info->new_password === '1';
$current_password = $changing_password && is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
$current_password_ok = $forced_password_change
    || ($current_password !== '' && password_verify($current_password, (string) ($_SESSION['s_stored_hash'] ?? '')));

if ($changing_password && $current_password_ok && $new_password != "" && $new_password_confirmation != "" && $new_password == $new_password_confirmation && !$user->passwordHasUnusableCharacter($typed_password) && !$user->passwordTooShort($typed_password) && !$user->passwordTooLong($typed_password)) {

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :user_id
          AND email_address = :email_address");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM users
        WHERE id = :user_id
          AND email_address = :email_address");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
    $stmt->execute();
    $user_count = $stmt->fetchColumn();

    if (!$result || $user_count > 1) {

        $_SESSION['s_message_danger'] .= _('Your password could not be updated.') . '<BR>';
        $_SESSION['s_message_danger'] .= _('If the problem persists, please contact your administrator.') . '<BR>';

    } else {

        // password_hash() fails on a password it can't take, which the checks above should already have refused
        try {
            $new_hash = $user->generateHash($typed_password);
        } catch (\ValueError $e) {
            $new_hash = '';
        }

        if (!is_string($new_hash) || $new_hash === '') {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), $user->passwordUnusableMessage());

        } else {

            /*
             * Only if the account still has the password (and, with two-factor sign-in, the stamp) this session signed
             * in with, so a change made elsewhere first (such as an admin's reset) wins. A new password also forgets
             * the user's remembered devices, in the same transaction.
             */
            $stamp_condition = $two_factor->isAvailableNow() ? "
                  AND two_factor_stamp = :session_stamp" : '';
            $changed = $two_factor->changePassword($_SESSION['s_user_id'], function () use ($pdo, $time, $new_hash, $stamp_condition) {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET `password` = :new_hash,
                        new_password = '0',
                        update_time = :timestamp
                    WHERE id = :user_id
                      AND email_address = :email_address
                      AND `password` = :session_hash
                      AND active = '1'" . $stamp_condition);
                $stmt->bindValue('new_hash', $new_hash, PDO::PARAM_STR);
                $timestamp = $time->stamp();
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
                $stmt->bindValue('session_hash', $_SESSION['s_stored_hash'] ?? '', PDO::PARAM_STR);
                if ($stamp_condition !== '') $stmt->bindValue('session_stamp', $_SESSION['s_two_factor_stamp'] ?? '', PDO::PARAM_STR);
                $stmt->execute();

                return $stmt->rowCount() === 1;

            });

            if (!$changed) {

                // The account changed first, so this session ends here, with the message kept for the sign-in page
                $_SESSION = array();
                session_regenerate_id(true);
                $_SESSION['s_message_danger'] = _('Your password could not be updated.') . '<BR>';
                header('Location: ' . WEB_ROOT . '/');
                exit;

            }

            // This session keeps the new password, so only the user's other sessions are signed out. It gets a new ID
            // and security token too, so a copy of its old cookie (the reason someone may be changing their password)
            // doesn't carry on with it.
            if (!session_regenerate_id(true)) {

                // The ID couldn't be replaced, so this session ends too, and the user signs in with the new password
                $_SESSION = array();
                $_SESSION['s_message_success'] = _('Password changed') . '<BR>';
                header('Location: ' . WEB_ROOT . '/');
                exit;

            }
            $_SESSION['s_stored_hash'] = $new_hash;
            $csrf = new DomainMOD\Csrf();
            $csrf->newToken();
            // And the account's admin and read-only flags as they are now, since the session carries on
            $login->setPermissions($login->getUserInfo($_SESSION['s_user_id']));

            // The new password has just been chosen, so a two-factor setup or replacement step that comes next (such
            // as after a reset) doesn't ask for it again for the next 10 minutes
            if ($stamp_condition !== '') {
                $sign_in_steps = $two_factor->pendingSteps($two_factor->getState($_SESSION['s_user_id']));
                foreach (array('setup', 'replace') as $step) {
                    if (in_array($step, $sign_in_steps, true)) {
                        $two_factor->grantPermission($step, $_SESSION['s_user_id'], $new_hash, $_SESSION['s_two_factor_stamp'] ?? '', time());
                    }
                }
            }

            $_SESSION['s_message_success'] .= _('Password changed') . '<BR>';

            if (($_SESSION['s_running_login_checks'] ?? '') == '1') {

                header('Location: ../../checks.php');

            } else {

                header('Location: index.php');

            }
            exit;

        }

    }

} else {

    if ($changing_password) {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';

        if (!$current_password_ok) {

            $form->addFieldError('current_password', _('That password is not right.'));

        }

        if ($new_password == "" && $new_password_confirmation == "") {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), _('Your passwords were left blank'));

        } elseif ($new_password != $new_password_confirmation) {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), _("Your passwords didn't match"));

        } elseif ($user->passwordHasUnusableCharacter($typed_password)) {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), $user->passwordUnusableMessage());

        } elseif ($user->passwordTooShort($typed_password)) {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), $user->passwordTooShortMessage());

        } elseif ($user->passwordTooLong($typed_password)) {

            $form->addFieldError(array('new_password', 'new_password_confirmation'), $user->passwordTooLongMessage());

        }

    }
}

// MFA's changes. Each action is one $allowed lists (none before the database has MFA, on the demo, or while the
// password is still to be changed).
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$changing_password) {

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $code = is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
    $typed_password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (DEMO_INSTALLATION == true || !in_array($action, $allowed, true)) {

        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
        header('Location: index.php');
        exit;

    }

    $context = $two_factor->context($state, $session_hash, true);
    $password_ok = $typed_password !== '' && hash_equals((string) $state['password'], $session_hash)
        && password_verify($typed_password, $state['password']);

    if (strncmp($action, 'password_', 9) === 0) {

        // The password check that gives one action its permission for 10 minutes
        $for = substr($action, 9);
        if ($password_ok) {
            $two_factor->grantPermission($for, $user_id, $session_hash, $state['stamp'], $now);
            header('Location: index.php?do=' . $for);
            exit;
        }
        $form->addFieldError('password', _('That password is not right.'));
        $_GET['do'] = $for;

    } elseif ($action === 'setup' || $action === 'replace') {

        if ($setup === null || $setup['action'] !== $action) {

            $_SESSION['s_message_danger'] .= _('Your setup has expired. Please start again.') . '<BR>';
            header('Location: index.php');
            exit;

        }

        if ($action === 'setup' && $two_factor->isOn($state)) {

            $_SESSION['s_message_danger'] .= _('MFA was already set up in another window.') . '<BR>';
            header('Location: index.php');
            exit;

        }

        $pending_secret = $totp->base32Decode($setup['secret']);
        $step = $two_factor->isLocked($state, $now) ? false : $totp->verify($pending_secret, $code, $now, 0);

        if ($two_factor->isLocked($state, $now)) {

            $form->addFieldError('code', $code_message('locked', 'authenticator'));

        } elseif ($step === false) {

            // Checked against the new secret, but charged to the account as it's stored, so a typo can be retried
            $result = $two_factor->recordFailure($context, $now);
            $offset = $totp->clockOffsetMinutes($pending_secret, $code, $now);
            if ($offset !== false && $result === 'wrong') {
                $form->addFieldError('code', sprintf(ngettext("That code is not right, but it would be right if the server's clock were %d minute different. Please check that your server's clock is kept in sync.",
                    "That code is not right, but it would be right if the server's clock were %d minutes different. Please check that your server's clock is kept in sync.", abs($offset)), abs($offset)));
            } elseif ($result === 'lockout') {
                $_SESSION['s_message_danger'] .= $code_message($result, 'authenticator') . '<BR>';
                $two_factor->redirectThenNotice('index.php', 'locked', $state);
            } else {
                $form->addFieldError('code', $code_message($result, 'authenticator'));
            }
            $_GET['do'] = $action;

        } else {

            $saved = $two_factor->saveSecret($context, $pending_secret, $step, $now);

            if (!is_array($saved)) {

                $_SESSION['s_message_danger'] .= $code_message('expired', 'authenticator') . '<BR>';
                header('Location: index.php');
                exit;

            }

            $two_factor->usePermission($action);
            if ($action === 'replace') $two_factor->useRecoveryPermission();
            unset($_SESSION['s_two_factor_setup']);
            $keep_session($saved['stamp']);
            $_SESSION['s_two_factor_new_codes'] = array('user_id' => $user_id, 'stamp' => $saved['stamp'], 'codes' => $saved['codes']);
            $_SESSION['s_message_success'] .= _('MFA is on. Save your recovery codes now.') . '<BR>';
            $two_factor->redirectThenNotice('index.php', 'on', $state);

        }

    } elseif ($action === 'confirm_codes') {

        $shown_stamp = is_string($_POST['stamp'] ?? null) ? $_POST['stamp'] : '';

        if ($codes_shown !== null && hash_equals($codes_shown['stamp'], $shown_stamp)
            && $two_factor->confirmCodes($user_id, $session_hash, $shown_stamp)) {

            unset($_SESSION['s_two_factor_new_codes']);
            $_SESSION['s_message_success'] .= _('Your recovery codes are saved.') . '<BR>';
            header('Location: ' . $return_url);
            exit;

        }

        $_SESSION['s_message_danger'] .= _('These recovery codes have been replaced. Please use the newest set.') . '<BR>';
        header('Location: index.php');
        exit;

    } elseif ($action === 'regenerate' || $action === 'regenerate_recovery') {

        if ($action === 'regenerate') {

            $checked = $two_factor->useCode($context, $code, $now, false, function () use ($two_factor, $context) {
                return $two_factor->regenerateCodesIn($context);
            });
            $result = $checked['result'];
            $saved = $checked['change'];

        } elseif (!$two_factor->hasPermission('regenerate', $user_id, $session_hash, $state['stamp'], $now)) {

            header('Location: index.php?do=regenerate');
            exit;

        } else {

            $saved = $two_factor->inTransaction($context, $now, false, function () use ($two_factor, $context) {
                return $two_factor->regenerateCodesIn($context);
            });
            $result = is_array($saved) ? 'ok' : $saved;

        }

        if ($result === 'ok' && is_array($saved)) {

            if ($action === 'regenerate_recovery') {
                $two_factor->usePermission('regenerate');
                $two_factor->useRecoveryPermission();
            }
            $keep_session($saved['stamp']);
            $_SESSION['s_two_factor_new_codes'] = array('user_id' => $user_id, 'stamp' => $saved['stamp'], 'codes' => $saved['codes']);
            $_SESSION['s_message_success'] .= _('You have new recovery codes. Save them now.') . '<BR>';
            header('Location: index.php');
            exit;

        }

        // A page message rather than a field's, since the page has several forms
        $_SESSION['s_message_danger'] .= $code_message($result, $checked['type'] ?? 'authenticator') . '<BR>';
        if ($result === 'lockout') $two_factor->redirectThenNotice('index.php', 'locked', $state);
        header('Location: index.php');
        exit;

    } elseif ($action === 'turn_off') {

        $turn_off_code = is_string($_POST['turn_off_code'] ?? null) ? $_POST['turn_off_code'] : '';

        if (!$password_ok) {

            $_SESSION['s_message_danger'] .= _('That password is not right.') . '<BR>';

        } else {

            $checked = $two_factor->useCode($context, $turn_off_code, $now, true, function () use ($two_factor, $user_id) {
                if ($two_factor->isRequiredIn()) return 'required';
                return array('stamp' => $two_factor->clearIn($user_id));
            });

            if ($checked['result'] === 'ok') {

                $keep_session($checked['change']['stamp']);
                unset($_SESSION['s_two_factor_new_codes'], $_SESSION['s_two_factor_recovery']);
                $_SESSION['s_message_success'] .= _('MFA is off.') . '<BR>';
                $two_factor->redirectThenNotice('index.php', 'off', $state);

            }

            $_SESSION['s_message_danger'] .= $code_message($checked['result'], $checked['type']) . '<BR>';
            if ($checked['result'] === 'lockout') $two_factor->redirectThenNotice('index.php', 'locked', $state);

        }

        // A page message rather than a field's, since the page has several forms
        header('Location: index.php');
        exit;

    } elseif ($action === 'forget_devices') {

        $two_factor->forgetDevices($user_id, $session_hash, $state['stamp']);
        setcookie(DomainMOD\TwoFactor::deviceCookieName(), '', array('expires' => 1,
            'path' => DomainMOD\TwoFactor::deviceCookiePath(), 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'));
        // The delete only runs while the account still matches the session, so it says so if any are left
        if ($two_factor->countDevices($user_id, $state['stamp'], $now) === 0) {
            $_SESSION['s_message_success'] .= _('Every remembered device has been forgotten.') . '<BR>';
        } else {
            $_SESSION['s_message_danger'] .= _('Your account changed while this was being saved. Please try again.') . '<BR>';
        }
        header('Location: index.php');
        exit;

    }

}

// What the page shows: a step in progress (?do=), the codes waiting to be saved, or the overview
$do = is_string($_GET['do'] ?? null) ? $_GET['do'] : '';
if (!in_array($do, array('setup', 'replace', 'regenerate'), true) || !in_array('password_' . $do, $allowed, true)) $do = '';
if ($do === '' && in_array('setup', $steps, true) && in_array('password_setup', $allowed, true)) $do = 'setup';
if ($do === '' && in_array('replace', $steps, true) && in_array('password_replace', $allowed, true)) $do = 'replace';
$do_permitted = $do !== '' && $two_factor->hasPermission($do, $user_id, $session_hash, $state['stamp'], $now);

// A new secret for a setup that's starting, tied to the permission that allows it
if (($do === 'setup' || $do === 'replace') && $do_permitted && ($setup === null || $setup['action'] !== $do)) {

    $setup = array('action' => $do, 'user_id' => $user_id, 'stamp' => (string) $state['stamp'],
        'secret' => $totp->base32Encode($totp->newSecret()), 'time' => $now,
        'permission_time' => $_SESSION['s_two_factor_permissions'][$do]['time']);
    $_SESSION['s_two_factor_setup'] = $setup;

}

// <host> from the install's full URL, so people with several installs can tell them apart in their app
$host = (string) parse_url((string) ($_SESSION['s_system_full_url'] ?? ''), PHP_URL_HOST);
$port = parse_url((string) ($_SESSION['s_system_full_url'] ?? ''), PHP_URL_PORT);
$account = ($state['username'] ?? '') . ($host !== '' ? '@' . $host . ($port ? ':' . $port : '') : '');
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
// The Password card comes first, except while an MFA step (the codes to save, a password check, or a new
// authenticator) fills the page. Each card's button is under the card, with space after it before the next card.
if ($do === '' && !($codes_shown !== null && in_array('confirm_codes', $allowed, true))) {

    echo $form->showFormTop('<div class="domainmod-form mb-5">');
    ob_start();
    echo $form->showInputHidden('action', 'change_password');
    // The current password on a row of its own, in the left column (not asked for during the forced change at sign-in)
    if (!$forced_password_change) {
        echo $form->showInputText('current_password', _('Current Password'), '', '', '72', 'current', '1',
            '<div class="domainmod-span-all"><div class="domainmod-form-section-grid">', '</div></div>');
    }
    // The cursor starts in New Password when changing the password is the sign-in step to do
    echo $form->showInputText('new_password', _('New Password') . ' (72)', $user->passwordLengthHint(), '', '72', '1', '1', '', '', $forced_password_change ? '1' : '');
    // A hint line like the New Password field's, so the two fields line up side by side
    echo $form->showInputText('new_password_confirmation', _('Confirm New Password') . ' (72)', _('Type the same password again.'), '', '72', '1', '1', '', '');
    echo $layout->formSection(_('Password'), 'password', ob_get_clean());
    echo $form->showSubmitButton(_('Change Password'), '', '');
    echo $form->showFormBottom('</div>');

}

// MFA's card, with $body already escaped for the page
$mfa_card = function ($body) use ($layout) {
    return $layout->formSection(_('MFA'), 'two-factor', $body);
};

// MFA's card with only a note, at the same width as the page's other cards
$mfa_note = function ($note) use ($mfa_card) {
    return '<div class="domainmod-form">' . $mfa_card('<p class="domainmod-span-all">' . $note . '</p>') . '</div>';
};

if (!$two_factor_available) {

    echo $mfa_note(_('MFA will be available once the database has been upgraded.'));

} elseif (DEMO_INSTALLATION == true) {

    echo $mfa_note(_("MFA isn't available on the demo."));

} elseif (in_array('password', $steps, true)) {

    // Nothing of MFA is allowed until the password has been changed (TwoFactor::allowedActions())
    echo $mfa_note(_('Please change your password first.'));

} elseif ($codes_shown !== null && in_array('confirm_codes', $allowed, true)) {

    // The codes waiting to be saved, with Copy and Download, and the confirmation for this set
    $codes_id = 'domainmod-recovery-codes';
    ob_start(); ?>
    <div class="domainmod-span-all">
        <p><?php echo _('Each recovery code works once, in place of a code from your authenticator app. Keep them somewhere safe, such as a password manager. They are not shown again.'); ?></p>
        <pre class="bg-body-tertiary border rounded p-2 mb-3" dir="ltr"><code id="<?php echo $codes_id; ?>"><?php echo htmlspecialchars(implode("\n", $codes_shown['codes']), ENT_QUOTES, 'UTF-8'); ?></code></pre>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-domainmod-copy="<?php echo $codes_id; ?>" data-domainmod-copied="<?php echo htmlspecialchars(_('Copied'), ENT_QUOTES, 'UTF-8'); ?>" data-domainmod-copy-failed="<?php echo htmlspecialchars(_('Selected. Copy it yourself.'), ENT_QUOTES, 'UTF-8'); ?>" hidden><i class="bi bi-clipboard me-1" aria-hidden="true"></i><span><?php echo htmlspecialchars(_('Copy'), ENT_QUOTES, 'UTF-8'); ?></span></button>
            <span class="visually-hidden" role="status"></span>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?download=1"><i class="bi bi-download me-1" aria-hidden="true"></i><?php echo _('Download'); ?></a>
        </div>
    </div><?php
    $codes_card = $layout->formSection(_('Recovery Codes'), 'recovery-codes', ob_get_clean());
    // The card is inside the form it confirms, with the button under it, like the page's other forms
    echo $form->showFormTop('<div class="domainmod-form">');
    echo $form->showInputHidden('action', 'confirm_codes');
    echo $form->showInputHidden('stamp', $codes_shown['stamp']);
    echo $codes_card;
    echo $form->showSubmitButton(_("I've Saved These Codes"), '', '');
    echo $form->showFormBottom('</div>');

} elseif ($do !== '' && !$do_permitted) {

    // The password check for this action. The card holds the prompt and the Password field, in the same grid as the
    // page's other cards, and the Continue button is under the card.
    echo $form->showFormTop('<div class="domainmod-form mb-5">');
    ob_start();
    echo '<p class="domainmod-span-all">' . _('Please enter your password to continue.') . '</p>';
    echo $form->showInputHidden('action', 'password_' . $do);
    echo $form->showInputText('password', _('Password'), '', '', '72', '1', '1', '', '', '1');
    echo $layout->formSection(_('Password'), 'password-check', ob_get_clean());
    echo $form->showSubmitButton(_('Continue'), '', '');
    echo $form->showFormBottom('</div>');

} elseif (($do === 'setup' || $do === 'replace') && $setup !== null) {

    // The new secret: the QR code, the secret as text for apps without a camera, and its first code
    echo '<p>' . sprintf(_('Setting up MFA for %s.'), $layout->ltr($state['username'])) . ' '
        . _('Scan the QR code with your authenticator app, or type the key into it, then enter the six-digit code it shows.') . '</p>';
    $uri = $totp->otpauthUri($totp->base32Decode($setup['secret']), $account, SOFTWARE_TITLE);
    $setup_label = $do === 'replace' ? _('Set Up a New Authenticator') : _('Turn On MFA');
    // The card holds the QR code, the key, and the Code field, in the same grid as the page's other cards, and the
    // button is under the card.
    echo $form->showFormTop('<div class="domainmod-form mb-5">');
    ob_start(); ?>
    <div class="domainmod-two-factor-qr domainmod-span-all mb-3" data-domainmod-otpauth="<?php echo htmlspecialchars($uri, ENT_QUOTES, 'UTF-8'); ?>" data-domainmod-qr-label="<?php echo htmlspecialchars(_('QR code for your authenticator app'), ENT_QUOTES, 'UTF-8'); ?>"></div>
    <p class="domainmod-span-all"><?php echo _('Key'); ?>: <strong><?php echo $layout->ltr(implode(' ', str_split($setup['secret'], 4))); ?></strong></p><?php
    echo $form->showInputHidden('action', $do);
    echo $form->showInputText('code', _('Code'), '', '', '7', '', '1', '', '', '1', 'ltr');
    echo $layout->formSection($setup_label, 'setup', ob_get_clean());
    echo $form->showSubmitButton($setup_label, '', '');
    echo $form->showFormBottom('</div>');
    $show_qr = true;

} elseif ($do === 'regenerate' && $do_permitted) {

    echo '<p>' . _('This replaces all of your recovery codes with a new set.') . '</p>';
    echo $form->showFormTop('');
    echo $form->showInputHidden('action', 'regenerate_recovery');
    echo $form->showSubmitButton(_('Regenerate Recovery Codes'), '', '');
    echo $form->showFormBottom('');

} elseif (!$two_factor->isOn($state)) {

    echo '<div class="domainmod-form mb-5">' . $mfa_card('<p class="domainmod-span-all">' . _('Multi-Factor Authentication (MFA) asks for a code from an authenticator app (such as Google Authenticator, Aegis, 1Password, or Bitwarden) after your password, so someone who learns your password still can\'t sign in.') . '</p>')
        . $layout->linkButton('index.php?do=setup', _('Turn On MFA'), 'primary') . '</div>';

} else {

    // MFA is on: its card with the overview, then what can be done with it
    ob_start();
    if ($has_recovery_permission) {

        echo '<p class="domainmod-span-all">' . _('You signed in with a recovery code. If you no longer have your authenticator app, set up a new one now.') . '</p>';

    }

    if (!in_array('codes', $steps, true)) {

        $devices = $two_factor->countDevices($user_id, $state['stamp'], $now);
        $codes_left = $two_factor->remainingCodes($user_id);
        echo '<div class="domainmod-span-all">' . $layout->factList(array(
            array('label' => _('MFA'), 'value' => _('On')),
            array('label' => _('Turned On'), 'value' => $time->toUserTimezone($state['enabled_time'], 'Y-m-d H:i'), 'ltr' => true),
            array('label' => _('Recovery Codes Left'), 'value' => (string) $codes_left, 'ltr' => true),
            array('label' => _('Remembered Devices'), 'value' => (string) $devices, 'ltr' => true))) . '</div>';

    } else {

        echo '<p class="domainmod-span-all">' . _('Your recovery codes need to be replaced with a new set before you continue.') . '</p>';

    }
    echo '<div class="domainmod-form mb-5">' . $mfa_card(ob_get_clean());
    // After a recovery code sign-in, its buttons are under the card
    if ($has_recovery_permission) {

        // Beside each other, wrapping on phones with space between the rows
        echo '<div class="d-flex flex-wrap gap-2">' . $layout->linkButton('index.php?do=replace', _('Set Up a New Authenticator'), 'primary')
            . $layout->linkButton('index.php?do=regenerate', _('Regenerate Recovery Codes')) . '</div>';

    }
    echo '</div>';

    // Regenerating the recovery codes with a current code
    echo $form->showFormTop('<div class="domainmod-form mb-5">');
    ob_start();
    echo $form->showInputHidden('action', 'regenerate');
    echo $form->showInputText('code', _('Authenticator Code or Recovery Code'), '', '', '30', '', '1', '', '', '', 'ltr');
    echo $layout->formSection(_('Regenerate Recovery Codes'), 'regenerate', ob_get_clean());
    echo $form->showSubmitButton(_('Regenerate Recovery Codes'), '', '');
    echo $form->showFormBottom('</div>');

    if (in_array('forget_devices', $allowed, true) && !in_array('codes', $steps, true) && $devices > 0) {

        echo $form->showFormTop('<div class="domainmod-form mb-5">');
        ob_start();
        echo '<p class="domainmod-span-all">' . _('Devices you chose to remember skip the code step for 30 days. This makes every one of them ask for a code again.') . '</p>';
        echo $form->showInputHidden('action', 'forget_devices');
        echo $layout->formSection(_('Remembered Devices'), 'devices', ob_get_clean());
        echo $form->showSubmitButton(_('Forget All Devices'), '', '');
        echo $form->showFormBottom('</div>');

    }

    if (in_array('turn_off', $allowed, true)) {

        echo $form->showFormTop('<div class="domainmod-form mb-5">');
        ob_start();
        echo $form->showInputHidden('action', 'turn_off');
        echo $form->showInputText('password', _('Password'), '', '', '72', '1', '1', '', '');
        echo $form->showInputText('turn_off_code', _('Authenticator Code or Recovery Code'), '', '', '30', '', '1', '', '', '', 'ltr');
        echo $layout->formSection(_('Turn Off MFA'), 'turn-off', ob_get_clean());
        echo $form->showSubmitButton(_('Turn Off MFA'), '', '');
        echo $form->showFormBottom('</div>');

    }

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/copy-command.js'); ?>"></script><?php
if (!empty($show_qr)) { ?>
<script src="<?php echo $layout->assetUrl(WEBROOT_THEME . '/plugins/qrcode-generator/qrcode.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/two-factor-qr.js'); ?>"></script><?php
} ?>
</body>
</html>
