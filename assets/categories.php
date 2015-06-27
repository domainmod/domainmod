<?php
/**
 * /assets/categories.php
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

$page_title = "Domain & SSL Categories";
$software_section = "categories";

$export_data = $_GET['export_data'];

$sql = "(SELECT c.id, c.name, c.stakeholder, c.notes, c.insert_time, c.update_time
         FROM categories AS c, domains AS d
         WHERE c.id = d.cat_id
           AND d.active NOT IN ('0', '10')
         GROUP BY c.name)
        UNION
        (SELECT c.id, c.name, c.stakeholder, c.notes, c.insert_time, c.update_time
         FROM categories AS c, ssl_certs AS sslc
         WHERE c.id = sslc.cat_id
           AND sslc.active NOT IN ('0')
         GROUP BY c.name)
        ORDER BY name";

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('category_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'Category',
        'Stakeholder',
        'Domains',
        'SSL Certs',
        'Default Domain Category?',
        'Default SSL Category?',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        $has_active = "1";

        while ($row = mysqli_fetch_object($result)) {

            $new_pcid = $row->id;

            if ($current_pcid != $new_pcid) {
                $exclude_category_string_raw .= "'" . $row->id . "', ";
            }

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM domains
                                WHERE active NOT IN ('0', '10')
                                  AND cat_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_domains = $row_total_count->total_count;
            }

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE active NOT IN ('0')
                                  AND cat_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($row->id == $_SESSION['default_category_domains']) {

                $is_default_domains = "1";

            } else {

                $is_default_domains = "";

            }

            if ($row->id == $_SESSION['default_category_ssl']) {

                $is_default_ssl = "1";

            } else {

                $is_default_ssl = "";

            }

            $row_contents = array(
                'Active',
                $row->name,
                $row->stakeholder,
                $total_domains,
                $total_certs,
                $is_default_domains,
                $is_default_ssl,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

            $current_pcid = $row->id;

        }

    }

    $exclude_category_string = substr($exclude_category_string_raw, 0, -2);

    if ($exclude_category_string == "") {

        $sql = "SELECT id, name, stakeholer, notes, insert_time, update_time
                FROM categories
                ORDER BY name ASC";

    } else {

        $sql = "SELECT id, name, stakeholder, notes, insert_time, update_time
                FROM categories
                WHERE id NOT IN (" . $exclude_category_string . ")
                ORDER BY name ASC";

    }

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";

        while ($row = mysqli_fetch_object($result)) {

            if ($row->id == $_SESSION['default_category_domains']) {

                $is_default_domains = "1";

            } else {

                $is_default_domains = "";

            }

            if ($row->id == $_SESSION['default_category_ssl']) {

                $is_default_ssl = "1";

            } else {

                $is_default_ssl = "";

            }

            $row_contents = array(
                'Inactive',
                $row->name,
                $row->stakeholder,
                '0',
                '0',
                $is_default_domains,
                $is_default_ssl,
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
Below is a list of all the Categories that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="categories.php?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) {

$has_active = "1"; ?>
<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Categories (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Stakeholder</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">SSL Certs</font>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) {

        $new_pcid = $row->id;

        if ($current_pcid != $new_pcid) {
            $exclude_category_string_raw .= "'" . $row->id . "', ";
        } ?>

        <tr class="main_table_row_active">
        <td class="main_table_cell_active">
            <a class="invisiblelink"
               href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_category_domains'] == $row->id) echo "<a title=\"Default Domain Category\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_category_ssl'] == $row->id) echo "<a title=\"Default SSL Category\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
        </td>
        <td class="main_table_cell_active">
            <a class="invisiblelink"
               href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->stakeholder; ?></a>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_count
                                    FROM domains
                                    WHERE active NOT IN ('0', '10')
                                      AND cat_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_domains = $row_total_count->total_count;
            }

            if ($total_domains >= 1) { ?>

                <a class="nobold"
                   href="../domains.php?pcid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php

            } else {

                echo "-";

            } ?>
        </td>
        <td class="main_table_cell_active"><?php
            $sql_total_count = "SELECT count(*) AS total_count
                                    FROM ssl_certs
                                    WHERE active NOT IN ('0')
                                      AND cat_id = '" . $row->id . "'";
            $result_total_count = mysqli_query($connection, $sql_total_count);
            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($total_certs >= 1) { ?>

                <a class="nobold"
                   href="../ssl-certs.php?sslpcid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php

            } else {

                echo "-";

            } ?>
        </td>
        </tr><?php

        $current_pcid = $row->id;

    }

    }

    if ($_SESSION['display_inactive_assets'] == "1") {

        $exclude_category_string = substr($exclude_category_string_raw, 0, -2);

        if ($exclude_category_string == "") {

            $sql = "SELECT id, name, stakeholder
                FROM categories
                ORDER BY name ASC";

        } else {

            $sql = "SELECT id, name, stakeholder
                FROM categories
                WHERE id NOT IN (" . $exclude_category_string . ")
                ORDER BY name ASC";

        }

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            $has_inactive = "1";
            if ($has_active == "1") echo "<BR>";
            if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

            <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <font class="main_table_heading">Inactive Categories (<?php echo mysqli_num_rows($result); ?>)</font>
            </td>
            <td class="main_table_cell_heading_inactive">
                <font class="main_table_heading">Stakeholder</font>
            </td>
            </tr><?php

            while ($row = mysqli_fetch_object($result)) { ?>

                <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink"
                       href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_category_domains'] == $row->id) echo "<a title=\"Default Domain Category\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_category_ssl'] == $row->id) echo "<a title=\"Default SSL Category\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="invisiblelink"
                       href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->stakeholder; ?></a>
                </td>
                </tr><?php

            }

        }

    }

    if ($has_active == "1" || $has_inactive == "1") echo "</table>";

    if ($_SESSION['display_inactive_assets'] != "1") { ?>
        <BR><em>Inactive Categories are currently not displayed. <a class="invisiblelink"
                                                                    href="../system/display-settings.php">Click here to
                display them</a>.</em><BR><?php
    }

    if ($has_active || $has_inactive) { ?>
        <BR><font class="default_highlight">*</font> = Default Domain Category&nbsp;&nbsp;<font
            class="default_highlight_secondary">*</font> = Default SSL Category<?php
    }

    if (!$has_active && !$has_inactive) { ?>
        <BR><BR>You don't currently have any Categories. <a href="add/category.php">Click here to add one</a>.<?php
    } ?>
    <?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
