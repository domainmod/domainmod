<?php
/**
 * /system/admin/add/domain-field.php
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
include("../../../_includes/start-session.inc.php");
include("../../../_includes/init.inc.php");

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
$system->checkAdminUser();

$page_title = "Adding A Custom Domain Field";
$software_section = "admin-domain-field-add";

$new_name = $_POST['new_name'];
$new_field_name = $_POST['new_field_name'];
$new_description = $_POST['new_description'];
$new_field_type_id = $_POST['new_field_type_id'];
$new_notes = $_POST['new_notes'];

$custom_field = new DomainMOD\CustomField();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != "" && $new_field_name != "" &&
    $custom_field->checkFieldFormat($new_field_name)) {

    $query = "SELECT field_name
              FROM domain_fields
              WHERE field_name = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('s', $new_field_name);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['result_message'] .= "The Database Field Name you entered already exists<BR>";

        } else {

            $query_i = "INSERT INTO domain_fields
                        (`name`, field_name, description, type_id, notes, insert_time)
                        VALUES
                        (?, ?, ?, ?, ?, ?)";
            $q_i = $conn->stmt_init();

            if ($q_i->prepare($query_i)) {

                $timestamp = $time->time();

                $q_i->bind_param('sssiss', $new_name, $new_field_name, $new_description, $new_field_type_id, $new_notes,
                    $timestamp);
                $q_i->execute();
                $q_i->close();

            } else { $error->outputSqlError($conn, "ERROR"); }

            if ($new_field_type_id == '1') { // Check Box

                $query = "ALTER TABLE `domain_field_data`
                          ADD `" . $new_field_name . "` INT(1) NOT NULL DEFAULT '0'";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {
                    $q->execute();
                    $q->close();

                } else { $error->outputSqlError($conn, "ERROR"); }

            } elseif ($new_field_type_id == '2') { // Text

                $query = "ALTER TABLE `domain_field_data`
                          ADD `" . $new_field_name . "` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT
                          NULL";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {
                    $q->execute();
                    $q->close();

                } else { $error->outputSqlError($conn, "ERROR"); }

            } elseif ($new_field_type_id == '3') { // Text Area

                $query = "ALTER TABLE `domain_field_data`
                          ADD `" . $new_field_name . "` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->execute();
                    $q->close();

                } else { $error->outputSqlError($conn, "ERROR"); }

            }

            $_SESSION['result_message'] .= "Custom Domain Field <font class=\"highlight\">" . $new_name . " (" .
                $new_field_name . ")</font> Added<BR>";

            header("Location: ../domain-fields.php");
            exit;

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == "") $_SESSION['result_message'] .= "Enter the Display Name<BR>";
        if (!$custom_field->checkFieldFormat($new_field_name)) $_SESSION['result_message'] .=
            "The Database Field Name format is incorrect<BR>";

    }

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_domain_field_form" method="post">
<strong>Display Name (75)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
    <input name="new_name" type="text" size="30" maxlength="75" value="<?php echo $new_name; ?>"><BR><BR>
<strong>Database Field Name (30)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
The Database Field Name can contain only letters and underscores (ie. sample_field or SampleField).<BR><BR>
<font class="default_highlight">WARNING:</font> The Database Field Name cannot be renamed.<BR><BR><input
        name="new_field_name" type="text" size="20" maxlength="30" value="<?php echo $new_field_name; ?>"><BR><BR>
<strong>Data Type</strong><BR><BR>
<font class="default_highlight">WARNING:</font> The Data Type cannot be changed.<BR><BR>
<?php
$query = "SELECT id, `name`
          FROM custom_field_types
          ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo "<select name=\"new_field_type_id\">";

    while ($q->fetch()) { ?>

        <option value="<?php echo $id; ?>"><?php echo $name; ?></option><?php

    }

    echo "</select>";

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<BR><BR>
<strong>Description (255)</strong><BR><BR><input name="new_description" type="text" size="50" maxlength="255"
    value="<?php echo $new_description; ?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add Custom Field &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
