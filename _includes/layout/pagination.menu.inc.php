<?php
// /_includes/layout/pagination.menu.inc.php
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
<div class="pagination_menu_block">
	<div class="pagination_menu_block_inner">
		<?php echo $navigate[2]; ?>&nbsp;<?php if ($totalrows != '0') { ?><?php echo "(Listing $navigate[1] of " . number_format($totalrows) . ")"; ?><?php } ?>
		<?php 
        if ($software_section == "domains" || $software_section == "ssl-certs") {
            if ($software_section == "domains") { 
            
                $export_filename = "domains.php";
                
            } elseif ($software_section == "ssl-certs") {
            
                $export_filename = "ssl-certs.php";
            
            } ?>
            [<a href="<?=$export_filename?>?<?=$_SERVER['QUERY_STRING']?>&export=1">export results</a>]&nbsp;[<a href="system/display-settings.php">display settings</a>]<?php
    
        }
        ?>
	</div>
</div>
<div style="clear: both;"></div>