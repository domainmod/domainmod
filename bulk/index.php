<?php
/**
 * /bulk/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$maint = new DomainMOD\Maintenance();
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$domain = new DomainMOD\Domain();

$timestamp = $time->stamp();
$timestamp_basic = $time->timeBasic();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/bulk-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$jumpMenu = $_GET['jumpMenu'];
$action = $_REQUEST['action'];
$raw_domain_list = $_POST['raw_domain_list'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_pcid = $_POST['new_pcid'];
$new_dnsid = $_POST['new_dnsid'];
$new_ipid = $_POST['new_ipid'];
$new_whid = $_POST['new_whid'];
$new_raid = $_POST['new_raid'];
$new_autorenew = $_POST['new_autorenew'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];
$new_renewal_years = $_POST['new_renewal_years'];
$new_field_type_id = $_POST['new_field_type_id'];
$type_id = $_REQUEST['type_id'];
$field_id = $_REQUEST['field_id'];

// Custom Fields
$sql = "SELECT field_name
        FROM domain_fields
        ORDER BY name";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) {

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field];

    }

}

$choose_text = "Click here to choose the new";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $format = new DomainMOD\Format();

    $domain_list = $format->cleanAndSplitDomains($raw_domain_list);

    if ($raw_domain_list == "") {

        $_SESSION['s_message_danger'] .= "Enter the list of domains to apply the action to<BR>";

    } else {

        list($invalid_to_display, $invalid_domains, $invalid_count, $temp_result_message) = $domain->findInvalidDomains($domain_list);

        if ($raw_domain_list == "" || $invalid_domains == 1) {

            if ($invalid_domains == 1) {

                if ($invalid_count == 1) {

                    $_SESSION['s_message_danger'] .= "There is " . number_format($invalid_count) . " invalid domain on your list<BR><BR>" . $temp_result_message;

                } else {

                    $_SESSION['s_message_danger'] .= "There are " . number_format($invalid_count) . " invalid domains on your list<BR><BR>" . $temp_result_message;

                    if (($invalid_count - $invalid_to_display) == 1) {

                        $_SESSION['s_message_danger'] .= "<BR>Plus " . number_format($invalid_count - $invalid_to_display) . " other<BR>";

                    } elseif (($invalid_count - $invalid_to_display) > 1) {

                        $_SESSION['s_message_danger'] .= "<BR>Plus " . number_format($invalid_count - $invalid_to_display) . " others<BR>";
                    }

                }

            } else {

                $_SESSION['s_message_danger'] .= "Enter the list of domains to apply the action to<BR>";

            }
            $submission_failed = 1;

        } else {

            $new_data_formatted = $format->formatForMysql($domain_list);

            if ($action == "RENEW") {

                foreach ($domain_list AS $each_domain) {

                    $domain->renew($conn, $each_domain, $new_renewal_years, $new_notes);

                }

                $_SESSION['s_message_success'] .= "Domains Renewed<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "AD") {

                $date = new DomainMOD\Date();

                reset($domain_list);

                // cycle through domains here
                while (list($key, $new_domain) = each($domain_list)) {

                    $query = "SELECT domain
                              FROM domains
                              WHERE domain = ?";
                    $q = $conn->stmt_init();

                    if ($q->prepare($query)) {

                        $q->bind_param('s', $new_domain);
                        $q->execute();
                        $q->store_result();

                        if ($q->num_rows() > 0) {

                            $has_existing_domains = '1';

                        }

                    }

                }

                if (!$date->checkDateFormat($new_expiry_date) || $new_pcid == "" || $new_dnsid == "" || $new_ipid == "" || $new_whid == "" || $new_raid == "" || $new_pcid == "0" || $new_dnsid == "0" || $new_ipid == "0" || $new_whid == "0" || $new_raid == "0" || $has_existing_domains == '1') {

                    if ($has_existing_domains == '1') $_SESSION['s_message_danger'] .= "At least one of the domains you entered already exists in " . $software_title . ".<BR><BR>You should run the domain list through a Segment filter to determine which one(s).<BR>";
                    if (!$date->checkDateFormat($new_expiry_date)) $_SESSION['s_message_danger'] .= "You have entered an invalid expiry date<BR>";
                    if ($new_pcid == "" || $new_pcid == "0") $_SESSION['s_message_danger'] .= "Please choose the new Category<BR>";
                    if ($new_dnsid == "" || $new_dnsid == "0") $_SESSION['s_message_danger'] .= "Please choose the new DNS Profile<BR>";
                    if ($new_ipid == "" || $new_ipid == "0") $_SESSION['s_message_danger'] .= "Please choose the new IP Address<BR>";
                    if ($new_whid == "" || $new_whid == "0") $_SESSION['s_message_danger'] .= "Please choose the new Web Hosting Provider<BR>";
                    if ($new_raid == "" || $new_raid == "0") $_SESSION['s_message_danger'] .= "Please choose the new Registrar Account<BR>";

                    $submission_failed = 1;

                } else {

                    $query = "SELECT owner_id, registrar_id
                              FROM registrar_accounts
                              WHERE id = ?";
                    $q = $conn->stmt_init();

                    if ($q->prepare($query)) {

                        $q->bind_param('i', $new_raid);
                        $q->execute();
                        $q->store_result();
                        $q->bind_result($t_owner_id, $t_registrar_id);

                        while ($q->fetch()) {

                            $temp_owner_id = $t_owner_id;
                            $temp_registrar_id = $t_registrar_id;

                        }

                        $q->close();

                    } else $error->outputSqlError($conn, "ERROR");

                    reset($domain_list);

                    // cycle through domains here
                    while (list($key, $new_domain) = each($domain_list)) {

                        $new_tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

                        $query = "SELECT id
                                  FROM fees
                                  WHERE registrar_id = ?
                                    AND tld = ?";
                        $q = $conn->stmt_init();

                        if ($q->prepare($query)) {

                            $q->bind_param('is', $temp_registrar_id, $new_tld);
                            $q->execute();
                            $q->store_result();
                            $q->bind_result($id);

                            while ($q->fetch()) {

                                $temp_fee_id = $id;

                            }

                            $q->close();

                        } else $error->outputSqlError($conn, "ERROR");

                        if ($temp_fee_id == '0' || $temp_fee_id == "") {
                            $temp_fee_fixed = 0;
                            $temp_fee_id = 0;
                        } else {
                            $temp_fee_fixed = 1;
                        }

                        if ($new_privacy == "1") {

                            $fee_string = "renewal_fee + privacy_fee + misc_fee";

                        } else {

                            $fee_string = "renewal_fee + misc_fee";

                        }

                        $query = "SELECT id, (" . $fee_string . ") AS total_cost
                                  FROM fees
                                  WHERE registrar_id = ?
                                    AND tld = ?";
                        $q = $conn->stmt_init();

                        if ($q->prepare($query)) {

                            $q->bind_param('is', $temp_registrar_id, $new_tld);
                            $q->execute();
                            $q->store_result();
                            $q->bind_result($id, $cost);

                            while ($q->fetch()) {

                                $new_total_cost = $cost;

                            }

                            if (!$new_total_cost || $new_total_cost == '') $new_total_cost = 0;

                            $q->close();

                        } else $error->outputSqlError($conn, "ERROR");

                        $query = "INSERT INTO domains
                                  (owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id,
                                   total_cost, dns_id, ip_id, hosting_id, `function`, notes, autorenew, privacy,
                                   creation_type_id, created_by, active, fee_fixed, insert_time)
                                  VALUES
                                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $q = $conn->stmt_init();

                        if ($q->prepare($query)) {

                            $creation_type_id = $system->getCreationTypeId($connection, 'Bulk Updater');

                            $q->bind_param('iiisssiidiiissiiiiiis', $temp_owner_id, $temp_registrar_id, $new_raid,
                                $new_domain, $new_tld, $new_expiry_date, $new_pcid, $temp_fee_id, $new_total_cost,
                                $new_dnsid, $new_ipid, $new_whid, $new_function, $new_notes, $new_autorenew,
                                $new_privacy, $creation_type_id, $_SESSION['s_user_id'], $new_active, $temp_fee_fixed,
                                $timestamp);
                            $q->execute() or $error->outputSqlError($conn, "Couldn't insert domains");
                            $q->close();

                        } else $error->outputSqlError($conn, "ERROR");

                        $temp_fee_id = 0;

                        $query = "SELECT id
                                  FROM domains
                                  WHERE domain = ?
                                    AND insert_time = ?";
                        $q = $conn->stmt_init();

                        if ($q->prepare($query)) {

                            $q->bind_param('ss', $new_domain, $timestamp);
                            $q->execute();
                            $q->store_result();
                            $q->bind_result($id);

                            while ($q->fetch()) {

                                $temp_domain_id = $id;

                            }

                            $q->close();

                        } else $error->outputSqlError($conn, "ERROR");

                        $query = "INSERT INTO domain_field_data
                                  (domain_id, insert_time)
                                  VALUES
                                  (?, ?)";
                        $q = $conn->stmt_init();

                        if ($q->prepare($query)) {

                            $q->bind_param('is', $temp_domain_id, $timestamp);
                            $q->execute();
                            $q->close();

                        } else $error->outputSqlError($conn, "ERROR");

                        $sql = "SELECT field_name
                                FROM domain_fields
                                ORDER BY `name`";
                        $result = mysqli_query($connection, $sql);

                        if (mysqli_num_rows($result) > 0) {

                            $count = 0;

                            while ($row = mysqli_fetch_object($result)) {

                                $field_array[$count] = $row->field_name;
                                $count++;

                            }

                            foreach ($field_array as $field) {

                                $full_field = "new_" . $field;

                                $sql = "UPDATE domain_field_data
                                        SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "'
                                        WHERE domain_id = '" . $temp_domain_id . "'";
                                $result = mysqli_query($connection, $sql);

                            }

                        }

                        // finish cycling through domains here
                    }

                    $_SESSION['s_message_success'] .= "Domains Added<BR>";

                    $queryB = new DomainMOD\QueryBuild();

                    $sql = $queryB->missingFees('domains');
                    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($connection, $sql);

                    $maint->updateSegments($connection);

                    $maint->updateTlds($connection);

                }

            } elseif ($action == "FR") {

                $sql = "SELECT domain, expiry_date
                        FROM domains
                        WHERE domain IN (" . $new_data_formatted . ")";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {

                    $expiry_pieces = explode("-", $row->expiry_date);
                    $old_expiry = $expiry_pieces[0] . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];
                    $new_expiry = $expiry_pieces[0] + $new_renewal_years . "-" . $expiry_pieces[1] . "-" . $expiry_pieces[2];

                    if ($new_renewal_years == "1") {
                        $renewal_years_string = $new_renewal_years . " Year";
                    } else {
                        $renewal_years_string = $new_renewal_years . " Years";
                    }

                    if ($new_notes != "") {

                        $new_notes_renewal = $timestamp_basic . " - Domain Renewed For " . $renewal_years_string;

                        $sql_update = "UPDATE domains
                                       SET expiry_date = '" . mysqli_real_escape_string($connection, $new_expiry) . "',
                                              notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', '" . mysqli_real_escape_string($connection, $new_notes_renewal) . "\r\n\r\n', notes),
                                           active = '1',
                                           update_time = '" . $timestamp . "'
                                       WHERE domain = '" . $row->domain . "'";

                    } else {

                        $new_notes_renewal = $timestamp_basic . " - Domain Renewed For " . $renewal_years_string;

                        $sql_update = "UPDATE domains
                                       SET expiry_date = '" . mysqli_real_escape_string($connection, $new_expiry) . "',
                                              notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes_renewal) . "\r\n\r\n', notes),
                                           active = '1',
                                           update_time = '" . $timestamp . "'
                                       WHERE domain = '" . $row->domain . "'";

                    }
                    $result_update = mysqli_query($connection, $sql_update);

                }

                $maint->updateSegments($connection);

                $_SESSION['s_message_success'] .= "Domains Fully Renewed<BR>";

            } elseif ($action == "CPC") {

                if ($new_pcid == "" || $new_pcid == 0) {

                    $_SESSION['s_message_danger'] .= "Please choose the new Category<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET cat_id  = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('isss', $new_pcid, $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET cat_id  = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iss', $new_pcid, $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $_SESSION['s_message_success'] .= "Category Changed<BR>";

                }

            } elseif ($action == "CDNS") {

                if ($new_dnsid == "" || $new_dnsid == 0) {

                    $_SESSION['s_message_danger'] .= "Please choose the new DNS Profile<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET dns_id  = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('isss', $new_dnsid, $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET dns_id  = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iss', $new_dnsid, $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $_SESSION['s_message_success'] .= "DNS Profile Changed<BR>";
                }

            } elseif ($action == "CIP") {

                if ($new_ipid == "" || $new_ipid == 0) {

                    $_SESSION['s_message_danger'] .= "Please choose the new IP Address<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET ip_id  = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('isss', $new_ipid, $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET ip_id  = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iss', $new_ipid, $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $_SESSION['s_message_success'] .= "IP Address Changed<BR>";

                }

            } elseif ($action == "AN") {

                if ($new_notes == "") {

                    $_SESSION['s_message_danger'] .= "Enter the new Note<BR>";
                    $submission_failed = 1;

                } else {

                    $query = "UPDATE domains
                              SET notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                    $_SESSION['s_message_success'] .= "Note Added<BR>";

                }

            } elseif ($action == "CRA") {

                if ($new_raid == "" || $new_raid == 0) {

                    $_SESSION['s_message_danger'] .= "Please choose the new Registrar Account<BR>";
                    $submission_failed = 1;

                } else {

                    $query = "SELECT ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS r_name, o.id AS o_id,
                                  o.name AS o_name
                              FROM registrar_accounts AS ra, registrars AS r, owners AS o
                              WHERE ra.registrar_id = r.id
                                AND ra.owner_id = o.id
                                AND ra.id = ?
                              GROUP BY r.name, o.name, ra.username
                              ORDER BY r.name ASC, o.name ASC, ra.username ASC";
                    $q = $conn->stmt_init();

                    if ($q->prepare($query)) {

                        $q->bind_param('i', $new_raid);
                        $q->execute();
                        $q->store_result();
                        $q->bind_result($ra_id, $username, $r_id, $r_name, $o_id, $o_name);

                        while ($q->fetch()) {

                            $new_registrar_account_id = $ra_id;
                            $new_username = $username;
                            $new_registrar_id = $r_id;
                            $new_registrar_name = $r_name;
                            $new_owner_id = $o_id;
                            $new_owner_name = $o_name;

                        }

                        $q->close();

                    } else $error->outputSqlError($conn, "ERROR");

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET owner_id = ?,
                                      registrar_id = ?,
                                      account_id = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iiisss', $new_owner_id, $new_registrar_id, $new_registrar_account_id,
                                $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET owner_id = ?,
                                      registrar_id = ?,
                                      account_id = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iiiss', $new_owner_id, $new_registrar_id, $new_registrar_account_id,
                                $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $sql = "UPDATE domains
                            SET fee_id = '0', total_cost = '0'
                            WHERE domain IN (" . $new_data_formatted . ")";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                    $sql = "SELECT d.id, f.id AS fee_id
                            FROM domains AS d, fees AS f
                            WHERE d.registrar_id = f.registrar_id
                              AND d.tld = f.tld
                              AND d.domain IN (" . $new_data_formatted . ")";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                    while ($row = mysqli_fetch_object($result)) {

                        $sql_update = "UPDATE domains
                                       SET fee_id = '" . $row->fee_id . "'
                                       WHERE id = '" . $row->id . "'";
                        $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

                    }

                    $sql = "UPDATE domains d
                            JOIN fees f ON d.fee_id = f.id
                            SET d.total_cost = f.renewal_fee + f.privacy_fee + f.misc_fee
                            WHERE d.privacy = '1'
                              AND d.domain IN (" . $new_data_formatted . ")";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                    $sql = "UPDATE domains d
                            JOIN fees f ON d.fee_id = f.id
                            SET d.total_cost = f.renewal_fee + f.misc_fee
                            WHERE d.privacy = '0'
                              AND d.domain IN (" . $new_data_formatted . ")";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                    $_SESSION['s_message_success'] .= "Registrar Account Changed<BR>";

                    $queryB = new DomainMOD\QueryBuild();

                    $sql = $queryB->missingFees('domains');
                    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($connection, $sql);

                }

            } elseif ($action == "CWH") {

                if ($new_whid == "" || $new_whid == 0) {

                    $_SESSION['s_message_danger'] .= "Please choose the new Web Hosting Provider<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET hosting_id  = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('isss', $new_whid, $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET hosting_id  = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('iss', $new_whid, $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $_SESSION['s_message_success'] .= "Web Hosting Provider Changed<BR>";

                }

            } elseif ($action == "DD") {

                $sql = "SELECT id
                        FROM domains
                        WHERE domain IN (" . $new_data_formatted . ")";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                if (mysqli_num_rows($result) > 0) {

                    while ($row = mysqli_fetch_object($result)) {

                        $domain_id_list .= "'" . $row->id . "', ";

                    }

                    $domain_id_list_formatted = substr($domain_id_list, 0, -2);

                    $sql_domain = "DELETE FROM domains
                                   WHERE id IN (" . $domain_id_list_formatted . ")";
                    $result_domain = mysqli_query($connection, $sql_domain) or $error->outputOldSqlError($connection);

                    $sql_domain = "DELETE FROM domain_field_data
                                   WHERE domain_id IN (" . $domain_id_list_formatted . ")";
                    $result_domain = mysqli_query($connection, $sql_domain) or $error->outputOldSqlError($connection);

                    $sql_ssl = "SELECT id
                                FROM ssl_certs
                                WHERE domain_id IN (" . $domain_id_list_formatted . ")";
                    $result_ssl = mysqli_query($connection, $sql_ssl) or $error->outputOldSqlError($connection);

                    if (mysqli_num_rows($result_ssl) > 0) {

                        while ($row_ssl = mysqli_fetch_object($result_ssl)) {

                            $ssl_id_list .= "'" . $row_ssl->id . "', ";

                        }

                        $ssl_id_list_formatted = substr($ssl_id_list, 0, -2);

                        $sql_ssl = "DELETE FROM ssl_certs
                                    WHERE domain_id IN (" . $domain_id_list_formatted . ")";
                        $result_ssl = mysqli_query($connection, $sql_ssl) or $error->outputOldSqlError($connection);

                        $sql_ssl = "DELETE FROM ssl_cert_field_data
                                    WHERE ssl_id IN (" . $ssl_id_list_formatted . ")";
                        $result_ssl = mysqli_query($connection, $sql_ssl) or $error->outputOldSqlError($connection);

                    }

                }

                $_SESSION['s_message_success'] .= "Domains (and associated data) Deleted<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "E") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '0',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '0',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as expired<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "S") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '10',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '10',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as sold<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "A") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '1',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '1',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as active<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "T") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '2',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '2',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Pending Transfer<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "PRg") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '5',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '5',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Pending Registration<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "PRn") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '3',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '3',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Pending Renewal<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "PO") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET active = '4',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET active = '4',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Pending (Other)<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "AURNE") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET autorenew = '1',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET autorenew = '1',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Auto Renewal<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "AURND") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET autorenew = '0',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET autorenew = '0',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $_SESSION['s_message_success'] .= "Domains marked as Manual Renewal<BR>";

            } elseif ($action == "PRVE") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET privacy = '1',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET privacy = '1',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $sql = "SELECT d.id, (f.renewal_fee + f.privacy_fee + f.misc_fee) AS total_cost
                            FROM domains AS d, fees AS f
                            WHERE d.fee_id = f.id
                              AND d.domain IN (" . $new_data_formatted . ")";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {

                    $sql_update = "UPDATE domains
                                       SET total_cost = '" . $row->total_cost . "'
                                       WHERE id = '" . $row->id . "'";
                    $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

                }

                $_SESSION['s_message_success'] .= "Domains marked as Private WHOIS<BR>";

                $maint->updateSegments($connection);

            } elseif ($action == "PRVD") {

                if ($new_notes != "") {

                    $query = "UPDATE domains
                              SET privacy = '0',
                                  notes = CONCAT(?, '\r\n\r\n', notes),
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('sss', $new_notes, $timestamp, $each_domain);
                        $q->execute();

                    }

                } else {

                    $query = "UPDATE domains
                              SET privacy = '0',
                                  update_time = ?
                              WHERE domain = ?";
                    $q = $conn->stmt_init();
                    $stmt = $q->prepare($query);

                    foreach ($domain_list AS $each_domain) {

                        $q->bind_param('ss', $timestamp, $each_domain);
                        $q->execute();

                    }

                }

                $sql = "SELECT d.id, (f.renewal_fee + f.misc_fee) AS total_cost
                            FROM domains AS d, fees AS f
                            WHERE d.fee_id = f.id
                              AND d.domain IN (" . $new_data_formatted . ")";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {

                    $sql_update = "UPDATE domains
                                       SET total_cost = '" . $row->total_cost . "'
                                       WHERE id = '" . $row->id . "'";
                    $result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

                }

                $_SESSION['s_message_success'] .= "Domains marked as Public WHOIS<BR>";

            } elseif ($action == "CED") {

                if (!$date->checkDateFormat($new_expiry_date)) {

                    $_SESSION['s_message_danger'] .= "The expiry date you entered is invalid<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $query = "UPDATE domains
                                  SET expiry_date = ?,
                                      notes = CONCAT(?, '\r\n\r\n', notes),
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('ssss', $new_expiry_date, $new_notes, $timestamp, $each_domain);
                            $q->execute();

                        }

                    } else {

                        $query = "UPDATE domains
                                  SET expiry_date = ?,
                                      update_time = ?
                                  WHERE domain = ?";
                        $q = $conn->stmt_init();
                        $stmt = $q->prepare($query);

                        foreach ($domain_list AS $each_domain) {

                            $q->bind_param('sss', $new_expiry_date, $timestamp, $each_domain);
                            $q->execute();

                        }

                    }

                    $_SESSION['s_message_success'] .= "Expiry Date Updated<BR>";

                }

            } elseif ($action == "UCF") {

                $sql = "SELECT id
                        FROM domains
                        WHERE domain IN (" . $new_data_formatted . ")";
                $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                while ($row = mysqli_fetch_object($result)) {

                    $domain_id_list .= "'" . $row->id . "', ";

                }

                $domain_id_list_formatted = substr($domain_id_list, 0, -2);

                $query = "SELECT `name`, field_name
                          FROM domain_fields
                          WHERE id = ?";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->bind_param('i', $field_id);
                    $q->execute();
                    $q->store_result();
                    $q->bind_result($name, $field_name);

                    while ($q->fetch()) {

                        $temp_name = $name;
                        $temp_field_name = $field_name;

                    }

                    $q->close();

                } else $error->outputSqlError($conn, "ERROR");

                $full_field = "new_" . $temp_field_name;

                $sql = "UPDATE domain_field_data
                        SET `" . $temp_field_name . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "',
                             update_time = '" . $timestamp . "'
                        WHERE domain_id IN (" . $domain_id_list_formatted . ")";
                $result = mysqli_query($connection, $sql);

                if ($new_notes != "") {

                    $sql = "UPDATE domains
                            SET notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
                                update_time = '" . $timestamp . "'
                            WHERE id IN (" . $domain_id_list_formatted . ")";
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                }

                $_SESSION['s_message_success'] .= "Custom Field " . $name_array[0] . " Updated<BR>";

            }

            $done = "1";
            reset($domain_list);
            $new_data_unformatted = implode(", ", $domain_list);

        }

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
    <?php echo $layout->jumpMenu(); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php //@formatter:off
if ($action == "AD") { $breadcrumb_text = 'Add Domains'; }
elseif ($action == "RENEW") { $breadcrumb_text = 'Renew Domains'; }
elseif ($action == "FR") { $breadcrumb_text = 'Fully Renew Domains'; }
elseif ($action == "E") { $breadcrumb_text = 'Mark as Expired'; }
elseif ($action == "S") { $breadcrumb_text = 'Mark as Sold'; }
elseif ($action == "A") { $breadcrumb_text = 'Mark as Active'; }
elseif ($action == "T") { $breadcrumb_text = 'Mark as Pending Transfer'; }
elseif ($action == "PRg") { $breadcrumb_text = 'Mark as Pending Registration'; }
elseif ($action == "PRn") { $breadcrumb_text = 'Mark as Pending Renewal'; }
elseif ($action == "PO") { $breadcrumb_text = 'Mark as Pending (Other)'; }
elseif ($action == "AURNE") { $breadcrumb_text = 'Mark as Auto Renewal'; }
elseif ($action == "AURND") { $breadcrumb_text = 'Mark as Manual Renewal'; }
elseif ($action == "PRVE") { $breadcrumb_text = 'Mark as Private WHOIS'; }
elseif ($action == "PRVD") { $breadcrumb_text = 'Mark as Public WHOIS'; }
elseif ($action == "CED") { $breadcrumb_text = 'Change Expiry Date'; }
elseif ($action == "CPC") { $breadcrumb_text = 'Change Category'; }
elseif ($action == "CDNS") { $breadcrumb_text = 'Change DNS Profile'; }
elseif ($action == "CIP") { $breadcrumb_text = 'Change IP Address'; }
elseif ($action == "CRA") { $breadcrumb_text = 'Change Registrar Account'; }
elseif ($action == "CWH") { $breadcrumb_text = 'Change Hosting Provider'; }
elseif ($action == "DD") { $breadcrumb_text = 'Delete Domains'; }
elseif ($action == "AN") { $breadcrumb_text = 'Add A Note'; }
elseif ($action == "UCF") { $breadcrumb_text = 'Update Custom Domain Field'; }
else { $breadcrumb_text = ''; }

if ($breadcrumb_text != '') {
    $breadcrumb_end = '<li class=\"active\">' . $breadcrumb_text . '</li>';
} //@formatter:on
?>

<?php include(DIR_INC . "layout/header.inc.php"); ?>
The Bulk Updater allows you add or modify multiple domains at the same time, whether it's a couple dozen or a couple thousand, all with a few clicks.<BR>
<?php if ($done == "1") { ?>

    <?php if ($submission_failed != "1") { ?>

        <?php if ($action == "AD") { ?>
            <BR><strong>The following domains were added:</strong><BR>
        <?php } elseif ($action == "RENEW") { ?>
            <BR><strong>The following domains were renewed for <?php echo htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8'); ?>
                year<?php if ($new_renewal_years > 1) {
                    echo "s";
                } ?>:</strong><BR>
        <?php } elseif ($action == "FR") { ?>
            <BR><strong>The following domains were fully renewed for <?php echo htmlentities($new_renewal_years, ENT_QUOTES, 'UTF-8'); ?>
                year<?php if ($new_renewal_years > 1) {
                    echo "s";
                } ?>:</strong><BR>
        <?php } elseif ($action == "E") { ?>
            <BR><strong>The following domains were marked as expired:</strong><BR>
        <?php } elseif ($action == "S") { ?>
            <BR><strong>The following domains were marked as sold:</strong><BR>
        <?php } elseif ($action == "A") { ?>
            <BR><strong>The following domains were marked as active:</strong><BR>
        <?php } elseif ($action == "T") { ?>
            <BR><strong>The following domains were marked as Pending Transfer:</strong><BR>
        <?php } elseif ($action == "PRg") { ?>
            <BR><strong>The following domains were marked as Pending Registration:</strong><BR>
        <?php } elseif ($action == "PRn") { ?>
            <BR><strong>The following domains were marked as Pending Renewal:</strong><BR>
        <?php } elseif ($action == "PO") { ?>
            <BR><strong>The following domains were marked as Pending (Other):</strong><BR>
        <?php } elseif ($action == "AURNE") { ?>
            <BR><strong>The following domains were marked as Auto Renewal:</strong><BR>
        <?php } elseif ($action == "AURND") { ?>
            <BR><strong>The following domains were marked as Manual Renewal:</strong><BR>
        <?php } elseif ($action == "PRVE") { ?>
            <BR><strong>The following domains were marked as Private WHOIS:</strong><BR>
        <?php } elseif ($action == "PRVD") { ?>
            <BR><strong>The following domains were marked as Public WHOIS:</strong><BR>
        <?php } elseif ($action == "CED") { ?>
            <BR><strong>The expiry date was updated for the following domains:</strong><BR>
        <?php } elseif ($action == "CPC") { ?>
            <BR><strong>The following domains had their Category changed:</strong><BR>
        <?php } elseif ($action == "CDNS") { ?>
            <BR><strong>The following domains had their DNS Profile changed:</strong><BR>
        <?php } elseif ($action == "CIP") { ?>
            <BR><strong>The following domains had their IP Address changed:</strong><BR>
        <?php } elseif ($action == "CRA") { ?>
            <BR><strong>The following domains had their Registrar Account changed:</strong><BR>
        <?php } elseif ($action == "CWH") { ?>
            <BR><strong>The following domains had their Web Hosting Provider changed:</strong><BR>
        <?php } elseif ($action == "DD") { ?>
            <BR><strong>The following domains (and associated SSL Certificates) were deleted:</strong><BR>
        <?php } elseif ($action == "AN") { ?>
            <BR><strong>The following domains had the Note appended:</strong><BR>
        <?php } elseif ($action == "UCF") { ?>
            <BR><strong>The following domains had their Custom Domain Field updated:</strong><BR>
        <?php } ?>

        <BR><?php echo htmlentities($new_data_unformatted, ENT_QUOTES, 'UTF-8'); ?><BR><BR>
    <?php } ?>

<?php } ?>

<?php
echo $form->showFormTop('');
echo $form->showDropdownTopJump('', '', '', '');
echo $form->showDropdownOptionJump('index.php', '', 'Choose Action', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'AD', 'Add Domains', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'AN', 'Add A Note', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'FR', 'Renew Domains (Update Expiry Date, Mark Active, Add Note)', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'RENEW', 'Renew Domains (Update Expiry Date Only)', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'A', 'Mark as Active', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'T', 'Mark as Pending Transfer', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'PRg', 'Mark as Pending Registration', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'PRn', 'Mark as Pending Renewal', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'PO', 'Mark as Pending (Other)', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'E', 'Mark as Expired', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'S', 'Mark as Sold', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'AURNE', 'Mark as Auto Renewal', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'AURND', 'Mark as Manual Renewal', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'PRVE', 'Mark as Private WHOIS', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'PRVD', 'Mark as Public WHOIS', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CPC', 'Change Category', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CDNS', 'Change DNS Profile', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CED', 'Change Expiry Date', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CIP', 'Change IP Address', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CRA', 'Change Registrar Account', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'CWH', 'Change Web Hosting Provider', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'UCF', 'Update Custom Domain Field', $action);
echo $form->showDropdownOptionJump('index.php?action=', 'DD', 'Delete Domains', $action);
echo $form->showDropdownBottom('');

if ($action == "UCF") {

    echo $form->showDropdownTopJump('', '', '', '');
    echo $form->showDropdownOptionJump('index.php?action=', 'UCF', 'Choose the Custom Field to Edit', $action);

    $sql = "SELECT df.id, df.name, df.type_id, cft.name AS type
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
            ORDER BY df.name";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {

        echo $form->showDropdownOptionJump('index.php?action=UCF&type_id=' . $row->type_id . '&field_id=', $row->id, $row->name . ' (' . $row->type . ')', $field_id);

    }

    echo $form->showDropdownBottom('');

}

if (($action != "" && $action != "UCF") || ($action == "UCF" && $type_id != "")) {

    if ($action == "DD") {

        echo "<strong>WARNING: In addition to deleting the domains, all SSL certificates and custom field data associated with the domains and SSL certificates will also be deleted. If you don't want to completely remove all traces of the domains from the system you may be better off marking them as expired instead.</strong><BR><BR>";
    }

    if ($action == "AD") {

        $text = 'Domains to add (one per line)';

    } else {

        $text = 'Domains to update (one per line)';

    }

    echo $form->showInputTextarea('raw_domain_list', $text, '', $raw_domain_list, '1', '', '');

}

// Display forms for various actions
if ($action == "AD") { // Add Domains

    // Function
    echo $form->showInputText('new_function', 'Function (255)', '', $new_function, '255', '', '', '', '');

    // Expiry Date
    if ($new_expiry_date != "") {
        $temp_expiry_date = $new_expiry_date;
    } else {
        $temp_expiry_date = $timestamp_basic_plus_one_year;
    }
    echo $form->showInputText('new_expiry_date', 'Expiry Date (YYYY-MM-DD)', '', $temp_expiry_date, '10', '', '1', '', '');

    // Registrar Account
    echo $form->showDropdownTop('new_raid', 'Registrar Account', '', '1', '');

    $sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                    FROM registrar_accounts AS ra, owners AS o, registrars AS r
                    WHERE ra.owner_id = o.id
                      AND ra.registrar_id = r.id
                    ORDER BY r_name, o_name, ra.username";
    $result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);

    while ($row_account = mysqli_fetch_object($result_account)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_raid;
        } else {
            $temp_to_compare = $_SESSION['s_default_registrar_account'];
        }

        echo $form->showDropdownOption($row_account->id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $temp_to_compare);

    }
    echo $form->showDropdownBottom('');

    // DNS Profile
    echo $form->showDropdownTop('new_dnsid', 'DNS Profile', '', '1', '');

    $sql_dns = "SELECT id, `name`
                FROM dns
                ORDER BY name";
    $result_dns = mysqli_query($connection, $sql_dns) or $error->outputOldSqlError($connection);

    while ($row_dns = mysqli_fetch_object($result_dns)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_dnsid;
        } else {
            $temp_to_compare = $_SESSION['s_default_dns'];
        }

        echo $form->showDropdownOption($row_dns->id, $row_dns->name, $temp_to_compare);

    }
    echo $form->showDropdownBottom('');

    // IP Address
    echo $form->showDropdownTop('new_ipid', 'IP Address', '', '1', '');

    $sql_ip = "SELECT id, `name`, ip
               FROM ip_addresses
               ORDER BY name, ip";
    $result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);

    while ($row_ip = mysqli_fetch_object($result_ip)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_ipid;
        } else {
            $temp_to_compare = $_SESSION['s_default_ip_address_domains'];
        }

        echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $temp_to_compare);

    }
    echo $form->showDropdownBottom('');

    // Web Hosting Provider
    echo $form->showDropdownTop('new_whid', 'Web Hosting Provider', '', '1', '');

    $sql_host = "SELECT id, `name`
                 FROM hosting
                 ORDER BY name";
    $result_host = mysqli_query($connection, $sql_host) or $error->outputOldSqlError($connection);

    while ($row_host = mysqli_fetch_object($result_host)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_whid;
        } else {
            $temp_to_compare = $_SESSION['s_default_host'];
        }

        echo $form->showDropdownOption($row_host->id, $row_host->name, $temp_to_compare);

    }
    echo $form->showDropdownBottom('');

    // Category
    echo $form->showDropdownTop('new_pcid', 'Category', '', '1', '');

    $sql_cat = "SELECT id, `name`
                FROM categories
                ORDER BY name";
    $result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);

    while ($row_cat = mysqli_fetch_object($result_cat)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_pcid;
        } else {
            $temp_to_compare = $_SESSION['s_default_category_domains'];
        }

        echo $form->showDropdownOption($row_cat->id, $row_cat->name, $temp_to_compare);

    }
    echo $form->showDropdownBottom('');

    // Domain Status
    echo $form->showDropdownTop('new_active', 'Domain Status', '', '', '');
    echo $form->showDropdownOption('1', 'Active', $new_active);
    echo $form->showDropdownOption('5', 'Pending (Registration)', $new_active);
    echo $form->showDropdownOption('3', 'Pending (Renewal)', $new_active);
    echo $form->showDropdownOption('2', 'Pending (Transfer)', $new_active);
    echo $form->showDropdownOption('4', 'Pending (Other)', $new_active);
    echo $form->showDropdownOption('0', 'Expired', $new_active);
    echo $form->showDropdownOption('10', 'Sold', $new_active);
    echo $form->showDropdownBottom('');

    // Auto Renewal
    echo $form->showRadioTop('Auto Renewal?', '', '');
    if ($new_autorenew == '') $new_autorenew = '1';
    echo $form->showRadioOption('new_autorenew', '1', 'Yes', $new_autorenew, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
    echo $form->showRadioOption('new_autorenew', '0', 'No', $new_autorenew, '', '');
    echo $form->showRadioBottom('');

    // WHOIS Privacy Status
    if ($new_privacy == '') $new_privacy = '1';
    echo $form->showRadioTop('Privacy Enabled?', '', '');
    echo $form->showRadioOption('new_privacy', '1', 'Yes', $new_privacy, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
    echo $form->showRadioOption('new_privacy', '0', 'No', $new_privacy, '', '');
    echo $form->showRadioBottom('');

} elseif ($action == "RENEW" || $action == "FR") {

    echo $form->showDropdownTop('new_renewal_years', 'Renew For', '', '', '');
    echo $form->showDropdownOption('1', '1 Year', $new_renewal_years);
    echo $form->showDropdownOption('2', '2 Years', $new_renewal_years);
    echo $form->showDropdownOption('3', '3 Years', $new_renewal_years);
    echo $form->showDropdownOption('4', '4 Years', $new_renewal_years);
    echo $form->showDropdownOption('5', '5 Years', $new_renewal_years);
    echo $form->showDropdownOption('6', '6 Years', $new_renewal_years);
    echo $form->showDropdownOption('7', '7 Years', $new_renewal_years);
    echo $form->showDropdownOption('8', '8 Years', $new_renewal_years);
    echo $form->showDropdownOption('9', '9 Years', $new_renewal_years);
    echo $form->showDropdownOption('10', '10 Years', $new_renewal_years);
    echo $form->showDropdownBottom('');

} elseif ($action == "CPC") {

    echo $form->showDropdownTop('new_pcid', 'New Category', '', '1', '');
    echo $form->showDropdownOption('', $choose_text . ' Category', $new_pcid);

    $sql_cat = "SELECT id, `name`
                FROM categories
                ORDER BY name";
    $result_cat = mysqli_query($connection, $sql_cat);
    while ($row_cat = mysqli_fetch_object($result_cat)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_pcid;
        } else {
            $temp_to_compare = $_SESSION['s_default_category_domains'];
        }

        echo $form->showDropdownOption($row_cat->id, $row_cat->name, $temp_to_compare);


    }

    echo $form->showDropdownBottom('');

} elseif ($action == "CDNS") {

    echo $form->showDropdownTop('new_dnsid', 'New DNS Profile', '', '1', '');
    echo $form->showDropdownOption('', $choose_text . ' DNS Profile', $new_dnsid);

    $sql_dns = "SELECT id, `name`
                FROM dns
                ORDER BY name ASC";
    $result_dns = mysqli_query($connection, $sql_dns);

    while ($row_dns = mysqli_fetch_object($result_dns)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_dnsid;
        } else {
            $temp_to_compare = $_SESSION['s_default_dns'];
        }

        echo $form->showDropdownOption($row_dns->id, $row_dns->name, $temp_to_compare);

    }

    echo $form->showDropdownBottom('');

} elseif ($action == "CIP") {

    echo $form->showDropdownTop('new_ipid', 'New IP Address', '', '1', '');
    echo $form->showDropdownOption('', $choose_text . ' IP Address', $new_ipid);

    $sql_ip = "SELECT id, `name`, ip
               FROM ip_addresses
               ORDER BY name ASC, ip ASC";
    $result_ip = mysqli_query($connection, $sql_ip);

    while ($row_ip = mysqli_fetch_object($result_ip)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_ipid;
        } else {
            $temp_to_compare = $_SESSION['s_default_ip_address_domains'];
        }

        echo $form->showDropdownOption($row_ip->id, $row_ip->name . ' (' . $row_ip->ip . ')', $temp_to_compare);

    }

    echo $form->showDropdownBottom('');

} elseif ($action == "CRA") {

    echo $form->showDropdownTop('new_raid', 'New Registrar Account', '', '1', '');
    echo $form->showDropdownOption('', $choose_text . ' Registrar Account', $new_raid);

    $sql_account = "SELECT ra.id AS ra_id, ra.username, r.name AS r_name, o.name AS o_name
                    FROM registrar_accounts AS ra, registrars AS r, owners AS o
                    WHERE ra.registrar_id = r.id
                      AND ra.owner_id = o.id
                      $is_active_string
                      $oid_string
                      $rid_string
                      $tld_string
                    GROUP BY r.name, o.name, ra.username
                    ORDER BY r.name asc, o.name asc, ra.username asc";
    $result_account = mysqli_query($connection, $sql_account);

    while ($row_account = mysqli_fetch_object($result_account)) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $temp_to_compare = $new_raid;
        } else {
            $temp_to_compare = $_SESSION['s_default_registrar_account'];
        }

        echo $form->showDropdownOption($row_account->ra_id, $row_account->r_name . ', ' . $row_account->o_name . ' (' . $row_account->username . ')', $temp_to_compare);

    }

    echo $form->showDropdownBottom('');

} elseif ($action == "CWH") {

    echo $form->showDropdownTop('new_whid', 'New Web Hosting Provider', '', '1', '');
    echo $form->showDropdownOption('', $choose_text . ' Web Hosting Provider', $new_whid);

    $sql_host = "SELECT id, `name`
                 FROM hosting
                 ORDER BY name ASC";
    $result_host = mysqli_query($connection, $sql_host);

    while ($row_host = mysqli_fetch_object($result_host)) {

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
        $temp_expiry_date = $timestamp_basic;
    }

    echo $form->showInputText('new_expiry_date', 'New Expiry Date (YYYY-MM-DD)', '', $temp_expiry_date, '10', '', '1', '', '');

} elseif ($action == "UCF") {

    if ($type_id == "1") {

        $query = "SELECT df.name, df.field_name, df.description
                  FROM domain_fields AS df, custom_field_types AS cft
                  WHERE df.type_id = cft.id
                    AND df.id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $field_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_name, $temp_field_name, $temp_description);

            while ($q->fetch()) {

                echo $form->showCheckbox('new_' . $temp_field_name, '1', $temp_name, $temp_description, ${'new_' . $temp_field_name}, '', '');

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    } elseif ($type_id == "2") {

        $query = "SELECT df.name, df.field_name, df.description
                  FROM domain_fields AS df, custom_field_types AS cft
                  WHERE df.type_id = cft.id
                    AND df.id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $field_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_name, $temp_field_name, $temp_description);

            while ($q->fetch()) {

                echo $form->showInputText('new_' . $temp_field_name, $temp_name . ' (255)', $temp_description, ${'new_' . $temp_field_name}, '255', '', '', '', '');

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    } elseif ($type_id == "3") {

        $query = "SELECT df.name, df.field_name, df.type_id, df.description
                  FROM domain_fields AS df, custom_field_types AS cft
                  WHERE df.type_id = cft.id
                    AND df.id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $field_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_name, $temp_field_name, $temp_description);

            while ($q->fetch()) {

                echo $form->showInputTextarea('new_' . $temp_field_name, $temp_name, $temp_description, ${'new_' . $temp_field_name}, '', '', '');

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    }

}

if (($action != "" && $action != "UCF") || ($action == "UCF" && $type_id != "")) {

    if ($action == "AD") {

        $notes_heading = 'Notes';

    } elseif ($action == "") {

        $notes_heading = '';

    } else {

        $notes_heading = 'Notes (will be appended to current domain notes)';

    }

    if ($action != "DD") {

        if ($action == "AN") {

            echo $form->showInputTextarea('new_notes', $notes_heading, '', $new_notes, '1', '', '');

        } else {

            echo $form->showInputTextarea('new_notes', $notes_heading, '', $new_notes, '', '', '');
        }

    }

    if ($action == "AD") { ?>

        <?php
        $sql = "SELECT field_name
                FROM domain_fields
                ORDER BY type_id, `name`";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) > 0) { ?>

            <BR><h3>Custom Fields</h3><?php

            $count = 0;

            while ($row = mysqli_fetch_object($result)) {

                $field_array[$count] = $row->field_name;
                $count++;

            }

            foreach ($field_array as $field) {

                $query = "SELECT df.name, df.field_name, df.type_id, df.description
                          FROM domain_fields AS df, custom_field_types AS cft
                          WHERE df.type_id = cft.id
                            AND df.field_name = ?";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->bind_param('s', $field);
                    $q->execute();
                    $q->store_result();
                    $q->bind_result($temp_name, $temp_field_name, $temp_type_id, $temp_description);

                    while ($q->fetch()) {

                        if ($temp_type_id == "1") { // Check Box

                            echo $form->showCheckbox('new_' . $temp_field_name, '1', $temp_name, $temp_description, '', '', '');

                        } elseif ($temp_type_id == "2") { // Text

                            echo $form->showInputText('new_' . $temp_field_name, $temp_name, $temp_description, ${'new_' . $temp_field_name}, '255', '', '', '', '');

                        } elseif ($temp_type_id == "3") { // Text Area

                            echo $form->showInputTextarea('new_' . $temp_field_name, $temp_name, $temp_description, ${'new_' . $temp_field_name}, '', '', '');

                        }

                    }

                    $q->close();

                } else $error->outputSqlError($conn, "ERROR");

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

    }

    if ($action == "AD") {

        echo $form->showSubmitButton('Add Domains', '<BR>', '');

    } elseif ($action == "AN") {

        echo $form->showSubmitButton('Add Notes', '', '');

    } elseif ($action == "FR") {

        echo $form->showSubmitButton('Mark As Renewed', '', '');

    } elseif ($action == "RENEW") {

        echo $form->showSubmitButton('Mark As Renewed', '', '');

    } elseif ($action == "A") {

        echo $form->showSubmitButton('Mark as Active', '', '');

    } elseif ($action == "T") {

        echo $form->showSubmitButton('Mark as Pending Transfer', '', '');

    } elseif ($action == "PRg") {

        echo $form->showSubmitButton('Mark as Pending Registration', '', '');

    } elseif ($action == "PRn") {

        echo $form->showSubmitButton('Mark as Pending Renewal', '', '');

    } elseif ($action == "PO") {

        echo $form->showSubmitButton('Mark as Pending (Other)', '', '');

    } elseif ($action == "E") {

        echo $form->showSubmitButton('Mark as Expired', '', '');

    } elseif ($action == "S") {

        echo $form->showSubmitButton('Mark as Sold', '', '');

    } elseif ($action == "AURNE") {

        echo $form->showSubmitButton('Mark as Auto Renewal', '', '');

    } elseif ($action == "AURND") {

        echo $form->showSubmitButton('Mark as Manual Renewal', '', '');

    } elseif ($action == "PRVE") {

        echo $form->showSubmitButton('Mark as Private WHOIS', '', '');

    } elseif ($action == "PRVD") {

        echo $form->showSubmitButton('Mark as Public WHOIS', '', '');

    } elseif ($action == "CPC") {

        echo $form->showSubmitButton('Change Category', '', '');

    } elseif ($action == "CDNS") {

        echo $form->showSubmitButton('Change DNS Profile', '', '');

    } elseif ($action == "CED") {

        echo $form->showSubmitButton('Change Expiry Date', '', '');

    } elseif ($action == "CIP") {

        echo $form->showSubmitButton('Change IP Address', '', '');

    } elseif ($action == "CRA") {

        echo $form->showSubmitButton('Change Registrar Account', '', '');

    } elseif ($action == "CWH") {

        echo $form->showSubmitButton('Change Web Hosting Provider', '', '');

    } elseif ($action == "UCF") {

        echo $form->showSubmitButton('Update Custom Domain Field', '', '');

    } elseif ($action == "DD") {

        echo $form->showSubmitButton('Delete Domains', '', '');

    } else {

        echo $form->showSubmitButton('Perform Bulk Action', '', '');

    }

}

echo $form->showFormBottom('');
?>

<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
