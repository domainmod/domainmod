<?php
/**
 * /assets/edit/ssl-provider-fee.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
$log = new DomainMOD\Log('/assets/edit/ssl-provider-fee.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$conversion = new DomainMOD\Conversion();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-ssl-provider-fee.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// An ID sent as anything but digits (such as an array, which an (int) cast would turn into 1) counts as 0, which
// finds nothing. The fee's ID comes from the link on GET and from the form's hidden field on POST.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fee_id = is_string($_POST['fee_id'] ?? null) && ctype_digit($_POST['fee_id']) ? (int) $_POST['fee_id'] : 0;
} else {
    $fee_id = is_string($_GET['fee_id'] ?? null) && ctype_digit($_GET['fee_id']) ? (int) $_GET['fee_id'] : 0;
}
// The fees as typed, so a refused one shows again in its field (each is checked and turned into a number on submit)
// A fee posted as anything but text (such as an array) counts as empty, as the login form's fields do
$new_initial_fee = is_string($_POST['new_initial_fee'] ?? '0') ? trim($_POST['new_initial_fee'] ?? '0') : '';
$new_renewal_fee = is_string($_POST['new_renewal_fee'] ?? '0') ? trim($_POST['new_renewal_fee'] ?? '0') : '';
$new_misc_fee = is_string($_POST['new_misc_fee'] ?? '0') ? trim($_POST['new_misc_fee'] ?? '0') : '';
$new_currency_id = is_string($_POST['new_currency_id'] ?? null) && ctype_digit($_POST['new_currency_id'])
    ? (int) $_POST['new_currency_id'] : 0;
$has_errors = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

}

// The SSL provider and type come from the fee's own row, never the request, so the page always shows (and saves) the
// fee it was opened for
$stmt = $pdo->prepare("
    SELECT ssl_provider_id, type_id, initial_fee, renewal_fee, misc_fee, currency_id
    FROM ssl_fees
    WHERE id = :fee_id");
$stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
$stmt->execute();
$fee_row = $stmt->fetch();
$stmt->closeCursor();

if (!$fee_row) {

    // a bad link, or a fee deleted since the form was opened
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
    header("Location: ../ssl-providers.php");
    exit;

}

$sslpid = (int) $fee_row->ssl_provider_id;
$new_type_id = (int) $fee_row->type_id;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Initial and Renewal are required (marked with an asterisk), Misc is optional
    $fee_fields = array('new_initial_fee' => array($new_initial_fee, true), 'new_renewal_fee' => array($new_renewal_fee, true),
        'new_misc_fee' => array($new_misc_fee, false));

    foreach ($fee_fields as $fee_field => $fee_check) {

        $fee_error = $validate->feeError($fee_check[0], $fee_check[1]);
        if ($fee_error !== '') {
            $form->addFieldError($fee_field, $fee_error);
            $has_errors = true;
        }

    }

    // The currency has to be one in the list, since the fee is saved with its ID (the empty choice, shown when the
    // saved currency isn't in the list, and a hand-made request can both send one that isn't)
    $stmt = $pdo->prepare("
        SELECT id
        FROM currencies
        WHERE id = :new_currency_id");
    $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
    $stmt->execute();
    $currency_found = $stmt->fetchColumn() !== false;
    $stmt->closeCursor();

    if (!$currency_found) {

        $form->addFieldError('new_currency_id', _('Choose the Currency'));
        $has_errors = true;
        // the fee's saved currency shows again, rather than the refused one
        $new_currency_id = (int) $fee_row->currency_id;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$has_errors) {

    $new_initial_fee = (float) $new_initial_fee;
    $new_renewal_fee = (float) $new_renewal_fee;
    $new_misc_fee = (float) $new_misc_fee;

    try {

        $pdo->beginTransaction();

        $timestamp = $time->stamp();

        // Lock the fee's row and read it again, so a fee deleted since the form was opened is refused, and the
        // certificates are relinked by the SSL provider and type it has now
        $stmt = $pdo->prepare("
                SELECT ssl_provider_id, type_id
                FROM ssl_fees
                WHERE id = :fee_id
                FOR UPDATE");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();
        $locked_fee = $stmt->fetch();
        $stmt->closeCursor();

        if (!$locked_fee) {

            if ($pdo->InTransaction()) $pdo->rollback();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
            header("Location: ../ssl-providers.php");
            exit;

        }

        $sslpid = (int) $locked_fee->ssl_provider_id;
        $new_type_id = (int) $locked_fee->type_id;

        $stmt = $pdo->prepare("
                UPDATE ssl_fees
                SET initial_fee = :new_initial_fee,
                    renewal_fee = :new_renewal_fee,
                    misc_fee = :new_misc_fee,
                    currency_id = :new_currency_id,
                    update_time = :timestamp
                WHERE id = :fee_id");
        $stmt->bindValue('new_initial_fee', strval($new_initial_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_renewal_fee', strval($new_renewal_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_misc_fee', strval($new_misc_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET fee_id = :fee_id,
                    update_time = :timestamp
                WHERE ssl_provider_id = :sslpid
                  AND type_id = :new_type_id");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('sslpid', $sslpid, PDO::PARAM_INT);
        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
        $stmt->execute();

        $temp_type = $assets->getSslType($new_type_id);

        $stmt = $pdo->prepare("
                UPDATE ssl_certs sslc
                JOIN ssl_fees sslf ON sslc.fee_id = sslf.id
                SET sslc.total_cost = sslf.renewal_fee + sslf.misc_fee
                WHERE sslc.fee_id = :fee_id");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();

        $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);

        if ($pdo->InTransaction()) $pdo->commit();

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to update fee';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

    // The save links every certificate with this SSL provider and type, including ones that had no fee, so the missing
    // fees banner goes once the last one is linked
    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('ssl_certs');
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

    $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been updated.'), $temp_type) . '<BR>';

    header("Location: ../ssl-provider-fees.php?sslpid=" . $sslpid);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $new_initial_fee = $fee_row->initial_fee;
    $new_renewal_fee = $fee_row->renewal_fee;
    $new_misc_fee = $fee_row->misc_fee;
    $new_currency_id = (int) $fee_row->currency_id;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $layout->factList(array(
    array('label' => _('SSL Provider'), 'value' => $unsanitize->text($assets->getSslProvider($sslpid))),
    array('label' => _('SSL Type'), 'value' => $unsanitize->text($assets->getSslType($new_type_id)))));

// 11 characters fit the largest fee the fee columns hold (DECIMAL(10,2)), 99999999.99
echo $form->showInputText('new_initial_fee', _('Initial Fee'), '', $new_initial_fee, '11', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', _('Renewal Fee'), '', $new_renewal_fee, '11', '', '1', '', '');
echo $form->showInputText('new_misc_fee', _('Misc Fee'), '', $new_misc_fee, '11', '', '', '', '');

$result = $pdo->query("
    SELECT id, currency, `name`, symbol
    FROM currencies
    ORDER BY `name`")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_currency_id', _('Currency'), '', '', '');

    // A saved currency that isn't in the list (only a hand-made request could have saved one) selects an empty
    // option, so saving needs a currency chosen rather than quietly taking the first one
    if (!in_array($new_currency_id, array_map('intval', array_column($result, 'id')), true)) {

        echo $form->showDropdownOption('', _('Choose the Currency'), '');

    }

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->currency . ')', $new_currency_id);

    }

    echo $form->showDropdownBottom('', 'new_currency_id');

}

echo $form->showInputHidden('fee_id', $fee_id);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
