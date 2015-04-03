<?php
/**
 * /system/admin/ssl-fields.php
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
include("../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../invalid.php";
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Custom SSL Fields";
$software_section = "admin-ssl-fields";

$export = $_GET['export'];

$sql = "SELECT f.id, f.name, f.field_name, f.description, f.notes, f.insert_time, f.update_time, t.name AS type
		FROM ssl_cert_fields AS f, custom_field_types AS t
		WHERE f.type_id = t.id
		ORDER BY f.name";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "custom_ssl_field_list_" . $current_timestamp_unix . ".csv";
	include("../../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Display Name";
	$row_content[$count++] = "DB Field";
	$row_content[$count++] = "Data Type";
	$row_content[$count++] = "Description";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result) > 0) {

		while ($row = mysqli_fetch_object($result)) {

			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->field_name;
			$row_content[$count++] = $row->type;
			$row_content[$count++] = $row->description;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../../_includes/system/export/write-row.inc.php");

		}

	}

	include("../../_includes/system/export/footer.inc.php");

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
Below is a list of all the Custom SSL Fields that have been added to <?php echo $software_title; ?>.<BR><BR>
Custom SSL Fields help extend the functionality of <?php echo $software_title; ?> by allowing the user to create their own data fields. For example, if you were working in a corporate environment and wanted to keep a record of who purchased each of your SSL certificates, you could create a Purchaser Name text field and keep track of this information for every one of your SSL certificates. And when you export your SSL data, the information contained in your custom fields will automatically be included in the exported data.
<BR><BR><?php

$result = mysqli_query($connection, $sql) or die(mysqli_error());

if (mysqli_num_rows($result) > 0) { ?>

	[<a href="<?php echo $PHP_SELF; ?>?export=1">EXPORT</a>]

    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Display Name (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">DB Field</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Data Type</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Inserted</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Updated</font>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-field.php?csfid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-field.php?csfid=<?php echo $row->id; ?>"><?php echo $row->field_name; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-field.php?csfid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-field.php?csfid=<?php echo $row->id; ?>"><?php echo $row->insert_time; ?></a>
            </td>
            <td class="main_table_cell_active">
            	<?php 
				if ($row->update_time == "0000-00-00 00:00:00") {
					
					$temp_update_time = "n/a";
					
				} else {
					
					$temp_update_time = $row->update_time;
					
				}
				?>
                <a class="invisiblelink" href="edit/ssl-field.php?csfid=<?php echo $row->id; ?>"><?php echo $temp_update_time; ?></a>
            </td>
        </tr><?php 
	}
		
} else { ?>
	
	It appears as though you haven't created any Custom SSL Fields yet. <a href="add/ssl-field.php">Click here</a> to add one.<?php 

} ?>
</table>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
