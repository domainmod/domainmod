<?php
/**
 * /admin/domain-fields/edit.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
<?php //@formatter:off
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/admin-edit-custom-domain-field.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$cdfid = $_GET['cdfid'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_cdfid = $_POST['new_cdfid'];
$new_notes = $_POST['new_notes'];

if ($new_cdfid == '') $new_cdfid = $cdfid;

$sql = "SELECT id
        FROM domain_fields
        WHERE id = '" . $cdfid . "'";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) == 0) {

    $_SESSION['s_message_danger'] .= 'You\'re trying to edit an invalid Custom Domain Field<BR>';

    header("Location: ../domain-fields/");
    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != '') {

    $sql = "UPDATE domain_fields
            SET name = '" . mysqli_real_escape_string($connection, $new_name) . "',
                description = '" . mysqli_real_escape_string($connection, $new_description) . "',
                notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
                update_time = '" . $time->stamp() . "'
            WHERE id = '" . $new_cdfid . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT field_name
            FROM domain_fields
            WHERE id = '" . $new_cdfid . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    while ($row = mysqli_fetch_object($result)) {
        $temp_field_name = $row->field_name;
    }

    $_SESSION['s_message_success'] .= 'Custom Domain Field ' . $new_name . ' (' . $temp_field_name . ') Updated<BR>';

    header("Location: ../domain-fields/");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == '') $_SESSION['s_message_danger'] .= 'Enter the display name<BR>';

    } else {

        $sql = "SELECT f.name, f.field_name, f.description, f.notes, f.insert_time, f.update_time, t.name AS type
                FROM domain_fields AS f, custom_field_types AS t
                WHERE f.type_id = t.id
                  AND f.id = '" . $cdfid . "'
                ORDER BY f.name";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {

            $new_name = $row->name;
            $new_field_name = $row->field_name;
            $new_description = $row->description;
            $new_field_type = $row->type;
            $new_notes = $row->notes;

        }

    }

}

if ($del == '1') {

    $_SESSION['s_message_danger'] = 'Are you sure you want to delete this Custom Domain Field?<BR><BR><a href=\"edit.php?cdfid=' . $cdfid . '&really_del=1\">YES, REALLY DELETE THIS CUSTOM DOMAIN FIELD</a><BR>';

}

if ($really_del == '1') {

    if ($cdfid == '') {

        $_SESSION['s_message_danger'] = 'The Custom Domain Field cannot be deleted<BR>';

    } else {

        $sql = "SELECT `name`, field_name
                FROM domain_fields
                WHERE id = '" . $cdfid . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {
            $temp_name = $row->name;
            $temp_field_name = $row->field_name;
        }

        $sql = "ALTER TABLE `domain_field_data`
                DROP `" . $temp_field_name . "`";
        $result = mysqli_query($connection, $sql);

        $sql = "DELETE FROM domain_fields
                WHERE id = '" . $cdfid . "'";
        $result = mysqli_query($connection, $sql);

        $_SESSION['s_message_success'] = 'Custom Domain Field ' . $temp_name . ' (' . $temp_field_name . ') Deleted<BR>';

        header("Location: ../domain-fields/");
        exit;

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'Display Name (75)', '', $new_name, '75', '', '', '');
?>
<strong>Database Field Name</strong><BR><?php echo $new_field_name; ?><BR><BR>
<strong>Data Type</strong><BR><?php echo $new_field_type; ?><BR><BR>
<?php
echo $form->showInputText('new_description', 'Description (255)', '', $new_description, '255', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '');
echo $form->showInputHidden('new_cdfid', $cdfid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="edit.php?cdfid=<?php echo $cdfid; ?>&del=1">DELETE THIS CUSTOM DOMAIN FIELD</a>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
</body>
</html>
