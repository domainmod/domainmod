<?php
/**
 * /assets/ssl-accounts.php
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
include("../_includes/system/functions/error-reporting.inc.php");

$page_title = "SSL Provider Accounts";
$software_section = "ssl-provider-accounts";

$sslpid = $_GET['sslpid'];
$sslpaid = $_GET['sslpaid'];
$oid = $_GET['oid'];
$export = $_GET['export'];

if ($sslpid != "") { $sslpid_string = " AND sa.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sa.id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($oid != "") { $oid_string = " AND sa.owner_id = '$oid' "; } else { $oid_string = ""; }

$sql = "SELECT sa.id AS sslpaid, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller, o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes, sa.insert_time, sa.update_time
		FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp, ssl_certs as sslc
		WHERE sa.owner_id = o.id
		  AND sa.ssl_provider_id = sslp.id
		  AND sa.id = sslc.account_id
		  AND sslc.active not in ('0')
		  $sslpid_string
		  $sslpaid_string
		  $oid_string
		  AND (SELECT count(*) 
		  	   FROM ssl_certs 
			   WHERE account_id = sa.id 
			     AND active NOT IN ('0')) 
				 > 0
		GROUP BY sa.username, oname, sslpname
		ORDER BY sslpname, username, oname";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "ssl_provider_account_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "SSL Provider";
	$row_content[$count++] = "Username";
	$row_content[$count++] = "Password";
	$row_content[$count++] = "Owner";
	$row_content[$count++] = "SSL Certs";
	$row_content[$count++] = "Default Account?";
	$row_content[$count++] = "Reseller Account?";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result) > 0) {
		
		$has_active = 1;
	
		while ($row = mysqli_fetch_object($result)) { 
	
			$new_sslpaid = $row->sslpaid;
		
			if ($current_sslpaid != $new_sslpaid) {
				$exclude_account_string_raw .= "'" . $row->sslpaid . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_cert_count
								FROM ssl_certs
								WHERE account_id = '$row->sslpaid'
								  AND active NOT IN ('0')";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_cert_count = mysqli_fetch_object($result_total_count)) {
				$total_cert_count = $row_cert_count->total_cert_count;
			}
	
			if ($row->sslpaid == $_SESSION['default_ssl_provider_account']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}
			
			if ($row->reseller == "0") {
				
				$is_reseller = "";
				
			} else {
				
				$is_reseller = "1";
	
			}

			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->sslpname;
			$row_content[$count++] = $row->username;
			$row_content[$count++] = $row->password;
			$row_content[$count++] = $row->oname;
			$row_content[$count++] = $total_cert_count;
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $is_reseller;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");

			$current_sslpaid = $row->sslpaid;
		
		}
		
	}
	
	$exclude_account_string = substr($exclude_account_string_raw, 0, -2); 
	
	if ($exclude_account_string != "") { 
	
		$sslpaid_string = " AND sa.id not in (" . $exclude_account_string . ") "; 
		
	} else { 
	
		$sslpaid_string = ""; 
	
	}
	
	$sql = "SELECT sa.id AS sslpaid, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller, o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes, sa.insert_time, sa.update_time
			FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
			WHERE sa.owner_id = o.id
			  AND sa.ssl_provider_id = sslp.id
			  " . $sslpid_string . "
			  " . $sslpaid_string . "
			  " . $oid_string . "
			GROUP BY sa.username, oname, sslpname
			ORDER BY sslpname, username, oname";
	$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);
	
	if (mysqli_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->sslpaid == $_SESSION['default_ssl_provider_account']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}
			
			if ($row->reseller == "0") {
				
				$is_reseller = "";
				
			} else {
				
				$is_reseller = "1";
	
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->sslpname;
			$row_content[$count++] = $row->username;
			$row_content[$count++] = $row->password;
			$row_content[$count++] = $row->oname;
			$row_content[$count++] = 0;
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $is_reseller;
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
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the SSL Provider Accounts that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="<?php echo $PHP_SELF; ?>?export=1&sslpid=<?php echo $sslpid; ?>&sslpaid=<?php echo $sslpaid; ?>&oid=<?php echo $oid; ?>">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

if (mysqli_num_rows($result) > 0) {
	
	$has_active = 1; ?>
    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Provider</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Accounts (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Owner</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Certs</font>
        </td>
    </tr><?php 

    while ($row = mysqli_fetch_object($result)) { 

	    $new_sslpaid = $row->sslpaid;
    
        if ($current_sslpaid != $new_sslpaid) {
			$exclude_account_string_raw .= "'" . $row->sslpaid . "', ";
		} ?>

		<tr class="main_table_row_active">
			<td class="main_table_cell_active">
				<a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->sslpname; ?></a>
			</td>
			<td class="main_table_cell_active">
				<a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->username; ?></a><?php if ($_SESSION['default_ssl_provider_account'] == $row->sslpaid) echo "<a title=\"Default Account\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\">*</font></a>"; ?>
			</td>
			<td class="main_table_cell_active">
				<a class="invisiblelink" href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->oname; ?></a>
			</td>
			<td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_cert_count
									FROM ssl_certs
									WHERE account_id = '$row->sslpaid'
									  AND active NOT IN ('0')";
				$result_total_count = mysqli_query($connection, $sql_total_count);

				while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					echo "<a class=\"nobold\" href=\"../ssl-certs.php?oid=$row->oid&sslpid=$row->sslpid&sslpaid=$row->sslpaid\">" . number_format($row_total_count->total_cert_count) . "</a>"; 
				} ?>
			</td>
		</tr><?php 

		$current_sslpaid = $row->sslpaid;
	
	}
	
}

if ($_SESSION['display_inactive_assets'] == "1") {

    $exclude_account_string = substr($exclude_account_string_raw, 0, -2);

    if ($exclude_account_string != "") {

        $sslpaid_string = " AND sa.id not in ($exclude_account_string) ";

    } else {

        $sslpaid_string = "";

    }

    $sql = "SELECT sa.id AS sslpaid, sa.username, sa.owner_id, sa.ssl_provider_id, sa.reseller, o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname
            FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
            WHERE sa.owner_id = o.id
              AND sa.ssl_provider_id = sslp.id
              " . $sslpid_string . "
              " . $sslpaid_string . "
              " . $oid_string . "
            GROUP BY sa.username, oname, sslpname
            ORDER BY sslpname, username, oname";
    $result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";
        if ($has_active == "1") echo "<BR>";
        if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

        <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">SSL Provider</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Accounts (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Owner</font>
        </td>
        <td class="main_table_cell_heading_inactive">&nbsp;

        </td>
        </tr><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->sslpname; ?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->username; ?></a><?php if ($_SESSION['default_ssl_provider_account'] == $row->sslpaid) echo "<a title=\"Default Account\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->oname; ?></a>
            </td>
            <td class="main_table_cell_inactive">&nbsp;

            </td>
            </tr><?php

        }

    }

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($_SESSION['display_inactive_assets'] != "1") { ?>
    <BR><em>Inactive Accounts are currently not displayed. <a class="invisiblelink" href="../system/display-settings.php">Click here to display them</a>.</em><BR><?php
}

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default Account&nbsp;&nbsp;<font class="reseller_highlight">*</font> = Reseller Account<?php 
}

if (!$has_active && !$has_inactive) {
	
	$sql = "SELECT id
			FROM ssl_providers
			LIMIT 1";
	$result = mysqli_query($connection, $sql);

	if (mysqli_num_rows($result) == 0) { ?>

		<BR>Before adding an SSL Provider Account you must add at least one SSL Provider. <a href="add/ssl-provider.php">Click here to add an SSL Provider</a>.<BR><?php 

	} else { ?>

		<BR>You don't currently have any SSL Provider Accounts. <a href="add/ssl-provider-account.php">Click here to add one</a>.<BR><?php 

	}

} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
