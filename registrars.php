<?php
// registrars.php
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

$page_title = "Registrar Breakdown";
$software_section = "registrars";
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
$sql = "select r.id as rid, r.name as rname, r.url
		from registrars as r, domains as d
		where r.id = d.registrar_id
		and r.active = '1'
		and d.domain not in ('0', '10')
		and (select count(*) from domains where registrar_id = r.id and active not in ('0','10')) > 0
		group by r.name
		order by r.name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<strong>Number of Active Registrars:</strong> <?=mysql_num_rows($result)?>
<?php

if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Registrar Name</font>
        </td>
        <td width="150">
            <font class="subheadline"># of Accounts</font>
        </td>
        <td>
            <font class="subheadline"># of Domains</font>
        </td>
    </tr>
    <?php 
    
    while ($row = mysql_fetch_object($result)) {

	    $new_rid = $row->rid;
    
        if ($current_rid != $new_rid) {
			$exclude_registrar_string_raw .= "'$row->rid', ";
		} ?>
    
        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/registrar.php?rid=<?=$row->rid?>"><?=$row->rname?></a>&nbsp;[<a class="subtlelink" target="_blank" href="<?=$row->url?>">v</a>]
            </td>
            <td>
                <?php
                $sql2 = "select count(*) as total_count
                         from registrar_accounts
                         where active = '1'
                         and registrar_id = '$row->rid'";
                $result2 = mysql_query($sql2,$connection);
        
                while ($row2 = mysql_fetch_object($result2)) { 
                    $total_accounts = $row2->total_count;
                }
                
                    if ($total_accounts >= 1) { ?>
            
                        <a class="nobold" href="registrar-accounts.php?rid=<?=$row->rid?>"><?=number_format($total_accounts)?></a>
                        <?php 
            
                    } else { ?>
            
                        <?=number_format($total_accounts)?>
                        <?php
                    } ?>
        
            </td>
            <td>
                <?php
                $sql3 = "select count(*) as total_count
                         from domains
                         where active not in ('0', '10')
                         and registrar_id = '$row->rid'";
                $result3 = mysql_query($sql3,$connection);
        
                while ($row3 = mysql_fetch_object($result3)) { 
                    $total_domains = $row3->total_count;
                }		
        
                    if ($total_accounts >= 1) { ?>
            
                        <a class="nobold" href="domains.php?rid=<?=$row->rid?>"><?=number_format($total_domains)?></a>
                        <?php 
            
                    } else { ?>
            
                        <?=number_format($total_domains)?>
                        <?php 
                    
                    } ?>
        
            </td>
        </tr>
        <?php 
		$current_rid = $row->rid;

	} ?>

    </table>
	<?php

} ?>

<?php
$exclude_registrar_string = substr($exclude_registrar_string_raw, 0, -2); 

$sql = "select r.id as rid, r.name as rname, r.url
		from registrars as r
		where r.id
		and r.active = '1'
		and r.id not in ($exclude_registrar_string)
		group by r.name
		order by r.name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php

if (mysql_num_rows($result) > 0) { ?>
<BR><BR>
<strong>Number of Inactive Registrars:</strong> <?=mysql_num_rows($result)?>
    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="250">
            <font class="subheadline">Registrar Name</font>
        </td>
        <td width="150">
            <font class="subheadline"># of Accounts</font>
        </td>
        <td>&nbsp;
        	
        </td>
    </tr>
    <?php 
    
    while ($row = mysql_fetch_object($result)) { ?>
    
        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/registrar.php?rid=<?=$row->rid?>"><?=$row->rname?></a>&nbsp;[<a class="subtlelink" target="_blank" href="<?=$row->url?>">v</a>]
            </td>
            <td>
                <?php
                $sql2 = "select count(*) as total_count
                         from registrar_accounts
                         where active = '1'
                         and registrar_id = '$row->rid'";
                $result2 = mysql_query($sql2,$connection);
        
                while ($row2 = mysql_fetch_object($result2)) { 
                    $total_accounts = $row2->total_count;
                }
                
                    if ($total_accounts >= 1) { ?>
            
                        <a class="nobold" href="registrar-accounts.php?rid=<?=$row->rid?>"><?=number_format($total_accounts)?></a>
                        <?php 
            
                    } else { ?>
            
                        <?=number_format($total_accounts)?>
                        <?php
                    } ?>
        
            </td>
            <td>&nbsp;
				
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