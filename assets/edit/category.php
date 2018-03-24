<?php
/**
 * /assets/edit/category.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$form = new DomainMOD\Form();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-edit-category.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

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

        $stmt = $pdo->prepare("
            UPDATE categories
            SET `name` = :new_category,
                stakeholder = :new_stakeholder,
                notes = :new_notes,
                update_time = :timestamp
            WHERE id = :new_pcid");
        $stmt->bindValue('new_category', $new_category, PDO::PARAM_STR);
        $stmt->bindValue('new_stakeholder', $new_stakeholder, PDO::PARAM_STR);
        $stmt->bindValue('new_notes', $new_notes, PDO::PARAM_LOB);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('new_pcid', $new_pcid, PDO::PARAM_INT);
        $stmt->execute();

        $pcid = $new_pcid;

        $_SESSION['s_message_success'] .= "Category " . $new_category . " Updated<BR>";

        header("Location: ../categories.php");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= "Enter the category name<BR>";

    }

} else {

    $stmt = $pdo->prepare("
        SELECT `name`, stakeholder, notes
        FROM categories
        WHERE id = :pcid");
    $stmt->bindValue('pcid', $pcid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $new_category = $result->name;
        $new_stakeholder = $result->stakeholder;
        $new_notes = $result->notes;

    }

}

if ($del == "1") {

    $stmt = $pdo->prepare("
        SELECT cat_id
        FROM domains
        WHERE cat_id = :pcid
        LIMIT 1");
    $stmt->bindValue('pcid', $pcid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result) {

        $_SESSION['s_message_danger'] .= "This Category has domains associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Category?<BR><BR><a
        href=\"category.php?pcid=" . $pcid . "&really_del=1\">YES, REALLY DELETE THIS CATEGORY</a><BR>";

    }

}

if ($really_del == "1") {

    $stmt = $pdo->prepare("
        DELETE FROM categories
        WHERE id = :pcid");
    $stmt->bindValue('pcid', $pcid, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['s_message_success'] .= "Category " . $new_category . " Deleted<BR>";

    header("Location: ../categories.php");
    exit;

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
echo $form->showInputText('new_category', 'Category Name (150)', '', $new_category, '150', '', '1', '', '');
echo $form->showInputText('new_stakeholder', 'Stakeholder (100)', '', $new_stakeholder, '100', '', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_pcid', $pcid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="category.php?pcid=<?php echo urlencode($pcid); ?>&del=1">DELETE THIS CATEGORY</a>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
