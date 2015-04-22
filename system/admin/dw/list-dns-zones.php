<?php
/**
 * /system/admin/dw/list-dns-zones.php
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
include("../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../invalid.php";
include("../../../_includes/auth/admin-user-check.inc.php");

include("../../../_includes/config.inc.php");
include("../../../_includes/database.inc.php");
include("../../../_includes/software.inc.php");
include("../../../_includes/auth/auth-check.inc.php");
include("../../../_includes/timestamps/current-timestamp.inc.php");
include("../../../_includes/classes/Error.class.php");
include("../../../_includes/classes/Layout.class.php");

$error = new DomainMOD\Error();

$domain = $_GET['domain'];
$search_for = $_REQUEST['search_for'];
$export = $_GET['export'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($search_for != "") $domain = "";

$page_title = "Data Warehouse";
if ($_SESSION['dw_view_all'] == "1") {

	$page_subtitle = "Listing All DNS Zones & Records";
	
} else {

	$page_subtitle = "Listing DNS Zones & Records on " . $_SESSION['dw_server_name'] . " (" . $_SESSION['dw_server_host'] . ")";
	
}
$software_section = "admin-dw-list-dns-zones";

if ($_SESSION['dw_view_all'] == "1") {
	
	$where_clause = "";
	$where_clause_no_join = "";
	
} else {

	$where_clause = "AND z.server_id = '" . $_SESSION['dw_server_id'] . "'";
	$where_clause_no_join = "AND server_id = '" . $_SESSION['dw_server_id'] . "'";
	$where_clause_no_join_first_line = "WHERE server_id = '" . $_SESSION['dw_server_id'] . "'";

}

if ($domain != "") {

		$sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								 FROM dw_dns_zones AS z, dw_servers AS s
								 WHERE z.server_id = s.id
								   AND z.domain = '" . $domain . "'
								   " . $where_clause . "
								 ORDER BY s.name, z.zonefile, z.domain";

} else {

	if ($search_for != "") {

		$sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								 FROM dw_dns_zones AS z, dw_servers AS s
								 WHERE z.server_id = s.id
								   AND z.domain LIKE '%" . $search_for . "%'
								   " . $where_clause . "
								 ORDER BY s.name, z.zonefile, z.domain";

	} else {

		$sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								 FROM dw_dns_zones AS z, dw_servers AS s
								 WHERE z.server_id = s.id
								   " . $where_clause . "
								 ORDER BY s.name, z.zonefile, z.domain";

	}

}

if ($export == "1") {

	$result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "dw_dns_zones_" . $current_timestamp_unix . ".csv";
	include("../../../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../../../_includes/system/export/write-row.inc.php");

	$row_content[$count++] = $page_subtitle;
	include("../../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	if ($domain != "") {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										   WHERE domain = '" . $domain . "'
										     " . $where_clause_no_join . "";
	
	} else {
	
		if ($search_for != "") {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										   WHERE domain LIKE '%" . $search_for . "%'
										     " . $where_clause_no_join . "";
	
		} else {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										     " . $where_clause_no_join_first_line . "";
	
		}
	
	}
	$result_total_dns_record_count = mysqli_query($connection, $sql_total_dns_record_count);
	
	while ($row_total_dns_record_count = mysqli_fetch_object($result_total_dns_record_count)) {
		
		$total_dns_record_count_temp = $row_total_dns_record_count->total_dns_record_count;
		
	}

	$row_content[$count++] = "Number of DNS Zones:";
	$row_content[$count++] = number_format(mysqli_num_rows($result_dw_dns_zone_temp));
	include("../../../_includes/system/export/write-row.inc.php");

	$row_content[$count++] = "Number of DNS Records:";
	$row_content[$count++] = number_format($total_dns_record_count_temp);
	include("../../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	if ($search_for != "") { 
	
		$row_content[$count++] = "Keyword Search:";
		$row_content[$count++] = "\"" . $search_for . "\"";
		include("../../../_includes/system/export/write-row.inc.php");
	
		fputcsv($file_content, $blank_line);
	
	}
	
	if ($domain != "") { 
	
		$row_content[$count++] = "Domain Filter:";
		$row_content[$count++] = $domain;
		include("../../../_includes/system/export/write-row.inc.php");
	
		fputcsv($file_content, $blank_line);
	
	}

	$row_content[$count++] = "Server Name";
	$row_content[$count++] = "Host";
	$row_content[$count++] = "Domain";
	$row_content[$count++] = "DNS Zone File";
	$row_content[$count++] = "Original/Primary Zone Source";
	$row_content[$count++] = "Zone Admin Email";
	$row_content[$count++] = "Serial";
	$row_content[$count++] = "Zone Refresh TTL";
	$row_content[$count++] = "Retry Interval";
	$row_content[$count++] = "Zone Expiration";
	$row_content[$count++] = "Minimum Record TTL";
	$row_content[$count++] = "Authoritative Name Server";
	$row_content[$count++] = "DNS Record";
	$row_content[$count++] = "Record TTL";
	$row_content[$count++] = "Record Class";
	$row_content[$count++] = "Record Type";
	$row_content[$count++] = "IP Address";
	$row_content[$count++] = "Canonical Name";
	$row_content[$count++] = "Mail Server";
	$row_content[$count++] = "Mail Server Priority";
	$row_content[$count++] = "Text Record Data";
	$row_content[$count++] = "Zone Line #";
	$row_content[$count++] = "Number of Lines";
	$row_content[$count++] = "Raw Line Output";
	$row_content[$count++] = "Inserted (into DW)";
	include("../../../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result_dw_dns_zone_temp) > 0) {

		while ($row_dw_dns_zone_temp = mysqli_fetch_object($result_dw_dns_zone_temp)) {

			$sql_get_records = "SELECT *
								FROM dw_dns_records
								WHERE server_id = '" . $row_dw_dns_zone_temp->dw_server_id . "'
								  AND domain = '" . $row_dw_dns_zone_temp->domain . "'
								ORDER BY new_order";
			$result_get_records = mysqli_query($connection, $sql_get_records);
			
			while ($row_get_records = mysqli_fetch_object($result_get_records)) {
	
				$row_content[$count++] = $row_dw_dns_zone_temp->dw_server_name;
				$row_content[$count++] = $row_dw_dns_zone_temp->dw_server_host;
				$row_content[$count++] = $row_get_records->domain;
				$row_content[$count++] = $row_get_records->zonefile;
				$row_content[$count++] = $row_get_records->mname;
				$row_content[$count++] = $row_get_records->rname;
				$row_content[$count++] = $row_get_records->serial;
				$row_content[$count++] = $row_get_records->refresh;
				$row_content[$count++] = $row_get_records->retry;
				$row_content[$count++] = $row_get_records->expire;
				$row_content[$count++] = $row_get_records->minimum;
				$row_content[$count++] = $row_get_records->nsdname;
				$row_content[$count++] = $row_get_records->name;
				$row_content[$count++] = $row_get_records->ttl;
				$row_content[$count++] = $row_get_records->class;
				$row_content[$count++] = $row_get_records->type;
				$row_content[$count++] = $row_get_records->address;
				$row_content[$count++] = $row_get_records->cname;
				$row_content[$count++] = $row_get_records->exchange;
				$row_content[$count++] = $row_get_records->preference;
				$row_content[$count++] = $row_get_records->txtdata;
				$row_content[$count++] = $row_get_records->line;
				$row_content[$count++] = $row_get_records->nlines;
				$row_content[$count++] = $row_get_records->raw;
				$row_content[$count++] = $row_get_records->insert_time;
				include("../../../_includes/system/export/write-row.inc.php");
	
			}
			
		}
	
	}

	include("../../../_includes/system/export/footer.inc.php");

}
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../../../_includes/layout/header.inc.php"); ?>
	<font class="subheadline"><?php echo $page_subtitle; ?></font><BR><BR><?php

$totalrows = mysqli_num_rows(mysqli_query($connection, $sql_dw_dns_zone_temp));
$layout = new DomainMOD\Layout();
$navigate = $layout->pageBrowser($totalrows, 15, 10, "&search_for=" . $search_for . "", $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
$sql_dw_dns_zone_temp = $sql_dw_dns_zone_temp.$navigate[0];
$result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);

if(mysqli_num_rows($result_dw_dns_zone_temp) == 0) {
	
	echo "Your search returned 0 results.";
	
} else { ?>

	<form name="form1" method="post" action="<?php echo $PHP_SELF; ?>">
		<input type="text" name="search_for" size="17" value="<?php echo $search_for; ?>">&nbsp;
		<input type="submit" name="button" value="Search &raquo;">
		<input type="hidden" name="begin" value="0">
		<input type="hidden" name="num" value="1">
		<input type="hidden" name="numBegin" value="1">
	</form><BR>
	
	<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&domain=<?php echo $domain; ?>&search_for=<?php echo $search_for; ?>">EXPORT</a>]</strong><BR><BR><?php
	
	if ($domain != "") {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										   WHERE domain = '" . $domain . "'
										     " . $where_clause_no_join . "";
	
	} else {
	
		if ($search_for != "") {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										   WHERE domain LIKE '%" . $search_for . "%'
										     " . $where_clause_no_join . "";
	
		} else {
	
			$sql_total_dns_record_count = "SELECT count(*) AS total_dns_record_count
										   FROM dw_dns_records
										     " . $where_clause_no_join_first_line . "";
	
		}
	
	}
	$result_total_dns_record_count = mysqli_query($connection, $sql_total_dns_record_count);
	
	while ($row_total_dns_record_count = mysqli_fetch_object($result_total_dns_record_count)) {
		
		$total_dns_record_count_temp = $row_total_dns_record_count->total_dns_record_count;
		
	} ?>

    <strong>Number of DNS Zones:</strong> <?php echo number_format($totalrows); ?><BR><BR>

	<strong>Number of DNS Records:</strong> <?php echo number_format($total_dns_record_count_temp); ?><BR><BR>
	<?php include("../../../_includes/layout/pagination.menu.inc.php"); ?><BR><?php
	// QUERY AT TOP OF PAGE
	// $sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
	//							FROM dw_dns_zones AS z, dw_servers AS s
	//							WHERE z.server_id = s.id
	//							  AND X
	//							ORDER BY s.name, z.zonefile, z.domain";
	// $result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);
	$from_main_dw_dns_zone_page = 1;
	include("../../../_includes/dw/display-dns-zone.inc.php");

}
?>
<?php include("../../../_includes/layout/pagination.menu.inc.php"); ?>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
