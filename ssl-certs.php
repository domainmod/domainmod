<?php
// /ssl-certs.php
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

$page_title = "SSL Certificates";
$software_section = "ssl-certs";

// Form Variables
$export = $_GET['export'];
$oid = $_REQUEST['oid'];
$did = $_REQUEST['did'];
$sslpid = $_REQUEST['sslpid'];
$sslpaid = $_REQUEST['sslpaid'];
$ssltid = $_REQUEST['ssltid'];
$sslipid = $_REQUEST['sslipid'];
$sslpcid = $_REQUEST['sslpcid'];
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];
$from_dropdown = $_REQUEST['from_dropdown'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') $from_dropdown = 0;

if ($export != "1") {

	if ($from_dropdown != "1") {

		if ($search_for != "") { $_SESSION['search_for_ssl'] = $search_for; } else { $_SESSION['search_for_ssl'] = ""; }

	}

}

if ($result_limit == "") $result_limit = $_SESSION['number_of_ssl_certs'];
if ($is_active == "") $is_active = "LIVE";

//
// START - Code for pagination
// 
function pageBrowser($totalrows,$numLimit,$amm,$queryStr,$numBegin,$begin,$num) {
		$larrow = "&nbsp;&laquo; Prev &nbsp;";
		$rarrow = "&nbsp;Next &raquo;&nbsp;";
		$wholePiece = "<B>Page:</B> ";
		if ($totalrows > 0) {
			$numSoFar = 1;
			$cycle = ceil($totalrows/$amm);
			
			if (!isset($numBegin) || $numBegin < 1) {
				$numBegin = 1;
				$num = 1;
			}

			$minus = $numBegin-1;
			$start = $minus*$amm;

			if (!isset($begin)) {
				$begin = $start;
			}

			$preBegin = $numBegin-$numLimit;
			$preStart = $amm*$numLimit;
			$preStart = $start-$preStart;
			$preVBegin = $start-$amm;
			$preRedBegin = $numBegin-1;

			if ($start > 0 || $numBegin > 1) {
				$wholePiece .= "<a href='?num=".$preRedBegin
						."&numBegin=".$preBegin
						."&begin=".$preVBegin
						.$queryStsslp."'>"
						.$larrow."</a>\n";
			}

			for ($i=$numBegin;$i<=$cycle;$i++) {
				if ($numSoFar == $numLimit+1) {
					$piece = "<a href='?numBegin=".$i
						."&num=".$i
						."&begin=".$start
						.$queryStsslp."'>"
						.$rarrow."</a>\n";
					$wholePiece .= $piece;
					break;
				}

				$piece = "<a href='?begin=".$start
					."&num=".$i
					."&numBegin=".$numBegin
					.$queryStr
					."'>";

				if ($num == $i) {
					$piece .= "</a><b>$i</b><a>";
				} else {
					$piece .= "$i";
				}

				$piece .= "</a>\n";
				$start = $start+$amm;
				$numSoFar++;
				$wholePiece .= $piece;

			}

			$wholePiece .= "\n";
			$wheBeg = $begin+1;
			$wheEnd = $begin+$amm;
			$wheToWhe = "<b>".number_format($wheBeg)."</b>-<b>";

			if ($totalrows <= $wheEnd) {
				$wheToWhe .= $totalrows."</b>";
			} else {
				$wheToWhe .= number_format($wheEnd)."</b>";
			}

			$sqlprod = " LIMIT ".$begin.", ".$amm;

		} else {

			$wholePiece = "";
			$wheToWhe = "<b>0</b> - <b>0</b>";

		}

		return array($sqlprod,$wheToWhe,$wholePiece);
	}

//
// END - Code for pagination
// 
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND o.id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND d.id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslp.id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") { $sort_by_string = " ORDER BY sslc.expiry_date asc, sslc.name asc "; } 
elseif ($sort_by == "ed_d") { $sort_by_string = " ORDER BY sslc.expiry_date desc, sslc.name asc "; } 
elseif ($sort_by == "dn_a") { $sort_by_string = " ORDER BY d.domain asc "; } 
elseif ($sort_by == "dn_d") {  $sort_by_string = " ORDER BY d.domain desc "; } 
elseif ($sort_by == "sslc_a") { $sort_by_string = " ORDER BY sslc.name asc "; } 
elseif ($sort_by == "sslc_d") { $sort_by_string = " ORDER BY sslc.name desc "; } 
elseif ($sort_by == "sslf_a") { $sort_by_string = " ORDER BY sslcf.type asc, sslc.name asc "; } 
elseif ($sort_by == "sslf_d") { $sort_by_string = " ORDER BY sslcf.type desc, sslc.name asc "; } 
elseif ($sort_by == "sslip_a") { $sort_by_string = " ORDER BY ip.name asc, ip.ip asc "; } 
elseif ($sort_by == "sslip_d") { $sort_by_string = " ORDER BY ip.name desc, ip.ip desc "; } 
elseif ($sort_by == "sslpc_a") { $sort_by_string = " ORDER BY cat.name asc "; } 
elseif ($sort_by == "sslpc_d") { $sort_by_string = " ORDER BY cat.name desc "; } 
elseif ($sort_by == "o_a") { $sort_by_string = " ORDER BY o.name asc, sslc.name asc "; } 
elseif ($sort_by == "o_d") { $sort_by_string = " ORDER BY o.name desc, sslc.name asc "; } 
elseif ($sort_by == "sslp_a") { $sort_by_string = " ORDER BY sslp.name asc, sslc.name asc "; } 
elseif ($sort_by == "sslp_d") { $sort_by_string = " ORDER BY sslp.name desc, sslc.name asc "; }
elseif ($sort_by == "sslpa_a") { $sort_by_string = " ORDER BY sslp.name asc, sslc.name asc "; } 
elseif ($sort_by == "sslpa_d") { $sort_by_string = " ORDER BY sslp.name desc, sslc.name asc "; }
elseif ($sort_by == "sf_a") { $sort_by_string = " ORDER BY f.renewal_fee asc "; } 
elseif ($sort_by == "sf_d") { $sort_by_string = " ORDER BY f.renewal_fee desc "; }

$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS sslpa_id, sslpa.username, sslp.id AS sslp_id, sslp.name AS ssl_provider_name, o.id AS o_id, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, d.domain, sslcf.id as type_id, sslcf.type, ip.id AS ip_id, ip.name as ip_name, ip.ip, ip.rdns, cat.id AS cat_id, cat.name AS cat_name
		FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
		WHERE sslc.account_id = sslpa.id
		  AND sslpa.ssl_provider_id = sslp.id
		  AND sslpa.owner_id = o.id
		  AND sslc.fee_id = f.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND sslc.domain_id = d.id
		  AND sslc.type_id = sslcf.id
		  AND sslc.ip_id = ip.id
		  AND sslc.cat_id = cat.id
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  $is_active_string
		  $oid_string
		  $did_string
		  $sslpid_string
		  $sslpaid_string
		  $ssltid_string
		  $sslipid_string
		  $sslpcid_string
		  $search_string
		  $sort_by_string";	

$sql_grand_total = "SELECT SUM(f.renewal_fee * cc.conversion) AS grand_total
					FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
					WHERE sslc.account_id = sslpa.id
					  AND sslpa.ssl_provider_id = sslp.id
					  AND sslpa.owner_id = o.id
					  AND sslc.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND sslc.domain_id = d.id
					  AND sslc.type_id = sslcf.id
					  AND sslc.ip_id = ip.id
					  AND sslc.cat_id = cat.id
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  $is_active_string
					  $oid_string
					  $did_string
					  $sslpid_string
					  $sslpaid_string
					  $ssltid_string
					  $sslipid_string
					  $sslpcid_string
					  $search_string
					  $sort_by_string";	

$result_grand_total = mysql_query($sql_grand_total,$connection) or die(mysql_error());
while ($row_grand_total = mysql_fetch_object($result_grand_total)) {
	$grand_total = $row_grand_total->grand_total;
}

$temp_input_amount = $grand_total;
$temp_input_conversion = "";
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include("_includes/system/convert-and-format-currency.inc.php");
$grand_total = $temp_output_amount;

$full_export = "";

if ($export == "1") {

	$result = mysql_query($sql,$connection);
	$total_rows = number_format(mysql_num_rows($result));

	$full_export .= "\"SSL Certificate Search Results Export\"\n\n";
	$full_export .= "\"Total Cost:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n";
	$full_export .= "\"Number of SSL Certs:\",\"" . $total_rows . "\"\n\n";

	$full_export .= "\"[Search Filters]\"\n";

	if ($_SESSION['search_for_ssl'] != "") { 

		$full_export .= "\"Keyword Search:\",\"" . $_SESSION['search_for_ssl'] . "\"\n"; 

	}

	if ($did > 0) {

		$sql_filter = "SELECT domain
					   FROM domains
					   WHERE id = '" . $did . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Associated Domain:\",\"" . $row_filter->domain . "\"\n"; 
		}

	}

	if ($sslpid > 0) {

		$sql_filter = "SELECT name
					   FROM ssl_providers
					   WHERE id = '" . $sslpid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"SSL Provider:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($sslpaid > 0) { 

		$sql_filter = "SELECT sslp.name AS ssl_provider_name, o.name AS owner_name, sslpa.username
					   FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o
					   WHERE sslpa.ssl_provider_id = sslp.id
						 AND sslpa.owner_id = o.id
						 AND sslpa.id = '" . $sslpaid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"SSL Provider Account:\",\"" . $row_filter->ssl_provider_name . " - " . $row_filter->owner_name . " - " . $row_filter->username . "\"\n"; 
		}

	}

	if ($ssltid > 0) {

		$sql_filter = "SELECT type
					   FROM ssl_cert_types
					   WHERE id = '" . $ssltid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"SSL Type:\",\"" . $row_filter->type . "\"\n"; 
		}

	}

	if ($sslipid > 0) {

		$sql_filter = "SELECT name, ip
					   FROM ip_addresses
					   WHERE id = '" . $sslipid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"SSL IP Address:\",\"" . $row_filter->name . " (" . $row_filter->ip . ")\"\n"; 
		}

	}

	if ($sslpcid > 0) {

		$sql_filter = "SELECT name
					   FROM categories
					   WHERE id = '" . $sslpcid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"SSL Category:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($oid > 0) { 

		$sql_filter = "SELECT name
					   FROM owners
					   WHERE id = '" . $oid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Owner:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($is_active == "ALL") { $full_export .= "\"SSL Status:\",\"ALL\"\n"; } 
	elseif ($is_active == "LIVE" || $is_active == "") { $full_export .= "\"SSL Status:\",\"LIVE (Active / Pending)\"\n"; } 
	elseif ($is_active == "0") { $full_export .= "\"SSL Status:\",\"Expired\"\n"; } 
	elseif ($is_active == "1") { $full_export .= "\"SSL Status:\",\"Active\"\n"; } 
	elseif ($is_active == "3") { $full_export .= "\"SSL Status:\",\"Pending (Renewal)\"\n"; } 
	elseif ($is_active == "4") { $full_export .= "\"SSL Status:\",\"Pending (Other)\"\n"; } 
	elseif ($is_active == "5") { $full_export .= "\"SSL Status:\",\"Pending (Registration)\"\n"; } 

	$full_export .= "\n";

	$full_export .= "\"SSL Cert Status\",\"Expiry Date\",\"Initial Fee\",\"Renewal Fee\",\"Host / Label\",\"Domain\",\"SSL Provider\",\"SSL Provider Account\",\"Username\",\"SSL Type\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Category\",\"Owner\",\"Notes\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_initial_fee = $row->initial_fee * $row->conversion;

		$temp_renewal_fee = $row->renewal_fee * $row->conversion;

		if ($row->active == "0") { $ssl_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $ssl_status = "ACTIVE"; } 
		elseif ($row->active == "3") { $ssl_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $ssl_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $ssl_status = "PENDING (REGISTRATION)"; } 
		else { $ssl_status = "ERROR -- PROBLEM WITH CODE IN SSL-CERTS.PHP"; } 
		
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

		$full_export .= "\"$ssl_status\",\"$row->expiry_date\",\"" . $export_initial_fee . "\",\"" . $export_renewal_fee . "\",\"" . $row->name . "\",\"" . $row->domain . "\",\"$row->ssl_provider_name\",\"$row->ssl_provider_name, $row->owner_name ($row->username)\",\"$row->username\",\"$row->type\",\"$row->ip_name\",\"$row->ip\",\"$row->rdns\",\"$row->cat_name\",\"$row->owner_name\",\"$row->notes\"\n";
	}
	
	$full_export .= "\n";

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "ssl_results_" . $current_timestamp_unix . ".csv";
	include("_includes/system/export-to-csv.inc.php");
	exit;
}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/layout/head-tags.inc.php"); ?>
<script type="text/javascript">
<!--
function MM_jumpMenu(targ,selObj,restore){ //v3.0
  eval(targ+".location='"+selObj.options[selObj.selectedIndex].value+"'");
  if (restore) selObj.selectedIndex=0;
}
//-->
</script>
</head>
<body onLoad="document.forms[0].elements[10].focus()";>
<?php include("_includes/layout/header.inc.php"); ?>
<?php
if ($_SESSION['need_ssl_provider'] == "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Providers found. Please <a href=\"assets/add/ssl-provider.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['need_ssl_account'] == "1" && $_SESSION['need_ssl_provider'] != "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Provider Accounts found. Please <a href=\"assets/add/ssl-provider-account.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['need_ssl_cert'] == "1" && $_SESSION['need_ssl_provider'] != "1" && $_SESSION['need_ssl_account'] != "1" && $_SESSION['need_domain'] == "0") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Certificates. Please <a href=\"add/ssl-cert.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['need_domain'] == "1" && $_SESSION['need_ssl_provider'] == "0" && $_SESSION['need_ssl_account'] == "0") {
	echo "Before you can add an SSL Certificate you must have at least one domain stored in your $software_title. Please <a href=\"domains.php\">click here</a> to add one.<BR><BR>";
	exit;
}
$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "&oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=" . $_SESSION['search_for_ssl'] . "",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection);
$total_rows = number_format(mysql_num_rows($result));
?>
<form name="ssl_cert_search_form" method="post" action="<?=$PHP_SELF?>">
<div class="search-block-outer">
<div class="search-block-inner">
<div class="search-block-left">
&nbsp;&nbsp;
<?php 
// DOMAIN
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_domain = "SELECT d.id, d.domain 
			   FROM domains AS d, ssl_certs AS sslc
			   WHERE d.id = sslc.domain_id
			     AND d.active not in ('0', '10')
			     $is_active_string
			     $oid_string
			     $sslpid_string
			     $sslpaid_string
			     $ssltid_string
			     $sslipid_string
			     $sslpcid_string
			     $search_string
			   GROUP BY d.domain
			   ORDER BY d.domain asc"; 
