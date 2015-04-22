<?php
/**
 * /system/admin/dw/list-accounts.php
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
include("../../../_includes/system/functions/pagination.inc.php");

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

	$page_subtitle = "Listing All Accounts";
	
} else {

	$page_subtitle = "Listing Accounts on " . $_SESSION['dw_server_name'] . " (" . $_SESSION['dw_server_host'] . ")";
	
}
$software_section = "admin-dw-list-accounts";

if ($_SESSION['dw_view_all'] == "1") {
	
	$where_clause = "";
	
} else {

	$where_clause = "AND a.server_id = '" . $_SESSION['dw_server_id'] . "'";

}

if ($domain != "") {

		$sql_dw_account_temp = "SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								FROM dw_accounts AS a, dw_servers AS s
								WHERE a.server_id = s.id
								  AND a.domain = '" . $domain . "'
								  " . $where_clause . "
								ORDER BY s.name, a.unix_startdate DESC";

} else {

	if ($search_for != "") {

		$sql_dw_account_temp = "SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								FROM dw_accounts AS a, dw_servers AS s
								WHERE a.server_id = s.id
								  AND a.domain LIKE '%" . $search_for . "%'
								  " . $where_clause . "
								ORDER BY s.name, a.unix_startdate DESC";

	} else {

		$sql_dw_account_temp = "SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
								FROM dw_accounts AS a, dw_servers AS s
								WHERE a.server_id = s.id
								  " . $where_clause . "
								ORDER BY s.name, a.unix_startdate DESC";

	}

}

if ($export == "1") {

	$result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or $error->outputOldSqlError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "dw_account_list_" . $current_timestamp_unix . ".csv";
	include("../../../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../../../_includes/system/export/write-row.inc.php");

	$row_content[$count++] = $page_subtitle;
	include("../../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Number of Accounts:";
	$row_content[$count++] = number_format(mysqli_num_rows($result_dw_account_temp));
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
	$row_content[$count++] = "Server Host";
	$row_content[$count++] = "Domain";
	$row_content[$count++] = "IP Address";
	$row_content[$count++] = "Owner";
	$row_content[$count++] = "User";
	$row_content[$count++] = "Email";
	$row_content[$count++] = "Plan";
	$row_content[$count++] = "Theme";
	$row_content[$count++] = "Shell";
	$row_content[$count++] = "Partition";
	$row_content[$count++] = "Disk Limit (MB)";
	$row_content[$count++] = "Disk Used (MB)";
	$row_content[$count++] = "Max Addons";
	$row_content[$count++] = "Max FTP";
	$row_content[$count++] = "Max Email Lists";
	$row_content[$count++] = "Max Parked Domains";
	$row_content[$count++] = "Max POP Accounts";
	$row_content[$count++] = "Max SQL Databases";
	$row_content[$count++] = "Max Subdomains";
	$row_content[$count++] = "Start Date";
	$row_content[$count++] = "Start Date (Unix)";
	$row_content[$count++] = "Suspended?";
	$row_content[$count++] = "Suspend Reason";
	$row_content[$count++] = "Suspend Time (Unix)";
	$row_content[$count++] = "Max Emails Per Hour";
	$row_content[$count++] = "Max Email Failure % (For Rate Limiting)";
	$row_content[$count++] = "Min Email Failure # (For Rate Limiting)";
	$row_content[$count++] = "Inserted (into DW)";
	include("../../../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result_dw_account_temp) > 0) {

		while ($row_dw_account_temp = mysqli_fetch_object($result_dw_account_temp)) {

			$row_content[$count++] = $row_dw_account_temp->dw_server_name;
			$row_content[$count++] = $row_dw_account_temp->dw_server_host;
			$row_content[$count++] = $row_dw_account_temp->domain;
			$row_content[$count++] = $row_dw_account_temp->ip;
			$row_content[$count++] = $row_dw_account_temp->owner;
			$row_content[$count++] = $row_dw_account_temp->user;
			$row_content[$count++] = $row_dw_account_temp->email;
			$row_content[$count++] = $row_dw_account_temp->plan;
			$row_content[$count++] = $row_dw_account_temp->theme;
			$row_content[$count++] = $row_dw_account_temp->shell;
			$row_content[$count++] = $row_dw_account_temp->partition;
			$row_content[$count++] = $row_dw_account_temp->disklimit;
			$row_content[$count++] = $row_dw_account_temp->diskused;
			$row_content[$count++] = $row_dw_account_temp->maxaddons;
			$row_content[$count++] = $row_dw_account_temp->maxftp;
			$row_content[$count++] = $row_dw_account_temp->maxlst;
			$row_content[$count++] = $row_dw_account_temp->maxparked;
			$row_content[$count++] = $row_dw_account_temp->maxpop;
			$row_content[$count++] = $row_dw_account_temp->maxsql;
			$row_content[$count++] = $row_dw_account_temp->maxsub;
			$row_content[$count++] = $row_dw_account_temp->startdate;
			$row_content[$count++] = $row_dw_account_temp->unix_startdate;
			$row_content[$count++] = $row_dw_account_temp->suspended;
			$row_content[$count++] = $row_dw_account_temp->suspendreason;
			$row_content[$count++] = $row_dw_account_temp->suspendtime;
			$row_content[$count++] = $row_dw_account_temp->MAX_EMAIL_PER_HOUR;
			$row_content[$count++] = $row_dw_account_temp->MAX_DEFER_FAIL_PERCENTAGE;
			$row_content[$count++] = $row_dw_account_temp->MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION;
			$row_content[$count++] = $row_dw_account_temp->insert_time;
			include("../../../_includes/system/export/write-row.inc.php");

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

$totalrows = mysqli_num_rows(mysqli_query($connection, $sql_dw_account_temp));
$navigate = pageBrowser($totalrows, 15, 10, "&search_for=" . $search_for . "", $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
$sql_dw_account_temp = $sql_dw_account_temp.$navigate[0];
$result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or $error->outputOldSqlError($connection);

if(mysqli_num_rows($result_dw_account_temp) == 0) {
	
	echo "Your search returned 0 results.";
	
} else { ?>

	<form name="form1" method="post" action="<?php echo $PHP_SELF; ?>">
		<input type="text" name="search_for" size="17" value="<?php echo $search_for; ?>">&nbsp;
		<input type="submit" name="button" value="Search &raquo;">
		<input type="hidden" name="begin" value="0">
		<input type="hidden" name="num" value="1">
		<input type="hidden" name="numBegin" value="1">
	</form><BR>
	
	<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&domain=<?php echo $domain; ?>&search_for=<?php echo $search_for; ?>">EXPORT</a>]</strong><BR><BR>
	
	<strong>Number of Accounts:</strong> <?php echo $totalrows; ?><BR><BR>
	<?php include("../../../_includes/layout/pagination.menu.inc.php"); ?><BR>
    <?php
    $from_main_dw_account_page = 1;
	include("../../../_includes/dw/display-account.inc.php");

}
?>
<?php include("../../../_includes/layout/pagination.menu.inc.php"); ?>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
