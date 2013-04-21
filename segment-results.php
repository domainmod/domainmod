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
include("_includes/timestamps/current-timestamp-basic.inc.php");

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

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, ra.username, r.name AS registrar_name, o.name AS owner_name, f.renewal_fee AS renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = cc.id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
			  AND d.domain in (SELECT domain FROM segment_data WHERE segment_id = '$segid' AND inactive = '1' ORDER BY domain)
			ORDER BY d.domain asc";	

} elseif ($type == "filtered") {

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, ra.username, r.name AS registrar_name, o.name AS owner_name, f.renewal_fee AS renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.owner_id = o.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = cc.id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND d.hosting_id = h.id
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

$full_export = "";

if ($export == "1") {

	if ($type == "inactive" || $type == "filtered") { 
	
		$sql_currency = "SELECT currency
						 FROM currencies
						 WHERE default_currency = '1'";
		$result_currency = mysql_query($sql_currency,$connection);
		while ($row_currency = mysql_fetch_object($result_currency)) {
			$default_currency = $row_currency->currency;
		}
	
		if ($type == "inactive") {
			
			$full_export .= "\"INACTIVE DOMAINS\"\n\n";
			
		} elseif ($type == "filtered") {

			$full_export .= "\"FILTERED DOMAINS\"\n\n";
			
		}

		$full_export .= "\"All fees are listed in " . $default_currency . "\"\n\n";
	
		$full_export .= "\"Domain Status\",\"Expiry Date\",\"Renewal Fee\",\"Domain\",\"TLD\",\"WHOIS Status\",\"Registrar\",\"Username\",\"DNS Profile\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Web Host\",\"Category\",\"Category Stakeholder\",\"Owner\",\"Function\",\"Notes\"\n";
	
	} elseif ($type == "missing") {
	
		$full_export .= "\"MISSING DOMAINS\"\n";
	
	}

	if ($type == "inactive" || $type == "filtered") {

		while ($row = mysql_fetch_object($result)) {
			
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
	
			$temp_input_amount = $temp_renewal_fee;
			$temp_input_conversion = "";
			include("_includes/system/convert-and-format-currency.inc.php");
			$export_renewal_fee = $temp_output_amount;
	
			$full_export .= "\"$domain_status\",\"$row->expiry_date\",\"" . $export_renewal_fee . "\",\"$row->domain\",\".$row->tld\",\"$privacy_status\",\"$row->registrar_name\",\"$row->username\",\"$row->dns_profile\",\"$row->name\",\"$row->ip\",\"$row->rdns\",\"$row->wh_name\",\"$row->category_name\",\"$row->category_stakeholder\",\"$row->owner_name\",\"$row->function\",\"$row->notes\"\n";

		}
		
	} elseif ($type == "missing") {

		while ($row = mysql_fetch_object($result)) {
			
			$full_export .= "\"$row->domain\"\n";
		}
		
	}

	$full_export .= "\n";

	if ($type == "inactive" || $type == "filtered") {

		$temp_input_amount = $total_renewal_fee_export;
		$temp_input_conversion = "";
		include("_includes/system/convert-and-format-currency.inc.php");
		$total_export_renewal_fee = $temp_output_amount;
	
		$full_export .= "\"\",\"Total Cost:\",\"" . $total_export_renewal_fee . "\",\"" . $default_currency . "\"\n";

	} elseif ($type == "missing") {

	}

	$export = "0";
	
	header('Content-Type: text/plain');
	if ($type == "inactive") { 
		$full_content_disposition = "Content-Disposition: attachment; filename=\"export_inactive_$current_timestamp_basic.csv\"";
	} elseif ($type == "filtered") {
		$full_content_disposition = "Content-Disposition: attachment; filename=\"export_filtered_$current_timestamp_basic.csv\"";
	} elseif ($type == "missing") {
		$full_content_disposition = "Content-Disposition: attachment; filename=\"export_missing_$current_timestamp_basic.csv\"";
	}
	header("$full_content_disposition");
	header('Content-Transfer-Encoding: binary');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	echo $full_export;
	exit;
}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags-bare.inc.php"); ?>
</head>
<body>
<?php include("_includes/header-bare.inc.php"); ?>
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
	echo "[<a href=\"" . $PHP_SELF . "?type=inactive&segid=" . $segid . "&export=1\">export results</a>]<BR><BR>";
} elseif ($type == "filtered") {
	echo "[<a href=\"" . $PHP_SELF . "?type=filtered&segid=" . $segid . "&export=1\">export results</a>]<BR><BR>";
} elseif ($type == "missing") {
	echo "[<a href=\"" . $PHP_SELF . "?type=missing&segid=" . $segid . "&export=1\">export results</a>]<BR><BR>";
}
?>
<?php
while ($row = mysql_fetch_object($result)) {
	echo $row->domain . "<BR>";
}
?>
<?php include("_includes/footer-bare.inc.php"); ?>
</body>
</html>