<?php
/**
 * /assets/edit/ssl-provider-fee.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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

$assets = new DomainMOD\Assets();
$conversion = new DomainMOD\Conversion();
$deeb = DomainMOD\Database::getInstance();
$form = new DomainMOD\Form();
$layout = new DomainMOD\Layout();
$log = new DomainMOD\Log('/assets/edit/ssl-provider-fee.php');
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-provider-fee.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

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

        try {

            $pdo->beginTransaction();

            $timestamp = $time->stamp();

            $stmt = $pdo->prepare("
                UPDATE ssl_fees
                SET initial_fee = :new_initial_fee,
                    renewal_fee = :new_renewal_fee,
                    misc_fee = :new_misc_fee,
                    currency_id = :new_currency_id,
                    update_time = :timestamp
                WHERE ssl_provider_id = :sslpid
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_initial_fee', strval($new_initial_fee), PDO::PARAM_STR);
            $stmt->bindValue('new_renewal_fee', strval($new_renewal_fee), PDO::PARAM_STR);
            $stmt->bindValue('new_misc_fee', strval($new_misc_fee), PDO::PARAM_STR);
            $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_fees
                WHERE ssl_provider_id = :sslpid
                  AND type_id = :new_type_id
                  AND currency_id = :new_currency_id
                LIMIT 1");
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
            $stmt->execute();
            $new_fee_id = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET fee_id = :new_fee_id,
                    update_time = :timestamp
                WHERE ssl_provider_id = :sslpid
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();

            $temp_type = $assets->getSslType($new_type_id);

            $stmt = $pdo->prepare("
                UPDATE ssl_certs sslc
                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                WHERE sslc.fee_id = :new_fee_id");
            $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
            $stmt->execute();

            $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);

            $pdo->commit();

            $_SESSION['s_message_success'] .= "The fee for " . $temp_type . " has been updated<BR>";

            header("Location: ../ssl-provider-fees.php?sslpid=" . urlencode($sslpid));
            exit;

        } catch (Exception $e) {

            $pdo->rollback();

            $log_message = 'Unable to update fee';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    } else {

        if ($new_initial_fee == '') $_SESSION['s_message_danger'] .= "Enter the initial fee<BR>";
        if ($new_renewal_fee == '') $_SESSION['s_message_danger'] .= "Enter the renewal fee<BR>";

    }

} else {

    $stmt = $pdo->prepare("
        SELECT ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id
        FROM ssl_fees
        WHERE id = :fee_id");
    $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $sslpid = $result->ssl_provider_id;
        $new_type_id = $result->type_id;
        $new_initial_fee = $result->initial_fee;
        $new_renewal_fee = $result->renewal_fee;
        $new_misc_fee = $result->misc_fee;
        $new_currency_id = $result->currency_id;

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
<a href="../ssl-provider-fees.php?sslpid=<?php echo urlencode($sslpid); ?>"><?php echo $layout->showButton('button', 'Back to SSL Provider Fees'); ?></a><BR><BR>
<?php
echo $form->showFormTop('');
?>
<strong>SSL Provider</strong><BR>
<?php
$temp_ssl_provider = $assets->getSslProvider($sslpid);
echo $temp_ssl_provider;
?>
<BR><BR><?php

$temp_type = $assets->getSslType($new_type_id);
?>
<strong>Type</strong><BR>
<?php echo $temp_type; ?><BR><BR>
<?php
echo $form->showInputText('new_initial_fee', 'Initial Fee', '', $new_initial_fee, '10', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', 'Renewal Fee', '', $new_renewal_fee, '10', '', '1', '', '');
echo $form->showInputText('new_misc_fee', 'Misc Fee', '', $new_misc_fee, '10', '', '', '', '');

$result = $pdo->query("
    SELECT id, currency, `name`, symbol
    FROM currencies
    ORDER BY `name`")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_currency_id', 'Currency', '', '', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->currency . ')', $new_currency_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showInputHidden('fee_id', $fee_id);
echo $form->showInputHidden('sslpid', $sslpid);
echo $form->showInputHidden('new_type_id', $new_type_id);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
