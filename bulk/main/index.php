<?php
/**
 * /bulk/main/index.php
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
$log = new DomainMOD\Log('/bulk/main/index.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$domain = new DomainMOD\Domain();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

$timestamp = $time->stamp();
$timestamp_basic = $time->userToday();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/bulk-main.inc.php';

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? '');
$pdo = $deeb->cnxx;

$action = isset($_REQUEST['action']) ? $sanitize->text($_REQUEST['action']) : '';
$raw_domain_list = isset($_POST['raw_domain_list']) ? $sanitize->text($_POST['raw_domain_list']) : '';
$new_expiry_date = $_POST['datepick'] ?? '';
$new_function = isset($_POST['new_function']) ? $sanitize->text($_POST['new_function']) : '';
$new_pcid = (int) ($_POST['new_pcid'] ?? 0);
$new_dnsid = (int) ($_POST['new_dnsid'] ?? 0);
$new_ipid = (int) ($_POST['new_ipid'] ?? 0);
// The optional hosting service for Add Domains and Change Hosting Service (0 is None), once hosting is available. A
// malformed value is -1, refused before any lock or write.
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$new_service_id = $hosting_available ? DomainMOD\Hosting::postedInt($_POST['new_service_id'] ?? '0', -1) : 0;
$new_raid = (int) ($_POST['new_raid'] ?? 0);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0);
$new_privacy = (int) ($_POST['new_privacy'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';

/*
 * For the Change History: locks the domains on the list in id order and reads their recorded columns before an action's
 * statements, for compare() once they've run: only the fields the action writes (and the notes when a note was typed,
 * or when the action always adds its own line, as Force Renew does), so a large list holds as little as it can.
 */
/*
 * The listed domains' ids by lower-case name, read before the action's transaction starts (a plain read inside it would
 * open its snapshot early), so the action can lock them by id in ascending order, as every writer does
 */
$bulk_known_ids = array();
$bulk_lookup_ids = function (array $domain_names) use ($pdo) {
    $known = array();
    foreach (array_chunk(array_values(array_unique($domain_names)), 60000) as $names) {
        $stmt = $pdo->prepare("
            SELECT id, domain
            FROM domains
            WHERE domain IN (" . implode(', ', array_fill(0, count($names), '?')) . ")");
        $stmt->execute($names);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $known[strtolower((string) $row['domain'])][] = (int) $row['id'];
        $stmt->closeCursor();
    }
    return $known;
};

/*
 * Locks the listed domains, inside the action's transaction, and returns their ids in ascending order: first by primary
 * key in ascending id order (an IN() on the domain index would lock them in name order instead), keeping only rows that
 * still have a listed name, then a locking read by name for every listed name, which only adds rows that appeared since
 * the lookup (a domain added or renamed), so every row with a listed name is included, duplicates too
 */
$bulk_lock_ids = function (array $domain_names) use ($pdo, &$bulk_known_ids) {
    $wanted = array();
    foreach ($domain_names as $name) $wanted[strtolower((string) $name)] = (string) $name;
    $ids = array();
    foreach ($wanted as $key => $name) {
        foreach ($bulk_known_ids[$key] ?? array() as $id) $ids[] = $id;
    }
    $ids = array_values(array_unique($ids));
    sort($ids);

    $locked = array();
    foreach (array_chunk($ids, 1000) as $chunk) {
        $stmt = $pdo->query("
            SELECT id, domain
            FROM domains FORCE INDEX (PRIMARY)
            WHERE id IN (" . implode(',', $chunk) . ")
            ORDER BY id
            FOR UPDATE");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($wanted[strtolower((string) $row['domain'])])) $locked[(int) $row['id']] = true;
        }
        $stmt->closeCursor();
    }

    foreach (array_chunk(array_values($wanted), 60000) as $names) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM domains
            WHERE domain IN (" . implode(', ', array_fill(0, count($names), '?')) . ")
            ORDER BY id
            FOR UPDATE");
        $stmt->execute($names);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $locked[(int) $id] = true;
        $stmt->closeCursor();
    }

    $ids = array_keys($locked);
    sort($ids);
    return $ids;
};

/*
 * Locked ids in the order their domains were pasted (then by id, with rows that appeared since the lookup last), so
 * Change History lists the action's rows in that order. Only for recording: the locks are taken by $bulk_lock_ids, in
 * its order.
 */
$bulk_pasted_order = function (array $ids, array $domain_names) use (&$bulk_known_ids) {
    $position = array();
    foreach ($domain_names as $name) {
        foreach ($bulk_known_ids[strtolower((string) $name)] ?? array() as $id) {
            if (!isset($position[(int) $id])) $position[(int) $id] = count($position);
        }
    }
    usort($ids, function ($a, $b) use ($position) {
        return array($position[(int) $a] ?? PHP_INT_MAX, (int) $a) <=> array($position[(int) $b] ?? PHP_INT_MAX, (int) $b);
    });
    return $ids;
};

$bulk_history_capture = function (DomainMOD\ChangeHistoryOperation $op, array $domain_names) use ($new_notes, $action, $bulk_lock_ids, $bulk_pasted_order) {
    // The listed domains, locked by $bulk_lock_ids, then captured in the order they were pasted
    $ids = $bulk_pasted_order($bulk_lock_ids($domain_names), $domain_names);
    // The fields each action writes (every status action writes only the status)
    $action_fields = array('RENEW' => array('expiry_date'), 'FR' => array('expiry_date', 'active', 'notes'),
        'CPC' => array('cat_id'), 'CDNS' => array('dns_id'), 'CIP' => array('ip_id'), 'CWH' => array('service_id'),
        'CRA' => array('account_id', 'owner_id', 'registrar_id', 'total_cost'), 'AURNE' => array('autorenew'),
        'AURND' => array('autorenew'), 'PRVE' => array('privacy', 'total_cost'), 'PRVD' => array('privacy', 'total_cost'),
        'CED' => array('expiry_date'), 'AN' => array('notes'));
    $fields = $action_fields[$action] ?? array('active');
    if ($new_notes !== '') $fields[] = 'notes';
    return $op->capture('domain', $ids, array_values(array_unique($fields)), false);
};
$new_renewal_years = (int) ($_POST['new_renewal_years'] ?? 0);
$is_submitted = (int) ($_POST['is_submitted'] ?? 0);

// Change Hosting Service needs the hosting tables, so before the database update it's refused, whatever is posted, and
// the page starts again at the action menu, which doesn't offer it
if ($action == 'CWH' && !$hosting_available) {
    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    $action = '';
    $is_submitted = 0;
}

// Custom Fields. Their values are kept in their own array, keyed by field name, so a field named like one of the
// settings above (such as notes) can't overwrite it (see DomainMOD\CustomField::inputName()).
$custom_field = new DomainMOD\CustomField();
$custom_field_types = $custom_field->getFieldTypes('domain_fields');
$new_custom_fields = DomainMOD\CustomField::postedValues(array_keys($custom_field_types), $_POST);

