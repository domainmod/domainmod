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
//@formatter:off
    public function account($connection, $server_id, $domain, $show_sidebar, $show_heading, $show_url)
    { 
        $result_account = $this->getAccount($connection, $server_id, $domain);
        $result = '';

        while ($row = mysqli_fetch_object($result_account)) {

            ob_start();  ?>
            <tr class="main_table_row_active_no_hover"><?php
            echo $this->accountSidebar($row->server_name, $domain, $show_sidebar, $show_heading, $show_url); ?>
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
            $result = ob_get_clean(); 
        }
        return $result;
    } 

    public function getAccount($connection, $server_id, $domain)
    {
        $sql = "SELECT a.unix_startdate, a.email, a.ip, a.plan, a.theme, a.`user`, a.`owner`, a.shell, a.`partition`,
                    a.disklimit, a.diskused, a.maxpop, a.maxlst, a.maxaddons, a.maxsub, a.maxsql, a.maxftp, a.maxparked,
                    a.suspended, a.suspendtime, a.suspendreason, s.name AS server_name
                FROM dw_accounts AS a, dw_servers AS s
                WHERE a.server_id = s.id
                  AND a.server_id = '" . $server_id . "'
                  AND a.domain = '" . $domain . "'";
        return mysqli_query($connection, $sql);
    }

    public function accountSidebar($server_name, $domain, $show_sidebar, $show_heading, $show_url)
    { 
        ob_start();
        $result = '';

        if ($show_sidebar == '1') { ?>
            <td class="main_table_cell_active_top_aligned"><?php
                echo $this->showHeading($domain, $show_heading);
                $server_wrapped = wordwrap($server_name, 20, "<BR>", true);
                echo $server_wrapped . "<BR>";
                echo $this->showUrl($domain, 'zone', 'list-zones.php', $show_url); ?>
            </td><?php
            $result = ob_get_clean();
        }
        return $result;
    } 

    public function showHeading($heading, $show_heading)
    { 
        $result = '';
        
        if ($show_heading == '1') {
            $visible_heading = wordwrap($heading, 20, "<BR>", true);
            $result = "<strong>" . $visible_heading . "</strong><BR><BR>";
        }
        return $result;
    } 

    public function showUrl($domain, $text, $url, $show_url)
    { 
        $result = '';
        
        if ($show_url == '1') {
            $result = "[<a class=\"invisiblelink\"
                href=\"" . $url . "?domain=" . $domain . "\">" . $text . "</a>]";
        }
        return $result;
    } 

    public function zone($connection, $server_id, $domain, $show_sidebar, $show_heading, $show_url)
    { 
        ob_start(); ?>

        <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_active_no_hover"><?php

        echo $this->zoneSidebar($connection, $server_id, $domain, $show_sidebar, $show_heading, $show_url);

        $result_zone = $this->getZone($connection, $server_id, $domain);

        while ($row_zone = mysqli_fetch_object($result_zone)) {
            $result_records = $this->getRecords($connection, $row_zone->zone_id); ?>
            <td>
                <table class="main_table_no_right_padding" cellpadding="0" cellspacing="0"><?php

                    while ($row = mysqli_fetch_object($result_records)) { ?>
                        <tr class="main_table_row_active_no_right_padding">
                        <td class="main_table_cell_active_top_aligned_no_right_padding" width="70" align="right"
                            valign="top"><?php echo $row->formatted_type; ?></td>
                        <td class="main_table_cell_active_top_aligned_no_right_padding" width="46" align="center"><?php
                            echo $row->formatted_line; ?></td>
                        <td class="main_table_cell_active_top_aligned_no_right_padding"><?php
                            echo $row->formatted_output; ?></td>
                        </tr><?php
                    } ?>
                </table><BR><BR>
            </td>
        </tr>
        </table><?php
        }
        $result = ob_get_clean();
        return $result;
    } 

    public function zoneSidebar($connection, $server_id, $domain, $show_sidebar, $show_heading, $show_url)
    { 
        ob_start();
        $result = '';

        if ($show_sidebar == '1') { ?>
            <td class="main_table_cell_active_top_aligned" width="130"><?php
                $zone = $this->getZonefile($connection, $server_id, $domain);
                echo $this->showHeading($zone, $show_heading);
                $server_name = $this->getServerName($connection, $server_id);
                echo $server_name . "<BR>";
                echo $this->showURL($domain, 'account', 'list-accounts.php', $show_url); ?>
            </td><?php
            $result = ob_get_clean();
        }
        return $result;
    } 

    public function getZonefile($connection, $server_id, $domain)
    {
        $zonefile = '';
        $sql = "SELECT zonefile
                FROM dw_dns_zones
                WHERE server_id = '" . $server_id . "'
                  AND domain = '" . $domain . "'";
        $result = mysqli_query($connection, $sql);
        while ($row = mysqli_fetch_object($result)) { $zonefile = $row->zonefile; }
        return $zonefile;
    } 

    public function getServerName($connection, $server_id)
    { 
        $server_name = '';
        $sql = "SELECT `name`
                FROM dw_servers
                WHERE id = '" . $server_id . "'";
        $result = mysqli_query($connection, $sql);
        while ($row = mysqli_fetch_object($result)) { $server_name = $row->name; }
        return $server_name;
    } 

    public function getZone($connection, $server_id, $domain)
    {
        $sql = "SELECT z.id AS zone_id, z.domain, z.zonefile, s.id AS server_id, s.name AS server_name
                FROM dw_dns_zones AS z, dw_servers AS s
                WHERE z.server_id = s.id
                  AND z.server_id = '" . $server_id . "'
                  AND z.domain = '" . $domain . "'";
        return mysqli_query($connection, $sql);
    }

    public function getRecords($connection, $zone_id)
    {
        $sql = "SELECT address, cname, `exchange`, expire, line, minimum, mname, `name`, nsdname, preference, raw,
                    refresh, retry, rname, `serial`, ttl, txtdata, type, formatted_type, formatted_line,
                    formatted_output
                FROM dw_dns_records
                WHERE dns_zone_id = '" . $zone_id . "'
                ORDER BY new_order";
        return mysqli_query($connection, $sql);
    }

    public function tableTop()
    { 
        ob_start(); ?>
        <table class="main_table" cellpadding="0" cellspacing="0" border="0"><tr><?php
        return ob_get_clean();
    } 

    public function tableBottom()
    { 
        ob_start(); ?>
        </tr></table><?php
        return ob_get_clean();
    }  

} //@formatter:on
