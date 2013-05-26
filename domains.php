<?php
// /domains.php
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
include("_includes/system/functions/check-domain-format.inc.php");

$page_title = "Domains";
$software_section = "domains";

// Form Variables
$export = $_GET['export'];
$pcid = $_REQUEST['pcid'];
$oid = $_REQUEST['oid'];
$dnsid = $_REQUEST['dnsid'];
$ipid = $_REQUEST['ipid'];
$whid = $_REQUEST['whid'];
$rid = $_REQUEST['rid'];
$raid = $_REQUEST['raid'];
$tld = $_REQUEST['tld'];
$segid = $_REQUEST['segid'];
$is_active = $_REQUEST['is_active'];
$result_limit = $_REQUEST['result_limit'];
$sort_by = $_REQUEST['sort_by'];
$search_for = $_REQUEST['search_for'];
$quick_search = $_REQUEST['quick_search'];
$from_dropdown = $_REQUEST['from_dropdown'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') $from_dropdown = 0;

if ($export != "1") {

	if ($from_dropdown != "1") {

		if ($search_for != "") { $_SESSION['search_for'] = $search_for; } else { $_SESSION['search_for'] = ""; }
		if ($quick_search != "") { $_SESSION['quick_search'] = $quick_search; } else { $_SESSION['quick_search'] = ""; }

	}

}

if ($_SESSION['search_for'] == "Search Term") $_SESSION['search_for'] = "";
if ($result_limit == "") $result_limit = $_SESSION['number_of_domains'];

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
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')"; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10')"; } 

if ($segid != "") { 

	$seg_sql = "SELECT segment 
				FROM segments 
				WHERE id = '$segid'";
	$seg_result = mysql_query($seg_sql,$connection);
	while ($seg_row = mysql_fetch_object($seg_result)) { $temp_segment = $seg_row->segment; }
	$segid_string = " AND d.domain IN ($temp_segment)"; 

} else { 

	$segid_string = "";
}

if ($_SESSION['quick_search'] != "") {

	$temp_input_string = $_SESSION['quick_search'];
	include("_includes/system/regex-bulk-form-strip-whitespace.inc.php");
	$_SESSION['quick_search'] = $temp_output_string;

	$lines = explode("\r\n", $_SESSION['quick_search']);
	$invalid_domain_count = 0;
	$invalid_domains_to_display = 5;
	
	while (list($key, $new_domain) = each($lines)) {

		if (!CheckDomainFormat($new_domain)) {
			if ($invalid_domain_count < $invalid_domains_to_display) $temp_result_message .= "Line " . number_format($key + 1) . " contains an invalid domain<BR>";
			$invalid_domains = 1;
			$invalid_domain_count++;
		}

	}

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

		$lines = explode("\r\n", $_SESSION['quick_search']);
		$quick_search_number_of_domains = count($lines);
		
		$quick_search_new_segment_formatted = "'" . $_SESSION['quick_search'] . "'";
		$quick_search_new_segment_formatted = preg_replace("/\r\n/", "','", $quick_search_new_segment_formatted);
		$quick_search_new_segment_formatted = str_replace (" ", "", $quick_search_new_segment_formatted);
		$quick_search_new_segment_formatted = trim($quick_search_new_segment_formatted);
		$_SESSION['quick_search'] = $quick_search_new_segment_formatted;

		$segid_string .= " AND d.domain IN (" . $_SESSION['quick_search'] . ")";

	}

}

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND o.id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND dns.id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND ip.id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND h.id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND r.id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%' "; } else { $search_string = ""; }

if ($sort_by == "") $sort_by = "ed_a";

if ($sort_by == "ed_a") { $sort_by_string = " ORDER BY d.expiry_date asc, d.domain asc "; } 
elseif ($sort_by == "ed_d") { $sort_by_string = " ORDER BY d.expiry_date desc, d.domain asc "; } 
elseif ($sort_by == "pc_a") { $sort_by_string = " ORDER BY cat.name asc "; } 
elseif ($sort_by == "pc_d") { $sort_by_string = " ORDER BY cat.name desc "; } 
elseif ($sort_by == "dn_a") { $sort_by_string = " ORDER BY d.domain asc "; } 
elseif ($sort_by == "dn_d") { $sort_by_string = " ORDER BY d.domain desc "; } 
elseif ($sort_by == "df_a") { $sort_by_string = " ORDER BY f.renewal_fee asc "; } 
elseif ($sort_by == "df_d") { $sort_by_string = " ORDER BY f.renewal_fee desc "; } 
elseif ($sort_by == "dns_a") { $sort_by_string = " ORDER BY dns.name asc "; } 
elseif ($sort_by == "dns_d") { $sort_by_string = " ORDER BY dns.name desc "; } 
elseif ($sort_by == "tld_a") { $sort_by_string = " ORDER BY d.tld asc "; } 
elseif ($sort_by == "tld_d") { $sort_by_string = " ORDER BY d.tld desc "; } 
elseif ($sort_by == "ip_a") { $sort_by_string = " ORDER BY ip.name asc, ip.ip asc"; } 
elseif ($sort_by == "ip_d") { $sort_by_string = " ORDER BY ip.name desc, ip.ip desc"; } 
elseif ($sort_by == "wh_a") { $sort_by_string = " ORDER BY h.name asc"; } 
elseif ($sort_by == "wh_d") { $sort_by_string = " ORDER BY h.name desc"; } 
elseif ($sort_by == "o_a") { $sort_by_string = " ORDER BY o.name asc, d.domain asc "; } 
elseif ($sort_by == "o_d") { $sort_by_string = " ORDER BY o.name desc, d.domain asc "; } 
elseif ($sort_by == "r_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc "; } 
elseif ($sort_by == "r_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc "; }
elseif ($sort_by == "ra_a") { $sort_by_string = " ORDER BY r.name asc, d.domain asc "; } 
elseif ($sort_by == "ra_d") { $sort_by_string = " ORDER BY r.name desc, d.domain asc "; }

