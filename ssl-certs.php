<?php
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
// You should have received a copy of the GNU General Public License along with Domain Managesslp. If not, please 
// see http://www.gnu.org/licenses/
?>
<?php
session_start();
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
$page_title = "SSL Certificates";
$software_section = "ssl-certs";

// Form Variables
$cid = $_REQUEST['cid'];
$did = $_REQUEST['did'];
$sslpid = $_REQUEST['sslpid'];
$sslpaid = $_REQUEST['sslpaid'];
$typeid = $_REQUEST['typeid'];
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($search_for == "Search Term") $search_for = "";
if ($result_limit == "") $result_limit = "50";
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
?>
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
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and c.id = '$cid' "; } else { $cid_string = ""; }
if ($did != "") { $did_string = " and d.id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " and sslp.id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($typeid != "") { $typeid_string = " and sslc.type_id = '$typeid' "; } else { $typeid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") {
	$sort_by_string = " order by sslc.expiry_date asc, sslc.name asc ";
} elseif ($sort_by == "ed_d") {
	$sort_by_string = " order by sslc.expiry_date desc, sslc.name asc ";
} elseif ($sort_by == "dn_a") {
	$sort_by_string = " order by d.domain asc ";
} elseif ($sort_by == "dn_d") {
	$sort_by_string = " order by d.domain desc ";
} elseif ($sort_by == "sslc_a") {
	$sort_by_string = " order by sslc.name asc ";
} elseif ($sort_by == "sslc_d") {
	$sort_by_string = " order by sslc.name desc ";
} elseif ($sort_by == "sslt_a") {
	$sort_by_string = " order by sslct.type asc, sslc.name asc ";
} elseif ($sort_by == "sslt_d") {
	$sort_by_string = " order by sslct.type desc, sslc.name asc ";
} elseif ($sort_by == "ca_a") {
	$sort_by_string = " order by c.name asc, sslc.name asc ";
} elseif ($sort_by == "ca_d") {
	$sort_by_string = " order by c.name desc, sslc.name asc ";
} elseif ($sort_by == "sslp_a") {
	$sort_by_string = " order by sslp.name asc, sslc.name asc ";
} elseif ($sort_by == "sslp_d") {
	$sort_by_string = " order by sslp.name desc, sslc.name asc ";
}

$sql = "select sslc.id, sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id as sslpa_id, sslpa.username, sslp.id as sslp_id, sslp.name as ssl_provider_name, c.id as c_id, c.name as company_name, f.renewal_fee, cc.conversion, d.domain, sslct.type
		from ssl_certs as sslc, ssl_accounts as sslpa, ssl_providers as sslp, companies as c, ssl_fees as f, currencies as cc, domains as d, ssl_cert_types as sslct
		where sslc.account_id = sslpa.id
		and sslpa.ssl_provider_id = sslp.id
		and sslpa.company_id = c.id
		and sslc.fee_id = f.id
		and f.currency_id = cc.id
		and sslc.domain_id = d.id
		and sslc.type_id = sslct.id
		$is_active_string
		and sslpa.active = '1'
		and sslp.active = '1'
		and c.active = '1'
		and cc.active = '1'
		$cid_string
		$did_string
		$sslpid_string
		$sslpaid_string
		$typeid_string
		$search_string
		$sort_by_string
";	

$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "&cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection);
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td width="100%" class="search-table"><BR>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside" width="640">

&nbsp;&nbsp;
<?php 
// COMPANY
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($did != "") { $did_string = " and sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " and sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($typeid != "") { $typeid_string = " and sslc.type_id = '$typeid' "; } else { $typeid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

$sql_company = "select c.id, c.name 
				from companies as c, ssl_certs as sslc, domains as d
				where c.id = sslc.company_id
				and c.id = d.company_id
				and c.active = '1'
				$is_active_string
				$did_string
				$sslpid_string
				$sslpaid_string
				$typeid_string
				$search_string
				group by c.name
				order by c.name asc";
$result_company = mysql_query($sql_company,$connection);
echo "<select name=\"cid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?cid=&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Company - ALL</option>";
while ($row_company = mysql_fetch_object($result_company)) { 
echo "<option value=\"$PHP_SELF?cid=$row_company->id&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_company->id == $cid) echo " selected"; echo ">"; echo "$row_company->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// SSL PROVIDER
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and sslc.company_id = '$cid' "; } else { $cid_string = ""; }
if ($did != "") { $did_string = " and sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($typeid != "") { $typeid_string = " and sslc.type_id = '$typeid' "; } else { $typeid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

$sql_ssl_provider = "select sslp.id, sslp.name 
				  from ssl_providers as sslp, ssl_certs as sslc, domains as d
				  where sslp.id = sslc.ssl_provider_id
				  and sslc.domain_id = d.id
				  and sslp.active = '1' 
				  $is_active_string
				  $cid_string
				  $did_string
				  $sslpaid_string
				  $typeid_string
				  $search_string
				  group by sslp.name
				  order by sslp.name asc";
$result_ssl_provider = mysql_query($sql_ssl_provider,$connection);
echo "<select name=\"sslpid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">SSL Providers - ALL</option>";
while ($row_ssl_provider = mysql_fetch_object($result_ssl_provider)) { 
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$row_ssl_provider->id&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_ssl_provider->id == $sslpid) echo " selected"; echo ">"; echo "$row_ssl_provider->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// SSL PROVIDER ACCOUNT
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and sslc.company_id = '$cid' "; } else { $cid_string = ""; }
if ($did != "") { $did_string = " and sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " and sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($typeid != "") { $typeid_string = " and sslc.type_id = '$typeid' "; } else { $typeid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

$sql_account = "select sslpa.id as sslpa_id, sslpa.username, sslp.name as sslp_name, c.name as c_name
				  from ssl_accounts as sslpa, ssl_providers as sslp, companies as c, ssl_certs as sslc, domains as d
				  where sslpa.ssl_provider_id = sslp.id
				  and sslpa.company_id = c.id
				  and sslpa.id = sslc.account_id
				  and sslc.domain_id = d.id
				  and sslpa.active = '1'
				  and sslp.active = '1'
				  and c.active = '1'
				  $is_active_string
				  $cid_string
				  $did_string
				  $sslpid_string
				  $typeid_string
				  $search_string
				  group by sslp.name, c.name, sslpa.username
				  order by sslp.name asc, c.name asc, sslpa.username asc";
$result_account = mysql_query($sql_account,$connection);
echo "<select name=\"sslpaid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?sslpaid=&sort_by=$sort_by&cid=$cid&did=$did&sslpid=$sslpid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&search_for=$search_for\">SSL Provider Account - ALL</option>";
while ($row_account = mysql_fetch_object($result_account)) { 
echo "<option value=\"$PHP_SELF?sslpaid=$row_account->sslpa_id&sort_by=$sort_by&cid=$cid&did=$did&sslpid=$sslpid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&search_for=$search_for\""; if ($row_account->sslpa_id == $sslpaid) echo " selected"; echo ">"; echo "$row_account->sslp_name :: $row_account->c_name ($row_account->username)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// DOMAIN
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and sslc.company_id = '$cid' "; } else { $cid_string = ""; }
if ($sslpid != "") { $sslpid_string = " and sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($typeid != "") { $typeid_string = " and sslc.type_id = '$typeid' "; } else { $typeid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

$sql_domain = "select d.id, d.domain 
				from domains as d, ssl_certs as sslc
				where d.id = sslc.domain_id
				and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')
				$is_active_string
				$cid_string
				$sslpid_string
				$sslpaid_string
				$typeid_string
				$search_string
				group by d.domain
				order by d.domain asc"; 
$result_domain = mysql_query($sql_domain,$connection);
echo "<select name=\"did\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?did=&cid=$cid&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Domain - ALL</option>";
while ($row_domain = mysql_fetch_object($result_domain)) { 
echo "<option value=\"$PHP_SELF?did=$row_domain->id&cid=$cid&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_domain->id == $did) echo " selected"; echo ">"; echo "$row_domain->domain</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// TYPE
if ($is_active == "0") { $is_active_string = " and sslc.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and sslc.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and sslc.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and sslc.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and sslc.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and sslc.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and sslc.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and sslc.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and sslc.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and sslc.active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and sslc.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and sslc.company_id = '$cid' "; } else { $cid_string = ""; }
if ($did != "") { $did_string = " and sslc.domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslpid_string = " and sslc.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and sslc.account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($search_for != "") { $search_string = " and (sslc.name like '%$search_for%' or d.domain like '%$search_for%')"; } else { $search_string = ""; }

$sql_type = "select sslc.type_id, sslct.type
			from ssl_certs as sslc, domains as d, ssl_cert_types as sslct
			where sslc.domain_id = d.id
			and sslc.type_id = sslct.id
			$is_active_string
			$cid_string
			$did_string
			$sslpid_string
			$sslpaid_string
			$search_string
			group by sslct.type
			order by sslct.type asc";
$result_type = mysql_query($sql_type,$connection);
echo "<select name=\"typeid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Type - ALL</option>";
while ($row_type = mysql_fetch_object($result_type)) { 
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$row_type->type_id&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_type->type_id == $typeid) echo " selected"; echo ">"; echo "$row_type->type</option>";
} 
echo "</select>";
?>

&nbsp;&nbsp;
<?php 
// STATUS
if ($is_active == "0") { $is_active_string = " and active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and active = '9' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 

if ($cid != "") { $cid_string = " and company_id = '$cid' "; } else { $cid_string = ""; }
if ($did != "") { $did_string = " and domain_id = '$did' "; } else { $did_string = ""; }
if ($sslpid != "") { $sslp_string = " and ssl_provider_id = '$sslp' "; } else { $sslp_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " and account_id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($typeid != "") { $typeid_string = " and typeid = '$typeid' "; } else { $typeid_string = ""; }

$sql_active = "select active, count(*) as total_count
			from ssl_certs
			where id != '0'
			$cid_string
			$did_string
			$sslpid_string
			$sslpaid_string
			$typeid_string
			group by active
			order by active asc";
$result_active = mysql_query($sql_active,$connection);
echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "LIVE") echo " selected"; echo ">"; echo "\"Live\" (Active / Pending)</option>";

while ($row_active = mysql_fetch_object($result_active)) { 
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_active->active == $is_active) echo " selected"; echo ">"; if ($row_active->active == "0") { echo "Expired"; } elseif ($row_active->active == "1") { echo "Active"; } elseif ($row_active->active == "2") { echo "In Transfer"; } elseif ($row_active->active == "3") { echo "Pending (Renewal)"; } elseif ($row_active->active == "4") { echo "Pending (Other)"; } elseif ($row_active->active == "5") { echo "Pending (Registration)"; } echo "</option>";
} 

echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "ALL") echo " selected"; echo ">"; echo "ALL</option>";

echo "</select>";

?>

&nbsp;&nbsp;
<?php 
// NUMBER OF SSL CERTS TO DISPLAY
echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">"; 

echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=10&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "10") echo " selected"; echo ">"; echo "10</option>";
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=50&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "50") echo " selected"; echo ">"; echo "50</option>";
echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=100&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "100") echo " selected"; echo ">"; echo "100</option>";

echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=500&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "500") echo " selected"; echo ">"; echo "500</option>";

echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=1000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000") echo " selected"; echo ">"; echo "1,000</option>";

echo "<option value=\"$PHP_SELF?cid=$cid&did=$did&sslpid=$sslpid&sslpaid=$sslpaid&typeid=$typeid&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000000") echo " selected"; echo ">"; echo "ALL</option>";

echo "</select>";
?>
<BR>

<input type="hidden" name="sort_by" value="<?=$sort_by?>">
</td>
<td class="search-table-inside">
<input name="search_for" type="text" id="textfield" value="<?=stripslashes($search_for)?>" size="20">&nbsp;&nbsp;<input type="submit" name="button" id="button" value="Search Results &raquo;">
<BR><BR>
<input type="hidden" name="cid" value="<?=$cid?>">
<input type="hidden" name="did" value="<?=$did?>">
<input type="hidden" name="sslpid" value="<?=$sslpid?>">
<input type="hidden" name="sslpaid" value="<?=$sslpaid?>">
<input type="hidden" name="typeid" value="<?=$typeid?>">
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
	</td>
	<td width="280" align="right" valign="top">
		<?php if ($totalrows != '0') { ?>
		<?php 
		echo "&nbsp;&nbsp;(Listing $navigate[1] of " . number_format($totalrows) . ")";
		?>
		<?php } ?>
	</td>
  </tr>
</table>
<BR>
<?php if ($totalrows != '0') { ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="30">
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ed_a") { echo "ed_d"; } else { echo "ed_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Expiry Date</font></a>
	</td>

	<td width="20" align="center">&nbsp;
		
	</td>
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslc_a") { echo "sslc_d"; } else { echo "sslc_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Host / Label</font></a>
	</td>
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dn_a") { echo "dn_d"; } else { echo "dn_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Domain</font></a>
	</td>
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslt_a") { echo "sslt_d"; } else { echo "sslt_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Type</font></a>
	</td>
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ca_a") { echo "ca_d"; } else { echo "ca_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Company/Account</font></a>
	</td>
	<td>
		<a href="ssl-certs.php?cid=<?=$cid?>&did=<?=$did?>&sslpid=<?=$sslpid?>&sslpaid=<?=$sslpaid?>&typeid=<?=$typeid?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "sslp_a") { echo "sslp_d"; } else { echo "sslp_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">SSL Provider (Username)</font></a>
	</td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
	<td valign="top">
		<?=$row->expiry_date?>
	</td>
	<td valign="top" align="right">
		  <?php if ($row->active == "0") { 
					echo "<a title=\"Inactive SSL Certificate\"><strong><font color=\"#DD0000\">x</font></strong></a>"; 
				} elseif ($row->active == "2") { 
					echo "<a title=\"In Transfer\"><strong><font color=\"#DD0000\">T</font></strong></a>"; 
				} elseif ($row->active == "3") { 
					echo "<a title=\"Pending (Renewal)\"><strong><font color=\"#DD0000\">PRn</font></strong></a>"; 
				} elseif ($row->active == "4") { 
					echo "<a title=\"Pending (Other)\"><strong><font color=\"#DD0000\">PO</font></strong></a>"; 
				} elseif ($row->active == "5") { 
					echo "<a title=\"Pending (Registration)\"><strong><font color=\"#DD0000\">PRg</font></strong></a>"; 
				} else { 
					echo "&nbsp;"; 
				} 
			?>
	&nbsp;</td>
	<td valign="top">
		<a class="subtlelink" href="edit/ssl-cert.php?sslcid=<?=$row->id?>"><?=$row->name?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/domain.php?did=<?=$row->domain_id?>"><?=$row->domain?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/ssl-cert.php?sslcid=<?=$row->id?>"><?=$row->type?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/company.php?cid=<?=$row->c_id?>"><?=$row->company_name?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/ssl-provider.php?sslpid=<?=$row->sslp_id?>"><?=$row->ssl_provider_name?></a> (<a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpa_id?>"><?=substr($row->username, 0, 10);?><?php if (strlen($row->username) >= 11) echo "..."; ?></a>)
	</td>
</tr>
<?php } ?>
</table>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
	<td align="left" valign="top"><?php echo $navigate[2]; ?> </td>
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