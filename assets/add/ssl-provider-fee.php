<?php
/**
 * /assets/add/ssl-provider-fee.php
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$conversion = new DomainMOD\Conversion();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/assets-add-ssl-provider-fee.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$sslpid = $_REQUEST['sslpid'];
$type_id = $_GET['type_id'];
if ($type_id != '') {
    $new_type_id = $type_id;
} else {
    $new_type_id = $_POST['new_type_id'];
}
$new_initial_fee = $_POST['new_initial_fee'];
$new_renewal_fee = $_POST['new_renewal_fee'];
$new_misc_fee = $_POST['new_misc_fee'];
$new_currency = $_POST['new_currency'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_type_id != '' && $new_initial_fee != '' && $new_renewal_fee != '') {

        $timestamp = $time->stamp();

        $sql = "SELECT *
                FROM ssl_fees
                WHERE ssl_provider_id = '" . $sslpid . "'
                  AND type_id = '" . $new_type_id . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            $_SESSION['s_message_danger'] .= 'A fee for this SSL Type already exists.<BR>';

        } else {

            $sql = "SELECT id
                    FROM currencies
                    WHERE currency = '" . $new_currency . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            while ($row = mysqli_fetch_object($result)) {
                $currency_id = $row->id;
            }

            $sql = "INSERT INTO ssl_fees
                    (ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id, insert_time)
                    VALUES
                    ('" . $sslpid . "', '" . $new_type_id . "', '" . $new_initial_fee . "',
                     '" . $new_renewal_fee . "', '" . $new_misc_fee . "', '" . $currency_id . "',
                     '" . $timestamp . "')";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            $new_fee_id = mysqli_insert_id($connection);

            $sql = "UPDATE ssl_certs
                    SET fee_id = '" . $new_fee_id . "',
                        update_time = '" . $timestamp . "'
                    WHERE ssl_provider_id = '" . $sslpid . "'
                      AND type_id = '" . $new_type_id . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            $sql = "SELECT type
                    FROM ssl_cert_types
                    WHERE id = '" . $new_type_id . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            while ($row = mysqli_fetch_object($result)) {
                $temp_type = $row->type;
            }

            $sql = "UPDATE ssl_certs sslc
                    JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                    SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                    WHERE sslc.fee_id = '" . $new_fee_id . "'";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('ssl_certs');
            $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

            $conversion->updateRates($connection, $_SESSION['s_default_currency'], $_SESSION['s_user_id']);

            $_SESSION['s_message_success'] .= "The fee for " . $temp_type . "has been added<BR>";

            $sslpid_clean = (integer) $sslpid;

            header("Location: ../ssl-provider-fees.php?sslpid=" . $sslpid_clean);
            exit;

        }

    } else {

        if ($new_type_id == '') $_SESSION['s_message_danger'] .= "Choose the SSL Type<BR>";
        if ($new_initial_fee == '') $_SESSION['s_message_danger'] .= "Enter the initial fee<BR>";
        if ($new_renewal_fee == '') $_SESSION['s_message_danger'] .= "Enter the renewal fee<BR>";

    }

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
<a href="../ssl-provider-fees.php?sslpid=<?php echo $sslpid; ?>"><?php echo $layout->showButton('button', 'Back to SSL Provider Fees'); ?></a><BR><BR>
<?php
echo $form->showFormTop('');

$sql = "SELECT `name`
        FROM ssl_providers
        where id = '" . $sslpid . "'";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
    $temp_ssl_provider = $row->name;
}
?>
<strong>SSL Provider</strong><BR>
<?php echo $temp_ssl_provider; ?><BR><BR><?php

$sql = "SELECT id, type
        FROM ssl_cert_types
        ORDER BY `type`";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_type_id', 'SSL Type', '', '');
echo $form->showDropdownOption('', 'Choose an SSL Type', 'null');
while ($row = mysqli_fetch_object($result)) {

    echo $form->showDropdownOption($row->id, $row->type, $new_type_id);

}
echo $form->showDropdownBottom('');

echo $form->showInputText('new_initial_fee', 'Initial Fee', '', $new_initial_fee, '10', '', '', '');
echo $form->showInputText('new_renewal_fee', 'Renewal Fee', '', $new_renewal_fee, '10', '', '', '');
echo $form->showInputText('new_misc_fee', 'Misc Fee', '', $new_misc_fee, '10', '', '', '');

$sql = "SELECT id, currency, `name`, symbol
        FROM currencies
        ORDER BY `name`";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
echo $form->showDropdownTop('new_currency', 'Currency', '', '');
while ($row = mysqli_fetch_object($result)) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ')', $_SESSION['s_default_currency']);

}
echo $form->showDropdownBottom('');

echo $form->showSubmitButton('Add Fee', '', '');
echo $form->showInputHidden('sslpid', $sslpid);
echo $form->showFormBottom('');
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
