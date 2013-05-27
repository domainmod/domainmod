<?php
// /segment-results.php
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
include("_includes/timestamps/current-timestamp.inc.php");

$segid = $_GET['segid'];
$export = $_GET['export'];
$type = $_GET['type'];

if ($type == "inactive") { 
	$page_title = "Segments - Inactive Domains";
} elseif ($type == "filtered") {
	$page_title = "Segments - Filtered Domains";
} elseif ($type == "missing") {
	$page_title = "Segments - Missing Domains";
}

$software_section = "segments";

if ($type == "inactive") { 

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = c.id
			  AND c.id = cc.currency_id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
			  AND cc.user_id = '" . $_SESSION['user_id'] . "'
			  AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = '$segid' AND inactive = '1' ORDER BY domain)
			ORDER BY d.domain asc";	

} elseif ($type == "filtered") {

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = c.id
			  AND c.id = cc.currency_id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
			  AND cc.user_id = '" . $_SESSION['user_id'] . "'
			  AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = '$segid' AND filtered = '1' ORDER BY domain)
			ORDER BY d.domain asc";	

} elseif ($type == "missing") {

	$sql = "SELECT domain
			FROM segment_data
			WHERE segment_id = '$segid'
			  AND missing = '1'
			ORDER BY domain";

}
$result = mysql_query($sql,$connection);

if ($export == "1") {

	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$current_timestamp_unix = strtotime($current_timestamp);
	if ($type == "inactive") { 
		$export_filename = "segment_results_inactive_" . $current_timestamp_unix . ".csv";
	} elseif ($type == "filtered") {
		$export_filename = "segment_results_filtered_" . $current_timestamp_unix . ".csv";
	} elseif ($type == "missing") {
		$export_filename = "segment_results_missing_" . $current_timestamp_unix . ".csv";
	}
	include("_includes/system/export/header.inc.php");
	
	if ($type == "inactive" || $type == "filtered") { 
	
		if ($type == "inactive") {
			
			$row_content[$count++] = "INACTIVE DOMAINS";
			include("_includes/system/export/write-row.inc.php");

			fputcsv($file_content, $blank_line);

		} elseif ($type == "filtered") {

			$row_content[$count++] = "FILTERED DOMAINS";
			include("_includes/system/export/write-row.inc.php");

			fputcsv($file_content, $blank_line);
			
		}

		$row_content[$count++] = "Domain Status";
		$row_content[$count++] = "Expiry Date";
		$row_content[$count++] = "Initial Fee";
		$row_content[$count++] = "Renewal Fee";
		$row_content[$count++] = "Domain";
		$row_content[$count++] = "TLD";
		$row_content[$count++] = "WHOIS Status";
		$row_content[$count++] = "Registrar";
		$row_content[$count++] = "Username";
		$row_content[$count++] = "DNS Profile";
		$row_content[$count++] = "IP Address Name";
		$row_content[$count++] = "IP Address";
		$row_content[$count++] = "IP Address rDNS";
		$row_content[$count++] = "Web Host";
		$row_content[$count++] = "Category";
		$row_content[$count++] = "Category Stakeholder";
		$row_content[$count++] = "Owner";
		$row_content[$count++] = "Function";
		$row_content[$count++] = "Notes";
		include("_includes/system/export/write-row.inc.php");
	
	} elseif ($type == "missing") {
	
		$row_content[$count++] = "MISSING DOMAINS";
		include("_includes/system/export/write-row.inc.php");
	
	}

	if ($type == "inactive" || $type == "filtered") {

		while ($row = mysql_fetch_object($result)) {
			
			$temp_initial_fee = $row->initial_fee * $row->conversion;
			$total_initial_fee_export = $total_initial_fee_export + $temp_initial_fee;

			$temp_renewal_fee = $row->renewal_fee * $row->conversion;
			$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;
	
			if ($row->active == "0") { $domain_status = "EXPIRED"; } 
			elseif ($row->active == "1") { $domain_status = "ACTIVE"; } 
			elseif ($row->active == "2") { $domain_status = "IN TRANSFER"; } 
			elseif ($row->active == "3") { $domain_status = "PENDING (RENEWAL)"; } 
			elseif ($row->active == "4") { $domain_status = "PENDING (OTHER)"; } 
			elseif ($row->active == "5") { $domain_status = "PENDING (REGISTRATION)"; } 
			elseif ($row->active == "10") { $domain_status = "SOLD"; } 
			else { $domain_status = "ERROR -- PROBLEM WITH CODE IN SEGMENT-RESULTS.PHP"; } 
			
			if ($row->privacy == "1") {
				$privacy_status = "Private";
			} elseif ($row->privacy == "0") {
				$privacy_status = "Public";
			}
	
			$temp_input_amount = $temp_initial_fee;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("_includes/system/convert-and-format-currency.inc.php");
			$export_initial_fee = $temp_output_amount;

			$temp_input_amount = $temp_renewal_fee;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("_includes/system/convert-and-format-currency.inc.php");
			$export_renewal_fee = $temp_output_amount;
	
			$row_content[$count++] = $domain_status;
			$row_content[$count++] = $row->expiry_date;
			$row_content[$count++] = $export_initial_fee;
			$row_content[$count++] = $export_renewal_fee;
			$row_content[$count++] = $row->domain;
			$row_content[$count++] = "." . $row->tld;
			$row_content[$count++] = $privacy_status;
			$row_content[$count++] = $row->registrar_name;
			$row_content[$count++] = $row->username;
			$row_content[$count++] = $row->dns_profile;
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->ip;
			$row_content[$count++] = $row->rdns;
			$row_content[$count++] = $row->wh_name;
			$row_content[$count++] = $row->category_name;
			$row_content[$count++] = $row->category_stakeholder;
			$row_content[$count++] = $row->owner_name;
			$row_content[$count++] = $row->function;
			$row_content[$count++] = $row->notes;
			include("_includes/system/export/write-row.inc.php");

		}
		
	} elseif ($type == "missing") {

		while ($row = mysql_fetch_object($result)) {
			
			$row_content[$count++] = $row->domain;
			include("_includes/system/export/write-row.inc.php");

		}
		
	}

/*
	if ($type == "inactive" || $type == "filtered") {

		$temp_input_amount = $total_initial_fee_export;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		$total_export_initial_fee = $temp_output_amount;

		$temp_input_amount = $total_renewal_fee_export;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		$total_export_renewal_fee = $temp_output_amount;

	}
*/

	include("_includes/system/export/footer.inc.php");

}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/layout/head-tags-bare.inc.php"); ?>
</head>
<body>
<?php include("_includes/layout/header-bare.inc.php"); ?>
<?php
$sql_name = "SELECT name
			 FROM segments
			 WHERE id = '$segid'";
