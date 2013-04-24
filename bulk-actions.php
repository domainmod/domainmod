<?php
// /bulk-actions.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<?php
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
include("_includes/timestamps/current-timestamp-basic.inc.php");
include("_includes/timestamps/current-timestamp.inc.php");
include("_includes/timestamps/current-timestamp-basic-plus-one-year.inc.php");

$page_title = "Bulk Actions";
$software_section == "bulkactions";

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

$choose_text = "Click here to choose the new";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$temp_input_string = $new_data;
	include("_includes/system/regex-bulk-form-strip-whitespace.inc.php");
	$new_data = $temp_output_string;

	if ($new_data == "") {

		$_SESSION['session_result_message'] = "Please enter the list of domains to apply the action to<BR>";

	} else {

		$lines = explode("\r\n", $new_data);
		$invalid_domain_count = 0;
		$invalid_domains_to_display = 5;
		
		include("_includes/system/functions/check-domain-format.inc.php");
	
		while (list($key, $new_domain) = each($lines)) {
	
			if (!CheckDomainFormat($new_domain)) {
				if ($invalid_domain_count < $invalid_domains_to_display) $temp_result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";
				$invalid_domains = 1;
				$invalid_domain_count++;
			}
	
		}
		
		if ($new_data == "" || $invalid_domains == 1) { 
		
			if ($invalid_domains == 1) {
	
				if ($invalid_domain_count == 1) {

					$_SESSION['session_result_message'] = "There is " . number_format($invalid_domain_count) . " invalid domain on your list<BR><BR>" . $temp_result_message;

				} else {

					$_SESSION['session_result_message'] = "There are " . number_format($invalid_domain_count) . " invalid domains on your list<BR><BR>" . $temp_result_message;

					if (($invalid_domain_count-$invalid_domains_to_display) == 1) { 
	
						$_SESSION['session_result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " other<BR>";
	
					} elseif (($invalid_domain_count-$invalid_domains_to_display) > 1) { 
	
						$_SESSION['session_result_message'] .= "<BR>Plus " . number_format($invalid_domain_count-$invalid_domains_to_display) . " others<BR>";
					}

				}
	
			} else {

				$_SESSION['session_result_message'] = "Please enter the list of domains to apply the action to<BR>";
	
			}
			$submission_failed = 1;
	
		} else {
		
			$lines = explode("\r\n", $new_data);
			$number_of_domains = count($lines);
			
			include("_includes/system/functions/check-domain-format.inc.php");
	
			while (list($key, $new_domain) = each($lines)) {
	
				if (!CheckDomainFormat($new_domain)) {
					echo "invalid domain $key"; exit;
				}
	
			}

			$new_data_formatted = "'" . $new_data;
			$new_data_formatted = $new_data_formatted . "'";
			$new_data_formatted = preg_replace("/\r\n/", "','", $new_data_formatted);
			$new_data_formatted = str_replace (" ", "", $new_data_formatted);
			$new_data_formatted = trim($new_data_formatted);
	
			if ($action == "R") { 
			
				$sql = "SELECT domain, expiry_date
						FROM domains
						WHERE domain IN ($new_data_formatted)";
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				while ($row = mysql_fetch_object($result)) {
				
					$lines = explode("-", $row->expiry_date);
					$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
					$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];

					if ($new_notes != "") {

						$sql2 = "UPDATE domains
								 SET expiry_date = '$new_expiry',
									 notes = CONCAT('$new_notes\r\n\r\n', notes),
									 update_time = '$current_timestamp'
								 WHERE domain = '$row->domain'";
						
					} else {

						$sql2 = "UPDATE domains
								 SET expiry_date = '$new_expiry',
									 update_time = '$current_timestamp'
								 WHERE domain = '$row->domain'";

					}
					$result2 = mysql_query($sql2,$connection);
				
				}

				$_SESSION['session_result_message'] = "Domains Renewed<BR>";

				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "AD") { 
			
				include("_includes/system/functions/check-date-format.inc.php");
	
				if (!CheckDateFormat($new_expiry_date) || $new_pcid == "" || $new_dnsid == "" || $new_ipid == "" || $new_whid == "" || $new_raid == "" || $new_pcid == "0" || $new_dnsid == "0" || $new_ipid == "0" || $new_whid == "0" || $new_raid == "0") {
	
					if (!CheckDateFormat($new_expiry_date)) $_SESSION['session_result_message'] .= "You have entered an invalid expiry date<BR>";
					if ($new_pcid == "" || $new_pcid == "0") $_SESSION['session_result_message'] .= "Please choose the new Category<BR>";
					if ($new_dnsid == "" || $new_dnsid == "0") $_SESSION['session_result_message'] .= "Please choose the new DNS Profile<BR>";
					if ($new_ipid == "" || $new_ipid == "0") $_SESSION['session_result_message'] .= "Please choose the new IP Address<BR>";
					if ($new_whid == "" || $new_whid == "0") $_SESSION['session_result_message'] .= "Please choose the new Web Hosting Provider<BR>";
					if ($new_raid == "" || $new_raid == "0") $_SESSION['session_result_message'] .= "Please choose the new Registrar Account<BR>";
					$submission_failed = 1;
				
				} else {
	
					$sql = "SELECT owner_id, registrar_id
							FROM registrar_accounts
							WHERE id = '$new_raid'";
					$result = mysql_query($sql,$connection);
					while ($row = mysql_fetch_object($result)) {
						$temp_owner_id = $row->owner_id;
						$temp_registrar_id = $row->registrar_id;
					}
		
					$lines = explode("\r\n", $new_data);
					$number_of_domains = count($lines);
			
					reset($lines);
			
					// cycle through domains here
					while (list($key, $new_domain) = each($lines)) {
					
						$new_tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
		
						$sql = "SELECT id
								FROM fees
								WHERE registrar_id = '$temp_registrar_id'
								  AND tld = '$new_tld'";
						$result = mysql_query($sql,$connection);
						while ($row = mysql_fetch_object($result)) {
							$temp_fee_id = $row->id;
						}
		
						if ($temp_fee_id == '0' || $temp_fee_id == "") { $temp_fee_fixed = 0; $temp_fee_id = 0; } else { $temp_fee_fixed = 1; }
			
						$sql = "INSERT INTO domains 
								(owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, fee_id, dns_id, ip_id, hosting_id, function, notes, privacy, active, fee_fixed, insert_time) VALUES 
								('$temp_owner_id', '$temp_registrar_id', '$new_raid',  '$new_domain', '$new_tld', '$new_expiry_date', '$new_pcid', '$temp_fee_id', '$new_dnsid', '$new_ipid', '$new_whid', '$new_function', '$new_notes', '$new_privacy', '$new_active', '$temp_fee_fixed', '$current_timestamp')";
						$result = mysql_query($sql,$connection) or die(mysql_error());
						$temp_fee_id = 0;
		
					// finish cycling through domains here
					}

					$_SESSION['session_result_message'] = "Domains Added<BR>";

					include("_includes/system/update-domain-fees.inc.php");
					include("_includes/system/update-segments.inc.php");
					include("_includes/system/update-tlds.inc.php");

				}
	
			} elseif ($action == "FR") { 
			
				$sql = "SELECT domain, expiry_date
						FROM domains
						WHERE domain IN ($new_data_formatted)";
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				while ($row = mysql_fetch_object($result)) {
				
					$lines = explode("-", $row->expiry_date);
					$old_expiry = $lines[0] . "-" . $lines[1] . "-" . $lines[2];
					$new_expiry = $lines[0]+$new_renewal_years . "-" . $lines[1] . "-" . $lines[2];
					
					if ($new_renewal_years == "1") {
						$renewal_years_string = "$new_renewal_years Year";
					} else {
						$renewal_years_string = "$new_renewal_years Years";
					}

					if ($new_notes != "") {

						$new_notes_renewal = "$current_timestamp_basic - Domain Renewed For $renewal_years_string";

						$sql2 = "UPDATE domains
								 SET expiry_date = '$new_expiry',
									 notes = CONCAT('$new_notes\r\n\r\n', '$new_notes_renewal\r\n\r\n', notes),
									 active = '1',
									 update_time = '$current_timestamp'
								 WHERE domain = '$row->domain'";
						
					} else {

						$new_notes_renewal = "$current_timestamp_basic - Domain Renewed For $renewal_years_string";

						$sql2 = "UPDATE domains
								 SET expiry_date = '$new_expiry',
									 notes = CONCAT('$new_notes_renewal\r\n\r\n', notes),
									 active = '1',
									 update_time = '$current_timestamp'
								 WHERE domain = '$row->domain'";

					}
					$result2 = mysql_query($sql2,$connection);
				
				}

				include("_includes/system/update-segments.inc.php");

				$_SESSION['session_result_message'] = "Domains Fully Renewed<BR>";
				
			} elseif ($action == "CPC") { 
			
				if ($new_pcid == "" || $new_pcid == 0) {
	
					$_SESSION['session_result_message'] = "Please choose the new Category<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET cat_id = '$new_pcid',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {

						$sql = "UPDATE domains
								SET cat_id = '$new_pcid',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";

					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
					
					$_SESSION['session_result_message'] = "Category Changed<BR>";
	
				}
	
			} elseif ($action == "CDNS") { 
	
				if ($new_dnsid == "" || $new_dnsid == 0) {
	
					$_SESSION['session_result_message'] = "Please choose the new DNS Profile<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET dns_id = '$new_dnsid',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {

						$sql = "UPDATE domains
								SET dns_id = '$new_dnsid',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";

					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
					
					$_SESSION['session_result_message'] = "DNS Profile Changed<BR>";
				}
	
			} elseif ($action == "CIP") {
	
				if ($new_ipid == "" || $new_ipid == 0) {
	
					$_SESSION['session_result_message'] = "Please choose the new IP Address<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET ip_id = '$new_ipid',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {

						$sql = "UPDATE domains
								SET ip_id = '$new_ipid',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";

					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
	
					$_SESSION['session_result_message'] = "IP Address Changed<BR>";
	
				}
	
			} elseif ($action == "AN") {
				
				if ($new_notes == "") {
	
					$_SESSION['session_result_message'] = "Please enter the new Note<BR>";
					$submission_failed = 1;
	
				} else {

					$sql2 = "UPDATE domains
							SET notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					$result2 = mysql_query($sql2,$connection) or die(mysql_error());
					
					$_SESSION['session_result_message'] = "Note Added<BR>";
	
				}

			} elseif ($action == "CRA") { 
	
				if ($new_raid == "" || $new_raid == 0) {
	
					$_SESSION['session_result_message'] = "Please choose the new Registrar Account<BR>";
					$submission_failed = 1;
	
				} else {
	
					$sql = "SELECT ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS r_name, o.id AS o_id, o.name AS o_name
							FROM registrar_accounts AS ra, registrars AS r, owners AS o
							WHERE ra.registrar_id = r.id
							  AND ra.owner_id = o.id
							  AND ra.id = '$new_raid'
							GROUP BY r.name, o.name, ra.username
							ORDER BY r.name asc, o.name asc, ra.username asc";
					$result = mysql_query($sql,$connection);
			
					while ($row = mysql_fetch_object($result)) {
						$new_owner_id = $row->o_id;
						$new_registrar_id = $row->r_id;
						$new_registrar_account_id = $row->ra_id;
						$new_owner_name = $row->o_name;
						$new_registrar_name = $row->r_name;
						$new_username = $row->username;
					}

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET owner_id = '$new_owner_id', 
									registrar_id = '$new_registrar_id', 
									account_id = '$new_registrar_account_id',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {

						$sql = "UPDATE domains
								SET owner_id = '$new_owner_id', 
									registrar_id = '$new_registrar_id', 
									account_id = '$new_registrar_account_id',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
					
					$_SESSION['session_result_message'] = "Registrar Account Changed<BR>";

					include("_includes/system/update-domain-fees.inc.php");
	
				}

			} elseif ($action == "CWH") {
	
				if ($new_whid == "" || $new_whid == 0) {
	
					$_SESSION['session_result_message'] = "Please choose the new Web Hosting Provider<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {

						$sql = "UPDATE domains
								SET hosting_id = '$new_whid',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {

						$sql = "UPDATE domains
								SET hosting_id = '$new_whid',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";

					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
	
					$_SESSION['session_result_message'] = "Web Hosting Provider Changed<BR>";
	
				}

			} elseif ($action == "E") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '0',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '0',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";

				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as expired<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "S") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '10',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '10',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";

				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as sold<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "A") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '1',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '1',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as active<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "T") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '2',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '2',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'In Transfer'<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "PRg") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '5',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '5',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'Pending (Registration)'<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "PRn") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '3',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '3',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'Pending (Renewal)'<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "PO") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET active = '4',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET active = '4',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'Pending (Other)'<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "PRVE") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET privacy = '1',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET privacy = '1',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'Private WHOIS'<BR>";

				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
	
			} elseif ($action == "PRVD") { 

				if ($new_notes != "") {

					$sql = "UPDATE domains
							SET privacy = '0',
								notes = CONCAT('$new_notes\r\n\r\n', notes),
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";
					
				} else {

					$sql = "UPDATE domains
							SET privacy = '0',
								update_time = '$current_timestamp'
							WHERE domain IN ($new_data_formatted)";

				}
				$result = mysql_query($sql,$connection) or die(mysql_error());
				
				$_SESSION['session_result_message'] = "Domains marked as 'Public WHOIS'<BR>";
	
			} elseif ($action == "CED") { 
	
				include("_includes/system/functions/check-date-format.inc.php");

				if (!CheckDateFormat($new_expiry_date)) {
	
					$_SESSION['session_result_message'] = "The expiry date you entered is invalid<BR>";
					$submission_failed = 1;
	
				} else {

					if ($new_notes != "") {
	
						$sql = "UPDATE domains
								SET expiry_date = '$new_expiry_date',
									notes = CONCAT('$new_notes\r\n\r\n', notes),
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
						
					} else {
	
						$sql = "UPDATE domains
								SET expiry_date = '$new_expiry_date',
									update_time = '$current_timestamp'
								WHERE domain IN ($new_data_formatted)";
	
					}
					$result = mysql_query($sql,$connection) or die(mysql_error());
					
					$_SESSION['session_result_message'] = "Expiry Date Updated<BR>";
	
				}
	
			}
	
			$done = "1";
			$new_data_unformatted = strtolower(preg_replace("/\r\n/", ", ", $new_data));
	
		}

	}

	include("_includes/system/update-tlds.inc.php");
	include("_includes/system/update-domain-fees.inc.php");
	include("_includes/system/update-ssl-fees.inc.php");
	include("_includes/system/update-segments.inc.php");

}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
<script type="text/javascript">
<!--
function MM_jumpMenu(targ,selObj,restore){ //v3.0
  eval(targ+".location='"+selObj.options[selObj.selectedIndex].value+"'");
  if (restore) selObj.selectedIndex=0;
}
//-->
</script>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php if ($done == "1") { ?>

	<?php if ($submission_failed != "1") { ?>

        <?php if ($action == "AD") { ?>
            <BR><strong>The following domains were added:</strong><BR>
        <?php } elseif ($action == "R") { ?>
            <BR><strong>The following domains were renewed for <?=$new_renewal_years?> year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
        <?php } elseif ($action == "FR") { ?>
            <BR><strong>The following domains were fully renewed for <?=$new_renewal_years?> year<?php if ($new_renewal_years > 1) { echo "s"; } ?>:</strong><BR>
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
        <?php } elseif ($action == "AN") { ?>
            <BR><strong>The following domains had the Note appended:</strong><BR>
        <?php } ?>

		<BR><?=$new_data_unformatted?><BR><BR><BR>
	<?php } ?>

<?php } ?>
Instead of having to waste time editing domains one-by-one, you can use the below form to execute actions on multiple domains.<BR><BR><BR>
<form name="bulk_actions_forum" method="post" action="<?=$PHP_SELF?>">
  <select name="jumpMenu" id="jumpMenu" onChange="MM_jumpMenu('parent',this,0)">
    <option value="bulk-actions.php"<?php if ($action == "") { echo " selected"; } ?>>Click to Choose Action</option>
    <option value="bulk-actions.php?action=AD"<?php if ($action == "AD") { echo " selected"; } ?>>Add Domains</option>
    <option value="bulk-actions.php?action=FR"<?php if ($action == "FR") { echo " selected"; } ?>>Renew Domains (Update Expiry Date, Mark Active, Add Note)</option>
    <option value="bulk-actions.php?action=R"<?php if ($action == "R") { echo " selected"; } ?>>Renew Domains (Update Expiry Date Only)</option>
    <option value="bulk-actions.php?action=A"<?php if ($action == "A") { echo " selected"; } ?>>Mark as 'Active'</option>
    <option value="bulk-actions.php?action=T"<?php if ($action == "T") { echo " selected"; } ?>>Mark as 'In Transfer'</option>
	<option value="bulk-actions.php?action=PRg"<?php if ($action == "PRg") { echo " selected"; } ?>>Mark as 'Pending (Registration)'</option>
	<option value="bulk-actions.php?action=PRn"<?php if ($action == "PRn") { echo " selected"; } ?>>Mark as 'Pending (Renewal)'</option>
	<option value="bulk-actions.php?action=PO"<?php if ($action == "PO") { echo " selected"; } ?>>Mark as 'Pending (Other)'</option>
    <option value="bulk-actions.php?action=E"<?php if ($action == "E") { echo " selected"; } ?>>Mark as 'Expired'</option>
    <option value="bulk-actions.php?action=S"<?php if ($action == "S") { echo " selected"; } ?>>Mark as 'Sold'</option>
    <option value="bulk-actions.php?action=PRVE"<?php if ($action == "PRVE") { echo " selected"; } ?>>Mark as Private WHOIS</option>
    <option value="bulk-actions.php?action=PRVD"<?php if ($action == "PRVD") { echo " selected"; } ?>>Mark as Public WHOIS</option>
    <option value="bulk-actions.php?action=CPC"<?php if ($action == "CPC") { echo " selected"; } ?>>Change Category</option>
    <option value="bulk-actions.php?action=CDNS"<?php if ($action == "CDNS") { echo " selected"; } ?>>Change DNS Profile</option>
    <option value="bulk-actions.php?action=CED"<?php if ($action == "CED") { echo " selected"; } ?>>Change Expiry Date</option>
    <option value="bulk-actions.php?action=CIP"<?php if ($action == "CIP") { echo " selected"; } ?>>Change IP Address</option>
    <option value="bulk-actions.php?action=CRA"<?php if ($action == "CRA") { echo " selected"; } ?>>Change Registrar Account</option>
    <option value="bulk-actions.php?action=CWH"<?php if ($action == "CWH") { echo " selected"; } ?>>Change Web Hosting Provider</option>
    <option value="bulk-actions.php?action=AN"<?php if ($action == "AN") { echo " selected"; } ?>>Add A Note</option>
  </select>

