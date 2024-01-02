<?php
/**
 * /bulk/main/index.php
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
$timestamp_basic = $time->timeBasic();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

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
$new_whid = (int) ($_POST['new_whid'] ?? 0);
$new_raid = (int) ($_POST['new_raid'] ?? 0);
$new_autorenew = (int) ($_POST['new_autorenew'] ?? 0);
$new_privacy = (int) ($_POST['new_privacy'] ?? 0);
$new_active = (int) ($_POST['new_active'] ?? 0);
$new_notes = isset($_POST['new_notes']) ? $sanitize->text($_POST['new_notes']) : '';
$new_renewal_years = (int) ($_POST['new_renewal_years'] ?? 0);
$is_submitted = (int) ($_POST['is_submitted'] ?? 0);

$choose_text = _('Click here to choose the new');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_submitted === 1) {

    $format = new DomainMOD\Format();

    $domain_array = $format->cleanAndSplitDomains($raw_domain_list);

    if ($raw_domain_list == "") {

        $_SESSION['s_message_danger'] .= _('Enter the list of domains to apply the action to') . '<BR>';

        if ($action == 'AN' && $new_notes == '') {

            $_SESSION['s_message_danger'] .= _('Enter the new Note') . '<BR>';

        }

    } else {

        if ($action == 'AN' && $new_notes == '') {

            $_SESSION['s_message_danger'] .= _('Enter the new Note') . '<BR>';

        } else {

            list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_array);

            if ($raw_domain_list == "" || $invalid_domains == 1) {

                if ($invalid_domains == 1) {

                    if ($invalid_count == 1) {

                        $_SESSION['s_message_danger'] .= sprintf(_('There is %s invalid domain on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                    } else {

                        $_SESSION['s_message_danger'] .= sprintf(_('There are %s invalid domains on your list'), number_format($invalid_count)) . '<BR><BR>' . $temp_result_message;

                        if (($invalid_count - $invalid_to_display) == 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s other'), number_format($invalid_count - $invalid_to_display)) . '<BR>';

                        } elseif (($invalid_count - $invalid_to_display) > 1) {

                            $_SESSION['s_message_danger'] .= '<BR>' . sprintf(_('Plus %s others'), number_format($invalid_count - $invalid_to_display)) . '<BR>';
                        }

                    }

                } else {

                    $_SESSION['s_message_danger'] .= _('Enter the list of domains to apply the action to') . '<BR>';

                }
                $submission_failed = 1;

            } else {

                if ($action == "RENEW") {

                    try {

                        $pdo->beginTransaction();

                        foreach ($domain_array as $each_domain) {

                            $domain->renew($each_domain, $new_renewal_years, $new_notes);

                        }

                        $maint->updateSegments();

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

                    if (!$date->checkDateFormat($new_expiry_date) || $new_pcid === 0 || $new_dnsid === 0 || $new_ipid === 0 || $new_whid === 0 || $new_raid === 0 || $has_existing_domains == '1') {

                        if ($has_existing_domains == '1') $_SESSION['s_message_danger'] .= sprintf(_('At least one of the domains you entered already exists in %s. %s You should run the domain list through a Segment filter to determine which one(s).'), SOFTWARE_TITLE, '<BR><BR>') . '<BR>';
                        if (!$date->checkDateFormat($new_expiry_date)) $_SESSION['s_message_danger'] .= _('You have entered an invalid expiry date') . '<BR>';
                        if ($new_pcid === 0) $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Category')) . '<BR>';
                        if ($new_dnsid === 0) $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('DNS Profile')) . '<BR>';
                        if ($new_ipid === 0) $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('IP Address')) . '<BR>';
                        if ($new_whid === 0) $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Web Hosting Provider')) . '<BR>';
                        if ($new_raid === 0) $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Registrar Account')) . '<BR>';

                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

                            $stmt = $pdo->prepare("
                                SELECT owner_id, registrar_id
                                FROM registrar_accounts
                                WHERE id = :raid");
                            $stmt->bindValue('raid', $new_raid, PDO::PARAM_INT);
                            $stmt->execute();
                            $result = $stmt->fetch();
                            $stmt->closeCursor();

                            if ($result) {

                                $temp_owner_id = $result->owner_id;
                                $temp_registrar_id = $result->registrar_id;

                            }

                            // cycle through domains here
                            foreach ($domain_array as $key => $new_domain) {

                                $new_tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

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

                                $stmt = $pdo->prepare("
                                    INSERT INTO domains
                                    (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id,
                                     total_cost, dns_id, ip_id, hosting_id, `function`, notes, autorenew, privacy,
                                     creation_type_id, created_by, active, fee_fixed, insert_time)
                                    VALUES
                                    (:temp_owner_id, :temp_registrar_id, :new_raid, :new_domain, :new_tld, :new_expiry_date,
                                     :new_pcid, :temp_fee_id, :new_total_cost, :new_dnsid, :new_ipid, :new_whid, :new_function,
                                     :new_notes, :new_autorenew, :new_privacy, :creation_type_id, :user_id, :new_active,
                                     :temp_fee_fixed, :timestamp)");
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
                                $stmt->bindValue('new_whid', $new_whid, PDO::PARAM_INT);
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
                                    ORDER BY `name`")->fetchAll();

                                if ($result) {

                                    $field_array = array();

                                    foreach ($result as $row) {

                                        $field_array[] = $row->field_name;

                                    }

                                    foreach ($field_array as $field) {

                                        $full_field = "new_" . $field;

                                        $stmt = $pdo->prepare("
                                            UPDATE domain_field_data
                                            SET {$field} = :full_field
                                            WHERE domain_id = :domain_id");
                                        $stmt->bindValue('full_field', ${$full_field}, PDO::PARAM_STR);
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

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Domains added') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to add domains';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "FR") {

                    try {

                        $pdo->beginTransaction();

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

                            if ($new_renewal_years === 1) {
                                $renewal_years_string = $new_renewal_years . ' ' . _('Year');
                            } else {
                                $renewal_years_string = $new_renewal_years . ' ' . _('Years');
                            }

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

                        $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Category')) . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                        $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('DNS Profile')) . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                        $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('IP Address')) . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                        $_SESSION['s_message_danger'] .= _('Enter the new Note') . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                        $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Registrar Account')) . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                            if ($result) {

                                $new_registrar_account_id = $result->ra_id;
                                $new_username = $result->username;
                                $new_registrar_id = $result->r_id;
                                $new_registrar_name = $result->r_name;
                                $new_owner_id = $result->o_id;
                                $new_owner_name = $result->o_name;

                            }

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

                    if ($new_whid === 0) {

                        $_SESSION['s_message_danger'] .= sprintf(_('Please choose the new %s'), _('Web Hosting Provider')) . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

                            if ($new_notes != "") {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET hosting_id  = :new_whid,
                                        notes = CONCAT(:new_notes, '\r\n\r\n', notes),
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_whid', $new_whid, PDO::PARAM_INT);
                                $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE domains
                                    SET hosting_id  = :new_whid,
                                        update_time = :timestamp
                                    WHERE domain = :each_domain");
                                $stmt->bindValue('new_whid', $new_whid, PDO::PARAM_INT);
                                $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
                                $stmt->bindParam('each_domain', $bind_each_domain, PDO::PARAM_STR);

                                foreach ($domain_array as $bind_each_domain) {

                                    $stmt->execute();

                                }

                            }

                            if ($pdo->InTransaction()) $pdo->commit();

                            $_SESSION['s_message_success'] .= _('Web Hosting Provider Changed') . '<BR>';

                        } catch (Exception $e) {

                            if ($pdo->InTransaction()) $pdo->rollback();

                            $log_message = 'Unable to change web hosting provider';
                            $log_extra = array('Error' => $e);
                            $log->critical($log_message, $log_extra);

                            $_SESSION['s_message_danger'] .= $log_message . '<BR>';

                            throw $e;

                        }

                    }

                } elseif ($action == "DD") {

                    try {

                        $pdo->beginTransaction();

                        $in_list = str_repeat('?, ', count($domain_array) - 1) . '?';
                        $sql = "SELECT id
                                FROM domains
                                WHERE domain IN (" . $in_list . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($domain_array);
                        $result = $stmt->fetchAll();

                        if ($result) {

                            $domain_id_list = array();

                            foreach ($result as $row) {

                                $domain_id_list[] = $row->id;

                            }

                            $in_list = str_repeat('?, ', count($domain_id_list) - 1) . '?';

                            $sql = "DELETE FROM domains
                                    WHERE id IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_id_list);

                            $sql = "DELETE FROM domain_field_data
                                    WHERE domain_id IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_id_list);

                            $sql = "SELECT id
                                    FROM ssl_certs
                                    WHERE domain_id IN (" . $in_list . ")";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($domain_id_list);
                            $result_ssl = $stmt->fetchAll();

                            if ($result_ssl) {

                                $ssl_id_list = array();

                                foreach ($result_ssl as $row_ssl) {

                                    $ssl_id_list[] = $row_ssl->id;

                                }

                                $in_list = str_repeat('?, ', count($ssl_id_list) - 1) . '?';

                                $sql = "DELETE FROM ssl_certs
                                        WHERE id IN (" . $in_list . ")";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($ssl_id_list);

                                $sql = "DELETE FROM ssl_cert_field_data
                                        WHERE ssl_id IN (" . $in_list . ")";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($ssl_id_list);

                            }

                        }

                        $maint->updateSegments();

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains (and associated data) Deleted') . '<BR>';

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        if ($pdo->InTransaction()) $pdo->commit();

                        $_SESSION['s_message_success'] .= _('Domains marked as Auto Renewal') . '<BR>';

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $pdo->beginTransaction();

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

                        $_SESSION['s_message_danger'] .= _('The expiry date you entered is invalid') . '<BR>';
                        $submission_failed = 1;

                    } else {

                        try {

                            $pdo->beginTransaction();

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

                $done = "1";
                $new_data_unformatted = implode(", ", $domain_array);

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
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
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
    $breadcrumb_text = _('Mark as Auto Renewal');
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
    $breadcrumb_text = _('Change Hosting Provider');
} elseif ($action == "DD") {
    $breadcrumb_text = _('Delete Domains');
} elseif ($action == "AN") {
    $breadcrumb_text = _('Add A Note');
} else {
    $breadcrumb_text = '';
}

if ($breadcrumb_text != '') {
    $breadcrumb_end = '<li class=\"active\">' . $breadcrumb_text . '</li>';
} //@formatter:on
?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo _("The Bulk Updater allows you add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks.") . '<BR>';
?>
<?php
    $done = $done ?? '';
    if ($done == "1") { ?>

        <?php
        $submission_failed = $submission_failed ?? 0;
        if ($submission_failed != "1") { ?>

        <?php if ($action == "AD") { ?>
            <BR><strong><?php echo _('The following domains were added'); ?>:</strong><BR>
        <?php } elseif ($action == "RENEW") { ?>
            <BR><strong><?php echo sprintf(ngettext('The following domain was renewed for %s year', 'The following domains were renewed for %s years', htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')), htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')); ?>:</strong><BR>
        <?php } elseif ($action == "FR") { ?>
            <BR><strong><?php echo sprintf(ngettext('The following domain was fully renewed for %s year', 'The following domains were fully renewed for %s years', htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')), htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8')); ?>:</strong><BR>
        <?php } elseif ($action == "E") { ?>
            <BR><strong><?php echo _('The following domains were marked as expired'); ?>:</strong><BR>
        <?php } elseif ($action == "S") { ?>
            <BR><strong><?php echo _('The following domains were marked as sold'); ?>:</strong><BR>
        <?php } elseif ($action == "A") { ?>
            <BR><strong><?php echo _('The following domains were marked as active'); ?>:</strong><BR>
        <?php } elseif ($action == "T") { ?>
            <BR><strong><?php echo _('The following domains were marked as Pending Transfer'); ?>:</strong><BR>
        <?php } elseif ($action == "PRg") { ?>
            <BR><strong><?php echo _('The following domains were marked as Pending Registration'); ?>:</strong><BR>
        <?php } elseif ($action == "PRn") { ?>
            <BR><strong><?php echo _('The following domains were marked as Pending Renewal'); ?>:</strong><BR>
        <?php } elseif ($action == "PO") { ?>
            <BR><strong><?php echo _('The following domains were marked as Pending (Other)'); ?>:</strong><BR>
        <?php } elseif ($action == "AURNE") { ?>
            <BR><strong><?php echo _('The following domains were marked as Auto Renewal'); ?>:</strong><BR>
        <?php } elseif ($action == "AURND") { ?>
            <BR><strong><?php echo _('The following domains were marked as Manual Renewal'); ?>:</strong><BR>
        <?php } elseif ($action == "PRVE") { ?>
            <BR><strong><?php echo _('The following domains were marked as Private WHOIS'); ?>:</strong><BR>
        <?php } elseif ($action == "PRVD") { ?>
            <BR><strong><?php echo _('The following domains were marked as Public WHOIS'); ?>:</strong><BR>
        <?php } elseif ($action == "CED") { ?>
            <BR><strong><?php echo _('The expiry date was updated for the following domains'); ?>:</strong><BR>
        <?php } elseif ($action == "CPC") { ?>
            <BR><strong><?php echo _('The following domains had their Category changed'); ?>:</strong><BR>
        <?php } elseif ($action == "CDNS") { ?>
            <BR><strong><?php echo _('The following domains had their DNS Profile changed'); ?>:</strong><BR>
        <?php } elseif ($action == "CIP") { ?>
            <BR><strong><?php echo _('The following domains had their IP Address changed'); ?>:</strong><BR>
        <?php } elseif ($action == "CRA") { ?>
            <BR><strong><?php echo _('The following domains had their Registrar Account changed'); ?>:</strong><BR>
        <?php } elseif ($action == "CWH") { ?>
            <BR><strong><?php echo _('The following domains had their Web Hosting Provider changed'); ?>:</strong><BR>
        <?php } elseif ($action == "DD") { ?>
            <BR><strong><?php echo _('The following domains (and associated SSL Certificates) were deleted'); ?>:</strong><BR>
        <?php } elseif ($action == "AN") { ?>
            <BR><strong><?php echo _('The following domains had the Note appended'); ?>:</strong><BR>
        <?php } ?>

        <BR><?php echo htmlentities($new_data_unformatted, ENT_QUOTES, 'UTF-8'); ?><BR><BR><?php

    }

}

if ($done != "1") {

    if ($action == '') {

        echo $form->showFormTop('');
        echo $form->showDropdownTop('action', '', '', '', '');
        echo $form->showDropdownOption('', _('Choose Action'), $action);
        echo $form->showDropdownOption('AD', _('Add Domains'), $action);
        echo $form->showDropdownOption('AN', _('Add A Note'), $action);
        echo $form->showDropdownOption('FR', _('Renew Domains (Update Expiry Date, Mark Active, Add Note)'), $action);
        echo $form->showDropdownOption('RENEW', _('Renew Domains (Update Expiry Date Only)'), $action);
        echo $form->showDropdownOption('A', _('Mark as Active'), $action);
        echo $form->showDropdownOption('T', _('Mark as Pending Transfer'), $action);
        echo $form->showDropdownOption('PRg', _('Mark as Pending Registration'), $action);
        echo $form->showDropdownOption('PRn', _('Mark as Pending Renewal'), $action);
        echo $form->showDropdownOption('PO', _('Mark as Pending (Other)'), $action);
        echo $form->showDropdownOption('E', _('Mark as Expired'), $action);
        echo $form->showDropdownOption('S', _('Mark as Sold'), $action);
        echo $form->showDropdownOption('AURNE', _('Mark as Auto Renewal'), $action);
        echo $form->showDropdownOption('AURND', _('Mark as Manual Renewal'), $action);
        echo $form->showDropdownOption('PRVE', _('Mark as Private WHOIS'), $action);
        echo $form->showDropdownOption('PRVD', _('Mark as Public WHOIS'), $action);
        echo $form->showDropdownOption('CPC', _('Change Category'), $action);
        echo $form->showDropdownOption('CDNS', _('Change DNS Profile'), $action);
        echo $form->showDropdownOption('CED', _('Change Expiry Date'), $action);
        echo $form->showDropdownOption('CIP', _('Change IP Address'), $action);
        echo $form->showDropdownOption('CRA', _('Change Registrar Account'), $action);
        echo $form->showDropdownOption('CWH', _('Change Web Hosting Provider'), $action);
        echo $form->showDropdownOption('UCF', _('Update Custom Domain Field'), $action);
        echo $form->showDropdownOption('DD', _('Delete Domains'), $action);
        echo $form->showDropdownBottom('');
        echo $form->showSubmitButton(_('Next Step'), '', '');
        echo $form->showInputHidden('is_submitted', '1');
        echo $form->showFormBottom('');

    } else {

        echo '<BR>';

    }

    echo $form->showFormTop('');

    if ($action != "") {

        if ($action == "DD") {

            echo "<strong>" . _("WARNING: In addition to deleting the domains, all SSL certificates and custom field data associated with the domains and SSL certificates will also be deleted.") . ' ' . _("If you don't want to completely remove all traces of the domains from the system you may be better off marking them as expired instead.") . "</strong><BR><BR>";
        }

        if ($action == "AD") {

            $text = _('Domains to add (one per line)');

        } else {

            $text = _('Domains to update (one per line)');

        }

        echo $form->showInputTextarea('raw_domain_list', $text, '', $unsanitize->text($raw_domain_list), '1', '<strong>' . $breadcrumb_text . '</strong><BR><BR>', '');

    }

    // Display forms for various actions
    if ($action == "AD") { // Add Domains

        // Function
        echo $form->showInputText('new_function', _('Function') . ' (255)', '', $unsanitize->text($new_function), '255', '', '', '', '');

        // Expiry Date
        if ($new_expiry_date != "") {
            $temp_expiry_date = $new_expiry_date;
        } else {
            $temp_expiry_date = $time->toUserTimezone($timestamp_basic_plus_one_year, 'Y-m-d');
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
        echo $form->showDropdownBottom('');

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
        echo $form->showDropdownBottom('');

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
        echo $form->showDropdownBottom('');

        // Web Hosting Provider
        echo $form->showDropdownTop('new_whid', _('Web Hosting Provider'), '', '1', '');

        $result_host = $pdo->query("
            SELECT id, `name`
            FROM hosting
            ORDER BY name")->fetchAll();

        foreach ($result_host as $row_host) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_whid;
            } else {
                $temp_to_compare = $_SESSION['s_default_host'];
            }

            echo $form->showDropdownOption($row_host->id, $row_host->name, $temp_to_compare);

        }
        echo $form->showDropdownBottom('');

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
        echo $form->showDropdownBottom('');

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
        echo $form->showSwitch(_('Auto Renewal') . '?', '', 'new_autorenew', $new_autorenew, '', '<BR><BR>');

        // WHOIS Privacy Status
        echo $form->showSwitch(_('Privacy Enabled') . '?', '', 'new_privacy', $new_privacy, '', '<BR><BR>');

    } elseif ($action == "RENEW" || $action == "FR") {

        echo $form->showDropdownTop('new_renewal_years', _('Renew For'), '', '', '');
        echo $form->showDropdownOption('1', '1 ' . _('Year'), $new_renewal_years);
        echo $form->showDropdownOption('2', '2 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('3', '3 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('4', '4 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('5', '5 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('6', '6 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('7', '7 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('8', '8 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('9', '9 ' . _('Years'), $new_renewal_years);
        echo $form->showDropdownOption('10', '10 ' . _('Years'), $new_renewal_years);
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

        echo $form->showDropdownBottom('');

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

        echo $form->showDropdownBottom('');

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

        echo $form->showDropdownBottom('');

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

        echo $form->showDropdownBottom('');

    } elseif ($action == "CWH") {

        echo $form->showDropdownTop('new_whid', _('New Web Hosting Provider'), '', '1', '');
        echo $form->showDropdownOption('', $choose_text . ' ' . _('Web Hosting Provider'), $new_whid);

        $result_host = $pdo->query("
            SELECT id, `name`
            FROM hosting
            ORDER BY name ASC")->fetchAll();

        foreach ($result_host as $row_host) {

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $temp_to_compare = $new_whid;
            } else {
                $temp_to_compare = $_SESSION['s_default_host'];
            }

            echo $form->showDropdownOption($row_host->id, $row_host->name, $temp_to_compare);

        }

        echo $form->showDropdownBottom('');

    } elseif ($action == "CED") {

        if ($new_expiry_date != "") {
            $temp_expiry_date = $new_expiry_date;
        } else {
            $temp_expiry_date = $time->toUserTimezone($timestamp_basic, 'Y-m-d');
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

                <BR><h3>Custom Fields</h3><?php

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

                                echo $form->showCheckbox('new_' . $row->field_name, '1', $row->name, $row->description, '', '', '');

                            } elseif ($row->type_id === 2) { // Text

                                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '255', '', '', '', '');

                            } elseif ($row->type_id === 3) { // Text Area

                                echo $form->showInputTextarea('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '', '', '');

                            } elseif ($row->type_id === 4) { // Date

                                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '10', '', '', '', '');

                            } elseif ($row->type_id === 5) { // Time Stamp

                                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '19', '', '', '', '');

                            } elseif ($row->type_id === 6) { // URL

                                echo $form->showInputText('new_' . $row->field_name, $row->name, $row->description, ${'new_' . $row->field_name}, '255', '', '', '', '');

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

            echo $form->showInputHidden('whid', $new_whid);

        } ?>

        <a href='../'><?php echo $layout->showButton('button', _('Go Back')); ?></a>&nbsp;&nbsp;<?php

        if ($action == "AD") {

            echo $form->showSubmitButton(_('Add Domains'), '', '');

        } elseif ($action == "AN") {

            echo $form->showSubmitButton(_('Add Notes'), '', '');

        } elseif ($action == "FR") {

            echo $form->showSubmitButton(_('Mark As Renewed'), '', '');

        } elseif ($action == "RENEW") {

            echo $form->showSubmitButton(_('Mark As Renewed'), '', '');

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

            echo $form->showSubmitButton(_('Mark as Auto Renewal'), '', '');

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

            echo $form->showSubmitButton(_('Change Web Hosting Provider'), '', '');

        } elseif ($action == "DD") {

            echo $form->showSubmitButton(_('Delete Domains'), '', '');

        } else {

            echo $form->showSubmitButton(_('Perform Bulk Action'), '', '');

        }

    }

    echo $form->showInputHidden('is_submitted', '1');
    echo $form->showFormBottom('');

} else { ?>

    <a href='../'><?php echo $layout->showButton('button', _('Go Back')); ?></a><BR><BR><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/date-picker-footer.inc.php'; ?>
</body>
</html>