$result_domain = mysql_query($sql_domain,$connection);
echo "<select name=\"did\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">Domain - ALL</option>";
while ($row_domain = mysql_fetch_object($result_domain)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$row_domain->id&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_domain->id == $did) echo " selected"; echo ">"; echo "$row_domain->domain</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// SSL PROVIDER
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_ssl_provider = "SELECT sslp.id, sslp.name 
					 FROM ssl_providers AS sslp, ssl_certs AS sslc, domains AS d
					 WHERE sslp.id = sslc.ssl_provider_id
					   AND sslc.domain_id = d.id
					   $is_active_string
					   $oid_string
					   $did_string
					   $sslpaid_string
					   $ssltid_string
					   $sslipid_string
					   $sslpcid_string
					   $search_string
					 GROUP BY sslp.name
					 ORDER BY sslp.name asc";
$result_ssl_provider = mysql_query($sql_ssl_provider,$connection);
echo "<select name=\"sslpid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">SSL Provider - ALL</option>";
while ($row_ssl_provider = mysql_fetch_object($result_ssl_provider)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$row_ssl_provider->id&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_ssl_provider->id == $sslpid) echo " selected"; echo ">"; echo "$row_ssl_provider->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// SSL PROVIDER ACCOUNT
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_account = "SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
				FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_certs AS sslc, domains AS d
				WHERE sslpa.ssl_provider_id = sslp.id
				  AND sslpa.owner_id = o.id
				  AND sslpa.id = sslc.account_id
				  AND sslc.domain_id = d.id
				  $is_active_string
				  $oid_string
				  $did_string
				  $sslpid_string
				  $ssltid_string
				  $sslipid_string
				  $sslpcid_string
				  $search_string
				GROUP BY sslp.name, o.name, sslpa.username
				ORDER BY sslp.name asc, o.name asc, sslpa.username asc";
