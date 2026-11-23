<?php
/**
 * /index.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/index.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$login = new DomainMOD\Login();
$user = new DomainMOD\User();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$format = new DomainMOD\Format();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/login.inc.php';

$system->loginCheck();
$pdo = $deeb->cnxx;

$_SESSION['s_installation_mode'] = $system->installMode();

if ($_SESSION['s_installation_mode'] === 1) {

    header("Location: install/");
    exit;

}

// The sign-in form carries the security token too, so another site can't sign a visitor in to an account it chose
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $csrf = new DomainMOD\Csrf();
    $csrf->check(WEB_ROOT . '/');

}

// Only a plain string counts (a crafted request could send an array, which the form below can't print back)
$new_username = is_string($_POST['new_username'] ?? null) ? $_POST['new_username'] : '';
$new_password = $_POST['new_password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "") {

    $_SESSION['s_read_only'] = '1';

    // Check to see if the user's password is still stored in the older format (the one MySQL's PASSWORD() made)
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :new_username
          AND `password` = :legacy_hash
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->bindValue('legacy_hash', $user->generateLegacyHash($new_password), PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    // Move the stored password to the current format. password_hash() can't take a NUL byte, so a password with one
    // keeps its older hash, and the sign-in fails below instead of with a server error.
    if ($result) {

        try {
            $new_hashed_password = $user->generateHash($new_password);
        } catch (\ValueError $e) {
            $new_hashed_password = '';
        }

    }

    if ($result && is_string($new_hashed_password) && $new_hashed_password !== '') {

        $stmt = $pdo->prepare("
            UPDATE `users`
            SET password = :new_hashed_password
            WHERE username = :new_username
              AND `password` = :legacy_hash
              AND active = '1'");
        $stmt->bindValue('new_hashed_password', $new_hashed_password, PDO::PARAM_STR);
        $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
        $stmt->bindValue('legacy_hash', $user->generateLegacyHash($new_password), PDO::PARAM_STR);
        $stmt->execute();

    }

    // Check to see if the user's password matches
    $stmt = $pdo->prepare("
        SELECT `password`
        FROM users
        WHERE username = :new_username
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->execute();
    $stored_hash = $stmt->fetchColumn();

    if (is_string($stored_hash) && password_verify($new_password, $stored_hash)) {

        // Rehash a password stored with an older algorithm or a lower cost than this PHP's default (PHP 8.4 raised
        // bcrypt's from 10 to 12). password_needs_rehash() is also true for a higher cost, such as a cost 12 hash on
        // PHP 8.3, so a hash is never replaced with a weaker one. A typed password with a NUL byte can pass
        // password_verify() but can't be hashed, so it keeps its stored hash.
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {

            try {
                $rehashed_password = $user->generateHash($new_password);
            } catch (\ValueError $e) {
                $rehashed_password = '';
            }

            if (is_string($rehashed_password) && $rehashed_password !== '') {

                $stored_info = password_get_info($stored_hash);
                $rehashed_info = password_get_info($rehashed_password);
                $stored_is_as_strong = $stored_info['algo'] === PASSWORD_BCRYPT && $rehashed_info['algo'] === PASSWORD_BCRYPT
                    && ($stored_info['options']['cost'] ?? 0) >= ($rehashed_info['options']['cost'] ?? 0);

                if (!$stored_is_as_strong) {

                    // Only if the stored hash hasn't changed since it was read, such as a sign-in from another browser
                    $stmt = $pdo->prepare("
                        UPDATE `users`
                        SET password = :rehashed_password
                        WHERE username = :new_username
                          AND `password` = :stored_hash
                          AND active = '1'");
                    $stmt->bindValue('rehashed_password', $rehashed_password, PDO::PARAM_STR);
                    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
                    $stmt->bindValue('stored_hash', $stored_hash, PDO::PARAM_STR);
                    $stmt->execute();

                    if ($stmt->rowCount() === 1) {

                        $stored_hash = $rehashed_password;

                    } else {

                        // Another sign-in changed it first, so the session gets the hash that's stored now
                        $stmt = $pdo->prepare("
                            SELECT `password`
                            FROM users
                            WHERE username = :new_username
                              AND active = '1'");
                        $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
                        $stmt->execute();
                        $current_hash = $stmt->fetchColumn();

                        if (is_string($current_hash) && password_verify($new_password, $current_hash)) {
                            $stored_hash = $current_hash;
                        }

                    }

                }

            }

        }

        $user_id = (int) $user->getUserId($new_username);
        $now = time();

        // The password is right, so the user's own language is used for the code step (kept with the pending sign-in
        // below, so an abandoned code step doesn't leave it behind) and, once signed in, for the first messages from
        // checks.php, which would otherwise show in the sign-in page's language
        $user_language = $login->getUserLanguage($user_id);
        $user_language = $user_language ? (string) $user_language->default_language : '';

        /*
         * Two-factor sign-in, once the live database has it (before the upgrade nobody can have turned it on). If the
         * version or the user's state can't be read, the sign-in is refused rather than skipping the second step.
         */
        $two_factor = new DomainMOD\TwoFactor();
        try {
            $two_factor_available = $two_factor->isAvailableNow();
            $two_factor_state = $two_factor_available ? $two_factor->getState($user_id) : null;
        } catch (\Throwable $e) {
            $two_factor_available = true;
            $two_factor_state = false;
        }

        $device_cookie = DomainMOD\TwoFactor::deviceCookieName();
        $device_stamp = false;
        if ($two_factor_available && $two_factor->isOn($two_factor_state) && DomainMOD\TwoFactor::canRememberDevices()
            && isset($_COOKIE[$device_cookie])) {

            // A remembered device skips the code, unless the secret can't be decrypted (that needs a recovery code)
            if ($two_factor->decodeSecret($two_factor_state['secret'], $user_id) !== false) {
                $device_stamp = $two_factor->findDevice($user_id, $stored_hash, $_COOKIE[$device_cookie], $now);
            }
            if ($device_stamp === false) {
                setcookie($device_cookie, '', array('expires' => 1, 'path' => DomainMOD\TwoFactor::deviceCookiePath(),
                    'secure' => true, 'httponly' => true, 'samesite' => 'Lax'));
            }

        }

        if ($two_factor_available && $two_factor_state === false) {

            $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

        } elseif ($two_factor_available && $two_factor->isOn($two_factor_state) && $device_stamp === false && !session_regenerate_id(true)) {

            $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

        } elseif ($two_factor_available && $two_factor->isOn($two_factor_state) && $device_stamp === false) {

            // The code step, under the new session ID: nothing that signs the session in is set until the code is
            // right (two-factor.php)
            $_SESSION['s_two_factor_pending'] = array('user_id' => $user_id, 'username' => $new_username,
                'password_hash' => $stored_hash, 'stamp' => (string) $two_factor_state['stamp'], 'time' => $now,
                'wrong' => 0, 'language' => $user_language,
                'language_until' => $now + DomainMOD\TwoFactor::PENDING_SECONDS);
            $csrf = new DomainMOD\Csrf();
            $csrf->newToken();

            header("Location: two-factor.php");
            exit;

        } else {

            $two_factor_session = null;
            if ($two_factor_available) {
                $two_factor_session = array('stamp' => $device_stamp !== false ? $device_stamp : $two_factor_state['stamp'],
                    'verified' => $device_stamp !== false ? 1 : 0);
            }

            if ($login->completeSignIn($user_id, $new_username, $stored_hash, $two_factor_session)) {

                // The sign-in has just checked the password, so a restricted session's setup step (two-factor sign-in
                // is required and not set up yet) doesn't ask for it again for the next 10 minutes
                if ($two_factor_available && in_array('setup', $two_factor->pendingSteps($two_factor_state), true)) {
                    $two_factor->grantPermission('setup', $user_id, $stored_hash, $two_factor_session['stamp'], $now);
                }

                if ($user_language !== '') $_SESSION['s_default_language'] = $user_language;
                header("Location: checks.php");
                exit;

            }

            $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

        }

    } else {

        $log_message = 'Unable to login';
        $log_extra = array('Username' => $new_username, 'Password' => $format->obfusc($new_password));
        $log->warning($log_message, $log_extra);

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';

        if ($new_username == "" && $new_password == "") {

            $form->addFieldError(array('new_username', 'new_password'), _('Enter your username & password'));

        } elseif ($new_username == "" || $new_password == "") {

            if ($new_username == "") $form->addFieldError('new_username', _('Enter your username'));
            if ($new_password == "") $form->addFieldError('new_password', _('Enter your password'));

        }

    }

}
$new_password = "";
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('login'); ?>
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>

