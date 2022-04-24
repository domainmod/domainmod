<?php
/**
 * /admin/settings/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$new_full_url = $sanitize->text($_POST['new_full_url']);
$new_email_address = $sanitize->text($_POST['new_email_address']);
$new_expiration_days = (int) $_POST['new_expiration_days'];
$new_email_signature = (int) $_POST['new_email_signature'];
$new_currency_converter = $sanitize->text($_POST['new_currency_converter']);
$new_large_mode = (int) $_POST['new_large_mode'];
$new_use_smtp = (int) $_POST['new_use_smtp'];
$new_smtp_server = $sanitize->text($_POST['new_smtp_server']);
$new_smtp_protocol = $_POST['new_smtp_protocol'];
$new_smtp_port = (int) $_POST['new_smtp_port'];
$new_smtp_email_address = $sanitize->text($_POST['new_smtp_email_address']);
$new_smtp_username = $sanitize->text($_POST['new_smtp_username']);
$new_smtp_password = $sanitize->text($_POST['new_smtp_password']);
$new_debug_mode = (int) $_POST['new_debug_mode'];
$new_local_php_log = (int) $_POST['new_local_php_log'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != '' && $new_full_url != '' && $new_expiration_days !== 0) {

    $stmt = $pdo->prepare("
        UPDATE settings
        SET full_url = :new_full_url,
            email_address = :new_email_address,
            large_mode = :new_large_mode,
            use_smtp = :new_use_smtp,
            smtp_server = :new_smtp_server,
            smtp_protocol = :new_smtp_protocol,
            smtp_port = :new_smtp_port,
            smtp_email_address = :new_smtp_email_address,
            smtp_username = :new_smtp_username,
            smtp_password = :new_smtp_password,
            expiration_days = :new_expiration_days,
            email_signature = :new_email_signature,
            currency_converter = :new_currency_converter,
            debug_mode = :new_debug_mode,
            local_php_log = :new_local_php_log,
            update_time = :timestamp");
    $stmt->bindValue('new_full_url', $new_full_url, PDO::PARAM_STR);
    $stmt->bindValue('new_email_address', $new_email_address, PDO::PARAM_STR);
    $stmt->bindValue('new_large_mode', $new_large_mode, PDO::PARAM_INT);
    $stmt->bindValue('new_use_smtp', $new_use_smtp, PDO::PARAM_INT);
    $stmt->bindValue('new_smtp_server', $new_smtp_server, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_protocol', $new_smtp_protocol, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_port', $new_smtp_port, PDO::PARAM_INT);
    $stmt->bindValue('new_smtp_email_address', $new_smtp_email_address, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_username', $new_smtp_username, PDO::PARAM_STR);
    $stmt->bindValue('new_smtp_password', $new_smtp_password, PDO::PARAM_STR);
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
    $_SESSION['s_system_large_mode'] = $new_large_mode;
    $_SESSION['s_system_expiration_days'] = $new_expiration_days;
    $_SESSION['s_system_email_signature'] = $new_email_signature;
    $_SESSION['s_system_currency_converter'] = $new_currency_converter;
    $_SESSION['s_system_local_php_log'] = $new_local_php_log;

    $_SESSION['s_message_success'] .= _('The System Settings were updated') . '<BR>';

    header("Location: index.php");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_full_url == "") $_SESSION['s_message_danger'] .= sprintf(_('Enter the full URL of your %s installation'), SOFTWARE_TITLE) . '<BR>';
        if ($new_email_address == "") $_SESSION['s_message_danger'] .= _('Enter the system email address') . '<BR>';
        if ($new_expiration_days == "") $_SESSION['s_message_danger'] .= _('Enter the number of days to display in expiration emails') . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT full_url, email_address, large_mode, use_smtp, smtp_server, smtp_protocol, smtp_port,
                smtp_email_address, smtp_username, smtp_password, expiration_days, email_signature, currency_converter,
                debug_mode, local_php_log
            FROM settings");
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $new_full_url = $result->full_url;
            $new_email_address = $result->email_address;
            $new_large_mode = $result->large_mode;
            $new_use_smtp = $result->use_smtp;
            $new_smtp_server = $result->smtp_server;
            $new_smtp_protocol = $result->smtp_protocol;
            $new_smtp_port = $result->smtp_port;
            $new_smtp_email_address = $result->smtp_email_address;
            $new_smtp_username = $result->smtp_username;
            $new_smtp_password = $result->smtp_password;
            $new_expiration_days = $result->expiration_days;
            $new_email_signature = $result->email_signature;
            $new_currency_converter = $result->currency_converter;
            $new_debug_mode = $result->debug_mode;
            $new_local_php_log = $result->local_php_log;

        }

    }
}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_full_url', sprintf(_('Full %s URL'), SOFTWARE_TITLE) . ' (100)', sprintf(_('Enter the full URL of your %s installation, excluding the trailing slash (Example: http://example.com/domainmod)'), SOFTWARE_TITLE), $unsanitize->text($new_full_url), '100', '', '1', '', '');
echo $form->showInputText('new_email_address', _('System Email Address') . ' (100)', sprintf(_('This should be a valid email address that is monitored by the %s System Administrator. It will be used in various system locations, such as the REPLY-TO address for emails sent by %s.'), SOFTWARE_TITLE, SOFTWARE_TITLE), $unsanitize->text($new_email_address), '100', '', '1', '', '');
echo $form->showInputText('new_expiration_days', _('Expiration Days to Display'), _('This is the number of days in the future to display on the Dashboard and in expiration emails.'), $new_expiration_days, '3', '', '1', '', '');
echo $form->showDropdownTop('new_email_signature', _('Email Signature'), _("Every email sent by the system (new account notices, expiration emails, etc.), includes an email signature, which includes a user's full name and email address. Use the below menu to choose the user that you would like to appear in your system's email signature."), '1', '');
$result = $pdo->query("
    SELECT id, `first_name`, `last_name`
    FROM users
    ORDER BY first_name, last_name")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->first_name . ' ' . $row->last_name, $_SESSION['s_system_email_signature']);

}
echo $form->showDropdownBottom('');
echo $form->showDropdownTop('new_currency_converter', _('Currency Converter'), _("Although it doesn't happen often, sometimes currency converters can experience downtime. If this happens, and your conversions aren't working properly, try a different source."), '', '');
echo $form->showDropdownOption('erh', _('Exchangerate.host'), $new_currency_converter);
echo $form->showDropdownBottom('');

echo $form->showSwitch(_('Large Mode') . '', _('If you have a very large database and your main Domains page is loading slowly, enabling Large Mode should fix the issue, at the cost of losing some of the advanced filtering and mobile functionality.'), 'new_large_mode', $new_large_mode, '', '<BR><BR>');

echo $form->showSwitch(_('Debugging Mode') . '', sprintf(_("Unless you're having problems with %s and support has asked you to turn this on, you should leave it turned off."), SOFTWARE_TITLE), 'new_debug_mode', $new_debug_mode, '', '<BR><BR>');

echo $form->showSwitch(_('Local PHP Log') . '',
    _('This allows you to log PHP errors in a local file called domainmod.log, instead of recording them in the main PHP log.') . '<BR>' .
    $layout->highlightText('red', strtoupper(_('Warning'))) . ': ' . sprintf(_("Only enable this feature temporarily for troubleshooting, and if you're asked to by %s support."), SOFTWARE_TITLE) .
    '&nbsp;' . sprintf(_('Leaving it enabled all the time will cause logged errors to be visible to everyone who knows the URL to your %s installation, which could allow them to compromise your system.'), SOFTWARE_TITLE), 'new_local_php_log', $new_local_php_log, '', '<BR><BR>');

echo $layout->expandableBoxTop(_('SMTP Server Settings'), '', '');

echo $form->showSwitch(_('Use SMTP Server') . '?', sprintf(_("If the instance of PHP running on your %s server isn't configured to send mail, you can use an external SMTP server to send system emails."), SOFTWARE_TITLE), 'new_use_smtp', $new_use_smtp, '', '<BR><BR>');

echo $form->showInputText('new_smtp_server', _('SMTP Server') . ' (255)', _('If you plan on using an external SMTP server, enter the server name here.'), $unsanitize->text($new_smtp_server), '100', '', '', '', '');
echo $form->showRadioTop(_('SMTP Server Protocol'), '', '');
echo $form->showRadioOption('new_smtp_protocol', 'tls', _('TLS'), $new_smtp_protocol, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_smtp_protocol', 'ssl', _('SSL'), $new_smtp_protocol, '', '');
echo $form->showRadioBottom('');
echo $form->showInputText('new_smtp_port', _('SMTP Server Port') . ' (5)', '', $new_smtp_port, '5', '', '', '', '');
echo $form->showInputText('new_smtp_email_address', _('SMTP Email Address') . ' (100)', '', $unsanitize->text($new_smtp_email_address), '100', '', '', '', '');
echo $form->showInputText('new_smtp_username', _('SMTP Username') . ' (100)', _('This is usually the same as the SMTP Email Address.'), $unsanitize->text($new_smtp_username), '100', '', '', '', '');
echo $form->showInputText('new_smtp_password', _('SMTP Password') . ' (255)', '', $unsanitize->text($new_smtp_password), '255', '', '', '', '');
echo $layout->expandableBoxBottom();

echo $form->showSubmitButton(_('Update System Settings'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
