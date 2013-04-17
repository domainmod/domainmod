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
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($search_for == "Search Term") $search_for = "";
if ($result_limit == "") $result_limit = $_SESSION['session_number_of_ssl_certs'];
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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") { $sort_by_string = " ORDER BY sslc.expiry_date asc, sslc.name asc "; } 
elseif ($sort_by == "ed_d") { $sort_by_string = " ORDER BY sslc.expiry_date desc, sslc.name asc "; } 
elseif ($sort_by == "dn_a") { $sort_by_string = " ORDER BY d.domain asc "; } 
elseif ($sort_by == "dn_d") {  $sort_by_string = " ORDER BY d.domain desc "; } 
elseif ($sort_by == "sslc_a") { $sort_by_string = " ORDER BY sslc.name asc "; } 
elseif ($sort_by == "sslc_d") { $sort_by_string = " ORDER BY sslc.name desc "; } 
elseif ($sort_by == "sslf_a") { $sort_by_string = " ORDER BY sslcf.type asc, sslc.name asc "; } 
elseif ($sort_by == "sslf_d") { $sort_by_string = " ORDER BY sslcf.type desc, sslc.name asc "; } 
elseif ($sort_by == "o_a") { $sort_by_string = " ORDER BY o.name asc, sslc.name asc "; } 
elseif ($sort_by == "o_d") { $sort_by_string = " ORDER BY o.name desc, sslc.name asc "; } 
elseif ($sort_by == "sslp_a") { $sort_by_string = " ORDER BY sslp.name asc, sslc.name asc "; } 
elseif ($sort_by == "sslp_d") { $sort_by_string = " ORDER BY sslp.name desc, sslc.name asc "; }
elseif ($sort_by == "sslpa_a") { $sort_by_string = " ORDER BY sslp.name asc, sslc.name asc "; } 
elseif ($sort_by == "sslpa_d") { $sort_by_string = " ORDER BY sslp.name desc, sslc.name asc "; }
elseif ($sort_by == "sf_a") { $sort_by_string = " ORDER BY f.renewal_fee asc "; } 
elseif ($sort_by == "sf_d") { $sort_by_string = " ORDER BY f.renewal_fee desc "; }
 
$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS sslpa_id, sslpa.username, sslp.id AS sslp_id, sslp.name AS ssl_provider_name, o.id AS o_id, o.name AS owner_name, f.renewal_fee, cc.conversion, d.domain, sslcf.id as type_id, sslcf.type
		FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS cc, domains AS d, ssl_cert_types AS sslcf
		WHERE sslc.account_id = sslpa.id
		  AND sslpa.ssl_provider_id = sslp.id
		  AND sslpa.owner_id = o.id
		  AND sslc.fee_id = f.id
		  AND f.currency_id = cc.id
		  AND sslc.domain_id = d.id
		  AND sslc.type_id = sslcf.id
		  $is_active_string
		  AND sslpa.active = '1'
		  AND sslp.active = '1'
		  AND o.active = '1'
		  AND cc.active = '1'
		  $oid_string
		  $did_string
		  $sslpid_string
		  $sslpaid_string
		  $ssltid_string
		  $search_string
		  $sort_by_string";	

$full_export = "";

if ($export == "1") {

	$result = mysql_query($sql,$connection);

	$sql_currency = "SELECT currency
			FROM currencies
			WHERE default_currency = '1'
			LIMIT 1";
	$result_currency = mysql_query($sql_currency,$connection);
	while ($row_currency = mysql_fetch_object($result_currency)) { $default_currency = $row_currency->currency; }

	$full_export .= "\"All prices are listed in " . $default_currency . "\"\n\n";

	$full_export .= "\"SSL STATUS\",\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Host / Label\",\"Domain\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"SSL Type\",\"Owner\",\"SSL Provider\",\"Username\",\"Notes\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = $row->renewal_fee * $row->conversion;
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { $ssl_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $ssl_status = "ACTIVE"; } 
		elseif ($row->active == "3") { $ssl_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $ssl_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $ssl_status = "PENDING (REGISTRATION)"; } 
		else { $ssl_status = "ERROR -- PROBLEM WITH CODE IN SSL-CERTS.PHP"; } 
		
		$sql_domain = "SELECT d.domain, ip.name, ip.ip, ip.rdns
					   FROM domains AS d, ip_addresses AS ip
					   WHERE d.ip_id = ip.id
					     AND d.id = '$row->domain_id'";
		$result_domain = mysql_query($sql_domain,$connection);
		
		while ($row_domain = mysql_fetch_object($result_domain)) {
			$full_domain_name = $row_domain->domain;
			$full_ip_name = $row_domain->name;
			$full_ip_address = $row_domain->ip;
			$full_ip_rdns = $row_domain->rdns;
		}

		// Currency Conversion & Formatting
		// Input: $temp_input_amount  /  Conversion: $temp_input_conversion (assign empty variable if no conversion is necessary)
		// Output: $temp_output_amount
		$temp_input_amount = $temp_renewal_fee;
		$temp_input_conversion = "";
		include("_includes/system/convert-and-format-currency.inc.php");
		$export_renewal_fee = $temp_output_amount;

		$full_export .= "\"$ssl_status\",\"$row->expiry_date\",\"$row->to_renew\",\"" . $export_renewal_fee . "\",\"$row->name\",\"$full_domain_name\",\"$full_ip_name\",\"$full_ip_address\",\"$full_ip_rdns\",\"$row->type\",\"$row->owner_name\",\"$row->ssl_provider_name\",\"$row->username\",\"$row->notes\"\n";
	}
	
	$full_export .= "\n";

	// Currency Conversion & Formatting
	// Input: $temp_input_amount  /  Conversion: $temp_input_conversion (assign empty variable if no conversion is necessary)
	// Output: $temp_output_amount
	$temp_input_amount = $total_renewal_fee_export;
	$temp_input_conversion = "";
	include("_includes/system/convert-and-format-currency.inc.php");
	$total_export_renewal_fee = $temp_output_amount;

	$full_export .= "\"\",\"\",\"Total Cost:\",\"" . $total_export_renewal_fee . "\",\"$default_currency\"\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$unixtime_timestamp = strtotime($current_timestamp);