$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, d.insert_time, d.update_time, ra.id AS ra_id, ra.username, r.id AS r_id, r.name AS registrar_name, o.id AS o_id, o.name AS owner_name, cat.id AS pcid, cat.name AS category_name, cat.stakeholder, f.initial_fee, f.renewal_fee, c.currency, cc.conversion, dns.id as dnsid, dns.name as dns_name, ip.id AS ipid, ip.ip AS ip, ip.name AS ip_name, ip.rdns, h.id AS whid, h.name AS wh_name
		FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h
		WHERE d.account_id = ra.id
		  AND ra.registrar_id = r.id
		  AND ra.owner_id = o.id
		  AND d.cat_id = cat.id
		  AND d.fee_id = f.id
		  AND d.dns_id = dns.id
		  AND d.ip_id = ip.id
		  AND d.hosting_id = h.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  $is_active_string
		  $segid_string
		  $pcid_string
		  $oid_string
		  $dnsid_string
		  $ipid_string
		  $whid_string
		  $rid_string
		  $raid_string
		  $tld_string
		  $search_string
		  $quick_search_string
		  $sort_by_string";	

$sql_grand_total = "SELECT SUM(f.renewal_fee * cc.conversion) AS grand_total
					FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, hosting AS h
					WHERE d.account_id = ra.id
					  AND ra.registrar_id = r.id
					  AND ra.owner_id = o.id
					  AND d.cat_id = cat.id
					  AND d.fee_id = f.id
					  AND d.dns_id = dns.id
					  AND d.ip_id = ip.id
					  AND d.hosting_id = h.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  $is_active_string
					  $segid_string
					  $pcid_string
					  $oid_string
					  $dnsid_string
					  $ipid_string
					  $whid_string
					  $rid_string
					  $raid_string
					  $tld_string
					  $search_string
					  $quick_search_string
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

if ($segid != "") {
	
	$result = mysql_query($sql,$connection);

	$active_domains = "'";
	while ($row = mysql_fetch_object($result)) { $active_domains .= $row->domain . "', '";	}
	$active_domains .= "'";
	$active_domains = substr($active_domains, 0, -4);
	
	$sql_filter_update = "UPDATE segment_data
						  SET filtered = '0'
						  WHERE active = '1'
						    AND segment_id = '$segid'";
	$result_filter_update = mysql_query($sql_filter_update,$connection);

	$sql_filter_update = "UPDATE segment_data
						  SET filtered = '1'
						  WHERE active = '1'
							AND segment_id = '$segid'
							AND domain NOT IN ($active_domains)";
	$result_filter_update = mysql_query($sql_filter_update,$connection);

	$sql_filter_update = "UPDATE segment_data
						  SET filtered = '1'
						  WHERE active = '1'
							AND segment_id = '$segid'
							AND domain NOT LIKE '%" . $search_for . "%'";
	$result_filter_update = mysql_query($sql_filter_update,$connection);

	$sql_filter_update = "UPDATE segment_data
						  SET filtered = '1'
						  WHERE active = '1'
							AND segment_id = '$segid'
							AND domain NOT IN (" . $_SESSION['quick_search'] . ")";
	$result_filter_update = mysql_query($sql_filter_update,$connection);

}

$full_export = "";

