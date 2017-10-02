<?php
/**
 * /assets/add/ip-address.php
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
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$form = new DomainMOD\Form();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-add-ip-address.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_rdns = $_POST['new_rdns'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != '' && $new_ip != '') {

        $pdo = $deeb->cnxx;

        $stmt = $pdo->prepare("
            INSERT INTO ip_addresses
            (`name`, ip, rdns, notes, created_by, insert_time)
            VALUES
            (:new_name, :new_ip, :new_rdns, :new_notes, :created_by, :timestamp)");
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_ip', $new_ip, PDO::PARAM_STR);
        $stmt->bindValue('new_rdns', $new_rdns, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $stmt->bindValue('created_by', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['s_message_success'] .= "IP Address " . $new_name . " (" . $new_ip . ") Added<BR>";

        header("Location: ../ip-addresses.php");
        exit;

    } else {

        if ($new_name == '') {
            $_SESSION['s_message_danger'] .= "Enter a name for the IP address<BR>";
        }
        if ($new_ip == '') {
            $_SESSION['s_message_danger'] .= "Enter the IP address<BR>";
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
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'IP Address Name (100)', '', $new_name, '100', '', '1', '', '');
echo $form->showInputText('new_ip', 'IP Address (100)', '', $new_ip, '100', '', '1', '', '');
echo $form->showInputText('new_rdns', 'rDNS (100)', '', $new_rdns, '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showSubmitButton('Add IP Address', '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