$result_account = mysql_query($sql_account,$connection);
echo "<select name=\"sslpaid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">SSL Provider Account - ALL</option>";
while ($row_account = mysql_fetch_object($result_account)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$row_account->sslpa_id&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_account->sslpa_id == $sslpaid) echo " selected"; echo ">"; echo "$row_account->sslp_name :: $row_account->owner_name ($row_account->username)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// TYPE
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_type = "SELECT sslc.type_id, sslcf.type
			 FROM ssl_certs AS sslc, domains AS d, ssl_cert_types AS sslcf
			 WHERE sslc.domain_id = d.id
			   AND sslc.type_id = sslcf.id
			   $is_active_string
			   $oid_string
			   $did_string
			   $sslpid_string
			   $sslpaid_string
			   $sslipid_string
			   $sslpcid_string
			   $search_string
			 GROUP BY sslcf.type
			 ORDER BY sslcf.type asc";
$result_type = mysql_query($sql_type,$connection);
echo "<select name=\"ssltid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">SSL Type - ALL</option>";
while ($row_type = mysql_fetch_object($result_type)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$row_type->type_id&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_type->type_id == $ssltid) echo " selected"; echo ">"; echo "$row_type->type</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// IP ADDRESS
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_ip = "SELECT ip.id AS ip_id, ip.name AS ip_name, ip.ip
		   FROM ssl_certs AS sslc, domains AS d, ip_addresses AS ip
		   WHERE sslc.domain_id = d.id
		     AND sslc.ip_id = ip.id
		     $is_active_string
		     $oid_string
		     $did_string
		     $sslpid_string
		     $sslpaid_string
		     $ssltid_string
		     $sslpcid_string
		     $search_string
		   GROUP BY ip.name, ip.ip
		   ORDER BY ip.name, ip.ip";