if ($export == "1") {

	$result = mysql_query($sql,$connection);
	$total_rows = number_format(mysql_num_rows($result));

	$full_export .= "\"Domain Search Results Export\"\n\n";
	if ($segid == "") {

		$full_export .= "\"Total Cost:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n";
		$full_export .= "\"Number of Domains:\",\"" . number_format($total_rows) . "\"\n\n";

	} else {

		$full_export .= "\"Total Cost:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n\n";

	}

	if ($tld != "") { 

		$full_export .= "\"TLD:\",\"." . $tld . "\"\n"; 

	}

	if ($segid != "") {

		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND inactive = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_inactive = mysql_num_rows($result_segment);
	
		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND missing = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_missing = mysql_num_rows($result_segment);
	
		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND filtered = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_filtered = mysql_num_rows($result_segment);

		if ($segid != "") {
		
			$sql_segment = "SELECT number_of_domains
							FROM segments
							WHERE id = '$segid'";
			$result_segment = mysql_query($sql_segment,$connection);
			while ($row_segment = mysql_fetch_object($result_segment)) { $number_of_domains = $row_segment->number_of_domains; }
		
		}

		$full_export .= "\"[Segment Results]\"\n";

		$sql_filter = "SELECT name
					   FROM segments
					   WHERE id = '" . $segid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Segment Filter:\",\"" . $row_filter->name . "\"\n"; 
		}

		$full_export .= "\"Domains in Segment:\",\"" . number_format($number_of_domains) . "\"\n";
	
		$full_export .= "\"Matching Domains:\",\"" . number_format($total_rows) . "\"\n";
		
		if ($totalrows_inactive > 0) {
			$full_export .= "\"Matching But Inactive Domains:\",\"" . number_format($totalrows_inactive) . "\"\n";
		}
		if ($totalrows_filtered > 0) {
			$full_export .= "\"Matching But Filtered Domains:\",\"" . number_format($totalrows_filtered) . "\"\n";
		}
		if ($totalrows_missing > 0) {
			$full_export .= "\"Missing Domains:\",\"" . number_format($totalrows_missing) . "\"\n";
		}

		$full_export .= "\n";

	}

	$full_export .= "\"[Search Filters]\"\n";

	if ($_SESSION['search_for'] != "") { 

		$full_export .= "\"Keyword Search:\",\"" . $_SESSION['search_for'] . "\"\n"; 

	}

	if ($_SESSION['quick_search'] != "") { 
	
		$formatted_quick_search = str_replace("'", "", $_SESSION['quick_search']);
		$formatted_quick_search = str_replace(",", ", ", $formatted_quick_search);
		$full_export .= "\"Quick Domain Search:\",\"" . $formatted_quick_search . "\"\n"; 

	}

	if ($rid > 0) { 

		$sql_filter = "SELECT name
					   FROM registrars
					   WHERE id = '" . $rid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Registrar:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($raid > 0) { 

		$sql_filter = "SELECT r.name AS registrar_name, o.name AS owner_name, ra.username
					   FROM registrar_accounts AS ra, registrars AS r, owners AS o
					   WHERE ra.registrar_id = r.id
						 AND ra.owner_id = o.id
						 AND ra.id = '" . $raid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Registrar Account:\",\"" . $row_filter->registrar_name . " - " . $row_filter->owner_name . " - " . $row_filter->username . "\"\n"; 
		}

	}

	if ($dnsid > 0) { 

		$sql_filter = "SELECT name
					   FROM dns
					   WHERE id = '" . $dnsid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"DNS Profile:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($ipid > 0) { 

		$sql_filter = "SELECT name, ip
					   FROM ip_addresses
					   WHERE id = '" . $ipid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"IP Address:\",\"" . $row_filter->name . " (" . $row_filter->ip . ")\"\n"; 
		}

	}

	if ($whid > 0) { 

		$sql_filter = "SELECT name
					   FROM hosting
					   WHERE id = '" . $whid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Web Host:\",\"" . $row_filter->name . "\"\n"; 
		}

	}

	if ($pcid > 0) { 

		$sql_filter = "SELECT name
					   FROM categories
					   WHERE id = '" . $pcid . "'";
		$result_filter = mysql_query($sql_filter,$connection);

		while ($row_filter = mysql_fetch_object($result_filter)) {
			$full_export .= "\"Category:\",\"" . $row_filter->name . "\"\n"; 
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

	if ($is_active == "ALL") { $full_export .= "\"Domain Status:\",\"ALL\"\n"; } 
	elseif ($is_active == "LIVE" || $is_active == "") { $full_export .= "\"Domain Status:\",\"LIVE (Active / Transfers / Pending)\"\n"; } 
	elseif ($is_active == "0") { $full_export .= "\"Domain Status:\",\"Expired\"\n"; } 
	elseif ($is_active == "1") { $full_export .= "\"Domain Status:\",\"Active\"\n"; } 
	elseif ($is_active == "2") { $full_export .= "\"Domain Status:\",\"In Transfer\"\n"; } 
	elseif ($is_active == "3") { $full_export .= "\"Domain Status:\",\"Pending (Renewal)\"\n"; } 
	elseif ($is_active == "4") { $full_export .= "\"Domain Status:\",\"Pending (Other)\"\n"; } 
	elseif ($is_active == "5") { $full_export .= "\"Domain Status:\",\"Pending (Registration)\"\n"; } 
	elseif ($is_active == "10") { $full_export .= "\"Domain Status:\",\"Sold\"\n"; }

	$full_export .= "\n";

	$sql_field = "SELECT name
				  FROM domain_fields
				  ORDER BY name";
	$result_field = mysql_query($sql_field,$connection);
	
	$count = 0;
	$header_list = "";
	
	while ($row_field = mysql_fetch_object($result_field)) {
		
		$name_array[$count] = $row_field->name;
		$count++;
	
	}
	
	foreach($name_array as $field_name) {
		
		$header_list .= "\"" . $field_name . "\",";
	
	}

	$full_export .= "\"Domain Status\",\"Expiry Date\",\"Initial Fee\",\"Renewal Fee\",\"Domain\",\"TLD\",\"Function\",\"WHOIS Status\",\"Registrar\",\"Registrar Account\",\"Username\",\"DNS Profile\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Web Host\",\"Category\",\"Category Stakeholder\",\"Owner\",\"Notes\",$header_list\"Inserted\",\"Updated\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_initial_fee = $row->initial_fee * $row->conversion;

		$temp_renewal_fee = $row->renewal_fee * $row->conversion;

		if ($row->active == "0") { $domain_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $domain_status = "ACTIVE"; } 
		elseif ($row->active == "2") { $domain_status = "IN TRANSFER"; } 
		elseif ($row->active == "3") { $domain_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $domain_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $domain_status = "PENDING (REGISTRATION)"; } 
		elseif ($row->active == "10") { $domain_status = "SOLD"; } 
		else { $domain_status = "ERROR -- PROBLEM WITH CODE IN DOMAINS.PHP"; } 
		
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

		$sql_field = "SELECT field_name
					  FROM domain_fields
					  ORDER BY name";
		$result_field = mysql_query($sql_field,$connection);
		
		$count = 0;
		$field_data = "";
		
		while ($row_field = mysql_fetch_object($result_field)) {
			
			$field_array[$count] = $row_field->field_name;
			$count++;
		
		}
		
		foreach($field_array as $field) {
		
			$sql_data = "SELECT " . $field . " 
						 FROM domain_field_data
						 WHERE domain_id = '" . $row->id . "'";
			$result_data = mysql_query($sql_data,$connection);
			
			while ($row_data = mysql_fetch_object($result_data)) {
		
				$field_data .= "\"" . $row_data->{$field} . "\",";
			
			}
		
		}

		$full_export .= "\"$domain_status\",\"$row->expiry_date\",\"" . $export_initial_fee . "\",\"" . $export_renewal_fee . "\",\"$row->domain\",\".$row->tld\",\"$row->function\",\"$privacy_status\",\"$row->registrar_name\",\"$row->registrar_name, $row->owner_name ($row->username)\",\"$row->username\",\"$row->dns_name\",\"$row->ip_name\",\"$row->ip\",\"$row->rdns\",\"$row->wh_name\",\"$row->category_name\",\"$row->stakeholder\",\"$row->owner_name\",\"$row->notes\",$field_data\"$row->insert_time\",\"$row->update_time\"\n";

	}

	$full_export .= "\n";

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "domain_results_" . $current_timestamp_unix . ".csv";
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
<body onLoad="document.forms[0].elements[12].focus()";>
<?php include("_includes/layout/header.inc.php"); ?>
<?php
if ($_SESSION['need_registrar'] == "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> Domain Registrars found. Please <a href=\"assets/add/registrar.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['need_registrar_account'] == "1" && $_SESSION['need_registrar'] != "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> Domain Registrar Accounts found. Please <a href=\"assets/add/registrar-account.php\">click here</a> to add one.<BR><BR>";
	exit;
}

if ($_SESSION['need_domain'] == "1" && $_SESSION['need_registrar'] != "1" && $_SESSION['need_registrar_account'] != "1") {
	echo "<strong><font class=\"highlight\">0</font></strong> Domains found. Please <a href=\"add/domain.php\">click here</a> to add one.<BR><BR>";
	exit;
}
$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "&pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection);
$total_rows = number_format(mysql_num_rows($result));

if ($segid != "") {

	$sql_segment = "SELECT number_of_domains
					FROM segments
					WHERE id = '$segid'";
	$result_segment = mysql_query($sql_segment,$connection);
	while ($row_segment = mysql_fetch_object($result_segment)) { $number_of_domains = $row_segment->number_of_domains; }

}
?>
<form name="domain_search_form" method="post" action="<?=$PHP_SELF?>">
<div class="search-block-outer">
<div class="search-block-inner">
<div class="search-block-left">
&nbsp;&nbsp;
<?php 
// SEGMENT
if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }

