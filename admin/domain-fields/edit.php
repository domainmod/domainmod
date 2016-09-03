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

$system->authCheck($web_root);
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$cdfid = $_GET['cdfid'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_cdfid = $_POST['new_cdfid'];
$new_notes = $_POST['new_notes'];

if ($new_cdfid == '') $new_cdfid = $cdfid;

$query = "SELECT id
          FROM domain_fields
          WHERE id = ?";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $cdfid);
    $q->execute();
    $q->store_result();

    if ($q->num_rows() == 0) {

        $_SESSION['s_message_danger'] .= "You're trying to edit an invalid Custom Domain Field<BR>";

        header("Location: ../domain-fields/");
        exit;

    }

    $q->close();

} else $error->outputSqlError($conn, "ERROR");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != '') {

    $query = "UPDATE domain_fields
              SET `name` = ?,
                  description = ?,
                  notes = ?,
                  update_time = ?
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $timestamp = $time->stamp();

        $q->bind_param('ssssi', $new_name, $new_description, $new_notes, $timestamp, $new_cdfid);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

    $query = "SELECT field_name
              FROM domain_fields
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $new_cdfid);
        $q->execute();
        $q->store_result();
        $q->bind_result($field_name);

        while ($q->fetch()) {

            $_SESSION['s_message_success'] .= 'Custom Domain Field ' . $new_name . ' (' . $field_name . ') Updated<BR>';

        }

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

    header("Location: ../domain-fields/");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == '') $_SESSION['s_message_danger'] .= 'Enter the display name<BR>';

    } else {

        $query = "SELECT f.name, f.field_name, f.description, f.notes, t.name
                  FROM domain_fields AS f, custom_field_types AS t
                  WHERE f.type_id = t.id
                    AND f.id = ?
                  ORDER BY f.name";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $cdfid);
            $q->execute();
            $q->store_result();
            $q->bind_result($name, $field_name, $description, $notes, $field_type);

            while ($q->fetch()) {

                $new_name = $name;
                $new_field_name = $field_name;
                $new_description = $description;
                $new_notes = $notes;
                $new_field_type = $field_type;

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    }

}

if ($del == '1') {

    $_SESSION['s_message_danger'] .= 'Are you sure you want to delete this Custom Domain Field?<BR><BR><a href="edit.php?cdfid=' . $cdfid . '&really_del=1">YES, REALLY DELETE THIS CUSTOM DOMAIN FIELD</a><BR>';

}

if ($really_del == '1') {

    if ($cdfid == '') {

        $_SESSION['s_message_danger'] .= 'The Custom Domain Field cannot be deleted<BR>';

    } else {

        $query = "SELECT `name`, field_name
                  FROM domain_fields
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $cdfid);
            $q->execute();
            $q->store_result();
            $q->bind_result($name, $field_name);

            while ($q->fetch()) {

                $temp_name = $name;
                $temp_field_name = $field_name;

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $query = "ALTER TABLE `domain_field_data`
                  DROP `?`";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('s', $temp_field_name);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $query = "DELETE FROM domain_fields
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $cdfid);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $_SESSION['s_message_success'] .= 'Custom Domain Field ' . $temp_name . ' (' . $temp_field_name . ') deleted<BR>';

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
echo $form->showInputText('new_name', 'Display Name (75)', '', $new_name, '75', '', '1', '', '');
?>
<strong>Database Field Name</strong><BR><?php echo $new_field_name; ?><BR><BR>
<strong>Data Type</strong><BR><?php echo $new_field_type; ?><BR><BR>
<?php
echo $form->showInputText('new_description', 'Description (255)', '', $new_description, '255', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_cdfid', $cdfid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="edit.php?cdfid=<?php echo urlencode($cdfid); ?>&del=1">DELETE THIS CUSTOM DOMAIN FIELD</a>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
</body>
</html>
