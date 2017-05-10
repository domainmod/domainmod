<?php
/**
 * /assets/edit/registrar-fee.php
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
require_once(DIR_INC . '/settings/assets-edit-registrar-fee.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();

$fee_id = $_REQUEST['fee_id'];
$rid = $_REQUEST['rid'];
$new_tld = $_POST['new_tld'];
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_transfer_fee = $_POST['new_transfer_fee'];
$new_privacy_fee = $_POST['new_privacy_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency_id = $_POST['new_currency_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_initial_fee != '' && $new_renewal_fee != '' && $new_transfer_fee != '') {

        $new_tld = trim($new_tld, ". \t\n\r\0\x0B");
        $timestamp = $time->stamp();

        $query = "UPDATE fees
                  SET initial_fee = ?,
                      renewal_fee = ?,
                      transfer_fee = ?,
                      privacy_fee = ?,
                      misc_fee = ?,
                      currency_id = ?,
                      update_time = ?
                  WHERE registrar_id = ?
                    AND tld = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('dddddisis', $new_initial_fee, $new_renewal_fee, $new_transfer_fee, $new_privacy_fee, $new_misc_fee, $new_currency_id, $timestamp, $rid, $new_tld);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, '1', 'ERROR');

        $query = "UPDATE domains
                  SET fee_id = ?,
                      update_time = ?
                  WHERE registrar_id = ?
                    AND tld = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('isis', $fee_id, $timestamp, $rid, $new_tld);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, '1', 'ERROR');

        $query = "UPDATE domains d
                  JOIN fees f ON d.fee_id = f.id
                  SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                  WHERE d.privacy = '1'
                    AND d.fee_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $fee_id);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, '1', 'ERROR');

        $query = "UPDATE domains d
                  JOIN fees f ON d.fee_id = f.id
                  SET d.total_cost = f.renewal_fee + f.misc_fee
                  WHERE d.privacy = '0'
                    AND d.fee_id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $fee_id);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($dbcon, '1', 'ERROR');

        $conversion->updateRates($dbcon, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

        $_SESSION['s_message_success'] .= "The fee for ." . $new_tld . " has been updated<BR>";

        header("Location: ../registrar-fees.php?rid=" . urlencode($rid));
        exit;

    } else {

        if ($new_initial_fee == '') $_SESSION['s_message_danger'] .= "Enter the initial fee<BR>";
        if ($new_renewal_fee == '') $_SESSION['s_message_danger'] .= "Enter the renewal fee<BR>";
        if ($new_transfer_fee == '') $_SESSION['s_message_danger'] .= "Enter the transfer fee<BR>";

    }

} else {

    $query = "SELECT registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id
              FROM fees
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $fee_id);
        $q->execute();
        $q->store_result();
        $q->bind_result($rid, $new_tld, $new_initial_fee, $new_renewal_fee, $new_transfer_fee, $new_privacy_fee, $new_misc_fee, $new_currency_id);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
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
<?php echo $temp_registrar; ?><BR><BR>
<strong>TLD</strong><BR>
<?php echo '.' . htmlentities($new_tld, ENT_QUOTES, 'UTF-8'); ?><BR><BR>
<?php
echo $form->showInputText('new_initial_fee', 'Initial Fee', '', $new_initial_fee, '10', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', 'Renewal Fee', '', $new_renewal_fee, '10', '', '1', '', '');
echo $form->showInputText('new_transfer_fee', 'Transfer Fee', '', $new_transfer_fee, '10', '', '1', '', '');
echo $form->showInputText('new_privacy_fee', 'Privacy Fee', '', $new_privacy_fee, '10', '', '', '', '');
echo $form->showInputText('new_misc_fee', 'Misc Fee', '', $new_misc_fee, '10', '', '', '', '');

$sql = "SELECT id, currency, `name`, symbol
        FROM currencies
        ORDER BY `name`";
$result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
echo $form->showDropdownTop('new_currency_id', 'Currency', '', '', '');
while ($row = mysqli_fetch_object($result)) {

    echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->currency . ')', $new_currency_id);

}
echo $form->showDropdownBottom('');
echo $form->showInputHidden('fee_id', $fee_id);
echo $form->showInputHidden('rid', $rid);
echo $form->showInputHidden('new_tld', $new_tld);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