$sql_segment = "SELECT id, name
				FROM segments
				ORDER BY name asc";
$result_segment = mysql_query($sql_segment,$connection);

echo "<select name=\"segid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Segment Filter - ALL</option>";
while ($row_segment = mysql_fetch_object($result_segment)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&segid=$row_segment->id&tld=$tld&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_segment->id == $segid) echo " selected"; echo ">"; echo "$row_segment->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// REGISTRAR
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_registrar = "SELECT r.id, r.name 
				  FROM registrars AS r, domains AS d
				  WHERE r.id = d.registrar_id
				    $is_active_string
				    $pcid_string
				    $oid_string
				    $dnsid_string
				    $ipid_string
				    $whid_string
				    $raid_string
				    $tld_string
				    $search_string
					$quick_search_string
					$segment_string
				  GROUP BY r.name
				  ORDER BY r.name asc";
$result_registrar = mysql_query($sql_registrar,$connection);
echo "<select name=\"rid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Registrar - ALL</option>";
while ($row_registrar = mysql_fetch_object($result_registrar)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$row_registrar->id&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_registrar->id == $rid) echo " selected"; echo ">"; echo "$row_registrar->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// REGISTRAR ACCOUNT
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_account = "SELECT ra.id AS ra_id, ra.username, r.name AS r_name, o.name AS o_name
				FROM registrar_accounts AS ra, registrars AS r, owners AS o, domains AS d
				WHERE ra.registrar_id = r.id
				  AND ra.owner_id = o.id
				  AND ra.id = d.account_id
				  $is_active_string
				  $pcid_string
				  $oid_string
				  $dnsid_string
				  $ipid_string
				  $whid_string
				  $rid_string
				  $tld_string
				  $search_string
				  $quick_search_string
				  $segment_string
				GROUP BY r.name, o.name, ra.username
				ORDER BY r.name asc, o.name asc, ra.username asc"; 