$full_content_disposition = "Content-Disposition: attachment; filename=\"ssl_results_$unixtime_timestamp.csv\"";
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
<body onLoad="document.forms[0].elements[8].focus()";>
<?php include("_includes/header.inc.php"); ?>
<?php
if ($_SESSION['session_need_ssl_provider'] == "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Providers found. Please <a href=\"add/ssl-provider.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['session_need_ssl_account'] == "1" && $_SESSION['session_need_ssl_provider'] != "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Provider Accounts found. Please <a href=\"add/ssl-account.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['session_need_ssl_cert'] == "1" && $_SESSION['session_need_ssl_provider'] != "1" && $_SESSION['session_need_ssl_account'] != "1" && $_SESSION['session_need_domain'] == "0") {
	echo "<strong><font class=\"highlight\">0</font></strong> SSL Certificates. Please <a href=\"add/ssl-cert.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['session_need_domain'] == "1" && $_SESSION['session_need_ssl_provider'] == "0" && $_SESSION['session_need_ssl_account'] == "0") {
	echo "Before you can add an SSL Certificate you must have at least one domain stored in your $software_title. Please <a href=\"domains.php\">click here</a> to add one.<BR><BR>";
	exit;
}
$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "&oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection);
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td width="100%" class="search-table"><BR>
<form name="ssl_cert_search_form" method="post" action="<?=$PHP_SELF?>">
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside" width="640">
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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

$sql_owner = "SELECT o.id, o.name 
			  FROM owners AS o, ssl_certs AS sslc, domains AS d
			  WHERE o.id = sslc.owner_id
			    AND o.id = d.owner_id
				AND o.active = '1'
				$is_active_string
				$did_string
				$sslpid_string
				$sslpaid_string
				$ssltid_string
				$search_string
			  GROUP BY o.name
			  ORDER BY o.name asc";