$result_ip = mysql_query($sql_ip,$connection);
echo "<select name=\"sslipid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">IP Address - ALL</option>";
while ($row_ip = mysql_fetch_object($result_ip)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$row_ip->ip_id&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_ip->ip_id == $sslipid) echo " selected"; echo ">"; echo "$row_ip->ip_name ($row_ip->ip)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// CATEGORY
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND sslc.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_cat = "SELECT c.id AS cat_id, c.name AS cat_name
			FROM ssl_certs AS sslc, domains AS d, categories AS c
			WHERE sslc.domain_id = d.id
			  AND sslc.cat_id = c.id
			  $is_active_string
			  $oid_string
			  $did_string
			  $sslpid_string
			  $sslpaid_string
			  $ssltid_string
			  $sslipid_string
			  $search_string
		   GROUP BY c.name
		   ORDER BY c.name";
$result_cat = mysql_query($sql_cat,$connection);
echo "<select name=\"sslpcid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">Category - ALL</option>";
while ($row_cat = mysql_fetch_object($result_cat)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$row_cat->cat_id&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_cat->cat_id == $sslpcid) echo " selected"; echo ">"; echo "$row_cat->cat_name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// OWNER
if ($is_active == "0") { $is_active_string = " AND sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND sslc.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND sslc.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND sslc.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($did != "") { $did_string = " AND sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " AND sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND sslc.type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND sslc.ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND sslc.cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }
if ($_SESSION['search_for_ssl'] != "") { $search_string = " AND (sslc.name LIKE '%" . $_SESSION['search_for_ssl'] . "%' OR d.domain LIKE '%" . $_SESSION['search_for_ssl'] . "%')"; } else { $search_string = ""; }

$sql_owner = "SELECT o.id, o.name 
			  FROM owners AS o, ssl_certs AS sslc, domains AS d
			  WHERE o.id = sslc.owner_id
			    AND o.id = d.owner_id
				$is_active_string
				$did_string
				$sslpid_string
				$sslpaid_string
				$ssltid_string
				$sslipid_string
				$sslpcid_string
				$search_string
			  GROUP BY o.name
			  ORDER BY o.name asc";
$result_owner = mysql_query($sql_owner,$connection);
echo "<select name=\"oid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\">Owner - ALL</option>";
while ($row_owner = mysql_fetch_object($result_owner)) { 
	echo "<option value=\"$PHP_SELF?oid=$row_owner->id&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_owner->id == $oid) echo " selected"; echo ">"; echo "$row_owner->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// STATUS
if ($is_active == "0") { $is_active_string = " AND active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($oid != "") { $oid_string = " AND owner_id = '$oid' "; } else { $oid_string = ""; }
if ($did != "") { $did_string = " AND domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslp_string = " AND ssl_provider_id = '$sslpid' "; } else { $sslp_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($ssltid != "") { $ssltid_string = " AND type_id = '$ssltid' "; } else { $ssltid_string = ""; }
if ($sslipid != "") { $sslipid_string = " AND ip_id = '$sslipid' "; } else { $sslipid_string = ""; }
if ($sslpcid != "") { $sslpcid_string = " AND cat_id = '$sslpcid' "; } else { $sslpcid_string = ""; }

$sql_active = "SELECT active, count(*) AS total_count
			   FROM ssl_certs
			   WHERE id != '0'
			     $oid_string
			     $did_string
			     $sslpid_string
			     $sslpaid_string
			     $ssltid_string
			     $sslipid_string
			     $sslpcid_string
			   GROUP BY active
			   ORDER BY active asc";
$result_active = mysql_query($sql_active,$connection);
echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($is_active == "LIVE") echo " selected"; echo ">"; echo "\"Live\" (Active / Pending)</option>";
while ($row_active = mysql_fetch_object($result_active)) {
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($row_active->active == $is_active) echo " selected"; echo ">"; if ($row_active->active == "0") { echo "Expired"; } elseif ($row_active->active == "1") { echo "Active"; } elseif ($row_active->active == "3") { echo "Pending (Renewal)"; } elseif ($row_active->active == "4") { echo "Pending (Other)"; } elseif ($row_active->active == "5") { echo "Pending (Registration)"; } echo "</option>";
} 
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($is_active == "ALL") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>

&nbsp;&nbsp;
<?php 
// NUMBER OF SSL CERTS TO DISPLAY
echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">"; 

if ($_SESSION['number_of_ssl_certs'] != "10" && $_SESSION['number_of_ssl_certs'] != "50" && $_SESSION['number_of_ssl_certs'] != "100" && $_SESSION['number_of_ssl_certs'] != "500" && $_SESSION['number_of_ssl_certs'] != "1000" && $_SESSION['number_of_ssl_certs'] != "1000000") {
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=" . $_SESSION['number_of_ssl_certs'] . "&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == $_SESSION['number_of_ssl_certs']) echo " selected"; echo ">"; echo "" . $_SESSION['number_of_ssl_certs'] . "</option>";
}

echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=10&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "10") echo " selected"; echo ">"; echo "10</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=50&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "50") echo " selected"; echo ">"; echo "50</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=100&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "100") echo " selected"; echo ">"; echo "100</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=500&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "500") echo " selected"; echo ">"; echo "500</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=1000&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "1000") echo " selected"; echo ">"; echo "1,000</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&sslipid=$sslipid&sslpcid=$sslpcid&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&from_dropdown=1&search_for=" . $_SESSION['search_for_ssl'] . "\""; if ($result_limit == "1000000") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>
<BR>
<input type="hidden" name="sort_by" value="<?=$sort_by?>">
</div>
<div class="search-block-right">
<strong>Keyword Search:</strong><BR><BR>
<input name="search_for" type="text" id="textfield" value="<?=$_SESSION['search_for_ssl']?>" size="20">&nbsp;&nbsp;<input type="submit" name="button" id="button" value="Search &raquo;">
<input type="hidden" name="oid" value="<?=$oid?>">
<input type="hidden" name="did" value="<?=$did?>">
<input type="hidden" name="sslpid" value="<?=$sslpid?>">
<input type="hidden" name="sslpaid" value="<?=$sslpaid?>">
<input type="hidden" name="ssltid" value="<?=$ssltid?>">
<input type="hidden" name="sslipid" value="<?=$sslipid?>">
<input type="hidden" name="sslpcid" value="<?=$sslpcid?>">
<input type="hidden" name="is_active" value="<?=$is_active?>">
<input type="hidden" name="result_limit" value="<?=$result_limit?>">
</div>
</div>
</div>
</form>
<div style="clear: both;"></div>
<?php if (mysql_num_rows($result) > 0) { ?>
<BR><strong>Total Cost:</strong> <?=$grand_total?> <?=$_SESSION['default_currency']?><BR><BR>
<strong>Number of SSL Certs:</strong> <?=number_format($totalrows)?><BR><BR>
<?php include("_includes/layout/search-options-block.inc.php"); ?>
<BR>
<?php if ($totalrows != '0') { ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ed_a") { echo "ed_d"; } else { echo "ed_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Expiry Date</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sf_a") { echo "sf_d"; } else { echo "sf_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Fee</font></a>
	</td>
<?php } ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslc_a") { echo "sslc_d"; } else { echo "sslc_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Host / Label</font></a>
	</td>
<?php if ($_SESSION['display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dn_a") { echo "dn_d"; } else { echo "dn_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Domain</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslp_a") { echo "sslp_d"; } else { echo "sslp_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">SSL Provider</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslpa_a") { echo "sslpa_d"; } else { echo "sslpa_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">SSL Account</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslf_a") { echo "sslf_d"; } else { echo "sslf_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Type</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_ip'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslip_a") { echo "sslip_d"; } else { echo "sslip_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">IP Address</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_category'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslpc_a") { echo "sslpc_d"; } else { echo "sslpc_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Category</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&ssltid=<?=$ssltid?>&sslipid=<?=$sslipid?>&sslpcid=<?=$sslpcid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "o_a") { echo "o_d"; } else { echo "o_a"; } ?>&from_dropdown=1&search_for=<?=$_SESSION['search_for_ssl']?>"><font class="main_table_heading">Owner</font></a>
	</td>
<?php } ?>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
<?php if ($_SESSION['display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-cert.php?sslcid=<?=$row->id?>"><?=$row->expiry_date?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ssl-provider-fees.php?sslpid=<?=$row->sslp_id?>">
		<?php
		$temp_input_amount = $row->renewal_fee;
		$temp_input_conversion = $row->conversion;
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		echo $temp_output_amount;
		?>
        </a>
	</td>
<?php } ?>
	<td class="main_table_cell_active">
		  <?php if ($row->active == "0") { 
					echo "<a title=\"Expired\"><strong><font class=\"highlight\">x</font></strong></a>&nbsp;"; 
		  		} elseif ($row->active == "2") { 
					echo "<a title=\"Pending (Registration)\"><strong><font class=\"highlight\">PRg</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "3") { 
					echo "<a title=\"Pending (Renewal)\"><strong><font class=\"highlight\">PRn</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "4") { 
					echo "<a title=\"Pending (Other)\"><strong><font class=\"highlight\">PO</font></strong></a>&nbsp;"; 
				}
			?><a class="invisiblelink" href="edit/ssl-cert.php?sslcid=<?=$row->id?>"><?=$row->name?></a>
	</td>
<?php if ($_SESSION['display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/domain.php?did=<?=$row->domain_id?>"><?=$row->domain?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ssl-provider.php?sslpid=<?=$row->sslp_id?>"><?=$row->ssl_provider_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ssl-provider.php?sslpid=<?=$row->sslp_id?>"><?=$row->ssl_provider_name?></a>, <a class="invisiblelink" href="assets/edit/account-owner.php?oid=<?=$row->o_id?>"><?=$row->owner_name?></a> (<a class="invisiblelink" href="assets/edit/ssl-provider-account.php?sslpaid=<?=$row->sslpa_id?>"><?=substr($row->username, 0, 15);?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ssl-type.php?ssltid=<?=$row->type_id?>"><?=$row->type?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_ip'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ip-address.php?ipid=<?=$row->ip_id?>"><?=$row->ip_name?> (<?=$row->ip?>)</a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_category'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/category.php?pcid=<?=$row->cat_id?>"><?=$row->cat_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/account-owner.php?oid=<?=$row->o_id?>"><?=$row->owner_name?></a>
	</td>
<?php } ?>
</tr>
<?php } ?>
</table>
<BR>
<?php } ?>
<?php include("_includes/layout/search-options-block.inc.php"); ?>
<?php } else { ?>
			<BR>Your search returned zero results.
<?php } ?>
<?php include("_includes/layout/footer.inc.php"); ?>
</body>
</html>