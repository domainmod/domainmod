<?php
/**
 * /assets/add/ssl-type.php
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
<?php
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Adding A New SSL Type";
$software_section = "ssl-types-add";

// Form Variables
$new_type = $_POST['new_type'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_type != "") {

        $query = "INSERT INTO ssl_cert_types
                  (type, notes, insert_time)
                  VALUES
                  (?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('sss', $new_type, $new_notes, $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_result_message'] = "SSL Type <div class=\"highlight\">$new_type</div> Added<BR>";

        header("Location: ../ssl-types.php");
        exit;

    } else {

        $_SESSION['s_result_message'] .= "Please enter the SSL Type<BR>";

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_type_form" method="post">
    <strong>Type (100)</strong><a title="Required Field">
        <div
            class="default_highlight"><strong>*</strong></div>
    </a><BR>
    <BR>
    <input name="new_type" type="text" value="<?php echo $new_type; ?>" size="50" maxlength="100">
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <input type="submit" name="button" value="Add This SSL Type &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
