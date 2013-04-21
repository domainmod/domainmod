<?php
// /ssl-providers.php
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

$page_title = "SSL Certificate Providers";
$software_section = "ssl-providers";
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
$sql = "SELECT id, name, url, default_provider
		FROM ssl_providers
		WHERE id IN (SELECT ssl_provider_id FROM ssl_certs WHERE ssl_provider_id != '0' AND active != '0' GROUP BY ssl_provider_id)
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the SSL Certificate Providers that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Providers (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Accounts</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Certs</font>
        </td>
    </tr>

    <?php 
	while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ssl-provider.php?sslpid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_provider == "1") echo "<a title=\"Default SSL Provider\"><font class=\"default_highlight\">*</font></a>"; ?>&nbsp;[<a class="invisiblelink" target="_blank" href="<?=$row->url?>">v</a>]
            </td>
            <td class="main_table_cell_active">
                <?php
                $sql2 = "SELECT count(*) AS total_count
                         FROM ssl_accounts
                         WHERE ssl_provider_id = '$row->id'";
                $result2 = mysql_query($sql2,$connection);
                while ($row2 = mysql_fetch_object($result2)) { $total_accounts = $row2->total_count; }
                ?>
        
                <?php if ($total_accounts >= 1) { ?>
        
                    <a class="nobold" href="ssl-accounts.php?sslpid=<?=$row->id?>"><?=number_format($total_accounts)?></a>
        
                <?php } else { ?>
        
                    <?=number_format($total_accounts)?>
                
                <?php } ?>
        
            </td>
            <td class="main_table_cell_active">
                <?php
                $sql3 = "SELECT count(*) AS total_count
                         FROM ssl_certs
                         WHERE active != '0'
                           AND ssl_provider_id = '$row->id'";
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
<?php 
} ?>

<?php
$sql = "SELECT id, name, url, default_provider
		FROM ssl_providers
		WHERE id NOT IN (SELECT ssl_provider_id FROM ssl_certs WHERE ssl_provider_id != '0' AND active != '0' GROUP BY ssl_provider_id)
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">";
?>
        <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <font class="main_table_heading">Inactive Providers (<?=mysql_num_rows($result)?>)</font>
            </td>
            <td class="main_table_cell_heading_inactive">
                <font class="main_table_heading">Accounts</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink" href="edit/ssl-provider.php?sslpid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_provider == "1") echo "<a title=\"Default SSL Provider\"><font class=\"default_highlight\">*</font></a>"; ?>&nbsp;[<a class="invisiblelink" target="_blank" href="<?=$row->url?>">v</a>]
                </td>
                <td class="main_table_cell_inactive">
                    <?php
                    $sql2 = "SELECT count(*) AS total_count
                             FROM ssl_accounts
                             WHERE ssl_provider_id = '$row->id'";
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
<?php 
} ?>
<?php
if ($has_active == "1" || $has_inactive == "1") echo "</table>";
?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight">*</font> = Default SSL Provider
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		<BR>You don't currently have any SSL Providers. <a href="add/ssl-provider.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>