<?php
/**
 * /admin/domain-fields/edit.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$log = new DomainMOD\Log('admin.domainfields.edit');

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/debug.inc.php');
require_once(DIR_INC . '/settings/admin-edit-custom-domain-field.inc.php');
require_once(DIR_INC . '/database.inc.php');

$pdo = $system->db();
$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$cdfid = $_GET['cdfid'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_cdfid = $_POST['new_cdfid'];
$new_notes = $_POST['new_notes'];

if ($new_cdfid == '') $new_cdfid = $cdfid;

$stmt = $pdo->prepare("
    SELECT id
    FROM domain_fields
    WHERE id = :cdfid");
$stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchColumn();

if (!$result) {

    $_SESSION['s_message_danger'] .= "The Custom Domain Field you're trying to edit is invalid<BR>";

    header("Location: ../domain-fields/");
    exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != '') {

    $stmt = $pdo->prepare("
        SELECT field_name
        FROM domain_fields
        WHERE id = :new_cdfid");
    $stmt->bindValue('new_cdfid', $new_cdfid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    if (!$result) {

        $log_message = 'Unable to retrieve Custom Domain Field ID';
        $log_extra = array('Custom Domain Field Name' => $new_name, 'Custom Domain Field ID' => $new_cdfid);
        $log->error($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            UPDATE domain_fields
            SET `name` = :new_name,
                description = :new_description,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_cdfid");
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_description', $new_description, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_cdfid', $new_cdfid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= 'Custom Domain Field ' . $new_name . ' (' . $result . ') Updated<BR>';

    }

    header("Location: ../domain-fields/");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == '') $_SESSION['s_message_danger'] .= 'Enter the display name<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT f.name, f.field_name, f.description, f.notes, t.name AS field_type
            FROM domain_fields AS f, custom_field_types AS t
            WHERE f.type_id = t.id
              AND f.id = :cdfid
            ORDER BY f.name");
        $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result) {

            $new_name = $result->name;
            $new_field_name = $result->field_name;
            $new_description = $result->description;
            $new_notes = $result->notes;
            $new_field_type = $result->field_type;

        }

    }

}

if ($del == '1') {

    $_SESSION['s_message_danger'] .= 'Are you sure you want to delete this Custom Domain Field?<BR><BR><a href="edit.php?cdfid=' . $cdfid . '&really_del=1">YES, REALLY DELETE THIS CUSTOM DOMAIN FIELD</a><BR>';

}

if ($really_del == '1') {

    if ($cdfid == '') {

        $_SESSION['s_message_danger'] .= 'The Custom Domain Field cannot be deleted<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT `name`, field_name
            FROM domain_fields
            WHERE id = :cdfid");
        $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if (!$result) {

            $log_message = 'Unable to delete Custom Domain Field';
            $log_extra = array('Custom Domain Field Name' => $new_name, 'Custom Domain Field ID' => $cdfid);
            $log->error($log_message, $log_extra);

            $_SESSION['s_message_danger'] .= $log_message . '<BR>';


        } else {

            $pdo->query("
                ALTER TABLE `domain_field_data`
                DROP `" . $result->field_name . "`");

            $stmt = $pdo->prepare("
                DELETE FROM domain_fields
                WHERE id = :cdfid");
            $stmt->bindValue('cdfid', $cdfid, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['s_message_success'] .= 'Custom Domain Field ' . $result->name . ' (' . $result->field_name . ') deleted<BR>';

            header("Location: ../domain-fields/");
            exit;

        }

    }

}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
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
<?php require_once(DIR_INC . '/layout/footer.inc.php'); //@formatter:on ?>
</body>
</html>
