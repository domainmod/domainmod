<?php
// /registrar-accounts.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "Domains Registrar Accounts";
$software_section = "accounts";

// Form Variables
$rid = $_GET['rid'];
$raid = $_GET['raid'];
$oid = $_GET['oid'];
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php

if ($rid != "") { $rid_string = " AND ra.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND ra.id = '$raid' "; } else { $raid_string = ""; }
if ($oid != "") { $oid_string = " AND ra.owner_id = '$oid' "; } else { $oid_string = ""; }

$sql = "SELECT ra.id AS raid, ra.username, ra.owner_id, ra.registrar_id, ra.reseller, ra.default_account, o.id AS oid, o.name AS oname, r.id AS rid, r.name AS rname
		FROM registrar_accounts AS ra, owners AS o, registrars AS r, domains AS d
		WHERE ra.active = '1'
		  AND ra.owner_id = o.id
		  AND ra.registrar_id = r.id
		  AND ra.id = d.account_id
		  AND d.active not in ('0', '10')
		  $rid_string
		  $raid_string
		  $oid_string
		  AND (SELECT count(*) FROM domains WHERE account_id = ra.id AND active NOT IN ('0', '10')) > 0
		GROUP BY ra.username, oname, rname
		ORDER BY rname, username, oname";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the Domain Registrar Accounts that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = 1; ?>
    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Registrar Name</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Accounts (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Owner</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
    </tr>
    <?php 

    while ($row = mysql_fetch_object($result)) { 

	    $new_raid = $row->raid;
    
        if ($current_raid != $new_raid) {
			$exclude_account_string_raw .= "'$row->raid', ";
		} ?>

		<tr class="main_table_row_active">
			<td class="main_table_cell_active">
				<a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->rname?></a>
			</td>
			<td class="main_table_cell_active" valign="top">
				<a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->username?></a><?php if ($row->default_account == "1") echo "<a title=\"Default Account\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\"><strong>*</strong></font></a>"; ?>
			</td>
			<td class="main_table_cell_active">
				<a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->oname?></a>
			</td>
			<td class="main_table_cell_active">
				<?php
				$sql2 = "SELECT count(*) AS total_domain_count
						 FROM domains
						 WHERE account_id = '$row->raid'
						   AND active NOT IN ('0', '10')";
				$result2 = mysql_query($sql2,$connection);

				while ($row2 = mysql_fetch_object($result2)) { 
					echo "<a class=\"nobold\" href=\"domains.php?oid=$row->oid&rid=$row->rid&raid=$row->raid\">" . number_format($row2->total_domain_count) . "</a>"; 
				} ?>

			</td>
		</tr>
		<?php 
		$current_raid = $row->raid;
	
	} ?>
	<?php 
} ?>
<?php
$exclude_account_string = substr($exclude_account_string_raw, 0, -2); 

if ($exclude_account_string != "") { $raid_string = " AND ra.id not in ($exclude_account_string) "; } else { $raid_string = ""; }

$sql = "SELECT ra.id AS raid, ra.username, ra.owner_id, ra.registrar_id, ra.reseller, ra.default_account, o.id AS oid, o.name AS oname, r.id AS rid, r.name AS rname
		FROM registrar_accounts AS ra, owners AS o, registrars AS r
		WHERE ra.active = '1'
		  AND ra.owner_id = o.id
		  AND ra.registrar_id = r.id
		  $rid_string
		  $raid_string
		  $oid_string
		GROUP BY ra.username, oname, rname
		ORDER BY rname, username, oname";
$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">";
?>
    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Registrar Name</font>
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
                <a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->rname?></a>
            </td>
            <td class="main_table_cell_inactive" valign="top">
                    <a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->username?></a><?php if ($row->default_account == "1") echo "<a title=\"Default Account\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font class=\"reseller_highlight\"><strong>*</strong></font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->oname?></a>
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
                    FROM registrars
                    WHERE active = '1'
					LIMIT 1";
            $result = mysql_query($sql,$connection);

            if (mysql_num_rows($result) == 0) { 
			?>
                    <BR>Before adding a Registrar Account you must add at least one Registrar. <a href="add/registrar.php">Click here to add a Registrar</a>.<BR>
			<?php } else { ?>
                    <BR>You don't currently have any Registrar Accounts. <a href="add/account.php">Click here to add one</a>.<BR>
			<?php } ?>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>