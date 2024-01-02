<?php
/**
 * /admin/users/add.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
<?php //@formatter:off
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/users/add.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$user = new DomainMOD\User();
$conversion = new DomainMOD\Conversion();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-users-add.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$new_first_name = isset($_POST['new_first_name']) ? $sanitize->text($_POST['new_first_name']) : '';
$new_last_name = isset($_POST['new_last_name']) ? $sanitize->text($_POST['new_last_name']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != ''
    && $new_email_address != ''
) {

    $existing_username = '';
    $existing_email_address = '';

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :username");
    $stmt->bindValue('username', $new_username, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if ($result) {

        $existing_username = 1;

    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email_address = :email_address");
    $stmt->bindValue('email_address', $new_email_address, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    if ($result) {

        $existing_email_address = 1;

    }

    if ($existing_username === 1 || $existing_email_address === 1) {

        if ($existing_username === 1) $_SESSION['s_message_danger'] .= _('A user with that username already exists') . '<BR>';
        if ($existing_email_address === 1) $_SESSION['s_message_danger'] .= _('A user with that email address already exists') . '<BR>';

    } else {

        $new_password = $user->generatePassword(30);

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users
                (first_name, last_name, username, email_address, `password`, created_by, insert_time)
                VALUES
                (:first_name, :last_name, :username, :email_address, CONCAT('*', UPPER(SHA1(UNHEX(SHA1(:new_password))))), :created_by, :insert_time)");
            $stmt->bindValue('first_name', $new_first_name, PDO::PARAM_STR);
            $stmt->bindValue('last_name', $new_last_name, PDO::PARAM_STR);
            $stmt->bindValue('username', $new_username, PDO::PARAM_STR);
            $stmt->bindValue('email_address', $new_email_address, PDO::PARAM_STR);
            $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
            $stmt->bindValue('created_by', $_SESSION['s_user_id'], PDO::PARAM_INT);
            $bind_timestamp = $time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, PDO::PARAM_STR);
            $stmt->execute();

            $temp_user_id = $pdo->lastInsertId('id');

            $stmt = $pdo->prepare("
                INSERT INTO user_settings
                (user_id,
                 default_currency,
                 default_category_domains,
                 default_category_ssl,
                 default_dns,
                 default_host,
                 default_ip_address_domains,
                 default_ip_address_ssl,
                 default_owner_domains,
                 default_owner_ssl,
                 default_registrar,
                 default_registrar_account,
                 default_ssl_provider,
                 default_ssl_provider_account,
                 default_ssl_type,
                 insert_time)
                 VALUES
                 (:user_id, 'USD', :default_category_domains, :default_category_ssl, :default_dns, :default_host,
                  :default_ip_address_domains, :default_ip_address_ssl, :default_owner_domains, :default_owner_ssl,
                  :default_registrar, :default_registrar_account, :default_ssl_provider, :default_ssl_provider_account,
                  :default_ssl_type, :insert_time)");
            $stmt->bindValue('user_id', $temp_user_id, PDO::PARAM_INT);
            $stmt->bindValue('default_category_domains', $_SESSION['s_system_default_category_domains'], PDO::PARAM_INT);
            $stmt->bindValue('default_category_ssl', $_SESSION['s_system_default_category_ssl'], PDO::PARAM_INT);
            $stmt->bindValue('default_dns', $_SESSION['s_system_default_dns'], PDO::PARAM_INT);
            $stmt->bindValue('default_host', $_SESSION['s_system_default_host'], PDO::PARAM_INT);
            $stmt->bindValue('default_ip_address_domains', $_SESSION['s_system_default_ip_address_domains'], PDO::PARAM_INT);
            $stmt->bindValue('default_ip_address_ssl', $_SESSION['s_system_default_ip_address_ssl'], PDO::PARAM_INT);
            $stmt->bindValue('default_owner_domains', $_SESSION['s_system_default_owner_domains'], PDO::PARAM_INT);
            $stmt->bindValue('default_owner_ssl', $_SESSION['s_system_default_owner_ssl'], PDO::PARAM_INT);
            $stmt->bindValue('default_registrar', $_SESSION['s_system_default_registrar'], PDO::PARAM_INT);
            $stmt->bindValue('default_registrar_account', $_SESSION['s_system_default_registrar_account'], PDO::PARAM_INT);
            $stmt->bindValue('default_ssl_provider', $_SESSION['s_system_default_ssl_provider'], PDO::PARAM_INT);
            $stmt->bindValue('default_ssl_provider_account', $_SESSION['s_system_default_ssl_provider_account'], PDO::PARAM_INT);
            $stmt->bindValue('default_ssl_type', $_SESSION['s_system_default_ssl_type'], PDO::PARAM_INT);
            $bind_timestamp = $time->stamp();
            $stmt->bindValue('insert_time', $bind_timestamp, PDO::PARAM_STR);
            $stmt->execute();

            //@formatter:on

            $conversion->updateRates('USD', $temp_user_id);

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('User %s %s (%s / %s) added.'), $new_first_name, $new_last_name, $new_username, $new_password) . '<BR><BR>';

            $_SESSION['s_message_success'] .= sprintf(_('You can either manually email the above credentials to the user, or you can %sclick here%s to have %s email them for you.'), '<a href="reset-password.php?new_username=' . $new_username . '">', '</a>', SOFTWARE_TITLE) . '<BR><BR>';

            $_SESSION['s_message_success'] .= _("Use the below form to customize the new user's account.") . '<BR>';

            header("Location: edit.php?uid=" . $temp_user_id);
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to add user';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_first_name == '') $_SESSION['s_message_danger'] .= _("Enter the new user's first name") . '<BR>';
        if ($new_last_name == '') $_SESSION['s_message_danger'] .= _("Enter the new user's last name") . '<BR>';
        if ($new_username == '') $_SESSION['s_message_danger'] .= _("Enter the new user's username") . '<BR>';
        if ($new_email_address == '') $_SESSION['s_message_danger'] .= _("Enter the new user's email address") . '<BR>';

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
echo $form->showInputText('new_first_name', _('First Name') . ' (50)', '', $unsanitize->text($new_first_name), '50', '', '1', '', '');
echo $form->showInputText('new_last_name', _('Last Name') . ' (50)', '', $sanitize->text($new_last_name), '50', '', '1', '', '');
echo $form->showInputText('new_username', _('Username') . ' (30)', '', $sanitize->text($new_username), '30', '', '1', '', '');
echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $sanitize->text($new_email_address), '100', '', '1', '', '');
echo $form->showSubmitButton(_('Add User'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php //@formatter:on ?>
</body>
</html>
