<?php
/**
 * /admin/ssl-fields/add.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$custom_field = new DomainMOD\CustomField();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-add-custom-ssl-field.inc.php';
require_once DIR_INC . '/database.inc.php';

$pdo = $system->db();
$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);

$new_name = $_POST['new_name'];
$new_field_name = $_POST['new_field_name'];
$new_description = $_POST['new_description'];
$new_field_type_id = $_POST['new_field_type_id'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != '' && $new_field_name != '' && $custom_field->checkFieldFormat($new_field_name)) {

    $stmt = $pdo->prepare("
        SELECT field_name
        FROM ssl_cert_fields
        WHERE field_name = :new_field_name
        LIMIT 1");
    $stmt->bindValue('new_field_name', $new_field_name, PDO::PARAM_STR);
    $stmt->execute();
    $result_main = $stmt->fetchColumn();

    if ($result_main) {

        $_SESSION['s_message_danger'] .= 'The Database Field Name you entered already exists<BR>';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO ssl_cert_fields
            (`name`, field_name, description, type_id, notes, created_by, insert_time)
            VALUES
            (:new_name, :new_field_name, :new_description, :new_field_type_id, :new_notes, :created_by, :timestamp)");
        $stmt->bindValue('new_name', $new_name, PDO::PARAM_STR);
        $stmt->bindValue('new_field_name', $new_field_name, PDO::PARAM_STR);
        $stmt->bindValue('new_description', $new_description, PDO::PARAM_STR);
        $stmt->bindValue('new_field_type_id', $new_field_type_id, PDO::PARAM_INT);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $stmt->bindValue('created_by', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->execute();

        if ($new_field_type_id == '1') { // Check Box

            $sql = "ALTER TABLE `ssl_cert_field_data`
                    ADD `" . $new_field_name . "` TINYINT(1) NOT NULL DEFAULT '0'";

        } elseif ($new_field_type_id == '2') { // Text

            $sql = "ALTER TABLE `ssl_cert_field_data`
                    ADD `" . $new_field_name . "` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";

        } elseif ($new_field_type_id == '3') { // Text Area

            $sql = "ALTER TABLE `ssl_cert_field_data`
                    ADD `" . $new_field_name . "` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";

        } elseif ($new_field_type_id == '4') { // Date

            $sql = "ALTER TABLE `ssl_cert_field_data`
                    ADD `" . $new_field_name . "` DATE NOT NULL DEFAULT '1978-01-23'";

        } elseif ($new_field_type_id == '5') { // Time Stamp

            $sql = "ALTER TABLE `ssl_cert_field_data`
                    ADD `" . $new_field_name . "` DATETIME NOT NULL DEFAULT '1978-01-23 00:00:00'";

        }

        $pdo->query($sql);

        $_SESSION['s_message_success'] .= 'Custom SSL Field ' . $new_name . ' (' . $new_field_name . ') Added<BR>';

        header("Location: ../ssl-fields/");
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_name == '') $_SESSION['s_message_danger'] .= 'Enter the Display Name<BR>';
        if (!$custom_field->checkFieldFormat($new_field_name)) $_SESSION['s_message_danger'] .= 'The Database Field Name format is incorrect<BR>';

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_name', 'Display Name (75)', '', $new_name, '75', '', '1', '', '');
echo $form->showInputText('new_field_name', 'Database Field Name (30)', 'The Database Field Name can contain only letters and underscores (ie. sample_field or SampleField).<BR><strong>WARNING:</strong> The Database Field Name cannot be renamed.', $new_field_name, '30', '', '1', '', '');

$result = $pdo->query("
    SELECT id, `name`
    FROM custom_field_types
    ORDER BY `name` ASC")->fetchAll();

if ($result) {

    echo $form->showDropdownTop('new_field_type_id', 'Data Type', '<strong>WARNING:</strong> The Data Type cannot be changed.', '', '');

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->id, $row->name, $new_field_type_id);

    }

    echo $form->showDropdownBottom('');

}

echo $form->showInputText('new_description', 'Description (255)', '', $new_description, '255', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showSubmitButton('Add Custom Field', '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
