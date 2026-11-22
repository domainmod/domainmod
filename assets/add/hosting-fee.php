<?php
/**
 * /assets/add/hosting-fee.php
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
$log = new DomainMOD\Log('/assets/add/hosting-fee.php');
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
require_once DIR_INC . '/settings/assets-add-hosting-fee.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to add to
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: ../index.php");
    exit;

}

// An ID sent as anything but digits (such as an array, which an (int) cast would turn into 1) counts as 0, which
// finds nothing
$hosting_id = is_string($_REQUEST['hosting_id'] ?? null) && ctype_digit($_REQUEST['hosting_id']) ? (int) $_REQUEST['hosting_id'] : 0;
// Opened from the Hosting Fees report's Missing Fees, the page goes back there once the fee is added (the only return
// it accepts)
$return = ($_REQUEST['return'] ?? '') === 'missing' ? 'missing' : '';
// The link's service type and billing term (from a missing fee) are only chosen when the form first shows. Once it's
// posted, the ones chosen in the form are the ones saved, even though the form's address still carries the link's.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_type_id = is_string($_POST['new_type_id'] ?? null) && ctype_digit($_POST['new_type_id'])
        ? (int) $_POST['new_type_id'] : 0;
    $new_billing_term = is_string($_POST['new_billing_term'] ?? null) && ctype_digit($_POST['new_billing_term'])
        ? (int) $_POST['new_billing_term'] : 0;
} else {
    $new_type_id = is_string($_GET['type_id'] ?? null) && ctype_digit($_GET['type_id']) ? (int) $_GET['type_id'] : 0;
    $new_billing_term = is_string($_GET['billing_term'] ?? null) && ctype_digit($_GET['billing_term']) ? (int) $_GET['billing_term'] : 0;
}
// The fees as typed, so a refused one shows again in its field (each is checked and turned into cents on submit)
// A fee posted as anything but text (such as an array) counts as empty, as the login form's fields do
$new_initial_fee = is_string($_POST['new_initial_fee'] ?? '0') ? trim($_POST['new_initial_fee'] ?? '0') : '';
$new_renewal_fee = is_string($_POST['new_renewal_fee'] ?? '0') ? trim($_POST['new_renewal_fee'] ?? '0') : '';
$new_misc_fee = is_string($_POST['new_misc_fee'] ?? '0') ? trim($_POST['new_misc_fee'] ?? '0') : '';
// A currency posted as anything but text (such as an array) counts as empty, like the fees above
$new_currency = is_string($_POST['new_currency'] ?? null) ? $sanitize->text($_POST['new_currency']) : '';

// The fee is added under this hosting provider, so it has to exist (a bad link, or a provider deleted since the
// form was opened, adds nothing)
$stmt = $pdo->prepare("
    SELECT id
    FROM hosting
    WHERE id = :hosting_id");
$stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
$stmt->execute();
$provider_found = $stmt->fetchColumn() !== false;
$stmt->closeCursor();

if (!$provider_found) {

    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That hosting provider no longer exists.') . '<BR>';
    header("Location: ../hosting.php");
    exit;

}

// The message for a fee that already exists, with a link to edit it
$exists_message = function ($fee_id) use ($hosting_id) {
    return _('A fee for this service type and billing term already exists.') . ' [<a href=\'' . WEB_ROOT .
        '/assets/edit/hosting-fee.php?hosting_id=' . $hosting_id . '&fee_id=' . urlencode($fee_id) .
        '\'>' . DomainMOD\Format::toLower(_('Edit Fee')) . '</a>]<BR>';
};

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Initial and Renewal are required (marked with an asterisk), Misc is optional
    $fee_fields = array('new_initial_fee' => array($new_initial_fee, true), 'new_renewal_fee' => array($new_renewal_fee, true),
        'new_misc_fee' => array($new_misc_fee, false));
    $has_errors = false;

    // The billing term has to be one of the allowed ones, since services are matched to the fee by it
    if (!DomainMOD\Hosting::validTerm($new_billing_term)) {

        $form->addFieldError('new_billing_term', _('Choose the Billing Term'));
        $has_errors = true;

    }

    // The currency has to be one in the list (only a hand-made request can send another), since the fee is saved
    // with its ID
    $stmt = $pdo->prepare("
        SELECT id
        FROM currencies
        WHERE currency = :new_currency");
    $stmt->bindValue('new_currency', $new_currency, PDO::PARAM_STR);
    $stmt->execute();
    $currency_id = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($currency_id === false) {

        $form->addFieldError('new_currency', _('Choose the Currency'));
        $has_errors = true;

    }

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

    // The service type is checked under the hosting lock below, which its delete holds too
    if ($new_type_id === 0) {

        $form->addFieldError('new_type_id', _('Choose the Service Type'));
        $has_errors = true;

    }

    if (!$has_errors) {

        $timestamp = $time->stamp();

        // '' while the form is shown again, 'added' once it's added, 'exists' (with $existing_fee_id) when the fee is
        // already there, or 'gone' when the provider was deleted meanwhile
        $outcome = '';
        $existing_fee_id = 0;

        /*
         * The add holds the hosting lock (see DomainMOD\Hosting::lock()), taken before its transaction, so the provider
         * and service type it checks can't be deleted, and the same fee can't be added by anyone else, before it commits
         */
        if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            try {

                $stmt = $pdo->prepare("
                    SELECT (SELECT COUNT(*) FROM hosting WHERE id = :hosting_id) AS provider_found,
                           (SELECT COUNT(*) FROM hosting_types WHERE id = :new_type_id) AS type_found");
                $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                $stmt->execute();
                $found = $stmt->fetch();
                $stmt->closeCursor();

                if ((int) $found->provider_found === 0) {

                    $outcome = 'gone';

                } elseif ((int) $found->type_found === 0) {

                    $form->addFieldError('new_type_id', _('Choose the Service Type'));

                } else {

                    // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                    $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                    $pdo->beginTransaction();

                    // The fee add is one Change History action, started before the fee row is inserted
                    $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_ASSET,
                        $_SESSION['s_user_id'], 'hosting_fee_add', DomainMOD\ChangeHistory::feeNote($pdo, 'hosting', $hosting_id, $new_type_id, $new_billing_term), 'fee');

                    // Checked under the lock, so two adds of the same fee can't both insert one (the unique key is the
                    // backstop)
                    $existing_fee_id = DomainMOD\HostingFee::findId($pdo, $hosting_id, $new_type_id, $new_billing_term, 'LOCK IN SHARE MODE');

                    if ($existing_fee_id) {

                        if ($pdo->InTransaction()) $pdo->rollback();
                        $outcome = 'exists';

                    } else {

                        $total_cost = DomainMOD\HostingFee::fromCents($renewal_cents + $misc_cents);

                        $stmt = $pdo->prepare("
                            INSERT INTO hosting_fees
                            (hosting_id, type_id, billing_term, initial_fee, renewal_fee, misc_fee, currency_id, insert_time)
                            VALUES
                            (:hosting_id, :new_type_id, :new_billing_term, :new_initial_fee, :new_renewal_fee, :new_misc_fee,
                             :currency_id, :timestamp)");
                        $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_billing_term', $new_billing_term, PDO::PARAM_INT);
                        $stmt->bindValue('new_initial_fee', DomainMOD\HostingFee::fromCents($initial_cents), PDO::PARAM_STR);
                        $stmt->bindValue('new_renewal_fee', DomainMOD\HostingFee::fromCents($renewal_cents), PDO::PARAM_STR);
                        $stmt->bindValue('new_misc_fee', DomainMOD\HostingFee::fromCents($misc_cents), PDO::PARAM_STR);
                        $stmt->bindValue('currency_id', (int) $currency_id, PDO::PARAM_INT);
                        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                        $stmt->execute();

                        $new_fee_id = (int) $pdo->lastInsertId('id');

                        /*
                         * The change is one Change History action (see DomainMOD\ChangeHistory::feeScope()): the services the
                         * new fee is given to (its provider, service type, and billing term), and any old service still pointing
                         * at a deleted fee whose id the new row reuses, captured and locked now. No fee row had this id before
                         * the insert, so its services had no prior currency.
                         */
                        $history_op->setFeeCurrencies('hosting', array($new_fee_id => ''));
                        $history_costs = $history_op->captureCosts('hosting', DomainMOD\ChangeHistory::feeScope('hosting', $hosting_id, $new_type_id, $new_fee_id, $new_billing_term));

                        // The fee's services get it, with their cost (one term's renewal fee plus misc fee) set together
                        $stmt = $pdo->prepare("
                            UPDATE hosting_services
                            SET fee_id = :new_fee_id,
                                total_cost = :total_cost,
                                fee_fixed = '1',
                                revision = revision + 1,
                                update_time = :timestamp
                            WHERE hosting_id = :hosting_id
                              AND type_id = :new_type_id
                              AND billing_term = :new_billing_term");
                        $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                        $stmt->bindValue('total_cost', $total_cost, PDO::PARAM_STR);
                        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                        $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_billing_term', $new_billing_term, PDO::PARAM_INT);
                        $stmt->execute();

                        // Any other service already on this fee's id takes its cost too, as on the SSL fee page
                        $stmt = $pdo->prepare("
                            UPDATE hosting_services
                            SET total_cost = :total_cost,
                                revision = revision + 1,
                                update_time = :timestamp
                            WHERE fee_id = :new_fee_id
                              AND (hosting_id <> :hosting_id OR type_id <> :new_type_id OR billing_term <> :new_billing_term)");
                        $stmt->bindValue('total_cost', $total_cost, PDO::PARAM_STR);
                        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                        $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                        $stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                        $stmt->bindValue('new_billing_term', $new_billing_term, PDO::PARAM_INT);
                        $stmt->execute();

                        $history_op->compareCosts($history_costs);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $outcome = 'added';

                    }

                }

            } catch (PDOException $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                // Another add of the same fee got in first (the unique key), so it's offered for editing
                if ($e->getCode() === '23000') {

                    $existing_fee_id = DomainMOD\HostingFee::findId($pdo, $hosting_id, $new_type_id, $new_billing_term);
                    $outcome = 'exists';

                } else {

                    $log_message = 'Unable to add hosting fee';
                    $log_extra = array('Error' => $e);
                    $log->critical($log_message, $log_extra);

                    // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
                    if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                    throw $e;

                }

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                $log_message = 'Unable to add hosting fee';
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

        }

        if ($outcome === 'gone') {

            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That hosting provider no longer exists.') . '<BR>';
            header("Location: ../hosting.php");
            exit;

        }

        if ($outcome === 'exists') {

            $_SESSION['s_message_danger'] .= $exists_message($existing_fee_id);

        }

        if ($outcome === 'added') {

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('hosting_services');
            $_SESSION['s_missing_hosting_fees'] = $system->checkForRows($sql);

            /*
             * A rate for a currency no fee used before, added only now that the fee is saved and the hosting lock is let
             * go of, since it can be a network lookup. A failure here leaves the fee saved, and the rate is added on a
             * later page load.
             */
            try {
                $conversion->ensureRates($_SESSION['s_user_id'], $_SESSION['s_default_currency']);
            } catch (Throwable $e) {
                $log->warning('Unable to add a conversion rate after adding a hosting fee', array('Error' => $e->getMessage()));
            }

            $_SESSION['s_message_success'] .= sprintf(_('The fee for %s has been added.'),
                $assets->getHostingType($new_type_id) . ' (' . DomainMOD\Hosting::termLabel($new_billing_term) . ')') . '<BR>';

            if ($return === 'missing') {
                // Back to the missing fees while any are left, otherwise to the top of the report
                header("Location: ../../reporting/hosting/provider-fees.php?all=0" . ($_SESSION['s_missing_hosting_fees'] == 1 ? '#missing' : ''));
            } else {
                header("Location: ../hosting-fees.php?hosting_id=" . $hosting_id);
            }
            exit;

        }

    }

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
    array('label' => _('Hosting Provider'), 'value' => $unsanitize->text($provider_name))));