$result_account = mysql_query($sql_account,$connection);
echo "<select name=\"raid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Registrar Account - ALL</option>";
while ($row_account = mysql_fetch_object($result_account)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$row_account->ra_id&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_account->ra_id == $raid) echo " selected"; echo ">"; echo "$row_account->r_name, $row_account->o_name ($row_account->username)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// DNS
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_dns = "SELECT dns.id, dns.name 
			FROM dns AS dns, domains AS d
			WHERE dns.id = d.dns_id
			  $is_active_string
			  $pcid_string
			  $oid_string
			  $ipid_string
			  $whid_string
			  $rid_string
			  $raid_string
			  $tld_string
			  $search_string
			  $quick_search_string
			  $segment_string
			GROUP BY dns.name
			ORDER BY dns.name asc";
$result_dns = mysql_query($sql_dns,$connection);
echo "<select name=\"dnsid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">DNS Profile - ALL</option>";
while ($row_dns = mysql_fetch_object($result_dns)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$row_dns->id&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_dns->id == $dnsid) echo " selected"; echo ">"; echo "$row_dns->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// IP ADDRESS
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

	$sql_ip = "SELECT ip.id, ip.name, ip.ip
			   FROM ip_addresses AS ip, domains AS d
			   WHERE ip.id = d.ip_id
				 $is_active_string
				 $pcid_string
				 $oid_string
				 $dnsid_string
				 $whid_string
				 $rid_string
				 $raid_string
				 $tld_string
				 $search_string
				 $quick_search_string
				 $segment_string
			   GROUP BY ip.name
			   ORDER BY ip.name asc";
$result_ip = mysql_query($sql_ip,$connection);
echo "<select name=\"ipid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">IP Address - ALL</option>";
while ($row_ip = mysql_fetch_object($result_ip)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$row_ip->id&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_ip->id == $ipid) echo " selected"; echo ">"; echo "$row_ip->name ($row_ip->ip)</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// WEB HOSTING PROVIDER
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

	$sql_hosting = "SELECT h.id, h.name
					FROM hosting AS h, domains AS d
					WHERE h.id = d.hosting_id
					  $is_active_string
					  $pcid_string
					  $oid_string
					  $dnsid_string
					  $ipid_string
					  $rid_string
					  $raid_string
					  $tld_string
					  $search_string
					  $quick_search_string
					  $segment_string
					GROUP BY h.name
					ORDER BY h.name asc";
