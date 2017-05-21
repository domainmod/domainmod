<?php
/**
 * /assets/add/dns.php
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

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/assets-add-dns.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$new_name = $_POST['new_name'];
$new_notes = $_POST['new_notes'];
$new_dns1 = $_POST['new_dns1'];
$new_dns2 = $_POST['new_dns2'];
$new_dns3 = $_POST['new_dns3'];
$new_dns4 = $_POST['new_dns4'];
$new_dns5 = $_POST['new_dns5'];
$new_dns6 = $_POST['new_dns6'];
$new_dns7 = $_POST['new_dns7'];
$new_dns8 = $_POST['new_dns8'];
$new_dns9 = $_POST['new_dns9'];
$new_dns10 = $_POST['new_dns10'];
$new_ip1 = $_POST['new_ip1'];
$new_ip2 = $_POST['new_ip2'];
$new_ip3 = $_POST['new_ip3'];
$new_ip4 = $_POST['new_ip4'];
$new_ip5 = $_POST['new_ip5'];
$new_ip6 = $_POST['new_ip6'];
$new_ip7 = $_POST['new_ip7'];
$new_ip8 = $_POST['new_ip8'];
$new_ip9 = $_POST['new_ip9'];
$new_ip10 = $_POST['new_ip10'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != '' && $new_dns1 != '' && $new_dns2 != '') {

        $new_number_of_servers = 10;

        if ($new_dns10 == '') { $new_number_of_servers = '9'; }
        if ($new_dns9 == '') { $new_number_of_servers = '8'; }
        if ($new_dns8 == '') { $new_number_of_servers = '7'; }
        if ($new_dns7 == '') { $new_number_of_servers = '6'; }
        if ($new_dns6 == '') { $new_number_of_servers = '5'; }
        if ($new_dns5 == '') { $new_number_of_servers = '4'; }
        if ($new_dns4 == '') { $new_number_of_servers = '3'; }
        if ($new_dns3 == '') { $new_number_of_servers = '2'; }
        if ($new_dns2 == '') { $new_number_of_servers = '1'; }
        if ($new_dns1 == '') { $new_number_of_servers = '0'; }

        $query = "INSERT INTO dns
                  (`name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6,
                   ip7, ip8, ip9, ip10, notes, number_of_servers, created_by, insert_time)
                   VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('sssssssssssssssssssssssis', $new_name, $new_dns1, $new_dns2, $new_dns3, $new_dns4, $new_dns5,
                $new_dns6, $new_dns7, $new_dns8, $new_dns9, $new_dns10, $new_ip1, $new_ip2, $new_ip3, $new_ip4,
                $new_ip5, $new_ip6, $new_ip7, $new_ip8, $new_ip9, $new_ip10, $new_notes, $new_number_of_servers,
                $_SESSION['s_user_id'], $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $_SESSION['s_message_success'] .= 'DNS Profile ' . $new_name . ' Added<BR>';

        header("Location: ../dns.php");
        exit;

    } else {

        if ($new_name == '') {
            $_SESSION['s_message_danger'] .= 'Enter a name for the DNS profile<BR>';
        }
        if ($new_dns1 == '' || $new_dns2 == '') {
            $_SESSION['s_message_danger'] .= 'Enter at least two DNS servers<BR>';
        }

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
echo $form->showInputText('new_name', 'Profile Name', '', $new_name, '255', '', '1', '', ''); ?>
<table width="100%">
    <tbody>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns1', 'DNS Server 1', '', $new_dns1, '255', '', '1', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip1', 'IP Address 1', '', $new_ip1, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns2', 'DNS Server 2', '', $new_dns2, '255', '', '1', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip2', 'IP Address 2', '', $new_ip2, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns3', 'DNS Server 3', '', $new_dns3, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip3', 'IP Address 3', '', $new_ip3, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns4', 'DNS Server 4', '', $new_dns4, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip4', 'IP Address 4', '', $new_ip4, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns5', 'DNS Server 5', '', $new_dns5, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip5', 'IP Address 5', '', $new_ip5, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns6', 'DNS Server 6', '', $new_dns6, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip6', 'IP Address 6', '', $new_ip6, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns7', 'DNS Server 7', '', $new_dns7, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip7', 'IP Address 7', '', $new_ip7, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns8', 'DNS Server 8', '', $new_dns8, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip8', 'IP Address 8', '', $new_ip8, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns9', 'DNS Server 9', '', $new_dns9, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip9', 'IP Address 9', '', $new_ip9, '255', '', '', '', ''); ?>
        </td>
    </tr>
    <tr>
        <td width="49%">
            <?php echo $form->showInputText('new_dns10', 'DNS Server 10', '', $new_dns10, '255', '', '', '', ''); ?>
        </td>
        <td width="2%">
            &nbsp;
        </td>
        <td width="49%">
            <?php echo $form->showInputText('new_ip10', 'IP Address 10', '', $new_ip10, '255', '', '', '', ''); ?>
        </td>
    </tr>
    </tbody>
</table>
<?php
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showSubmitButton('Add DNS Profile', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); //@formatter:on ?>
</body>
</html>
