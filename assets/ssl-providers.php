<?php
/**
 * /assets/ssl-providers.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/classes/Error.class.php");
include("../_includes/classes/Export.class.php");

$error = new DomainMOD\Error();

$page_title = "SSL Certificate Providers";
$software_section = "ssl-providers";

$export_data = $_GET['export_data'];

$sql = "SELECT id, name, url, notes, insert_time, update_time
		FROM ssl_providers
		WHERE id IN (SELECT ssl_provider_id 
					 FROM ssl_certs 
					 WHERE ssl_provider_id != '0' 
					   AND active != '0' 
					 GROUP BY ssl_provider_id)
		ORDER BY name asc";

if ($export_data == "1") {

	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_provider_list');

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'SSL Provider',
        'Accounts',
        'SSL Certs',
        'Default SSL Provider?',
        'URL',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {
	
		$has_active = "1";

		while ($row = mysqli_fetch_object($result)) {

			$new_sslpid = $row->id;
		
			if ($current_sslpid != $new_sslpid) {
				$exclude_ssl_provider_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_accounts
								WHERE ssl_provider_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_accounts = $row_total_count->total_count; 
			}
	
			$sql_cert_count = "SELECT count(*) AS total_count
							   FROM ssl_certs
							   WHERE active != '0'
								 AND ssl_provider_id = '" . $row->id . "'";
			$result_cert_count = mysqli_query($connection, $sql_cert_count);
			while ($row_cert_count = mysqli_fetch_object($result_cert_count)) { 
				$total_certs = $row_cert_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_ssl_provider']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

            $row_contents = array(
                'Active',
                $row->name,
                $total_accounts,
                $total_certs,
                $is_default,
                $row->url,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

            $current_sslpid = $row->id;
	
		}
		
	}

	$exclude_ssl_provider_string = substr($exclude_ssl_provider_string_raw, 0, -2); 

	if ($exclude_ssl_provider_string == "") {
	
		$sql = "SELECT id, name, url, notes, insert_time, update_time
				FROM ssl_providers
				ORDER BY name asc";
	
	} else {
		
		$sql = "SELECT id, name, url, notes, insert_time, update_time
				FROM ssl_providers
				WHERE id NOT IN (" . $exclude_ssl_provider_string . ")
				ORDER BY name asc";

	}
	
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
	if (mysqli_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_accounts
								WHERE ssl_provider_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_accounts = $row_total_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_ssl_provider']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

            $row_contents = array(
                'Inactive',
                $row->name,
                $total_accounts,
                '0',
                $is_default,
                $row->url,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }
	
	}

    $export->closeFile($export_file);

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the SSL Certificate Providers that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="<?php echo $PHP_SELF; ?>?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) {

	$has_active = "1"; ?>
    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Providers (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Accounts</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Certs</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Options</font>
        </td>
    </tr><?php 

	while ($row = mysqli_fetch_object($result)) {

	    $new_sslpid = $row->id;
    
        if ($current_sslpid != $new_sslpid) {
			$exclude_ssl_provider_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-provider.php?sslpid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_ssl_provider'] == $row->id) echo "<a title=\"Default SSL Provider\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active"><?php
                $sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_accounts
									WHERE ssl_provider_id = '" . $row->id . "'";
                $result_total_count = mysqli_query($connection, $sql_total_count);
                while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					$total_accounts = $row_total_count->total_count; 
				}
				
				if ($total_accounts >= 1) { ?>
        
                    <a class="nobold" href="ssl-accounts.php?sslpid=<?php echo $row->id; ?>"><?php echo number_format($total_accounts); ?></a><?php
        
                } else {
					
					echo number_format($total_accounts);
					
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
                $sql_cert_count = "SELECT count(*) AS total_count
								   FROM ssl_certs
								   WHERE active != '0'
								     AND ssl_provider_id = '" . $row->id . "'";
                $result_cert_count = mysqli_query($connection, $sql_cert_count);
                while ($row_cert_count = mysqli_fetch_object($result_cert_count)) { 
					$total_certs = $row_cert_count->total_count; 
				}
				
				if ($total_certs >= 1) { ?>
        
                    <a class="nobold" href="../ssl-certs.php?sslpid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php
				
				} else {
					
					echo number_format($total_certs);
					
				} ?>
            </td>
            <td class="main_table_cell_active">
				<a class="invisiblelink" href="edit/ssl-provider-fees.php?sslpid=<?php echo $row->id; ?>">fees</a>&nbsp;&nbsp;<a class="invisiblelink" target="_blank" href="<?php echo $row->url; ?>">www</a>
            </td>
        </tr><?php 

		$current_sslpid = $row->id;

	}
	
}

if ($_SESSION['display_inactive_assets'] == "1") {

    $exclude_ssl_provider_string = substr($exclude_ssl_provider_string_raw, 0, -2);

    if ($exclude_ssl_provider_string == "") {

        $sql = "SELECT id, name, url, notes, insert_time, update_time
                FROM ssl_providers
                ORDER BY name ASC";

    } else {

        $sql = "SELECT id, name, url, notes, insert_time, update_time
                FROM ssl_providers
                WHERE id NOT IN (" . $exclude_ssl_provider_string . ")
                ORDER BY name ASC";

    }

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";
        if ($has_active == "1") echo "<BR>";
        if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

        <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Providers (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Accounts</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Options</font>
        </td>
        </tr><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ssl-provider.php?sslpid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_ssl_provider'] == $row->id) echo "<a title=\"Default SSL Provider\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive"><?php
                $sql_total_count = "SELECT count(*) AS total_count
                                        FROM ssl_accounts
                                        WHERE ssl_provider_id = '" . $row->id . "'";
                $result_total_count = mysqli_query($connection, $sql_total_count);
                while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                    $total_accounts = $row_total_count->total_count;
                }

                if ($total_accounts >= 1) { ?>

                    <a class="nobold"
                       href="ssl-accounts.php?sslpid=<?php echo $row->id; ?>"><?php echo number_format($total_accounts); ?></a><?php

                } else {

                    echo number_format($total_accounts);

                } ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/ssl-provider-fees.php?sslpid=<?php echo $row->id; ?>">fees</a>&nbsp;&nbsp;<a
                    class="invisiblelink" target="_blank" href="<?php echo $row->url; ?>">www</a>
            </td>
            </tr><?php

        }

    }

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($_SESSION['display_inactive_assets'] != "1") { ?>
    <BR><em>Inactive Providers are currently not displayed. <a class="invisiblelink" href="../system/display-settings.php">Click here to display them</a>.</em><BR><?php
}

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default SSL Provider<?php
}

if (!$has_active && !$has_inactive) { ?>
	<BR>You don't currently have any SSL Providers. <a href="add/ssl-provider.php">Click here to add one</a>.<?php
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
