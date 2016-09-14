<?php
/**
 * /assets/edit/ssl-provider-account.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include(DIR_INC . "settings/assets-edit-ssl-account.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpaid = $_GET['sslpaid'];
$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_email_address = $_POST['new_email_address'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_reseller_id = $_POST['new_reseller_id'];
$new_notes = $_POST['new_notes'];
$new_sslpaid = $_POST['new_sslpaid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" && $new_ssl_provider_id != "0") {

        $query = "UPDATE ssl_accounts
                  SET owner_id = ?,
                      ssl_provider_id = ?,
                      email_address = ?,
                      username = ?,
                      `password` = ?,
                      reseller = ?,
                      reseller_id = ?,
                      notes = ?,
                      update_time = ?
                      WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('iisssisssi', $new_owner_id, $new_ssl_provider_id, $new_email_address, $new_username,
                $new_password, $new_reseller, $new_reseller_id, $new_notes, $timestamp, $new_sslpaid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $sslpaid = $new_sslpaid;

        $query = "SELECT `name`
                  FROM ssl_providers
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_ssl_provider_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_ssl_provider);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT `name`
                  FROM owners
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_owner_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_owner);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_message_success'] .= "SSL Account " . $new_username . " (" . $temp_ssl_provider . ", " . $temp_owner . ") Updated<BR>";

        header("Location: ../ssl-accounts.php");
        exit;

    } else {

        if ($new_owner_id == '' || $new_owner_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Owner<BR>";

        }

        if ($new_ssl_provider_id == '' || $new_ssl_provider_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the SSL Provider<BR>";

        }

        if ($new_username == "") { $_SESSION['s_message_danger'] .= "Enter a username<BR>"; }

    }

} else {

    $query = "SELECT owner_id, ssl_provider_id, email_address, username, `password`, reseller, reseller_id, notes
              FROM ssl_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_owner_id, $new_ssl_provider_id, $new_email_address, $new_username, $new_password, $new_reseller, $new_reseller_id, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($del == "1") {

    $query = "SELECT account_id
              FROM ssl_certs
              WHERE account_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_certs = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    if ($existing_ssl_certs > 0) {

        $_SESSION['s_message_danger'] .= "This SSL Account has SSL certificates associated with it and cannot be
        deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this SSL Account?<BR><BR><a
            href=\"ssl-provider-account.php?sslpaid=" . $sslpaid . "&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER ACCOUNT</a><BR>";

    }

}

if ($really_del == "1") {

    $query = "SELECT a.username AS username, o.name AS owner_name, p.name AS ssl_provider_name
              FROM ssl_accounts AS a, owners AS o, ssl_providers AS p
              WHERE a.owner_id = o.id
                AND a.ssl_provider_id = p.id
                AND a.id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_username, $temp_owner_name, $temp_ssl_provider_name);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM ssl_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['s_message_success'] .= "SSL Account " . $temp_username . " (" . $temp_ssl_provider_name . ", " . $temp_owner_name . ") Deleted<BR>";

    $system->checkExistingAssets($connection);

    header("Location: ../ssl-accounts.php");
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

$query = "SELECT id, `name`
              FROM ssl_providers
              ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_ssl_provider_id', 'SSL Provider', '', '1', '');

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $new_ssl_provider_id);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($conn, "ERROR");
}

$query = "SELECT id, `name`
          FROM owners
          ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_owner_id', 'Account Owner', '', '1', '');

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $new_owner_id);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($conn, "ERROR");
}

echo $form->showInputText('new_email_address', 'Email Address (100)', '', $new_email_address, '100', '', '', '', '');
echo $form->showInputText('new_username', 'Username (100)', '', $new_username, '100', '', '1', '', '');
echo $form->showInputText('new_password', 'Password (255)', '', $new_password, '255', '', '', '', '');
echo $form->showRadioTop('Reseller Account?', '', '');
echo $form->showRadioOption('new_reseller', '1', 'Yes', $new_reseller, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_reseller', '0', 'No', $new_reseller, '', '');
echo $form->showRadioBottom('');
echo $form->showInputText('new_reseller_id', 'Reseller ID (100)', '', $new_reseller_id, '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_sslpaid', $sslpaid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="ssl-provider-account.php?sslpaid=<?php echo urlencode($sslpaid); ?>&del=1">DELETE THIS SSL PROVIDER ACCOUNT</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
