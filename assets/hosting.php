<?php
// /assets/hosting.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Web Hosting Providers";
$software_section = "hosting";

$export = $_GET['export'];

$sql = "SELECT id, name, url, notes, insert_time, update_time
		FROM hosting
		WHERE id IN (SELECT hosting_id 
					 FROM domains 
					 WHERE hosting_id != '0' 
					   AND active NOT IN ('0','10') 
					 GROUP BY hosting_id)
		ORDER BY name";

if ($export == "1") {

	$result = mysql_query($sql,$connection) or die(mysql_error());

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "web_hosting_provider_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "Web Host";
	$row_content[$count++] = "Domains";
	$row_content[$count++] = "Default Web Host?";
	$row_content[$count++] = "URL";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

	if (mysql_num_rows($result) > 0) {
		
		$has_active = "1";

		while ($row = mysql_fetch_object($result)) {
	
			$new_whid = $row->id;
		
			if ($current_whid != $new_whid) {
				$exclude_web_host_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE hosting_id = '" . $row->id . "'
								  AND active NOT IN ('0', '10')";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$active_domains = $row_total_count->total_count; 
			}

			if ($row->id == $_SESSION['default_host']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = number_format($active_domains);
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $row->url;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");

			$current_whid = $row->id;
	
		}
	
	}
	
	$exclude_web_host_string = substr($exclude_web_host_string_raw, 0, -2); 
	
	if ($exclude_web_host_string == "") {
	
		$sql = "SELECT id, name, url, notes, insert_time, update_time
				FROM hosting
				ORDER BY name";
	
	} else {
	
		$sql = "SELECT id, name, url, notes, insert_time, update_time
				FROM hosting
				WHERE id NOT IN (" . $exclude_web_host_string . ")
				ORDER BY name";
	
	}
	
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysql_fetch_object($result)) {

			if ($row->id == $_SESSION['default_host']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = 0;
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $row->url;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");

		}
	
	}

	include("../_includes/system/export/footer.inc.php");

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
<?php
?>
Below is a list of all the Web Hosting Providers that are stored in your <?=$software_title?>.<BR><BR>
[<a href="<?=$PHP_SELF?>?export=1">EXPORT</a>]<?php

$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_hosting_providers = mysql_num_rows($result);

if (mysql_num_rows($result) > 0) {
	
	$has_active = "1"; ?>
    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">Active Hosts (<?=$number_of_hosting_providers?>)</font>
		</td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Options</font>
        </td>
    </tr><?php 
	
	while ($row = mysql_fetch_object($result)) {

	    $new_whid = $row->id;
    
        if ($current_whid != $new_whid) {
			$exclude_web_host_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/host.php?whid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_host'] == $row->id) echo "<a title=\"Default Web Host\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM domains
									WHERE hosting_id = '" . $row->id . "'
									  AND active NOT IN ('0', '10')";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$active_domains = $row_total_count->total_count; 
				}
				
				if ($active_domains == "0") {
					
					echo number_format($active_domains);
					
				} else { ?>
	
					<a class="nobold" href="../domains.php?whid=<?=$row->id?>"><?=number_format($active_domains)?></a><?php 
					
				} ?>
            </td>
            <td class="main_table_cell_active">
				<a class="invisiblelink" target="_blank" href="<?=$row->url?>">www</a>
            </td>
        </tr><?php 

		$current_whid = $row->id;

	}

}

$exclude_web_host_string = substr($exclude_web_host_string_raw, 0, -2); 

if ($exclude_web_host_string == "") {

	$sql = "SELECT id, name, url, notes, insert_time, update_time
			FROM hosting
			ORDER BY name";

} else {

	$sql = "SELECT id, name, url, notes, insert_time, update_time
			FROM hosting
			WHERE id NOT IN (" . $exclude_web_host_string . ")
			ORDER BY name";

}

$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_hosting_providers = mysql_num_rows($result);

if (mysql_num_rows($result) > 0) { 

	$has_inactive = "1";
	if ($has_active == "1") echo "<BR>";
	if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

	<tr class="main_table_row_heading_inactive">
		<td class="main_table_cell_heading_inactive">
			<font class="main_table_heading">Inactive Hosts (<?=$number_of_hosting_providers?>)</font>
		</td>
		<td class="main_table_cell_heading_inactive">
			<font class="main_table_heading">Options</font>
		</td>
	</tr><?php 
	
	while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/host.php?whid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_host'] == "1") echo "<a title=\"Default Web Host\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
				<a class="invisiblelink" target="_blank" href="<?=$row->url?>">www</a>
            </td>
        </tr><?php 
		
	}

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default Web Host<?php
}

if (!$has_active && !$has_inactive) { ?>
	<BR>You don't currently have any Web Hosting Providers. <a href="add/host.php">Click here to add one</a>.<?php
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>