<div class="card card-outline card-danger">
    <div class="card-body login-card-body">
        <p class="login-box-msg"><?php echo _('Please enter your username and password'); ?></p><?php

        if (DEMO_INSTALLATION == true) { ?>

            <strong><?php echo _('Demo Username'); ?>:</strong> demo<BR>
            <strong><?php echo _('Demo Password'); ?>:</strong> demo<BR><BR><?php

        } ?>

        <form action="" method="post">
            <?php echo $form->showCsrfField(); ?>
            <div class="input-group has-validation mb-3">
                <input type="text" class="form-control<?php echo $form->errorClass('new_username'); ?>" placeholder="<?php echo htmlspecialchars(_('Username'), ENT_QUOTES, 'UTF-8'); ?>" name="new_username" id="<?php echo $form->fieldId('new_username'); ?>" value="<?php echo htmlspecialchars($new_username, ENT_QUOTES, 'UTF-8'); // kept after a mistake, so only the password needs typing again ?>" maxlength="30"<?php echo $form->errorAttributes('new_username'); ?><?php if (!DomainMOD\System::$error_shown && $_SERVER['REQUEST_METHOD'] !== 'POST') echo ' autofocus'; // not when there's an error to read first ?>>
                <?php echo $form->errorFeedback('new_username'); ?>
            </div>
            <div class="input-group has-validation mb-3">
                <input type="password" class="form-control<?php echo $form->errorClass('new_password'); ?>" placeholder="<?php echo htmlspecialchars(_('Password'), ENT_QUOTES, 'UTF-8'); ?>" name="new_password" id="<?php echo $form->fieldId('new_password'); ?>" maxlength="72"<?php echo $form->errorAttributes('new_password'); ?>>
                <?php echo $form->errorFeedback('new_password'); ?>
            </div>
            <div class="row">
                <div class="col-8">
                    &nbsp;
                </div>
                <!-- /.col -->
                <div class="col-4">
                    <button type="submit" class="btn btn-danger w-100"><?php echo _('Sign In'); ?></button>
                </div>
                <!-- /.col -->
            </div>
        </form>
    </div>
    <!-- /.login-card-body -->
</div><?php

if (DEMO_INSTALLATION == false) { ?>

    <BR>
    <p class="mb-1">
        <a href="reset.php"><?php echo _('Forgot your password?'); ?></a>
    </p><?php

}
?>

<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
