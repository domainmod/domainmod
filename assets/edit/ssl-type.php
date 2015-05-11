<?php
/**
 * /assets/edit/ssl-type.php
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
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = "Editing An SSL Type";
$software_section = "ssl-types-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$ssltid = $_GET['ssltid'];

$new_type = $_REQUEST['new_type'];
$new_notes = $_REQUEST['new_notes'];
$new_ssltid = $_REQUEST['new_ssltid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_type != "") {

		$sql = "UPDATE ssl_cert_types
				SET type = '" . mysqli_real_escape_string($connection, $new_type) . "',
					notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
					update_time = '" . $time->time() . "'
				WHERE id = '" . $new_ssltid . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$new_type = $new_type;
		$new_notes = $new_notes;

		$ssltid = $new_ssltid;
		
		$_SESSION['result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Updated<BR>";

		header("Location: ../ssl-types.php");
		exit;

	} else {
	
		$_SESSION['result_message'] = "Please enter the Type name<BR>";

	}

} else {

	$sql = "SELECT type, notes
			FROM ssl_cert_types
			WHERE id = '" . $ssltid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_type = $row->type;
		$new_notes = $row->notes;

	}

}
if ($del == "1") {

	$sql = "SELECT type_id
			FROM ssl_certs
			WHERE type_id = '" . $ssltid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_ssl_cert = 1;
	}
	
	if ($existing_ssl_cert > 0) {

		$_SESSION['result_message'] = "This Type has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this SSL Type?<BR><BR><a href=\"ssl-type.php?ssltid=$ssltid&really_del=1\">YES, REALLY DELETE THIS TYPE</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_cert_types
			WHERE id = '" . $ssltid . "'";
	$result = mysqli_query($connection, $sql);
	
	$_SESSION['result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Deleted<BR>";
	
	header("Location: ../ssl-types.php");
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
<form name="edit_type_form" method="post">
<strong>Type Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_type" type="text" value="<?php if ($new_type != "") echo htmlentities($new_type); ?>
" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_ssltid" value="<?php echo $ssltid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Type &raquo;">
</form>
<BR><BR><a href="ssl-type.php?ssltid=<?php echo $ssltid; ?>&del=1">DELETE THIS TYPE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
