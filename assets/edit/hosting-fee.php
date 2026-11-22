<?php
/**
 * /assets/edit/hosting-fee.php
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
$log = new DomainMOD\Log('/assets/edit/hosting-fee.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$assets = new DomainMOD\Assets();
$conversion = new DomainMOD\Conversion();
$unsanitize = new DomainMOD\Unsanitize();
$validate = new DomainMOD\Validate();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-hosting-fee.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting fees to edit
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: ../index.php");
    exit;

}

// An ID sent as anything but digits (such as an array, which an (int) cast would turn into 1) counts as 0, which
// finds nothing. The fee's ID comes from the link on GET and from the form's hidden field on POST.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fee_id = is_string($_POST['fee_id'] ?? null) && ctype_digit($_POST['fee_id']) ? (int) $_POST['fee_id'] : 0;
} else {
    $fee_id = is_string($_GET['fee_id'] ?? null) && ctype_digit($_GET['fee_id']) ? (int) $_GET['fee_id'] : 0;
}
$del = is_string($_GET['del'] ?? null) && $_GET['del'] === '1' ? 1 : 0;
// The fees as typed, so a refused one shows again in its field (each is checked and turned into cents on submit)
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

// The provider, service type, and billing term come from the fee's own row, never the request, so the page always shows
// (and saves) the fee it was opened for. They're fixed once the fee is added, so services on it never end up on a fee
// for a different term.
$stmt = $pdo->prepare("
    SELECT hosting_id, type_id, billing_term, initial_fee, renewal_fee, misc_fee, currency_id
    FROM hosting_fees
    WHERE id = :fee_id");
$stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
$stmt->execute();
$fee_row = $stmt->fetch();
$stmt->closeCursor();

if (!$fee_row) {

    // a bad link, or a fee deleted since the form was opened
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
    header("Location: ../hosting.php");
    exit;

}

$hosting_id = (int) $fee_row->hosting_id;
$type_id = (int) $fee_row->type_id;
$billing_term = (int) $fee_row->billing_term;
// The fee's service type and billing term, such as "Shared Pro (12 Months)", for the messages
$fee_label = $assets->getHostingType($type_id) . ' (' . DomainMOD\Hosting::termLabel($billing_term) . ')';

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    /*
     * Only a fee no hosting service uses can be deleted, so no service's cost ever changes. The delete holds the hosting
     * lock, taken before its transaction, so no service can be given the fee between the check and the delete.
     */
    if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
        header("Location: hosting-fee.php?hosting_id=" . $hosting_id . "&fee_id=" . $fee_id);
        exit;

    }

    // '' until it's done, 'deleted', 'refused' (with $history_refused), or 'gone' when the fee was already deleted
    $outcome = '';
    $history_refused = '';

    try {

        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $pdo->beginTransaction();

        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'],
            'hosting_fee_delete', DomainMOD\ChangeHistory::feeNote($pdo, 'hosting', $hosting_id, $type_id, $billing_term), 'fee');

        // The services on this fee, counted with a locking read through the fee_id key (services before fees, the lock
        // order every writer uses)
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM hosting_services FORCE INDEX (fee_id)
            WHERE fee_id = :fee_id
            FOR UPDATE");
        $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
        $stmt->execute();
        $history_refused = DomainMOD\HostingFee::deleteRefusal($stmt->fetchColumn());
        $stmt->closeCursor();

        // The fee's services captured for the Change History, as every fee delete does (there are none, since the delete
        // is refused while any service uses the fee, so nothing is recorded)
        $history_costs = $history_refused === '' ? $history_op->captureCosts('hosting', " AND i.fee_id = :history_fee_id", array('history_fee_id' => $fee_id)) : null;

        if ($history_refused !== '') {

            if ($pdo->InTransaction()) $pdo->rollback();
            $outcome = 'refused';

        } else {

            $stmt = $pdo->prepare("
                SELECT id
                FROM hosting_fees
                WHERE id = :fee_id
                FOR UPDATE");
            $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
            $stmt->execute();
            $fee_found = $stmt->fetchColumn() !== false;
            $stmt->closeCursor();

            if ($fee_found) {

                $stmt = $pdo->prepare("
                    DELETE FROM hosting_fees
                    WHERE id = :fee_id");
                $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
                $stmt->execute();

            }

            $history_op->compareCosts($history_costs);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $outcome = $fee_found ? 'deleted' : 'gone';

        }

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete hosting fee';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
        if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    } finally {

        // The action goes (and lets go of anything it holds) before the hosting lock does
        $history_op = null;
        DomainMOD\Hosting::unlock($pdo);

    }

    if ($outcome === 'refused') {

        $_SESSION['s_message_danger'] .= $history_refused . '<BR>';
        header("Location: hosting-fee.php?hosting_id=" . $hosting_id . "&fee_id=" . $fee_id);
        exit;

    }

    if ($outcome === 'gone') {

        $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
        header("Location: ../hosting-fees.php?hosting_id=" . $hosting_id);
        exit;

    }

    // No service used the fee, so none is missing one now that didn't before, but the warning is worked out again as
    // after every fee change
    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('hosting_services');
    $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

    $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been deleted.'), $fee_label) . '<BR>';

    header("Location: ../hosting-fees.php?hosting_id=" . $hosting_id);
    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Initial and Renewal are required (marked with an asterisk), Misc is optional
    $fee_fields = array('new_initial_fee' => array($new_initial_fee, true), 'new_renewal_fee' => array($new_renewal_fee, true),
        'new_misc_fee' => array($new_misc_fee, false));
    $fee_errors = false;

    foreach ($fee_fields as $fee_field => $fee_check) {

        $fee_error = $validate->feeError($fee_check[0], $fee_check[1]);
        if ($fee_error !== '') {
            $form->addFieldError($fee_field, $fee_error);
            $has_errors = true;
            $fee_errors = true;
        }

    }

    // Each amount as whole cents, and a service's cost (renewal plus misc, or initial plus misc) has to fit as the
    // amounts do. The decimal strings saved are made from these same cents, so what's checked is what's stored.
    if (!$fee_errors) {

        $initial_cents = DomainMOD\HostingFee::toCents($new_initial_fee);
        $renewal_cents = DomainMOD\HostingFee::toCents($new_renewal_fee);
        $misc_cents = DomainMOD\HostingFee::toCents($new_misc_fee);

        foreach (DomainMOD\HostingFee::totalErrors($initial_cents, $renewal_cents, $misc_cents) as $fee_field => $fee_error) {
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

    /*
     * The save holds the hosting lock (see DomainMOD\Hosting::lock()), taken before its transaction, so the fee and its
     * services can't change before it commits
     */
    if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

    } else {

        // '' until it's done, 'saved', or 'gone' when the fee was deleted since the form was opened
        $outcome = '';

        try {

            $timestamp = $time->stamp();
            $total_cost = DomainMOD\HostingFee::fromCents($renewal_cents + $misc_cents);

            // The fee's keys as they are now (a plain read is enough, since the hosting lock keeps them from changing)
            $stmt = $pdo->prepare("
                SELECT hosting_id, type_id, billing_term
                FROM hosting_fees
                WHERE id = :fee_id");
            $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
            $stmt->execute();
            $history_keys = $stmt->fetch();
            $stmt->closeCursor();

            if (!$history_keys) {

                $outcome = 'gone';

            } else {

                $hosting_id = (int) $history_keys->hosting_id;
                $type_id = (int) $history_keys->type_id;
                $billing_term = (int) $history_keys->billing_term;

                // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                $pdo->beginTransaction();

                /*
                 * The change is one Change History action (see DomainMOD\ChangeHistory::feeScope()). Every service it can
                 * change is captured and locked first, from the fee's keys, and only then the fee row below, so services are
                 * always locked before fees, as every other writer does.
                 */
                $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET, $_SESSION['s_user_id'],
                    'hosting_fee_edit', DomainMOD\ChangeHistory::feeNote($pdo, 'hosting', $hosting_id, $type_id, $billing_term), 'fee');
                $history_costs = $history_op->captureCosts('hosting', DomainMOD\ChangeHistory::feeScope('hosting', $hosting_id, $type_id, $fee_id, $billing_term));

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM hosting_fees
                    WHERE id = :fee_id
                    FOR UPDATE");
                $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt->closeCursor();

                $stmt = $pdo->prepare("
                    UPDATE hosting_fees
                    SET initial_fee = :new_initial_fee,
                        renewal_fee = :new_renewal_fee,
                        misc_fee = :new_misc_fee,
                        currency_id = :new_currency_id,
                        update_time = :timestamp
                    WHERE id = :fee_id");
                $stmt->bindValue('new_initial_fee', DomainMOD\HostingFee::fromCents($initial_cents), PDO::PARAM_STR);
                $stmt->bindValue('new_renewal_fee', DomainMOD\HostingFee::fromCents($renewal_cents), PDO::PARAM_STR);
                $stmt->bindValue('new_misc_fee', DomainMOD\HostingFee::fromCents($misc_cents), PDO::PARAM_STR);
                $stmt->bindValue('new_currency_id', $new_currency_id, PDO::PARAM_INT);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
                $stmt->execute();

                // Every service with this provider, service type, and billing term is linked to the fee (including ones that
                // had none), with its cost set together
                $stmt = $pdo->prepare("
                    UPDATE hosting_services
                    SET fee_id = :fee_id,
                        total_cost = :total_cost,
                        fee_fixed = '1',
                        revision = revision + 1,
                        update_time = :timestamp
                    WHERE hosting_id = :hosting_id
                      AND type_id = :type_id
                      AND billing_term = :billing_term");
                $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
                $stmt->bindValue('total_cost', $total_cost, PDO::PARAM_STR);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                $stmt->bindValue('type_id', $type_id, PDO::PARAM_INT);
                $stmt->bindValue('billing_term', $billing_term, PDO::PARAM_INT);
                $stmt->execute();

                // Any other service on this fee takes its new cost too, as on the SSL fee page
                $stmt = $pdo->prepare("
                    UPDATE hosting_services
                    SET total_cost = :total_cost,
                        revision = revision + 1,
                        update_time = :timestamp
                    WHERE fee_id = :fee_id
                      AND (hosting_id <> :hosting_id OR type_id <> :type_id OR billing_term <> :billing_term)");
                $stmt->bindValue('total_cost', $total_cost, PDO::PARAM_STR);
                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                $stmt->bindValue('fee_id', $fee_id, PDO::PARAM_INT);
                $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                $stmt->bindValue('type_id', $type_id, PDO::PARAM_INT);
                $stmt->bindValue('billing_term', $billing_term, PDO::PARAM_INT);
                $stmt->execute();

                $history_op->compareCosts($history_costs);
                $history_op->finish();

                if ($pdo->InTransaction()) $pdo->commit();

                $outcome = 'saved';

            }

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            $log_message = 'Unable to update fee';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
            if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        } finally {

            // The action goes (and lets go of anything it holds) before the hosting lock does
            $history_op = null;
            DomainMOD\Hosting::unlock($pdo);

        }

        if ($outcome === 'gone') {

            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That fee no longer exists.') . '<BR>';
            header("Location: ../hosting.php");
            exit;

        }

        // The save links every service with this provider, service type, and billing term, including ones that had no fee,
        // so the missing fees warning goes once the last one is linked
        $queryB = new DomainMOD\QueryBuild();

        $sql = $queryB->missingFees('hosting_services');
        $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

        /*
         * A rate for a currency no fee used before, added only now that the fee is saved and the hosting lock is let go
         * of, since it can be a network lookup. A failure here leaves the fee saved, and the rate is added on a later
         * page load.
         */
        try {
            $conversion->ensureRates($_SESSION['s_user_id'], $_SESSION['s_default_currency']);
        } catch (Throwable $e) {
            $log->warning('Unable to add a conversion rate after saving a hosting fee', array('Error' => $e->getMessage()));
        }

        $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been updated.'), $fee_label) . '<BR>';

        header("Location: ../hosting-fees.php?hosting_id=" . $hosting_id);
        exit;

    }

} elseif ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $new_initial_fee = $fee_row->initial_fee;
    $new_renewal_fee = $fee_row->renewal_fee;
    $new_misc_fee = $fee_row->misc_fee;
    $new_currency_id = (int) $fee_row->currency_id;

}
?>
<?php
// The hosting provider's name (stored HTML-escaped), for the breadcrumb and the summary below
$provider_name = $assets->getHost($hosting_id);
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
    array('label' => _('Hosting Provider'), 'value' => $unsanitize->text($provider_name)),
    array('label' => _('Service Type'), 'value' => $unsanitize->text($assets->getHostingType($type_id))),
    array('label' => _('Billing Term'), 'value' => DomainMOD\Hosting::termLabel($billing_term))));

// 11 characters fit the largest fee the fee columns hold (DECIMAL(10,2)), 99999999.99
echo $form->showInputText('new_initial_fee', _('Initial Fee'), _('For your records. It isn\'t counted in costs.'), $new_initial_fee, '11', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', _('Renewal Fee'), _('The fee for each billing term.'), $new_renewal_fee, '11', '', '1', '', '');
echo $form->showInputText('new_misc_fee', _('Misc Fee'), _('Added to every renewal, such as a control panel license.'), $new_misc_fee, '11', '', '', '', '');

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

$layout->dangerZone(_('Fee'), $fee_label, 'hosting-fee.php?hosting_id=' . $hosting_id . '&fee_id=' . $fee_id . '&del=1', _('Delete this fee'), sprintf(_('This removes the fee for %s. It can only be deleted when no hosting services use it. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($fee_label, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>'), 2);
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
