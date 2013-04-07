<?php
// ssl-accounts.php
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
session_start();

include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "SSL Provider Accounts";
$software_section = "ssl-accounts";

// Form Variables
$sslpid = $_GET['sslpid'];
$sslpaid = $_GET['sslpaid'];
$oid = $_GET['oid'];
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php

if ($sslpid != "") { $sslpid_string = " AND sa.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($sslpaid != "") { $sslpaid_string = " AND sa.id = '$sslpaid' "; } else { $sslpaid_string = ""; }
if ($oid != "") { $oid_string = " AND sa.owner_id = '$oid' "; } else { $oid_string = ""; }

$sql = "SELECT sa.id AS sslpaid, sa.username, sa.owner_id, sa.ssl_provider_id, sa.reseller, sa.default_account, o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname
		FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp, ssl_certs as sslc
		WHERE sa.active = '1'
		  AND sa.owner_id = o.id
		  AND sa.ssl_provider_id = sslp.id
		  AND sa.id = sslc.account_id
		  AND sslc.active not in ('0')
		  $sslpid_string
		  $sslpaid_string
		  $oid_string
		  AND (SELECT count(*) FROM ssl_certs WHERE account_id = sa.id AND active NOT IN ('0')) > 0
		GROUP BY sa.username, oname, sslpname
		ORDER BY sslpname, username, oname";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the SSL Provider Accounts that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = 1; ?>
    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Provider</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Accounts (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Owner</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Certs</font>
        </td>
    </tr>
    <?php 

    while ($row = mysql_fetch_object($result)) { 

	    $new_sslpaid = $row->sslpaid;
    
        if ($current_sslpaid != $new_sslpaid) {
			$exclude_account_string_raw .= "'$row->sslpaid', ";
		} ?>

		<tr class="main_table_row_active">
			<td class="main_table_cell_active">
				<a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->sslpname?></a>
			</td>
			<td class="main_table_cell_active">
				<a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->username?></a><?php if ($row->default_account == "1") echo "<a title=\"Default Account\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\"><strong>*</strong></font></a>"; ?>
			</td>
			<td class="main_table_cell_active">
				<a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->oname?></a>
			</td>
			<td class="main_table_cell_active">
				<?php
				$sql2 = "SELECT count(*) AS total_cert_count
						 FROM ssl_certs
						 WHERE account_id = '$row->sslpaid'
						   AND active NOT IN ('0')";
				$result2 = mysql_query($sql2,$connection);

				while ($row2 = mysql_fetch_object($result2)) { 
					echo "<a class=\"nobold\" href=\"ssl-certs.php?oid=$row->oid&sslpid=$row->sslpid&sslpaid=$row->sslpaid\">" . number_format($row2->total_cert_count) . "</a>"; 
				} ?>

			</td>
		</tr>
		<?php 
		$current_sslpaid = $row->sslpaid;
	
	} ?>
	<?php 
} ?>
<?php
$exclude_account_string = substr($exclude_account_string_raw, 0, -2); 

if ($exclude_account_string != "") { $sslpaid_string = " AND sa.id not in ($exclude_account_string) "; } else { $sslpaid_string = ""; }

$sql = "SELECT sa.id AS sslpaid, sa.username, sa.owner_id, sa.ssl_provider_id, sa.reseller, sa.default_account, o.id AS oid, o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname
		FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
		WHERE sa.active = '1'
		  AND sa.owner_id = o.id
		  AND sa.ssl_provider_id = sslp.id
		  $sslpid_string
		  $sslpaid_string
		  $oid_string
		GROUP BY sa.username, oname, sslpname
		ORDER BY sslpname, username, oname";
$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">";
?>
    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">SSL Provider</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Accounts (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Owner</font>
        </td>
        <td class="main_table_cell_heading_inactive">&nbsp;
            
        </td>
    </tr>
    <?php 

	while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->sslpname?></a>
            </td>
            <td class="main_table_cell_inactive">
                    <a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->username?></a><?php if ($row->default_account == "1") echo "<a title=\"Default Account\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\"><strong>*</strong></font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->sslpaid?>"><?=$row->oname?></a>
            </td>
            <td class="main_table_cell_inactive">&nbsp;
                
            </td>
        </tr>
        <?php 

	} ?>
	<?php 
} ?>
<?php
if ($has_active == "1" || $has_inactive == "1") echo "</table>";
?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight"><strong>*</strong></font> = Default Account&nbsp;&nbsp;<font class="reseller_highlight"><strong>*</strong></font> = Reseller Account
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
			<?php
            $sql = "SELECT id
                    FROM ssl_providers
                    WHERE active = '1'
					LIMIT 1";
            $result = mysql_query($sql,$connection);

            if (mysql_num_rows($result) == 0) { 
			?>
                    <BR>Before adding an SSL Provider Account you must add at least one SSL Provider. <a href="add/ssl-provider.php">Click here to add an SSL Provider</a>.<BR>
			<?php } else { ?>
                    <BR>You don't currently have any SSL Provider Accounts. <a href="add/ssl-account.php">Click here to add one</a>.<BR>
			<?php } ?>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>