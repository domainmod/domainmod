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
$cid = $_GET['cid'];
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

if ($rid != "") { $rid_string = " and ra.registrar_id = '$rid' "; } else { $rid_string = ""; }
if ($raid != "") { $raid_string = " and ra.id = '$raid' "; } else { $raid_string = ""; }
if ($cid != "") { $cid_string = " and ra.company_id = '$cid' "; } else { $cid_string = ""; }

$sql = "select ra.id as raid, ra.username, ra.company_id, ra.registrar_id, ra.reseller, c.id as cid, c.name as cname, r.id as rid, r.name as rname
		from registrar_accounts as ra, companies as c, registrars as r, domains as d
		where ra.active = '1'
		and ra.company_id = c.id
		and ra.registrar_id = r.id
		and ra.id = d.account_id
		and d.active not in ('0', '10')
		$rid_string
		$raid_string
		$cid_string
		and (select count(*) from domains where account_id = ra.id and active not in ('0', '10')) > 0
		group by ra.username, cname, rname
		order by rname asc";

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { 
	
    $has_active_accounts = 1; ?>

	<strong>Number of Active Accounts:</strong> <?=mysql_num_rows($result)?>

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
            <font class="subheadline">Company</font>
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
				<a class="subtlelink" href="edit/company.php?cid=<?=$row->cid?>"><?=$row->cname?></a>
			</td>
			<td>
				<?php
				$sql2 = "select count(*) as total_domain_count
						 from domains
						 where account_id = '$row->raid'
						 and active not in ('0', '10')";
				$result2 = mysql_query($sql2,$connection);

				while ($row2 = mysql_fetch_object($result2)) { 
					echo "<a class=\"nobold\" href=\"domains.php?cid=$row->cid&rid=$row->rid&raid=$row->raid\">" . number_format($row2->total_domain_count) . "</a>"; 
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

if ($exclude_account_string != "") { $raid_string = " and ra.id not in ($exclude_account_string) "; } else { $raid_string = ""; }

$sql = "select ra.id as raid, ra.username, ra.company_id, ra.registrar_id, ra.reseller, c.id as cid, c.name as cname, r.id as rid, r.name as rname
		from registrar_accounts as ra, companies as c, registrars as r, domains as d
		where ra.active = '1'
		and ra.company_id = c.id
		and ra.registrar_id = r.id
		$rid_string
		$raid_string
		$cid_string
		group by ra.username, cname, rname
		order by rname";

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
            <font class="subheadline">Company</font>
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
                <a class="subtlelink" href="edit/company.php?cid=<?=$row->cid?>"><?=$row->cname?></a>
            </td>
            <td>&nbsp;
                
            </td>
        </tr>
        <?php 

	} ?>

    </table>

	<BR><font color="#DD0000"><strong>*</strong></font> = Reseller Account
	<?php 

} ?>

<?php include("_includes/footer.inc.php"); ?>
</body>
</html>