<?php
/**
 * /admin/users/reset-password.php
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
$user = new DomainMOD\User();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$page_title = 'Reset Password';
$software_section = 'system';

$new_username = $_GET['new_username'];
$display = $_GET['display'];

if ($new_username != '') {

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, username, email_address
        FROM users
        WHERE username = :new_username
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    // Apparently doing a second query to get the row count is the best approach with PDO, which is kind of ridiculous
    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM users
        WHERE username = :new_username
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->execute();
    $user_count = $stmt->fetchColumn();

    if (!$result || $user_count > 1) {

        $_SESSION['s_message_danger'] .= _('The password could not be reset due to an invalid username.') . '<BR>';

        header("Location: index.php");
        exit;

    } else {

        $new_password = $user->generatePassword(30);
        $new_hash = $user->generateHash($new_password);

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = :new_hash,
                new_password = '1',
                update_time = :timestamp
            WHERE username = :username
              AND email_address = :email_address");
        $stmt->bindValue('new_hash', $new_hash, PDO::PARAM_STR);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('username', $result->username, PDO::PARAM_STR);
        $stmt->bindValue('email_address', $result->email_address, PDO::PARAM_STR);
        $stmt->execute();

        if ($display == '1') {

            $_SESSION['s_message_success'] .= sprintf(_('The new password for %s is %s'), $result->username, $new_password) . '<BR>';

        } else {

            $first_name = $result->first_name;
            $last_name = $result->last_name;
            $username = $result->username;
            $email_address = $result->email_address;
            require_once DIR_INC . '/email/send-new-password.inc.php';
            $_SESSION['s_message_success'] .= _('The password has been reset and emailed to the account holder') . '<BR>';

        }

        header('Location: edit.php?uid=' . $result->id);
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        if ($new_username == '') $_SESSION['s_message_danger'] .= _('Enter the username') . '<BR>';

        header("Location: index.php");
        exit;

    }

}
