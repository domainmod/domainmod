<?php
/**
 * /_includes/layout/pagination.menu.inc.php
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
<div class="pagination_menu_block">
    <div class="pagination_menu_block_inner">
        <?php echo $navigate[2]; ?>&nbsp;
        <?php if ($totalrows != '0') {
            echo "(Listing $navigate[1] of " . number_format($totalrows) . ")";
        } ?>
        <?php
        if ($software_section == "domains" || $software_section == "ssl-certs") {

            if ($software_section == "domains") {

                $export_filename = "domains.php";

            } elseif ($software_section == "ssl-certs") {

                $export_filename = "ssl-certs.php";

            }
            //@formatter:off ?>&nbsp;&nbsp;
            [<a href="<?php echo $export_filename . "?" . $_SERVER['QUERY_STRING'];
                ?>&export_data=1">export results</a>]&nbsp;[<a target="_blank" href="<?php echo $web_root;
                ?>/raw.php">raw list</a>]&nbsp;[<a href="system/display-settings.php">display settings</a>]
            <?php //@formatter:on

        } ?>
    </div>
</div>
<div style="clear: both;"></div>
