<?php
/**
 * /two-factor.php
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
$layout = new DomainMOD\Layout();
$login = new DomainMOD\Login();
$form = new DomainMOD\Form();
$two_factor = new DomainMOD\TwoFactor();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/two-factor.inc.php';

/*
 * The second sign-in step. index.php sends a user here once their password is right and they have two-factor sign-in
 * on, with only s_two_factor_pending set (nothing that signs the session in), and this page signs them in once their
 * authenticator code or a recovery code is right.
 */
$system->loginCheck();

$pending = $_SESSION['s_two_factor_pending'] ?? null;
$now = time();

if (!is_array($pending)) {

    header('Location: ' . WEB_ROOT . '/');
    exit;

}

if ($now - (int) $pending['time'] > DomainMOD\TwoFactor::PENDING_SECONDS || $now < (int) $pending['time']) {

    unset($_SESSION['s_two_factor_pending']);
    $_SESSION['s_message_danger'] .= _('Your sign-in attempt has expired. Please sign in again.') . '<BR>';
    header('Location: ' . WEB_ROOT . '/');
    exit;

}

// The account as it is now, which has to be the one the password was checked against
try {
    $state = $two_factor->isAvailableNow() ? $two_factor->getState($pending['user_id']) : false;
} catch (\Throwable $e) {
    $state = false;
}

if (!$state || !$two_factor->isOn($state) || !hash_equals((string) $state['password'], (string) $pending['password_hash'])
    || !hash_equals((string) $state['stamp'], (string) $pending['stamp'])) {

    unset($_SESSION['s_two_factor_pending']);
    $_SESSION['s_message_danger'] .= _('Your sign-in attempt has expired. Please sign in again.') . '<BR>';
    header('Location: ' . WEB_ROOT . '/');
    exit;

}

