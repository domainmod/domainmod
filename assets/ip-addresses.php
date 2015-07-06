<?php
/**
 * /assets/ip-addresses.php
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Domain & SSL IP Addresses";
$software_section = "ip-addresses";

$export_data = $_GET['export_data'];

$sql = "(SELECT ip.id, ip.name, ip.ip, ip.rdns, ip.notes, ip.insert_time, ip.update_time
         FROM ip_addresses AS ip, domains AS d
         WHERE ip.id = d.ip_id
           AND d.active NOT IN ('0', '10')
         GROUP BY ip.name)
        UNION
        (SELECT ip.id, ip.name, ip.ip, ip.rdns, ip.notes, ip.insert_time, ip.update_time
         FROM ip_addresses AS ip, ssl_certs AS sslc
         WHERE ip.id = sslc.ip_id
           AND sslc.active NOT IN ('0')
         GROUP BY ip.name)
        ORDER BY name";

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ip_address_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'IP Address Name',
        'IP Address',
        'rDNS',
        'Domains',
        'SSL Certs',
        'Default Domain IP Address?',
        'Default SSL IP Address?',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        $has_active = "1";

        while ($row = mysqli_fetch_object($result)) {

            $new_ipid = $row->id;

            if ($current_ipid != $new_ipid) {
                $exclude_ip_address_string_raw .= "'" . $row->id . "', ";
            }

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM domains
                                WHERE active NOT IN ('0', '10')
                                  AND ip_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_domains = $row_total_count->total_count;
            }

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE active NOT IN ('0')
                                  AND ip_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($row->id == $_SESSION['default_ip_address_domains']) {

                $is_default_domains = "1";

            } else {

                $is_default_domains = "";

            }

            if ($row->id == $_SESSION['default_ip_address_ssl']) {

                $is_default_ssl = "1";

            } else {

                $is_default_ssl = "";

            }

            $row_contents = array(
                'Active',
                $row->name,
                $row->ip,
                $row->rdns,
                $total_domains,
                $total_certs,
                $is_default_domains,
                $is_default_ssl,
                $row->notes,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

            $current_ipid = $row->id;

        }

    }

    $exclude_ip_address_string = substr($exclude_ip_address_string_raw, 0, -2);

    if ($exclude_ip_address_string == "") {

        $sql = "SELECT id, name, ip, rdns, notes, insert_time, update_time
                FROM ip_addresses
                ORDER BY name ASC, ip ASC";

    } else {

        $sql = "SELECT id, name, ip, rdns, notes, insert_time, update_time
                FROM ip_addresses
                WHERE id NOT IN (" . $exclude_ip_address_string . ")
                ORDER BY name ASC, ip ASC";

    }

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";

        while ($row = mysqli_fetch_object($result)) {

            if ($row->id == $_SESSION['default_ip_address_domains']) {

                $is_default_domains = "1";

            } else {

                $is_default_domains = "";

            }

            if ($row->id == $_SESSION['default_ip_address_ssl']) {

                $is_default_ssl = "1";

            } else {

                $is_default_ssl = "";

            }

            $row_contents = array(
                'Inactive',
                $row->name,
                $row->ip,
                $row->rdns,
                '0',
                '0',
                $is_default_domains,
                $is_default_ssl,
                $row->notes,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
Below is a list of all the IP Addresses that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="ip-addresses.php?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) {

$has_active = "1"; ?>
<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">Active IP Names (<?php echo mysqli_num_rows($result); ?>)</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">IP Address</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">rDNS</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">Domains</div>
        </td>
        <td class="main_table_cell_heading_active">
            <div class="main_table_heading">SSL Certs</div>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) {

        $new_ipid = $row->id;

        if ($current_ipid != $new_ipid) {
            $exclude_ip_address_string_raw .= "'" . $row->id . "', ";
        } ?>

        <tr class="main_table_row_active">
        <td class="main_table_cell_active">
            <?php //@formatter:off ?>
            <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php
                echo $row->name; ?></a><?php if ($_SESSION['default_ip_address_domains'] == $row->id) echo "<a
                title=\"Default Domain IP Address\"><div class=\"default_highlight\">*</div></a>";
            if ($_SESSION['default_ip_address_ssl'] == $row->id) echo "<a title=\"Default SSL IP Address\"><div
                class=\"default_highlight_secondary\">*</div></a>"; ?>
            <?php //@formatter:on ?>
        </td>
        <td class="main_table_cell_active">
            <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->ip; ?></a>
        </td>
        <td class="main_table_cell_active">
            <a class="invisiblelink"
               href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->rdns; ?></a>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_count
                                FROM domains
                                WHERE active NOT IN ('0', '10')
                                  AND ip_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_domains = $row_total_count->total_count;
            }

            if ($total_domains >= 1) { ?>

                <a class="nobold" href="../domains.php?ipid=<?php echo $row->id; ?>"><?php echo number_format
                ($total_domains); ?></a><?php

            } else {

                echo "-";

            } ?>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE active NOT IN ('0')
                                  AND ip_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($total_certs >= 1) { ?>

                <a class="nobold" href="../ssl-certs.php?sslipid=<?php echo $row->id; ?>"><?php echo number_format
                ($total_certs); ?></a><?php

            } else {

                echo "-";

            } ?>
        </td>
        </tr><?php

        $current_ipid = $row->id;

    }

    }

    if ($_SESSION['display_inactive_assets'] == "1") {

        $exclude_ip_address_string = substr($exclude_ip_address_string_raw, 0, -2);

        if ($exclude_ip_address_string == "") {

            $sql = "SELECT id, name, ip, rdns
                    FROM ip_addresses
                    ORDER BY name ASC, ip ASC";

        } else {

            $sql = "SELECT id, name, ip, rdns
                    FROM ip_addresses
                    WHERE id NOT IN (" . $exclude_ip_address_string . ")
                    ORDER BY name ASC, ip ASC";

        }

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            $has_inactive = "1";
            if ($has_active == "1") echo "<BR>";
            if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\"
            cellspacing=\"0\">"; ?>

            <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">Inactive IP Names (<?php echo mysqli_num_rows($result); ?>)</div>
            </td>
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">IP Address</div>
            </td>
            <td class="main_table_cell_heading_inactive">
                <div class="main_table_heading">rDNS</div>
            </td>
            </tr><?php

            while ($row = mysqli_fetch_object($result)) { ?>

                <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <?php //@formatter:off ?>
                    <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo
                        $row->name; ?></a><?php if ($_SESSION['default_ip_address_domains'] == $row->id) echo "<a
                        title=\"Default Domain IP Address\"><div class=\"default_highlight\">*</div></a>";
                    if ($_SESSION['default_ip_address_ssl'] == $row->id) echo "<a title=\"Default SSL IP Address\"><div
                        class=\"default_highlight_secondary\">*</div></a>"; ?>
                    <?php //@formatter:on ?>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink"
                       href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->ip; ?></a>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink"
                       href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->rdns; ?></a>
                </td>
                </tr><?php

            }

        }

    }

    if ($has_active == "1" || $has_inactive == "1") echo "</table>";

     //@formatter:off
     if ($_SESSION['display_inactive_assets'] != "1") { ?>
        <BR><em>Inactive IP Addresses are currently not displayed. <a class="invisiblelink"
            href="../settings/display/">Click here to display them</a>.</em><BR><?php
    } //@formatter:on

    if ($has_active || $has_inactive) { ?>
        <BR>
        <div class="default_highlight">*</div> = Default Domain Owner&nbsp;&nbsp;
        <div class="default_highlight_secondary">*</div> = Default SSL Owner<?php
    }

    if (!$has_active && !$has_inactive) { ?>
        <BR><BR>You don't currently have any IP Addresses. <a href="add/ip-address.php">Click here to add one</a>.<?php
    } ?>
    <?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