$choose_text = _('Click here to choose the new');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    $format = new DomainMOD\Format();

    $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

    if ($raw_domain_list == "") {

        $form->addFieldError('raw_domain_list', _('Enter the list of domains to apply the action to'));

        if ($action == 'AN' && $new_notes == '') {

            $form->addFieldError('new_notes', _('Enter the new Note'));

        }

    } else {

        if ($action == 'AN' && $new_notes == '') {

            $form->addFieldError('new_notes', _('Enter the new Note'));

        } else {

            list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

            if ($raw_domain_list == "" || $invalid_domains == 1) {

                if ($invalid_domains == 1) {

                    if ($invalid_count == 1) {

                        $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                    } else {

                        $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list.'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                        if (($invalid_count - $invalid_to_display) == 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

                        } elseif (($invalid_count - $invalid_to_display) > 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
                        }

                    }

                } else {

                    $form->addFieldError('raw_domain_list', _('Enter the list of domains to apply the action to'));

                }
                $submission_failed = 1;

            } elseif ($action != 'AD' && ($missing = $domain->findMissingDomains($domain_array))[1] > 0) {

                // Every action apart from Add Domains changes existing domains, so stop before any of them is touched
                list($missing_to_display, $missing_count) = $missing;
                $form->addFieldError('raw_domain_list', $domain->missingDomainsMessage($missing_to_display, $missing_count));
                $submission_failed = 1;

            } else {

                // The listed domains' ids, before any action's transaction starts (see $bulk_lock_ids)
                if ($action != 'AD') $bulk_known_ids = $bulk_lookup_ids($domain_array);
                // and, for a delete, their certificates' ids, so they're locked in id order too (see
                // DomainMOD\Domain::unlinkSslCertificatesRecorded())
                $bulk_known_ssl_ids = array();
                if ($action == 'DD' && $bulk_known_ids) {
                    foreach (array_chunk(array_merge(...array_values($bulk_known_ids)), 1000) as $bulk_chunk) {
                        $bulk_known_ssl_ids = array_merge($bulk_known_ssl_ids, array_map('intval', $pdo->query("
                            SELECT id
                            FROM ssl_certs
                            WHERE domain_id IN (" . implode(',', array_map('intval', $bulk_chunk)) . ")")->fetchAll(PDO::FETCH_COLUMN)));
                    }
                }

                if ($action == "RENEW") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_RENEW', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        foreach ($domain_array as $each_domain) {

                            $domain->renew($each_domain, $new_renewal_years, $new_notes);

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains Renewed') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to renew domains';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "AD") {

                    $date = new DomainMOD\Date();

                    // set by the check below when a domain is already in DomainMOD
                    $has_existing_domains = '0';

                    // cycle through domains here
                    foreach ($domain_array as $key => $new_domain) {

                        $stmt = $pdo->prepare("
                            SELECT domain
                            FROM domains
                            WHERE domain = :new_domain");
                        $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                        $stmt->execute();
                        $result = $stmt->fetchColumn();

                        if ($result) {

                            $has_existing_domains = '1';

                        }

                    }

                    // A custom Date or Time Stamp that isn't a real date or time is marked under its field, and nothing is added
                    $custom_fields_valid = DomainMOD\CustomField::markInvalidValues($form, $custom_field_types, $new_custom_fields);

                    if (!$date->checkDateFormat($new_expiry_date) || $new_pcid === 0 || $new_dnsid === 0 || $new_ipid === 0 || $new_service_id < 0 || $new_raid === 0 || $has_existing_domains == '1' || !$custom_fields_valid) {

                        if ($has_existing_domains == '1') $_SESSION['s_message_danger'] .= sprintf(_('At least one of the domains you entered already exists in %s. %s You should run the domain list through a Segment filter to determine which one(s).'), SOFTWARE_TITLE, '<BR><BR>') . '<BR>';
                        if (!$date->checkDateFormat($new_expiry_date)) $form->addFieldError('datepick', _('You have entered an invalid expiry date'));
                        if ($new_pcid === 0) $form->addFieldError('new_pcid', sprintf(_('Please choose the new %s'), _('Category')));
                        if ($new_dnsid === 0) $form->addFieldError('new_dnsid', sprintf(_('Please choose the new %s'), _('DNS Profile')));
                        if ($new_ipid === 0) $form->addFieldError('new_ipid', sprintf(_('Please choose the new %s'), _('IP Address')));
                        if ($new_service_id < 0) $form->addFieldError('new_service_id', DomainMOD\Hosting::serviceGoneMessage());
                        if ($new_raid === 0) $form->addFieldError('new_raid', sprintf(_('Please choose the new %s'), _('Registrar Account')));

                        $submission_failed = 1;

                    } elseif ($new_service_id !== 0 && !DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

                        // Domains added with a hosting service hold the hosting lock, taken before the transaction, so the
                        // service can't be deleted or cancelled between its check and the inserts (see DomainMOD\Hosting)
                        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The hosting service must still exist and not be cancelled. Nothing is added, and the form shows
                            // again with the service marked, when it isn't.
                            $service_error = DomainMOD\Hosting::serviceError($pdo, $new_service_id);
                            if ($service_error !== '') {
                                $pdo->rollback();
                                throw new DomainMOD\HostingServiceRefused($service_error);
                            }

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_AD', $new_notes);

                            $stmt = $pdo->prepare("
                                SELECT owner_id, registrar_id
                                FROM registrar_accounts
                                WHERE id = :raid");
                            $stmt->bindValue('raid', $new_raid, PDO::PARAM_INT);
                            $stmt->execute();
                            $result = $stmt->fetch();
                            $stmt->closeCursor();

                            if (!$result) {

                                // the account was deleted while the form was open (or the form was changed), so nothing is saved
                                if ($pdo->InTransaction()) $pdo->rollback();
                                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The registrar account you chose no longer exists. Please choose another one.') . '<BR>';
                                header("Location: index.php?action=" . urlencode($action) . "");
                                exit;

                            }

                            $temp_owner_id = $result->owner_id;
                            $temp_registrar_id = $result->registrar_id;

                            // cycle through domains here
                            foreach ($domain_array as $key => $new_domain) {

                                $new_tld = DomainMOD\Domain::tldOf($new_domain);

                                $stmt = $pdo->prepare("
                                    SELECT id
                                    FROM fees
                                    WHERE registrar_id = :registrar_id
                                      AND tld = :new_tld");
                                $stmt->bindValue('registrar_id', $temp_registrar_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                                $stmt->execute();
                                $temp_fee_id = $stmt->fetchColumn();

                                if ($temp_fee_id == '0' || $temp_fee_id == "") {

                                    $temp_fee_fixed = 0;
                                    $temp_fee_id = 0;

                                } else {

                                    $temp_fee_fixed = 1;

                                }

                                if ($new_privacy === 1) {

                                    $fee_string = "renewal_fee + privacy_fee + misc_fee";

                                } else {

                                    $fee_string = "renewal_fee + misc_fee";

                                }

                                $stmt = $pdo->prepare("
                                    SELECT (" . $fee_string . ") AS total_cost
                                    FROM fees
                                    WHERE registrar_id = :temp_registrar_id
                                      AND tld = :new_tld");
                                $stmt->bindValue('temp_registrar_id', $temp_registrar_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                                $stmt->execute();
                                $result = $stmt->fetchColumn();

                                if ($result) {

                                    $new_total_cost = $result;

                                } else {

                                    $new_total_cost = 0;

                                }

                                // Before the update, the old hosting_id column is still there, and is written as 0 so
                                // it doesn't take its old default of 1 (a real provider on some installs). A stopped
                                // upgrade that has dropped it writes the domain as the update does (see
                                // Hosting::legacyDomainColumn()).
                                if (!DomainMOD\Hosting::legacyDomainColumn($pdo)) {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO domains
                                        (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id,
                                         total_cost, dns_id, ip_id, service_id, `function`, notes, autorenew, privacy,
                                         creation_type_id, created_by, active, fee_fixed, insert_time)
                                        VALUES
                                        (:temp_owner_id, :temp_registrar_id, :new_raid, :new_domain, :new_tld, :new_expiry_date,
                                         :new_pcid, :temp_fee_id, :new_total_cost, :new_dnsid, :new_ipid, :new_service_id, :new_function,
                                         :new_notes, :new_autorenew, :new_privacy, :creation_type_id, :user_id, :new_active,
                                         :temp_fee_fixed, :timestamp)");
                                    $stmt->bindValue('new_service_id', $new_service_id, PDO::PARAM_INT);
                                } else {
                                    // Legacy: domains.hosting_id before 4.27.0
                                    $stmt = $pdo->prepare("
                                        INSERT INTO domains
                                        (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id,
                                         total_cost, dns_id, ip_id, hosting_id, `function`, notes, autorenew, privacy,
                                         creation_type_id, created_by, active, fee_fixed, insert_time)
                                        VALUES
                                        (:temp_owner_id, :temp_registrar_id, :new_raid, :new_domain, :new_tld, :new_expiry_date,
                                         :new_pcid, :temp_fee_id, :new_total_cost, :new_dnsid, :new_ipid, 0, :new_function,
                                         :new_notes, :new_autorenew, :new_privacy, :creation_type_id, :user_id, :new_active,
                                         :temp_fee_fixed, :timestamp)");
                                }
                                $stmt->bindValue('temp_owner_id', $temp_owner_id, PDO::PARAM_INT);
                                $stmt->bindValue('temp_registrar_id', $temp_registrar_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
                                $stmt->bindValue('new_domain', $new_domain, PDO::PARAM_STR);
                                $stmt->bindValue('new_tld', $new_tld, PDO::PARAM_STR);
                                $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                                $stmt->bindValue('new_pcid', $new_pcid, PDO::PARAM_INT);
                                $stmt->bindValue('temp_fee_id', $temp_fee_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_total_cost', strval($new_total_cost), PDO::PARAM_STR);
                                $stmt->bindValue('new_dnsid', $new_dnsid, PDO::PARAM_INT);
                                $stmt->bindValue('new_ipid', $new_ipid, PDO::PARAM_INT);
                                $stmt->bindValue('new_function', $new_function, PDO::PARAM_STR);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('new_autorenew', $new_autorenew, PDO::PARAM_INT);
                                $stmt->bindValue('new_privacy', $new_privacy, PDO::PARAM_INT);
                                $creation_type_id = $system->getCreationTypeId('Bulk Updater');
                                $stmt->bindValue('creation_type_id', $creation_type_id, PDO::PARAM_INT);
                                $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
                                $stmt->bindValue('new_active', $new_active, PDO::PARAM_INT);
                                $stmt->bindValue('temp_fee_fixed', $temp_fee_fixed, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->execute();

                                $temp_fee_id = 0;

                                $temp_domain_id = $pdo->lastInsertId('id');
                                $history_op->created('domain', $temp_domain_id);

                                $stmt = $pdo->prepare("
                                    INSERT INTO domain_field_data
                                    (domain_id, insert_time)
                                    VALUES
                                    (:temp_domain_id, :timestamp)");
                                $stmt->bindValue('temp_domain_id', $temp_domain_id, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->execute();

                                $result = $pdo->query("
                                    SELECT field_name
                                    FROM domain_fields
                                    ORDER BY `name`
                                    LOCK IN SHARE MODE")->fetchAll();

                                if ($result) {

                                    $field_array = array();

                                    foreach ($result as $row) {

                                        $field_array[] = $row->field_name;

                                    }

                                    foreach ($field_array as $field) {

                                        $stmt = $pdo->prepare("
                                            UPDATE domain_field_data
                                            SET {$field} = :full_field
                                            WHERE domain_id = :domain_id");
                                        $stmt->bindValue('full_field', $new_custom_fields[$field] ?? '', PDO::PARAM_STR);
                                        $stmt->bindValue('domain_id', $temp_domain_id, PDO::PARAM_INT);
                                        $stmt->execute();

                                    }

                                }

                            } // finish cycling through domains here

                            $queryB = new DomainMOD\QueryBuild();

                            $sql = $queryB->missingFees('domains');
                            $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                            $maint->updateSegments();

                            $maint->updateTlds();

                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Domains added') . '<BR>';

                        } catch (DomainMOD\HostingServiceRefused $e) {

                            $form->addFieldError('new_service_id', $e->getMessage());
                            $submission_failed = 1;

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to add domains';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        } finally {

                            if ($new_service_id !== 0) DomainMOD\Hosting::unlock($pdo);

                        }

                    }

                } elseif ($action == "FR") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_FR', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                        $sql = "SELECT domain, expiry_date
                                FROM domains
                                WHERE domain IN (" . $in_list . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($domain_array);
                        $result = $stmt->fetchAll();

                        foreach ($result as $row) {

                            $expiry_pieces = explode("-", $row->expiry_date);
                            $old_expiry = $expiry_pieces[0] . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
                            $new_expiry = $expiry_pieces[0] + $new_renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];

                            $renewal_years_string = sprintf(ngettext('%s Year', '%s Years', $new_renewal_years), $new_renewal_years);

                            $new_notes_renewal = $timestamp_basic . ' - ' . sprintf(_('Domain Renewed For %s'), $renewal_years_string);

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET expiry_date = :new_expiry,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', :new_notes_renewal, '\r\n\r\n', notes),
                                        active = '1',
                                        update_time = :update_time
                                    WHERE domain = :domain");
                                $stmt->bindValue('new_expiry', $new_expiry, PDO::PARAM_STR);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('new_notes_renewal', $new_notes_renewal, PDO::PARAM_LOB);
                                $stmt->bindValue('update_time', $timestamp, PDO::PARAM_STR);
                                $stmt->bindValue('domain', $row->domain, PDO::PARAM_STR);
                                $stmt->execute();

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET expiry_date = :new_expiry,
                                        notes = CONCAT(:new_notes_renewal, '\r\n\r\n', notes),
                                        active = '1',
                                        update_time = :update_time
                                    WHERE domain = :domain");
                                $stmt->bindValue('new_expiry', $new_expiry, PDO::PARAM_STR);
                                $stmt->bindValue('new_notes_renewal', $new_notes_renewal, PDO::PARAM_LOB);
                                $stmt->bindValue('update_time', $timestamp, PDO::PARAM_STR);
                                $stmt->bindValue('domain', $row->domain, PDO::PARAM_STR);
                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains Fully Renewed') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to fully renew domains';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "CPC") {

                    if ($new_pcid === 0) {

                        $form->addFieldError('new_pcid', sprintf(_('Please choose the new %s'), _('Category')));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CPC', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET cat_id  = :new_pcid,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :update_time
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_pcid', $new_pcid, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('update_time', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET cat_id  = :new_pcid,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_pcid', $new_pcid, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Category Changed') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change category';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "CDNS") {

                    if ($new_dnsid === 0) {

                        $form->addFieldError('new_dnsid', sprintf(_('Please choose the new %s'), _('DNS Profile')));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CDNS', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET dns_id  = :new_dnsid,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_dnsid', $new_dnsid, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET dns_id  = :new_dnsid,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_dnsid', $new_dnsid, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('DNS Profile Changed') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change DNS profile';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "CIP") {

                    if ($new_ipid === 0) {

                        $form->addFieldError('new_ipid', sprintf(_('Please choose the new %s'), _('IP Address')));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CIP', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET ip_id  = :new_ipid,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_ipid', $new_ipid, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET ip_id  = :new_ipid,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_ipid', $new_ipid, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('IP Address Changed') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change IP address';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "AN") {

                    if ($new_notes == "") {

                        $form->addFieldError('new_notes', _('Enter the new Note'));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_AN', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Note added') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to add note';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "CRA") {

                    if ($new_raid === 0) {

                        $form->addFieldError('new_raid', sprintf(_('Please choose the new %s'), _('Registrar Account')));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CRA', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            $stmt = $pdo->prepare("
                                SELECT ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS r_name, o.id AS o_id, o.name AS o_name
                                FROM registrar_accounts AS ra, registrars AS r, owners AS o
                                WHERE ra.registrar_id = r.id
                                  AND ra.owner_id = o.id
                                  AND ra.id = :new_raid
                                GROUP BY r.name, o.name, ra.username
                                ORDER BY r.name ASC, o.name ASC, ra.username ASC");
                            $stmt->bindValue('new_raid', $new_raid, PDO::PARAM_INT);
                            $stmt->execute();
                            $result = $stmt->fetch();
                            $stmt->closeCursor();

                            if (!$result) {

                                // the account was deleted while the form was open (or the form was changed), so nothing is saved
                                if ($pdo->InTransaction()) $pdo->rollback();
                                $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('The registrar account you chose no longer exists. Please choose another one.') . '<BR>';
                                header("Location: index.php?action=" . urlencode($action) . "");
                                exit;

                            }

                            $new_registrar_account_id = $result->ra_id;
                            $new_username = $result->username;
                            $new_registrar_id = $result->r_id;
                            $new_registrar_name = $result->r_name;
                            $new_owner_id = $result->o_id;
                            $new_owner_name = $result->o_name;

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET owner_id = :new_owner_id,
                                        registrar_id = :new_registrar_id,
                                        account_id = :new_registrar_account_id,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_registrar_account_id', $new_registrar_account_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET owner_id = :new_owner_id,
                                        registrar_id = :new_registrar_id,
                                        account_id = :new_registrar_account_id,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_owner_id', $new_owner_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_registrar_id', $new_registrar_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_registrar_account_id', $new_registrar_account_id, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                            $sql = "UPDATE domains
                                    SET fee_id = '0',
                                        total_cost = '0'
                                    WHERE domain IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_array);

                            $sql = "SELECT d.id, f.id AS fee_id
                                    FROM domains AS d, fees AS f
                                    WHERE d.registrar_id = f.registrar_id
                                      AND d.tld = f.tld
                                      AND d.domain IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_array);
                            $result = $stmt->fetchAll();

                            foreach ($result as $row) {

                                $pdo->query("
                                    UPDATE domains
                                    SET fee_id = '" . $row->fee_id . "'
                                    WHERE id = '" . $row->id . "'");

                            }

                            $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                            $sql = "UPDATE domains d
                                    JOIN fees f ON d.fee_id = f.id
                                    SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                                    WHERE d.privacy = '1'
                                      AND d.domain IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_array);

                            $sql = "UPDATE domains d
                                    JOIN fees f ON d.fee_id = f.id
                                    SET d.total_cost = f.renewal_fee + f.misc_fee
                                    WHERE d.privacy = '0'
                                      AND d.domain IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_array);

                            $queryB = new DomainMOD\QueryBuild();

                            $sql = $queryB->missingFees('domains');
                            $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Registrar Account Changed') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change registrar account';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "CWH") {

                    // None (0) is allowed, and clears the domains' link. A malformed value is refused before any lock or write.
                    if ($new_service_id < 0) {

                        $form->addFieldError('new_service_id', DomainMOD\Hosting::serviceGoneMessage());
                        $submission_failed = 1;

                    } elseif (!DomainMOD\Hosting::lock($pdo, DomainMOD\Hosting::PAGE_WAIT)) {

                        // A change of hosting service, even to None, holds the hosting lock, taken before the transaction,
                        // so a service delete can't slip between the check and the write (see DomainMOD\Hosting)
                        $_SESSION['s_message_danger'] .= DomainMOD\Hosting::busyMessage() . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CWH', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            // With the domains locked, the hosting service must still exist and not be cancelled. Nothing is
                            // changed, and the form shows again with the service marked, when it isn't.
                            $service_error = DomainMOD\Hosting::serviceError($pdo, $new_service_id);
                            if ($service_error !== '') {
                                $pdo->rollback();
                                throw new DomainMOD\HostingServiceRefused($service_error);
                            }

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET service_id = :new_service_id,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_service_id', $new_service_id, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET service_id = :new_service_id,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_service_id', $new_service_id, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Hosting Service Changed') . '<BR>';

                        } catch (DomainMOD\HostingServiceRefused $e) {

                            $form->addFieldError('new_service_id', $e->getMessage());
                            $submission_failed = 1;

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change hosting service';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        } finally {

                            DomainMOD\Hosting::unlock($pdo);

                        }

                    }

                } elseif ($action == "DD") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_DD', $new_notes);

                        $unlinked_ssl_certs = 0;

                        // The listed domains, locked in ascending id order (see $bulk_lock_ids)
                        $domain_id_list = $bulk_lock_ids($domain_array);

                        if ($domain_id_list) {

                            // Each domain's delete row, with a snapshot read under its lock, then its SSL certificates, which are
                            // kept without a domain: locked after the domains, a page at a time, each recorded going from its
                            // domain to none (while the domains' names can still be read)
                            // In the order they were pasted (the domains are all locked already, and the known certificates
                            // are locked first, by id)
                            $pasted_ids = $bulk_pasted_order($domain_id_list, $domain_array);
                            $history_op->deleting('domain', $pasted_ids, true);
                            $unlinked_ssl_certs = $domain->unlinkSslCertificatesRecorded($pasted_ids, $history_op, $bulk_known_ssl_ids, true);

                            $in_list = str_repeat('?, ', count($domain_id_list) - 1) . '?';

                            $sql = "DELETE FROM domains
                                    WHERE id IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_id_list);

                            $sql = "DELETE FROM domain_field_data
                                    WHERE domain_id IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_id_list);


                        }

                        $maint->updateSegments();

                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains Deleted') . '<BR>';

                        if ($unlinked_ssl_certs > 0) {
                            // TRANSLATORS: %s is the number of SSL certificates that were linked to the deleted domain or domains.
                            $_SESSION['s_message_success'] .= sprintf(ngettext('%s SSL certificate was kept without a domain.', '%s SSL certificates were kept without a domain.', $unlinked_ssl_certs), number_format($unlinked_ssl_certs)) . '<BR>';
                        }

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to delete domains';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "E") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_E', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '0',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '0',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as expired') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as expired';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "S") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_S', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '10',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '10',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as sold') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as sold';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "A") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_A', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '1',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '1',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as active') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as active';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "T") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_T', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '2',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '2',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Pending Transfer') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as pending transfer';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "PRg") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_PRg', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '5',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '5',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Pending Registration') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as pending registration';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "PRn") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_PRn', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '3',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '3',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Pending Renewal') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as pending renewal';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "PO") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_PO', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '4',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET active = '4',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Pending (Other)') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as pending other';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "AURNE") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_AURNE', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET autorenew = '1',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET autorenew = '1',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Auto-Renewal') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as auto renewal';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "AURND") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_AURND', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET autorenew = '0',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET autorenew = '0',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Manual Renewal') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as manual renewal';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "PRVE") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_PRVE', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET privacy = '1',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET privacy = '1',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                        $sql = "SELECT d.id, (f.renewal_fee + f.privacy_fee + f.misc_fee) AS total_cost
                                FROM domains AS d, fees AS f
                                WHERE d.fee_id = f.id
                                  AND d.domain IN (" . $in_list . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($domain_array);
                        $result = $stmt->fetchAll();

                        foreach ($result as $row) {

                            $pdo->query("
                                UPDATE domains
                                SET total_cost = '" . $row->total_cost . "'
                                WHERE id = '" . $row->id . "'");

                        }

                        $maint->updateSegments();

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Private WHOIS') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as private WHOIS';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "PRVD") {

                    try {

                        // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                        $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                        $pdo->beginTransaction();

                        // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                        $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_PRVD', $new_notes);
                        $history_before = $bulk_history_capture($history_op, $domain_array);

                        if ($new_notes != "") {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET privacy = '0',
                                    notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE domains
                                SET privacy = '0',
                                    update_time = :timestamp
                                WHERE domain = :each_domain");
                            $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                            $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                            foreach ($domain_array as $bind_each_domain) {

                                $stmt->execute();

                            }

                        }

                        $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                        $sql = "SELECT d.id, (f.renewal_fee + f.misc_fee) AS total_cost
                                FROM domains AS d, fees AS f
                                WHERE d.fee_id = f.id
                                  AND d.domain IN (" . $in_list . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($domain_array);
                        $result = $stmt->fetchAll();

                        foreach ($result as $row) {

                            $pdo->query("
                                UPDATE domains
                                SET total_cost = '" . $row->total_cost . "'
                                WHERE id = '" . $row->id . "'");

                        }

                        $history_op->compare($history_before);
                        $history_op->finish();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Public WHOIS') . '<BR>';

                    } catch (Exception $e) {

                        if ($pdo->InTransaction()) $pdo->rollback();

                        $log_message = 'Unable to mark domains as public WHOIS';
                        $log_extra = array('Error' => $e);
                        $log->critical($log_message, $log_extra);

                        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                        throw $e;

                    }

                } elseif ($action == "CED") {

                    if (!$date->checkDateFormat($new_expiry_date)) {

                        $form->addFieldError('datepick', _('The expiry date you entered is invalid'));
                        $submission_failed = 1;

                    } else {

                        try {

                            // REPEATABLE READ, so the locking reads' next-key locks hold even on a server whose default is READ COMMITTED
                            $pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                            $pdo->beginTransaction();

                            // The action is recorded in the Change History, in the same transaction (see DomainMOD\ChangeHistory)
                            $history_op = (new DomainMOD\ChangeHistory($pdo))->start(DomainMOD\ChangeHistory::SOURCE_BULK, $_SESSION['s_user_id'], 'bulk_CED', $new_notes);
                            $history_before = $bulk_history_capture($history_op, $domain_array);

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET expiry_date = :new_expiry_date,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET expiry_date = :new_expiry_date,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_expiry_date', $new_expiry_date, PDO::PARAM_STR);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            $history_op->compare($history_before);
                            $history_op->finish();

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Expiry Date Updated') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to update expiry date';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                }

                // A failed action redraws the form with its fields marked, rather than ending with only a Go Back button
                if (($submission_failed ?? 0) != 1) {
                    $done = "1";
                    $new_data_unformatted = implode(", ", $domain_array);
                }

            }

        }

    }

} else {

    // Casting $new_active as int sets it to 0 on first load, which sets the default status to 'Expired'. The below
    // line sets the default to 'Active' instead.
    if ($action == "AD") $new_active = 1;

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
<?php //@formatter:off
if ($action == "AD") {
    $breadcrumb_text = _('Add Domains');
} elseif ($action == "RENEW") {
    $breadcrumb_text = _('Renew Domains');
} elseif ($action == "FR") {
    $breadcrumb_text = _('Fully Renew Domains');
} elseif ($action == "E") {
    $breadcrumb_text = _('Mark as Expired');
} elseif ($action == "S") {
    $breadcrumb_text = _('Mark as Sold');
} elseif ($action == "A") {
    $breadcrumb_text = _('Mark as Active');
} elseif ($action == "T") {
    $breadcrumb_text = _('Mark as Pending Transfer');
} elseif ($action == "PRg") {
    $breadcrumb_text = _('Mark as Pending Registration');
} elseif ($action == "PRn") {
    $breadcrumb_text = _('Mark as Pending Renewal');
} elseif ($action == "PO") {
    $breadcrumb_text = _('Mark as Pending (Other)');
} elseif ($action == "AURNE") {
    $breadcrumb_text = _('Mark as Auto-Renewal');
} elseif ($action == "AURND") {
    $breadcrumb_text = _('Mark as Manual Renewal');
} elseif ($action == "PRVE") {
    $breadcrumb_text = _('Mark as Private WHOIS');
} elseif ($action == "PRVD") {
    $breadcrumb_text = _('Mark as Public WHOIS');
} elseif ($action == "CED") {
    $breadcrumb_text = _('Change Expiry Date');
} elseif ($action == "CPC") {
    $breadcrumb_text = _('Change Category');
} elseif ($action == "CDNS") {
    $breadcrumb_text = _('Change DNS Profile');
} elseif ($action == "CIP") {
    $breadcrumb_text = _('Change IP Address');
} elseif ($action == "CRA") {
    $breadcrumb_text = _('Change Registrar Account');
} elseif ($action == "CWH") {
    $breadcrumb_text = _('Change Hosting Service');
} elseif ($action == "DD") {
    $breadcrumb_text = _('Delete Domains');
} elseif ($action == "AN") {
    $breadcrumb_text = _('Add a Note');
} else {
    $breadcrumb_text = '';
}

if ($breadcrumb_text != '') {
    $breadcrumb_end = '<li class=\"active\">' . $breadcrumb_text . '</li>';
} //@formatter:on
?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $layout->calloutTop() . _("The Bulk Updater allows you to add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks.") . $layout->calloutBottom();
?>
<?php
    $done = $done ?? '';
    if ($done == "1") { ?>

        <?php
        $submission_failed = $submission_failed ?? 0;
        if ($submission_failed != "1") { ?>

        <?php if ($action == "AD") { ?>
            <h2 class="h5"><?php echo _('The following domains were added'); ?></h2>
        <?php } elseif ($action == "RENEW") { ?>
            <h2 class="h5"><?php echo sprintf(ngettext('The following domains were renewed for %s year', 'The following domains were renewed for %s years', $new_renewal_years), htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')); ?></h2>
        <?php } elseif ($action == "FR") { ?>
            <h2 class="h5"><?php echo sprintf(ngettext('The following domains were fully renewed for %s year', 'The following domains were fully renewed for %s years', $new_renewal_years), htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')); ?></h2>
        <?php } elseif ($action == "E") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as expired'); ?></h2>
        <?php } elseif ($action == "S") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as sold'); ?></h2>
        <?php } elseif ($action == "A") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as active'); ?></h2>
        <?php } elseif ($action == "T") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Pending Transfer'); ?></h2>
        <?php } elseif ($action == "PRg") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Pending Registration'); ?></h2>
        <?php } elseif ($action == "PRn") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Pending Renewal'); ?></h2>
        <?php } elseif ($action == "PO") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Pending (Other)'); ?></h2>
        <?php } elseif ($action == "AURNE") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Auto-Renewal'); ?></h2>
        <?php } elseif ($action == "AURND") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Manual Renewal'); ?></h2>
        <?php } elseif ($action == "PRVE") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Private WHOIS'); ?></h2>
        <?php } elseif ($action == "PRVD") { ?>
            <h2 class="h5"><?php echo _('The following domains were marked as Public WHOIS'); ?></h2>
        <?php } elseif ($action == "CED") { ?>
            <h2 class="h5"><?php echo _('The expiry date was updated for the following domains'); ?></h2>
        <?php } elseif ($action == "CPC") { ?>
            <h2 class="h5"><?php echo _('The following domains had their Category changed'); ?></h2>
        <?php } elseif ($action == "CDNS") { ?>
            <h2 class="h5"><?php echo _('The following domains had their DNS Profile changed'); ?></h2>
        <?php } elseif ($action == "CIP") { ?>
            <h2 class="h5"><?php echo _('The following domains had their IP Address changed'); ?></h2>
        <?php } elseif ($action == "CRA") { ?>
            <h2 class="h5"><?php echo _('The following domains had their Registrar Account changed'); ?></h2>
        <?php } elseif ($action == "CWH") { ?>
            <h2 class="h5"><?php echo _('The following domains had their Hosting Service changed'); ?></h2>
        <?php } elseif ($action == "DD") { ?>
            <h2 class="h5"><?php echo _('The following domains were deleted'); ?></h2>
        <?php } elseif ($action == "AN") { ?>
            <h2 class="h5"><?php echo _('The following domains had the Note appended'); ?></h2>
        <?php } ?>

        <p><?php echo htmlentities($new_data_unformatted, ENT_QUOTES, 'UTF-8'); ?></p><?php

    }

}

