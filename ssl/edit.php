<?php
/**
 * /ssl/edit.php
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
$log = new DomainMOD\Log('/ssl/edit.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$assets = new DomainMOD\Assets();
$ssl_queue = new DomainMOD\SslQueue();

$timestamp = $time->stamp();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/ssl-edit.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$sslcid = (int) ($_REQUEST['sslcid'] ?? 0);
$new_domain_id = (int) ($_POST['new_domain_id'] ?? 0);
$new_name = is_string($_POST['new_name'] ?? null) ? $sanitize->text(trim($_POST['new_name'])) : '';
$new_type_id = (int) ($_POST['new_type_id'] ?? 0);
$new_ip_id = (int) ($_POST['new_ip_id'] ?? 0);
$new_cat_id = (int) ($_POST['new_cat_id'] ?? 0);
$new_expiry_date = $_POST['datepick'] ?? '';
$new_account_id = (int) ($_POST['new_account_id'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $custom_field->getFieldTypes('ssl_cert_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

// The Refresh form posts here too, and is handled on its own, before the save handler.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['refresh_ssl'] ?? '') === '1') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $refresh = new DomainMOD\SslRefresh();
    // The name and domain the page was drawn with, so a change made in another window since then stops the refresh
    $expected_name = is_string($_POST['expected_name'] ?? null) ? $_POST['expected_name'] : '';
    $expected_domain_id = (int) ($_POST['expected_domain_id'] ?? 0);

    // The Refresh button at the top of the page runs in place (htmx, see js/ssl-refresh.js): it answers with the result
    // message and, after a refresh that worked, the saved values of the fields Refresh owns, so the page can update them
    // in the form. Without JavaScript it's a normal post, handled below.
    if ($system->isHtmxRequest()) {

        $refresh_values = function ($pdo, $sslcid) {
            $stmt = $pdo->prepare("
                SELECT expiry_date, active, notes
                FROM ssl_certs
                WHERE id = :sslcid");
            $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();
            $stmt->closeCursor();
            return $row;
        };

        // A new refresh of this certificate clears any result stored by an earlier one without JavaScript (below)
        if ((int) ($_SESSION['s_ssl_refresh_result']['sslcid'] ?? 0) === $sslcid) unset($_SESSION['s_ssl_refresh_result']);

        $before = $refresh_values($pdo, $sslcid);
        list($refreshed, $refresh_lines, $refresh_parts) = $refresh->refresh($sslcid, (int) $_SESSION['s_user_id'], $expected_name, $expected_domain_id);

        header('X-DomainMOD-Result: 1');
        echo '<div data-domainmod-focus>';
        // The compact result: a summary, any reasons, the changes, and the notes behind "Why?"
        echo $system->showMessageResult($refreshed ? 'success' : 'danger', $refresh_parts);

        if ($refreshed) {

            $after = $refresh_values($pdo, $sslcid);

            if ($after) {

                $values = array(
                    'expiry_date' => (string) $after->expiry_date,
                    'active' => (int) $after->active,
                    // Notes are stored escaped, so they're unescaped the same way the notes field shows them
                    'notes' => $unsanitize->text($after->notes));

                $changed = array();
                foreach (array('expiry_date', 'active', 'notes') as $field) {
                    if (!$before || (string) $before->$field !== (string) $after->$field) $changed[] = $field;
                }
                $values['changed'] = $changed;

                $json = json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
                echo '<div id="domainmod-refresh-data" hidden data-values="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"></div>';

            }

        }

        echo '</div>';

        // The Change History section, redrawn with the refresh's changes, replaces the page's (an out-of-band swap)
        echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'ssl', $sslcid, true);
        exit;

    }

    list($refreshed, $refresh_lines, $refresh_parts) = $refresh->refresh($sslcid, (int) $_SESSION['s_user_id'], $expected_name, $expected_domain_id);

    $stmt = $pdo->prepare("
        SELECT id
        FROM ssl_certs
        WHERE id = :sslcid");
    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
    $stmt->execute();
    $refresh_exists = $stmt->fetchColumn();
    $stmt->closeCursor();

    if ($refresh_exists && $ssl_queue->isAvailable()) {

        // The result is shown under the Refresh button after the redirect, on this certificate's page only (see below)
        $_SESSION['s_ssl_refresh_result'] = array('sslcid' => $sslcid, 'type' => $refreshed ? 'success' : 'danger', 'parts' => $refresh_parts);
        header('Location: edit.php?sslcid=' . $sslcid);

    } else {

        // Without the Refresh section (the certificate is gone, or the database isn't updated yet), the result is a page
        // message instead
        $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . implode(' ', array_merge(array($refresh_parts['summary']), $refresh_parts['reasons'])) . '<BR>';
        header($refresh_exists ? 'Location: edit.php?sslcid=' . $sslcid : 'Location: index.php');

    }

    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');

    $date = new DomainMOD\Date();

    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is saved
    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_account_id !== 0 &&
        $new_type_id !== 0 && $new_ip_id !== 0 && $new_cat_id !== 0 && $custom_fields_valid
    ) {

        try {

            $pdo->beginTransaction();

            // The Save is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_USER, $_SESSION['s_user_id']);

            // The domain is optional, and 0 is None. A chosen domain may have been deleted while the form was open, and
            // then nothing is saved. The read locks the domain row before the certificate's own (below), the same order
            // as the domain deletes, the SSL Queue, and SSL Refresh, so a domain delete running at the same moment waits
            // for this save, and its unlink then sees the saved certificate. A negative id, which only a changed form can
            // send, is refused the same way.
            if ($new_domain_id !== 0) {

                $domain_exists = false;

                if ($new_domain_id > 0) {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM domains
                        WHERE id = :new_domain_id
                        FOR UPDATE");
                    $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $domain_exists = $stmt->fetchColumn();
                    $stmt->closeCursor();

                }

                if (!$domain_exists) {

                    if ($pdo->InTransaction()) $pdo->rollback();
                    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The domain you chose no longer exists. Please choose another one.') . '<BR>';
                    header("Location: edit.php?sslcid=" . $sslcid . "");
                    exit;

                }

            }

            // If the record was deleted while the form was open, nothing is saved. A record that's found stays locked
            // until the save ends, so its stored name and domain can be compared with the saved ones below.
            $stmt = $pdo->prepare("
                SELECT id, `name`, domain_id
                FROM ssl_certs
                WHERE id = :record_id
                FOR UPDATE");
            $stmt->bindValue('record_id', $sslcid, PDO::PARAM_INT);
            $stmt->execute();
            $stored_record = $stmt->fetch();
            $stmt->closeCursor();

            if (!$stored_record) {

                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That SSL certificate no longer exists.') . '<BR>';
                header("Location: index.php");
                exit;

            }

            // The certificate as it was (already locked above), so only what this Save really changes is recorded
            $history_before = $history_op->capture('ssl', array($sslcid));

            // The stored domain as the page shows it: a stored id with no domains row counts as no domain (0), so saving
            // such a certificate unchanged (it shows None and posts 0) doesn't count as a new domain below
            $stored_domain_id = (int) $stored_record->domain_id;

            if ($stored_domain_id !== 0) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM domains
                    WHERE id = :stored_domain_id");
                $stmt->bindValue('stored_domain_id', $stored_domain_id, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetchColumn()) $stored_domain_id = 0;
                $stmt->closeCursor();

            }

            $stmt = $pdo->prepare("
                SELECT ssl_provider_id, owner_id
                FROM ssl_accounts
                WHERE id = :new_account_id");
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stmt->closeCursor();

            if (!$result) {

                // the account was deleted while the form was open (or the form was changed), so nothing is saved
                if ($pdo->InTransaction()) $pdo->rollback();
                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The SSL provider account you chose no longer exists. Please choose another one.') . '<BR>';
                header("Location: edit.php?sslcid=" . $sslcid . "");
                exit;

            }

            $new_ssl_provider_id = $result->ssl_provider_id;
            $new_owner_id = $result->owner_id;

            $stmt = $pdo->prepare("
                SELECT id
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchColumn();

            if (!$result) {

                $temp_fee_id = "0";
                $temp_fee_fixed = "0";

            } else {

                $temp_fee_id = $result;
                $temp_fee_fixed = "1";

            }

            $stmt = $pdo->prepare("
                SELECT (renewal_fee + misc_fee) AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = :new_ssl_provider_id
                  AND type_id = :new_type_id");
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->execute();
            $new_total_cost = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                UPDATE ssl_certs
                SET owner_id = :new_owner_id,
                    ssl_provider_id = :new_ssl_provider_id,
                    account_id = :new_account_id,
                    domain_id = :new_domain_id,
                    `name` = :new_name,
                    type_id = :new_type_id,
                    ip_id = :new_ip_id,
                    cat_id = :new_cat_id,
                    expiry_date = :new_expiry_date,
                    fee_id = :temp_fee_id,
                    total_cost = :new_total_cost,
                    notes = :new_notes,
                    active = :new_active,
                    fee_fixed = :temp_fee_fixed,
                    update_time = :timestamp
                WHERE id = :sslcid");
            $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ssl_provider_id', $new_ssl_provider_id, PDO::PARAM_INT);
            $stmt->bindValue('new_account_id', $new_account_id, PDO::PARAM_INT);
            $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
            $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
            $stmt->bindValue('new_type_id', $new_type_id, PDO::PARAM_INT);
            $stmt->bindValue('new_ip_id', $new_ip_id, PDO::PARAM_INT);
            $stmt->bindValue('new_cat_id', $new_cat_id, PDO::PARAM_INT);
            $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
            $stmt->bindValue('temp_fee_id', $temp_fee_id, PDO::PARAM_INT);
            $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
            $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
            $stmt->bindValue('temp_fee_fixed', $temp_fee_fixed, PDO::PARAM_INT);
            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
            $stmt->execute();

            // A new name or domain means the certificate's last lookup describes another site, so its details are cleared.
            // The values are compared as stored (the database can cut a name that's too long), not as posted.
            $stmt = $pdo->prepare("
                SELECT `name`, domain_id
                FROM ssl_certs
                WHERE id = :sslcid");
            $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
            $stmt->execute();
            $saved_record = $stmt->fetch();
            $stmt->closeCursor();

            if (!$saved_record || (string) $saved_record->name !== (string) $stored_record->name
                || (int) $saved_record->domain_id !== $stored_domain_id) {
                // The issuer the reset clears is recorded going to none, read under the status row's lock first
                if ($history_op->enabled()) {
                    $stmt = $pdo->prepare("
                        SELECT refreshed_issuer
                        FROM ssl_cert_status
                        WHERE ssl_id = :sslcid
                        FOR UPDATE");
                    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
                    $stmt->execute();
                    $old_issuer = (string) $stmt->fetchColumn();
                    $stmt->closeCursor();
                    if ($old_issuer !== '') $history_op->record('ssl', $sslcid, 'issuer', $old_issuer, '');
                }
                $ssl_queue->resetStatus($sslcid);
            }

            $result = $pdo->query("
                SELECT field_name
                FROM ssl_cert_fields
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
                        UPDATE ssl_cert_field_data
                        SET `" . $field . "` = :full_field,
                            update_time = :timestamp
                        WHERE ssl_id = :sslcid");
                    $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
                    $stmt->execute();

                }

            }

            $queryB = new DomainMOD\QueryBuild();

            $sql = $queryB->missingFees('ssl_certs');
            $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

            $history_op->compare($history_before);
            $history_op->finish();

            if ($pdo->InTransaction()) $pdo->commit();

            $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s updated'), $new_name) . '<BR>';

            // A certificate saved without a fee is hidden from the list, so the save says so (DomainMOD\FeeStatus)
            $fee_status = new DomainMOD\FeeStatus();
            $fee_row = $fee_status->sslFee($sslcid);
            if ($fee_row && !$fee_status->hasFee($fee_row)) $_SESSION['s_message_warning'] .= $fee_status->sslWarning($fee_row, true) . '<BR>';

            header('Location: edit.php?sslcid=' . $sslcid);
            exit;

        } catch (Exception $e) {

            if ($pdo->InTransaction()) $pdo->rollback();

            // Another request changed the same rows at the same moment: nothing was saved, and the form shows again with
            // what was typed (see domainmod_restore_is_conflict())
            if (domainmod_restore_is_conflict($e)) {
                $_SESSION['s_message_danger'] .= domainmod_restore_conflict_form_message() . '<BR>';
            } else {

                $log_message = 'Unable to update SSL certificate';
                $log_extra = array('Error' => $e);
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                throw $e;

            }

        }

    } else {

        if ($new_name == "") {
            $form->addFieldError('new_name', _('Enter the SSL certificate\'s Host / Label'));
        }
        if (!$date->checkDateFormat($new_expiry_date)) {
            $form->addFieldError('datepick', _('The expiry date you entered is invalid'));
        }

        // A chosen domain that was deleted while the form was open isn't in the menu any more, so it's marked here
        // rather than shown again as None
        if ($new_domain_id !== 0) {

            $domain_exists = false;

            if ($new_domain_id > 0) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM domains
                    WHERE id = :new_domain_id");
                $stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
                $stmt->execute();
                $domain_exists = $stmt->fetchColumn();
                $stmt->closeCursor();

            }

            if (!$domain_exists) {

                $form->addFieldError('new_domain_id', _('The domain you chose no longer exists. Please choose another one.'));

            }

        }

        if ($new_account_id === 0) {

            $form->addFieldError('new_account_id', _('Choose the SSL Provider Account'));

        }

        if ($new_type_id === 0) {

            $form->addFieldError('new_type_id', _('Choose the SSL Type'));

        }

        if ($new_ip_id === 0) {

            $form->addFieldError('new_ip_id', _('Choose the IP Address'));

        }

        if ($new_cat_id === 0) {

            $form->addFieldError('new_cat_id', _('Choose the Category'));

        }

    }

} else {

    $stmt = $pdo->prepare("
         SELECT sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS account_id, sslcf.id AS type_id, ip.id AS ip_id, cat.id AS cat_id
         FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
         WHERE sslc.account_id = sslpa.id
           AND sslc.type_id = sslcf.id
           AND sslc.ip_id = ip.id
           AND sslc.cat_id = cat.id
           AND sslc.id = :sslcid");
    $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $new_domain_id = $result->domain_id;
        $new_name = $result->name;
        $new_type_id = $result->type_id;
        $new_ip_id = $result->ip_id;
        $new_cat_id = $result->cat_id;
        $new_expiry_date = $result->expiry_date;
        $new_notes = $result->notes;
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

        // the record may have been deleted while the form was open; then nothing is saved
        $stmt = $pdo->prepare("
            SELECT id
            FROM ssl_certs
            WHERE id = :record_id");
        $stmt->bindValue('record_id', $sslcid, PDO::PARAM_INT);
        $stmt->execute();
        $record_exists = $stmt->fetchColumn();
        $stmt->closeCursor();

        if (!$record_exists) {

            if ($pdo->InTransaction()) $pdo->rollback();
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That SSL certificate no longer exists.') . '<BR>';
            header("Location: index.php");
            exit;

        }

        // The delete row, with a snapshot read under the certificate's lock
        $history_op->deleting('ssl', array($sslcid));

        $stmt = $pdo->prepare("
            DELETE FROM ssl_certs
            WHERE id = :sslcid");
        $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM ssl_cert_field_data
            WHERE ssl_id = :sslcid");
        $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
        $stmt->execute();

        $ssl_queue->deleteStatus(array($sslcid));

        $temp_type = $assets->getSslType($new_type_id);

        $system->checkExistingAssets();

        $history_op->finish();

        if ($pdo->InTransaction()) $pdo->commit();

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        $_SESSION['s_message_success'] .= sprintf(_('SSL Certificate %s (%s) deleted.'), $new_name, $temp_type) . '<BR>';

        header("Location: index.php");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to delete SSL certificate';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

}

// The certificate as it's saved (even after a failed Save, which shows the posted values): Refresh checks the saved
// name's host, and the domain menu always lists the saved domain, so a changed domain can be put back
$stmt = $pdo->prepare("
    SELECT c.`name`, c.domain_id, d.domain
    FROM ssl_certs AS c
    LEFT JOIN domains AS d ON d.id = c.domain_id
    WHERE c.id = :sslcid");
$stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
$stmt->execute();
$refresh_record = $stmt->fetch();
$stmt->closeCursor();

// A certificate whose stored domain no longer exists has no domain, so its menu starts on None (Refresh's hidden
// expected_domain_id below still holds the stored id, for the server's check)
if ($_SERVER['REQUEST_METHOD'] != 'POST' && $refresh_record && $refresh_record->domain === null) $new_domain_id = 0;
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
// Refresh, at the top of the page in its own small form (read-only users can't change data, so they don't see it). It
// runs in place with htmx (see js/ssl-refresh.js), and without JavaScript it's a normal post. Its table comes with the
// 4.27.0 database update, so it's only shown after that.
if (($_SESSION['s_read_only'] ?? '') != '1' && $refresh_record && $ssl_queue->isAvailable()) {

    $refresh = new DomainMOD\SslRefresh();
    $saved_name = $unsanitize->text((string) $refresh_record->name);

    // The host Refresh checks, or why it can't check one. A certificate with no domain (or whose domain is gone) has the
    // domain name '', and refreshes its own hostname.
    $saved_domain = $unsanitize->text((string) $refresh_record->domain);
    list($refresh_host_status, $refresh_host) = $refresh->hostCheck($saved_name, $saved_domain);

    if ($refresh_host_status === 'ok') {
        // TRANSLATORS: %s is the hostname whose certificate Refresh reads, such as example.com.
        $refresh_source = sprintf(_('Updates the expiry date and status from the certificate %s serves now.'), $layout->ltr($refresh_host));
    } else {
        $refresh_source = $refresh->refusalMessage($refresh_host_status, $saved_name, $saved_domain);
    }

    $refresh_details = _("Read the certificate this hostname serves now and update this SSL certificate's expiry date and status when they've changed, with a note saying what changed. A pending status is kept, and the SSL provider, account, type, and fee aren't changed."); ?>

    <div class="domainmod-refresh-row d-flex flex-wrap align-items-center justify-content-end gap-2">
        <span class="text-body-secondary small"><?php echo $refresh_source; ?></span>
        <form method="post" class="d-inline-flex align-items-center" hx-post="edit.php?sslcid=<?php echo $sslcid; ?>" hx-target="#domainmod-refresh-result" hx-swap="innerHTML" hx-indicator="#domainmod-refresh-spinner" hx-disabled-elt="find button" hx-sync="this:drop" data-domainmod-refresh>
            <?php echo $form->showCsrfField(); ?>
            <?php echo $form->showInputHidden('refresh_ssl', '1'); ?>
            <?php echo $form->showInputHidden('sslcid', $sslcid); ?>
            <?php echo $form->showInputHidden('expected_name', $saved_name); ?>
            <?php echo $form->showInputHidden('expected_domain_id', (int) $refresh_record->domain_id); ?>
            <button type="submit" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($refresh_details, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?php echo _('Refresh'); ?></button>
            <?php echo $layout->htmxSpinner('domainmod-refresh-spinner'); ?>
        </form>
    </div>
    <div id="domainmod-refresh-result" class="domainmod-refresh-result"><?php
    // A result stored by a refresh without JavaScript, shown once, and only on the page of the certificate it belongs to
    $stored_refresh = $_SESSION['s_ssl_refresh_result'] ?? null;
    if (is_array($stored_refresh) && (int) ($stored_refresh['sslcid'] ?? 0) === $sslcid) {
        echo '<div data-domainmod-focus>' . $system->showMessageResult($stored_refresh['type'] === 'danger' ? 'danger' : 'success', $stored_refresh['parts']) . '</div>';
        unset($_SESSION['s_ssl_refresh_result']);
    } ?></div>

    <?php // Asks before a refresh replaces fields the user has changed but not saved, and stops one after an unsaved name or domain change (see js/ssl-refresh.js) ?>
    <div class="modal fade" id="domainmod-refresh-confirm" tabindex="-1" aria-labelledby="domainmod-refresh-confirm-title" aria-hidden="true" data-message="<?php echo htmlspecialchars(_("You've changed %s but haven't saved. Refreshing saves the looked-up details straight away and replaces what you typed in those fields."), ENT_QUOTES, 'UTF-8'); ?>" data-name-message="<?php echo htmlspecialchars(_('Save or undo your changes to the name or domain first, so Refresh checks the right site.'), ENT_QUOTES, 'UTF-8'); ?>">
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-domainmod-refresh-cancel><?php echo _('Cancel'); ?></button>
                    <button type="button" class="btn btn-secondary d-none" data-bs-dismiss="modal" data-domainmod-refresh-close><?php echo _('Close'); ?></button>
                    <button type="button" class="btn btn-danger" data-domainmod-refresh-anyway><?php echo _('Refresh anyway'); ?></button>
                </div>
            </div>
        </div>
    </div><?php

    // The saved name and domain decide which site Refresh checks, so the script compares the form with them. A
    // certificate whose domain is gone shows None in the menu, so its saved domain is 0 here too.
    $saved_domain_id = ($refresh_record->domain === null) ? 0 : (int) $refresh_record->domain_id;
    echo '<div hidden data-domainmod-refresh-saved data-name="' . htmlspecialchars($saved_name, ENT_QUOTES, 'UTF-8') . '" data-domain-id="' . $saved_domain_id . '"></div>';

    // A page shown again after a failed Save holds the user's unsaved values, not the saved ones
    if ($_SERVER['REQUEST_METHOD'] == 'POST') echo '<div hidden data-domainmod-refresh-unsaved></div>';

}

echo $form->showFormTop('');
// The fields in titled cards, the same way as the edit domain page. Each card's fields are built first, so a card
// whose menus all have nothing to choose from is left out rather than drawn empty. Moving a field to another card is
// a matter of moving its lines between these blocks.
ob_start();
echo $form->showInputText('new_name', _('Host') . ' / ' . _('Label') . ' (100)', '', $unsanitize->text($new_name), '100', '', '1', '', '');

$stmt = $pdo->prepare("
    SELECT id, domain
    FROM domains
    WHERE (active NOT IN ('0', '10') OR id = :new_domain_id OR id = :saved_domain_id)
    ORDER BY domain");
$stmt->bindValue('new_domain_id', $new_domain_id, PDO::PARAM_INT);
$stmt->bindValue('saved_domain_id', $refresh_record ? (int) $refresh_record->domain_id : 0, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

// Shown even when there are no domains, so None is always there and the saved choice is always posted
echo $form->showDropdownTop('new_domain_id', _('Domain'), '', '', '');
// TRANSLATORS: The Domain menu's choice for an SSL certificate that isn't linked to a domain.
echo $form->showDropdownOption('0', _('None'), $new_domain_id);

foreach ($result as $row) {

    echo $form->showDropdownOption($row->id, $row->domain, $new_domain_id);

}

echo $form->showDropdownBottom('', 'new_domain_id');

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

echo $form->showDropdownTop('new_active', _('Certificate Status'), '', '', '');
echo $form->showDropdownOption('1', _('Active'), $new_active);
echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
echo $form->showDropdownOption('0', _('Expired'), $new_active);
echo $form->showDropdownBottom('');

echo $layout->formSection(_('Details'), 'details', ob_get_clean());

ob_start();
$result = $pdo->query("
    SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
    FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
    WHERE sslpa.owner_id = o.id
      AND sslpa.ssl_provider_id = sslp.id
    ORDER BY sslp_name ASC, o_name ASC, sslpa.username ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_account_id', _('SSL Provider Account'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->sslp_name . ', ' . $row->o_name . ' (' . $row->username . ')', $new_account_id);

    }

    echo $form->showDropdownBottom('', 'new_account_id');

}

$result = $pdo->query("
    SELECT id, type
    FROM ssl_cert_types
    ORDER BY type ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_type_id', _('Certificate Type'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->type, $new_type_id);

    }

    echo $form->showDropdownBottom('', 'new_type_id');
}

echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $new_expiry_date, '10', '', '1', '', '');
echo $layout->formSection(_('Certificate'), 'certificate', ob_get_clean());

// The saved fee for the certificate's type at its provider, or what's missing, with a link to edit or add it for users
// who can edit fees (DomainMOD\FeeStatus)
$fee_status = new DomainMOD\FeeStatus();
$fee_row = $fee_status->sslFee($sslcid);
if ($fee_row) echo $layout->formSection(_('Fee'), 'fee', $fee_status->sslSection($fee_row, ($_SESSION['s_read_only'] ?? '') != '1'));

ob_start();
$result = $pdo->query("
    SELECT id, ip, `name`
    FROM ip_addresses
    ORDER BY `name`, ip")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_ip_id', _('IP Address'), '', '1', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name . ' (' . $row->ip . ')', $new_ip_id);

    }

    echo $form->showDropdownBottom('', 'new_ip_id');
}

echo $layout->formSection(_('Hosting'), 'hosting', ob_get_clean());

ob_start();
if ($new_notes != '') {
    // Opens the notes on their own page, in a new tab
    $subtext = '<a target="_blank" href="notes.php?sslcid=' . $sslcid . '">' . _('View Full Notes')
        . '<i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>';
} else {
    $subtext = '';
}
echo $form->showInputTextarea('new_notes', _('Notes'), $subtext, $unsanitize->text($new_notes), '', '<div class="domainmod-span-all">', '</div>');

echo $layout->formSection(_('Notes'), 'notes', ob_get_clean());

ob_start();
$result = $pdo->query("
    SELECT field_name
    FROM ssl_cert_fields
    ORDER BY type_id, `name`")->fetchAll();

if ($result) {

    $count = 0;

    foreach ($result as $row) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $result = $pdo->query("
            SELECT sf.name, sf.field_name, sf.type_id, sf.description
            FROM ssl_cert_fields AS sf, custom_field_types AS cft
            WHERE sf.type_id = cft.id
              AND sf.field_name = '" . $field . "'")->fetchAll();

        foreach ($result as $row) {

            // A save that failed shows what was entered, blank or not, rather than the stored value
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {

                $field_data = $new_custom_fields[$field] ?? '';

            } else {

                $stmt = $pdo->prepare("
                    SELECT " . $row->field_name . "
                    FROM ssl_cert_field_data
                    WHERE ssl_id = :sslcid");
                $stmt->bindValue('sslcid', $sslcid, PDO::PARAM_INT);
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

echo $form->showInputHidden('sslcid', $sslcid);
echo $form->showSubmitButton(_('Save'), '', '');
echo $form->showFormBottom('');

// The certificate's Change History, read-only and outside the form
echo DomainMOD\ChangeHistoryListColumns::editSection($pdo, 'ssl', $sslcid);

$layout->dangerZone(_('SSL Certificate'), $new_name, 'edit.php?sslcid=' . $sslcid . '&del=1', _('Delete this SSL certificate'), sprintf(_('This removes %s and its details from %s. It can\'t be undone.'), '<strong>' . htmlentities(html_entity_decode($new_name, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</strong>', SOFTWARE_TITLE));
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/ssl-refresh.js'); ?>"></script>
</body>
</html>
