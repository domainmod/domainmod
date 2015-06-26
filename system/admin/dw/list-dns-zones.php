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
include("../../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser();

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

	$page_subtitle = "Listing All DNS Zones & Records";
	
} else {

	$page_subtitle = 'Listing DNS Zones & Records on ' . $_SESSION['dw_server_name'] . ' (' . $_SESSION['dw_server_host'] . ')';
	
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

if ($export_data == "1") {

	$result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('dw_dns_zones', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array($page_subtitle);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

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

    $row_contents = array(
        'Number of DNS Zones:',
        number_format(mysqli_num_rows($result_dw_dns_zone_temp))
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        'Number of DNS Records:',
        number_format($total_dns_record_count_temp)
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
        'Host',
        'Domain',
        'DNS Zone File',
        'Original/Primary Zone Source',
        'Zone Admin Email',
        'Serial',
        'Zone Refresh TTL',
        'Retry Interval',
        'Zone Expiration',
        'Minimum Record TTL',
        'Authoritative Name Server',
        'DNS Record',
        'Record TTL',
        'Record Class',
        'Record Type',
        'IP Address',
        'Canonical Name',
        'Mail Server',
        'Mail Server Priority',
        'Text Record Data',
        'Zone Line #',
        'Number of Lines',
        'Raw Line Output',
        'Inserted (into DW)'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result_dw_dns_zone_temp) > 0) {

		while ($row_dw_dns_zone_temp = mysqli_fetch_object($result_dw_dns_zone_temp)) {

			$sql_get_records = "SELECT *
								FROM dw_dns_records
								WHERE server_id = '" . $row_dw_dns_zone_temp->dw_server_id . "'
								  AND domain = '" . $row_dw_dns_zone_temp->domain . "'
								ORDER BY new_order";
			$result_get_records = mysqli_query($connection, $sql_get_records);
			
			while ($row_get_records = mysqli_fetch_object($result_get_records)) {

                $row_contents = array(
                    $row_dw_dns_zone_temp->dw_server_name,
                    $row_dw_dns_zone_temp->dw_server_host,
                    $row_get_records->domain,
                    $row_get_records->zonefile,
                    $row_get_records->mname,
                    $row_get_records->rname,
                    $row_get_records->serial,
                    $row_get_records->refresh,
                    $row_get_records->retry,
                    $row_get_records->expire,
                    $row_get_records->minimum,
                    $row_get_records->nsdname,
                    $row_get_records->name,
                    $row_get_records->ttl,
                    $row_get_records->class,
                    $row_get_records->type,
                    $row_get_records->address,
                    $row_get_records->cname,
                    $row_get_records->exchange,
                    $row_get_records->preference,
                    $row_get_records->txtdata,
                    $row_get_records->line,
                    $row_get_records->nlines,
                    $row_get_records->raw,
                    $row_get_records->insert_time
                );
                $export->writeRow($export_file, $row_contents);

            }
			
		}
	
	}

    $export->closeFile($export_file);
    exit;

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
	<font class="subheadline"><?php echo $page_subtitle; ?></font><BR><BR><?php

$totalrows = mysqli_num_rows(mysqli_query($connection, $sql_dw_dns_zone_temp));
$layout = new DomainMOD\Layout();
$parameters = array($totalrows, 15, 10, "&search_for=" . $search_for . "", $_REQUEST[numBegin], $_REQUEST[begin], $_REQUEST[num]);
$navigate = $layout->pageBrowser($parameters);
$sql_dw_dns_zone_temp = $sql_dw_dns_zone_temp.$navigate[0];
$result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);

if(mysqli_num_rows($result_dw_dns_zone_temp) == 0) {
	
	echo "Your search returned 0 results.";
	
} else { ?>

	<form name="form1" method="post">
		<input type="text" name="search_for" size="17" value="<?php echo $search_for; ?>">&nbsp;
		<input type="submit" name="button" value="Search &raquo;">
		<input type="hidden" name="begin" value="0">
		<input type="hidden" name="num" value="1">
		<input type="hidden" name="numBegin" value="1">
	</form><BR>
	
	<strong>[<a href="list-dns-zones.php?export_data=1&domain=<?php echo $domain; ?>&search_for=<?php echo $search_for; ?>">EXPORT</a>]</strong><BR><BR><?php
	
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
	<?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?><BR><?php
	// QUERY AT TOP OF PAGE
	// $sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
	//							FROM dw_dns_zones AS z, dw_servers AS s
	//							WHERE z.server_id = s.id
	//							  AND X
	//							ORDER BY s.name, z.zonefile, z.domain";
	// $result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);
	$from_main_dw_dns_zone_page = 1;
	include(DIR_INC . "dw/display-dns-zone.inc.php");

}
?>
<?php include(DIR_INC . "layout/pagination.menu.inc.php"); ?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
