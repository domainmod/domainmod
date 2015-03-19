<?php
// /_includes/dw/display-dns-zone.inc.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
// // CODE TO USE
// // 
// // Display DNS Zones
// // Input: $sql_dw_dns_zone_temp
// $sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
//							FROM dw_dns_zones AS z, dw_servers AS s
//							WHERE z.server_id = s.id
//							  AND X
//							ORDER BY s.name, z.zonefile, z.domain";
// $result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or die(mysqli_error());
// $from_main_dw_dns_zone_page = 0;
// include("_includes/dw/display-dns-zone.inc.php");
?>
<table class="main_table" cellpadding="0" cellspacing="0"><?php

    while ($row_dw_dns_zone_temp = mysqli_fetch_object($result_dw_dns_zone_temp)) {

        $visible_domain = wordwrap($row_dw_dns_zone_temp->domain, 20, "<BR>", true); ?>

            <tr class="main_table_row_active_no_hover"><?php
			
				if ($from_main_dw_dns_zone_page == 1) { ?>

                    <td class="main_table_cell_active_top_aligned"><?php
    
                        $visible_domain = wordwrap($row_dw_dns_zone_temp->domain, 22, "<BR>", true);
                        $visible_zonefile = wordwrap($row_dw_dns_zone_temp->zonefile, 22, "<BR>", true); ?>
    
                        <strong><?php echo $visible_zonefile; ?></strong><?php
                        
                        if ($_SESSION['dw_view_all'] == "1") { ?>
                        
                            <BR><BR><?php echo $row_dw_dns_zone_temp->dw_server_name; ?><?php
    
                        }
    
                        $sql_temp = "SELECT id
                                     FROM dw_accounts
                                     WHERE domain = '" . $row_dw_dns_zone_temp->domain . "'";
                        $result_temp = mysqli_query($connection, $sql_temp);
                
                        if (mysqli_num_rows($result_temp) > 0 && $from_main_dw_dns_zone_page == 1) { ?>
        
                            <BR><BR>[<a class="covert_link" href="list-accounts.php?domain=<?php echo $row_dw_dns_zone_temp->domain; ?>">account</a>]<?php
        
                        } ?>
    
                    </td><?php

				} ?>

                <td>

                    <?php if ($from_main_dw_dns_zone_page == 0) { ?><font class="default_highlight"><?php echo $row_dw_dns_zone_temp->dw_server_name; ?></font><BR><BR><?php } ?>
                    <table class="main_table_no_right_padding" cellpadding="0" cellspacing="0"><?php

						$sql_get_records = "SELECT *
											FROM dw_dns_records
											WHERE server_id = '" . $row_dw_dns_zone_temp->dw_server_id . "'
											  AND domain = '" . $row_dw_dns_zone_temp->domain . "'
											ORDER BY new_order";
						$result_get_records = mysqli_query($connection, $sql_get_records);
						
						while ($row_get_records = mysqli_fetch_object($result_get_records)) { ?>
	
							<tr class="main_table_row_active_no_right_padding">
								<td class="main_table_cell_active_top_aligned_no_right_padding" width="70" align="right" valign="top">
									<?php $wrapped_raw = wordwrap($row_get_records->raw, 100, "<BR>", true); ?>
									<?php $wrapped_txtdata = wordwrap($row_get_records->txtdata, 90, "<BR>", true); ?>
									<?php if ($row_get_records->type != "") { ?> <strong><?php echo $row_get_records->type; ?></strong><?php } ?>
								</td>
								<td class="main_table_cell_active_top_aligned_no_right_padding" width="46" align="center">
									| <?php if ($row_get_records->line < 10) echo "0"; ?><?php echo $row_get_records->line; ?> |
								</td>
								<td class="main_table_cell_active_top_aligned_no_right_padding">
									<?php if ($row_get_records->name != "") { ?><?php if ($row_get_records->type != "SOA" && $row_get_records->type != "NS" && $row_get_records->type != "A" && $row_get_records->type != "MX" && $row_get_records->type != "TXT" && $row_get_records->type != "CNAME") { ?>Name: <?php } ?><?php echo $row_get_records->name; ?><?php } ?>
									<?php if ($row_get_records->address != "") { ?> | <?php if ($row_get_records->type != "A") { ?><strong>Address:</strong><?php } ?> <?php echo $row_get_records->address; ?><?php } ?>
									<?php if ($row_get_records->exchange != "") { ?> | <?php echo $row_get_records->exchange; ?><?php } ?>
									<?php if ($row_get_records->preference != "" && $row_get_records->type == "MX") { ?> | <strong>Preference:</strong> <?php echo $row_get_records->preference; ?><?php } ?>
									<?php if ($row_get_records->mname != "") { ?> | <?php echo $row_get_records->mname; ?><?php } ?>
									<?php if ($row_get_records->rname != "") { ?> | <?php echo $row_get_records->rname; ?><?php } ?>
									<?php if ($row_get_records->type == "SOA") { ?><?php if ($row_get_records->ttl != "" && $row_get_records->ttl != '0') { ?> | <?php if ($row_get_records->type != "ZONE TTL" && $row_get_records->type != "SOA" && $row_get_records->type != "NS" && $row_get_records->type != "A" && $row_get_records->type != "MX" && $row_get_records->type != "TXT" && $row_get_records->type != "CNAME" && $row_get_records->type != ":RAW" && $row_get_records->type != "COMMENT") { ?>TTL: <?php } ?><?php echo $row_get_records->ttl; ?><?php } ?><?php } ?>
									<?php if ($row_get_records->serial != "" && $row_get_records->serial != '0') { ?><BR><strong>Serial:</strong> <?php echo $row_get_records->serial; ?><?php } ?>
									<?php if ($row_get_records->refresh != "" && $row_get_records->refresh != '0') { ?> | <strong>Refresh:</strong> <?php echo $row_get_records->refresh; ?><?php } ?>
									<?php if ($row_get_records->retry != "" && $row_get_records->retry != '0') { ?> | <strong>Retry:</strong> <?php echo $row_get_records->retry; ?><?php } ?>
									<?php if ($row_get_records->expire != "") { ?> | <strong>Expire:</strong> <?php echo $row_get_records->expire; ?><?php } ?>
									<?php if ($row_get_records->minimum != "" && $row_get_records->minimum != '0') { ?> | <strong>Minimum TTL:</strong> <?php echo $row_get_records->minimum; ?><?php } ?>
									<?php if ($row_get_records->cname != "") { ?> | <?php echo $row_get_records->cname; ?><?php } ?>
									<?php if ($row_get_records->nsdname != "") { ?> | <?php if ($row_get_records->type != "NS") { ?><strong>NSDNAME:</strong> <?php } ?><?php echo $row_get_records->nsdname; ?><?php } ?>
									<?php if ($row_get_records->raw != "") { ?><?php echo $wrapped_raw; ?><?php } ?>
									<?php if ($row_get_records->txtdata != "") { ?> | <?php echo $wrapped_txtdata; ?><?php } ?>
									<?php if ($row_get_records->type != "SOA") { ?><?php if ($row_get_records->ttl != "" && $row_get_records->ttl != '0') { ?><?php if ($row_get_records->type != "ZONE TTL") { ?> | <?php } ?><?php if ($row_get_records->type != "ZONE TTL" && $row_get_records->type != "SOA" && $row_get_records->type != "NS" && $row_get_records->type != "A" && $row_get_records->type != "MX" && $row_get_records->type != "TXT" && $row_get_records->type != "CNAME" && $row_get_records->type != ":RAW" && $row_get_records->type != "COMMENT") { ?>TTL: <?php } ?><?php echo $row_get_records->ttl; ?><?php } ?><?php } ?>
								</td>
							</tr><?php
								
						} ?>

                    </table><BR>

				</td>
    
            </tr><?php

    } ?>
</table>
