<?php
// ssl-providers.php
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

$page_title = "SSL Provider Breakdown";
$software_section = "ssl-providers";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
These are the SSL Providers that have active certificates.

<?php
$sql = "select id, name, url
		from ssl_providers
		where id in (select ssl_provider_id from ssl_certs where ssl_provider_id != '0' and active != '0' group by ssl_provider_id)
		order by name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<BR><BR>
<strong>Number of Active SSL Providers:</strong> <?=mysql_num_rows($result)?>
<?php 
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Provider Name</font>
        </td>
        <td width="150">
            <font class="subheadline"># of Accounts</font>
        </td>
        <td>
            <font class="subheadline"># of Certs</font>
        </td>
    </tr>

    <?php 
	while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/ssl-provider.php?sslpid=<?=$row->id?>"><?=$row->name?></a>&nbsp;[<a class="subtlelink" target="_blank" href="<?=$row->url?>">v</a>]
            </td>
            <td>
                <?php
                $sql2 = "select count(*) as total_count
                         from ssl_accounts
                         where active = '1'
                         and ssl_provider_id = '$row->id'";
                $result2 = mysql_query($sql2,$connection);
                while ($row2 = mysql_fetch_object($result2)) { $total_accounts = $row2->total_count; }
                ?>
        
                <?php if ($total_accounts >= 1) { ?>
        
                    <a class="nobold" href="ssl-accounts.php?sslpid=<?=$row->id?>"><?=number_format($total_accounts)?></a>
        
                <?php } else { ?>
        
                    <?=number_format($total_accounts)?>
                
                <?php } ?>
        
            </td>
            <td>
                <?php
                $sql3 = "select count(*) as total_count
                         from ssl_certs
                         where active != '0'
                         and ssl_provider_id = '$row->id'";
                $result3 = mysql_query($sql3,$connection);
                while ($row3 = mysql_fetch_object($result3)) { $total_certs = $row3->total_count; }
                ?>
        
                <?php if ($total_certs >= 1) { ?>
        
                    <a class="nobold" href="ssl-certs.php?sslpid=<?=$row->id?>"><?=number_format($total_certs)?></a>
        
                <?php } else { ?>
        
                    <?=number_format($total_certs)?>
                
                <?php } ?>
        
            </td>
        </tr>
    <?php 
	} ?>

    </table>
<?php 
} ?>

<?php
$sql = "select id, name, url
		from ssl_providers
		where id not in (select ssl_provider_id from ssl_certs where ssl_provider_id != '0' and active != '0' group by ssl_provider_id)
		order by name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php 
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <strong>Number of Inactive SSL Providers:</strong> <?=mysql_num_rows($result)?>
    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr height="30">
            <td width="250">
                <font class="subheadline">Provider Name</font>
            </td>
            <td>
                <font class="subheadline"># of Accounts</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr height="20">
                <td>
                    <a class="subtlelink" href="edit/ssl-provider.php?sslpid=<?=$row->id?>"><?=$row->name?></a>&nbsp;[<a class="subtlelink" target="_blank" href="<?=$row->url?>">v</a>]
                </td>
                <td>
                    <?php
                    $sql2 = "select count(*) as total_count
                             from ssl_accounts
                             where active = '1'
                             and ssl_provider_id = '$row->id'";
                    $result2 = mysql_query($sql2,$connection);
                    while ($row2 = mysql_fetch_object($result2)) { $total_accounts = $row2->total_count; }
                    ?>
            
                    <?php if ($total_accounts >= 1) { ?>
            
                        <a class="nobold" href="ssl-accounts.php?sslpid=<?=$row->id?>"><?=number_format($total_accounts)?></a>
            
                    <?php } else { ?>
            
                        <?=number_format($total_accounts)?>
                    
                    <?php } ?>
            
                </td>
            </tr>
    
        <?php 
        } ?>

    </table>
<?php 
} ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>