if ($done != "1") {

    if ($action == '') {

        echo $form->showFormTop('');
        echo $form->showDropdownTop('action', _('Choose Action'), '', '', '', false, true);
        echo $form->showDropdownOption('', _('Choose Action'), $action);
        echo $form->showDropdownOption('AD', _('Add Domains'), $action);
        echo $form->showDropdownOption('AN', _('Add a Note'), $action);
        echo $form->showDropdownOption('FR', _('Renew Domains (Update Expiry Date, Mark Active, Add Note)'), $action);
        echo $form->showDropdownOption('RENEW', _('Renew Domains (Update Expiry Date Only)'), $action);
        echo $form->showDropdownOption('A', _('Mark as Active'), $action);
        echo $form->showDropdownOption('T', _('Mark as Pending Transfer'), $action);
        echo $form->showDropdownOption('PRg', _('Mark as Pending Registration'), $action);
        echo $form->showDropdownOption('PRn', _('Mark as Pending Renewal'), $action);
        echo $form->showDropdownOption('PO', _('Mark as Pending (Other)'), $action);
        echo $form->showDropdownOption('E', _('Mark as Expired'), $action);
        echo $form->showDropdownOption('S', _('Mark as Sold'), $action);
        echo $form->showDropdownOption('AURNE', _('Mark as Auto-Renewal'), $action);
        echo $form->showDropdownOption('AURND', _('Mark as Manual Renewal'), $action);
        echo $form->showDropdownOption('PRVE', _('Mark as Private WHOIS'), $action);
        echo $form->showDropdownOption('PRVD', _('Mark as Public WHOIS'), $action);
        echo $form->showDropdownOption('CPC', _('Change Category'), $action);
        echo $form->showDropdownOption('CDNS', _('Change DNS Profile'), $action);
        echo $form->showDropdownOption('CED', _('Change Expiry Date'), $action);
        echo $form->showDropdownOption('CIP', _('Change IP Address'), $action);
        echo $form->showDropdownOption('CRA', _('Change Registrar Account'), $action);
        if ($hosting_available) echo $form->showDropdownOption('CWH', _('Change Hosting Service'), $action);
        echo $form->showDropdownOption('UCF', _('Update Custom Domain Field'), $action);
        echo $form->showDropdownOption('DD', _('Delete Domains'), $action);
        echo $form->showDropdownBottom('');
        echo $form->showSubmitButton(_('Next Step'), '', '');
        echo $form->showInputHidden('is_submitted', '1');
        echo $form->showFormBottom('');

    }

    echo $form->showFormTop('');

    if ($action != "") {

        // The action's name as the heading over the form, and for Delete Domains, what else goes with the domains as a
        // slim warning under it, inside the form so it keeps to the form's width (like the restore warning on the
        // Backup & Restore page, it isn't a page message, so it isn't announced when the page loads)
        $before_list = '<h2 class="h5">' . $breadcrumb_text . '</h2>';
        if ($action == "DD") {

            $before_list .= '<div class="alert alert-danger d-flex align-items-start gap-2 py-2">'
                . '<i class="bi bi-exclamation-triangle-fill domainmod-message-icon" aria-hidden="true"></i>'
                . '<div class="domainmod-message-text">' . _('Deleting these domains also deletes their custom field data. Any linked SSL certificates are kept without a domain.') . ' ' . _("If you don't want to completely remove all traces of the domains from the system, you may be better off marking them as expired instead.") . '</div></div>';

        }

        $after_list = '';
        if ($action == "AD") {

            $text = _('Domains to add (one per line)');

        } else {

            $text = _('Domains to update (one per line)');

            // A search box that adds existing domains to the list, and the list's count (new domains can't be picked,
            // so Add Domains has neither)
            $before_list .= $form->showDomainPicker('raw_domain_list', _('Find a domain to add to the list'),
                _('Type part of a domain name, then pick it to add it to the list below. You can also type or paste domains straight into the list.'),
                WEB_ROOT . '/domains/name-search.php');
            $after_list = $form->showDomainListCount('raw_domain_list');

        }

        echo $form->showInputTextarea('raw_domain_list', $text, '', $unsanitize->text($raw_domain_list), '1', $before_list, $after_list);

    }

    // Display forms for various actions
    if ($action == "AD") { // Add Domains

        // Function
        echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');

        // Expiry Date
        if ($new_expiry_date != "") {
            $temp_expiry_date = $new_expiry_date;
        } else {
            $temp_expiry_date = $time->userDatePlusYears(1);
        }
        echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $temp_expiry_date, '10', '', '1', '', '');

        // Registrar Account
        echo $form->showDropdownTop('new_raid', _('Registrar Account'), '', '1', '');

        $result_account = $pdo->query("
            SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
            FROM registrar_accounts AS ra, owners AS o, registrars AS r
            WHERE ra.owner_id = o.id
              AND ra.registrar_id = r.id
            ORDER BY r_name, o_name, ra.username")->fetchAll();

        foreach ($result_account as $row_account) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_raid;
            } else {
                $temp_to_compare = $_SESSION['s_default_registrar_account'];
            }

            echo $form->showDropdownOption($row_account->id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $temp_to_compare);

        }
        echo $form->showDropdownBottom('', 'new_raid');

        // DNS Profile
        echo $form->showDropdownTop('new_dnsid', _('DNS Profile'), '', '1', '');

        $result_dns = $pdo->query("
            SELECT id, `name`
            FROM dns
            ORDER BY name")->fetchAll();

        foreach ($result_dns as $row_dns) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_dnsid;
            } else {
                $temp_to_compare = $_SESSION['s_default_dns'];
            }

            echo $form->showDropdownOption($row_dns->id, $row_dns->name, $temp_to_compare);

        }
        echo $form->showDropdownBottom('', 'new_dnsid');

        // IP Address
        echo $form->showDropdownTop('new_ipid', _('IP Address'), '', '1', '');

        $result_ip = $pdo->query("
            SELECT id, `name`, ip
            FROM ip_addresses
            ORDER BY name, ip")->fetchAll();

        foreach ($result_ip as $row_ip) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_ipid;
            } else {
                $temp_to_compare = $_SESSION['s_default_ip_address_domains'];
            }

            echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $temp_to_compare);

        }
        echo $form->showDropdownBottom('', 'new_ipid');

        // Hosting Service (optional), once hosting is available
        if ($hosting_available) {
            $temp_to_compare = $_SERVER['REQUEST_METHOD'] == 'POST' ? max($new_service_id, 0) : 0;
            $services = DomainMOD\Hosting::serviceMenu($pdo, $temp_to_compare);
            // Shown again after a failed save, the IP Address shown was already chosen, so the service no longer fills it in
            echo $form->showHostingServiceMenu('new_service_id', $temp_to_compare, $services, '', '',
                $_SERVER['REQUEST_METHOD'] == 'POST' ? '' : 'new_ipid', DomainMOD\Hosting::serviceIps($pdo, array_keys($services)));
        }

        // Category
        echo $form->showDropdownTop('new_pcid', _('Category'), '', '1', '');

        $result_cat = $pdo->query("
            SELECT id, `name`
            FROM categories
            ORDER BY name")->fetchAll();

        foreach ($result_cat as $row_cat) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_pcid;
            } else {
                $temp_to_compare = $_SESSION['s_default_category_domains'];
            }

            echo $form->showDropdownOption($row_cat->id, $row_cat->name, $temp_to_compare);

        }
        echo $form->showDropdownBottom('', 'new_pcid');

        // Domain Status
        echo $form->showDropdownTop('new_active', _('Domain Status'), '', '', '');
        echo $form->showDropdownOption('1', _('Active'), $new_active);
        echo $form->showDropdownOption('5', _('Pending (Registration)'), $new_active);
        echo $form->showDropdownOption('3', _('Pending (Renewal)'), $new_active);
        echo $form->showDropdownOption('2', _('Pending (Transfer)'), $new_active);
        echo $form->showDropdownOption('4', _('Pending (Other)'), $new_active);
        echo $form->showDropdownOption('0', _('Expired'), $new_active);
        echo $form->showDropdownOption('10', _('Sold'), $new_active);
        echo $form->showDropdownBottom('');

        // Auto Renewal
        echo $form->showSwitch(_('Auto-Renewal') . '?', '', 'new_autorenew', $new_autorenew, '', '<BR><BR>');

        // WHOIS Privacy Status
        echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '', '<BR><BR>');

    } elseif ($action == "RENEW" || $action == "FR") {

        echo $form->showDropdownTop('new_renewal_years', _('Renew For'), '', '', '');
        for ($years = 1; $years <= 10; $years++) {
            echo $form->showDropdownOption((string) $years, sprintf(ngettext('%s Year', '%s Years', $years), $years), $new_renewal_years);
        }
        echo $form->showDropdownBottom('');

    } elseif ($action == "CPC") {

        echo $form->showDropdownTop('new_pcid', _('New Category'), '', '1', '');
        echo $form->showDropdownOption('', $choose_text . ' ' . _('Category'), $new_pcid);

        $result_cat = $pdo->query("
            SELECT id, `name`
            FROM categories
            ORDER BY name")->fetchAll();

        foreach ($result_cat as $row_cat) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_pcid;
            } else {
                $temp_to_compare = $_SESSION['s_default_category_domains'];
            }

            echo $form->showDropdownOption($row_cat->id, $row_cat->name, $temp_to_compare);


        }

        echo $form->showDropdownBottom('', 'new_pcid');

    } elseif ($action == "CDNS") {

        echo $form->showDropdownTop('new_dnsid', _('New DNS Profile'), '', '1', '');
        echo $form->showDropdownOption('', $choose_text . ' ' . _('DNS Profile'), $new_dnsid);

        $result_dns = $pdo->query("
            SELECT id, `name`
            FROM dns
            ORDER BY name ASC")->fetchAll();

        foreach ($result_dns as $row_dns) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_dnsid;
            } else {
                $temp_to_compare = $_SESSION['s_default_dns'];
            }

            echo $form->showDropdownOption($row_dns->id, $row_dns->name, $temp_to_compare);

        }

        echo $form->showDropdownBottom('', 'new_dnsid');

    } elseif ($action == "CIP") {

        echo $form->showDropdownTop('new_ipid', _('New IP Address'), '', '1', '');
        echo $form->showDropdownOption('', $choose_text . ' ' . _('IP Address'), $new_ipid);

        $result_ip = $pdo->query("
            SELECT id, `name`, ip
            FROM ip_addresses
            ORDER BY name ASC, ip ASC")->fetchAll();

        foreach ($result_ip as $row_ip) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_ipid;
            } else {
                $temp_to_compare = $_SESSION['s_default_ip_address_domains'];
            }

            echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $temp_to_compare);

        }

        echo $form->showDropdownBottom('', 'new_ipid');

    } elseif ($action == "CRA") {

        echo $form->showDropdownTop('new_raid', _('New Registrar Account'), '', '1', '');
        echo $form->showDropdownOption('', $choose_text . ' ' . _('Registrar Account'), $new_raid);

        $result_account = $pdo->query("
            SELECT ra.id AS ra_id, ra.username, r.name AS r_name, o.name AS o_name
            FROM registrar_accounts AS ra, registrars AS r, owners AS o
            WHERE ra.registrar_id = r.id
              AND ra.owner_id = o.id
            GROUP BY r.name, o.name, ra.username
            ORDER BY r.name ASC, o.name ASC, ra.username ASC")->fetchAll();

        foreach ($result_account as $row_account) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_raid;
            } else {
                $temp_to_compare = $_SESSION['s_default_registrar_account'];
            }

            echo $form->showDropdownOption($row_account->ra_id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $temp_to_compare);

        }

        echo $form->showDropdownBottom('', 'new_raid');

    } elseif ($action == "CWH") {

        // The new hosting service, or None to clear the domains' link
        $temp_to_compare = $_SERVER['REQUEST_METHOD'] == 'POST' ? max($new_service_id, 0) : 0;
        echo $form->showHostingServiceMenu('new_service_id', $temp_to_compare, DomainMOD\Hosting::serviceMenu($pdo, $temp_to_compare));

    } elseif ($action == "CED") {

        if ($new_expiry_date != "") {
            $temp_expiry_date = $new_expiry_date;
        } else {
            $temp_expiry_date = $timestamp_basic;
        }
        echo $form->showInputText('datepick', _('Expiry Date') . ' (YYYY-MM-DD)', '', $temp_expiry_date, '10', '', '1', '', '');

    }

    if ($action != "") {

        if ($action == "AD") {

            $notes_heading = _('Notes');

        } elseif ($action == "") {

            $notes_heading = '';

        } else {

            $notes_heading = _('Notes (will be appended to current domain notes)');

        }

        if ($action != "DD") {

            if ($action == "AN") {

                echo $form->showInputTextarea('new_notes', $notes_heading, '', $unsanitize->text($new_notes), '1', '', '');

            } else {

                echo $form->showInputTextarea('new_notes', $notes_heading, '', $unsanitize->text($new_notes), '', '', '');
            }

        }

        if ($action == "AD") {

            $result = $pdo->query("
                SELECT field_name
                FROM domain_fields
                ORDER BY type_id, `name`")->fetchAll();

            if ($result) { ?>

                <BR><h2 class="h3"><?php echo _('Custom Fields'); ?></h2><?php

                $field_array = array();

                foreach ($result as $row) {

                    $field_array[] = $row->field_name;

                }

                foreach ($field_array as $field) {

                    $stmt = $pdo->prepare("
                    SELECT df.name, df.field_name, df.type_id, df.description
                    FROM domain_fields AS df, custom_field_types AS cft
                    WHERE df.type_id = cft.id
                      AND df.field_name = :field");
                    $stmt->bindValue('field', $field, PDO::PARAM_STR);
                    $stmt->execute();
                    $result = $stmt->fetchAll();

                    if ($result) {

                        foreach ($result as $row) {

                            if ($row->type_id === 1) { // Check Box

                                echo $form->showCheckbox(DomainMOD\CustomField::inputName($row->field_name), '1', $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '', '');

                            } elseif ($row->type_id === 2) { // Text

                                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '255', '', '', '', '');

                            } elseif ($row->type_id === 3) { // Text Area

                                echo $form->showInputTextarea(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '', '', '');

                            } elseif ($row->type_id === 4) { // Date

                                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '10', '', '', '', '', '', 'ltr');

                            } elseif ($row->type_id === 5) { // Time Stamp

                                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '19', '', '', '', '', '', 'ltr');

                            } elseif ($row->type_id === 6) { // URL

                                echo $form->showInputText(DomainMOD\CustomField::inputName($row->field_name), $row->name, $row->description, $new_custom_fields[$row->field_name] ?? '', '255', '', '', '', '', '', 'ltr');

                            }

                        }

                    }

                }

            }

        }

        echo $form->showInputHidden('action', $action);

        if ($action == "CDNS") {

            echo $form->showInputHidden('dnsid', $new_dnsid);

        } elseif ($action == "CIP") {

            echo $form->showInputHidden('ipid', $new_ipid);

        } elseif ($action == "CRA") {

            echo $form->showInputHidden('raid', $new_raid);

        } elseif ($action == "CWH") {

            echo $form->showInputHidden('hsid', $new_service_id);

        } ?>

        <?php // Go Back and the main button in one row, Back on the start side (css/base.css) ?>
        <div class="domainmod-back-next"><?php echo $layout->linkButton('../', _('Go Back'), 'secondary', 'bi-arrow-left');

        if ($action == "AD") {

            echo $form->showSubmitButton(_('Add Domains'), '', '');

        } elseif ($action == "AN") {

            echo $form->showSubmitButton(_('Add Notes'), '', '');

        } elseif ($action == "FR") {

            echo $form->showSubmitButton(_('Mark as Renewed'), '', '');

        } elseif ($action == "RENEW") {

            echo $form->showSubmitButton(_('Mark as Renewed'), '', '');

        } elseif ($action == "A") {

            echo $form->showSubmitButton(_('Mark as Active'), '', '');

        } elseif ($action == "T") {

            echo $form->showSubmitButton(_('Mark as Pending Transfer'), '', '');

        } elseif ($action == "PRg") {

            echo $form->showSubmitButton(_('Mark as Pending Registration'), '', '');

        } elseif ($action == "PRn") {

            echo $form->showSubmitButton(_('Mark as Pending Renewal'), '', '');

        } elseif ($action == "PO") {

            echo $form->showSubmitButton(_('Mark as Pending (Other)'), '', '');

        } elseif ($action == "E") {

            echo $form->showSubmitButton(_('Mark as Expired'), '', '');

        } elseif ($action == "S") {

            echo $form->showSubmitButton(_('Mark as Sold'), '', '');

        } elseif ($action == "AURNE") {

            echo $form->showSubmitButton(_('Mark as Auto-Renewal'), '', '');

        } elseif ($action == "AURND") {

            echo $form->showSubmitButton(_('Mark as Manual Renewal'), '', '');

        } elseif ($action == "PRVE") {

            echo $form->showSubmitButton(_('Mark as Private WHOIS'), '', '');

        } elseif ($action == "PRVD") {

            echo $form->showSubmitButton(_('Mark as Public WHOIS'), '', '');

        } elseif ($action == "CPC") {

            echo $form->showSubmitButton(_('Change Category'), '', '');

        } elseif ($action == "CDNS") {

            echo $form->showSubmitButton(_('Change DNS Profile'), '', '');

        } elseif ($action == "CED") {

            echo $form->showSubmitButton(_('Change Expiry Date'), '', '');

        } elseif ($action == "CIP") {

            echo $form->showSubmitButton(_('Change IP Address'), '', '');

        } elseif ($action == "CRA") {

            echo $form->showSubmitButton(_('Change Registrar Account'), '', '');

        } elseif ($action == "CWH") {

            echo $form->showSubmitButton(_('Change Hosting Service'), '', '');

        } elseif ($action == "DD") {

            echo $form->showSubmitButton(_('Delete Domains'), '', '');

        } else {

            echo $form->showSubmitButton(_('Perform Bulk Action'), '', '');

        } ?></div><?php

    }

    echo $form->showInputHidden('is_submitted', '1');
    echo $form->showFormBottom('');

} else { ?>

    <div class="domainmod-back-next"><?php echo $layout->linkButton('../', _('Go Back'), 'secondary', 'bi-arrow-left'); ?></div><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
<script src="<?php echo $layout->assetUrl('js/bulk-domain-picker.js'); ?>"></script>
<script src="<?php echo $layout->assetUrl('js/hosting-service-ip.js'); ?>"></script>
</body>
</html>