$result_owner = mysql_query($sql_owner,$connection);
echo "<select name=\"oid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Owner - ALL</option>";
while ($row_owner = mysql_fetch_object($result_owner)) { 
	echo "<option value=\"$PHP_SELF?oid=$row_owner->id&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_owner->id == $oid) echo " selected"; echo ">"; echo "$row_owner->name</option>";
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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

$sql_ssl_provider = "SELECT sslp.id, sslp.name 
					 FROM ssl_providers AS sslp, ssl_certs AS sslc, domains AS d
					 WHERE sslp.id = sslc.ssl_provider_id
					   AND sslc.domain_id = d.id
					   AND sslp.active = '1' 
					   $is_active_string
					   $oid_string
					   $did_string
					   $sslpaid_string
					   $ssltid_string
					   $search_string
					 GROUP BY sslp.name
					 ORDER BY sslp.name asc";
$result_ssl_provider = mysql_query($sql_ssl_provider,$connection);
echo "<select name=\"sslpid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">SSL Provider - ALL</option>";
while ($row_ssl_provider = mysql_fetch_object($result_ssl_provider)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$row_ssl_provider->id&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_ssl_provider->id == $sslpid) echo " selected"; echo ">"; echo "$row_ssl_provider->name</option>";
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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

$sql_account = "SELECT sslpa.id AS sslpa_id, sslpa.username, sslp.name AS sslp_name, o.name AS owner_name
				FROM ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_certs AS sslc, domains AS d
				WHERE sslpa.ssl_provider_id = sslp.id
				  AND sslpa.owner_id = o.id
				  AND sslpa.id = sslc.account_id
				  AND sslc.domain_id = d.id
				  AND sslpa.active = '1'
				  AND sslp.active = '1'
				  AND o.active = '1'
				  $is_active_string
				  $oid_string
				  $did_string
				  $sslpid_string
				  $ssltid_string
				  $search_string
				GROUP BY sslp.name, o.name, sslpa.username
				ORDER BY sslp.name asc, o.name asc, sslpa.username asc";
$result_account = mysql_query($sql_account,$connection);
echo "<select name=\"sslpaid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">SSL Provider Account - ALL</option>";
while ($row_account = mysql_fetch_object($result_account)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$row_account->sslpa_id&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_account->sslpa_id == $sslpaid) echo " selected"; echo ">"; echo "$row_account->sslp_name :: $row_account->owner_name ($row_account->username)</option>";
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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

$sql_type = "SELECT sslc.type_id, sslcf.type
			 FROM ssl_certs AS sslc, domains AS d, ssl_cert_types AS sslcf
			 WHERE sslc.domain_id = d.id
			   AND sslc.type_id = sslcf.id
			   $is_active_string
			   $oid_string
			   $did_string
			   $sslpid_string
			   $sslpaid_string
			   $search_string
			 GROUP BY sslcf.type
			 ORDER BY sslcf.type asc";
$result_type = mysql_query($sql_type,$connection);
echo "<select name=\"ssltid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">SSL Type - ALL</option>";
while ($row_type = mysql_fetch_object($result_type)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$row_type->type_id&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_type->type_id == $ssltid) echo " selected"; echo ">"; echo "$row_type->type</option>";
} 
echo "</select>";
?>
<BR><BR>

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
if ($search_for != "") { $search_string = " AND (sslc.name LIKE '%$search_for%' OR d.domain LIKE '%$search_for%')"; } else { $search_string = ""; }

$sql_domain = "SELECT d.id, d.domain 
			   FROM domains AS d, ssl_certs AS sslc
			   WHERE d.id = sslc.domain_id
			     AND d.active not in ('0', '10')
			     $is_active_string
			     $oid_string
			     $sslpid_string
			     $sslpaid_string
			     $ssltid_string
			     $search_string
			   GROUP BY d.domain
			   ORDER BY d.domain asc"; 
$result_domain = mysql_query($sql_domain,$connection);
echo "<select name=\"did\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Domain - ALL</option>";
while ($row_domain = mysql_fetch_object($result_domain)) { 
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$row_domain->id&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_domain->id == $did) echo " selected"; echo ">"; echo "$row_domain->domain</option>";
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

$sql_active = "SELECT active, count(*) AS total_count
			   FROM ssl_certs
			   WHERE id != '0'
			     $oid_string
			     $did_string
			     $sslpid_string
			     $sslpaid_string
			     $ssltid_string
			   GROUP BY active
			   ORDER BY active asc";
$result_active = mysql_query($sql_active,$connection);
echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "LIVE") echo " selected"; echo ">"; echo "\"Live\" (Active / Pending)</option>";
while ($row_active = mysql_fetch_object($result_active)) {
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_active->active == $is_active) echo " selected"; echo ">"; if ($row_active->active == "0") { echo "Expired"; } elseif ($row_active->active == "1") { echo "Active"; } elseif ($row_active->active == "3") { echo "Pending (Renewal)"; } elseif ($row_active->active == "4") { echo "Pending (Other)"; } elseif ($row_active->active == "5") { echo "Pending (Registration)"; } echo "</option>";
} 
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "ALL") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>

&nbsp;&nbsp;
<?php 
// NUMBER OF SSL CERTS TO DISPLAY
echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">"; 

if ($_SESSION['session_number_of_ssl_certs'] != "10" && $_SESSION['session_number_of_ssl_certs'] != "50" && $_SESSION['session_number_of_ssl_certs'] != "100" && $_SESSION['session_number_of_ssl_certs'] != "500" && $_SESSION['session_number_of_ssl_certs'] != "1000" && $_SESSION['session_number_of_ssl_certs'] != "1000000") {
	echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=" . $_SESSION['session_number_of_ssl_certs'] . "&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == $_SESSION['session_number_of_ssl_certs']) echo " selected"; echo ">"; echo "" . $_SESSION['session_number_of_ssl_certs'] . "</option>";
}

echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=10&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "10") echo " selected"; echo ">"; echo "10</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=50&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "50") echo " selected"; echo ">"; echo "50</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=100&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "100") echo " selected"; echo ">"; echo "100</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=500&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "500") echo " selected"; echo ">"; echo "500</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=1000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000") echo " selected"; echo ">"; echo "1,000</option>";
echo "<option value=\"$PHP_SELF?oid=$oid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&ssltid=$ssltid&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000000") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>
<BR>

