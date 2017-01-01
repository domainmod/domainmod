<?php
/**
 * /assets/edit/dns.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/assets-edit-dns.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$dnsid = $_GET['dnsid'];

$new_name = $_POST['new_name'];
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
$new_dnsid = $_POST['new_dnsid'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_name != "" && $new_dns1 != "" && $new_dns2 != "") {

        $new_number_of_servers = 10;

        if ($new_dns10 == '') $new_number_of_servers = '9';
        if ($new_dns9 == '') $new_number_of_servers = '8';
        if ($new_dns8 == '') $new_number_of_servers = '7';
        if ($new_dns7 == '') $new_number_of_servers = '6';
        if ($new_dns6 == '') $new_number_of_servers = '5';
        if ($new_dns5 == '') $new_number_of_servers = '4';
        if ($new_dns4 == '') $new_number_of_servers = '3';
        if ($new_dns3 == '') $new_number_of_servers = '2';
        if ($new_dns2 == '') $new_number_of_servers = '1';
        if ($new_dns1 == '') $new_number_of_servers = '0';

        $query = "UPDATE dns
                  SET `name` = ?,
                      dns1 = ?,
                      dns2 = ?,
                      dns3 = ?,
                      dns4 = ?,
                      dns5 = ?,
                      dns6 = ?,
                      dns7 = ?,
                      dns8 = ?,
                      dns9 = ?,
                      dns10 = ?,
                      ip1 = ?,
                      ip2 = ?,
                      ip3 = ?,
                      ip4 = ?,
                      ip5 = ?,
                      ip6 = ?,
                      ip7 = ?,
                      ip8 = ?,
                      ip9 = ?,
                      ip10 = ?,
                      notes = ?,
                      number_of_servers = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('ssssssssssssssssssssssisi', $new_name, $new_dns1, $new_dns2, $new_dns3, $new_dns4,
                $new_dns5, $new_dns6, $new_dns7, $new_dns8, $new_dns9, $new_dns10, $new_ip1, $new_ip2, $new_ip3,
                $new_ip4, $new_ip5, $new_ip6, $new_ip7, $new_ip8, $new_ip9, $new_ip10, $new_notes,
                $new_number_of_servers, $timestamp, $new_dnsid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $dnsid = $new_dnsid;

        $_SESSION['s_message_success'] .= "DNS Profile " . $new_name . " Updated<BR>";

        header("Location: ../dns.php");
        exit;

    } else {

        if ($new_name == "") $_SESSION['s_message_danger'] .= "Enter a name for the DNS profile<BR>";
        if ($new_dns1 == "") $_SESSION['s_message_danger'] .= "Enter the first DNS server<BR>";
        if ($new_dns2 == "") $_SESSION['s_message_danger'] .= "Enter the second DNS server<BR>";

    }

} else {

    $query = "SELECT `name`, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6,
                  ip7, ip8, ip9, ip10, notes
              FROM dns
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dnsid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_name, $new_dns1, $new_dns2, $new_dns3, $new_dns4, $new_dns5, $new_dns6, $new_dns7,
            $new_dns8, $new_dns9, $new_dns10, $new_ip1, $new_ip2, $new_ip3, $new_ip4, $new_ip5, $new_ip6, $new_ip7,
            $new_ip8, $new_ip9, $new_ip10, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}
if ($del == "1") {

    $query = "SELECT dns_id
              FROM domains
              WHERE dns_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dnsid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['s_message_danger'] .= "This DNS Profile has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['s_message_danger'] .= "Are you sure you want to delete this DNS Profile?<BR><BR><a
                href=\"dns.php?dnsid=" . $dnsid . "&really_del=1\">YES, REALLY DELETE THIS DNS PROFILE</a><BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($really_del == "1") {

    $query = "DELETE FROM dns
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dnsid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['s_message_success'] .= "DNS Profile " . $new_name . " Deleted<BR>";

    header("Location: ../dns.php");
    exit;

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
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
echo $form->showInputHidden('new_dnsid', $dnsid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="dns.php?dnsid=<?php echo urlencode($dnsid); ?>&del=1">DELETE THIS DNS PROFILE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
</body>
</html>
