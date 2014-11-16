<?php
// /assets/add/registrar.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
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

$page_title = "Adding A New Registrar";
$software_section = "registrars-add";

// Form Variables
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

		$sql = "INSERT INTO registrars
				(name, url, notes, insert_time) VALUES 
				('" . mysql_real_escape_string($new_registrar) . "', '" . mysql_real_escape_string($new_url) . "', '" . mysql_real_escape_string($new_notes) . "', '" . $current_timestamp . "')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['result_message'] = "Registrar <font class=\"highlight\">" . $new_registrar . "</font> Added<BR>";

		if ($_SESSION['need_registrar'] == "1") {
			
			include("../../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
			header("Location: ../../domains.php");

		} else {

			header("Location: ../registrars.php");

		}
		exit;

	} else {
	
		if ($new_registrar == "") $_SESSION['result_message'] .= "Please enter the registrar name<BR>";
		if ($new_url == "") $_SESSION['result_message'] .= "Please enter the registrar's URL<BR>";

	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../../_includes/layout/header.inc.php"); ?>
<form name="add_registrar_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Registrar Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_registrar" type="text" value="<?php echo $new_registrar; ?>" size="50" maxlength="100">
<BR><BR>
<strong>Registrar's URL (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_url" type="text" value="<?php echo $new_url; ?>" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add This Registrar &raquo;">
</form>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
