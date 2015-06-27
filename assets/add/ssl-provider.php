<?php
/**
 * /assets/add/ssl-provider.php
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

$page_title = "Adding A New SSL Provider";
$software_section = "ssl-providers-add";

// Form Variables
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_ssl_provider != "" && $new_url != "") {

        $query = "INSERT INTO ssl_providers
                  (`name`, url, notes, insert_time)
                  VALUES
                  (?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssss', $new_ssl_provider, $new_url, $new_notes, $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Added<BR>";

        if ($_SESSION['has_ssl_provider'] != '1') {

            $system->checkExistingAssets($connection);

            header("Location: ../../ssl-certs.php");

        } else {

            header("Location: ../ssl-providers.php");

        }
        exit;

    } else {

        if ($new_ssl_provider == "") $_SESSION['result_message'] .= "Please enter the SSL provider's name<BR>";
        if ($new_url == "") $_SESSION['result_message'] .= "Please enter the SSL provider's URL<BR>";

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()" ;>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_ssl_provider_form" method="post">
    <strong>SSL Provider Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
    <input name="new_ssl_provider" type="text" value="<?php echo $new_ssl_provider; ?>" size="50" maxlength="100">
    <BR><BR>
    <strong>SSL Provider's URL (100)</strong><a title="Required Field"><font
            class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
    <input name="new_url" type="text" value="<?php echo $new_url; ?>" size="50" maxlength="100">
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <input type="submit" name="button" value="Add This SSL Provider &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
