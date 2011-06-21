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
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<?php
session_start();
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
$page_title = "Domains";
$software_section = "domains";

// Form Variables
$pcid = $_REQUEST['pcid'];
$cid = $_REQUEST['cid'];
$dnsid = $_REQUEST['dnsid'];
$rid = $_REQUEST['rid'];
$raid = $_REQUEST['raid'];
$segid = $_REQUEST['segid'];
$tld = $_REQUEST['tld'];
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];
$quick_search = $_REQUEST['quick_search'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($search_for == "Search Term") $search_for = "";
if ($result_limit == "") $result_limit = "50";
if ($is_active == "") $is_active = "LIVE";

if ($tld == "0") $tld = "";

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
						.$queryStr."'>"
						.$larrow."</a>\n";
			}

			for ($i=$numBegin;$i<=$cycle;$i++) {
				if ($numSoFar == $numLimit+1) {
					$piece = "<a href='?numBegin=".$i
						."&num=".$i
						."&begin=".$start
						.$queryStr."'>"
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
<body onLoad="document.forms[0].elements[10].focus()";>
<?php include("_includes/header.inc.php"); ?>
<?php
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($segid != "") { 
	$seg_sql = "select segment from segments where id = '$segid'";
	$seg_result = mysql_query($seg_sql,$connection);
	while ($seg_row = mysql_fetch_object($seg_result)) { $temp_segment = $seg_row->segment; }
	$segid_string = " and d.domain in ($temp_segment)"; 
} else { 
	$segid_string = ""; 
}
if ($quick_search != "") {

	$quick_search_dropdown = $quick_search;
	$quick_search_dropdown = preg_replace("/\r\n/", "z8l2q9", $quick_search_dropdown);
	$quick_search_dropdown = preg_replace("/', '/", "z8l2q9", $quick_search_dropdown);
	$quick_search_dropdown = preg_replace("/','/", "z8l2q9", $quick_search_dropdown);
	$quick_search_dropdown = preg_replace("/'/", "", $quick_search_dropdown);

	$lines = explode("\r\n", $quick_search);
	$quick_search_number_of_domains = count($lines);

	$quick_search_new_segment_formatted = "'" . $quick_search . "'";
	$quick_search_new_segment_formatted = preg_replace("/\r\n/", "','", $quick_search_new_segment_formatted);
	$quick_search_new_segment_formatted = str_replace (" ", "", $quick_search_new_segment_formatted);
	$quick_search_new_segment_formatted = trim($quick_search_new_segment_formatted);
	$quick_search = $quick_search_new_segment_formatted;

	$segid_string .= " and d.domain in ($quick_search)"; 
}
	
	
if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and c.id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and dns.id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and r.id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%' "; } else { $search_string = ""; }

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") {
	$sort_by_string = " order by d.expiry_date asc, d.domain asc ";
} elseif ($sort_by == "ed_d") {
	$sort_by_string = " order by d.expiry_date desc, d.domain asc ";
} elseif ($sort_by == "pc_a") {
	$sort_by_string = " order by cat.name asc ";
} elseif ($sort_by == "pc_d") {
	$sort_by_string = " order by cat.name desc ";
} elseif ($sort_by == "dn_a") {
	$sort_by_string = " order by d.domain asc ";
} elseif ($sort_by == "dn_d") {
	$sort_by_string = " order by d.domain desc ";
} elseif ($sort_by == "ca_a") {
	$sort_by_string = " order by c.name asc, d.domain asc ";
} elseif ($sort_by == "ca_d") {
	$sort_by_string = " order by c.name desc, d.domain asc ";
} elseif ($sort_by == "r_a") {
	$sort_by_string = " order by r.name asc, d.domain asc ";
} elseif ($sort_by == "r_d") {
	$sort_by_string = " order by r.name desc, d.domain asc ";
}

$sql = "select d.id, d.domain, d.tld, d.expiry_date, d.notes, d.privacy, d.active, ra.id as ra_id, ra.username, r.id as r_id, r.name as registrar_name, c.id as c_id, c.name as company_name, cat.id as pcid, cat.name as category_name, f.renewal_fee, cc.conversion
		from domains as d, registrar_accounts as ra, registrars as r, companies as c, categories as cat, fees as f, currencies as cc, dns as dns
		where d.account_id = ra.id
		and ra.registrar_id = r.id
		and ra.company_id = c.id
		and d.cat_id = cat.id
		and d.fee_id = f.id
		and d.dns_id = dns.id
		and f.currency_id = cc.id
		$is_active_string
		and ra.active = '1'
		and r.active = '1'
		and c.active = '1'
		and cat.active = '1'
		and cc.active = '1'
		$segid_string
		$pcid_string
		$cid_string
		$dnsid_string
		$rid_string
		$raid_string
		$tld_string
		$search_string
		$sort_by_string
";	

$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "&pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection);

if ($segid != "") {
	$sql_segment = "select number_of_domains
					from segments
					where id = '$segid'";
	$result_segment = mysql_query($sql_segment,$connection);
	while ($row_segment = mysql_fetch_object($result_segment)) { $number_of_domains = $row_segment->number_of_domains; }
}
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
// CATEGORY
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and d.company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_category = "select c.id, c.name
				  from categories as c, domains as d
				  where c.id = d.cat_id
				  $is_active_string
				  $pcid_string
				  $cid_string
				  $dnsid_string
				  $raid_string
				  $tld_string
				  $search_string
				  and c.active = '1'
				  group by c.name
				  order by c.name asc";
$result_category = mysql_query($sql_category,$connection);
echo "<select name=\"pcid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Category - ALL</option>";
while ($row_category = mysql_fetch_object($result_category)) { 
echo "<option value=\"$PHP_SELF?pcid=$row_category->id&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_category->id == $pcid) echo " selected"; echo ">"; echo "$row_category->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// COMPANY
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_company = "select c.id, c.name 
				from companies as c, domains as d
				where c.id = d.company_id
				and c.active = '1'
				$is_active_string
				$pcid_string
				$dnsid_string
				$rid_string
				$raid_string
				$tld_string
				$search_string
				group by c.name
				order by c.name asc";
$result_company = mysql_query($sql_company,$connection);
echo "<select name=\"cid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Company - ALL</option>";
while ($row_company = mysql_fetch_object($result_company)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$row_company->id&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_company->id == $cid) echo " selected"; echo ">"; echo "$row_company->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// DNS
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and d.company_id = '$cid' "; } else { $cid_string = ""; }
if ($rid != "") { $rid_string = " and d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_dns = "select dns.id, dns.name 
				from dns as dns, domains as d
				where dns.id = d.company_id
				and dns.active = '1'
				$is_active_string
				$pcid_string
				$cid_string
				$rid_string
				$raid_string
				$tld_string
				$search_string
				group by dns.name
				order by dns.name asc";
$result_dns = mysql_query($sql_dns,$connection);
echo "<select name=\"dnsid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">DNS Profile - ALL</option>";
while ($row_dns = mysql_fetch_object($result_dns)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$row_dns->id&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_dns->id == $dnsid) echo " selected"; echo ">"; echo "$row_dns->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// REGISTRAR
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and d.company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_registrar = "select r.id, r.name 
				  from registrars as r, domains as d
				  where r.id = d.registrar_id
				  and r.active = '1' 
				  $is_active_string
				  $pcid_string
				  $cid_string
				  $dnsid_string
				  $raid_string
				  $tld_string
				  $search_string
				  group by r.name
				  order by r.name asc";
$result_registrar = mysql_query($sql_registrar,$connection);
echo "<select name=\"rid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">Registrar - ALL</option>";
while ($row_registrar = mysql_fetch_object($result_registrar)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$row_registrar->id&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_registrar->id == $rid) echo " selected"; echo ">"; echo "$row_registrar->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// REGISTRAR ACCOUNT
if ($is_active == "0") { $is_active_string = " and d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " and d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " and d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " and d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " and d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " and d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " and d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " and d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " and d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " and d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " and d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and d.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and d.active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($cid != "") { $cid_string = " and d.company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }
if ($search_for != "") { $search_string = " and d.domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_account = "select ra.id as ra_id, ra.username, r.name as r_name, c.name as c_name
				  from registrar_accounts as ra, registrars as r, companies as c, domains as d
				  where ra.registrar_id = r.id
				  and ra.company_id = c.id
				  and ra.id = d.account_id
				  and ra.active = '1'
				  and r.active = '1'
				  and c.active = '1'
				  $is_active_string
				  $cid_string
				  $dnsid_string
				  $rid_string
				  $tld_string
				  $search_string
				  group by r.name, c.name, ra.username
				  order by r.name asc, c.name asc, ra.username asc";
$result_account = mysql_query($sql_account,$connection);
echo "<select name=\"raid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?segid=$segid&raid=&sort_by=$sort_by&cid=$cid&dnsid=$dnsid&rid=$rid&tld=$tld&is_active=$is_active&result_limit=$result_limit&search_for=$search_for\">Registrar Account - ALL</option>";
while ($row_account = mysql_fetch_object($result_account)) { 
echo "<option value=\"$PHP_SELF?segid=$segid&raid=$row_account->ra_id&sort_by=$sort_by&cid=$cid&dnsid=$dnsid&rid=$rid&tld=$tld&is_active=$is_active&result_limit=$result_limit&search_for=$search_for\""; if ($row_account->ra_id == $raid) echo " selected"; echo ">"; echo "$row_account->r_name :: $row_account->c_name ($row_account->username)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// SEGMENT
if ($pcid != "") { $pcid_string = " and d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and d.company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and d.tld = '$tld' "; } else { $tld_string = ""; }

$sql_segment = "select id, name
				from segments
				where active = '1'
				order by name asc";
$result_segment = mysql_query($sql_segment,$connection);

echo "<select name=\"segid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&tld=$tld&segid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for&quick_search_dropdown=$quick_search_dropdown\">Segment - ALL DOMAINS</option>";

while ($row_segment = mysql_fetch_object($result_segment)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$row_segment->id&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for&quick_search_dropdown=$quick_search_dropdown\""; if ($row_segment->id == $segid) echo " selected"; echo ">"; echo "$row_segment->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// TLD
if ($is_active == "0") { $is_active_string = " where active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " where active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " where active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " where active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " where active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " where active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " where active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " where active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " where active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " where active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " where active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " where active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " where active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and account_id = '$raid' "; } else { $raid_string = ""; }
if ($search_for != "") { $search_string = " and domain like '%$search_for%'"; } else { $search_string = ""; }

$sql_tld = "select tld, count(*) as total_tld_count
			from domains
			$is_active_string
			$pcid_string
			$cid_string
			$dnsid_string
			$rid_string
			$raid_string
			$search_string
			group by tld
			order by tld asc";
$result_tld = mysql_query($sql_tld,$connection);
echo "<select name=\"tld\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\">TLD - ALL</option>";
while ($row_tld = mysql_fetch_object($result_tld)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$row_tld->tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_tld->tld == $tld) echo " selected"; echo ">"; echo ".$row_tld->tld</option>";
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
elseif ($is_active == "10") { $is_active_string = " and active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " and active in ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " and active in ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " and cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($cid != "") { $cid_string = " and company_id = '$cid' "; } else { $cid_string = ""; }
if ($dnsid != "") { $dnsid_string = " and dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($rid != "") { $rid_string = " and registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " and tld = '$tld' "; } else { $tld_string = ""; }

$sql_active = "select active, count(*) as total_count
			from domains
			where id != '0'
			$pcid_string
			$cid_string
			$dnsid_string
			$rid_string
			$raid_string
			$tld_string
			group by active
			order by active asc";
$result_active = mysql_query($sql_active,$connection);
echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "LIVE") echo " selected"; echo ">"; echo "\"Live\" (Active / Transfer / Pending)</option>";

while ($row_active = mysql_fetch_object($result_active)) { 
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($row_active->active == $is_active) echo " selected"; echo ">"; if ($row_active->active == "0") { echo "Expired"; } elseif ($row_active->active == "1") { echo "Active"; } elseif ($row_active->active == "2") { echo "In Transfer"; } elseif ($row_active->active == "3") { echo "Pending (Renewal)"; } elseif ($row_active->active == "4") { echo "Pending (Other)"; } elseif ($row_active->active == "5") { echo "Pending (Registration)"; } echo "</option>";
} 

echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&search_for=$search_for\""; if ($is_active == "ALL") echo " selected"; echo ">"; echo "ALL</option>";

echo "</select>";

?>

&nbsp;&nbsp;
<?php 
// NUMBER OF DOMAINS TO DISPLAY
echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">"; 

echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=10&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "10") echo " selected"; echo ">"; echo "10</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=50&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "50") echo " selected"; echo ">"; echo "50</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=100&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "100") echo " selected"; echo ">"; echo "100</option>";

echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=500&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "500") echo " selected"; echo ">"; echo "500</option>";

echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=1000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000") echo " selected"; echo ">"; echo "1,000</option>";

echo "<option value=\"$PHP_SELF?pcid=$pcid&cid=$cid&dnsid=$dnsid&rid=$rid&raid=$raid&segid=$segid&tld=$tld&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&search_for=$search_for\""; if ($result_limit == "1000000") echo " selected"; echo ">"; echo "ALL</option>";

echo "</select>";
?>
<BR>
<input type="hidden" name="sort_by" value="<?=$sort_by?>">
</td>
<td class="search-table-inside">
<input name="search_for" type="text" value="<?=stripslashes($search_for)?>" size="20">&nbsp;&nbsp;<input type="submit" name="button" value="Search Results &raquo;">
<BR><BR>
<?php
$quick_search = preg_replace("/', '/", "\r\n", $quick_search);
$quick_search = preg_replace("/','/", "\r\n", $quick_search);
$quick_search = preg_replace("/'/", "", $quick_search);
?>
<textarea name="quick_search" cols="40" rows="11"><?=stripslashes($quick_search)?>
</textarea>&nbsp;&nbsp;
<BR>
<BR>
<input type="hidden" name="segid" value="<?=$segid?>">
<input type="hidden" name="pcid" value="<?=$pcid?>">
<input type="hidden" name="cid" value="<?=$cid?>">
<input type="hidden" name="dnsid" value="<?=$dnsid?>">
<input type="hidden" name="rid" value="<?=$rid?>">
<input type="hidden" name="raid" value="<?=$raid?>">
<input type="hidden" name="tld" value="<?=$tld?>">
<input type="hidden" name="is_active" value="<?=$is_active?>">
<input type="hidden" name="result_limit" value="<?=$result_limit?>">
</td>
</tr>
</table>
</form></td>
</tr>
</table>
<BR>
<?php if ($segid != "") { ?>
<strong>Domains in Segment:</strong> <?=$number_of_domains?><BR><BR>
<strong>Number of Matching Domains:</strong> <?=number_format($totalrows)?>
<?php } else { ?>
<strong>Number of Domains:</strong> <?=number_format($totalrows)?>
<?php } ?>

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
		<a href="domains.php?pcid=<?=$pcid?>&cid=<?=$cid?>&dnsid=<?=$dnsid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ed_a") { echo "ed_d"; } else { echo "ed_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Expiry Date</font></a>
	</td>

	<td width="20" align="center">&nbsp;
		
	</td>
	<td>
		<a href="domains.php?pcid=<?=$pcid?>&cid=<?=$cid?>&dnsid=<?=$dnsid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dn_a") { echo "dn_d"; } else { echo "dn_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Domain Name</font></a>
	</td>
	<td>
		<a href="domains.php?pcid=<?=$pcid?>&cid=<?=$cid?>&dnsid=<?=$dnsid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "pc_a") { echo "pc_d"; } else { echo "pc_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Category</font></a>
	</td>
	<td>
		<a href="domains.php?pcid=<?=$pcid?>&cid=<?=$cid?>&dnsid=<?=$dnsid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ca_a") { echo "ca_d"; } else { echo "ca_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Company/Account</font></a>
	</td>
	<td>
		<a href="domains.php?pcid=<?=$pcid?>&cid=<?=$cid?>&dnsid=<?=$dnsid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "r_a") { echo "r_d"; } else { echo "r_a"; } ?>&search_for=<?=$search_for?>"><font class="subheadline">Registrar (Username)</font></a>
	</td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
	<td valign="top">
		<?=$row->expiry_date?>
	</td>
	<td valign="top" align="right">
		  <?php if ($row->active == "0") { 
					echo "<a title=\"Inactive Domain\"><strong><font color=\"#DD0000\">x</font></strong></a>"; 
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
		<a class="subtlelink" href="edit/domain.php?did=<?=$row->id?>"><?=$row->domain?></a><?php if ($row->privacy == "1") { echo "&nbsp;<a title=\"Private WHOIS Registration\"><strong><font color=\"#DD0000\">PRV</font></strong></a>&nbsp;"; } else { echo "&nbsp;"; } ?>[<a class="subtlelink" target="_blank" href="http://<?=$row->domain?>">v</a>] [<a target="_blank" class="subtlelink" href="https://whois.greg.ca/<?=$row->domain?>">w</a>]
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->pcid?>"><?=$row->category_name?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/company.php?cid=<?=$row->c_id?>"><?=$row->company_name?></a>
	</td>
	<td valign="top">
		<a class="subtlelink" href="edit/registrar.php?rid=<?=$row->r_id?>"><?=$row->registrar_name?></a> (<a class="subtlelink" href="edit/account.php?raid=<?=$row->ra_id?>"><?=substr($row->username, 0, 10);?><?php if (strlen($row->username) >= 11) echo "..."; ?></a>)
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