<input type="hidden" name="sort_by" value="<?=$sort_by?>">
</td>
<td class="search-table-inside">
<input name="search_for" type="text" id="textfield" value="<?=$search_for?>" size="20">&nbsp;&nbsp;<input type="submit" name="button" id="button" value="Search &raquo;">
<BR><BR>
<input type="hidden" name="oid" value="<?=$oid?>">
<input type="hidden" name="did" value="<?=$did?>">
<input type="hidden" name="sslpid" value="<?=$sslpid?>">
<input type="hidden" name="sslpaid" value="<?=$sslpaid?>">
<input type="hidden" name="ssltid" value="<?=$ssltid?>">
<input type="hidden" name="is_active" value="<?=$is_active?>">
<input type="hidden" name="result_limit" value="<?=$result_limit?>">
</td>
</tr>
</table>
</form></td>
</tr>
</table>
<BR>
<strong>Number of SSL Certs:</strong> <?=number_format($totalrows)?>

<?php if (mysql_num_rows($result) > 0) { ?>
<BR><BR>
<?php if ($totalrows == '0') echo "<BR"; ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
	<td align="left" valign="top">
		<?php echo $navigate[2]; ?>
        &nbsp;[<a href="ssl-certs.php?<?=$_SERVER['QUERY_STRING']?>&export=1">export results</a>]&nbsp;[<a href="system/display-settings.php">display settings</a>]
	</td>
	<td width="280" align="right" valign="top">
		<?php if ($totalrows != '0') { ?>
				<?php echo "&nbsp;&nbsp;(Listing $navigate[1] of " . number_format($totalrows) . ")"; ?>
		<?php } ?>
	</td>
  </tr>
</table>
<BR>
<?php if ($totalrows != '0') { ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['session_display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ed_a") { echo "ed_d"; } else { echo "ed_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Expiry Date</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<?php
        $sql_currency = "SELECT currency
                         FROM currencies
                         WHERE default_currency = '1'";
        $result_currency = mysql_query($sql_currency,$connection);
        while ($row_currency = mysql_fetch_object($result_currency)) { $temp_currency = $row_currency->currency; }
        ?>
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sf_a") { echo "sf_d"; } else { echo "sf_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Fee (<?=$temp_currency?>)</font></a>
	</td>
<?php } ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslc_a") { echo "sslc_d"; } else { echo "sslc_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Host / Label</font></a>
	</td>
<?php if ($_SESSION['session_display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dn_a") { echo "dn_d"; } else { echo "dn_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Domain</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslf_a") { echo "sslf_d"; } else { echo "sslf_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Type</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "o_a") { echo "o_d"; } else { echo "o_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">Owner</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslp_a") { echo "sslp_d"; } else { echo "sslp_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">SSL Provider</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="ssl-certs.php?oid=<?=$oid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslpa_a") { echo "sslpa_d"; } else { echo "sslpa_a"; } ?>&search_for=<?=$search_for?>"><font class="main_table_heading">SSL Account</font></a>
	</td>
<?php } ?>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
<?php if ($_SESSION['session_display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-cert.php?sslcid=<?=$row->id?>"><?=$row->expiry_date?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-provider-fees.php?sslpid=<?=$row->sslp_id?>">
		<?php
		// Currency Conversion & Formatting
		// Input: $temp_input_amount  /  Conversion: $temp_input_conversion (assign empty variable if no conversion is necessary)
		// Output: $temp_output_amount
		$temp_input_amount = $row->renewal_fee;
		$temp_input_conversion = $row->conversion;
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
<?php if ($_SESSION['session_display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/domain.php?did=<?=$row->domain_id?>"><?=$row->domain?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-type.php?ssltid=<?=$row->type_id?>"><?=$row->type?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/owner.php?oid=<?=$row->o_id?>"><?=$row->owner_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-provider.php?sslpid=<?=$row->sslp_id?>"><?=$row->ssl_provider_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/ssl-provider.php?sslpid=<?=$row->sslp_id?>"><?=$row->ssl_provider_name?></a> (<a class="invisiblelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpa_id?>"><?=substr($row->username, 0, 20);?><?php if (strlen($row->username) >= 21) echo "..."; ?></a>)
	</td>
<?php } ?>
</tr>
<?php } ?>
</table>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
	<td align="left" valign="top">
		<?php echo $navigate[2]; ?>
        &nbsp;[<a href="ssl-certs.php?<?=$_SERVER['QUERY_STRING']?>&export=1">export results</a>]&nbsp;[<a href="system/display-settings.php">display settings</a>]
    </td>
	<td width="280" align="right" valign="top"><?php 
		echo "&nbsp;&nbsp;(Listing $navigate[1] of " . number_format($totalrows) . ")";
		?>
	</td>
  </tr>
</table>
<?php } ?>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>