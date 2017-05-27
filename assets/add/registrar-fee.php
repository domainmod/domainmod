<?php
/**
 * /assets/add/registrar-fee.php
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$conversion = new DomainMOD\Conversion();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/assets-add-registrar-fee.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$rid = $_REQUEST['rid'];
$tld = $_GET['tld'];
if ($tld != '') {
    $new_tld = $tld;
} else {
    $new_tld = $_POST['new_tld'];
}
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_transfer_fee = $_POST['new_transfer_fee'];
$new_privacy_fee = $_POST['new_privacy_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency = $_POST['new_currency'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_tld != '' && $new_initial_fee != '' && $new_renewal_fee != '' && $new_transfer_fee != '') {

        $new_tld = trim($new_tld, ". \t\n\r\0\x0B");
        $timestamp = $time->stamp();

        $query = "SELECT *
                  FROM fees
                  WHERE registrar_id = ?
                    AND tld = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('is', $rid, $new_tld);
            $q->execute();
            $q->store_result();

            if ($q->num_rows() > 0) {

                $_SESSION['s_message_danger'] .= 'A fee for this TLD already exists.<BR>';

            } else {

                $query2 = "SELECT id
                           FROM currencies
                           WHERE currency = ?";
                $q2 = $dbcon->stmt_init();

                if ($q2->prepare($query2)) {

                    $q2->bind_param('s', $new_currency);
                    $q2->execute();
                    $q2->store_result();
                    $q2->bind_result($id);

                    while ($q2->fetch()) {

                        $currency_id = $id;

                    }

                    $q2->close();

                } else $error->outputSqlError($dbcon, '1', 'ERROR');

                $query2 = "INSERT INTO fees
                           (registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id, insert_time)
                           VALUES
                           (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $q2 = $dbcon->stmt_init();

                if ($q2->prepare($query2)) {

                    $q2->bind_param('isdddddis', $rid, $new_tld, $new_initial_fee, $new_renewal_fee, $new_transfer_fee, $new_privacy_fee, $new_misc_fee, $currency_id, $timestamp);
                    $q2->execute();

                    $new_fee_id = $q2->insert_id;

                    $q2->close();

                } else {
                    $error->outputSqlError($dbcon, '1', 'ERROR');
                }

                $query2 = "UPDATE domains
                           SET fee_id = ?,
                               update_time = ?
                           WHERE registrar_id = ?
                             AND tld = ?";
                $q2 = $dbcon->stmt_init();

                if ($q2->prepare($query2)) {

                    $q2->bind_param('isis', $new_fee_id, $timestamp, $rid, $new_tld);
                    $q2->execute();
                    $q2->close();

                } else $error->outputSqlError($dbcon, '1', 'ERROR');

                $query2 = "UPDATE domains d
                           JOIN fees f ON d.fee_id = f.id
                           SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                           WHERE d.privacy = '1'
                             AND d.fee_id = ?";
                $q2 = $dbcon->stmt_init();

                if ($q2->prepare($query2)) {

                    $q2->bind_param('i', $new_fee_id);
                    $q2->execute();
                    $q2->close();

                } else $error->outputSqlError($dbcon, '1', 'ERROR');

                $query2 = "UPDATE domains d
                           JOIN fees f ON d.fee_id = f.id
                           SET d.total_cost = f.renewal_fee + f.misc_fee
                           WHERE d.privacy = '0'
                             AND d.fee_id = ?";
                $q2 = $dbcon->stmt_init();

                if ($q2->prepare($query2)) {

                    $q2->bind_param('i', $new_fee_id);
                    $q2->execute();
                    $q2->close();

                } else $error->outputSqlError($dbcon, '1', 'ERROR');

                $queryB = new DomainMOD\QueryBuild();

                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);

                $_SESSION['s_message_success'] .= "The fee for " . $new_tld . "has been added<BR>";

                header("Location: ../registrar-fees.php?rid=" . urlencode($rid));
                exit;

            }

            $q->close();

        } else $error->outputSqlError($dbcon, '1', 'ERROR');

    } else {

        if ($new_tld == '') $_SESSION['s_message_danger'] .= "Enter the TLD<BR>";
        if ($new_initial_fee == '') $_SESSION['s_message_danger'] .= "Enter the initial fee<BR>";
        if ($new_renewal_fee == '') $_SESSION['s_message_danger'] .= "Enter the renewal fee<BR>";
        if ($new_transfer_fee == '') $_SESSION['s_message_danger'] .= "Enter the transfer fee<BR>";

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
<a href="../registrar-fees.php?rid=<?php echo urlencode($rid); ?>"><?php echo $layout->showButton('button', 'Back to Registrar Fees'); ?></a><BR><BR>
<?php
echo $form->showFormTop('');

$query = "SELECT `name`
          FROM registrars
          WHERE id = ?";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $rid);
    $q->execute();
    $q->store_result();
    $q->bind_result($t_registrar);

    while ($q->fetch()) {

        $temp_registrar = $t_registrar;

    }

    $q->close();

} else $error->outputSqlError($dbcon, '1', 'ERROR');
?>
<strong>Domain Registrar</strong><BR>
<?php echo $temp_registrar; ?><BR><BR><?php

echo $form->showInputText('new_tld', 'TLD', '', $new_tld, '50', '', '1', '', '');
echo $form->showInputText('new_initial_fee', 'Initial Fee', '', $new_initial_fee, '10', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', 'Renewal Fee', '', $new_renewal_fee, '10', '', '1', '', '');
echo $form->showInputText('new_transfer_fee', 'Transfer Fee', '', $new_transfer_fee, '10', '', '1', '', '');
echo $form->showInputText('new_privacy_fee', 'Privacy Fee', '', $new_privacy_fee, '10', '', '', '', '');
echo $form->showInputText('new_misc_fee', 'Misc Fee', '', $new_misc_fee, '10', '', '', '', '');

$sql = "SELECT id, currency, `name`, symbol
        FROM currencies
        ORDER BY `name`";
$result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_currency', 'Currency', '', '', '');
while ($row = mysqli_fetch_object($result)) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ')', $_SESSION['s_default_currency']);

}
echo $form->showDropdownBottom('');
echo $form->showSubmitButton('Add Fee', '', '');
echo $form->showInputHidden('rid', $rid);
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
