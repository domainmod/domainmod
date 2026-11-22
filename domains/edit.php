<?php
/**
 * /domains/edit.php
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
$log = new DomainMOD\Log('/domains/edit.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/domains-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$did = (int) ($_REQUEST['did'] ?? 0);

$del = (int) ($_GET['del'] ?? 0);

$new_domain = isset($_POST['new_domain']) ? $sanitize->text($_POST['new_domain']) : '';
$new_tld = $_POST['new_tld'] ?? '';

// the domain can't be changed here, so use the stored name and work out its TLD, rather than trusting the posted
// hidden fields (a form opened before an upgrade can hold an old TLD)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $did !== 0) {

    $stmt = $pdo->prepare("
        SELECT domain
        FROM domains
        WHERE id = :did");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();
    $stored_domain = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($stored_domain !== false) {

        $new_domain = $sanitize->text($stored_domain);
        $new_tld = DomainMOD\Domain::tldOf($stored_domain);

    }

}
$new_expiry_date = $_POST['datepick'] ?? '';
$new_function = isset($_POST['new_function']) ? $sanitize->text($_POST['new_function']) : '';
$new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
$new_dns_id = (int) ($_POST['new_dns_id'] ?? 0);
$new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);
// The domain's optional hosting service (0 is None), once hosting is available. A malformed value is -1, refused below.
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$new_service_id = $hosting_available ? DomainMOD\Hosting::postedInt($_POST['new_service_id'] ?? '0', -1) : 0;
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0);
$new_privacy = (int) ($_POST['new_privacy'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $custom_field->getFieldTypes('domain_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

// The Refresh Domain form posts here too, and is handled on its own, before the save handler.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['refresh_domain'] ?? '') === '1') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $refresh = new DomainMOD\DomainRefresh();

    // The Refresh button at the top of the page runs in place (htmx, see js/domain-refresh.js): it answers with the
    // result message and, after a refresh that worked, the saved values of the fields Refresh owns, so the page can
    // update them in the form. Without JavaScript it's a normal post, handled below.
    if ($system->isHtmxRequest()) {

        $refresh_values = function ($pdo, $did) {
            $stmt = $pdo->prepare("
                SELECT d.expiry_date, d.dns_id, dns.name AS dns_name, d.privacy, d.autorenew, d.notes
                FROM domains AS d
                LEFT JOIN dns ON d.dns_id = dns.id
                WHERE d.id = :did");
            $stmt->bindValue('did', $did, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
            return $row;
        };

        // A new refresh of this domain clears any result stored by an earlier one without JavaScript (below)
        if ((int) ($_SESSION['s_refresh_result']['did'] ?? 0) === (int) $did) unset($_SESSION['s_refresh_result']);

        $before = $refresh_values($pdo, $did);
        list($refreshed, $refresh_lines, $refresh_parts) = $refresh->refresh($did, (int) $_SESSION['s_user_id']);

        header('X-DomainMOD-Result: 1');
        echo '<div data-domainmod-focus>';
        // The compact result: a summary, any reasons, the changes, and the notes behind "Why?"
        echo $system->showMessageResult($refreshed ? 'success' : 'danger', $refresh_parts);

        if ($refreshed) {

            $after = $refresh_values($pdo, $did);

            if ($after) {

                $values = array(
                    'expiry_date' => (string) $after->expiry_date,
                    'dns_id' => (int) $after->dns_id,
                    'dns_name' => html_entity_decode((string) $after->dns_name, ENT_QUOTES, 'UTF-8'),
                    'privacy' => (int) $after->privacy,
                    'autorenew' => (int) $after->autorenew,
                    // Notes are stored escaped, so they're unescaped the same way the notes field shows them
                    'notes' => $unsanitize->text($after->notes));

                $changed = array();
                foreach (array('expiry_date', 'dns_id', 'privacy', 'autorenew', 'notes') as $field) {
                    if (!$before || (string) $before->$field !== (string) $after->$field) $changed[] = $field;
                }
                $values['changed'] = $changed;

                $json = json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
                echo '<div id="domainmod-refresh-data" hidden data-values="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"></div>';

            }

        }

        echo '</div>';

        // The Registry Status card, redrawn from the saved row, replaces the page's (an out-of-band swap)
        if (DomainMOD\DomainStatus::isAvailable($pdo)) {
            $status_row = DomainMOD\DomainStatus::savedRow($pdo, $did);
            if ($status_row) echo DomainMOD\DomainStatus::editCard($status_row, true, true);
        }

        // The Change History section, redrawn with the refresh's changes, replaces the page's (an out-of-band swap)
        echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'domain', $did, true, DomainMOD\DomainRefresh::statusLine($pdo, $did));
        exit;

    }

    list($refreshed, $refresh_lines, $refresh_parts) = $refresh->refresh($did, (int) $_SESSION['s_user_id']);

    // The result is shown under the Refresh button after the redirect, on this domain's page only (see below)
    $_SESSION['s_refresh_result'] = array('did' => (int) $did, 'type' => $refreshed ? 'success' : 'danger', 'parts' => $refresh_parts);

    header('Location: edit.php?did=' . $did);
    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $date = new DomainMOD\Date();

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    if ($date->checkDateFormat($new_expiry_date) && $new_cat_id !== 0 && $new_dns_id !== 0 && $new_ip_id !== 0 &&
        $new_service_id >= 0 && $new_account_id !== 0 && $custom_fields_valid
    ) {

        /*
         * A Save that involves a hosting service holds the hosting lock, taken before the transaction: one whose posted
         * service isn't None, or whose stored service (read here) isn't None, even for a Save to None, so a Save and a
         * service delete never meet on the same domain from opposite ends. A domain with no service, saved with None,
         * takes no lock, so ordinary edits never wait on hosting maintenance (see DomainMOD\Hosting).
         */
        $stored_service_id = 0;
        if ($hosting_available) {
            $stmt = $pdo->prepare("
                SELECT service_id
                FROM domains
                WHERE id = :did");
            $stmt->bindValue('did', $did, PDO::PARAM_INT);
            $stmt->execute();
            $stored_service_id = (int) $stmt->fetchColumn();
            $stmt->closeCursor();
        }
        $hosting_locked = false;

        try {

            if ($new_service_id !== 0 || $stored_service_id !== 0) {
                if (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {
                    throw new DomainMOD\HostingServiceRefused(DomainMOD\Hosting::busyMessage(), DomainMOD\HostingServiceRefused::BUSY);
                }
                $hosting_locked = true;
            }

            $pdo->beginTransaction();

            // The Save is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

            // The record may have been deleted while the form was open, and then nothing is saved. A locking read, so it
            // never opens the transaction's snapshot before the capture's locking read of the same row.
            $stmt = $pdo->prepare("
                SELECT id
                FROM domains
                WHERE id = :record_id
                FOR UPDATE");
            $stmt->bindValue('record_id', $did, PDO::PARAM_INT);
            $stmt->execute();
            $record_exists = $stmt->fetchColumn();
            $stmt->closeCursor();

            if (!$record_exists) {

                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That domain no longer exists.') . '<BR>';
                header("Location: ../domains/index.php");
                exit;

            }

            // The domain as it was, locked, so only what this Save really changes is recorded
            $history_before = $history_op->capture('domain', array($did));

            // The hosting service must still exist, and can't be a cancelled one unless the domain is already on it (its
            // stored service, read now under the domain's lock). Nothing is saved, and the form shows again with the
            // service marked, when it can't be used.
            if ($hosting_available && $new_service_id !== 0) {
                $stmt = $pdo->prepare("
                    SELECT service_id
                    FROM domains
                    WHERE id = :did");
                $stmt->bindValue('did', $did, PDO::PARAM_INT);
                $stmt->execute();
                $locked_service_id = (int) $stmt->fetchColumn();
                $stmt->closeCursor();
                $service_error = DomainMOD\Hosting::serviceError($pdo, $new_service_id, $locked_service_id);
                if ($service_error !== '') {
                    $pdo->rollback();
                    throw new DomainMOD\HostingServiceRefused($service_error);
                }
            }

            $stmt = $pdo->prepare("
                SELECT registrar_id, owner_id
                FROM registrar_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if (!$result) {

                // the account was deleted while the form was open (or the form was changed), so nothing is saved
                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The registrar account you chose no longer exists. Please choose another one.') . '<BR>';
                header("Location: edit.php?did=" . $did . "");
                exit;

            }

            $new_registrar_id = $result->registrar_id;
            $new_owner_id = $result->owner_id;

            $stmt = $pdo->prepare("
                SELECT id
                FROM fees
                WHERE registrar_id = :new_registrar_id
                  AND tld = :new_tld");
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchColumn();

            if (!$result) {

                $temp_fee_id = "0";
                $temp_fee_fixed = "0";

            } else {

                $temp_fee_id = $result;
                $temp_fee_fixed = "1";

            }

            if ($new_privacy === 1) {

                $fee_string = "renewal_fee + privacy_fee + misc_fee";

            } else {

                $fee_string = "renewal_fee + misc_fee";

            }

            $stmt = $pdo->prepare("
                SELECT (" . $fee_string . ")
                FROM fees
                WHERE registrar_id = :new_registrar_id
                  AND tld = :new_tld");
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
            $stmt->execute();
            $new_total_cost = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                UPDATE domains
                SET owner_id = :new_owner_id,
                    registrar_id = :new_registrar_id,
                    account_id = :new_account_id,
                    tld = :new_tld,
                    expiry_date = :new_expiry_date,
                    cat_id = :new_cat_id,
                    dns_id = :new_dns_id,
                    ip_id = :new_ip_id,
                    " . ($hosting_available ? "service_id = :new_service_id," : "") . "
                    fee_id = :temp_fee_id,
                    total_cost = :new_total_cost,
                    `function` = :new_function,
                    notes = :new_notes,
                    autorenew = :new_autorenew,
                    privacy = :new_privacy,
                    active = :new_active,
                    fee_fixed = :temp_fee_fixed,
                    update_time = :timestamp
                WHERE id = :did");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
            $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
            $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
            $stmt->bindValue('new_dns_id', $new_dns_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
            if ($hosting_available) $stmt->bindValue('new_service_id', $new_service_id, PDO::PARAM_INT);
            $stmt->bindValue('temp_fee_id', $temp_fee_id, PDO::PARAM_INT);
            $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
            $stmt->bindValue('new_function', $new_function, PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
            $stmt->bindValue('new_privacy', $new_privacy, PDO::PARAM_INT);
            $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
            $stmt->bindValue('temp_fee_fixed', $temp_fee_fixed, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('did', $did, PDO::PARAM_INT);
            $stmt->execute();

            $result = $pdo->query("
                SELECT field_name
                FROM domain_fields
                ORDER BY `name`
                LOCK IN SHARE MODE")->fetchAll();

            if ($result) {

                $count = 0;

                foreach ($result as $row) {

                    $field_array[$count] = $row->field_name;
                    $count++;

                }

                foreach ($field_array as $field) {

                    $stmt = $pdo->prepare("
                        UPDATE domain_field_data
                        SET `" . $field . "` = :full_field,
                            update_time = :timestamp
                        WHERE domain_id = :did");
                    $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('did', $did, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $maint->updateSegments();

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('domains');
            $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

            $history_op->compare($history_before);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('Domain %s updated'), $new_domain) . '<BR>';

            // A domain saved without a fee is hidden from the list, so the save says so (DomainMOD\FeeStatus)
            $fee_status = new DomainMOD\FeeStatus();
            $fee_row = $fee_status->domainFee($did);
            if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->domainWarning($fee_row, true) . '<BR>';

            header('Location: edit.php?did=' . $did);
            exit;

        } catch (DomainMOD\HostingServiceRefused $e) {

            // The hosting lock was busy (nothing was written), or the service can't be used (marked on its menu)
            if ($e->getCode() === DomainMOD\HostingServiceRefused::BUSY) {
                $_SESSION['s_message_danger'] .= $e->getMessage() . '<BR>';
            } else {
                $form->addFieldError('new_service_id', $e->getMessage());
            }

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            // Another request changed the same rows at the same moment: nothing was saved, and the form shows again with
            // what was typed (see domainmod_restore_is_conflict())
            if (domainmod_restore_is_conflict($e)) {
                $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            } else {

                $log_message = 'Unable to edit domain';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        } finally {

            if ($hosting_locked) DomainMOD\Hosting::unlock($pdo);

        }

    } else {

        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The expiry date you entered is invalid'));
        }

        if ($new_account_id === 0) {

            $form->addFieldError('new_account_id', _('Choose the Registrar Account'));

        }

        if ($new_dns_id === 0) {

            $form->addFieldError('new_dns_id', _('Choose the DNS Profile'));

        }

        if ($new_ip_id === 0) {

            $form->addFieldError('new_ip_id', _('Choose the IP Address'));

        }

        if ($new_service_id < 0) {

            $form->addFieldError('new_service_id', DomainMOD\Hosting::serviceGoneMessage());

        }

        if ($new_cat_id === 0) {

            $form->addFieldError('new_cat_id', _('Choose the Category'));

        }

    }

} else {

    $stmt = $pdo->prepare("
        SELECT d.domain, d.tld, d.expiry_date, d.cat_id, d.dns_id, d.ip_id" . ($hosting_available ? ", d.service_id" : "") . ", d.function, d.notes, d.autorenew, d.privacy, d.active, ra.id AS account_id
        FROM domains AS d, registrar_accounts AS ra
        WHERE d.account_id = ra.id
          AND d.id = :did");
    $stmt->bindValue('did', $did, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_domain = $result->domain;
        $new_tld = $result->tld;
        $new_expiry_date = $result->expiry_date;
        $new_cat_id = $result->cat_id;
        $new_dns_id = $result->dns_id;
        $new_ip_id = $result->ip_id;
        $new_service_id = $hosting_available ? (int) $result->service_id : 0;
        $new_function = $result->function;
        $new_notes = $result->notes;
        $new_autorenew = $result->autorenew;
        $new_privacy = $result->privacy;
        $new_active = $result->active;
        $new_account_id = $result->account_id;

    }

}

if ($del === 1) {

    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

    try {

        $pdo->beginTransaction();

        // The delete is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

        // The domain may have been deleted while the form was open, and then nothing is saved. A domain that's found is
        // locked, before its certificates, as every writer does
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE id = :record_id
            FOR UPDATE");
        $stmt->bindValue('record_id', $did, PDO::PARAM_INT);
        $stmt->execute();
        $record_exists = $stmt->fetchColumn();
        $stmt->closeCursor();

        if (!$record_exists) {

            if ($pdo->InTransaction()) $pdo->rollback();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That domain no longer exists.') . '<BR>';
            header("Location: ../domains/index.php");
            exit;

        }

        // The domain's delete row, with a snapshot read under its lock, then its SSL certificates, which are kept
        // without a domain: locked after the domain, a page at a time, each recorded going from the domain to none
        // (while the domain's name can still be read)
        $history_op->deleting('domain', array($did));
        $domain = new DomainMOD\Domain();
        $unlinked_ssl_certs = $domain->unlinkSslCertificatesRecorded(array($did), $history_op);

        $stmt = $pdo->prepare("
            DELETE FROM domains
            WHERE id = :did");
        $stmt->bindValue('did', $did, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM domain_field_data
            WHERE domain_id = :did");
        $stmt->bindValue('did', $did, PDO::PARAM_INT);
        $stmt->execute();

        // The Refresh Domains task's schedule for it (its history stays)
        (new DomainMOD\DomainRefresh())->deleteStatus(array($did));

        $maint->updateSegments();

        $system->checkExistingAssets();

        $history_op->finish();

        if ($pdo->InTransaction()) $pdo->commit();

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        $_SESSION['s_message_success'] .= sprintf(_('Domain %s deleted.'), $new_domain) . '<BR>';

        if ($unlinked_ssl_certs > 0) {
            // TRANSLATORS: %s is the number of SSL certificates that were linked to the deleted domain or domains.
            $_SESSION['s_message_success'] .= sprintf(ngettext('%s SSL certificate was kept without a domain.', '%s SSL certificates were kept without a domain.', $unlinked_ssl_certs), number_format($unlinked_ssl_certs)) . '<BR>';
        }

        header("Location: ../domains/index.php");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete domain';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

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
// Refresh, at the top of the page in its own small form (read-only users can't change data, so they don't see it).
// It runs in place with htmx (see js/domain-refresh.js), and without JavaScript it's a normal post.
if (($_SESSION['s_read_only'] ?? '') != '1') {

    $refresh = new DomainMOD\DomainRefresh();
    $refresh_api_name = $refresh->registrarApiName($did);
    // Refresh also checks the transfer lock status once the 4.27.0 database update is done (DomainMOD\DomainStatus)
    $refresh_checks_lock = DomainMOD\DomainStatus::isAvailable($pdo);

    if ($refresh_api_name !== '') {

        $refresh_source = sprintf(_('Updates the expiry date, DNS servers, WHOIS privacy, and auto-renewal from your %s API.'), htmlentities($refresh_api_name, ENT_QUOTES, 'UTF-8'));
        $refresh_details = sprintf(_("Look up this domain's current expiry date, DNS servers, WHOIS privacy, and auto-renewal status using your %s API, and update them here."), $refresh_api_name) . ' ' . _("Some registrars' APIs don't report every one of these, and anything not reported is left as it is.");
        if ($refresh_checks_lock) {
            $refresh_details .= ' ' . _("If the API can't be used, Refresh uses public RDAP or WHOIS data.") . ' ' . _("WHOIS privacy and auto-renewal status aren't available this way, so they're left as they are.");
        } else {
            $refresh_details .= ' ' . _("If the API can't be used, public RDAP and WHOIS data is used instead, which only updates the expiry date and DNS servers.");
        }

    } else {

        $refresh_source = _('Updates the expiry date and DNS servers from public RDAP and WHOIS data.');
        $refresh_details = _("Look up this domain's current expiry date and DNS servers using public RDAP and WHOIS data, and update them here. WHOIS privacy and auto-renewal status aren't available this way, so they're left as they are.");

    }

    if ($refresh_checks_lock) {
        $refresh_source .= ' ' . _('Also checks the transfer lock status using RDAP.');
        $refresh_details .= ' ' . _("Refresh also checks the transfer lock status using RDAP, where it's available.");
    } ?>

    <div class="domainmod-refresh-row d-flex flex-wrap align-items-center justify-content-end gap-2">
        <span class="text-body-secondary small"><?php echo $refresh_source; ?></span>
        <form method="post" class="d-inline-flex align-items-center" hx-post="edit.php?did=<?php echo $did; ?>" hx-target="#domainmod-refresh-result" hx-swap="innerHTML" hx-indicator="#domainmod-refresh-spinner" hx-disabled-elt="find button" hx-sync="this:drop" data-domainmod-refresh>
            <?php echo $form->showCsrfField(); ?>
            <?php echo $form->showInputHidden('refresh_domain', '1'); ?>
            <?php echo $form->showInputHidden('did', $did); ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($refresh_details, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?php echo _('Refresh'); ?></button>
            <?php echo $layout->htmxSpinner('domainmod-refresh-spinner'); ?>
        </form>
    </div>
    <div id="domainmod-refresh-result" class="domainmod-refresh-result"><?php
    // A result stored by a refresh without JavaScript, shown once, and only on the page of the domain it belongs to
    $stored_refresh = $_SESSION['s_refresh_result'] ?? null;
    if (is_array($stored_refresh) && (int) ($stored_refresh['did'] ?? 0) === (int) $did) {
        echo '<div data-domainmod-focus>' . $system->showMessageResult($stored_refresh['type'] === 'danger' ? 'danger' : 'success', $stored_refresh['parts']) . '</div>';
        unset($_SESSION['s_refresh_result']);
    } ?></div>

    <?php // Asks before a refresh replaces fields the user has changed but not saved (see js/domain-refresh.js) ?>
    <div class="modal fade" id="domainmod-refresh-confirm" tabindex="-1" aria-labelledby="domainmod-refresh-confirm-title" aria-hidden="true" data-message="<?php echo htmlspecialchars(_("You've changed %s but haven't saved. Refreshing saves the looked-up details straight away and replaces what you typed in those fields."), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="domainmod-refresh-confirm-title"><?php echo _('Unsaved Changes'); ?></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo _('Close'); ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" data-domainmod-refresh-message></p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo _('Cancel'); ?></button>
                    <button type="button" class="btn btn-danger" data-domainmod-refresh-anyway><?php echo _('Refresh anyway'); ?></button>
                </div>
            </div>
        </div>
    </div><?php

    // A page shown again after a failed Save holds the user's unsaved values, not the saved ones
    if ($_SERVER['REQUEST_METHOD'] == 'POST') echo '<div hidden data-domainmod-refresh-unsaved></div>';

}

echo $form->showFormTop('');
echo '<div class="mb-3 fs-4 fw-semibold lh-sm text-break">' . htmlentities($new_domain, ENT_QUOTES, 'UTF-8') . '</div>';
// The fields in titled cards (Greg's choice "C", 2026-11-14). Each card's fields are built first, so a card whose
// menus all have nothing to choose from is left out rather than drawn empty. Moving a field to another card is a
// matter of moving its lines between these blocks.
ob_start();
echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');
$result = $pdo->query("
    SELECT id, `name`
    FROM categories
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_cat_id', _('Category'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_cat_id);

    }

    echo $form->showDropdownBottom('', 'new_cat_id');

}

echo $form->showDropdownTop('new_active', _('Domain Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('2', _('Pending (Transfer)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('10', _('Sold'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $layout->formSection(_('Details'), 'details', ob_get_clean());

ob_start();
echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');

$result = $pdo->query("
    SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id
    ORDER BY r_name ASC, o_name ASC, ra.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('Registrar Account'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->r_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);

    }

    echo $form->showDropdownBottom('', 'new_account_id');

}

// Each switch is one grid item, with the same spacing as the other fields
echo $form->showSwitch(_('Auto-Renewal') . '?', '', 'new_autorenew', $new_autorenew, '<div class="mb-3">', '</div>');
echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '<div class="mb-3">', '</div>');
echo $layout->formSection(_('Registration'), 'registration', ob_get_clean());

// The registry's status codes and transfer lock (DomainMOD\DomainStatus), read-only, from the saved row (read on its
// own, so after a save that fails validation it still shows what's saved, not the posted form)
if (DomainMOD\DomainStatus::isAvailable($pdo)) {
    $status_row = DomainMOD\DomainStatus::savedRow($pdo, $did);
    if ($status_row) echo DomainMOD\DomainStatus::editCard($status_row, ($_SESSION['s_read_only'] ?? '') != '1');
}

// The saved fee for the domain's TLD at its registrar, or what's missing, with a link to edit or add it for users who
// can edit fees (DomainMOD\FeeStatus)
$fee_status = new DomainMOD\FeeStatus();
$fee_row = $fee_status->domainFee($did);
if ($fee_row) echo $layout->formSection(_('Fee'), 'fee', $fee_status->domainSection($fee_row, ($_SESSION['s_read_only'] ?? '') != '1'));

ob_start();
$result = $pdo->query("
    SELECT id, `name`
    FROM dns
    ORDER BY name ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_dns_id', _('DNS Profile'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_dns_id);

    }

    echo $form->showDropdownBottom('', 'new_dns_id');

}

$result = $pdo->query("
    SELECT id, `name`, ip
    FROM ip_addresses
    ORDER BY `name` ASC, ip ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);

    }

    echo $form->showDropdownBottom('', 'new_ip_id');
}

// The optional hosting service, once hosting is available. The domain's current service is listed even when it's
// cancelled, so a Save that keeps it still works.
if ($hosting_available) {

    $new_service_id = max((int) $new_service_id, 0);
    $services = DomainMOD\Hosting::serviceMenu($pdo, $new_service_id);
    // Shown again after a failed save, the IP Address shown was already chosen, so the service no longer fills it in
    echo $form->showHostingServiceMenu('new_service_id', $new_service_id, $services, '', '',
        $_SERVER['REQUEST_METHOD'] == 'POST' ? '' : 'new_ip_id', DomainMOD\Hosting::serviceIps($pdo, array_keys($services)), true);

}

echo $layout->formSection(_('Hosting'), 'hosting', ob_get_clean());

ob_start();
if ($new_notes != '') {
    // Opens the notes on their own page, in a new tab
    $subtext = '<a target="_blank" href="notes.php?did=' . $did . '">' . _('View Full Notes')
        . '<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');

echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

ob_start();
$result = $pdo->query("
    SELECT field_name
    FROM domain_fields
    ORDER BY type_id, `name`")->fetchAll();

if ($result) {

    $count = 0;

    foreach ($result as $row) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $result = $pdo->query("
            SELECT df.name, df.field_name, df.type_id, df.description
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
              AND df.field_name = '" . $field . "'")->fetchAll();

        foreach ($result as $row) {

            // A save that failed shows what was entered, blank or not, rather than the stored value
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                $field_data = $new_custom_fields[$field] ?? '';

            } else {

                $stmt = $pdo->prepare("
                    SELECT " . $row->field_name . "
                    FROM domain_field_data
                    WHERE domain_id = :did");
                $stmt->bindValue('did', $did, PDO::PARAM_INT);
                $stmt->execute();
                $result_data = $stmt->fetch();
                $stmt->closeCursor();
                $field_data = $result_data->{$row->field_name};

                // A Date or Time Stamp that was never set shows blank rather than its placeholder date
                if (($row->type_id == "4" || $row->type_id == "5") && DomainMOD\Time::isUnset($field_data)) {

                    $field_data = '';

                }

            }

            if ($row->type_id == "1") { // Check Box

                echo $form->showCheckbox(DomainMOD\CustomField::inputName($row->field_name), '1', $row->name, $row->description, $field_data, '', '');

            } elseif ($row->type_id == "2") { // Text

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $field_data, '255', '', '', '', '');

            } elseif ($row->type_id == "3") { // Text Area

                echo $form->showInputTextarea(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $field_data, '', '<div class="domainmod-span-all">', '</div>');

            } elseif ($row->type_id == "4") { // Date

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $field_data, '10', '', '', '', '', '', 'ltr');

            } elseif ($row->type_id == "5") { // Time Stamp

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $field_data, '19', '', '', '', '', '', 'ltr');

            } elseif ($row->type_id == "6") { // URL

                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $field_data, '255', '', '', '', '', '', 'ltr');

            }

        }

    }

}

echo $layout->formSection(_('Custom Fields'), 'custom-fields', ob_get_clean());

echo $form->showInputHidden('did', $did);
echo $form->showInputHidden('new_domain', $new_domain);
echo $form->showInputHidden('new_tld', $new_tld);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

// The domain's Change History, read-only and outside the form
echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'domain', $did, false, DomainMOD\DomainRefresh::statusLine($pdo, $did));

$dwaccounts = new DomainMOD\DwAccounts();
$dw_has_accounts = $dwaccounts->checkForAccounts($new_domain);

$dwzones = new DomainMOD\DwZones();
$dw_has_zones = $dwzones->checkForZones($new_domain);

if ($dw_has_accounts === 1 || $dw_has_zones === 1) { ?>

    <h2 class="h3 mt-5"><?php echo sprintf(_('Data Warehouse Information for %s'), htmlentities($new_domain, ENT_QUOTES, 'UTF-8')); ?></h2><?php

}

if ($dw_has_accounts === 1) { ?>

    <?php echo $layout->expandableBoxTop(_('Accounts'), '', '', 3); ?><?php

    $stmt = $pdo->prepare("
        SELECT s.id
        FROM dw_accounts AS a, dw_servers AS s
        WHERE a.server_id = s.id
          AND a.domain = :new_domain
        GROUP BY s.id, s.name
        ORDER BY s.name ASC");
    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    // The account's figures in their own columns, without the Domain column (this page is about the domain), and the
    // rest in Responsive's details row (DwDisplay::accountColumns())
    $dwdisplay = new DomainMOD\DwDisplay(); ?>

<table id="<?php echo $slug; ?>-account" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
        <?php echo implode('', DomainMOD\DwDisplay::accountHeaderCells(false)); ?>
    </tr>
    </thead>
    <tbody><?php

    foreach ($result as $row) {

        foreach ($dwdisplay->getAccount($row->id, $new_domain) as $account) { ?>

        <tr>
        <td></td>
        <?php echo implode('', DomainMOD\DwDisplay::accountCells($account, false)); ?>
        </tr><?php

        }

    } ?>

    </tbody>
    </table>
    <?php echo $layout->expandableBoxBottom(); ?><?php

}

if ($dw_has_zones === 1) { ?>

    <?php echo $layout->expandableBoxTop(_('DNS Zones & Records'), '', '', 3); ?><?php

    $stmt = $pdo->prepare("
        SELECT z.id AS zone_id, s.name
        FROM dw_dns_zones AS z, dw_servers AS s
        WHERE z.server_id = s.id
          AND z.domain = :new_domain
        ORDER BY s.name, z.zonefile, z.domain");
    $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

<table id="<?php echo $slug; ?>-zone" class="<?php echo $datatable_class; ?>">
    <thead>
    <tr>
        <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
        <th><?php echo _('Server'); ?></th>
        <th><?php echo _('Records'); ?></th>
    </tr>
    </thead>
    <tbody><?php

    foreach ($result as $row) { ?>

        <tr>
        <td></td>
        <td><?php echo $row->name; ?></td>
        <td><?php echo $dwdisplay->records($row->zone_id); ?></td>
        </tr><?php

    } ?>

    </tbody>
    </table>
    <?php echo $layout->expandableBoxBottom(); ?><?php
}

// TRANSLATORS: The first %s is the domain's name, and the second is the app's name, DomainMOD.
$layout->dangerZone(_('Domain'), $new_domain, 'edit.php?did=' . $did . '&del=1', _('Delete this domain'), sprintf(_('This removes %s and its details from %s. Any linked SSL certificates are kept without a domain. Deleting the domain can\'t be undone.'), '<strong>' . $layout->ltr(html_entity_decode($new_domain, ENT_QUOTES, 'UTF-8')) . '</strong>', SOFTWARE_TITLE));
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/domain-refresh.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/hosting-service-ip.js'); ?>"></script>
</body>
</html>
