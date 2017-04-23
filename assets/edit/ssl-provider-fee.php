<?php
/**
 * /assets/edit/ssl-provider-fee.php
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

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$conversion = new DomainMOD\Conversion();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/assets-edit-ssl-provider-fee.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$fee_id = $_REQUEST['fee_id'];
$sslpid = $_REQUEST['sslpid'];
$new_type_id = $_POST['new_type_id'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency_id = $_POST['new_currency_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_initial_fee != '' && $new_renewal_fee != '') {

        $timestamp = $time->stamp();

        $query = "UPDATE ssl_fees
                  SET initial_fee = ?,
                      renewal_fee = ?,
                      misc_fee = ?,
                      currency_id = ?,
                      update_time = ?
                  WHERE ssl_provider_id = ?
                    AND type_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('dddisii', $new_initial_fee, $new_renewal_fee, $new_misc_fee, $new_currency_id, $timestamp, $sslpid, $new_type_id);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, "ERROR");

        $query = "SELECT id
                  FROM ssl_fees
                  WHERE ssl_provider_id = ?
                    AND type_id = ?
                    AND currency_id = ?
                  LIMIT 1";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('iii', $sslpid, $new_type_id, $new_currency_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_id);

            while ($q->fetch()) {

                $new_fee_id = $temp_id;

            }

            $q->close();

        } else $error->outputSqlError($dbcon, "ERROR");

        $query = "UPDATE ssl_certs
                  SET fee_id = ?,
                      update_time = ?
                  WHERE ssl_provider_id = ?
                    AND type_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('isii', $new_fee_id, $timestamp, $sslpid, $new_type_id);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, "ERROR");

        $query = "SELECT type
                  FROM ssl_cert_types
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_type_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($t_type);

            while ($q->fetch()) {

                $temp_type = $t_type;

            }

            $q->close();

        } else $error->outputSqlError($dbcon, "ERROR");

        $query = "UPDATE ssl_certs sslc
                  JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                  SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                  WHERE sslc.fee_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_fee_id);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, "ERROR");

        $conversion->updateRates($dbcon, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

        $_SESSION['s_message_success'] .= "The fee for " . $temp_type . " has been updated<BR>";

        header("Location: ../ssl-provider-fees.php?sslpid=" . urlencode($sslpid));
        exit;

    } else {

        if ($new_initial_fee == '') $_SESSION['s_message_danger'] .= "Enter the initial fee<BR>";
        if ($new_renewal_fee == '') $_SESSION['s_message_danger'] .= "Enter the renewal fee<BR>";

    }

} else {

    $query = "SELECT ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id
              FROM ssl_fees
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $fee_id);
        $q->execute();
        $q->store_result();
        $q->bind_result($sslpid, $new_type_id, $new_initial_fee, $new_renewal_fee, $new_misc_fee, $new_currency_id);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, "ERROR");
    }

}
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . 'layout/header.inc.php'); ?>
<a href="../ssl-provider-fees.php?sslpid=<?php echo urlencode($sslpid); ?>"><?php echo $layout->showButton('button', 'Back to SSL Provider Fees'); ?></a><BR><BR>
<?php
echo $form->showFormTop('');

$query = "SELECT `name`
          FROM ssl_providers
          where id = ?";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $sslpid);
    $q->execute();
    $q->store_result();
    $q->bind_result($t_ssl_provider);

    while ($q->fetch()) {

        $temp_ssl_provider = $t_ssl_provider;

    }

    $q->close();

} else $error->outputSqlError($dbcon, "ERROR");
?>
<strong>SSL Provider</strong><BR>
<?php echo $temp_ssl_provider; ?><BR><BR><?php

$query = "SELECT type
          FROM ssl_cert_types
          WHERE id = ?";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $new_type_id);
    $q->execute();
    $q->store_result();
    $q->bind_result($t_type);

    while ($q->fetch()) {

        $temp_type = $t_type;

    }

    $q->close();

} else $error->outputSqlError($dbcon, "ERROR");
?>
<strong>Type</strong><BR>
<?php echo $temp_type; ?><BR><BR>
<?php
echo $form->showInputText('new_initial_fee', 'Initial Fee', '', $new_initial_fee, '10', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', 'Renewal Fee', '', $new_renewal_fee, '10', '', '1', '', '');
echo $form->showInputText('new_misc_fee', 'Misc Fee', '', $new_misc_fee, '10', '', '', '', '');

$sql = "SELECT id, currency, `name`, symbol
        FROM currencies
        ORDER BY `name`";
$result = mysqli_query($dbcon, $sql) or $error->outputOldSqlError($dbcon);
echo $form->showDropdownTop('new_currency_id', 'Currency', '', '', '');
while ($row = mysqli_fetch_object($result)) {

    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->currency . ')', $new_currency_id);

}
echo $form->showDropdownBottom('');
echo $form->showInputHidden('fee_id', $fee_id);
echo $form->showInputHidden('sslpid', $sslpid);
echo $form->showInputHidden('new_type_id', $new_type_id);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
