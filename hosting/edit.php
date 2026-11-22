<?php
/**
 * /hosting/edit.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/hosting/edit.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$date = new DomainMOD\Date();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/hosting-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables, so there's nothing to edit yet
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);

$del = (int) ($_GET['del'] ?? 0);

$hsid = DomainMOD\Hosting::postedInt($_REQUEST['hsid'] ?? null);
$new_name = is_string($_POST['new_name'] ?? null) ? $sanitize->text(trim($_POST['new_name'])) : '';
$new_account_id = DomainMOD\Hosting::postedInt($_POST['new_account_id'] ?? null);
$new_type_id = DomainMOD\Hosting::postedInt($_POST['new_type_id'] ?? null);
$new_billing_term = is_scalar($_POST['new_billing_term'] ?? null) ? (string) $_POST['new_billing_term'] : '';
$new_active = DomainMOD\Hosting::postedInt($_POST['new_active'] ?? null, -1);
$new_expiry_date = is_string($_POST['datepick'] ?? null) ? trim($_POST['datepick']) : '';
$new_renewal_day = DomainMOD\Hosting::postedInt($_POST['new_renewal_day'] ?? null, -1);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0) === 1 ? 1 : 0;
// The Renews On Day choice the form was opened with (see Hosting::renewalDayToSave())
$renewal_day_opened = DomainMOD\Hosting::postedInt($_POST['renewal_day_opened'] ?? null, -1);
$new_hostname = is_string($_POST['new_hostname'] ?? null) ? $sanitize->text(trim($_POST['new_hostname'])) : '';
// The IP address is optional, and 0 is None, so a malformed value (-1) is told apart from None and refused
$new_ip_id = DomainMOD\Hosting::postedInt($_POST['new_ip_id'] ?? null, -1);
$new_cat_id = DomainMOD\Hosting::postedInt($_POST['new_cat_id'] ?? null);
$new_notes = isset($_POST['new_notes']) && is_string($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// The service's revision and insert time from when the form (or the Renew form, or the delete link) was drawn, so a
// form left open while the service changed, or a link to a service that was deleted and whose id was given to a new
// one, changes nothing (see the stale form message below)
$form_revision = DomainMOD\Hosting::postedInt($_POST['revision'] ?? null, -1);
$form_insert_time = is_string($_REQUEST['insert_time'] ?? null) ? $_REQUEST['insert_time'] : '';
$stale_message = _('This hosting service was changed since you opened it. Reload to see the changes.');
$gone_message = _('That hosting service no longer exists.');

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $hosting_available ? $custom_field->getFieldTypes('hosting_service_fields') : array();
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

// Reads the custom field list in share mode, the first lock each write takes (the Change History's lock order: the
// field definitions, then the hosting services), so a custom field can't be added or deleted until the write ends
$lock_custom_fields = function () use ($pdo) {
    $field_array = array();
    foreach ($pdo->query("
        SELECT field_name
        FROM hosting_service_fields
        ORDER BY `name`
        LOCK IN SHARE MODE")->fetchAll() as $row) {
        $field_array[] = $row->field_name;
    }
    return $field_array;
};

// After a write, whether any hosting service is missing its fee, for the maintenance warning (see checks.php)
$refresh_missing_fees = function () use ($system) {
    $_SESSION['s_missing_hosting_fees'] = $system->checkForRows((new DomainMOD\QueryBuild())->missingFees('hosting_services'));
};

/*
 * Mark Renewed: moves the renewal date forward one term from the current one, landing on the service's "Renews On Day"
 * (see DomainMOD\HostingRenewal, which takes the hosting lock and records it in the Change History). It has its own
 * small form, posted here and handled before the Save. It isn't idempotent, so the form carries the service's revision,
 * and a click sent twice (a double click, or a resend from the browser's history) or from a page drawn before another
 * change finds no matching row and changes nothing. Read-only users can't use it, and the bootstrap checks the form's
 * security token (see _includes/debug.inc.php).
 */