$result_name = mysql_query($sql_name,$connection);
while ($row_name = mysql_fetch_object($result_name)) { $segment_name = $row_name->name; }
?>

<?php
if ($type == "inactive") { 
	echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, and they are stored in your  " . $software_title . " database, but they are currently marked as inactive.<BR><BR>";
} elseif ($type == "filtered") {
	echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, and they are stored in your  " . $software_title . " database, but they were filtered out based on your search criteria.<BR><BR>";
} elseif ($type == "missing") {
	echo "The below domains are in the segment <strong><font class=\"highlight\">" . $segment_name . "</font></strong>, but they are not in your " . $software_title . " database.<BR><BR>";
}
?>
<?php
if ($type == "inactive") { 
	echo "[<a href=\"" . $PHP_SELF . "?type=inactive&segid=" . $segid . "&export=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "filtered") {
	echo "[<a href=\"" . $PHP_SELF . "?type=filtered&segid=" . $segid . "&export=1\">EXPORT RESULTS</a>]<BR><BR>";
} elseif ($type == "missing") {
	echo "[<a href=\"" . $PHP_SELF . "?type=missing&segid=" . $segid . "&export=1\">EXPORT RESULTS</a>]<BR><BR>";
}
?>
<?php
while ($row = mysql_fetch_object($result)) {
	echo $row->domain . "<BR>";
}
?>
<?php include("_includes/layout/footer-bare.inc.php"); ?>
</body>
</html>