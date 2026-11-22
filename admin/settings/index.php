<?php
/**
 * /admin/settings/index.php
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
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-settings.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data
$pdo = $deeb->cnxx;

/*
 * "Require MFA for All Users", saved by its own form. TwoFactor::setRequired() checks the admin again in
 * the database (the session's admin and read-only flags are only loaded at sign-in). Never on the demo.
 */
$two_factor = new DomainMOD\TwoFactor();
$two_factor_available = $two_factor->isAvailableNow();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['two_factor_form'] ?? '') === '1') {

    if (DEMO_INSTALLATION == true || !$two_factor_available) {

        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';

    } else {

        $two_factor_result = $two_factor->setRequired($_SESSION['s_user_id'], $_SESSION['s_stored_hash'] ?? '',
            $_SESSION['s_two_factor_stamp'] ?? '', (int) ($_POST['new_two_factor_required'] ?? 0) === 1);

        if ($two_factor_result === 'ok') {
            $_SESSION['s_message_success'] .= _('The MFA setting was updated.') . '<BR>';
        } elseif ($two_factor_result === 'not_on') {
            $_SESSION['s_message_danger'] .= _('Turn on MFA for your own account (under Settings) before requiring it for everyone.') . '<BR>';
        } else {
            $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
        }

    }

    header("Location: index.php");
    exit;

}

/*
 * How long the Change History is kept, saved by its own form, and only once the database update has added the setting
 * (ChangeHistory::isAvailable()), so the page keeps working before then. 0 is Forever.
 */