echo $form->showDropdownTop('new_type_id', _('Service Type'), '', '1', '');
echo $form->showDropdownOption('', _('Choose a Service Type'), '');
$result = $pdo->query("
    SELECT id, type
    FROM hosting_types
    ORDER BY `type`")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->type, $new_type_id !== 0 ? $new_type_id : ($_SESSION['s_default_hosting_type'] ?? ''));

}
echo $form->showDropdownBottom('', 'new_type_id');

echo $form->showDropdownTop('new_billing_term', _('Billing Term'), '', '1', '');
echo $form->showDropdownOption('', _('Choose a Billing Term'), '');

foreach (DomainMOD\Hosting::termOptions() as $term => $label) {

    echo $form->showDropdownOption($term, $label, $new_billing_term);

}
echo $form->showDropdownBottom('', 'new_billing_term');

// 11 characters fit the largest fee the fee columns hold (DECIMAL(10,2)), 99999999.99
echo $form->showInputText('new_initial_fee', _('Initial Fee'), _('For your records. It isn\'t counted in costs.'), $new_initial_fee, '11', '', '1', '', '');
echo $form->showInputText('new_renewal_fee', _('Renewal Fee'), _('The fee for each billing term.'), $new_renewal_fee, '11', '', '1', '', '');
echo $form->showInputText('new_misc_fee', _('Misc Fee'), _('Added to every renewal, such as a control panel license.'), $new_misc_fee, '11', '', '', '', '');

echo $form->showDropdownTop('new_currency', _('Currency'), '', '', '');
$result = $pdo->query("
    SELECT id, currency, `name`, symbol
    FROM currencies
    ORDER BY `name`")->fetchAll();

foreach ($result as $row) {

    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ')',
        $new_currency !== '' ? $new_currency : ($_SESSION['s_default_currency'] ?? '')); // '' until the form is posted

}
echo $form->showDropdownBottom('', 'new_currency');


echo $form->showSubmitButton(_('Add Fee'), '', '');
echo $form->showInputHidden('hosting_id', $hosting_id);
if ($return !== '') echo $form->showInputHidden('return', $return);
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
