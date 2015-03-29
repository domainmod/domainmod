<?php
// /assets/categories.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Domain & SSL Categories";
$software_section = "categories";

$export = $_GET['export'];

$sql = "(SELECT c.id, c.name, c.stakeholder, c.notes, c.insert_time, c.update_time
		 FROM categories AS c, domains AS d
		 WHERE c.id = d.cat_id
		   AND d.active NOT IN ('0', '10')
		 GROUP BY c.name)
		UNION
		(SELECT c.id, c.name, c.stakeholder, c.notes, c.insert_time, c.update_time
		 FROM categories AS c, ssl_certs AS sslc
		 WHERE c.id = sslc.cat_id
		   AND sslc.active NOT IN ('0')
		 GROUP BY c.name)
		ORDER BY name";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "category_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "Category";
	$row_content[$count++] = "Stakeholder";
	$row_content[$count++] = "Domains";
	$row_content[$count++] = "SSL Certs";
	$row_content[$count++] = "Default Domain Category?";
	$row_content[$count++] = "Default SSL Category?";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result) > 0) {

		$has_active = "1";

		while ($row = mysqli_fetch_object($result)) {

			$new_pcid = $row->id;
		
			if ($current_pcid != $new_pcid) {
				$exclude_category_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE active NOT IN ('0', '10')
								  AND cat_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_domains = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_certs
								WHERE active NOT IN ('0')
								  AND cat_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_certs = $row_total_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_category_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_category_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}
	
			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->stakeholder;
			$row_content[$count++] = $total_domains;
			$row_content[$count++] = $total_certs;
			$row_content[$count++] = $is_default_domains;
			$row_content[$count++] = $is_default_ssl;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");
	
			$current_pcid = $row->id;

		}

	}

	$exclude_category_string = substr($exclude_category_string_raw, 0, -2); 
	
	if ($exclude_category_string == "") {
	
		$sql = "SELECT id, name, stakeholer, notes, insert_time, update_time
				FROM categories
				ORDER BY name asc";
	
	} else {
	
		$sql = "SELECT id, name, stakeholder, notes, insert_time, update_time
				FROM categories
				WHERE id NOT IN (" . $exclude_category_string . ")
				ORDER BY name asc";
	
	}
	
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	if (mysqli_num_rows($result) > 0) {
		
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->id == $_SESSION['default_category_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_category_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->stakeholder;
			$row_content[$count++] = 0;
			$row_content[$count++] = 0;
			$row_content[$count++] = $is_default_domains;
			$row_content[$count++] = $is_default_ssl;
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
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the Categories that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="<?php echo $PHP_SELF; ?>?export=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or die(mysqli_error());

if (mysqli_num_rows($result) > 0) {

	$has_active = "1"; ?>
	<table class="main_table" cellpadding="0" cellspacing="0">
	<tr class="main_table_row_heading_active">
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Active Categories (<?php echo mysqli_num_rows($result); ?>)</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Stakeholder</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Domains</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">SSL Certs</font>
		</td>
	</tr><?php 
	
	while ($row = mysqli_fetch_object($result)) {

		$new_pcid = $row->id;
	
		if ($current_pcid != $new_pcid) {
			$exclude_category_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_category_domains'] == $row->id) echo "<a title=\"Default Domain Category\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_category_ssl'] == $row->id) echo "<a title=\"Default SSL Category\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->stakeholder; ?></a>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM domains
									WHERE active NOT IN ('0', '10')
									  AND cat_id = '" . $row->id . "'";
				$result_total_count = mysqli_query($connection, $sql_total_count);
				while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					$total_domains = $row_total_count->total_count; 
				}
				
				if ($total_domains >= 1) { ?>
			
					<a class="nobold" href="../domains.php?pcid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_certs
									WHERE active NOT IN ('0')
									  AND cat_id = '" . $row->id . "'";
				$result_total_count = mysqli_query($connection, $sql_total_count);
				while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					$total_certs = $row_total_count->total_count; 
				}
				
				if ($total_certs >= 1) { ?>
			
					<a class="nobold" href="../ssl-certs.php?sslpcid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
        </tr><?php 

		$current_pcid = $row->id;

	}

}

if ($_SESSION['display_inactive_assets'] == "1") {

    $exclude_category_string = substr($exclude_category_string_raw, 0, -2);

    if ($exclude_category_string == "") {

        $sql = "SELECT id, name, stakeholder
                FROM categories
                ORDER BY name ASC";

    } else {

        $sql = "SELECT id, name, stakeholder
                FROM categories
                WHERE id NOT IN (" . $exclude_category_string . ")
                ORDER BY name ASC";

    }

    $result = mysqli_query($connection, $sql) or die(mysqli_error());

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";
        if ($has_active == "1") echo "<BR>";
        if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

        <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Categories (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Stakeholder</font>
        </td>
        </tr><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_category_domains'] == $row->id) echo "<a title=\"Default Domain Category\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_category_ssl'] == $row->id) echo "<a title=\"Default SSL Category\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->stakeholder; ?></a>
            </td>
            </tr><?php

        }

    }

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($_SESSION['display_inactive_assets'] != "1") { ?>
    <BR><em>Inactive Categories are currently not displayed. <a class="invisiblelink" href="../system/display-settings.php">Click here to display them</a>.</em><BR><?php
}

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default Domain Category&nbsp;&nbsp;<font class="default_highlight_secondary">*</font> = Default SSL Category<?php 
}

if (!$has_active && !$has_inactive) { ?>
	<BR><BR>You don't currently have any Categories. <a href="add/category.php">Click here to add one</a>.<?php 
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
