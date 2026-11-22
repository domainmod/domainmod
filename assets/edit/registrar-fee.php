<?php
/**
 * /assets/edit/registrar-fee.php
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
$log = new DomainMOD\Log('/assets/edit/registrar-fee.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$conversion = new DomainMOD\Conversion();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-registrar-fee.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
$timestamp = $time->stamp();

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
$new_transfer_fee = is_string($_POST['new_transfer_fee'] ?? '0') ? trim($_POST['new_transfer_fee'] ?? '0') : '';
$new_privacy_fee = is_string($_POST['new_privacy_fee'] ?? '0') ? trim($_POST['new_privacy_fee'] ?? '0') : '';
$new_misc_fee = is_string($_POST['new_misc_fee'] ?? '0') ? trim($_POST['new_misc_fee'] ?? '0') : '';
$new_currency_id = is_string($_POST['new_currency_id'] ?? null) && ctype_digit($_POST['new_currency_id'])
    ? (int) $_POST['new_currency_id'] : 0;
$has_errors = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

}

// The registrar and TLD come from the fee's own row, never the request, so the page always shows (and saves) the fee
// it was opened for
$stmt = $pdo->prepare("
    SELECT registrar_id, tld, initial_fee, renewal_fee, transfer_fee, privacy_fee, misc_fee, currency_id
    FROM fees
    WHERE id = :fee_id");
$stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
$stmt->execute();
$fee_row = $stmt->fetch();
$stmt->closeCursor();

if (!$fee_row) {

    // a bad link, or a fee deleted since the form was opened
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
    header("Location: ../registrars.php");
    exit;

}

$rid = (int) $fee_row->registrar_id;
$new_tld = $fee_row->tld;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Initial, Renewal, and Transfer are required (marked with an asterisk), Privacy and Misc are optional
    $fee_fields = array('new_initial_fee' => array($new_initial_fee, true), 'new_renewal_fee' => array($new_renewal_fee, true),
        'new_transfer_fee' => array($new_transfer_fee, true), 'new_privacy_fee' => array($new_privacy_fee, false),
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
    $new_transfer_fee = (float) $new_transfer_fee;
    $new_privacy_fee = (float) $new_privacy_fee;
    $new_misc_fee = (float) $new_misc_fee;

    try {

        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $pdo->beginTransaction();

        /*
         * The change is one Change History action (see DomainMOD\ChangeHistory::feeScope()). Every item it can change is
         * captured and locked first, from the fee's keys as they are now, and only then the fee row below, so items are
         * always locked before fees, as every other writer does. If the locked fee no longer has the same keys, nothing is
         * saved.
         */
        $stmt = $pdo->prepare("
                SELECT registrar_id, tld
                FROM fees
                WHERE id = :fee_id");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();
        $history_keys = $stmt->fetch();
        $stmt->closeCursor();
        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'],
            'registrar_fee_edit', $history_keys ? DomainMOD\ChangeHistory::feeNote($pdo, 'domain', $history_keys->registrar_id, $history_keys->tld) : '', 'fee');
        $history_costs = null;
        if ($history_keys) {
            // One fee change at a time, so two fee pages never deadlock over items in each other's groups
            $history_op->holdNamedLock('domainmod_fee_changes_' . substr(md5(DB_NAME), 0, 12));
            $history_costs = $history_op->captureCosts('domain', DomainMOD\ChangeHistory::feeScope('domain', $history_keys->registrar_id, $history_keys->tld, $fee_id));
        }

        // Lock the fee's row and read it again, so a fee deleted since the form was opened is refused, and the
        // domains are relinked by the registrar and TLD it has now
        $stmt = $pdo->prepare("
                SELECT registrar_id, tld
                FROM fees
                WHERE id = :fee_id
                FOR UPDATE");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();
        $locked_fee = $stmt->fetch();
        $stmt->closeCursor();

        if (!$locked_fee) {

            if ($pdo->InTransaction()) $pdo->rollback();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
            header("Location: ../registrars.php");
            exit;

        }

        $rid = (int) $locked_fee->registrar_id;
        $new_tld = $locked_fee->tld;

        if (!$history_keys || (string) $history_keys->registrar_id !== (string) $locked_fee->registrar_id
            || (string) $history_keys->tld !== (string) $locked_fee->tld) {

            if ($pdo->InTransaction()) $pdo->rollback();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The fee changed while it was being saved. Please try again.') . '<BR>';
            header("Location: ../registrars.php");
            exit;

        }

        $stmt = $pdo->prepare("
                UPDATE fees
                SET initial_fee = :new_initial_fee,
                    renewal_fee = :new_renewal_fee,
                    transfer_fee = :new_transfer_fee,
                    privacy_fee =:new_privacy_fee,
                    misc_fee = :new_misc_fee,
                    currency_id = :new_currency_id,
                    update_time = :timestamp
                WHERE id = :fee_id");
        $stmt->bindValue('new_initial_fee', strval($new_initial_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_renewal_fee', strval($new_renewal_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_transfer_fee', strval($new_transfer_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_privacy_fee', strval($new_privacy_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_misc_fee', strval($new_misc_fee), PDO::PARAM_STR);
        $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
                UPDATE domains
                SET fee_id = :fee_id,
                    update_time = :timestamp
                WHERE registrar_id = :rid
                  AND tld = :new_tld");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('rid', $rid, PDO::PARAM_INT);
        $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
        $stmt->execute();

        $stmt = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                WHERE d.privacy = '1'
                  AND d.fee_id = :fee_id");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
                UPDATE domains d
                JOIN fees f ON d.fee_id = f.id
                SET d.total_cost = f.renewal_fee + f.misc_fee
                WHERE d.privacy = '0'
                  AND d.fee_id = :fee_id");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();

        $conversion->updateRates($_SESSION['s_default_currency'], $_SESSION['s_user_id']);


        $history_op->compareCosts($history_costs);
        $history_op->finish();

        if ($pdo->InTransaction()) $pdo->commit();

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to update registrar fee';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
        if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

    // The save links every domain with this registrar and TLD, including ones that had no fee, so the missing fees
    // banner goes once the last one is linked
    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('domains');
    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

    $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been updated.'), $new_tld) . '<BR>';

    header("Location: ../registrar-fees.php?rid=" . $rid);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $new_initial_fee = $fee_row->initial_fee;
    $new_renewal_fee = $fee_row->renewal_fee;
    $new_transfer_fee = $fee_row->transfer_fee;
    $new_privacy_fee = $fee_row->privacy_fee;
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
    array('label' => _('Domain Registrar'), 'value' => $unsanitize->text($assets->getRegistrar($rid))),
    array('label' => _('TLD'), 'value' => '.' . $unsanitize->text($new_tld), 'ltr' => true)));

// 11 characters fit the largest fee the fee columns hold (DECIMAL(10,2)), 99999999.99
echo $form->showInputText('new_initial_fee', _('Initial Fee'), '', $new_initial_fee, '11', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', _('Renewal Fee'), '', $new_renewal_fee, '11', '', '1', '', '');
echo $form->showInputText('new_transfer_fee', _('Transfer Fee'), '', $new_transfer_fee, '11', '', '1', '', '');
echo $form->showInputText('new_privacy_fee', _('Privacy Fee'), '', $new_privacy_fee, '11', '', '', '', '');
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
