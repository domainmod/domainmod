<?php
/**
 * /reset.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$form = new DomainMOD\Form();
$layout = new DomainMOD\Layout();
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->loginCheck();
$pdo = $deeb->cnxx;

$page_title = "Reset Password";
$software_section = "resetpassword";

$user_identifier = $_REQUEST['user_identifier'];

if ($user_identifier != '') {

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, username, email_address
        FROM users
        WHERE (username = :username OR email_address = :email_address)
          AND active = '1'");
    $stmt->bindValue('username', $user_identifier, PDO::PARAM_STR);
    $stmt->bindValue('email_address', $user_identifier, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch();

    if (!$result) {

        $_SESSION['s_message_success'] .= "If there is a matching username or email address in the system your new password will been emailed to you.<BR>";

        header('Location: ' . $web_root . "/");
        exit;

    } else {

        $new_password = substr(md5(time()), 0, 8);

        $stmt = $pdo->prepare("
            UPDATE users
            SET `password` = password(:new_password),
                new_password = '1',
                update_time = :timestamp
            WHERE username = :username
              AND email_address = :email_address");
        $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
        $bind_timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $bind_timestamp, PDO::PARAM_STR);
        $stmt->bindValue('username', $result->username, PDO::PARAM_STR);
        $stmt->bindValue('email_address', $result->email_address, PDO::PARAM_STR);
        $stmt->execute();

        $first_name = $result->first_name;
        $last_name = $result->last_name;
        $username = $result->username;
        $email_address = $result->email_address;
        require_once DIR_INC . '/email/send-new-password.inc.php';

        $_SESSION['s_message_success'] .= "If there is a matching username or email address in the system your new password will been emailed to you.<BR>";

        header('Location: ' . $web_root . "/");
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($user_identifier == "") {
            $_SESSION['s_message_danger'] .= "Enter your username or email address<BR>";
        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red" onLoad="document.forms[0].elements[0].focus()">
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>
<?php
    echo $form->showFormTop('');
    echo $form->showInputText('user_identifier', 'Username or Email Address', '', $user_identifier, '100', '', '', '', '');
    echo $form->showSubmitButton('Reset Password', '', '');
    echo $form->showFormBottom('');
?>
<BR><a href="<?php echo $web_root; ?>/">Cancel Password Reset</a>
<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
