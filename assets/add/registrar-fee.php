<?php
/**
 * /assets/add/registrar-fee.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/assets/add/registrar-fee.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$conversion = new DomainMOD\Conversion();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-add-registrar-fee.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$rid = (int) ($_REQUEST['rid'] ?? 0);
$tld = isset($_GET['tld']) ? $sanitize->text($_GET['tld']) : '';
if ($tld != '') {
    $new_tld = $tld;
} else {
    $new_tld = $sanitize->text($_POST['new_tld']);
}
$new_initial_fee = (float) ($_POST['new_initial_fee'] ?? 0.0);
$new_renewal_fee = (float) ($_POST['new_renewal_fee'] ?? 0.0);
$new_transfer_fee = (float) ($_POST['new_transfer_fee'] ?? 0.0);
$new_privacy_fee = (float) ($_POST['new_privacy_fee'] ?? 0.0);
$new_misc_fee = (float) ($_POST['new_misc_fee'] ?? 0.0);
$new_currency = isset($_POST['new_currency']) ? $sanitize->text($_POST['new_currency']) : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_tld == '') {

        if ($new_tld == '') $_SESSION['s_message_danger'] .= _('Enter the TLD') . '<BR>';

    } else {

        $new_tld = trim($new_tld, ". \t\n\r\0\x0B");
        $timestamp = $time->stamp();

        $stmt = $pdo->prepare("
            SELECT id
            FROM fees
            WHERE registrar_id = :rid
              AND tld = :new_tld
            LIMIT 1");
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
        $stmt->execute();
        $fee_id = $stmt->fetchColumn();

        if ($fee_id) {

            $_SESSION['s_message_danger'] .= _('A fee for this TLD already exists') . ' [<a href=\'' . WEB_ROOT .
                '/assets/edit/registrar-fee.php?rid=' . $rid . '&fee_id=' . urlencode($fee_id) .
                '\'>' . strtolower(_('Edit Fee')) . '</a>]<BR>';

        } else {

            try {

                $pdo->beginTransaction();

                $currency = new DomainMOD\Currency();
                $currency_id = $currency->getCurrencyId($new_currency);

                $stmt = $pdo->prepare("
                    INSERT INTO fees
                    (registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id, insert_time)
                    VALUES
                    (:rid, :new_tld, :new_initial_fee, :new_renewal_fee, :new_transfer_fee, :new_privacy_fee, :new_misc_fee, :currency_id, :timestamp)");
                $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
                $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                $stmt->bindValue('new_initial_fee', strval($new_initial_fee), PDO::PARAM_STR);
                $stmt->bindValue('new_renewal_fee', strval($new_renewal_fee), PDO::PARAM_STR);
                $stmt->bindValue('new_transfer_fee', strval($new_transfer_fee), PDO::PARAM_STR);
                $stmt->bindValue('new_privacy_fee', strval($new_privacy_fee), PDO::PARAM_STR);
                $stmt->bindValue('new_misc_fee', strval($new_misc_fee), PDO::PARAM_STR);
                $stmt->bindValue('currency_id', $currency_id, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->execute();

                $new_fee_id = $pdo->lastInsertId('id');

                $stmt = $pdo->prepare("
                    UPDATE domains
                    SET fee_id = :new_fee_id,
                        update_time = :timestamp
                    WHERE registrar_id = :rid
                      AND tld = :new_tld");
                $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
                $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                $stmt->execute();

                $stmt = $pdo->prepare("
                    UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                    WHERE d.privacy = '1'
                      AND d.fee_id = :new_fee_id");
                $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $pdo->prepare("
                    UPDATE domains d
                    JOIN fees f ON d.fee_id = f.id
                    SET d.total_cost = f.renewal_fee + f.misc_fee
                    WHERE d.privacy = '0'
                      AND d.fee_id = :new_fee_id");
                $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                $stmt->execute();

                $queryB = new DomainMOD\QueryBuild();

                $sql = $queryB->missingFees('domains');
                $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);

                if ($pdo->InTransaction()) $pdo->commit();

                $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been added'), $new_tld) . '<BR>';

                header("Location: ../registrar-fees.php?rid=" . $rid);
                exit;

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to add registrar fee';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<a href="../registrar-fees.php?rid=<?php echo $rid; ?>"><?php echo $layout->showButton('button', _('Back to Registrar Fees')); ?></a><BR><BR>
<?php
echo $form->showFormTop('');
?>
<strong><?php echo _('Domain Registrar'); ?></strong><BR>
<?php echo $assets->getRegistrar($rid); ?><BR><BR><?php

echo $form->showInputText('new_tld', _('TLD'), '', $unsanitize->text($new_tld), '50', '', '1', '', '');
echo $form->showInputText('new_initial_fee', _('Initial Fee'), '', $new_initial_fee, '10', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', _('Renewal Fee'), '', $new_renewal_fee, '10', '', '1', '', '');
echo $form->showInputText('new_transfer_fee', _('Transfer Fee'), '', $new_transfer_fee, '10', '', '1', '', '');
echo $form->showInputText('new_privacy_fee', _('Privacy Fee'), '', $new_privacy_fee, '10', '', '', '', '');
echo $form->showInputText('new_misc_fee', _('Misc Fee'), '', $new_misc_fee, '10', '', '', '', '');

echo $form->showDropdownTop('new_currency', _('Currency'), '', '', '');
$result = $pdo->query("
    SELECT id, currency, `name`, symbol
    FROM currencies
    ORDER BY `name`")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ')',
        $new_currency ?? $_SESSION['s_default_currency']);

}
echo $form->showDropdownBottom('');

echo $form->showSubmitButton(_('Add Fee'), '', '');
echo $form->showInputHidden('rid', $rid);
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
