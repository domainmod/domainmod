<?php
/**
 * /assets/ssl-types.php
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

$page_title = "SSL Certificate Types";
$software_section = "ssl-types";

$export_data = $_GET['export_data'];

$sql = "SELECT id, type, notes, insert_time, update_time
        FROM ssl_cert_types
        WHERE id IN (SELECT type_id
                     FROM ssl_certs
                     WHERE type_id != '0'
                       AND active NOT IN ('0')
                     GROUP BY type_id)
        ORDER BY type ASC";

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_certificate_type_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'SSL Type',
        'SSL Certs',
        'Default SSL Type?',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        $has_active = "1";

        while ($row = mysqli_fetch_object($result)) {

            $new_ssltid = $row->id;

            if ($current_ssltid != $new_ssltid) {
                $exclude_ssl_type_string_raw .= "'" . $row->id . "', ";
            }

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE type_id = '$row->id'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $active_certs = $row_total_count->total_count;
            }

            if ($row->id == $_SESSION['default_ssl_type']) {

                $is_default = "1";

            } else {

                $is_default = "";

            }

            $row_contents = array(
                'Active2',
                $row->type,
                number_format($active_certs),
                $is_default,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

            $current_ssltid = $row->ssltid;

        }

    }

    $exclude_ssl_type_string = substr($exclude_ssl_type_string_raw, 0, -2);

    if ($exclude_ssl_type_string == "") {

        $sql = "SELECT id, type, notes, insert_time, update_time
                FROM ssl_cert_types
                ORDER BY type ASC";

    } else {

        $sql = "SELECT id, type, notes, insert_time, update_time
                FROM ssl_cert_types
                WHERE id NOT IN (" . $exclude_ssl_type_string . ")
                ORDER BY type ASC";

    }

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";

        while ($row = mysqli_fetch_object($result)) {

            if ($row->id == $_SESSION['default_ssl_type']) {

                $is_default = "1";

            } else {

                $is_default = "";

            }

            $row_contents = array(
                'Inactive',
                $row->type,
                '0',
                $is_default,
                $row->notes,
                $row->insert_time,
                $row->update_time
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
Below is a list of all the SSL Certificates Types that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="ssl-types.php?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) {

$has_active = "1"; ?>
<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active SSL Types (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Certs</font>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) {

        $new_ssltid = $row->id;

        if ($current_ssltid != $new_ssltid) {
            $exclude_ssl_type_string_raw .= "'" . $row->id . "', ";
        } ?>

        <tr class="main_table_row_active">
        <td class="main_table_cell_active">
            <a class="invisiblelink"
               href="edit/ssl-type.php?ssltid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a><?php if ($_SESSION['default_ssl_type'] == $row->id) echo "<a title=\"Default SSL Type\"><font class=\"default_highlight\">*</font></a>"; ?>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_count
                                    FROM ssl_certs
                                    WHERE type_id = '$row->id'
                                      AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $active_certs = $row_total_count->total_count;
            }

            if ($active_certs == "0") {

                echo number_format($active_certs);

            } else { ?>

                <a class="nobold"
                   href="../ssl-certs.php?ssltid=<?php echo $row->id; ?>"><?php echo number_format($active_certs); ?></a><?php

            } ?>
        </td>
        </tr><?php

        $current_ssltid = $row->ssltid;

    }

    }

    if ($_SESSION['display_inactive_assets'] == "1") {

        $exclude_ssl_type_string = substr($exclude_ssl_type_string_raw, 0, -2);

        if ($exclude_ssl_type_string == "") {

            $sql = "SELECT id, type
                FROM ssl_cert_types
                ORDER BY type ASC";

        } else {

            $sql = "SELECT id, type
                FROM ssl_cert_types
                WHERE id NOT IN (" . $exclude_ssl_type_string . ")
                ORDER BY type ASC";

        }

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            $has_inactive = "1";
            if ($has_active == "1") echo "<BR>";
            if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

            <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <font class="main_table_heading">Inactive SSL Types (<?php echo mysqli_num_rows($result); ?>)</font>
            </td>
            </tr><?php

            while ($row = mysqli_fetch_object($result)) { ?>

                <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink"
                       href="edit/ssl-type.php?ssltid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a><?php if ($_SESSION['default_ssl_type'] == $row->id) echo "<a title=\"Default SSL Type\"><font class=\"default_highlight\">*</font></a>"; ?>
                </td>
                </tr><?php

            }

        }

    }

    if ($has_active == "1" || $has_inactive == "1") echo "</table>";

    if ($_SESSION['display_inactive_assets'] != "1") { ?>
        <BR><em>Inactive SSL Types are currently not displayed. <a class="invisiblelink"
                                                                   href="../system/display-settings.php">Click here to
                display them</a>.</em><BR><?php
    }

    if ($has_active || $has_inactive) { ?>
        <BR><font class="default_highlight">*</font> = Default SSL Type<?php
    }

    if (!$has_active && !$has_inactive) { ?>
        <BR>You don't currently have any SSL Types. <a href="add/ssl-type.php">Click here to add one</a>.<?php
    } ?>
    <?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
