<?php
// /assets/account-owners.php
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

$page_title = "Domain Registrar & SSL Provider Account Owners";
$software_section = "account-owners";

$export = $_GET['export'];

$sql = "(SELECT o.id, o.name, o.notes, o.insert_time, o.update_time
		 FROM owners AS o, domains AS d
		 WHERE o.id = d.owner_id
		   AND d.active NOT IN ('0', '10')
		 GROUP BY o.name)
		UNION
		(SELECT o.id, o.name, o.notes, o.insert_time, o.update_time
		 FROM owners AS o, ssl_certs AS sslc
		 WHERE o.id = sslc.owner_id
		   AND sslc.active NOT IN ('0')
		 GROUP BY o.name)
		ORDER BY name";

if ($export == "1") {

	$result = mysql_query($sql,$connection) or die(mysql_error());

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "account_owner_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "Owner";
	$row_content[$count++] = "Registrar Accounts";
	$row_content[$count++] = "Domains";
	$row_content[$count++] = "SSL Provider Accounts";
	$row_content[$count++] = "SSL Certs";
	$row_content[$count++] = "Default Domain Owner?";
	$row_content[$count++] = "Default SSL Owner?";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

	if (mysql_num_rows($result) > 0) {
	
		$has_active = "1";
	
		while ($row = mysql_fetch_object($result)) {
	
			$new_oid = $row->id;
		
			if ($current_oid != $new_oid) {
				$exclude_owner_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM registrar_accounts
								WHERE owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_registrar_accounts = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE active NOT IN ('0', '10')
								  AND owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_domains = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_accounts
								WHERE owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_ssl_provider_accounts = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_certs
								WHERE active NOT IN ('0')
								  AND owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_certs = $row_total_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_owner_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_owner_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}

			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $total_registrar_accounts;
			$row_content[$count++] = $total_domains;
			$row_content[$count++] = $total_ssl_provider_accounts;
			$row_content[$count++] = $total_certs;
			$row_content[$count++] = $is_default_domains;
			$row_content[$count++] = $is_default_ssl;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");
	
			$current_oid = $row->id;
	
		}
	
	}

	$exclude_owner_string = substr($exclude_owner_string_raw, 0, -2); 
	
	if ($exclude_owner_string == "") {
	
		$sql = "SELECT id, name, notes, insert_time, update_time
				FROM owners
				ORDER BY name asc";
	
	} else {
	
		$sql = "SELECT id, name, notes, insert_time, update_time
				FROM owners
				WHERE id NOT IN (" . $exclude_owner_string . ")
				ORDER BY name asc";
	
	}
	
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result) > 0) {
		
		$has_inactive = "1";
	
		while ($row = mysql_fetch_object($result)) {
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM registrar_accounts
								WHERE owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_registrar_accounts = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_accounts
								WHERE owner_id = '" . $row->id . "'";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) { 
				$total_ssl_provider_accounts = $row_total_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_owner_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_owner_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $total_registrar_accounts;
			$row_content[$count++] = 0;
			$row_content[$count++] = $total_ssl_provider_accounts;
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
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the Account Owners that are stored in your <?=$software_title?>.<BR><BR>
[<a href="<?=$PHP_SELF?>?export=1">EXPORT</a>]<?php

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) {

	$has_active = "1"; ?>
	<table class="main_table" cellpadding="0" cellspacing="0">
	<tr class="main_table_row_heading_active">
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Active Owners (<?=mysql_num_rows($result)?>)</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Registrar<BR>Accounts</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Domains</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">SSL Provider<BR>Accounts</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">SSL Certs</font>
		</td>
	</tr><?php 
	
	while ($row = mysql_fetch_object($result)) {

		$new_oid = $row->id;
	
		if ($current_oid != $new_oid) {
			$exclude_owner_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_owner_domains'] == $row->id) echo "<a title=\"Default Domain Owner\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_owner_ssl'] == $row->id) echo "<a title=\"Default SSL Owner\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM registrar_accounts
									WHERE owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_accounts = $row_total_count->total_count; 
				}
				
				if ($total_accounts >= 1) { ?>
			
					<a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a><?php 
					
				} else {
					
					echo "-";
	
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM domains
									WHERE active NOT IN ('0', '10')
									  AND owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_domains = $row_total_count->total_count; 
				}
				
				if ($total_domains >= 1) { ?>
			
					<a class="nobold" href="../domains.php?oid=<?=$row->id?>"><?=number_format($total_domains)?></a><?php 
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_accounts
									WHERE owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_accounts = $row_total_count->total_count; 
				}
				
				if ($total_accounts >= 1) { ?>
			
					<a class="nobold" href="ssl-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a><?php 
					
				} else {
					
					echo "-";
	
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_certs
									WHERE active NOT IN ('0')
									  AND owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_certs = $row_total_count->total_count; 
				}
				
				if ($total_certs >= 1) { ?>
			
					<a class="nobold" href="../ssl-certs.php?oid=<?=$row->id?>"><?=number_format($total_certs)?></a><?php 
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
        </tr><?php 

		$current_oid = $row->id;

	}

}

$exclude_owner_string = substr($exclude_owner_string_raw, 0, -2); 

if ($exclude_owner_string == "") {

	$sql = "SELECT id, name
			FROM owners
			ORDER BY name asc";

} else {

	$sql = "SELECT id, name
			FROM owners
			WHERE id NOT IN (" . $exclude_owner_string . ")
			ORDER BY name asc";

}

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) {
	
	$has_inactive = "1";
	if ($has_active == "1") echo "<BR>";
	if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Owners (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Registrar<BR>Accounts</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">SSL Provider<BR>Accounts</font>
        </td>
    </tr><?php 
	
	while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_owner_domains'] == $row->id) echo "<a title=\"Default Domain Owner\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_owner_ssl'] == $row->id) echo "<a title=\"Default SSL Owner\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM registrar_accounts
									WHERE owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_accounts = $row_total_count->total_count; 
				}
				
				if ($total_accounts >= 1) { ?>
			
						<a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a><?php 
				} else {
					
					echo "-";
					
				} ?>
            </td>
            <td class="main_table_cell_inactive"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_accounts
									WHERE owner_id = '" . $row->id . "'";
				$result_total_count = mysql_query($sql_total_count,$connection);
				while ($row_total_count = mysql_fetch_object($result_total_count)) { 
					$total_accounts = $row_total_count->total_count; 
				}
				
				if ($total_accounts >= 1) { ?>
			
						<a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a><?php 
				} else {
					
					echo "-";
					
				} ?>
            </td>
        </tr><?php 

	}

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default Domain Owner&nbsp;&nbsp;<font class="default_highlight_secondary">*</font> = Default SSL Owner<?php 
}

if (!$has_active && !$has_inactive) { ?>
	<BR><BR>You don't currently have any Owners. <a href="add/account-owner.php">Click here to add one</a>.<?php 
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>