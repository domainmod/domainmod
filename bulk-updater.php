<?php
/**
 * /bulk-updater.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();
$timestamp_basic = $time->timeBasic();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

$page_title = "Bulk Domain Updater";
$software_section = "bulk-updater";

// Form Variables
$jumpMenu = $_GET['jumpMenu'];
$action = $_REQUEST['action'];
$new_data = $_POST['new_data'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_pcid = $_POST['new_pcid'];
$new_dnsid = $_POST['new_dnsid'];
$new_ipid = $_POST['new_ipid'];
$new_whid = $_POST['new_whid'];
$new_raid = $_POST['new_raid'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];
$new_renewal_years = $_POST['new_renewal_years'];
$new_field_type_id = $_POST['new_field_type_id'];
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
    $new_data = $format->stripSpacing($new_data);

    if ($new_data == "") {

        $_SESSION['result_message'] = "Please enter the list of domains to apply the action to<BR>";

	} else {

		$lines = explode("\r\n", $new_data);
		$invalid_domain_count = 0;
		$invalid_domains_to_display = 5;

        $domain = new DomainMOD\Domain();

        while (list($key, $new_domain) = each($lines)) {
	
			if (!$domain->checkDomainFormat($new_domain)) {
				if ($invalid_domain_count < $invalid_domains_to_display) $temp_result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";
				$invalid_domains = 1;
				$invalid_domain_count++;
			}
	
		}

        if ($new_data == "" || $invalid_domains == 1) {
		
			if ($invalid_domains == 1) {
	
				if ($invalid_domain_count == 1) {

					$_SESSION['result_message'] = "There is " . number_format($invalid_domain_count) . " invalid domain on your list<BR><BR>" . $temp_result_message;

				} else {

					$_SESSION['result_message'] = "There are " . number_format($invalid_domain_count) . " invalid domains on your list<BR><BR>" . $temp_result_message;

					if (($invalid_domain_count-$invalid_domains_to_display) == 1) { 
	
						$_SESSION['result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " other<BR>";
	
					} elseif (($invalid_domain_count-$invalid_domains_to_display) > 1) { 
	
						$_SESSION['result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " others<BR>";
					}

				}
	
			} else {

				$_SESSION['result_message'] = "Please enter the list of domains to apply the action to<BR>";
	
			}
			$submission_failed = 1;
	
		} else {

            $new_data_formatted = "'" . $new_data;
			$new_data_formatted = $new_data_formatted . "'";
			$new_data_formatted = preg_replace("/\r\n/", "','", $new_data_formatted);
			$new_data_formatted = str_replace (" ", "", $new_data_formatted);
			$new_data_formatted = trim($new_data_formatted);
	
			if ($action == "R") { 
			
				$sql = "SELECT domain, expiry_date
						FROM domains
						WHERE domain IN (" . $new_data_formatted . ")";
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				while ($row = mysqli_fetch_object($result)) {
				
					$lines = explode("-", $row->expiry_date);
					$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
					$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];

					if ($new_notes != "") {

						$sql_update = "UPDATE domains
									   SET expiry_date = '" . $new_expiry . "',
									   	   notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
										   update_time = '" . $timestamp . "'
									   WHERE domain = '" . $row->domain . "'";
						
					} else {

						$sql_update = "UPDATE domains
									   SET expiry_date = '" . $new_expiry . "',
									   	   update_time = '" . $timestamp . "'
									   WHERE domain = '" . $row->domain . "'";

					}
					$result_update = mysqli_query($connection, $sql_update);
				
				}

				$_SESSION['result_message'] = "Domains Renewed<BR>";

                $_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "AD") {

                $date = new DomainMOD\Date();

                if (!$date->checkDateFormat($new_expiry_date) || $new_pcid == "" || $new_dnsid == "" || $new_ipid == "" || $new_whid == "" || $new_raid == "" || $new_pcid == "0" || $new_dnsid == "0" || $new_ipid == "0" || $new_whid == "0" || $new_raid == "0") {
	
					if (!$date->checkDateFormat($new_expiry_date)) $_SESSION['result_message'] .= "You have entered an invalid expiry date<BR>";
					if ($new_pcid == "" || $new_pcid == "0") $_SESSION['result_message'] .= "Please choose the new Category<BR>";
					if ($new_dnsid == "" || $new_dnsid == "0") $_SESSION['result_message'] .= "Please choose the new DNS Profile<BR>";
					if ($new_ipid == "" || $new_ipid == "0") $_SESSION['result_message'] .= "Please choose the new IP Address<BR>";
					if ($new_whid == "" || $new_whid == "0") $_SESSION['result_message'] .= "Please choose the new Web Hosting Provider<BR>";
					if ($new_raid == "" || $new_raid == "0") $_SESSION['result_message'] .= "Please choose the new Registrar Account<BR>";
					$submission_failed = 1;
				
				} else {
	
					$sql = "SELECT owner_id, registrar_id
							FROM registrar_accounts
							WHERE id = '" . $new_raid . "'";
					$result = mysqli_query($connection, $sql);
					while ($row = mysqli_fetch_object($result)) {
						$temp_owner_id = $row->owner_id;
						$temp_registrar_id = $row->registrar_id;
					}
		
					$lines = explode("\r\n", $new_data);

                    reset($lines);
			
					// cycle through domains here
					while (list($key, $new_domain) = each($lines)) {
					
						$new_tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
		
						$sql = "SELECT id
								FROM fees
								WHERE registrar_id = '" . $temp_registrar_id . "'
								  AND tld = '" . $new_tld . "'";
						$result = mysqli_query($connection, $sql);
						while ($row = mysqli_fetch_object($result)) {
							$temp_fee_id = $row->id;
						}
		
						if ($temp_fee_id == '0' || $temp_fee_id == "") { $temp_fee_fixed = 0; $temp_fee_id = 0; } else { $temp_fee_fixed = 1; }

                        if ($new_privacy == "1") {

                            $fee_string = "renewal_fee + privacy_fee + misc_fee";

                        } else {

                            $fee_string = "renewal_fee + misc_fee";

                        }

                        $sql = "SELECT id, (" . $fee_string . ") AS total_cost
                        FROM fees
                        WHERE registrar_id = '" . $temp_registrar_id . "'
                          AND tld = '" . $new_tld . "'";
                        $result = mysqli_query($connection, $sql);

                        while ($row = mysqli_fetch_object($result)) { $new_total_cost = $row->total_cost; }

						$sql = "INSERT INTO domains
								(owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id, total_cost, dns_id, ip_id, hosting_id, function, notes, privacy, active, fee_fixed, insert_time) VALUES
								('" . $temp_owner_id . "', '" . $temp_registrar_id . "', '" . $new_raid . "', '" . mysqli_real_escape_string($connection, $new_domain) . "', '" . $new_tld . "', '" . $new_expiry_date . "', '" . $new_pcid . "', '" . $temp_fee_id . "', '" . $new_total_cost . "', '" . $new_dnsid . "', '" . $new_ipid . "', '" . $new_whid . "', '" . mysqli_real_escape_string($connection, $new_function) . "', '" . mysqli_real_escape_string($connection, $new_notes) . "', '" . $new_privacy . "', '" . $new_active . "', '" . $temp_fee_fixed . "', '" . $timestamp . "')";
						$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
						$temp_fee_id = 0;

						$sql = "SELECT id
								FROM domains
								WHERE domain = '" . mysqli_real_escape_string($connection, $new_domain) . "'
								  AND insert_time = '" . $timestamp . "'";
						$result = mysqli_query($connection, $sql);
						while ($row = mysqli_fetch_object($result)) { $temp_domain_id = $row->id; }
			
						$sql = "INSERT INTO domain_field_data
								(domain_id, insert_time) VALUES 
								('" . $temp_domain_id . "', '" . $timestamp . "')";
						$result = mysqli_query($connection, $sql);
			
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

                                $full_field = "new_" . $field;

                                $sql = "UPDATE domain_field_data
                                        SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "'
                                        WHERE domain_id = '" . $temp_domain_id . "'";
                                $result = mysqli_query($connection, $sql);

                            }

                        }

                    // finish cycling through domains here
					}

					$_SESSION['result_message'] = "Domains Added<BR>";

                    $_SESSION['missing_domain_fees'] = $system->checkMissingDomainFees($connection);

                    $_SESSION['result_message'] .= $system->updateSegments($connection);

                    $_SESSION['result_message'] .= $system->updateTlds($connection);

                }
	
			} elseif ($action == "FR") { 
			
				$sql = "SELECT domain, expiry_date
						FROM domains
						WHERE domain IN (" . $new_data_formatted . ")";
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				while ($row = mysqli_fetch_object($result)) {
				
					$lines = explode("-", $row->expiry_date);
					$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
					$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];
					
					if ($new_renewal_years == "1") {
						$renewal_years_string = $new_renewal_years . " Year";
					} else {
						$renewal_years_string = $new_renewal_years . " Years";
					}

					if ($new_notes != "") {

						$new_notes_renewal = $timestamp_basic . " - Domain Renewed For " . $renewal_years_string;

						$sql_update = "UPDATE domains
									   SET expiry_date = '" . $new_expiry . "',
									   	   notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', '" . mysqli_real_escape_string($connection, $new_notes_renewal) . "\r\n\r\n', notes),
										   active = '1',
										   update_time = '" . $timestamp . "'
									   WHERE domain = '" . $row->domain . "'";

					} else {

						$new_notes_renewal = $timestamp_basic . " - Domain Renewed For " . $renewal_years_string;

						$sql_update = "UPDATE domains
									   SET expiry_date = '" . $new_expiry . "',
									   	   notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes_renewal) . "\r\n\r\n', notes),
										   active = '1',
										   update_time = '" . $timestamp . "'
									   WHERE domain = '" . $row->domain . "'";

					}
					$result_update = mysqli_query($connection, $sql_update);
				
				}

				$_SESSION['result_message'] .= $system->updateSegments($connection);

				$_SESSION['result_message'] = "Domains Fully Renewed<BR>";
				
			} elseif ($action == "CPC") { 
			
				if ($new_pcid == "" || $new_pcid == 0) {
	
					$_SESSION['result_message'] = "Please choose the new Category<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET cat_id = '" . $new_pcid . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					} else {

						$sql = "UPDATE domains
								SET cat_id = '" . $new_pcid . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";

					}
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
					
					$_SESSION['result_message'] = "Category Changed<BR>";
	
				}
	
			} elseif ($action == "CDNS") { 
	
				if ($new_dnsid == "" || $new_dnsid == 0) {
	
					$_SESSION['result_message'] = "Please choose the new DNS Profile<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET dns_id = '" . $new_dnsid . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					} else {

						$sql = "UPDATE domains
								SET dns_id = '" . $new_dnsid . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";

					}
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
					
					$_SESSION['result_message'] = "DNS Profile Changed<BR>";
				}
	
			} elseif ($action == "CIP") {
	
				if ($new_ipid == "" || $new_ipid == 0) {
	
					$_SESSION['result_message'] = "Please choose the new IP Address<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET ip_id = '" . $new_ipid . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					} else {

						$sql = "UPDATE domains
								SET ip_id = '" . $new_ipid . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";

					}
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
					$_SESSION['result_message'] = "IP Address Changed<BR>";
	
				}
	
			} elseif ($action == "AN") {
				
				if ($new_notes == "") {
	
					$_SESSION['result_message'] = "Please enter the new Note<BR>";
					$submission_failed = 1;
	
				} else {

					$sql_update = "UPDATE domains
								   SET notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								   	   update_time = '" . $timestamp . "'
								   WHERE domain IN (" . $new_data_formatted . ")";
					$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
					
					$_SESSION['result_message'] = "Note Added<BR>";
	
				}

			} elseif ($action == "CRA") { 
	
				if ($new_raid == "" || $new_raid == 0) {
	
					$_SESSION['result_message'] = "Please choose the new Registrar Account<BR>";
					$submission_failed = 1;
	
				} else {
	
					$sql = "SELECT ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS r_name, o.id AS o_id, o.name AS o_name
							FROM registrar_accounts AS ra, registrars AS r, owners AS o
							WHERE ra.registrar_id = r.id
							  AND ra.owner_id = o.id
							  AND ra.id = '" . $new_raid . "'
							GROUP BY r.name, o.name, ra.username
							ORDER BY r.name asc, o.name asc, ra.username asc";
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
			
					while ($row = mysqli_fetch_object($result)) {
						$new_owner_id = $row->o_id;
						$new_registrar_id = $row->r_id;
						$new_registrar_account_id = $row->ra_id;
						$new_owner_name = $row->o_name;
						$new_registrar_name = $row->r_name;
						$new_username = $row->username;
					}

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET owner_id = '" . $new_owner_id . "', 
									registrar_id = '" . $new_registrar_id . "', 
									account_id = '" . $new_registrar_account_id . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					} else {

						$sql = "UPDATE domains
								SET owner_id = '" . $new_owner_id . "', 
									registrar_id = '" . $new_registrar_id . "', 
									account_id = '" . $new_registrar_account_id . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					}
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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

                    $_SESSION['result_message'] = "Registrar Account Changed<BR>";

                    $_SESSION['missing_domain_fees'] = $system->checkMissingDomainFees($connection);

                }

            } elseif ($action == "CWH") {

                if ($new_whid == "" || $new_whid == 0) {

                    $_SESSION['result_message'] = "Please choose the new Web Hosting Provider<BR>";
                    $submission_failed = 1;

                } else {

                    if ($new_notes != "") {

                        $sql = "UPDATE domains
								SET hosting_id = '" . $new_whid . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";

                    } else {

                        $sql = "UPDATE domains
								SET hosting_id = '" . $new_whid . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";

                    }
                    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

                    $_SESSION['result_message'] = "Web Hosting Provider Changed<BR>";

                }

            } elseif ($action == "DD") {

                $sql = "SELECT id
						FROM domains
						WHERE domain in (" . $new_data_formatted . ")";
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

                $_SESSION['result_message'] = "Domains (and associated SSL Certificates) Deleted<BR>";

                $_SESSION['result_message'] .= $system->updateSegments($connection);

            } elseif ($action == "E") {

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '0',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '0',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";

				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as expired<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "S") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '10',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '10',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";

				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as sold<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "A") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '1',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '1',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as active<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "T") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '2',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '2',
								update_time = '" . $timestamp . "'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as 'In Transfer'<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "PRg") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '5',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '5',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as 'Pending (Registration)'<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "PRn") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '3',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '3',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as 'Pending (Renewal)'<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "PO") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '4',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET active = '4',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				$_SESSION['result_message'] = "Domains marked as 'Pending (Other)'<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "PRVE") { 

				if ($new_notes != "") {

                    $sql = "UPDATE domains
							SET privacy = '1',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";

                } else {

					$sql = "UPDATE domains
							SET privacy = '1',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";

                }
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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

                $_SESSION['result_message'] = "Domains marked as 'Private WHOIS'<BR>";

				$_SESSION['result_message'] .= $system->updateSegments($connection);
	
			} elseif ($action == "PRVD") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET privacy = '0',
								notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";
					
				} else {

					$sql = "UPDATE domains
							SET privacy = '0',
								update_time = '" . $timestamp . "'
							WHERE domain IN (" . $new_data_formatted . ")";

				}
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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

                $_SESSION['result_message'] = "Domains marked as 'Public WHOIS'<BR>";
	
			} elseif ($action == "CED") { 
	
				if (!$date->checkDateFormat($new_expiry_date)) {
	
					$_SESSION['result_message'] = "The expiry date you entered is invalid<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {
	
						$sql = "UPDATE domains
								SET expiry_date = '" . $new_expiry_date . "',
									notes = CONCAT('" . mysqli_real_escape_string($connection, $new_notes) . "\r\n\r\n', notes),
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
						
					} else {
	
						$sql = "UPDATE domains
								SET expiry_date = '" . $new_expiry_date . "',
									update_time = '" . $timestamp . "'
								WHERE domain IN (" . $new_data_formatted . ")";
	
					}
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
					
					$_SESSION['result_message'] = "Expiry Date Updated<BR>";

				}

			} elseif ($action == "UCF1" || $action == "UCF2" || $action == "UCF3") { 
			
				$sql = "SELECT id
						FROM domains
						WHERE domain in (" . $new_data_formatted . ")";
				$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
				
				while ($row = mysqli_fetch_object($result)) {
					
					$domain_id_list .= "'" . $row->id . "', ";
					
				}
				
				$domain_id_list_formatted = substr($domain_id_list, 0, -2);

				$sql = "SELECT name, field_name
						FROM domain_fields
						WHERE id = '" . $field_id . "'";
				$result = mysqli_query($connection, $sql);

				while ($row = mysqli_fetch_object($result)) {

					$temp_name = $row->name;
					$temp_field_name = $row->field_name;

				}

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
							WHERE id in (" . $domain_id_list_formatted . ")";
					$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
					
				}
				
				$_SESSION['result_message'] = "Custom Field <font class=\"highlight\">" . $name_array[0] . "</font> Updated<BR>";

			}
	
			$done = "1";
			$new_data_unformatted = strtolower(preg_replace("/\r\n/", ", ", $new_data));
	
		}

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
<?php include(DIR_INC . "system/jumpmenu.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php if ($done == "1") { ?>

	<?php if ($submission_failed != "1") { ?>

        <?php if ($action == "AD") { ?>
            <BR><strong>The following domains were added:</strong><BR>
        <?php } elseif ($action == "R") { ?>
            <BR><strong>The following domains were renewed for <?php echo $new_renewal_years; ?> year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
        <?php } elseif ($action == "FR") { ?>
            <BR><strong>The following domains were fully renewed for <?php echo $new_renewal_years; ?> year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
        <?php } elseif ($action == "E") { ?>
            <BR><strong>The following domains were marked as expired:</strong><BR>
        <?php } elseif ($action == "S") { ?>
            <BR><strong>The following domains were marked as sold:</strong><BR>
        <?php } elseif ($action == "A") { ?>
            <BR><strong>The following domains were marked as active:</strong><BR>
        <?php } elseif ($action == "T") { ?>
            <BR><strong>The following domains were marked as 'In Transfer':</strong><BR>
        <?php } elseif ($action == "PRg") { ?>
            <BR><strong>The following domains were marked as 'Pending (Registration)':</strong><BR>
        <?php } elseif ($action == "PRn") { ?>
            <BR><strong>The following domains were marked as 'Pending (Renewal)':</strong><BR>
        <?php } elseif ($action == "PO") { ?>
            <BR><strong>The following domains were marked as 'Pending (Other)':</strong><BR>
        <?php } elseif ($action == "PRVE") { ?>
            <BR><strong>The following domains were marked as 'Private WHOIS':</strong><BR>
        <?php } elseif ($action == "PRVD") { ?>
            <BR><strong>The following domains were marked as 'Public WHOIS':</strong><BR>
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
        <?php } elseif ($action == "UCF1" || $action == "UCF2" || $action == "UCF3") { ?>
            <BR><strong>The following domains had their Custom Domain Field updated:</strong><BR>
        <?php } ?>

		<BR><?php echo $new_data_unformatted; ?><BR><BR><BR>
	<?php } ?>

<?php } ?>
Instead of having to waste time editing domains one-by-one, you can use the below form to update various data for multiple domains all at once.<BR><BR>
<form name="bulk_updater_form" method="post">
  <select name="jumpMenu" id="jumpMenu" onChange="MM_jumpMenu('parent',this,0)">
    <option value="bulk-updater.php"<?php if ($action == "") { echo " selected"; } ?>>Choose Action</option>
    <option value="bulk-updater.php?action=AD"<?php if ($action == "AD") { echo " selected"; } ?>>Add Domains</option>
    <option value="bulk-updater.php?action=AN"<?php if ($action == "AN") { echo " selected"; } ?>>Add A Note</option>
    <option value="bulk-updater.php?action=FR"<?php if ($action == "FR") { echo " selected"; } ?>>Renew Domains (Update Expiry Date, Mark Active, Add Note)</option>
    <option value="bulk-updater.php?action=R"<?php if ($action == "R") { echo " selected"; } ?>>Renew Domains (Update Expiry Date Only)</option>
    <option value="bulk-updater.php?action=A"<?php if ($action == "A") { echo " selected"; } ?>>Mark as 'Active'</option>
    <option value="bulk-updater.php?action=T"<?php if ($action == "T") { echo " selected"; } ?>>Mark as 'In Transfer'</option>
	<option value="bulk-updater.php?action=PRg"<?php if ($action == "PRg") { echo " selected"; } ?>>Mark as 'Pending (Registration)'</option>
	<option value="bulk-updater.php?action=PRn"<?php if ($action == "PRn") { echo " selected"; } ?>>Mark as 'Pending (Renewal)'</option>
	<option value="bulk-updater.php?action=PO"<?php if ($action == "PO") { echo " selected"; } ?>>Mark as 'Pending (Other)'</option>
    <option value="bulk-updater.php?action=E"<?php if ($action == "E") { echo " selected"; } ?>>Mark as 'Expired'</option>
    <option value="bulk-updater.php?action=S"<?php if ($action == "S") { echo " selected"; } ?>>Mark as 'Sold'</option>
    <option value="bulk-updater.php?action=PRVE"<?php if ($action == "PRVE") { echo " selected"; } ?>>Mark as Private WHOIS</option>
    <option value="bulk-updater.php?action=PRVD"<?php if ($action == "PRVD") { echo " selected"; } ?>>Mark as Public WHOIS</option>
    <option value="bulk-updater.php?action=CPC"<?php if ($action == "CPC") { echo " selected"; } ?>>Change Category</option>
    <option value="bulk-updater.php?action=CDNS"<?php if ($action == "CDNS") { echo " selected"; } ?>>Change DNS Profile</option>
    <option value="bulk-updater.php?action=CED"<?php if ($action == "CED") { echo " selected"; } ?>>Change Expiry Date</option>
    <option value="bulk-updater.php?action=CIP"<?php if ($action == "CIP") { echo " selected"; } ?>>Change IP Address</option>
    <option value="bulk-updater.php?action=CRA"<?php if ($action == "CRA") { echo " selected"; } ?>>Change Registrar Account</option>
    <option value="bulk-updater.php?action=CWH"<?php if ($action == "CWH") { echo " selected"; } ?>>Change Web Hosting Provider</option>
    <option value="bulk-updater.php?action=UCF"<?php if ($action == "UCF" || $action == "UCF1" || $action == "UCF2" || $action == "UCF3") { echo " selected"; } ?>>Update Custom Domain Field</option>
    <option value="bulk-updater.php?action=DD"<?php if ($action == "DD") { echo " selected"; } ?>>Delete Domains</option>
  </select>

<?php if ($action == "UCF" || $action == "UCF1" || $action == "UCF2" || $action == "UCF3") { ?>
<BR><BR>
<select name="jumpMenu" id="jumpMenu2" onChange="MM_jumpMenu('parent',this,0)">
    <option value="bulk-updater.php?action=UCF"<?php if ($action == "UCF") { echo " selected"; } ?>>Choose the Custom Field to Edit</option>
    <?php
    $sql = "SELECT df.id, df.name, df.type_id, cft.name AS type
            FROM domain_fields AS df, custom_field_types AS cft
            WHERE df.type_id = cft.id
            ORDER BY df.name";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    while ($row = mysqli_fetch_object($result)) { ?>
    
        <option value="bulk-updater.php?action=UCF<?php echo $row->type_id; ?>&field_id=<?php echo $row->id; ?>"<?php if ($row->id == $field_id) echo " selected"; ?>><?php echo $row->name; ?> (<?php echo $row->type; ?>)</option><?php
    
    }
    ?>
</select>
<?php } ?>

<?php if ($action != "" && $action != "UCF") { ?>
        <BR><BR>
		<?php if ($action == "AD") { ?>
	        <strong>Domains to add (one per line)</strong><a title="Required Field"><font class="default_highlight">*</font></a>
        <?php } else { ?>
	        <strong>Domains to update (one per line)</strong><a title="Required Field"><font class="default_highlight">*</font></a>
        <?php } ?>
        <BR><BR>
        <textarea name="new_data" cols="60" rows="5"><?php echo $new_data; ?></textarea>
        <BR><BR>
<?php } ?>

<?php if ($action == "R" || $action == "FR") { ?>
    <strong>Renew For</strong> 
    <select name="new_renewal_years">
      <option value="1"<?php if ($new_renewal_years == "1") { echo " selected"; } ?>>1 Year</option>
      <option value="2"<?php if ($new_renewal_years == "2") { echo " selected"; } ?>>2 Years</option>
      <option value="3"<?php if ($new_renewal_years == "3") { echo " selected"; } ?>>3 Years</option>
      <option value="4"<?php if ($new_renewal_years == "4") { echo " selected"; } ?>>4 Years</option>
      <option value="5"<?php if ($new_renewal_years == "5") { echo " selected"; } ?>>5 Years</option>
      <option value="6"<?php if ($new_renewal_years == "6") { echo " selected"; } ?>>6 Years</option>
      <option value="7"<?php if ($new_renewal_years == "7") { echo " selected"; } ?>>7 Years</option>
      <option value="8"<?php if ($new_renewal_years == "8") { echo " selected"; } ?>>8 Years</option>
      <option value="9"<?php if ($new_renewal_years == "9") { echo " selected"; } ?>>9 Years</option>
      <option value="10"<?php if ($new_renewal_years == "10") { echo " selected"; } ?>>10 Years</option>
    </select>
    <BR><BR>
<?php } elseif ($action == "AD") { ?>
    <strong>Function (255)</strong><BR><BR>
    <input name="new_function" type="text" size="50" maxlength="255" value="<?php echo $new_function; ?>">
    <BR><BR>
    <strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $timestamp_basic_plus_one_year; } ?>">
    <BR><BR>
    <strong>Registrar Account</strong><BR><BR>
    <?php
    $sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                    FROM registrar_accounts AS ra, owners AS o, registrars AS r
                    WHERE ra.owner_id = o.id
                      AND ra.registrar_id = r.id
                    ORDER BY r_name, o_name, ra.username";
    $result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
    echo "<select name=\"new_raid\">";
    while ($row_account = mysqli_fetch_object($result_account)) { ?>
    
    	<option value="<?php echo $row_account->id; ?>"<?php if ($row_account->id == $_SESSION['default_registrar_account']) echo " selected";?>><?php echo $row_account->r_name; ?>, <?php echo $row_account->o_name; ?> (<?php echo $row_account->username; ?>)</option><?php

    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>DNS Profile</strong><BR><BR>
    <?php
    $sql_dns = "SELECT id, name
				FROM dns
				ORDER BY name";
    $result_dns = mysqli_query($connection, $sql_dns) or $error->outputOldSqlError($connection);
    echo "<select name=\"new_dnsid\">";
    while ($row_dns = mysqli_fetch_object($result_dns)) { ?>
    
	    <option value="<?php echo $row_dns->id; ?>"<?php if ($row_dns->id == $_SESSION['default_dns']) echo " selected";?>><?php echo $row_dns->name; ?></option><?php

    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>IP Address</strong><BR><BR>
    <?php
    $sql_ip = "SELECT id, name, ip
			   FROM ip_addresses
			   ORDER BY name, ip";
    $result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
    echo "<select name=\"new_ipid\">";
    while ($row_ip = mysqli_fetch_object($result_ip)) { ?>

		<option value="<?php echo $row_ip->id; ?>"<?php if ($row_ip->id == $_SESSION['default_ip_address_domains']) echo " selected";?>><?php echo $row_ip->name; ?> (<?php echo $row_ip->ip; ?>)</option><?php

    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Web Hosting Provider</strong><BR><BR>
    <?php
    $sql_host = "SELECT id, name
				 FROM hosting
				 ORDER BY name";

    $result_host = mysqli_query($connection, $sql_host) or $error->outputOldSqlError($connection);
    echo "<select name=\"new_whid\">";
    while ($row_host = mysqli_fetch_object($result_host)) { ?>

		<option value="<?php echo $row_host->id; ?>"<?php if ($row_host->id == $_SESSION['default_host']) echo " selected";?>><?php echo $row_host->name; ?></option><?php

    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Category</strong><BR><BR>
    <?php
    $sql_cat = "SELECT id, name
				FROM categories
				ORDER BY name";

    $result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
    echo "<select name=\"new_pcid\">";
    while ($row_cat = mysqli_fetch_object($result_cat)) { ?>
    
		<option value="<?php echo $row_cat->id; ?>"<?php if ($row_cat->id == $_SESSION['default_category_domains']) echo " selected";?>><?php echo $row_cat->name; ?></option><?php
    
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Domain Status</strong><BR><BR>
    <?php
    echo "<select name=\"new_active\">";
    echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
    echo "<option value=\"2\""; if ($new_active == "2") echo " selected"; echo ">In Transfer</option>";
    echo "<option value=\"5\""; if ($new_active == "5") echo " selected"; echo ">Pending (Registration)</option>";
    echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
    echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
    echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
    echo "<option value=\"10\""; if ($new_active == "10") echo " selected"; echo ">Sold</option>";
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Privacy Enabled?</strong><BR><BR>
    <?php
    echo "<select name=\"new_privacy\">";
    echo "<option value=\"0\""; if ($new_privacy == "0") echo " selected"; echo ">No</option>";
    echo "<option value=\"1\""; if ($new_privacy == "1") echo " selected"; echo ">Yes</option>";
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CPC") { ?>

	<?php
    $sql_cat = "SELECT id, name
				FROM categories
				ORDER BY name";
    $result_cat = mysqli_query($connection, $sql_cat);
    echo "<strong>New Category</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
	echo "<select name=\"new_pcid\">";
    echo "<option value=\"\""; if ($new_pcid == "") echo " selected"; echo ">"; echo "$choose_text Category</option>";
	while ($row_cat = mysqli_fetch_object($result_cat)) { 
    echo "<option value=\"$row_cat->id\""; if ($row_cat->id == $_SESSION['default_category_domains']) echo " selected"; echo ">"; echo "$row_cat->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CDNS") { ?>

	<?php
    $sql_dns = "SELECT id, name
				FROM dns
				ORDER BY name asc";
    $result_dns = mysqli_query($connection, $sql_dns);
    echo "<strong>New DNS Profile</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_dnsid\">";
    echo "<option value=\"\""; if ($new_dnsid == "") echo " selected"; echo ">"; echo "$choose_text DNS Profile</option>";
    while ($row_dns = mysqli_fetch_object($result_dns)) { 
    echo "<option value=\"$row_dns->id\""; if ($row_dns->id == $_SESSION['default_dns']) echo " selected"; echo ">"; echo "$row_dns->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CIP") { ?>

	<?php
    $sql_ip = "SELECT id, name, ip
			   FROM ip_addresses
			   ORDER BY name asc, ip asc";
    $result_ip = mysqli_query($connection, $sql_ip);
    echo "<strong>New IP Address</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_ipid\">";
    echo "<option value=\"\""; if ($new_ipid == "") echo " selected"; echo ">"; echo "$choose_text IP Address</option>";
    while ($row_ip = mysqli_fetch_object($result_ip)) { 
    echo "<option value=\"$row_ip->id\""; if ($row_ip->id == $_SESSION['default_ip_address_domains']) echo " selected"; echo ">"; echo "$row_ip->name ($row_ip->ip)</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CRA") { ?>
	<?php
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
    echo "<strong>New Registrar Account</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_raid\">";
    echo "<option value=\"\""; if ($new_raid == "") echo " selected"; echo ">"; echo "$choose_text Registrar Account</option>";
	while ($row_account = mysqli_fetch_object($result_account)) { 
	    echo "<option value=\"$row_account->ra_id\""; if ($row_account->ra_id == $_SESSION['default_registrar_account']) echo " selected"; echo ">"; echo "$row_account->r_name, $row_account->o_name ($row_account->username)</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CWH") { ?>
    <?php
    $sql_host = "SELECT id, name
				 FROM hosting
				 ORDER BY name asc";
    $result_host = mysqli_query($connection, $sql_host);
    echo "<strong>New Web Hosting Provider</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_whid\">";
    echo "<option value=\"\""; if ($new_whid == "") echo " selected"; echo ">"; echo "$choose_text Web Hosting Provider</option>";
    while ($row_host = mysqli_fetch_object($result_host)) {
        echo "<option value=\"$row_host->id\""; if ($row_host->id == $_SESSION['default_host']) echo " selected"; echo ">"; echo "$row_host->name</option>";
    }
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "DD") { ?>

<?php } elseif ($action == "AN") { ?>

<?php } elseif ($action == "CED") { ?>
    <strong>New Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <input name="new_expiry_date" type="text" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $timestamp_basic; } ?>" size="10" maxlength="10">
    <BR><BR>

<?php } elseif ($action == "UCF1") {

	$sql = "SELECT df.name, df.field_name, df.type_id, df.description
			FROM domain_fields AS df, custom_field_types AS cft
			WHERE df.type_id = cft.id
			  AND df.id = '" . $field_id . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { ?>
	
        <strong><?php echo $row->name; ?></strong>
        <input type="checkbox" name="new_<?php echo $row->field_name; ?>" value="1"<?php if (${'new_' . $field} == "1") echo " checked"; ?>><BR><?php
        
        if ($row->description != "") {
            
            echo $row->description . "<BR><BR>";
            
        } else {
            
            echo "<BR>";
            
        }
	
	}

} elseif ($action == "UCF2") {

	$sql = "SELECT df.name, df.field_name, df.type_id, df.description
			FROM domain_fields AS df, custom_field_types AS cft
			WHERE df.type_id = cft.id
			  AND df.id = '" . $field_id . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { ?>

		<strong><?php echo $row->name; ?> (255)</strong><?php

		if ($row->description != "") {
			
			echo "<BR>" . $row->description . "<BR><BR>";
			
		} else {
			
			echo "<BR><BR>";
			
		} ?>
		<input type="text" name="new_<?php echo $row->field_name; ?>" size="50" maxlength="255" value="<?php echo ${'new_' . $row->field_name}; ?>"><BR><BR><?php

	}

} elseif ($action == "UCF3") {

	$sql = "SELECT df.name, df.field_name, df.type_id, df.description
			FROM domain_fields AS df, custom_field_types AS cft
			WHERE df.type_id = cft.id
			  AND df.id = '" . $field_id . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { ?>

		<strong><?php echo $row->name; ?></strong><?php

		if ($row->description != "") {
			
			echo "<BR>" . $row->description . "<BR><BR>";
			
		} else {
			
			echo "<BR><BR>";
			
		} ?>
		<textarea name="new_<?php echo $row->field_name; ?>" cols="60" rows="5"><?php echo ${'new_' . $row->field_name}; ?></textarea><BR><BR><?php

	}

} ?>
<?php if ($action != "" && $action != "UCF") { ?>

    <?php if ($action == "AN") { ?>
		<strong>Notes</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <?php } elseif ($action == "AD") { ?>
		<strong>Notes</strong><BR><BR>
    <?php } elseif ($action == "UCF" || $action == "DD") { ?>
    <?php } else { ?>
		<strong>Notes (will be appended to current domain notes)</strong><BR><BR>
    <?php } ?>

    <?php if ($action == "DD") { ?>
    <?php } else { ?>
        <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
        <BR><BR>
    <?php } ?>

    <?php if ($action == "AD") { ?>

        <?php
        $sql = "SELECT field_name
                FROM domain_fields
                ORDER BY type_id, name";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) > 0) { ?>

            <BR><BR><font class="subheadline">Custom Fields</font><BR><BR><?php

            $count = 0;

            while ($row = mysqli_fetch_object($result)) {

                $field_array[$count] = $row->field_name;
                $count++;

            }

            foreach($field_array as $field) {

                $sql = "SELECT df.name, df.field_name, df.type_id, df.description
                        FROM domain_fields AS df, custom_field_types AS cft
                        WHERE df.type_id = cft.id
                          AND df.field_name = '" . $field . "'";
                $result = mysqli_query($connection, $sql);

                while ($row = mysqli_fetch_object($result)) {

                    if ($row->type_id == "1") { // Check Box ?>

                        <input type="checkbox" name="new_<?php echo $row->field_name; ?>" value="1"<?php if (${'new_' . $field} == "1") echo " checked"; ?>>
                        &nbsp;<strong><?php echo $row->name; ?></strong><BR><?php

                        if ($row->description != "") {

                            echo $row->description . "<BR><BR>";

                        } else {

                            echo "<BR>";

                        }

                    } elseif ($row->type_id == "2") { // Text ?>

                        <strong><?php echo $row->name; ?> (255)</strong><?php

                        if ($row->description != "") {

                            echo "<BR>" . $row->description . "<BR><BR>";

                        } else {

                            echo "<BR><BR>";

                        } ?>
                        <input type="text" name="new_<?php echo $row->field_name; ?>" size="50" maxlength="255" value="<?php echo ${'new_' . $row->field_name}; ?>"><BR><BR><?php

                    } elseif ($row->type_id == "3") { // Text Area ?>

                        <strong><?php echo $row->name; ?></strong><?php

                        if ($row->description != "") {

                            echo "<BR>" . $row->description . "<BR><BR>";

                        } else {

                            echo "<BR><BR>";

                        } ?>
                        <textarea name="new_<?php echo $row->field_name; ?>" cols="60" rows="5"><?php echo ${'new_' . $row->field_name}; ?></textarea><BR><BR><?php

                    }

                }

            }

        }

    } ?>

    <input type="hidden" name="action" value="<?php echo $action; ?>">
    <?php if ($action == "CDNS") { ?>
    <input type="hidden" name="dnsid" value="<?php echo $new_dnsid; ?>">
    <?php } ?>
    <?php if ($action == "CIP") { ?>
    <input type="hidden" name="ipid" value="<?php echo $new_ipid; ?>">
    <?php } ?>
    <?php if ($action == "CRA") { ?>
    <input type="hidden" name="raid" value="<?php echo $new_raid; ?>">
    <?php } ?>
    <?php if ($action == "CWH") { ?>
    <input type="hidden" name="whid" value="<?php echo $new_whid; ?>">
    <?php } ?>
    <input type="submit" name="button" value="Perform Bulk Action &raquo;">
<?php } ?>
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
