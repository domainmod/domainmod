<?php
/**
 * /assets/edit/category.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

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

$page_title = "Editing A Category";
$software_section = "categories-edit";

// 'Delete Category' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$pcid = $_GET['pcid'];

// Form Variables
$new_category = $_REQUEST['new_category'];
$new_stakeholder = $_REQUEST['new_stakeholder'];
$new_notes = $_REQUEST['new_notes'];
$new_pcid = $_REQUEST['new_pcid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_category != "") {

        $query = "UPDATE categories
                  SET `name` = ?,
                      stakeholder = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssssi', $new_category, $new_stakeholder, $new_notes, $timestamp, $new_pcid);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $pcid = $new_pcid;
		
		$_SESSION['result_message'] = "Category <font class=\"highlight\">$new_category</font> Updated<BR>";

		header("Location: ../categories.php");
		exit;

	} else {
	
		$_SESSION['result_message'] = "Please enter the category name<BR>";

	}

} else {

    $query = "SELECT `name`, stakeholder, notes
              FROM categories
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_category, $new_stakeholder, $new_notes);
        $q->fetch();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}

if ($del == "1") {

    $query = "SELECT cat_id
              FROM domains
              WHERE cat_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['result_message'] = "This Category has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['result_message'] = "Are you sure you want to delete this Category?<BR><BR><a
                href=\"category.php?pcid=" . $pcid . "&really_del=1\">YES, REALLY DELETE THIS CATEGORY</a><BR>";

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}

if ($really_del == "1") {

    $query = "DELETE FROM categories
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $pcid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $_SESSION['result_message'] = "Category <font class=\"highlight\">$new_category</font> Deleted<BR>";
	
	header("Location: ../categories.php");
	exit;

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="edit_category_form" method="post">
<strong>Category Name (150)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_category" type="text" value="<?php if ($new_category != "") echo htmlentities($new_category); ?>
" size="50" maxlength="150">
<BR><BR>
<strong>Stakeholder (100)</strong><BR><BR>
<input name="new_stakeholder" type="text" value="<?php if ($new_stakeholder != "")
    echo htmlentities($new_stakeholder); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="hidden" name="new_pcid" value="<?php echo $pcid; ?>">
<input type="submit" name="button" value="Update This Category &raquo;">
</form>
<BR><BR><a href="category.php?pcid=<?php echo $pcid; ?>&del=1">DELETE THIS CATEGORY</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