if ($hosting_available && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['renew_hosting'] ?? '') === '1') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $renew_location = 'edit.php?hsid=' . $hsid;

    try {

        $renewal = (new DomainMOD\HostingRenewal($pdo))->markRenewed($hsid, $form_revision, $form_insert_time, $_SESSION['s_user_id']);

        if ($renewal['status'] === 'renewed') {

            $refresh_missing_fees();

            $_SESSION['s_message_success'] .= sprintf(_('Hosting Service %1$s marked renewed. Its renewal date is now %2$s.'),
                $renewal['name'], $layout->ltr($renewal['date'])) . '<BR>';

        } elseif ($renewal['status'] === 'busy') {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } elseif ($renewal['status'] === 'gone') {

            $_SESSION['s_message_danger'] .= $gone_message . '<BR>';
            $renew_location = 'index.php';

        } elseif ($renewal['status'] === 'term') {

            $_SESSION['s_message_danger'] .= _("This hosting service's billing term isn't one DomainMOD knows, so it can't be renewed. Choose a billing term and save first.") . '<BR>';

        } elseif ($renewal['status'] === 'date') {

            $_SESSION['s_message_danger'] .= _("The renewal date can't be moved forward one term, since the new date wouldn't be a valid date.") . '<BR>';

        } else {

            $_SESSION['s_message_danger'] .= $stale_message . '<BR>';

        }

    } catch (Exception $e) {

        if (domainmod_restore_is_conflict($e)) {
            $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
        } else {

            $log_message = 'Unable to renew hosting service';
            $log_extra = array('Error' => $e);
            $log->critical($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

            throw $e;

        }

    }

    header('Location: ' . $renew_location);
    exit;

}

