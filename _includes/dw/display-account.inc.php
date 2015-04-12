<?php
/**
 * /_includes/dw/display-account.inc.php
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
// // CODE TO USE
// // 
// // Display Accounts
// // Input: $sql_dw_account_temp
// $sql_dw_account_temp = "SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
// 						   FROM dw_accounts AS a, dw_servers AS s
// 						   WHERE a.server_id = s.id
// 						     AND X
// 						   ORDER BY s.name, a.unix_startdate DESC";
// $result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or outputOldSqlError($connection);
// $from_main_dw_account_page = 0;
// include("_includes/dw/display-account.inc.php");
?>
<?php if ($from_main_dw_account_page == 0) { ?><BR><BR><?php } ?>
<table class="main_table" cellpadding="0" cellspacing="0"><?php

    while ($row_dw_account_temp = mysqli_fetch_object($result_dw_account_temp)) {

        $visible_domain = wordwrap($row_dw_account_temp->domain, 20, "<BR>", true); ?>
        
        <tr class="main_table_row_active_no_hover"><?php
		
			if ($from_main_dw_account_page == 1) { ?>

                <td class="main_table_cell_active_top_aligned">
                
                    <strong><?php echo $visible_domain; ?></strong><BR><?php
                
                    if ($_SESSION['dw_view_all'] == "1") { ?>
                    
                        <BR><?php echo $row_dw_account_temp->dw_server_name; ?><BR><?php
    
                    }

                    $sql_zone = "SELECT id
                                 FROM dw_dns_zones
                                 WHERE domain = '" . $row_dw_account_temp->domain . "'
                                   AND server_id = '" . $row_dw_account_temp->dw_server_id . "'";
                    $result_zone = mysqli_query($connection, $sql_zone);
            
                    while ($row_zone = mysqli_fetch_object($result_zone)) { ?>
        
                        <BR>[<a class="covert_link" href="list-dns-zones.php?domain=<?php echo $row_dw_account_temp->domain; ?>">dns zone</a>]<?php
        
                    } ?>

                </td><?php
				
			} ?>
            
            <td>

                <?php if ($from_main_dw_account_page == 0) { ?><font class="default_highlight"><?php echo $row_dw_account_temp->dw_server_name; ?></font><BR><BR><?php } ?>
                <table class="main_table" cellpadding="0" cellspacing="0">
                    <tr class="main_table_row_active_no_hover">
                        <td class="main_table_cell_active_top_aligned">
                            <strong>Created:</strong> <?php echo date("Y M jS", $row_dw_account_temp->unix_startdate); ?><BR>
                            <strong>Contact:</strong> <?php echo $row_dw_account_temp->email; ?><BR>
                            <strong>IP Address:</strong> <?php echo $row_dw_account_temp->ip; ?><BR>
                            <strong>Hosting Plan:</strong> <?php echo $row_dw_account_temp->plan; ?><BR>
                            <strong>cPanel Theme:</strong> <?php echo $row_dw_account_temp->theme; ?><BR>
                            <strong>User, Owner:</strong> <?php echo $row_dw_account_temp->user; ?>, <?php echo $row_dw_account_temp->owner; ?>
                        </td>
                        <td class="main_table_cell_active_top_aligned">
                            <strong>Shell:</strong> <?php echo $row_dw_account_temp->shell; ?><BR>
                            <strong>Home:</strong> /<?php echo $row_dw_account_temp->partition; ?>/<?php echo $row_dw_account_temp->user; ?><BR>
                            <strong>HD Quota:</strong> <?php echo $row_dw_account_temp->disklimit; ?><?php if ($row_dw_account_temp->disklimit != "unlimited") echo " MB"; ?><BR>
                            <strong>HD Used:</strong> <?php echo number_format($row_dw_account_temp->diskused); ?> MB
                        </td>
                        <td class="main_table_cell_active_top_aligned">
                            <strong>POP:</strong> <?php echo $row_dw_account_temp->maxpop; ?><BR>
                            <strong>Lists:</strong> <?php echo $row_dw_account_temp->maxlst; ?><BR>
                            <strong>Addons:</strong> <?php echo $row_dw_account_temp->maxaddons; ?><BR>
                            <strong>Subdomains:</strong> <?php echo $row_dw_account_temp->maxsub; ?><BR>
                            <strong>SQL:</strong> <?php echo $row_dw_account_temp->maxsql; ?><BR>
                            <strong>FTP:</strong> <?php echo $row_dw_account_temp->maxftp; ?><BR>
                            <strong>Parked:</strong> <?php echo $row_dw_account_temp->maxparked; ?>
                            <?php if ($domain == "" && $from_main_dw_account_page == "1") { ?><BR><BR><?php } ?>
                            <?php if ($from_main_dw_account_page != "1") { ?><BR><BR><?php } ?>
                        </td>
                        <td class="main_table_cell_active_top_aligned">
                            <strong>Suspended?</strong> <?php echo $row_dw_account_temp->suspended; ?><BR>
                            <strong>When?</strong> <?php echo $row_dw_account_temp->suspendtime; ?><BR>
                            <strong>Why?</strong> <?php echo $row_dw_account_temp->suspendreason; ?>
                        </td>
                    </tr>
                </table>
			</td>
		</tr><?php

    } ?>

</table>
