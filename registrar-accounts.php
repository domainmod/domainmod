<?php
// registrar-accounts.php
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

$page_title = "Registrar Accounts";
$software_section = "accounts";

// Form Variables
$rid = $_GET['rid'];
$raid = $_GET['raid'];
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

if ($rid != "") { $rid_string = " AND ra.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " AND ra.id = '$raid' "; } else { $raid_string = ""; }
if ($oid != "") { $oid_string = " AND ra.owner_id = '$oid' "; } else { $oid_string = ""; }

$sql = "SELECT ra.id AS raid, ra.username, ra.owner_id, ra.registrar_id, ra.reseller, o.id AS oid, o.name AS oname, r.id AS rid, r.name AS rname
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
		ORDER BY rname asc";

$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the Domain Registrar Accounts that are stored in the <?=$software_title?>.<BR><BR>
<strong>Number of Active Accounts:</strong> <?=mysql_num_rows($result)?>
<BR><BR>
<?php
if (mysql_num_rows($result) > 0) { 
	
    $has_active_accounts = 1; ?>

    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Registrar Name</font>
        </td>
        <td width="200">
            <font class="subheadline">Account/Username</font>
        </td>
        <td width="200">
            <font class="subheadline">Owner</font>
        </td>
        <td>
            <font class="subheadline"># of Domains</font>
        </td>
    </tr>
    <?php 

    while ($row = mysql_fetch_object($result)) { 

	    $new_raid = $row->raid;
    
        if ($current_raid != $new_raid) {
			$exclude_account_string_raw .= "'$row->raid', ";
		} ?>

		<tr height="20">
			<td>
				<a class="subtlelink" href="edit/registrar.php?rid=<?=$row->rid?>"><?=$row->rname?></a>
			</td>
			<td valign="top">
				<a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->username?></a><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
			</td>
			<td>
				<a class="subtlelink" href="edit/owner.php?oid=<?=$row->oid?>"><?=$row->oname?></a>
			</td>
			<td>
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

	</table>
	<?php 

} ?>

<?php
$exclude_account_string = substr($exclude_account_string_raw, 0, -2); 

if ($exclude_account_string != "") { $raid_string = " AND ra.id not in ($exclude_account_string) "; } else { $raid_string = ""; }

$sql = "SELECT ra.id AS raid, ra.username, ra.owner_id, ra.registrar_id, ra.reseller, o.id AS oid, o.name AS oname, r.id AS rid, r.name AS rname
		FROM registrar_accounts AS ra, owners AS o, registrars AS r, domains AS d
		WHERE ra.active = '1'
		  AND ra.owner_id = o.id
		  AND ra.registrar_id = r.id
		  $rid_string
		  $raid_string
		  $oid_string
		GROUP BY ra.username, oname, rname
		ORDER BY rname";

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) {

    if ($has_active_accounts == 1) { echo "<BR><BR>"; } ?>
    
    <strong>Number of Inactive Accounts:</strong> <?=mysql_num_rows($result)?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Registrar Name</font>
        </td>
        <td width="200">
            <font class="subheadline">Account/Username</font>
        </td>
        <td width="200">
            <font class="subheadline">Owner</font>
        </td>
        <td>&nbsp;
            
        </td>
    </tr>
    <?php 

	while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td width="200">
                <a class="subtlelink" href="edit/registrar.php?rid=<?=$row->rid?>"><?=$row->rname?></a>
            </td>
            <td valign="top" width="200">
                    <a class="subtlelink" href="edit/account.php?raid=<?=$row->raid?>"><?=$row->username?></a><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
            </td>
            <td width="200">
                <a class="subtlelink" href="edit/owner.php?oid=<?=$row->oid?>"><?=$row->oname?></a>
            </td>
            <td>&nbsp;
                
            </td>
        </tr>
        <?php 

	} ?>

    </table>
	<?php 

} ?>
<font color="#DD0000"><strong>*</strong></font> = Reseller Account
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>