if ($hosting_available && $_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    $term_valid = DomainMOD\Hosting::validTerm($new_billing_term);
    // "Same as the Renewal Date" (0) or a day of the month
    $day_valid = $new_renewal_day >= 0 && $new_renewal_day <= 31;
    $status_valid = DomainMOD\Hosting::statusName($new_active) !== '';

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_account_id !== 0 && $new_type_id !== 0 &&
        $new_ip_id >= 0 && $new_cat_id !== 0 && $term_valid && $day_valid && $status_valid &&
        $custom_fields_valid
    ) {

        // Every write to hosting services holds the hosting lock, taken before the transaction (see DomainMOD\Hosting)
        if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

            $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';

        } else {

            try {

                $pdo->beginTransaction();

                // The Save is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

                $field_array = $lock_custom_fields();

                // Each asset the form chose must still exist (the asset deletes hold the hosting lock too). A missing one
                // is marked on its field.
                $asset_errors = array();

                // The service as it's stored, locked until the save ends
                $stmt = $pdo->prepare("
                    SELECT id, expiry_date, renewal_day
                    FROM hosting_services
                    WHERE id = :hsid
                    FOR UPDATE");
                $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
                $stmt->execute();
                $stored_record = $stmt->fetch();
                $stmt->closeCursor();

                if (!$stored_record) {

                    if ($pdo->InTransaction()) $pdo->rollback();
                    $_SESSION['s_message_danger'] .= $gone_message . '<BR>';
                    DomainMOD\Hosting::unlock($pdo);
                    header("Location: index.php");
                    exit;

                }

                // The hosting account, with its owner and provider, which the service takes from it
                $stmt = $pdo->prepare("
                    SELECT hpa.hosting_id, hpa.owner_id
                    FROM hosting_provider_accounts AS hpa
                    JOIN hosting AS h ON h.id = hpa.hosting_id
                    JOIN owners AS o ON o.id = hpa.owner_id
                    WHERE hpa.id = :new_account_id");
                $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                $stmt->execute();
                $provider_account = $stmt->fetch();
                $stmt->closeCursor();
                if (!$provider_account) $asset_errors['new_account_id'] = _('The hosting account you chose no longer exists. Please choose another one.');

                $asset_checks = array(
                    'new_type_id' => array('hosting_types', $new_type_id, _('The service type you chose no longer exists. Please choose another one.')),
                    'new_ip_id' => array('ip_addresses', $new_ip_id, _('The IP address you chose no longer exists. Please choose another one.')),
                    'new_cat_id' => array('categories', $new_cat_id, _('The category you chose no longer exists. Please choose another one.')));
                // An IP address of 0 is None, so there's nothing to check
                if ($new_ip_id === 0) unset($asset_checks['new_ip_id']);
                foreach ($asset_checks as $asset_field => $asset_check) {

                    $stmt = $pdo->prepare("SELECT id FROM " . $asset_check[0] . " WHERE id = :id");
                    $stmt->bindValue('id', $asset_check[1], PDO::PARAM_INT);
                    $stmt->execute();
                    if (!$stmt->fetchColumn()) $asset_errors[$asset_field] = $asset_check[2];
                    $stmt->closeCursor();

                }

                if ($asset_errors) {

                    // Nothing is saved, and the form shows again with what was typed and the missing assets marked
                    if ($pdo->InTransaction()) $pdo->rollback();
                    foreach ($asset_errors as $asset_field => $asset_error) $form->addFieldError($asset_field, $asset_error);

                } else {

                    // The service as it was (already locked above), so only what this Save really changes is recorded
                    $history_before = $history_op->capture('hosting', array($hsid));

                    $new_hosting_id = (int) $provider_account->hosting_id;
                    $new_owner_id = (int) $provider_account->owner_id;

                    // The fee for the provider, service type, and billing term, or none (the service is then saved
                    // with no cost, and the list shows "No fee" until one is added)
                    $stmt = $pdo->prepare("
                        SELECT id, (renewal_fee + misc_fee) AS total_cost
                        FROM hosting_fees
                        WHERE hosting_id = :new_hosting_id
                          AND type_id = :new_type_id
                          AND billing_term = :new_billing_term");
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_billing_term', (int) $new_billing_term, PDO::PARAM_INT);
                    $stmt->execute();
                    $fee = $stmt->fetch();
                    $stmt->closeCursor();

                    $new_fee_id = $fee ? (int) $fee->id : 0;
                    $new_total_cost = $fee ? (string) $fee->total_cost : '0';
                    $new_fee_fixed = $fee ? 1 : 0;

                    // The day follows a changed date unless the user changed the Renews On Day choice too (see
                    // Hosting::renewalDayToSave())
                    $save_renewal_day = DomainMOD\Hosting::renewalDayToSave((string) $stored_record->expiry_date,
                        (int) $stored_record->renewal_day, $new_expiry_date, $new_renewal_day, $renewal_day_opened);

                    // One conditional write: only the service as the form saw it (its revision and insert time) is
                    // changed, and its revision goes up by one
                    $stmt = $pdo->prepare("
                        UPDATE hosting_services
                        SET owner_id = :new_owner_id,
                            hosting_id = :new_hosting_id,
                            account_id = :new_account_id,
                            type_id = :new_type_id,
                            ip_id = :new_ip_id,
                            cat_id = :new_cat_id,
                            `name` = :new_name,
                            hostname = :new_hostname,
                            billing_term = :new_billing_term,
                            renewal_day = :new_renewal_day,
                            expiry_date = :new_expiry_date,
                            autorenew = :new_autorenew,
                            fee_id = :new_fee_id,
                            total_cost = :new_total_cost,
                            fee_fixed = :new_fee_fixed,
                            notes = :new_notes,
                            active = :new_active,
                            revision = revision + 1,
                            update_time = :timestamp
                        WHERE id = :hsid
                          AND revision = :form_revision
                          AND insert_time = :form_insert_time");
                    $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_hosting_id', $new_hosting_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
                    $stmt->bindValue('new_hostname', $new_hostname, PDO::PARAM_STR);
                    $stmt->bindValue('new_billing_term', (int) $new_billing_term, PDO::PARAM_INT);
                    $stmt->bindValue('new_renewal_day', $save_renewal_day, PDO::PARAM_INT);
                    $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                    $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
                    $stmt->bindValue('new_fee_id', $new_fee_id, PDO::PARAM_INT);
                    $stmt->bindValue('new_total_cost', $new_total_cost, PDO::PARAM_STR);
                    $stmt->bindValue('new_fee_fixed', $new_fee_fixed, PDO::PARAM_INT);
                    $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                    $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
                    $stmt->bindValue('form_revision', $form_revision, PDO::PARAM_INT);
                    $stmt->bindValue('form_insert_time', $form_insert_time, PDO::PARAM_STR);
                    $stmt->execute();

                    if ($stmt->rowCount() === 0) {

                        // The form is out of date: nothing changes
                        if ($pdo->InTransaction()) $pdo->rollback();
                        $_SESSION['s_message_danger'] .= $stale_message . '<BR>';

                    } else {

                        // The custom field values, written only once the service's own row was updated, all committed
                        // together
                        foreach ($field_array as $field) {

                            $stmt = $pdo->prepare("
                                UPDATE hosting_service_field_data
                                SET `" . $field . "` = :full_field,
                                    update_time = :timestamp
                                WHERE service_id = :hsid");
                            $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
                            $stmt->execute();

                        }

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $refresh_missing_fees();

                        $_SESSION['s_message_success'] .= sprintf(_('Hosting Service %s updated'), $new_name) . '<BR>';

                        // A hosting service saved without a fee has no cost until its fee is added, so the save says so
                        // (DomainMOD\FeeStatus)
                        $fee_status = new DomainMOD\FeeStatus();
                        $fee_row = $fee_status->hostingFee($hsid);
                        if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->hostingWarning($fee_row, ($_SESSION['s_read_only'] ?? '') != '1') . '<BR>';

                        DomainMOD\Hosting::unlock($pdo);
                        header('Location: edit.php?hsid=' . $hsid);
                        exit;

                    }

                }

            } catch (Exception $e) {

                if ($pdo->InTransaction()) $pdo->rollback();

                // Another request changed the same rows at the same moment: nothing was saved, and the form shows again
                // with what was typed (see domainmod_restore_is_conflict())
                if (domainmod_restore_is_conflict($e)) {
                    $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
                } else {

                    $log_message = 'Unable to update hosting service';
                    $log_extra = array('Error' => $e);
                    $log->critical($log_message, $log_extra);

                    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                    throw $e;

                }

            } finally {

                DomainMOD\Hosting::unlock($pdo);

            }

        }

    } else {

        if ($new_name == "") {
            $form->addFieldError('new_name', _("Enter the hosting service's name"));
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The renewal date you entered is invalid'));
        }
        if ($new_account_id === 0) {
            $form->addFieldError('new_account_id', _('Choose the Hosting Account'));
        }
        if ($new_type_id === 0) {
            $form->addFieldError('new_type_id', _('Choose the Service Type'));
        }
        if (!$term_valid) {
            $form->addFieldError('new_billing_term', _('Choose the Billing Term'));
        }
        if (!$day_valid) {
            $form->addFieldError('new_renewal_day', _('Choose the day of the month the service renews on'));
        }
        if (!$status_valid) {
            $form->addFieldError('new_active', _('Choose the Status'));
        }
        if ($new_ip_id < 0) {
            $form->addFieldError('new_ip_id', _('The IP address you chose no longer exists. Please choose another one.'));
        }
        if ($new_cat_id === 0) {
            $form->addFieldError('new_cat_id', _('Choose the Category'));
        }

    }

}

/*
 * Delete, from the Danger Zone's link, which carries the service's insert time: only the service the page showed is
 * deleted, even if it changed since (the revision isn't checked, as on the SSL certificate page), and never a new
 * service given the same id after a delete. Its domains are kept, without a hosting service.
 */
if ($hosting_available && $del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
        header('Location: edit.php?hsid=' . $hsid);
        exit;

    }

    $service_delete = new DomainMOD\HostingServiceDelete($pdo);

    try {

        /*
         * One attempt, in its own transaction. The locks are taken in the Change History's order: the hosting custom
         * field list (in share mode), then the service's domains (see HostingServiceDelete::unlinkDomains()), then the
         * service row. Every hosting service write holds the hosting lock, so the plain read that finds the service
         * first can't be overtaken before its row is locked. A deadlock with a domain delete runs it again from the
         * start (see HostingServiceDelete::withDeadlockRetry()), so the domain count is never counted twice.
         */
        $deleted = $service_delete->withDeadlockRetry(function () use ($pdo, $hsid, $form_insert_time, $lock_custom_fields,
            $service_delete, $system) {

            $pdo->beginTransaction();

            // The delete is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

            $lock_custom_fields();

            $find_service = function ($lock) use ($pdo, $hsid, $form_insert_time) {
                $stmt = $pdo->prepare("
                    SELECT id, `name`
                    FROM hosting_services
                    WHERE id = :hsid
                      AND insert_time = :form_insert_time" . ($lock ? "
                    FOR UPDATE" : ""));
                $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
                $stmt->bindValue('form_insert_time', $form_insert_time, PDO::PARAM_STR);
                $stmt->execute();
                $record = $stmt->fetch();
                $stmt->closeCursor();
                return $record;
            };

            if (!$find_service(false)) {

                $pdo->rollback();
                return false;

            }

            // The service's domains are kept, without a hosting service, each recorded as a side effect of the delete
            $domains_kept = $service_delete->unlinkDomains($hsid, $history_op);

            $delete_record = $find_service(true);
            if (!$delete_record) {

                $pdo->rollback();
                return false;

            }

            // The delete row, with a snapshot read under the service's lock
            $history_op->deleting('hosting', array($hsid));

            $stmt = $pdo->prepare("
                DELETE FROM hosting_services
                WHERE id = :hsid
                  AND insert_time = :form_insert_time");
            $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
            $stmt->bindValue('form_insert_time', $form_insert_time, PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $pdo->prepare("
                DELETE FROM hosting_service_field_data
                WHERE service_id = :hsid");
            $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
            $stmt->execute();

            $system->checkExistingAssets();

            $history_op->finish();

            $pdo->commit();

            return array('name' => $delete_record->name, 'domains_kept' => $domains_kept);

        });

        if ($deleted === false) {

            $_SESSION['s_message_danger'] .= $gone_message . '<BR>';
            DomainMOD\Hosting::unlock($pdo);
            header("Location: index.php");
            exit;

        }

        $refresh_missing_fees();

        $_SESSION['s_message_success'] .= sprintf(_('Hosting Service %s deleted'), $deleted['name']) . '<BR>';
        if ($deleted['domains_kept'] > 0) {
            $_SESSION['s_message_success'] .= sprintf(ngettext('%s domain was kept without a hosting service.',
                '%s domains were kept without a hosting service.', $deleted['domains_kept']),
                number_format($deleted['domains_kept'])) . '<BR>';
        }

        DomainMOD\Hosting::unlock($pdo);
        header("Location: index.php");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete hosting service';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        // No message for a conflict, whose own page explains it (see domainmod_restore_is_conflict())
        if (!domainmod_restore_is_conflict($e)) $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    } finally {

        DomainMOD\Hosting::unlock($pdo);

    }

}

// The service as it's saved (even after a failed Save, which shows the posted values): its revision and insert time
// for the forms, and its renewal details for Renew and the Renews On Day choice
$saved_record = false;

if ($hosting_available) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM hosting_services
        WHERE id = :hsid");
    $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
    $stmt->execute();
    $saved_record = $stmt->fetch();
    $stmt->closeCursor();

    if (!$saved_record) {

        $_SESSION['s_message_danger'] .= $gone_message . '<BR>';
        header("Location: index.php");
        exit;

    }

    if ($_SERVER['REQUEST_METHOD'] != 'POST') {

        $new_name = $saved_record->name;
        $new_account_id = (int) $saved_record->account_id;
        $new_type_id = (int) $saved_record->type_id;
        $new_billing_term = (string) $saved_record->billing_term;
        $new_active = (int) $saved_record->active;
        $new_expiry_date = (string) $saved_record->expiry_date;
        // "Same as the Renewal Date" when the stored day is the renewal date's own day, and the hidden field keeps the
        // choice the form opened with, posted back unchanged if a Save fails and the form shows again
        $new_renewal_day = DomainMOD\Hosting::renewalDayChoice($new_expiry_date, $saved_record->renewal_day);
        $renewal_day_opened = $new_renewal_day;
        $new_autorenew = (int) $saved_record->autorenew;
        $new_hostname = $saved_record->hostname;
        $new_ip_id = (int) $saved_record->ip_id;
        $new_cat_id = (int) $saved_record->cat_id;
        $new_notes = $saved_record->notes;
        $form_revision = (int) $saved_record->revision;
        $form_insert_time = (string) $saved_record->insert_time;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
if (!$hosting_available) {

    echo '<p>' . htmlspecialchars(DomainMOD\Hosting::unavailableMessage(), ENT_QUOTES, 'UTF-8') . '</p>';

} else {

    /*
     * The renew card, in its own small form outside the main one (forms can't nest). It moves the saved renewal date
     * forward one term (Mark Renewed), so it says where to, and carries the saved revision, so it can't apply twice. In
     * the Confirm renewal state (see DomainMOD\Hosting::renewalState(): auto-renew is on and the date passed in the last
     * 14 days), it's drawn at the top of the page, titled Confirm renewal, with a line saying what's needed, and
     * read-only users see that line without the button. Otherwise it's at the bottom, titled Renew, for users who aren't
     * read-only.
     */
    $read_only = ($_SESSION['s_read_only'] ?? '') == '1';
    $renewal_state = DomainMOD\Hosting::renewalState($saved_record->active, $saved_record->autorenew,
        $saved_record->expiry_date, $saved_record->billing_term, DomainMOD\Hosting::hostingDate($pdo));
    $renew_preview = DomainMOD\Hosting::validTerm((string) $saved_record->billing_term)
        ? $date->addMonths((string) $saved_record->expiry_date, (int) $saved_record->billing_term, (int) $saved_record->renewal_day) : '';
    $renew_card = function ($confirm) use ($form, $layout, $saved_record, $hsid, $read_only, $renew_preview) {

        ob_start();
        $can_renew = !$read_only && $renew_preview !== '';
        if ($confirm) {
            // The line's sentences, each translated on its own, with the one about the button only when it's drawn
            $help = array(sprintf(_('Auto-renew is on, so the host should have renewed this service on %s.'),
                $layout->ltr($saved_record->expiry_date)));
            if ($can_renew) $help[] = _("Once you've checked that it did, choose Mark Renewed.");
            $help[] = sprintf(_("If it's still unconfirmed on %s, it shows as overdue."),
                $layout->ltr(DomainMOD\Hosting::addDays((string) $saved_record->expiry_date, DomainMOD\Hosting::CONFIRM_DAYS + 1)));
            echo '<div class="domainmod-span-all callout callout-warning mb-0">' . implode(' ', $help) . '</div>';
        }
        if (!$read_only) {
            if ($renew_preview !== '') {

                echo '<p class="domainmod-span-all">' . sprintf(_('Move the renewal date forward one term (%1$s), from %2$s to %3$s.'),
                    htmlspecialchars(DomainMOD\Hosting::termLabel($saved_record->billing_term), ENT_QUOTES, 'UTF-8'),
                    $layout->ltr($saved_record->expiry_date), $layout->ltr($renew_preview)) . '</p>';
                echo '<div class="domainmod-span-all">' . $form->showSubmitButton(_('Mark Renewed'), '', '') . '</div>';

            } else {

                echo '<p class="domainmod-span-all">' . _("The renewal date can't be moved forward one term until the hosting service has a valid billing term and renewal date.") . '</p>';

            }
        }
        $renew_body = ob_get_clean();

        $html = $form->showFormTop('');
        $html .= $form->showInputHidden('renew_hosting', '1');
        $html .= $form->showInputHidden('hsid', (string) $hsid);
        $html .= $form->showInputHidden('revision', (string) (int) $saved_record->revision);
        $html .= $form->showInputHidden('insert_time', (string) $saved_record->insert_time);
        $html .= $layout->formSection($confirm ? _('Confirm renewal') : _('Renew'), 'renew', $renew_body);
        $html .= $form->showFormBottom('');
        return $html;

    };

    // The main form is drawn first and shown after the Confirm renewal card, so its field errors from a failed Save are
    // shown with their fields, never at the foot of the card's form (see Form::showFormBottom())
    ob_start();
    echo $form->showFormTop('');
    // The fields in titled cards, the same way as the edit SSL certificate page. Each card's fields are built first, so a
    // card whose menus all have nothing to choose from is left out rather than drawn empty.
    ob_start();
    echo $form->showInputText('new_name', _('Name') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '');

    $result = $pdo->query("
        SELECT hpa.id, hpa.username, o.name AS o_name, h.name AS h_name
        FROM hosting_provider_accounts AS hpa, owners AS o, hosting AS h
        WHERE hpa.owner_id = o.id
          AND hpa.hosting_id = h.id
        ORDER BY h_name ASC, o_name ASC, hpa.username ASC")->fetchAll();

    if ($result) {

        // The service's owner and provider come from its account (there's no owner menu of its own)
        echo $form->showDropdownTop('new_account_id', _('Hosting Account'), _('The owner comes from the account.'), '1', '');
        foreach ($result as $row) {

            echo $form->showDropdownOption($row->id, $row->h_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);

        }
        echo $form->showDropdownBottom('', 'new_account_id');

    }

    $result = $pdo->query("
        SELECT id, type
        FROM hosting_types
        ORDER BY type ASC")->fetchAll();

    if ($result) {

        echo $form->showDropdownTop('new_type_id', _('Service Type'), '', '1', '');
        foreach ($result as $row) {

            echo $form->showDropdownOption($row->id, $row->type, $new_type_id);

        }
        echo $form->showDropdownBottom('', 'new_type_id');

    }

    // A stored term that isn't an allowed one (only a damaged database has one) starts with nothing chosen
    echo $form->showDropdownTop('new_billing_term', _('Billing Term'), '', '1', '');
    if (!DomainMOD\Hosting::validTerm($new_billing_term)) echo $form->showDropdownOption('', _('Choose the Billing Term'), '');
    foreach (DomainMOD\Hosting::termOptions() as $term => $term_label) {

        echo $form->showDropdownOption($term, $term_label, $new_billing_term);

    }
    echo $form->showDropdownBottom('', 'new_billing_term');

    echo $form->showDropdownTop('new_active', _('Status'), '', '', '');
    foreach (DomainMOD\Hosting::statusOptions() as $status => $status_label) {

        echo $form->showDropdownOption($status, $status_label, $new_active);

    }
    echo $form->showDropdownBottom('', 'new_active');

    echo $form->showInputText('datepick', _('Renewal Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

    // The same menu as the add page's
    echo $form->showDropdownTop('new_renewal_day', _('Renews On Day'), _('If a shorter month moves the renewal date earlier, the next renewal goes back to the day chosen here.'), '1', '');
    // TRANSLATORS: The Renews On Day menu's first choice, which takes the day from the renewal date.
    echo $form->showDropdownOption('0', _('Same as the Renewal Date'), $new_renewal_day);
    for ($day = 1; $day <= 31; $day++) {

        echo $form->showDropdownOption($day, $day, $new_renewal_day);

    }
    echo $form->showDropdownBottom('', 'new_renewal_day');

    // The help is fixed text, so it's drawn whatever the switch's value, and is there when someone turns it on
    // TRANSLATORS: %s is the number of days an auto-renewing hosting service waits for its renewal to be confirmed (14).
    $autorenew_help = sprintf(ngettext('With auto-renew on, an active service whose renewal date has passed waits %s day for you to confirm the renewal before it shows as overdue.', 'With auto-renew on, an active service whose renewal date has passed waits %s days for you to confirm the renewal before it shows as overdue.', DomainMOD\Hosting::CONFIRM_DAYS), DomainMOD\Hosting::CONFIRM_DAYS);
    echo $form->showSwitch(_('Auto-Renewal') . '?', $autorenew_help, 'new_autorenew', $new_autorenew, '<div class="mb-3">', '</div>');

    echo $layout->formSection(_('Details'), 'details', ob_get_clean());

    ob_start();
    echo $form->showInputText('new_hostname', _('Hostname') . ' (255)', _('Such as web-01.example.com'), $unsanitize->text($new_hostname), '255', '', '', '', '', '', 'ltr');

    // The IP address is optional: None first, and the menu is always drawn, even with no IP addresses at all, so a
    // service saved with None still saves once the last IP address is deleted
    $result = $pdo->query("
        SELECT id, ip, `name`
        FROM ip_addresses
        ORDER BY `name`, ip")->fetchAll();

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '', '');
    // TRANSLATORS: The IP Address menu's choice for a hosting service with no IP address.
    echo $form->showDropdownOption('0', _('None'), $new_ip_id);
    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);

    }
    echo $form->showDropdownBottom('', 'new_ip_id');

    $result = $pdo->query("
        SELECT id, `name`
        FROM categories
        ORDER BY `name`")->fetchAll();

    if ($result) {

        echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');
        foreach ($result as $row) {

            echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);

        }
        echo $form->showDropdownBottom('', 'new_cat_id');

    }

    echo $layout->formSection(_('Where It Runs'), 'where-it-runs', ob_get_clean());

    // The saved fee for the service's type and billing term at its provider, or what's missing, with a link to edit
    // or add it for users who can edit fees (DomainMOD\FeeStatus)
    $fee_status = new DomainMOD\FeeStatus();
    $fee_row = $fee_status->hostingFee($hsid);
    if ($fee_row) echo $layout->formSection(_('Fee'), 'fee', $fee_status->hostingSection($fee_row, ($_SESSION['s_read_only'] ?? '') != '1'));

    ob_start();
    if ($new_notes != '') {
        // Opens the notes on their own page, in a new tab
        $subtext = '<a target="_blank" href="notes.php?hsid=' . $hsid . '">' . _('View Full Notes')
            . '<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>';
    } else {
        $subtext = '';
    }
    echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');

    echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

    ob_start();
    $result = $pdo->query("
        SELECT field_name, `name`, type_id, description
        FROM hosting_service_fields
        ORDER BY type_id, `name`")->fetchAll();

    $stored_custom_fields = false;
    if ($result && $_SERVER['REQUEST_METHOD'] != 'POST') {

        $stmt = $pdo->prepare("
            SELECT *
            FROM hosting_service_field_data
            WHERE service_id = :hsid");
        $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
        $stmt->execute();
        $stored_custom_fields = $stmt->fetch();
        $stmt->closeCursor();

    }

    foreach ($result as $row) {

        $field = $row->field_name;
        $input_name = DomainMOD\CustomField::inputName($row->field_name);

        // A save that failed shows what was entered, blank or not, rather than the stored value
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $field_data = $new_custom_fields[$field] ?? '';

        } else {

            $field_data = $stored_custom_fields ? ($stored_custom_fields->{$field} ?? '') : '';

            // A Date or Time Stamp that was never set shows blank rather than its placeholder date
            if (($row->type_id == "4" || $row->type_id == "5") && DomainMOD\Time::isUnset($field_data)) {

                $field_data = '';

            }

        }

        if ($row->type_id == "1") { // Check Box

            echo $form->showCheckbox($input_name, '1', $row->name, $row->description, $field_data, '', '');

        } elseif ($row->type_id == "2") { // Text

            echo $form->showInputText($input_name, $row->name, $row->description, $field_data, '255', '', '', '', '');

        } elseif ($row->type_id == "3") { // Text Area

            echo $form->showInputTextarea($input_name, $row->name, $row->description, $field_data, '', '<div class="domainmod-span-all">', '</div>');

        } elseif ($row->type_id == "4") { // Date

            echo $form->showInputText($input_name, $row->name, $row->description, $field_data, '10', '', '', '', '', '', 'ltr');

        } elseif ($row->type_id == "5") { // Time Stamp

            echo $form->showInputText($input_name, $row->name, $row->description, $field_data, '19', '', '', '', '', '', 'ltr');

        } elseif ($row->type_id == "6") { // URL

            echo $form->showInputText($input_name, $row->name, $row->description, $field_data, '255', '', '', '', '', '', 'ltr');

        }

    }

    echo $layout->formSection(_('Custom Fields'), 'custom-fields', ob_get_clean());

    echo $form->showInputHidden('hsid', (string) $hsid);
    echo $form->showInputHidden('renewal_day_opened', (string) $renewal_day_opened);
    echo $form->showInputHidden('revision', (string) $form_revision);
    echo $form->showInputHidden('insert_time', $form_insert_time);
    echo $form->showSubmitButton(_('Save'), '', '');
    echo $form->showFormBottom('');
    $main_form = ob_get_clean();

    if ($renewal_state === DomainMOD\Hosting::STATE_CONFIRM) echo $renew_card(true);
    echo $main_form;

    /*
     * The domains on this hosting service, read-only and outside the forms: the first 100 by name ($domains_card_limit), each
     * linked to its edit page with its expiry date and status, and the total in the heading. Past that, a link opens them
     * all in the Domains list (every status, as the card counts). The link names no number, since that list leaves out
     * domains with no fee or no conversion rate, so it can show fewer than the card counts.
     */
    $domains_card_limit = 100;
    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM domains
        WHERE service_id = :hsid");
    $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
    $stmt->execute();
    $service_domain_count = (int) $stmt->fetchColumn();
    $stmt->closeCursor();

    ob_start();
    if ($service_domain_count === 0) {

        echo '<p class="domainmod-span-all mb-0">' . _("No domains use this service. Choose it on a domain's edit page, or use the Bulk Updater's Change Hosting Service.") . '</p>';

    } else {

        $stmt = $pdo->prepare("
            SELECT id, domain, expiry_date, active
            FROM domains
            WHERE service_id = :hsid
            ORDER BY domain ASC, id ASC
            LIMIT " . $domains_card_limit);
        $stmt->bindValue('hsid', $hsid, PDO::PARAM_INT);
        $stmt->execute();
        $service_domains = $stmt->fetchAll();
        $stmt->closeCursor();

        // The status names, as the Domains list shows them
        $domain_status_names = array('0' => _('Expired'), '1' => _('Active'), '2' => _('Pending (Transfer)'),
            '3' => _('Pending (Renewal)'), '4' => _('Pending (Other)'), '5' => _('Pending (Registration)'), '10' => _('Sold')); ?>

        <div class="table-responsive domainmod-span-all"><table class="table table-sm table-striped mb-0">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Domain'); ?></th>
                <th scope="col"><?php echo _('Expiry Date'); ?></th>
                <th scope="col"><?php echo _('Status'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($service_domains as $service_domain) { ?>
                <tr>
                    <td><a href="../domains/edit.php?did=<?php echo (int) $service_domain->id; ?>"><?php echo $service_domain->domain; ?></a></td>
                    <td><?php echo $layout->ltr($service_domain->expiry_date); ?></td>
                    <td><?php echo htmlspecialchars($domain_status_names[(string) $service_domain->active] ?? (string) $service_domain->active, ENT_QUOTES, 'UTF-8'); ?></td>
                </tr><?php
            } ?>
            </tbody>
        </table></div><?php

        if ($service_domain_count > $domains_card_limit) {
            echo '<p class="domainmod-span-all mb-0"><a href="../domains/index.php?hsid=' . (int) $hsid . '&amp;is_active=ALL">'
                . _('Open these domains in the Domains list') . '</a></p>';
        }

    }
    // TRANSLATORS: The heading of the card listing a hosting service's domains. %s is how many domains use the service.
    echo '<div class="domainmod-form">' . $layout->formSection(sprintf(_('Domains (%s)'), number_format($service_domain_count)), 'domains', ob_get_clean()) . '</div>';

    // The renew card's usual place, unless it's at the top of the page for the Confirm renewal state
    if ($renewal_state !== DomainMOD\Hosting::STATE_CONFIRM && !$read_only) echo $renew_card(false);

    // The hosting service's Change History, read-only and outside the forms, at the same width as the cards above it
    echo '<div class="domainmod-form">' . DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'hosting', $hsid) . '</div>';

    $layout->dangerZone(_('Hosting Service'), $new_name, 'edit.php?hsid=' . $hsid . '&del=1&insert_time=' . rawurlencode((string) $saved_record->insert_time), _('Delete this hosting service'), sprintf(_('This removes %s and its details from %s. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($saved_record->name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>', SOFTWARE_TITLE)
        . ' ' . _('Any linked domains are kept without a hosting service.'));

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