$secret_readable = $two_factor->decodeSecret($state['secret'], $state['id']) !== false;
$use_recovery = !$secret_readable || ($_GET['recovery'] ?? '') === '1' || ($_POST['recovery'] ?? '') === '1';
$locked = $two_factor->isLocked($state, $now);
$can_remember = DomainMOD\TwoFactor::canRememberDevices();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$locked) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check('two-factor.php');

    $code = is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
    $remember = $can_remember && ($_POST['remember'] ?? '') === '1';
    $context = $two_factor->context($state, $pending['password_hash'], false);

    if ($code === '') {

        $form->addFieldError('code', $use_recovery ? _('Enter a recovery code') : _('Enter the code from your authenticator app'));

    } elseif (!session_regenerate_id(true)) {

        // Checked before the code is used, so a recovery code isn't spent on a sign-in that can't finish
        $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

    } else {

        // The secret as the code check leaves it (it may have been saved again encrypted), for remembering the device
        $checked = $two_factor->useCode($context, $code, $now, false, function ($stored) {
            return $stored;
        });

        if ($checked['result'] === 'ok') {

            unset($_SESSION['s_two_factor_pending']);

            // The session ID was replaced just before the code was used
            if ($login->completeSignIn($state['id'], $state['username'], $pending['password_hash'],
                array('stamp' => $state['stamp'], 'verified' => 1), true)) {

                // The sign-in has just checked the password, so a restricted session's replacement step (the secret
                // can't be decrypted) doesn't ask for it again for the next 10 minutes
                if (in_array('replace', $two_factor->pendingSteps($state), true)) {
                    $two_factor->grantPermission('replace', $state['id'], $pending['password_hash'], $state['stamp'], $now);
                }

                if ($checked['type'] === 'recovery') {

                    $two_factor->grantRecoveryPermission($state['id'], $state['stamp']);
                    $codes_left = $two_factor->remainingCodes($state['id']);
                    $_SESSION['s_message_warning'] .= sprintf(ngettext('You signed in with a recovery code. You have %d recovery code left.',
                        'You signed in with a recovery code. You have %d recovery codes left.', $codes_left), $codes_left)
                        . ' <a href="' . htmlspecialchars(WEB_ROOT . '/settings/two-factor/', ENT_QUOTES, 'UTF-8') . '">'
                        . _('Set up a new authenticator') . '</a>.<BR>';

                }

                $token = false;
                if ($remember) {
                    $context['secret'] = $checked['change']['secret'];
                    $token = $two_factor->rememberDevice($context, $now)['token'];
                }
                if (is_string($token)) {
                    setcookie(DomainMOD\TwoFactor::deviceCookieName(), $token, array(
                        'expires' => $now + DomainMOD\TwoFactor::REMEMBER_DAYS * 86400,
                        'path' => DomainMOD\TwoFactor::deviceCookiePath(), 'secure' => true, 'httponly' => true,
                        'samesite' => 'Lax'));
                }

                header('Location: checks.php');
                exit;

            }

            $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';
            header('Location: ' . WEB_ROOT . '/');
            exit;

        } elseif ($checked['result'] === 'unreadable') {

            $use_recovery = true;
            $secret_readable = false;

        } elseif ($checked['result'] === 'lockout' || $checked['result'] === 'locked') {

            // Only the wrong code that set the lock sends the email, after showing this page again (still pending, so it
            // shows the lock), so a slow mail server can't keep the page waiting
            if ($checked['result'] === 'lockout') $two_factor->redirectThenNotice('two-factor.php', 'locked', $state);
            $locked = true;
            $state = $two_factor->getState($state['id']) ?: $state;

        } elseif ($checked['result'] === 'expired') {

            unset($_SESSION['s_two_factor_pending']);
            $_SESSION['s_message_danger'] .= _('Your sign-in attempt has expired. Please sign in again.') . '<BR>';
            header('Location: ' . WEB_ROOT . '/');
            exit;

        } else {

            $_SESSION['s_two_factor_pending']['wrong'] = (int) $pending['wrong'] + 1;

            if ($_SESSION['s_two_factor_pending']['wrong'] >= DomainMOD\TwoFactor::PENDING_MAX_WRONG) {

                unset($_SESSION['s_two_factor_pending']);
                $_SESSION['s_message_danger'] .= _('Too many wrong codes. Please sign in again.') . '<BR>';
                header('Location: ' . WEB_ROOT . '/');
                exit;

            }

            $form->addFieldError('code', $use_recovery ? _('That recovery code is not right.') : _('That code is not right.'));

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('login'); ?>
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>

<div class="card card-outline card-danger">
    <div class="card-body login-card-body"><?php

        if ($locked) {

            // The lock outlasts this sign-in attempt, so the user signs in again once it's over
            unset($_SESSION['s_two_factor_pending']);
            $minutes_left = max(1, (int) ceil(((int) $state['locked_until'] - $now) / 60)); ?>

            <p class="login-box-msg"><?php echo sprintf(ngettext('Too many wrong codes were entered. Please sign in again in %d minute.',
                'Too many wrong codes were entered. Please sign in again in %d minutes.', $minutes_left), $minutes_left); ?></p><?php

        } else {

            if (!$secret_readable) { ?>

                <p class="login-box-msg"><?php echo _("Your authenticator code can't be checked right now. Please use one of your recovery codes."); ?></p><?php

            } elseif ($use_recovery) { ?>

                <p class="login-box-msg"><?php echo _('Enter one of your recovery codes.'); ?></p><?php

            } else { ?>

                <p class="login-box-msg"><?php echo _('Enter the code from your authenticator app.'); ?></p><?php

            } ?>

            <form action="two-factor.php" method="post">
                <?php echo $form->showCsrfField(); ?>
                <?php if ($use_recovery) { ?><input type="hidden" name="recovery" value="1"><?php } ?>
                <div class="input-group has-validation mb-3"><?php
                    if ($use_recovery) { ?>
                    <input type="text" class="form-control<?php echo $form->errorClass('code'); ?>" placeholder="<?php echo htmlspecialchars(_('Recovery Code'), ENT_QUOTES, 'UTF-8'); ?>" name="code" id="<?php echo $form->fieldId('code'); ?>" maxlength="30" autocomplete="off" autocapitalize="characters" spellcheck="false" dir="ltr"<?php echo $form->errorAttributes('code'); ?><?php if (!DomainMOD\System::$error_shown) echo ' autofocus'; ?>><?php
                    } else { ?>
                    <input type="text" class="form-control<?php echo $form->errorClass('code'); ?>" placeholder="<?php echo htmlspecialchars(_('Code'), ENT_QUOTES, 'UTF-8'); ?>" name="code" id="<?php echo $form->fieldId('code'); ?>" maxlength="7" autocomplete="one-time-code" inputmode="numeric" dir="ltr"<?php echo $form->errorAttributes('code'); ?><?php if (!DomainMOD\System::$error_shown) echo ' autofocus'; ?>><?php
                    } ?>
                    <?php echo $form->errorFeedback('code'); ?>
                </div><?php

                if ($can_remember) { ?>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="domainmod-remember-device">
                    <label class="form-check-label" for="domainmod-remember-device"><?php echo sprintf(_('Remember This Device for %d Days'), DomainMOD\TwoFactor::REMEMBER_DAYS); ?></label>
                </div><?php

                } ?>

                <div class="row">
                    <div class="col-8">
                        &nbsp;
                    </div>
                    <!-- /.col -->
                    <div class="col-4">
                        <button type="submit" class="btn btn-danger w-100"><?php echo _('Verify'); ?></button>
                    </div>
                    <!-- /.col -->
                </div>
            </form><?php

        } ?>
    </div>
    <!-- /.login-card-body -->
</div>

<BR>
<p class="mb-1"><?php

    if (!$locked && $secret_readable) {

        if ($use_recovery) { ?>

            <a href="two-factor.php"><?php echo _('Use the Authenticator App'); ?></a><BR><?php

        } else { ?>

            <a href="two-factor.php?recovery=1"><?php echo _('Use a Recovery Code'); ?></a><BR><?php

        }

    } ?>

    <a href="logout.php"><?php echo _('Cancel'); ?></a>
</p>

<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
