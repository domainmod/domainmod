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

if ($sslpid != "") { $sslpid_string = " and ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ""; }
if ($oid != "") { $oid_string = " and owner_id = '$oid' "; } else { $oid_string = ""; }

$sql = "SELECT id, username, owner_id, ssl_provider_id, reseller
		FROM ssl_accounts
		WHERE id IN (SELECT account_id FROM ssl_certs WHERE account_id != '0' AND active = '1' GROUP BY account_id)
		  $sslpid_string
		  $oid_string
		ORDER BY username asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the SSL Certificate Accounts that are stored in the <?=$software_title?>.<BR><BR>
<strong>Number of Active Accounts:</strong> <?=mysql_num_rows($result)?>
<?php 
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="200">
            <font class="subheadline">Account/Username</font>
        </td>
        <td width="250">
            <font class="subheadline">Owner</font>
        </td>
        <td width="250">
            <font class="subheadline">SSL Provider</font>
        </td>
        <td>
            <font class="subheadline"># of Certs</font>
        </td>
    </tr>

	<?php 
    while ($row = mysql_fetch_object($result)) { ?>
    <tr height="20">
        <td valign="top" width="200">
                <a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->id?>"><?=$row->username?></a><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
        </td>
        <td colspan="3">
            <table width="100%" border="0" cellspacing="3" cellpadding="0">
                <tr>
                    <td width="244">
                    <?php
                    $sql2 = "SELECT id, name
                             FROM owners
                             WHERE id = '$row->owner_id'";
                    $result2 = mysql_query($sql2,$connection) or die(mysql_error());
                    while ($row2 = mysql_fetch_object($result2)) {
                        $temp_id = $row2->id;
                        $temp_owner_name = $row2->name;
                    }
                    ?>
                    <a class="subtlelink" href="edit/owner.php?oid=<?=$temp_id?>"><?=$temp_owner_name?></a>
                    </td>
                    <td width="250">
                    <?php
                    $sql2 = "SELECT id, name
                             FROM ssl_providers
                             WHERE id = '$row->ssl_provider_id'";
                    $result2 = mysql_query($sql2,$connection) or die(mysql_error());
                    while ($row2 = mysql_fetch_object($result2)) {
                        $temp_id = $row2->id;
                        $temp_ssl_provider_name = $row2->name;
                    }
                    ?>
                    <a class="subtlelink" href="edit/ssl-provider.php?sslpid=<?=$temp_id?>"><?=$temp_ssl_provider_name?></a>
                    </td>
                    <td>
                    <?php
                    $sql3 = "SELECT count(*) AS total_ssl_count
                             FROM ssl_certs
                             WHERE account_id = '$row->id'
                               AND active != '0'
                               AND active != '10'";
                    $result3 = mysql_query($sql3,$connection);
                    while ($row3 = mysql_fetch_object($result3)) {
                        if ($row3->total_ssl_count != 0) {
                            echo "<a class=\"nobold\" href=\"ssl-certs.php?oid=$row->owner_id&sslpid=$row->ssl_provider_id&sslpaid=$row->id\">" . number_format($row3->total_ssl_count) . "</a>";
                        } else {
                            echo number_format($row3->total_ssl_count);
                        }
                    }
                    ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php 
    } ?>
	</table>
<?php 
} ?>
<?php
$sql = "SELECT id, username, owner_id, ssl_provider_id, reseller
		FROM ssl_accounts
		WHERE id NOT IN (SELECT account_id FROM ssl_certs WHERE account_id != '0' AND active = '1' GROUP BY account_id)
		  $sslpid_string
		  $oid_string
		ORDER BY username asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php 
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <strong>Number of Inactive Accounts:</strong> <?=mysql_num_rows($result)?>
    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr height="30">
            <td width="200">
                <font class="subheadline">Account/Username</font>
            </td>
            <td width="250">
                <font class="subheadline">Owner</font>
            </td>
            <td width="250">
                <font class="subheadline">SSL Provider</font>
            </td>
            <td>&nbsp;
                
            </td>
        </tr>

	<?php 
    while ($row = mysql_fetch_object($result)) { ?>
    
        <tr height="20">
            <td valign="top" width="200">
                    <a class="subtlelink" href="edit/ssl-account.php?sslpaid=<?=$row->id?>"><?=$row->username?></a><?php if ($row->reseller == "1") echo "<a title=\"Reseller Account\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
            </td>
            <td colspan="3">
    
                <table width="100%" border="0" cellspacing="3" cellpadding="0">
                    <tr>
                        <td width="246">
                        <?php
                        $sql2 = "SELECT id, name
                                 FROM owners
                                 WHERE id = '$row->owner_id'";
                        $result2 = mysql_query($sql2,$connection) or die(mysql_error());
                        while ($row2 = mysql_fetch_object($result2)) {
                            $temp_id = $row2->id;
                            $temp_owner_name = $row2->name;
                        }
                        ?>
                        <a class="subtlelink" href="edit/owner.php?oid=<?=$temp_id?>"><?=$temp_owner_name?></a>
                        </td>
                        <td width="250">
                        <?php
                        $sql2 = "SELECT id, name
                                 FROM ssl_providers
                                 WHERE id = '$row->ssl_provider_id'";
                        $result2 = mysql_query($sql2,$connection) or die(mysql_error());
                        while ($row2 = mysql_fetch_object($result2)) {
                            $temp_id = $row2->id;
                            $temp_ssl_provider_name = $row2->name;
                        }
                        ?>
                        <a class="subtlelink" href="edit/ssl-provider.php?sslpid=<?=$temp_id?>"><?=$temp_ssl_provider_name?></a>
                        </td>
                        <td>&nbsp;
                            
                        </td>
                    </tr>
                </table>
    
            </td>
        </tr>
    <?php 
    } ?>
</table>
<?php 
} ?>
<BR><BR><font color="#DD0000"><strong>*</strong></font> = Reseller Account
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>