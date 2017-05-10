<?php
/**
 * /assets/edit/registrar-account.php
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
require_once(DIR_INC . '/settings/assets-edit-registrar-account.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck($web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$raid = $_GET['raid'];
$new_owner_id = $_POST['new_owner_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_email_address = $_POST['new_email_address'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_reseller_id = $_POST['new_reseller_id'];
$new_api_app_name = $_POST['new_api_app_name'];
$new_api_key = $_POST['new_api_key'];
$new_api_secret = $_POST['new_api_secret'];
$new_api_ip_id = $_POST['new_api_ip_id'];
$new_notes = $_POST['new_notes'];
$new_raid = $_POST['new_raid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_username != "" && $new_owner_id != "" && $new_registrar_id != "" && $new_owner_id != "0" && $new_registrar_id != "0") {

        $query = "UPDATE registrar_accounts
                  SET owner_id = ?,
                      registrar_id = ?,
                      email_address = ?,
                      username = ?,
                      `password` = ?,
                      reseller = ?,
                      reseller_id = ?,
                      api_app_name = ?,
                      api_key = ?,
                      api_secret = ?,
                      api_ip_id = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('iisssissssissi', $new_owner_id, $new_registrar_id, $new_email_address, $new_username,
                $new_password, $new_reseller, $new_reseller_id, $new_api_app_name, $new_api_key, $new_api_secret,
                $new_api_ip_id, $new_notes, $timestamp, $new_raid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $query = "UPDATE domains
                  SET owner_id = ?
                  WHERE account_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('ii', $new_owner_id, $new_raid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $raid = $new_raid;

        $query = "SELECT `name`
                  FROM registrars
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_registrar_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_registrar);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $query = "SELECT `name`
                  FROM owners
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_owner_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_owner);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $_SESSION['s_message_success'] .= "Registrar Account " . $new_username . " (" . $temp_registrar . ", " . $temp_owner . ") Updated<BR>";

        header("Location: ../registrar-accounts.php");
        exit;

    } else {

        if ($new_owner_id == '' || $new_owner_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Owner<BR>";

        }

        if ($new_registrar_id == '' || $new_registrar_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the Registrar<BR>";

        }

        if ($new_username == "") { $_SESSION['s_message_danger'] .= "Enter the username<BR>"; }

    }

} else {

    $query = "SELECT owner_id, registrar_id, email_address, username, `password`, reseller, reseller_id, api_app_name,
                  api_key, api_secret, api_ip_id, notes
              FROM registrar_accounts
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_owner_id, $new_registrar_id, $new_email_address, $new_username,
                        $new_password, $new_reseller, $new_reseller_id, $new_api_app_name, $new_api_key,
                        $new_api_secret, $new_api_ip_id, $new_notes);
        $q->fetch();
        $q->close();

    } else {

        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

}

if ($del == "1") {

    $query = "SELECT account_id
              FROM domains
              WHERE account_id = ?
              LIMIT 1";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_domains = 1;

        }

        if ($existing_domains > 0) {

            $_SESSION['s_message_danger'] .= "This Registrar Account has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Registrar Account?<BR><BR><a
                href=\"registrar-account.php?raid=" . $raid . "&really_del=1\">YES, REALLY DELETE THIS DOMAIN REGISTRAR ACCOUNT</a><BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

}

if ($really_del == "1") {

    $query = "SELECT ra.username AS username, o.name AS owner_name, r.name AS registrar_name
              FROM registrar_accounts AS ra, owners AS o, registrars AS r
              WHERE ra.owner_id = o.id
                AND ra.registrar_id = r.id
                AND ra.id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_username, $temp_owner_name, $temp_registrar_name);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

    $query = "DELETE FROM registrar_accounts
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

    $_SESSION['s_message_success'] .= "Registrar Account " . $temp_username . " (" . $temp_registrar_name . ", " . $temp_owner_name . ") Deleted<BR>";

    $system->checkExistingAssets($dbcon);

    header("Location: ../registrar-accounts.php");
    exit;

}

$query = "SELECT apir.name, apir.req_account_username, apir.req_account_password, apir.req_reseller_id,
              apir.req_api_app_name, apir.req_api_key, apir.req_api_secret, apir.req_ip_address, apir.lists_domains,
              apir.ret_expiry_date, apir.ret_dns_servers, apir.ret_privacy_status, apir.ret_autorenewal_status,
              apir.notes
          FROM registrar_accounts AS ra, registrars AS r, api_registrars AS apir
          WHERE ra.registrar_id = r.id
            AND r.api_registrar_id = apir.id
            AND ra.id = ?";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $raid);
    $q->execute();
    $q->store_result();
    $q->bind_result($api_registrar_name, $req_account_username, $req_account_password, $req_reseller_id,
                    $req_api_app_name, $req_api_key, $req_api_secret, $req_ip_address, $lists_domains,
                    $ret_expiry_date, $ret_dns_servers, $ret_privacy_status, $ret_autorenewal_status,
                    $api_registrar_notes);
    $q->fetch();
    $has_api_support = $q->num_rows();
    $q->close();

} else {

    $error->outputSqlError($dbcon, '1', 'ERROR');
}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
<?php
echo $form->showFormTop('');

$query = "SELECT id, `name`
          FROM registrars
          ORDER BY `name` ASC";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_registrar_id', 'Registrar', '', '1', '');

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $new_registrar_id);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

$query = "SELECT id, `name`
          FROM owners
          ORDER BY `name` ASC";
$q = $dbcon->stmt_init();

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
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

echo $form->showInputText('new_email_address', 'Email Address (100)', '', $new_email_address, '100', '', '', '', '');
echo $form->showInputText('new_username', 'Username (100)', '', $new_username, '100', '', '1', '', '');
echo $form->showInputText('new_password', 'Password (255)', '', $new_password, '255', '', '', '', '');

echo $form->showRadioTop('Reseller Account?', '', '');
echo $form->showRadioOption('new_reseller', '1', 'Yes', $new_reseller, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_reseller', '0', 'No', $new_reseller, '', '');
echo $form->showRadioBottom('');

echo $form->showInputText('new_reseller_id', 'Reseller ID (100)', '', $new_reseller_id, '100', '', '', '', '');

if ($has_api_support >= 1) { ?>

    <div class="box box-default collapsed-box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title" style="padding-top: 3px;">
                <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>&nbsp;API Credentials
            </h3>
        </div>
        <div class="box-body">

            <strong>API Requirements</strong><BR>
            <?php echo $api_registrar_name; ?> requires the following credentials in order to use their API.

            <ul><?php

                $missing_text = ' (<span style="color: #a30000"><strong>missing</strong></span>)';
                $saved_text = ' (<span style="color: darkgreen"><strong>saved</strong></span>)';

                if ($req_account_username == '1') {
                    echo '<li>Registrar Account Username';
                    if ($new_username == '') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_account_password == '1') {
                    echo '<li>Registrar Account Password';
                    if ($new_password == '') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_reseller_id == '1') {
                    echo '<li>Reseller ID';
                    if ($new_reseller_id == '' || $new_reseller_id == '0') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_api_app_name == '1') {
                    echo '<li>API Application Name';
                    if ($new_api_app_name == '') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_api_key == '1') {
                    echo '<li>API Key';
                    if ($new_api_key == '') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_api_secret == '1') {
                    echo '<li>API Secret';
                    if ($new_api_secret == '') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                }
                if ($req_ip_address == '1') {
                    echo '<li>Connecting IP Address';
                    if ($new_api_ip_id == '0') {
                        echo $missing_text;
                    } else {
                        echo $saved_text;
                    }
                    echo '</li>';
                } ?>
            </ul><?php

            if ($api_registrar_notes != '') {

                echo '<strong>Registrar Notes</strong><BR>';
                echo $api_registrar_notes . "<BR><BR>";

            }

            echo $form->showInputText('new_api_app_name', 'API App Name', '', $new_api_app_name, '255', '', '', '', '');
            echo $form->showInputText('new_api_key', 'API Key', '', $new_api_key, '255', '', '', '', '');
            echo $form->showInputText('new_api_secret', 'API Secret', '', $new_api_secret, '255', '', '', '', '');

            $query = "SELECT id, `name`, ip
                      FROM ip_addresses
                      ORDER BY `name` ASC";
            $q = $dbcon->stmt_init();

            if ($q->prepare($query)) {

                $q->execute();
                $q->store_result();
                $q->bind_result($id, $name, $ip_address);

                echo $form->showDropdownTop('new_api_ip_id', 'API IP Address', 'The IP Address that you whitelisted with the domain registrar for API access. <a href="' . $web_root . '/assets/add/ip-address.php">Click here</a> to add a new IP Address.', '', '');

                echo $form->showDropdownOption('0', 'n/a', '0');

                while ($q->fetch()) {

                    echo $form->showDropdownOption($id, $name . ' (' . $ip_address . ')', $new_api_ip_id);

                }

                echo $form->showDropdownBottom('');

                $q->close();

            } else {
                $error->outputSqlError($dbcon, '1', 'ERROR');
            } ?>

        </div>
    </div><BR><?php

}

echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_raid', $raid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="registrar-account.php?raid=<?php echo urlencode($raid); ?>&del=1">DELETE THIS REGISTRAR ACCOUNT</a>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
