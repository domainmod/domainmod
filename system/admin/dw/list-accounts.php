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
include("../../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "auth/admin-user-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();

$domain = $_GET['domain'];
$search_for = $_REQUEST['search_for'];
$export_data = $_GET['export_data'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($search_for != "") $domain = "";

$page_title = "Data Warehouse";
if ($_SESSION['dw_view_all'] == "1") {

	$page_subtitle = "Listing All Accounts";
	
} else {

	$page_subtitle = 'Listing Accounts on ' . $_SESSION['dw_server_name'] . ' (' . $_SESSION['dw_server_host'] . ')';
	
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

if ($export_data == "1") {

	$result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('dw_account_list');

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array($page_subtitle);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Number of Accounts:',
        number_format(mysqli_num_rows($result_dw_account_temp))
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($search_for != "") {

        $row_contents = array(
            'Keyword Search:',
            "\"" . $search_for . "\""
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }
	
	if ($domain != "") {

        $row_contents = array(
            'Domain Filter:',
            $domain
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    $row_contents = array(
        'Server Name',
        'Server Host',
        'Domain',
        'IP Address',
        'Owner',
        'User',
        'Email',
        'Plan',
        'Theme',
        'Shell',
        'Partition',
        'Disk Limit (MB)',
        'Disk Used (MB)',
        'Max Addons',
        'Max FTP',
        'Max Email Lists',
        'Max Parked Domains',
        'Max POP Accounts',
        'Max SQL Databases',
        'Max Subdomains',
        'Start Date',
        'Start Date (Unix)',
        'Suspended?',
        'Suspend Reason',
        'Suspend Time (Unix)',
        'Max Emails Per Hour',
        'Max Email Failure % (For Rate Limiting)',
        'Min Email Failure # (For Rate Limiting)',
        'Inserted (into DW)'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result_dw_account_temp) > 0) {

		while ($row_dw_account_temp = mysqli_fetch_object($result_dw_account_temp)) {

            $row_contents = array(
                $row_dw_account_temp->dw_server_name,
                $row_dw_account_temp->dw_server_host,
                $row_dw_account_temp->domain,
                $row_dw_account_temp->ip,
                $row_dw_account_temp->owner,
                $row_dw_account_temp->user,
                $row_dw_account_temp->email,
                $row_dw_account_temp->plan,
                $row_dw_account_temp->theme,
                $row_dw_account_temp->shell,
                $row_dw_account_temp->partition,
                $row_dw_account_temp->disklimit,
                $row_dw_account_temp->diskused,
                $row_dw_account_temp->maxaddons,
                $row_dw_account_temp->maxftp,
                $row_dw_account_temp->maxlst,
                $row_dw_account_temp->maxparked,
                $row_dw_account_temp->maxpop,
                $row_dw_account_temp->maxsql,
                $row_dw_account_temp->maxsub,
                $row_dw_account_temp->startdate,
                $row_dw_account_temp->unix_startdate,
                $row_dw_account_temp->suspended,
                $row_dw_account_temp->suspendreason,
                $row_dw_account_temp->suspendtime,
                $row_dw_account_temp->MAX_EMAIL_PER_HOUR,
                $row_dw_account_temp->MAX_DEFER_FAIL_PERCENTAGE,
                $row_dw_account_temp->MIN_DEFER_FAIL_TO_TRIGGER_PROTECTION,
                $row_dw_account_temp->insert_time
            );
            $export->writeRow($export_file, $row_contents);

        }
	
	}

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
	<font class="subheadline"><?php echo $page_subtitle; ?></font><BR><BR><?php

$totalrows = mysqli_num_rows(mysqli_query($connection, $sql_dw_account_temp));
$layout = new DomainMOD\Layout();
$navigate = $layout->pageBrowser($totalrows, 15, 10, "&search_for=" . $search_for . "", $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
$sql_dw_account_temp = $sql_dw_account_temp.$navigate[0];
$result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or $error->outputOldSqlError($connection);

if(mysqli_num_rows($result_dw_account_temp) == 0) {
	
	echo "Your search returned 0 results.";
	
} else { ?>

	<form name="form1" method="post">
		<input type="text" name="search_for" size="17" value="<?php echo $search_for; ?>">&nbsp;
		<input type="submit" name="button" value="Search &raquo;">
		<input type="hidden" name="begin" value="0">
		<input type="hidden" name="num" value="1">
		<input type="hidden" name="numBegin" value="1">
	</form><BR>
	
	<strong>[<a href="list-accounts.php?export_data=1&domain=<?php echo $domain; ?>&search_for=<?php echo $search_for; ?>">EXPORT</a>]</strong><BR><BR>
	
	<strong>Number of Accounts:</strong> <?php echo $totalrows; ?><BR><BR>
	<?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?><BR>
    <?php
    $from_main_dw_account_page = 1;
	include(DIR_INC . "dw/display-account.inc.php");

}
?>
<?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
