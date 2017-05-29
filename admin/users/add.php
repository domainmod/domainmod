<?php
/**
 * /admin/users/add.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$conversion = new DomainMOD\Conversion();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/admin-users-add.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];
$new_admin = $_POST['new_admin'];
$new_read_only = $_POST['new_read_only'];
$new_active = $_POST['new_active'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != ''
    && $new_email_address != '') {

    $existing_username = '';
    $existing_email_address = '';

    $tmpq = $system->db()->prepare("
        SELECT id
        FROM users
        WHERE username = :username");
    $tmpq->execute(['username' => $new_username]);
    $result = $tmpq->fetchAll();

    if ($result) {

        $existing_username = '1';

    }

    $tmpq = $system->db()->prepare("
        SELECT id
        FROM users
        WHERE email_address = :email_address");
    $tmpq->execute(['email_address' => $new_email_address]);
    $result = $tmpq->fetchAll();

    if ($result) {

        $existing_email_address = '1';

    }

    if ($existing_username == '1' || $existing_email_address == '1') {

        if ($existing_username == '1') $_SESSION['s_message_danger'] .= 'A user with that username already exists<BR>';
        if ($existing_email_address == '1') $_SESSION['s_message_danger'] .= 'A user with that email address already exists<BR>';

    } else {

        $new_password = substr(md5(time()), 0, 8);

        $tmpq = $system->db()->prepare("
            INSERT INTO users
            (first_name, last_name, username, email_address, `password`, admin, `read_only`, active, created_by,
             insert_time)
            VALUES
            (:first_name, :last_name, :username, :email_address, password(:password), :is_admin, :read_only, :active,
             :created_by, :insert_time)");
        $tmpq->execute(['first_name' => $new_first_name,
                        'last_name' => $new_last_name,
                        'username' => $new_username,
                        'email_address' => $new_email_address,
                        'password' => $new_password,
                        'is_admin' => $new_admin,
                        'read_only' => $new_read_only,
                        'active' => $new_active,
                        'created_by' => $_SESSION['s_user_id'],
                        'insert_time' => $time->stamp()]);
        $temp_user_id = $this->system->db()->lastInsertId();

        $tmpq = $system->db()->prepare("
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
        $tmpq->execute(['user_id' => $temp_user_id,
                        'default_category_domains' => $_SESSION['s_system_default_category_domains'],
                        'default_category_ssl' => $_SESSION['s_system_default_category_ssl'],
                        'default_dns' => $_SESSION['s_system_default_dns'],
                        'default_host' => $_SESSION['s_system_default_host'],
                        'default_ip_address_domains' => $_SESSION['s_system_default_ip_address_domains'],
                        'default_ip_address_ssl' => $_SESSION['s_system_default_ip_address_ssl'],
                        'default_owner_domains' => $_SESSION['s_system_default_owner_domains'],
                        'default_owner_ssl' => $_SESSION['s_system_default_owner_ssl'],
                        'default_registrar' => $_SESSION['s_system_default_registrar'],
                        'default_registrar_account' => $_SESSION['s_system_default_registrar_account'],
                        'default_ssl_provider' => $_SESSION['s_system_default_ssl_provider'],
                        'default_ssl_provider_account' => $_SESSION['s_system_default_ssl_provider_account'],
                        'default_ssl_type' => $_SESSION['s_system_default_ssl_type'],
                        'insert_time' => $time->stamp()]);

        //@formatter:off
        $_SESSION['s_message_success']
            .= 'User ' . $new_first_name . ' ' . $new_last_name .
            ' (' . $new_username . " / " . $new_password . ') Added
            <BR><BR>You can either manually email the above credentials to the user, or you can
            <a href="reset-password.php?new_username=' . $new_username . '">click here</a> to have ' .
            SOFTWARE_TITLE . ' email them for you<BR><BR>';
        //@formatter:on

        $conversion->updateRates('USD', $temp_user_id);

        header("Location: index.php");
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_first_name == '') $_SESSION['s_message_danger'] .= 'Enter the new user\'s first name<BR>';
        if ($new_last_name == '') $_SESSION['s_message_danger'] .= 'Enter the new user\'s last name<BR>';
        if ($new_username == '') $_SESSION['s_message_danger'] .= 'Enter the new user\'s username<BR>';
        if ($new_email_address == '') $_SESSION['s_message_danger'] .= 'Enter the new user\'s email address<BR>';

    }

}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_first_name', 'First Name (50)', '', $new_first_name, '50', '', '1', '', '');
echo $form->showInputText('new_last_name', 'Last Name (50)', '', $new_last_name, '50', '', '1', '', '');
echo $form->showInputText('new_username', 'Username (30)', '', $new_username, '30', '', '1', '', '');
echo $form->showInputText('new_email_address', 'Email Address (100)', '', $new_email_address, '100', '', '1', '', '');
echo $form->showRadioTop('Admin Privileges?', '', '');
if ($new_admin == '') $new_admin = '0';
echo $form->showRadioOption('new_admin', '1', 'Yes', $new_admin, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_admin', '0', 'No', $new_admin, '', '');
echo $form->showRadioBottom('');
echo $form->showRadioTop('Read-Only User?', '', '');
if ($new_read_only == '') $new_read_only = '1';
echo $form->showRadioOption('new_read_only', '1', 'Yes', $new_read_only, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_read_only', '0', 'No', $new_read_only, '', '');
echo $form->showRadioBottom('');
echo $form->showRadioTop('Active Account?', '', '');
if ($new_active == '') $new_active = '1';
echo $form->showRadioOption('new_active', '1', 'Yes', $new_active, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_active', '0', 'No', $new_active, '', '');
echo $form->showRadioBottom('');
echo $form->showSubmitButton('Add User', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
<?php //@formatter:on ?>
</body>
</html>