$history_available = DomainMOD\ChangeHistory::isAvailable($pdo);
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['history_form'] ?? '') === '1') {

    $new_history_retention = filter_var($_POST['new_history_retention_months'] ?? '', FILTER_VALIDATE_INT);

    // The administrator and read-only flags as the database has them now, since a shorter setting lets the next System
    // Cleanup remove history for good, read with the user's row locked in the same transaction as the save, so access removed meanwhile counts
    $history_saved = false;
    if ($history_available && in_array($new_history_retention, DomainMOD\ChangeHistory::RETENTION_CHOICES, true)) {

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                SELECT admin, read_only
                FROM users
                WHERE id = :user_id
                  AND active = '1'
                FOR UPDATE");
            $stmt->bindValue('user_id', (int) $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $history_user = $stmt->fetch();
            $stmt->closeCursor();
            if ($history_user && (int) $history_user->admin === 1 && (int) $history_user->read_only === 0) {
                $stmt = $pdo->prepare("
                    UPDATE settings
                    SET history_retention_months = :history_retention_months,
                        update_time = :update_time");
                $stmt->bindValue('history_retention_months', $new_history_retention, PDO::PARAM_INT);
                $stmt->bindValue('update_time', $time->stamp(), PDO::PARAM_STR);
                $stmt->execute();
                $history_saved = true;
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollback();
            throw $e;
        }

    }

    if ($history_saved) {
        $_SESSION['s_message_success'] .= _('The Change History setting was updated.') . '<BR>';
    } else {
        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
    }

    header("Location: index.php");
    exit;

}

/*
 * "Use the Watch List", saved by its own form, once the database update has added the Watch List
 * (WatchList::isAvailable()). Off hides the Watch List and stops its checks, and keeps its entries (see
 * WatchList::isEnabled()). The administrator and read-only flags are read as the database has them now, with the user's
 * row locked in the same transaction as the save, as for the Change History setting. Never on the demo, where the
 * Watch List can't be changed either.
 */
$watch_available = DomainMOD\WatchList::isAvailable() && DEMO_INSTALLATION == false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['watch_form'] ?? '') === '1') {

    $new_use_watch_list = (int) ($_POST['new_use_watch_list'] ?? 0) === 1 ? 1 : 0;
    $watch_saved = false;
    if ($watch_available) {

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                SELECT admin, read_only
                FROM users
                WHERE id = :user_id
                  AND active = '1'
                FOR UPDATE");
            $stmt->bindValue('user_id', (int) $_SESSION['s_user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $watch_user = $stmt->fetch();
            $stmt->closeCursor();
            if ($watch_user && (int) $watch_user->admin === 1 && (int) $watch_user->read_only === 0) {
                $stmt = $pdo->prepare("
                    UPDATE settings
                    SET use_watch_list = :use_watch_list,
                        update_time = :update_time");
                $stmt->bindValue('use_watch_list', $new_use_watch_list, PDO::PARAM_INT);
                $stmt->bindValue('update_time', $time->stamp(), PDO::PARAM_STR);
                $stmt->execute();
                $watch_saved = true;
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollback();
            throw $e;
        }

    }

    if ($watch_saved) {
        $_SESSION['s_message_success'] .= _('The Watch List setting was updated.') . '<BR>';
    } else {
        $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
    }

    header("Location: index.php");
    exit;

}

$new_full_url = isset($_POST['new_full_url']) ? $sanitize->text($_POST['new_full_url']) : '';
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_expiration_days = (int) ($_POST['new_expiration_days'] ?? 0);
$new_email_signature = (int) ($_POST['new_email_signature'] ?? 0);
$new_currency_converter = isset($_POST['new_currency_converter']) ? $sanitize->text($_POST['new_currency_converter']) : '';
$new_use_smtp = (int) ($_POST['new_use_smtp'] ?? 0);
$new_smtp_server = isset($_POST['new_smtp_server']) ? $sanitize->text($_POST['new_smtp_server']) : '';
// TLS, SSL, or none (empty), for a server such as a local relay that has no encryption.
$new_smtp_protocol = in_array($_POST['new_smtp_protocol'] ?? '', array('tls', 'ssl', ''), true) ? ($_POST['new_smtp_protocol'] ?? '') : 'tls';
$new_smtp_port = (int) ($_POST['new_smtp_port'] ?? 0);
$new_smtp_email_address = isset($_POST['new_smtp_email_address']) ? $sanitize->text($_POST['new_smtp_email_address']) : '';
$new_smtp_username = isset($_POST['new_smtp_username']) ? $sanitize->text($_POST['new_smtp_username']) : '';
$new_smtp_password = isset($_POST['new_smtp_password']) ? $sanitize->text($_POST['new_smtp_password']) : '';
// A typed value wins over its Remove box, so the box only counts when nothing is typed (and a form shown again
// after a failed save doesn't tick it under a value that has to be typed again)
$new_smtp_password_remove = (int) ($_POST['new_smtp_password_remove'] ?? 0) === 1 && $new_smtp_password === '';
$new_debug_mode = (int) ($_POST['new_debug_mode'] ?? 0);
$new_local_php_log = (int) ($_POST['new_local_php_log'] ?? 0);
// A username and password are never sent without encryption, so signing in needs TLS or SSL.
$smtp_sign_in_unencrypted = $new_smtp_protocol === '' && ($new_smtp_username !== '' || $new_smtp_password !== '');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != '' && $new_full_url != '' && $new_expiration_days !== 0 &&
    !$smtp_sign_in_unencrypted) {

    // An empty SMTP Password field keeps the saved password, so the column is only set when one is typed or its
    // Remove box is ticked
    $smtp_password_to_store = DomainMOD\Form::savedSecretToStore($new_smtp_password, $new_smtp_password_remove);
    $smtp_password_column = $smtp_password_to_store !== null ? 'smtp_password = :new_smtp_password,' : '';

    $stmt = $pdo->prepare("
        UPDATE settings
        SET full_url = :new_full_url,
            email_address = :new_email_address,
            use_smtp = :new_use_smtp,
            smtp_server = :new_smtp_server,
            smtp_protocol = :new_smtp_protocol,
            smtp_port = :new_smtp_port,
            smtp_email_address = :new_smtp_email_address,
            smtp_username = :new_smtp_username,
            " . $smtp_password_column . "
            expiration_days = :new_expiration_days,
            email_signature = :new_email_signature,
            currency_converter = :new_currency_converter,
            debug_mode = :new_debug_mode,
            local_php_log = :new_local_php_log,
            update_time = :timestamp");
    $stmt->bindValue('new_full_url', $new_full_url, PDO::PARAM_STR);
    $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
    $stmt->bindValue('new_use_smtp', $new_use_smtp, PDO::PARAM_INT);
    $stmt->bindValue('new_smtp_server', $new_smtp_server, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_protocol', $new_smtp_protocol, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_port', $new_smtp_port, PDO::PARAM_INT);
    $stmt->bindValue('new_smtp_email_address', $new_smtp_email_address, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_username', $new_smtp_username, PDO::PARAM_STR);
    if ($smtp_password_to_store !== null) $stmt->bindValue('new_smtp_password', $smtp_password_to_store, PDO::PARAM_STR);
    $stmt->bindValue('new_expiration_days', $new_expiration_days, PDO::PARAM_INT);
    $stmt->bindValue('new_email_signature', $new_email_signature, PDO::PARAM_INT);
    $stmt->bindValue('new_currency_converter', $new_currency_converter, PDO::PARAM_STR);
    $stmt->bindValue('new_debug_mode', $new_debug_mode, PDO::PARAM_INT);
    $stmt->bindValue('new_local_php_log', $new_local_php_log, PDO::PARAM_INT);
    $timestamp = $time->stamp();
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $_SESSION['s_system_full_url'] = $new_full_url;
    $_SESSION['s_system_email_address'] = $new_email_address;
    $_SESSION['s_system_expiration_days'] = $new_expiration_days;
    $_SESSION['s_system_email_signature'] = $new_email_signature;
    $_SESSION['s_system_currency_converter'] = $new_currency_converter;
    $_SESSION['s_system_local_php_log'] = $new_local_php_log;

    $_SESSION['s_message_success'] .= _('The System Settings were updated.') . '<BR>';

    header("Location: index.php");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_full_url == "") $form->addFieldError('new_full_url', sprintf(_('Enter the full URL of your %s installation'), SOFTWARE_TITLE));
        if ($new_email_address == "") $form->addFieldError('new_email_address', _('Enter the system email address'));
        if ($new_expiration_days == "") $form->addFieldError('new_expiration_days', _('Enter the number of days to display in expiration emails'));
        if ($smtp_sign_in_unencrypted) $form->addFieldError('new_smtp_username', _('Choose TLS or SSL to sign in to the SMTP server, or leave the username and password blank.'));
        // A typed password isn't put back in the field, so it has to be typed again
        if ($new_smtp_password !== '' && !$smtp_sign_in_unencrypted) $form->addFieldError('new_smtp_password', _('Enter the new value again'));
        if ($new_smtp_password !== '' && $smtp_sign_in_unencrypted) $form->addFieldError('new_smtp_password', _('Choose TLS or SSL and enter the new value again, or leave it blank.'));

    } else {

        $stmt = $pdo->prepare("
            SELECT full_url, email_address, use_smtp, smtp_server, smtp_protocol, smtp_port, smtp_email_address,
                smtp_username, expiration_days, email_signature, currency_converter, debug_mode,
                local_php_log
            FROM settings");
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $new_full_url = $result->full_url;
            $new_email_address = $result->email_address;
            $new_use_smtp = $result->use_smtp;
            $new_smtp_server = $result->smtp_server;
            $new_smtp_protocol = $result->smtp_protocol;
            $new_smtp_port = $result->smtp_port;
            $new_smtp_email_address = $result->smtp_email_address;
            $new_smtp_username = $result->smtp_username;
            $new_expiration_days = $result->expiration_days;
            $new_email_signature = $result->email_signature;
            $new_currency_converter = $result->currency_converter;
            $new_debug_mode = $result->debug_mode;
            $new_local_php_log = $result->local_php_log;

        }

    }
}

// Whether an SMTP password is saved, for the note under the field (the field itself never shows it)
$has_saved_smtp_password = (string) $pdo->query("
    SELECT smtp_password
    FROM settings")->fetchColumn() !== '';
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
echo $form->showFormTop('');
echo $form->showInputText('new_full_url', sprintf(_('Full %s URL'), SOFTWARE_TITLE) . ' (100)', sprintf(_('Enter the full URL of your %s installation, excluding the trailing slash (Example: http://example.com/domainmod)'), SOFTWARE_TITLE), $unsanitize->text($new_full_url), '100', '', '1', '', '');
echo $form->showInputText('new_email_address', _('System Email Address') . ' (100)', sprintf(_('This should be a valid email address that is monitored by the %s System Administrator. It will be used in various system locations, such as the REPLY-TO address for emails sent by %s.'), SOFTWARE_TITLE, SOFTWARE_TITLE), $unsanitize->text($new_email_address), '100', '', '1', '', '');
echo $form->showInputText('new_expiration_days', _('Expiration Days to Display'), _('This is the number of days in the future to display on the Dashboard and in expiration emails.'), $new_expiration_days, '3', '', '1', '', '');
echo $form->showDropdownTop('new_email_signature', _('Email Signature'), _("Every email sent by the system (new account notices, expiration emails, etc.) includes a signature with a user's full name and email address. Use the menu below to choose whose signature appears."), '1', '');
$result = $pdo->query("
    SELECT id, `first_name`, `last_name`
    FROM users
    ORDER BY first_name, last_name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->first_name . ' ' . $row->last_name, $_SESSION['s_system_email_signature']);

}
echo $form->showDropdownBottom('');
echo $form->showDropdownTop('new_currency_converter', _('Currency Converter'), _("Although it doesn't happen often, sometimes currency converters can experience downtime. If this happens, and your conversions aren't working properly, try a different source."), '', '');
echo $form->showDropdownOption('fcra', _('Free Currency Rates API'), $new_currency_converter);
echo $form->showDropdownBottom('');

echo $form->showSwitch(_('Debugging Mode') . '', sprintf(_("Unless you're having problems with %s and support has asked you to turn this on, you should leave it turned off."), SOFTWARE_TITLE), 'new_debug_mode', $new_debug_mode, '<div class="mb-3">', '</div>');

echo $form->showSwitch(_('Local PHP Log') . '',
    _('This allows you to log PHP errors in a local file called domainmod.log, instead of recording them in the main PHP log.') . '<BR>' .
    '<strong>' . _('Warning') . ':</strong> ' . sprintf(_("Only enable this feature temporarily for troubleshooting, and if you're asked to by %s support."), SOFTWARE_TITLE) .
    ' ' . sprintf(_('Leaving it enabled all the time will cause logged errors to be visible to everyone who knows the URL to your %s installation, which could allow them to compromise your system.'), SOFTWARE_TITLE), 'new_local_php_log', $new_local_php_log, '<div class="mb-3">', '</div>');

// The SMTP settings in their own card, always open, with the switch that turns them on first
ob_start();
echo $form->showSwitch(_('Use SMTP Server') . '?', sprintf(_("If the instance of PHP running on your %s server isn't configured to send mail, you can use an external SMTP server to send system emails."), SOFTWARE_TITLE), 'new_use_smtp', $new_use_smtp, '<div class="domainmod-span-all mb-3">', '</div>');
echo $form->showInputText('new_smtp_server', _('SMTP Server') . ' (255)', _('If you plan on using an external SMTP server, enter the server name here.'), $unsanitize->text($new_smtp_server), '100', '', '', '', '');
echo $form->showRadioTop(_('SMTP Server Protocol'), sprintf(_('Choose %s only for a server that has no encryption and needs no sign-in, such as a local relay.'), _('None')), '');
echo $form->showRadioOption('new_smtp_protocol', 'tls', _('TLS'), $new_smtp_protocol, '', '');
echo $form->showRadioOption('new_smtp_protocol', 'ssl', _('SSL'), $new_smtp_protocol, '', '');
echo $form->showRadioOption('new_smtp_protocol', '', _('None'), $new_smtp_protocol, '', '');
echo $form->showRadioBottom('');
echo $form->showInputText('new_smtp_port', _('SMTP Server Port') . ' (5)', '', $new_smtp_port, '5', '', '', '', '');
echo $form->showInputText('new_smtp_email_address', _('SMTP Email Address') . ' (100)', '', $unsanitize->text($new_smtp_email_address), '100', '', '', '', '');
echo $form->showInputText('new_smtp_username', _('SMTP Username') . ' (100)', _('This is usually the same as the SMTP Email Address.') . ' ' . _('Leave it blank if your SMTP server doesn\'t need you to sign in.'), $unsanitize->text($new_smtp_username), '100', '', '', '', '');
echo $form->showSavedPassword('new_smtp_password', _('SMTP Password') . ' (255)', $has_saved_smtp_password, '255', $new_smtp_password_remove);
echo $layout->formSection(_('SMTP Server Settings'), 'smtp', ob_get_clean());

echo $form->showSubmitButton(_('Update System Settings'), '', '');
echo $form->showFormBottom('');

// Two-factor sign-in: the "require" setting, and what a database backup exposes (the secret key)
if ($two_factor_available && DEMO_INSTALLATION == false) {

    $two_factor_admin = $two_factor->getState($_SESSION['s_user_id']);
    $secret_counts = $two_factor->secretCounts();
    ob_start();
    echo '<div class="domainmod-span-all">' . $layout->factList(array(
        array('label' => _('Secret Key'), 'value' => $two_factor->hasSecretKey() ? _('Set') : _('Not set')),
        array('label' => _('Secrets Stored Without Encryption'), 'value' => (string) $secret_counts['raw'], 'ltr' => true))) . '</div>';
    if (!$two_factor->hasSecretKey() && $secret_counts['encrypted'] > 0) {
        echo '<p class="domainmod-span-all text-danger">' . _("Some users' MFA secrets are encrypted, but no secret key is set in config.inc.php, so they need a recovery code to sign in. Set the same key again.") . '</p>';
    } elseif ($two_factor_admin && $two_factor->isOn($two_factor_admin) && $two_factor->decodeSecret($two_factor_admin['secret'], $two_factor_admin['id']) === false) {
        echo '<p class="domainmod-span-all text-danger">' . _('Your own MFA secret could not be decrypted, so the secret key in config.inc.php may have changed.') . '</p>';
    }
    echo '<p class="domainmod-span-all">' . _('Without a secret key, MFA secrets are stored without encryption, so a database backup includes them. Secrets saved before a key was set are encrypted the next time each user signs in with a code from their authenticator app.') . '</p>';
    echo $form->showInputHidden('two_factor_form', '1');
    echo $form->showSwitch(_('Require MFA for All Users'), _("Users who don't have it on can only change their password and set it up until they do. You need it on for your own account first."), 'new_two_factor_required', (int) ($two_factor_admin['required'] ?? 0), '<div class="domainmod-span-all mb-3">', '</div>');
    // Its own form, set apart from System Settings above, with its button under its card like that form's
    echo '<div class="mt-5">' . $form->showFormTop('') . $layout->formSection(_('Set Up MFA'), 'two-factor', ob_get_clean())
        . $form->showSubmitButton(_('Update MFA Setting'), '', '') . $form->showFormBottom('') . '</div>';

}

// How long the Change History is kept (see above)
if ($history_available) {

    $history_retention = DomainMOD\ChangeHistory::retentionMonths($pdo);
    ob_start();
    echo $form->showInputHidden('history_form', '1');
    echo $form->showDropdownTop('new_history_retention_months', _('Keep change history for'), _('Older changes are removed by the daily System Cleanup task. A shorter time keeps backups smaller.'), '', '<div class="domainmod-span-all mb-3">');
    foreach (DomainMOD\ChangeHistory::RETENTION_CHOICES as $retention_months) {
        $retention_label = $retention_months === 0 ? _('Forever')
            : sprintf(ngettext('%s Year', '%s Years', $retention_months / 12), $retention_months / 12);
        echo $form->showDropdownOption($retention_months, $retention_label, $history_retention);
    }
    echo $form->showDropdownBottom('</div>');
    echo '<div class="mt-5">' . $form->showFormTop('') . $layout->formSection(_('Change History'), 'change-history', ob_get_clean())
        . $form->showSubmitButton(_('Update Change History Setting'), '', '') . $form->showFormBottom('') . '</div>';

}

// Whether the Watch List is in use (see above)
if ($watch_available) {

    ob_start();
    echo $form->showInputHidden('watch_form', '1');
    echo $form->showSwitch(_('Use the Watch List'), _('Turn this off to hide the Watch List from every user and stop its checks. Your watched domains are kept, so turning it back on brings everything back.'), 'new_use_watch_list', DomainMOD\WatchList::isEnabled() ? 1 : 0, '<div class="domainmod-span-all mb-3">', '</div>');
    echo '<div class="mt-5">' . $form->showFormTop('') . $layout->formSection(_('Watch List'), 'watch-list', ob_get_clean())
        . $form->showSubmitButton(_('Update Watch List Setting'), '', '') . $form->showFormBottom('') . '</div>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