<?php if ($action != "") { ?>
        <BR><BR><BR>
		<?php if ($action == "AD") { ?>
	        <strong>Domains to add (one per line)</strong><a title="Required Field"><font class="default_highlight">*</font></a>
        <?php } else { ?>
	        <strong>Domains to update (one per line)</strong><a title="Required Field"><font class="default_highlight">*</font></a>
        <?php } ?>
        <BR><BR>
        <textarea name="new_data" cols="60" rows="5"><?=$new_data?></textarea>
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
    <strong>Function</strong><BR><BR>
    <input name="new_function" type="text" size="50" maxlength="255" value="<?=$new_function?>">
    <BR><BR>
    <strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_basic_plus_one_year; } ?>">
    <BR><BR>
    <strong>Registrar Account</strong><BR><BR>
    <?php
    $sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                    FROM registrar_accounts AS ra, owners AS o, registrars AS r
                    WHERE ra.owner_id = o.id
                      AND ra.registrar_id = r.id
                    ORDER BY ra.default_account desc, r_name asc, o_name asc, ra.username asc";
    $result_account = mysql_query($sql_account,$connection) or die(mysql_error());
    echo "<select name=\"new_raid\">";
    while ($row_account = mysql_fetch_object($result_account)) {
    
        if ($row_account->id == $new_raid) {
    
            echo "<option value=\"$row_account->id\" selected>[ $row_account->r_name :: $row_account->o_name :: $row_account->username ]</option>";
        
        } else {
    
            echo "<option value=\"$row_account->id\">$row_account->r_name :: $row_account->o_name :: $row_account->username</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>DNS Profile</strong><BR><BR>
    <?php
    $sql_dns = "SELECT id, name
				FROM dns
				ORDER BY default_dns desc, name asc";
    $result_dns = mysql_query($sql_dns,$connection) or die(mysql_error());
    echo "<select name=\"new_dnsid\">";
    while ($row_dns = mysql_fetch_object($result_dns)) {
    
        if ($row_dns->id == $new_dnsid) {
    
            echo "<option value=\"$row_dns->id\" selected>[ $row_dns->name ]</option>";
        
        } else {
    
            echo "<option value=\"$row_dns->id\">$row_dns->name</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>IP Address</strong><BR><BR>
    <?php
    $sql_ip = "SELECT id, name, ip
			   FROM ip_addresses
			   ORDER BY default_ip_address desc, name asc, ip asc";
    $result_ip = mysql_query($sql_ip,$connection) or die(mysql_error());
    echo "<select name=\"new_ipid\">";
    while ($row_ip = mysql_fetch_object($result_ip)) {
    
        if ($row_ip->id == $new_ipid) {
    
            echo "<option value=\"$row_ip->id\" selected>[ $row_ip->name ($row_ip->ip) ]</option>";
        
        } else {
    
            echo "<option value=\"$row_ip->id\">$row_ip->name ($row_ip->ip)</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Web Hosting Provider</strong><BR><BR>
    <?php
    $sql_host = "SELECT id, name
				 FROM hosting
				 ORDER BY default_host desc, name asc";

    $result_host = mysql_query($sql_host,$connection) or die(mysql_error());
    echo "<select name=\"new_whid\">";
    while ($row_host = mysql_fetch_object($result_host)) {
    
        if ($row_host->id == $new_whid) {
    
            echo "<option value=\"$row_host->id\" selected>[ $row_host->name ]</option>";
        
        } else {
    
            echo "<option value=\"$row_host->id\">$row_host->name</option>";
        
        }
    }
    echo "</select>";
    ?>
    <BR><BR>
    <strong>Category</strong><BR><BR>
    <?php
    $sql_cat = "SELECT id, name
				FROM categories
				ORDER BY default_category desc, name asc";

    $result_cat = mysql_query($sql_cat,$connection) or die(mysql_error());
    echo "<select name=\"new_pcid\">";
    while ($row_cat = mysql_fetch_object($result_cat)) {
    
        if ($row_cat->id == $new_pcid) {
    
            echo "<option value=\"$row_cat->id\" selected>[ $row_cat->name ]</option>";
        
        } else {
    
            echo "<option value=\"$row_cat->id\">$row_cat->name</option>";
        
        }
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
				ORDER BY default_category desc, name asc";
    $result_cat = mysql_query($sql_cat,$connection);
    echo "<strong>New Category</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
	echo "<select name=\"new_pcid\">";
    echo "<option value=\"\""; if ($new_pcid == "") echo " selected"; echo ">"; echo "$choose_text Category</option>";
	while ($row_cat = mysql_fetch_object($result_cat)) { 
    echo "<option value=\"$row_cat->id\""; if ($row_cat->id == $new_pcid) echo " selected"; echo ">"; echo "$row_cat->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CDNS") { ?>

	<?php
    $sql_dns = "SELECT id, name
				FROM dns
				ORDER BY name asc";
    $result_dns = mysql_query($sql_dns,$connection);
    echo "<strong>New DNS Profile</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_dnsid\">";
    echo "<option value=\"\""; if ($new_dnsid == "") echo " selected"; echo ">"; echo "$choose_text DNS Profile</option>";
    while ($row_dns = mysql_fetch_object($result_dns)) { 
    echo "<option value=\"$row_dns->id\""; if ($row_dns->id == $new_dnsid) echo " selected"; echo ">"; echo "$row_dns->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CIP") { ?>

	<?php
    $sql_ip = "SELECT id, name, ip
			   FROM ip_addresses
			   ORDER BY name asc, ip asc";
    $result_ip = mysql_query($sql_ip,$connection);
    echo "<strong>New IP Address</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_ipid\">";
    echo "<option value=\"\""; if ($new_ipid == "") echo " selected"; echo ">"; echo "$choose_text IP Address</option>";
    while ($row_ip = mysql_fetch_object($result_ip)) { 
    echo "<option value=\"$row_ip->id\""; if ($row_ip->id == $new_ipid) echo " selected"; echo ">"; echo "$row_ip->name ($row_ip->ip)</option>";
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
    $result_account = mysql_query($sql_account,$connection);
    echo "<strong>New Registrar Account</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_raid\">";
    echo "<option value=\"\""; if ($new_raid == "") echo " selected"; echo ">"; echo "$choose_text Registrar Account</option>";
	while ($row_account = mysql_fetch_object($result_account)) { 
	    echo "<option value=\"$row_account->ra_id\""; if ($row_account->ra_id == $new_raid) echo " selected"; echo ">"; echo "$row_account->r_name :: $row_account->o_name ($row_account->username)</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "CWH") { ?>

	<?php
    $sql_host = "SELECT id, name
				 FROM hosting
				 ORDER BY name asc";
    $result_host = mysql_query($sql_host,$connection);
    echo "<strong>New Web Hosting Provider</strong><a title=\"Required Field\"><font class=\"default_highlight\">*</font></a><BR><BR>";
    echo "<select name=\"new_whid\">";
    echo "<option value=\"\""; if ($new_whid == "") echo " selected"; echo ">"; echo "$choose_text Web Hosting Provider</option>";
    while ($row_host = mysql_fetch_object($result_host)) { 
    echo "<option value=\"$row_host->id\""; if ($row_host->id == $new_whid) echo " selected"; echo ">"; echo "$row_host->name</option>";
    } 
    echo "</select>";
    ?>
    <BR><BR>
<?php } elseif ($action == "AN") { ?>

<?php } elseif ($action == "CED") { ?>
<strong>New Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_basic; } ?>" size="10" maxlength="10">
    <BR><BR>
<?php } ?>
<?php if ($action != "") { ?>
    <?php if ($action == "AN") { ?>
		<strong>Notes</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <?php } else { ?>
		<strong>Notes</strong><BR><BR>
    <?php } ?>
    <textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
    <BR><BR>
    <input type="hidden" name="action" value="<?=$action?>">
    <?php if ($action == "CDNS") { ?>
    <input type="hidden" name="dnsid" value="<?=$new_dnsid?>">
    <?php } ?>
    <?php if ($action == "CIP") { ?>
    <input type="hidden" name="ipid" value="<?=$new_ipid?>">
    <?php } ?>
    <?php if ($action == "CRA") { ?>
    <input type="hidden" name="raid" value="<?=$new_raid?>">
    <?php } ?>
    <?php if ($action == "CWH") { ?>
    <input type="hidden" name="whid" value="<?=$new_whid?>">
    <?php } ?>
    <input type="submit" name="button" value="Perform Bulk Action &raquo;">
<?php } ?>
</form>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>