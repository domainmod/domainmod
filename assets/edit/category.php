<?php
/**
 * /assets/edit/category.php
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
<?php
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/assets-edit-category.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$pcid = $_GET['pcid'];

$new_category = $_REQUEST['new_category'];
$new_stakeholder = $_REQUEST['new_stakeholder'];
$new_notes = $_REQUEST['new_notes'];
$new_pcid = $_REQUEST['new_pcid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_category != "") {

        $query = "UPDATE categories
                  SET `name` = ?,
                      stakeholder = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('ssssi', $new_category, $new_stakeholder, $new_notes, $timestamp, $new_pcid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $pcid = $new_pcid;

        $_SESSION['s_message_success'] .= "Category " . $new_category . " Updated<BR>";

        header("Location: ../categories.php");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= "Enter the category name<BR>";

    }

} else {

    $query = "SELECT `name`, stakeholder, notes
              FROM categories
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_category, $new_stakeholder, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

}

if ($del == "1") {

    $query = "SELECT cat_id
              FROM domains
              WHERE cat_id = ?
              LIMIT 1";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['s_message_danger'] .= "This Category has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Category?<BR><BR><a
                href=\"category.php?pcid=" . $pcid . "&really_del=1\">YES, REALLY DELETE THIS CATEGORY</a><BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

}

if ($really_del == "1") {

    $query = "DELETE FROM categories
              WHERE id = ?";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($dbcon, '1', 'ERROR');
    }

    $_SESSION['s_message_success'] .= "Category " . $new_category . " Deleted<BR>";

    header("Location: ../categories.php");
    exit;

}
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . 'layout/header.inc.php'); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_category', 'Category Name (150)', '', $new_category, '150', '', '1', '', '');
echo $form->showInputText('new_stakeholder', 'Stakeholder (100)', '', $new_stakeholder, '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_pcid', $pcid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="category.php?pcid=<?php echo urlencode($pcid); ?>&del=1">DELETE THIS CATEGORY</a>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