$result_hosting = mysql_query($sql_hosting,$connection);
echo "<select name=\"whid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Web Hosting Provider - ALL</option>";
while ($row_hosting = mysql_fetch_object($result_hosting)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$row_hosting->id&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_hosting->id == $whid) echo " selected"; echo ">"; echo "$row_hosting->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// CATEGORY
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($oid != "") { $oid_string = " AND d.owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_category = "SELECT c.id, c.name
				 FROM categories AS c, domains AS d
				 WHERE c.id = d.cat_id
				   $is_active_string
				   $oid_string
				   $dnsid_string
				   $ipid_string
				   $whid_string
				   $rid_string
				   $raid_string
				   $tld_string
				   $search_string
				   $quick_search_string
				   $segment_string
				 GROUP BY c.name
				 ORDER BY c.name asc";
$result_category = mysql_query($sql_category,$connection);
echo "<select name=\"pcid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Category - ALL</option>";
while ($row_category = mysql_fetch_object($result_category)) { 
	echo "<option value=\"$PHP_SELF?pcid=$row_category->id&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_category->id == $pcid) echo " selected"; echo ">"; echo "$row_category->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// OWNER
if ($is_active == "0") { $is_active_string = " AND d.active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " AND d.active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " AND d.active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " AND d.active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " AND d.active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " AND d.active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " AND d.active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " AND d.active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " AND d.active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " AND d.active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " AND d.active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND d.cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND d.dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND d.ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND d.hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND d.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND d.account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND d.tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND d.domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND d.domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND d.domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_owner = "SELECT o.id, o.name 
			  FROM owners AS o, domains AS d
			  WHERE o.id = d.owner_id
				$is_active_string
				$pcid_string
				$dnsid_string
				$ipid_string
				$whid_string
				$rid_string
				$raid_string
				$tld_string
				$search_string
				$quick_search_string
				$segment_string
			  GROUP BY o.name
			  ORDER BY o.name asc";
$result_owner = mysql_query($sql_owner,$connection);
echo "<select name=\"oid\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">Owner - ALL</option>";
while ($row_owner = mysql_fetch_object($result_owner)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$row_owner->id&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_owner->id == $oid) echo " selected"; echo ">"; echo "$row_owner->name</option>";
} 
echo "</select>";
?>
<BR><BR>

&nbsp;&nbsp;
<?php 
// TLD
if ($is_active == "0") { $is_active_string = " WHERE active = '0' "; } 
elseif ($is_active == "1") { $is_active_string = " WHERE active = '1' "; } 
elseif ($is_active == "2") { $is_active_string = " WHERE active = '2' "; } 
elseif ($is_active == "3") { $is_active_string = " WHERE active = '3' "; } 
elseif ($is_active == "4") { $is_active_string = " WHERE active = '4' "; } 
elseif ($is_active == "5") { $is_active_string = " WHERE active = '5' "; } 
elseif ($is_active == "6") { $is_active_string = " WHERE active = '6' "; } 
elseif ($is_active == "7") { $is_active_string = " WHERE active = '7' "; } 
elseif ($is_active == "8") { $is_active_string = " WHERE active = '8' "; } 
elseif ($is_active == "9") { $is_active_string = " WHERE active = '9' "; } 
elseif ($is_active == "10") { $is_active_string = " WHERE active = '10' "; } 
elseif ($is_active == "LIVE") { $is_active_string = " WHERE active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " WHERE active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND account_id = '$raid' "; } else { $raid_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_tld = "SELECT tld, count(*) AS total_tld_count
			FROM domains
			$is_active_string
			$pcid_string
			$oid_string
			$dnsid_string
			$ipid_string
			$whid_string
			$rid_string
			$raid_string
			$search_string
			$quick_search_string
			$segment_string
			GROUP BY tld
			ORDER BY tld asc";
$result_tld = mysql_query($sql_tld,$connection);
echo "<select name=\"tld\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\">TLD - ALL</option>";
while ($row_tld = mysql_fetch_object($result_tld)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$row_tld->tld&segid=$segid&is_active=$is_active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_tld->tld == $tld) echo " selected"; echo ">"; echo ".$row_tld->tld</option>";
} 
echo "</select>";
?>

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
elseif ($is_active == "LIVE") { $is_active_string = " AND active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "; } 
elseif ($is_active == "ALL") { $is_active_string = " AND active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') "; } 

if ($pcid != "") { $pcid_string = " AND cat_id = '$pcid' "; } else { $pcid_string = ""; }
if ($oid != "") { $oid_string = " AND owner_id = '$oid' "; } else { $oid_string = ""; }
if ($dnsid != "") { $dnsid_string = " AND dns_id = '$dnsid' "; } else { $dnsid_string = ""; }
if ($ipid != "") { $ipid_string = " AND ip_id = '$ipid' "; } else { $ipid_string = ""; }
if ($whid != "") { $whid_string = " AND hosting_id = '$whid' "; } else { $whid_string = ""; }
if ($rid != "") { $rid_string = " AND registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND account_id = '$raid' "; } else { $raid_string = ""; }
if ($tld != "") { $tld_string = " AND tld = '$tld' "; } else { $tld_string = ""; }
if ($_SESSION['search_for'] != "") { $search_string = " AND domain LIKE '%" . $_SESSION['search_for'] . "%'"; } else { $search_string = ""; }
if ($_SESSION['quick_search'] != "") { $quick_search_string = " AND domain IN (" . $_SESSION['quick_search'] . ") "; } else { $quick_search_string = ""; }
if ($segid != "") { $segment_string = " AND domain IN (SELECT domain FROM segment_data WHERE segment_id = '$segid') "; } else { $segment_string = ""; }

$sql_active = "SELECT active, count(*) AS total_count
			   FROM domains
			   WHERE id != '0'
			   	 $pcid_string
			     $oid_string
			     $dnsid_string
			     $ipid_string
			     $whid_string
			     $rid_string
			     $raid_string
			     $tld_string
				 $search_string
				 $quick_search_string
				 $segment_string
			   GROUP BY active
			   ORDER BY active asc";
$result_active = mysql_query($sql_active,$connection);
echo "<select name=\"is_active\" onChange=\"MM_jumpMenu('parent',this,0)\">";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=LIVE&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($is_active == "LIVE") echo " selected"; echo ">"; echo "\"Live\" (Active / Transfers / Pending)</option>";
while ($row_active = mysql_fetch_object($result_active)) { 
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$row_active->active&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($row_active->active == $is_active) echo " selected"; echo ">"; if ($row_active->active == "0") { echo "Expired"; } elseif ($row_active->active == "10") { echo "Sold"; } elseif ($row_active->active == "1") { echo "Active"; } elseif ($row_active->active == "2") { echo "In Transfer"; } elseif ($row_active->active == "3") { echo "Pending (Renewal)"; } elseif ($row_active->active == "4") { echo "Pending (Other)"; } elseif ($row_active->active == "5") { echo "Pending (Registration)"; } echo "</option>";
} 
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=ALL&result_limit=$result_limit&sort_by=$sort_by&from_dropdown=1\""; if ($is_active == "ALL") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>

&nbsp;&nbsp;
<?php 
// NUMBER OF DOMAINS TO DISPLAY
echo "<select name=\"result_limit\" onChange=\"MM_jumpMenu('parent',this,0)\">"; 

if ($_SESSION['number_of_domains'] != "10" && $_SESSION['number_of_domains'] != "50" && $_SESSION['number_of_domains'] != "100" && $_SESSION['number_of_domains'] != "500" && $_SESSION['number_of_domains'] != "1000" && $_SESSION['number_of_domains'] != "1000000") {
	echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=" . $_SESSION['number_of_domains'] . "&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == $_SESSION['number_of_domains']) echo " selected"; echo ">"; echo "" . $_SESSION['number_of_domains'] . "</option>";
}

echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=10&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "10") echo " selected"; echo ">"; echo "10</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=50&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "50") echo " selected"; echo ">"; echo "50</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=100&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "100") echo " selected"; echo ">"; echo "100</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=500&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "500") echo " selected"; echo ">"; echo "500</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=1000&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "1000") echo " selected"; echo ">"; echo "1,000</option>";
echo "<option value=\"$PHP_SELF?pcid=$pcid&oid=$oid&dnsid=$dnsid&ipid=$ipid&whid=$whid&rid=$rid&raid=$raid&tld=$tld&segid=$segid&is_active=$is_active&result_limit=1000000&sort_by=$sort_by&from_dropdown=1\""; if ($result_limit == "1000000") echo " selected"; echo ">"; echo "ALL</option>";
echo "</select>";
?>
<BR>
<input type="hidden" name="sort_by" value="<?=$sort_by?>">
</div>
<div class="search-block-right">
<strong>Keyword Search:</strong><BR><BR>
<input name="search_for" type="text" value="<?=$_SESSION['search_for']?>" size="20">&nbsp;&nbsp;<input type="submit" name="button" value="Search &raquo;">&nbsp;&nbsp;<BR><BR>
<?php
$_SESSION['quick_search'] = preg_replace("/', '/", "\r\n", $_SESSION['quick_search']);
$_SESSION['quick_search'] = preg_replace("/','/", "\r\n", $_SESSION['quick_search']);
$_SESSION['quick_search'] = preg_replace("/'/", "", $_SESSION['quick_search']);
?>
<BR><strong>Domain Search (one domain per line):</strong><BR><BR>
<textarea name="quick_search" cols="30" rows="11"><?=$_SESSION['quick_search']?></textarea>&nbsp;&nbsp;
<BR>
<BR>
<input type="hidden" name="pcid" value="<?=$pcid?>">
<input type="hidden" name="oid" value="<?=$oid?>">
<input type="hidden" name="dnsid" value="<?=$dnsid?>">
<input type="hidden" name="ipid" value="<?=$ipid?>">
<input type="hidden" name="whid" value="<?=$whid?>">
<input type="hidden" name="rid" value="<?=$rid?>">
<input type="hidden" name="raid" value="<?=$raid?>">
<input type="hidden" name="tld" value="<?=$tld?>">
<input type="hidden" name="segid" value="<?=$segid?>">
<input type="hidden" name="is_active" value="<?=$is_active?>">
<input type="hidden" name="result_limit" value="<?=$result_limit?>">
</div>
</div>
</div>
</form>
<div style="clear: both;"></div>
<BR>
<?php if ($segid != "") {
	
		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND inactive = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_inactive = mysql_num_rows($result_segment);
	
		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND missing = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_missing = mysql_num_rows($result_segment);

		$sql_segment = "SELECT domain
						FROM segment_data
						WHERE segment_id = '$segid'
						  AND filtered = '1'
						ORDER BY domain";
		$result_segment = mysql_query($sql_segment,$connection);
		$totalrows_filtered = mysql_num_rows($result_segment);
		?>
		<strong>Domains in Segment:</strong> <?=number_format($number_of_domains)?>
		<BR><BR><strong>Matching Domains:</strong> <?=$totalrows?>
        <?php if ($totalrows_inactive > 0) { ?>
			<BR><BR><strong>Matching But Inactive Domains:</strong> <?=number_format($totalrows_inactive)?> [<a class="invisiblelink" target="_blank" href="segment-results.php?type=inactive&segid=<?=$segid?>">view</a>]
		<?php } ?>
        <?php if ($totalrows_filtered > 0) { ?>
	        <BR><BR><strong>Matching But Filtered Domains:</strong> <?=number_format($totalrows_filtered)?> [<a class="invisiblelink" target="_blank" href="segment-results.php?type=filtered&segid=<?=$segid?>">view</a>]
		<?php } ?>
        <?php if ($totalrows_missing > 0) { ?>
	        <BR><BR><strong>Missing Domains:</strong> <?=number_format($totalrows_missing)?> [<a class="invisiblelink" target="_blank" href="segment-results.php?type=missing&segid=<?=$segid?>">view</a>]
		<?php } ?>

<?php } ?>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php if ($segid != "") { ?>
	<BR><BR><strong>Total Cost:</strong> <?=$grand_total?> <?=$_SESSION['default_currency']?><BR><BR>
<?php } else { ?>
	<strong>Total Cost:</strong> <?=$grand_total?> <?=$_SESSION['default_currency']?><BR><BR>
	<strong>Number of Domains:</strong> <?=number_format($totalrows)?><BR><BR>
<?php } ?>
<?php include("_includes/layout/search-options-block.inc.php"); ?>
<?php if ($totalrows != '0') { ?>
<table class="main_table" cellpadding="0" cellspacing="0">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['display_domain_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ed_a") { echo "ed_d"; } else { echo "ed_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Expiry Date</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "df_a") { echo "df_d"; } else { echo "df_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Fee</font></a>
	</td>
<?php } ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dn_a") { echo "dn_d"; } else { echo "dn_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Domain Name</font></a>
	</td>
<?php if ($_SESSION['display_domain_tld'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "tld_a") { echo "tld_d"; } else { echo "tld_a"; } ?>&from_dropdown=1"><font class="main_table_heading">TLD</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_registrar'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "r_a") { echo "r_d"; } else { echo "r_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Registrar</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ra_a") { echo "ra_d"; } else { echo "ra_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Registrar Account</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_dns'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "dns_a") { echo "dns_d"; } else { echo "dns_a"; } ?>&from_dropdown=1"><font class="main_table_heading">DNS Profile</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_ip'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "ip_a") { echo "ip_d"; } else { echo "ip_a"; } ?>&from_dropdown=1"><font class="main_table_heading">IP Address</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_host'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "wh_a") { echo "wh_d"; } else { echo "wh_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Web Host</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_category'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "pc_a") { echo "pc_d"; } else { echo "pc_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Category</font></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_owner'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<a href="domains.php?pcid=<?=$pcid?>&oid=<?=$oid?>&dnsid=<?=$dnsid?>&ipid=<?=$ipid?>&whid=<?=$whid?>&rid=<?=$rid?>&raid=<?=$raid?>&segid=<?=$segid?>&tld=<?=$tld?>&is_active=<?=$is_active?>&result_limit=<?=$result_limit?>&sort_by=<?php if ($sort_by == "o_a") { echo "o_d"; } else { echo "o_a"; } ?>&from_dropdown=1"><font class="main_table_heading">Owner</font></a>
	</td>
<?php } ?>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
<?php if ($_SESSION['display_domain_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/domain.php?did=<?=$row->id?>"><?=$row->expiry_date?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/registrar-fees.php?rid=<?=$row->r_id?>">
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
					echo "<a title=\"Inactive Domain\"><strong><font class=\"highlight\">x</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "10") { 
					echo "<a title=\"Sold\"><strong><font class=\"highlight\">S</font></strong></a>&nbsp;"; 
					echo "<a title=\"Sold\"></a>"; 
				} elseif ($row->active == "2") { 
					echo "<a title=\"In Transfer\"><strong><font class=\"highlight\">T</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "3") { 
					echo "<a title=\"Pending (Renewal)\"><strong><font class=\"highlight\">PRn</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "4") { 
					echo "<a title=\"Pending (Other)\"><strong><font class=\"highlight\">PO</font></strong></a>&nbsp;"; 
				} elseif ($row->active == "5") { 
					echo "<a title=\"Pending (Registration)\"><strong><font class=\"highlight\">PRg</font></strong></a>&nbsp;"; 
				} 
			?><a class="invisiblelink" href="edit/domain.php?did=<?=$row->id?>"><?=$row->domain?></a><?php if ($row->privacy == "1") { echo "&nbsp;<a title=\"Private WHOIS Registration\"><strong><font class=\"highlight\">prv</font></strong></a>&nbsp;"; } else { echo "&nbsp;"; } ?>[<a class="invisiblelink" target="_blank" href="http://<?=$row->domain?>">v</a>] [<a target="_blank" class="invisiblelink" href="http://who.is/whois/<?=$row->domain?>">w</a>]
	</td>
<?php if ($_SESSION['display_domain_tld'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/domain.php?did=<?=$row->id?>">.<?=$row->tld?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_registrar'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/registrar.php?rid=<?=$row->r_id?>"><?=$row->registrar_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/registrar.php?rid=<?=$row->r_id?>"><?=$row->registrar_name?></a>, <a class="invisiblelink" href="assets/edit/account-owner.php?oid=<?=$row->o_id?>"><?=$row->owner_name?></a> (<a class="invisiblelink" href="assets/edit/registrar-account.php?raid=<?=$row->ra_id?>"><?=substr($row->username, 0, 15);?><?php if (strlen($row->username) >= 16) echo "..."; ?></a>)
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_dns'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/dns.php?dnsid=<?=$row->dnsid?>"><?=$row->dns_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_ip'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/ip-address.php?ipid=<?=$row->ipid?>"><?=$row->ip_name?> (<?=$row->ip?>)</a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_host'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/host.php?whid=<?=$row->whid?>"><?=$row->wh_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_category'] == "1") { ?>
	<td class="main_table_cell_active">
		<a class="invisiblelink" href="assets/edit/category.php?pcid=<?=$row->pcid?>"><?=$row->category_name?></a>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_owner'] == "1") { ?>
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
			<BR><BR>Your search returned zero results.
<?php } ?>
<?php include("_includes/layout/footer.inc.php"); ?>
</body>
</html>