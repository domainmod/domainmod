<?php
/**
 * /classes/DomainMOD/DwDisplay.php
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
namespace DomainMOD;

class DwDisplay
{

    public function account($connection, $server_id, $domain, $show_sidebar, $show_domain, $show_zone)
    {

        $result_account = $this->getAccount($connection, $domain, $server_id);

        $result = '';

        while ($row = mysqli_fetch_object($result_account)) {

            ob_start(); //@formatter:off ?>

            <tr class="main_table_row_active_no_hover"><?php

            echo $this->sidebar($domain, $server_id, $row->server_name, $show_domain, $show_zone, $show_sidebar); ?>

            <td class="main_table_cell_active_top_aligned">
                <strong>Created:</strong> <?php echo date("Y M jS", $row->unix_startdate); ?><BR>
                <strong>Contact:</strong> <?php echo $row->email; ?><BR>
                <strong>IP Address:</strong> <?php echo $row->ip; ?><BR>
                <strong>Hosting Plan:</strong> <?php echo $row->plan; ?><BR>
                <strong>cPanel Theme:</strong> <?php echo $row->theme; ?><BR>
                <strong>User, Owner:</strong> <?php echo $row->user; ?>, <?php echo $row->owner; ?>
            </td>
            <td class="main_table_cell_active_top_aligned">
                <strong>Shell:</strong> <?php echo $row->shell; ?><BR>
                <strong>Home:</strong> /<?php echo $row->partition; ?> /<?php echo $row->user; ?><BR>
                <strong>HD Quota:</strong> <?php echo $row->disklimit; ?>
                <?php if ($row->disklimit != "unlimited") echo " MB"; ?><BR>
                <strong>HD Used:</strong> <?php echo number_format($row->diskused); ?> MB
            </td>
            <td class="main_table_cell_active_top_aligned">
                <strong>POP:</strong> <?php echo $row->maxpop; ?><BR>
                <strong>Lists:</strong> <?php echo $row->maxlst; ?><BR>
                <strong>Addons:</strong> <?php echo $row->maxaddons; ?><BR>
                <strong>Subdomains:</strong> <?php echo $row->maxsub; ?><BR>
                <strong>SQL:</strong> <?php echo $row->maxsql; ?><BR>
                <strong>FTP:</strong> <?php echo $row->maxftp; ?><BR>
                <strong>Parked:</strong> <?php echo $row->maxparked; ?><BR><BR>
            </td>
            <td class="main_table_cell_active_top_aligned">
                <strong>Suspended?</strong> <?php echo $row->suspended; ?><BR>
                <strong>When?</strong> <?php echo $row->suspendtime; ?><BR>
                <strong>Why?</strong> <?php echo $row->suspendreason; ?>
            </td>
            </tr><?php

            $result = ob_get_clean(); //@formatter:on

        }

        return $result;

    }

    public function getAccount($connection, $domain, $server_id)
    {

        $sql = "SELECT a.unix_startdate, a.email, a.ip, a.plan, a.theme, a.`user`, a.`owner`, a.shell, a.`partition`,
                    a.disklimit, a.diskused, a.maxpop, a.maxlst, a.maxaddons, a.maxsub, a.maxsql, a.maxftp, a.maxparked,
                    a.suspended, a.suspendtime, a.suspendreason, s.name AS server_name
                FROM dw_accounts AS a, dw_servers AS s
                WHERE a.server_id = s.id
                  AND a.domain = '" . $domain . "'
                  AND a.server_id = '" . $server_id . "'";

        return mysqli_query($connection, $sql);

    }

    public function sidebar($domain, $server_id, $server_name, $show_domain, $show_zone, $show_sidebar)
    {

        ob_start(); //@formatter:off

        $result = '';

        if ($show_sidebar == '1') { ?>
            <td class="main_table_cell_active_top_aligned"><?php
                echo $this->showDomain($domain, $show_domain);
                echo $server_name . "<BR>";
                echo $this->showZoneLink($domain, $server_id, $show_zone); ?>
            </td><?php

            $result = ob_get_clean();
        }
        return $result; //@formatter:on
    }

    public function showDomain($domain, $show_domain)
    {

        $result = '';

        if ($show_domain == '1') {
            $visible_domain = wordwrap($domain, 20, "<BR>", true);
            $result = "<strong>" . $visible_domain . "</strong><BR><BR>";
        }

        return $result;
    }

    public function showZoneLink($domain, $server_id, $show_zone)
    {

        $result = '';

        if ($show_zone == '1') {
            $result = "[<a class=\"covert_link\" href=\"list-zones.php?domain=<?php echo $domain
            ?>&server_id=$server_id\">dns zone</a>]";
        }

        return $result;

    }

    public function tableTop()
    {

        ob_start(); //@formatter:off ?>

        <table class="main_table" cellpadding="0" cellspacing="0"><tr><td><?php

        return ob_get_clean(); //@formatter:on

    }

    public function tableBottom()
    {

        ob_start(); //@formatter:off ?>

        </td></tr></table><?php

        return ob_get_clean(); //@formatter:on

    }

}
