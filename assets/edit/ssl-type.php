<?php
// /assets/edit/ssl-type.php
// 
// DomainMOD - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

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
				SET type = '" . mysql_real_escape_string($new_type) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_ssltid . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
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
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_type = $row->type;
		$new_notes = $row->notes;

	}

}
if ($del == "1") {

	$sql = "SELECT type_id
			FROM ssl_certs
			WHERE type_id = '" . $ssltid . "'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_cert = 1;
	}
	
	if ($existing_ssl_cert > 0) {

		$_SESSION['result_message'] = "This Type has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this SSL Type?<BR><BR><a href=\"$PHP_SELF?ssltid=$ssltid&really_del=1\">YES, REALLY DELETE THIS TYPE</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_cert_types
			WHERE id = '" . $ssltid . "'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Deleted<BR>";
	
	header("Location: ../ssl-types.php");
	exit;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<form name="edit_type_form" method="post" action="<?=$PHP_SELF?>">
<strong>Type Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_type" type="text" value="<?php if ($new_type != "") echo $new_type; ?>
" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<input type="hidden" name="new_ssltid" value="<?=$ssltid?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Type &raquo;">
</form>
<BR><BR><a href="<?=$PHP_SELF?>?ssltid=<?=$ssltid?>&del=1">DELETE THIS TYPE</a>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
